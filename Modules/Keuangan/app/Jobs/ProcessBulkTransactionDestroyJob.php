<?php

namespace Modules\Keuangan\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Keuangan\Models\Transaction;
use Modules\Keuangan\Models\Wallet;

class ProcessBulkTransactionDestroyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     *
     * @param  array<int>  $transactionIds
     */
    public function __construct(
        public array $transactionIds,
        public int $userId,
        public bool $isSuperadmin = false
    ) {}

    /**
     * Execute the job in the queue.
     */
    public function handle(): void
    {
        $query = Transaction::whereIn('id', $this->transactionIds)
            ->with(['wallet', 'targetWallet']);

        if (! $this->isSuperadmin) {
            $query->where('user_id', $this->userId);
        }

        $transactions = $query->get();

        if ($transactions->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($transactions) {
            // Group net delta per wallet to minimize lock contention
            $walletDeltas = [];

            foreach ($transactions as $transaction) {
                $walletId = $transaction->wallet_id;
                $targetWalletId = $transaction->target_wallet_id;
                $amount = (float) $transaction->amount;
                $type = $transaction->type;

                if (! isset($walletDeltas[$walletId])) {
                    $walletDeltas[$walletId] = 0.0;
                }

                if ($type === 'income') {
                    $walletDeltas[$walletId] -= $amount;
                } elseif ($type === 'expense') {
                    $walletDeltas[$walletId] += $amount;
                } elseif ($type === 'transfer') {
                    $walletDeltas[$walletId] += $amount;
                    if ($targetWalletId) {
                        if (! isset($walletDeltas[$targetWalletId])) {
                            $walletDeltas[$targetWalletId] = 0.0;
                        }
                        $walletDeltas[$targetWalletId] -= $amount;
                    }
                }

                $transaction->delete();
            }

            // Apply delta to each locked wallet atomically
            foreach ($walletDeltas as $wId => $delta) {
                $wallet = Wallet::where('id', $wId)->lockForUpdate()->first();
                if ($wallet && $delta != 0.0) {
                    if ($delta > 0) {
                        $wallet->increment('current_balance', $delta);
                    } else {
                        $wallet->decrement('current_balance', abs($delta));
                    }
                }
            }
        });

        // Invalidate dashboard analytics cache for the user
        Cache::forget("keuangan_dashboard_analytics_{$this->userId}");
    }
}
