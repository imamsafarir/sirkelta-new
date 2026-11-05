<?php

namespace Modules\Keuangan\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Keuangan\Jobs\ProcessBulkTransactionDestroyJob;
use Modules\Keuangan\Models\Transaction;
use Modules\Keuangan\Models\Wallet;
use Modules\Keuangan\Models\WalletShare;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    /**
     * Display a listing of user transactions, including shared wallets.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // 1. Get all accessible active wallet IDs (owned + accepted & active shared)
        $ownedWalletIds = $user->wallets()->pluck('id');
        $sharedActiveWalletIds = WalletShare::where('user_id', $user->id)
            ->where('status', 'accepted')
            ->where('is_active', true)
            ->pluck('wallet_id');
        $allAccessibleWalletIds = $ownedWalletIds->merge($sharedActiveWalletIds)->unique();

        $query = Transaction::where(function ($q) use ($user, $allAccessibleWalletIds) {
            $q->where('user_id', $user->id)
                ->orWhereIn('wallet_id', $allAccessibleWalletIds)
                ->orWhereIn('target_wallet_id', $allAccessibleWalletIds);
        })->with([
            'wallet.user:id,name,username',
            'targetWallet.user:id,name,username',
            'user:id,name,username',
        ]);

        if ($request->filled('wallet_id')) {
            $walletId = (int) $request->wallet_id;
            $query->where(function ($q) use ($walletId) {
                $q->where('wallet_id', $walletId)
                    ->orWhere('target_wallet_id', $walletId);
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('date_from')) {
            $query->where('transaction_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('transaction_date', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('party_name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $perPageNumber = ($perPage === 'all' || $perPage === 'Semua') ? 999999 : max(5, (int) $perPage);

        $transactions = $query->latest('transaction_date')
            ->latest('id')
            ->paginate($perPageNumber)
            ->withQueryString();

        // Prepare wallets for filters
        $ownedWallets = $user->wallets()->get(['id', 'name', 'current_balance', 'user_id'])->map(function ($w) use ($user) {
            return [
                'id' => $w->id,
                'name' => $w->name,
                'current_balance' => (float) $w->current_balance,
                'is_shared' => false,
                'role' => 'owner',
                'owner_name' => $user->name,
                'owner_username' => $user->username,
            ];
        });

        $sharedWallets = Wallet::whereIn('id', $sharedActiveWalletIds)
            ->with('user:id,name,username')
            ->get(['id', 'name', 'current_balance', 'user_id'])
            ->map(function ($w) use ($user) {
                $share = WalletShare::where('wallet_id', $w->id)->where('user_id', $user->id)->first();

                return [
                    'id' => $w->id,
                    'name' => $w->name,
                    'current_balance' => (float) $w->current_balance,
                    'is_shared' => true,
                    'role' => $share ? $share->role : 'view',
                    'owner_name' => $w->user?->name,
                    'owner_username' => $w->user?->username,
                ];
            });

        $wallets = $ownedWallets->merge($sharedWallets)->values();

        return Inertia::render('Keuangan::Transactions/Index', [
            'transactions' => $transactions,
            'wallets' => $wallets,
            'filters' => $request->only(['wallet_id', 'type', 'search', 'date_from', 'date_to', 'per_page']),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'accessible_modules' => $user->accessibleModules(),
            ],
        ]);
    }

    /**
     * Store a newly created transaction in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($request->has('amount') && is_string($request->amount)) {
            $request->merge([
                'amount' => str_replace(',', '.', $request->amount),
            ]);
        }

        $validated = $request->validate([
            'wallet_id' => ['required', 'integer'],
            'target_wallet_id' => [
                'nullable',
                'required_if:type,transfer',
                'different:wallet_id',
            ],
            'type' => ['required', 'in:income,expense,debt_payable,debt_receivable,transfer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category' => ['nullable', 'string', 'max:100'],
            'party_name' => ['nullable', 'required_if:type,debt_payable,debt_receivable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'debt_status' => ['nullable', 'in:unpaid,partially_paid,paid'],
            'description' => ['nullable', 'string', 'max:500'],
            'transaction_date' => ['required', 'date'],
        ], [
            'wallet_id.required' => 'Dompet sumber wajib dipilih.',
            'target_wallet_id.required_if' => 'Dompet tujuan wajib dipilih untuk transaksi transfer.',
            'target_wallet_id.different' => 'Dompet tujuan tidak boleh sama dengan dompet sumber.',
            'type.required' => 'Jenis transaksi wajib dipilih.',
            'type.in' => 'Jenis transaksi tidak valid.',
            'amount.required' => 'Nominal transaksi wajib diisi.',
            'amount.numeric' => 'Nominal transaksi harus berupa angka.',
            'amount.min' => 'Nominal transaksi minimal Rp 1.',
            'party_name.required_if' => 'Nama pihak terkait (debitur/kreditur) wajib diisi untuk catatan utang/piutang.',
            'due_date.date' => 'Format tanggal jatuh tempo tidak valid.',
            'transaction_date.required' => 'Tanggal transaksi wajib diisi.',
            'transaction_date.date' => 'Format tanggal transaksi tidak valid.',
        ]);

        $wallet = Wallet::find($validated['wallet_id']);
        if (! $wallet || ! $wallet->canUserEdit($user)) {
            return back()->withErrors(['wallet_id' => 'Dompet tidak ditemukan atau Anda tidak memiliki izin edit untuk mencatat transaksi di dompet ini.']);
        }

        // If type is transfer, only wallet owner can execute transfers
        if ($validated['type'] === 'transfer') {
            if (! $wallet->isOwner($user) && ! $user->hasRole('superadmin')) {
                return back()->withErrors(['type' => 'Fitur transfer antar dompet hanya dapat dilakukan oleh pemilik dompet.']);
            }

            $targetWallet = Wallet::where('id', $validated['target_wallet_id'])
                ->where('user_id', $user->id)
                ->first();

            if (! $targetWallet) {
                return back()->withErrors(['target_wallet_id' => 'Dompet tujuan transfer tidak ditemukan pada akun Anda.']);
            }
        }

        DB::transaction(function () use ($user, $wallet, $validated) {
            $amount = (float) $validated['amount'];
            $type = $validated['type'];

            $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            if ($type === 'income') {
                $lockedWallet->increment('current_balance', $amount);
            } elseif ($type === 'expense') {
                $lockedWallet->decrement('current_balance', $amount);
            } elseif ($type === 'transfer') {
                $targetWallet = Wallet::where('id', $validated['target_wallet_id'])
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedWallet->decrement('current_balance', $amount);
                $targetWallet->increment('current_balance', $amount);
            }

            Transaction::create([
                'user_id' => $user->id,
                'wallet_id' => $lockedWallet->id,
                'target_wallet_id' => $validated['target_wallet_id'] ?? null,
                'type' => $type,
                'amount' => $amount,
                'category' => $validated['category'] ?? null,
                'party_name' => $validated['party_name'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'debt_status' => in_array($type, ['debt_payable', 'debt_receivable'])
                    ? ($validated['debt_status'] ?? 'unpaid')
                    : null,
                'description' => $validated['description'] ?? null,
                'transaction_date' => $validated['transaction_date'],
            ]);

            Cache::forget("keuangan_dashboard_analytics_{$user->id}");
        });

        return back()->with('success', 'Transaksi berhasil dibukukan.');
    }

    /**
     * Update the specified transaction.
     * Note: Transaction recorded by user A can ONLY be edited by user A.
     */
    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $user = $request->user();

        // Enforce: only creator can edit (or superadmin)
        if ($transaction->user_id !== $user->id && ! $user->hasRole('superadmin')) {
            abort(403, 'Catatan transaksi ini dibuat oleh pengguna lain dan tidak dapat diubah oleh Anda.');
        }

        if ($request->has('amount') && is_string($request->amount)) {
            $request->merge([
                'amount' => str_replace(',', '.', $request->amount),
            ]);
        }

        $validated = $request->validate([
            'wallet_id' => ['required', 'integer'],
            'target_wallet_id' => [
                'nullable',
                'required_if:type,transfer',
                'different:wallet_id',
            ],
            'type' => ['required', 'in:income,expense,debt_payable,debt_receivable,transfer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category' => ['nullable', 'string', 'max:100'],
            'party_name' => ['nullable', 'required_if:type,debt_payable,debt_receivable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'debt_status' => ['nullable', 'in:unpaid,partially_paid,paid'],
            'description' => ['nullable', 'string', 'max:500'],
            'transaction_date' => ['required', 'date'],
        ], [
            'wallet_id.required' => 'Dompet sumber wajib dipilih.',
            'target_wallet_id.required_if' => 'Dompet tujuan wajib dipilih untuk transaksi transfer.',
            'target_wallet_id.different' => 'Dompet tujuan tidak boleh sama dengan dompet sumber.',
            'type.required' => 'Jenis transaksi wajib dipilih.',
            'type.in' => 'Jenis transaksi tidak valid.',
            'amount.required' => 'Nominal transaksi wajib diisi.',
            'amount.numeric' => 'Nominal transaksi harus berupa angka.',
            'amount.min' => 'Nominal transaksi minimal Rp 1.',
            'party_name.required_if' => 'Nama pihak terkait (debitur/kreditur) wajib diisi untuk catatan utang/piutang.',
            'due_date.date' => 'Format tanggal jatuh tempo tidak valid.',
            'transaction_date.required' => 'Tanggal transaksi wajib diisi.',
            'transaction_date.date' => 'Format tanggal transaksi tidak valid.',
        ]);

        $newWallet = Wallet::find($validated['wallet_id']);
        if (! $newWallet || ! $newWallet->canUserEdit($user)) {
            return back()->withErrors(['wallet_id' => 'Dompet tidak ditemukan atau Anda tidak memiliki izin edit di dompet ini.']);
        }

        if ($validated['type'] === 'transfer') {
            if (! $newWallet->isOwner($user) && ! $user->hasRole('superadmin')) {
                return back()->withErrors(['type' => 'Fitur transfer antar dompet hanya dapat dilakukan oleh pemilik dompet.']);
            }

            $targetWallet = Wallet::where('id', $validated['target_wallet_id'])
                ->where('user_id', $user->id)
                ->first();

            if (! $targetWallet) {
                return back()->withErrors(['target_wallet_id' => 'Dompet tujuan transfer tidak ditemukan pada akun Anda.']);
            }
        }

        DB::transaction(function () use ($transaction, $user, $newWallet, $validated) {
            $targetWalletId = $validated['target_wallet_id'] ?? null;

            // Collect and sort all wallet IDs to lock in deterministic order to prevent deadlocks
            $walletIdsToLock = collect([
                $transaction->wallet_id,
                $transaction->target_wallet_id,
                $newWallet->id,
                $targetWalletId,
            ])->filter()->unique()->sort()->values();

            $lockedWallets = Wallet::whereIn('id', $walletIdsToLock)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // 1. Revert old transaction effect
            $oldWallet = $lockedWallets->get($transaction->wallet_id);
            $oldAmount = (float) $transaction->amount;
            $oldType = $transaction->type;

            if ($oldWallet) {
                if ($oldType === 'income') {
                    $oldWallet->decrement('current_balance', $oldAmount);
                } elseif ($oldType === 'expense') {
                    $oldWallet->increment('current_balance', $oldAmount);
                } elseif ($oldType === 'transfer') {
                    $oldWallet->increment('current_balance', $oldAmount);
                    if ($transaction->target_wallet_id && $oldTarget = $lockedWallets->get($transaction->target_wallet_id)) {
                        $oldTarget->decrement('current_balance', $oldAmount);
                    }
                }
            }

            // 2. Apply new transaction effect
            $newAmount = (float) $validated['amount'];
            $newType = $validated['type'];
            $activeNewWallet = $lockedWallets->get($newWallet->id);

            if ($activeNewWallet) {
                if ($newType === 'income') {
                    $activeNewWallet->increment('current_balance', $newAmount);
                } elseif ($newType === 'expense') {
                    $activeNewWallet->decrement('current_balance', $newAmount);
                } elseif ($newType === 'transfer') {
                    $targetWallet = $targetWalletId ? $lockedWallets->get($targetWalletId) : null;
                    if ($targetWallet) {
                        $activeNewWallet->decrement('current_balance', $newAmount);
                        $targetWallet->increment('current_balance', $newAmount);
                    }
                }
            }

            // 3. Update transaction record
            $transaction->update([
                'wallet_id' => $newWallet->id,
                'target_wallet_id' => $targetWalletId,
                'type' => $newType,
                'amount' => $newAmount,
                'category' => $validated['category'] ?? null,
                'party_name' => $validated['party_name'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'debt_status' => in_array($newType, ['debt_payable', 'debt_receivable'])
                    ? ($validated['debt_status'] ?? 'unpaid')
                    : null,
                'description' => $validated['description'] ?? null,
                'transaction_date' => $validated['transaction_date'],
            ]);

            Cache::forget("keuangan_dashboard_analytics_{$user->id}");
        });

        return back()->with('success', 'Transaksi berhasil diperbarui dan saldo buku kas direkonsiliasi.');
    }

    /**
     * Remove the specified transaction from storage.
     * Note: Transaction recorded by user A can ONLY be deleted by user A.
     */
    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        $user = $request->user();

        if ($transaction->user_id !== $user->id && ! $user->hasRole('superadmin')) {
            abort(403, 'Catatan transaksi ini dibuat oleh pengguna lain dan tidak dapat dihapus oleh Anda.');
        }

        DB::transaction(function () use ($transaction, $user) {
            $walletIdsToLock = collect([
                $transaction->wallet_id,
                $transaction->target_wallet_id,
            ])->filter()->unique()->sort()->values();

            $lockedWallets = Wallet::whereIn('id', $walletIdsToLock)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $wallet = $lockedWallets->get($transaction->wallet_id);
            $amount = (float) $transaction->amount;
            $type = $transaction->type;

            if ($wallet) {
                if ($type === 'income') {
                    $wallet->decrement('current_balance', $amount);
                } elseif ($type === 'expense') {
                    $wallet->increment('current_balance', $amount);
                } elseif ($type === 'transfer') {
                    $wallet->increment('current_balance', $amount);
                    if ($transaction->target_wallet_id && $targetWallet = $lockedWallets->get($transaction->target_wallet_id)) {
                        $targetWallet->decrement('current_balance', $amount);
                    }
                }
            }

            $transaction->delete();

            Cache::forget("keuangan_dashboard_analytics_{$user->id}");
        });

        return back()->with('success', 'Transaksi berhasil dihapus dan saldo buku kas direkonsiliasi.');
    }

    /**
     * Remove multiple transactions in bulk.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ]);

        $user = $request->user();

        // Only delete transactions created by current user (or allow superadmin)
        $query = Transaction::whereIn('id', $validated['ids'])
            ->with(['wallet', 'targetWallet']);

        if (! $user->hasRole('superadmin')) {
            $query->where('user_id', $user->id);
        }

        $transactions = $query->get();

        if ($transactions->isEmpty()) {
            return back()->with('error', 'Tidak ada transaksi milik Anda yang dapat dihapus.');
        }

        $count = $transactions->count();

        // If batch is large (> 50 transactions), dispatch to queue for background processing
        if ($count > 50) {
            ProcessBulkTransactionDestroyJob::dispatch($validated['ids'], $user->id, $user->hasRole('superadmin'));

            return back()->with('success', "Penghapusan massal {$count} transaksi telah dimasukkan ke dalam antrean sistem dan sedang diproses di latar belakang.");
        }

        DB::transaction(function () use ($transactions, $user) {
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
            }

            $walletIds = array_keys($walletDeltas);
            sort($walletIds);

            $lockedWallets = Wallet::whereIn('id', $walletIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($walletDeltas as $wId => $delta) {
                if ($lockedWallet = $lockedWallets->get($wId)) {
                    if ($delta > 0) {
                        $lockedWallet->increment('current_balance', $delta);
                    } elseif ($delta < 0) {
                        $lockedWallet->decrement('current_balance', abs($delta));
                    }
                }
            }

            Transaction::whereIn('id', $transactions->pluck('id'))->delete();

            Cache::forget("keuangan_dashboard_analytics_{$user->id}");
        });

        return back()->with('success', "{$count} transaksi milik Anda berhasil dihapus dan saldo buku kas telah disesuaikan.");
    }

    /**
     * Export transactions matching current filter to CSV with UTF-8 BOM encoding.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $user = $request->user();

        $ownedWalletIds = $user->wallets()->pluck('id');
        $sharedActiveWalletIds = WalletShare::where('user_id', $user->id)
            ->where('status', 'accepted')
            ->where('is_active', true)
            ->pluck('wallet_id');
        $allAccessibleWalletIds = $ownedWalletIds->merge($sharedActiveWalletIds)->unique();

        $query = Transaction::where(function ($q) use ($user, $allAccessibleWalletIds) {
            $q->where('user_id', $user->id)
                ->orWhereIn('wallet_id', $allAccessibleWalletIds)
                ->orWhereIn('target_wallet_id', $allAccessibleWalletIds);
        })->with([
            'wallet.user:id,name,username',
            'targetWallet.user:id,name,username',
            'user:id,name,username',
        ]);

        if ($request->filled('wallet_id')) {
            $walletId = (int) $request->wallet_id;
            $query->where(function ($q) use ($walletId) {
                $q->where('wallet_id', $walletId)
                    ->orWhere('target_wallet_id', $walletId);
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('date_from')) {
            $query->where('transaction_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('transaction_date', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('party_name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $transactions = $query->latest('transaction_date')->latest('id')->get();

        $filename = 'mutasi-transaksi-keuangan-'.date('Ymd-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, ['ID', 'Tanggal', 'Jenis', 'Nominal (IDR)', 'Dompet', 'Pemilik Dompet', 'Dicatat Oleh', 'Tujuan Transfer', 'Kategori', 'Pihak Terkait', 'Jatuh Tempo', 'Status Utang', 'Keterangan']);

            foreach ($transactions as $tx) {
                fputcsv($handle, [
                    $tx->id,
                    $tx->transaction_date ? $tx->transaction_date->format('Y-m-d') : '-',
                    strtoupper($tx->type),
                    $tx->amount,
                    $tx->wallet?->name ?? '-',
                    $tx->wallet?->user?->username ? '@'.$tx->wallet->user->username : '-',
                    $tx->user?->username ? '@'.$tx->user->username : ($tx->user?->name ?? '-'),
                    $tx->targetWallet?->name ?? '-',
                    $tx->category ?? '-',
                    $tx->party_name ?? '-',
                    $tx->due_date ? $tx->due_date->format('Y-m-d') : '-',
                    $tx->debt_status ?? '-',
                    $tx->description ?? '-',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
