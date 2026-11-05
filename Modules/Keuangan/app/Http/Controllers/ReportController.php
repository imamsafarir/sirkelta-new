<?php

namespace Modules\Keuangan\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Keuangan\Models\Transaction;
use Modules\Keuangan\Models\WalletShare;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Display financial cash flow report and analytics.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $period = $request->get('period', 'this_month');
        $now = now();

        $accessibleWalletIds = $user->wallets()->pluck('id')->merge(
            WalletShare::where('user_id', $user->id)
                ->where('status', 'accepted')
                ->where('is_active', true)
                ->pluck('wallet_id')
        )->unique();

        $query = Transaction::where(function ($q) use ($user, $accessibleWalletIds) {
            $q->where('user_id', $user->id)
                ->orWhereIn('wallet_id', $accessibleWalletIds)
                ->orWhereIn('target_wallet_id', $accessibleWalletIds);
        })
            ->with(['wallet.user:id,name,username', 'targetWallet.user:id,name,username', 'user:id,name,username'])
            ->orderBy('transaction_date');

        if ($period === 'this_month') {
            $query->whereBetween('transaction_date', [
                $now->copy()->startOfMonth()->toDateString(),
                $now->copy()->endOfMonth()->toDateString(),
            ]);
        } elseif ($period === 'last_month') {
            $lastMonth = $now->copy()->subMonth();
            $query->whereBetween('transaction_date', [
                $lastMonth->copy()->startOfMonth()->toDateString(),
                $lastMonth->copy()->endOfMonth()->toDateString(),
            ]);
        } elseif ($period === 'this_year') {
            $query->whereBetween('transaction_date', [
                $now->copy()->startOfYear()->toDateString(),
                $now->copy()->endOfYear()->toDateString(),
            ]);
        } elseif ($period === 'custom') {
            if ($request->filled('date_from')) {
                $query->where('transaction_date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->where('transaction_date', '<=', $request->date_to);
            }
        }

        $transactions = $query->get();

        $totalIncome = (float) $transactions->where('type', 'income')->sum('amount');
        $totalExpense = (float) $transactions->where('type', 'expense')->sum('amount');
        $totalPiutang = (float) $transactions->where('type', 'debt_receivable')->where('debt_status', '!=', 'paid')->sum('amount');
        $totalUtang = (float) $transactions->where('type', 'debt_payable')->where('debt_status', '!=', 'paid')->sum('amount');

        // Breakdown per category
        $expenseByCategory = $transactions->where('type', 'expense')
            ->groupBy(fn ($item) => $item->category ?: 'Lain-lain')
            ->map(fn ($group) => (float) $group->sum('amount'))
            ->sortDesc();

        $incomeByCategory = $transactions->where('type', 'income')
            ->groupBy(fn ($item) => $item->category ?: 'Lain-lain')
            ->map(fn ($group) => (float) $group->sum('amount'))
            ->sortDesc();

        $wallets = $user->wallets()->get(['id', 'name', 'current_balance']);

        return Inertia::render('Keuangan::Reports/Index', [
            'summary' => [
                'totalIncome' => $totalIncome,
                'totalExpense' => $totalExpense,
                'netFlow' => $totalIncome - $totalExpense,
                'totalPiutang' => $totalPiutang,
                'totalUtang' => $totalUtang,
            ],
            'expenseByCategory' => $expenseByCategory,
            'incomeByCategory' => $incomeByCategory,
            'wallets' => $wallets,
            'currentPeriod' => $period,
            'filters' => $request->only(['period', 'date_from', 'date_to']),
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'accessible_modules' => $user->accessibleModules(),
            ],
        ]);
    }

    /**
     * Export all transactions to CSV with UTF-8 BOM encoding.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $period = $request->get('period', 'all');
        $now = now();

        $accessibleWalletIds = $user->wallets()->pluck('id')->merge(
            WalletShare::where('user_id', $user->id)
                ->where('status', 'accepted')
                ->where('is_active', true)
                ->pluck('wallet_id')
        )->unique();

        $query = Transaction::where(function ($q) use ($user, $accessibleWalletIds) {
            $q->where('user_id', $user->id)
                ->orWhereIn('wallet_id', $accessibleWalletIds)
                ->orWhereIn('target_wallet_id', $accessibleWalletIds);
        })
            ->with(['wallet.user:id,name,username', 'targetWallet.user:id,name,username', 'user:id,name,username'])
            ->latest('transaction_date');

        if ($period === 'this_month') {
            $query->whereBetween('transaction_date', [
                $now->copy()->startOfMonth()->toDateString(),
                $now->copy()->endOfMonth()->toDateString(),
            ]);
        } elseif ($period === 'last_month') {
            $lastMonth = $now->copy()->subMonth();
            $query->whereBetween('transaction_date', [
                $lastMonth->copy()->startOfMonth()->toDateString(),
                $lastMonth->copy()->endOfMonth()->toDateString(),
            ]);
        } elseif ($period === 'this_year') {
            $query->whereBetween('transaction_date', [
                $now->copy()->startOfYear()->toDateString(),
                $now->copy()->endOfYear()->toDateString(),
            ]);
        } elseif ($period === 'custom') {
            if ($request->filled('date_from')) {
                $query->where('transaction_date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->where('transaction_date', '<=', $request->date_to);
            }
        }

        $transactions = $query->get();

        $filename = 'buku-kas-keuangan-sirkelta-'.date('Ymd-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel compatibility
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
