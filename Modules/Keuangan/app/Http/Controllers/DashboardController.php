<?php

namespace Modules\Keuangan\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Keuangan\Models\Transaction;
use Modules\Keuangan\Models\WalletShare;

class DashboardController extends Controller
{
    /**
     * Display the Keuangan module dashboard.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($user && $user->last_module !== 'keuangan') {
            $user->forceFill(['last_module' => 'keuangan'])->saveQuietly();
        }

        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth()->toDateString();
        $endOfMonth = $now->copy()->endOfMonth()->toDateString();

        // 1. Wallets & Balances (Owned + Accepted Active Shared Wallets)
        $ownedWallets = $user->wallets()->with('user:id,name,username')->orderBy('id')->get()->map(function ($w) use ($user) {
            $w->is_shared = false;
            $w->role = 'owner';
            $w->owner_username = $user->username;

            return $w;
        });

        $sharedActiveShares = WalletShare::where('user_id', $user->id)
            ->where('status', 'accepted')
            ->where('is_active', true)
            ->with(['wallet.user:id,name,username'])
            ->get();

        $sharedWallets = $sharedActiveShares->map(function ($share) {
            $w = $share->wallet;
            if ($w) {
                $w->is_shared = true;
                $w->role = $share->role;
                $w->owner_username = $w->user?->username ?? 'user';
            }

            return $w;
        })->filter();

        $wallets = $ownedWallets->concat($sharedWallets)->values();
        $allAccessibleWalletIds = $wallets->pluck('id');

        $totalWalletBalance = (float) $wallets->sum('current_balance');
        $kasSiapPakai = (float) $wallets->where('category_label', '!=', 'INVESTASI')->sum('current_balance');

        // 2. Active Debts & Receivables
        $activeReceivablesQuery = $user->transactions()
            ->where('type', 'debt_receivable')
            ->where('debt_status', '!=', 'paid');
        $totalPiutang = (float) (clone $activeReceivablesQuery)->sum('amount');
        $receivablesList = (clone $activeReceivablesQuery)->orderBy('due_date')->take(5)->get()->map(function ($r) use ($now) {
            $dueCarbon = $r->due_date ? Carbon::parse($r->due_date) : null;
            $r->is_overdue = $dueCarbon ? $dueCarbon->startOfDay()->lt($now->copy()->startOfDay()) : false;
            $r->is_due_today = $dueCarbon ? $dueCarbon->isSameDay($now) : false;
            $r->days_diff = $dueCarbon ? (int) $now->copy()->startOfDay()->diffInDays($dueCarbon->copy()->startOfDay(), false) : null;
            $r->due_date_formatted = $dueCarbon ? $dueCarbon->locale('id')->translatedFormat('d M Y') : '-';

            return $r;
        });

        $activePayablesQuery = $user->transactions()
            ->where('type', 'debt_payable')
            ->where('debt_status', '!=', 'paid');
        $totalUtang = (float) (clone $activePayablesQuery)->sum('amount');
        $payablesList = (clone $activePayablesQuery)->orderBy('due_date')->take(5)->get()->map(function ($p) use ($now) {
            $dueCarbon = $p->due_date ? Carbon::parse($p->due_date) : null;
            $p->is_overdue = $dueCarbon ? $dueCarbon->startOfDay()->lt($now->copy()->startOfDay()) : false;
            $p->is_due_today = $dueCarbon ? $dueCarbon->isSameDay($now) : false;
            $p->days_diff = $dueCarbon ? (int) $now->copy()->startOfDay()->diffInDays($dueCarbon->copy()->startOfDay(), false) : null;
            $p->due_date_formatted = $dueCarbon ? $dueCarbon->locale('id')->translatedFormat('d M Y') : '-';

            return $p;
        });

        // 3. Net Worth: (Saldo Dompet + Piutang) - Utang
        $netWorth = ($totalWalletBalance + $totalPiutang) - $totalUtang;

        // 4. Recent Transactions
        $recentTransactions = Transaction::where(function ($q) use ($user, $allAccessibleWalletIds) {
            $q->where('user_id', $user->id)
                ->orWhereIn('wallet_id', $allAccessibleWalletIds)
                ->orWhereIn('target_wallet_id', $allAccessibleWalletIds);
        })
            ->with(['wallet.user:id,name,username', 'targetWallet.user:id,name,username', 'user:id,name,username'])
            ->latest('transaction_date')
            ->latest('id')
            ->take(8)
            ->get();

        // 5. Monthly Cashflow & Budget Radar
        $monthTransactions = $user->transactions()
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->get();

        $monthIncome = (float) $monthTransactions->where('type', 'income')->sum('amount');
        $monthExpense = (float) $monthTransactions->where('type', 'expense')->sum('amount');

        $isCustomBudget = ! is_null($user->monthly_budget) && (float) $user->monthly_budget > 0;
        $targetBudget = $isCustomBudget
            ? (float) $user->monthly_budget
            : round($monthIncome * 0.8, 2);

        $elapsedDays = $now->day;
        $totalDaysInMonth = $now->daysInMonth;
        $remainingDays = max(1, ($totalDaysInMonth - $elapsedDays) + 1);
        $elapsedPercent = round(($elapsedDays / $totalDaysInMonth) * 100, 1);

        $remainingBudget = max(0, $targetBudget - $monthExpense);
        $usedPercent = $targetBudget > 0 ? round(($monthExpense / $targetBudget) * 100, 1) : ($monthExpense > 0 ? 100.0 : 0.0);

        if ($targetBudget <= 0 && $monthExpense == 0) {
            $status = 'no_budget';
            $burnRateRatio = 0.0;
        } elseif ($monthExpense > $targetBudget) {
            $status = 'overbudget';
            $burnRateRatio = $targetBudget > 0 ? round($monthExpense / $targetBudget, 2) : 999.0;
        } else {
            $burnRateRatio = $elapsedPercent > 0 ? round($usedPercent / $elapsedPercent, 2) : 1.0;
            if ($burnRateRatio > 1.25 || $usedPercent >= 85) {
                $status = 'danger';
            } elseif ($burnRateRatio > 1.0 || $usedPercent >= 70) {
                $status = 'warning';
            } else {
                $status = 'safe';
            }
        }

        $dailyAllowance = $remainingDays > 0 ? round($remainingBudget / $remainingDays, 2) : 0.0;

        $expenseByCategory = $monthTransactions->where('type', 'expense')
            ->groupBy(fn ($item) => $item->category ?: 'Lain-lain')
            ->map(fn ($group) => (float) $group->sum('amount'))
            ->sortDesc();

        $topCategory = null;
        if ($expenseByCategory->isNotEmpty() && $monthExpense > 0) {
            $topCatName = $expenseByCategory->keys()->first();
            $topCatAmount = (float) $expenseByCategory->first();
            $topCatPercent = round(($topCatAmount / $monthExpense) * 100, 1);
            $topCategory = [
                'name' => $topCatName,
                'amount' => $topCatAmount,
                'percent' => $topCatPercent,
            ];
        }

        $topCategories = $expenseByCategory->take(4)->map(fn ($amount, $name) => [
            'name' => $name,
            'amount' => (float) $amount,
            'percent' => $monthExpense > 0 ? round(($amount / $monthExpense) * 100, 1) : 0.0,
        ])->values()->all();

        $budgetRadar = [
            'target_budget' => $targetBudget,
            'is_custom' => $isCustomBudget,
            'custom_budget' => $user->monthly_budget ? (float) $user->monthly_budget : null,
            'month_income' => $monthIncome,
            'spent' => $monthExpense,
            'remaining' => $remainingBudget,
            'used_percent' => $usedPercent,
            'elapsed_days' => $elapsedDays,
            'total_days' => $totalDaysInMonth,
            'remaining_days' => $remainingDays,
            'elapsed_percent' => $elapsedPercent,
            'burn_rate_ratio' => $burnRateRatio,
            'status' => $status,
            'daily_allowance' => $dailyAllowance,
            'top_category' => $topCategory,
            'top_categories' => $topCategories,
        ];

        // 6. 6-Month Cashflow Trend & Analytics (Cached for 60s to absorb traffic spikes)
        [$monthlyTrend, $trendSummary] = Cache::remember("keuangan_dashboard_analytics_{$user->id}", 60, function () use ($now, $user, $allAccessibleWalletIds) {
            $sixMonthsAgo = $now->copy()->subMonths(5)->startOfMonth()->toDateString();
            $currentMonthEnd = $now->copy()->endOfMonth()->toDateString();

            $allSixMonthsTx = Transaction::where(function ($q) use ($user, $allAccessibleWalletIds) {
                $q->where('user_id', $user->id)
                    ->orWhereIn('wallet_id', $allAccessibleWalletIds);
            })
                ->whereBetween('transaction_date', [$sixMonthsAgo, $currentMonthEnd])
                ->get();

            $monthlyTrend = [];
            $prevMonthNet = null;

            for ($i = 5; $i >= 0; $i--) {
                $mDate = $now->copy()->subMonths($i);
                $start = $mDate->copy()->startOfMonth()->toDateString();
                $end = $mDate->copy()->endOfMonth()->toDateString();

                $mTx = $allSixMonthsTx->filter(function ($tx) use ($start, $end) {
                    $txDate = is_string($tx->transaction_date) ? $tx->transaction_date : $tx->transaction_date->toDateString();

                    return $txDate >= $start && $txDate <= $end;
                });

                $incTx = $mTx->where('type', 'income');
                $expTx = $mTx->where('type', 'expense');

                $inc = (float) $incTx->sum('amount');
                $exp = (float) $expTx->sum('amount');
                $net = $inc - $exp;

                $savingsRate = 0.0;
                if ($inc > 0) {
                    $savingsRate = round(($net / $inc) * 100, 1);
                } elseif ($exp > 0) {
                    $savingsRate = -100.0;
                }

                $momGrowth = null;
                if ($prevMonthNet !== null) {
                    if ($prevMonthNet == 0.0) {
                        $momGrowth = $net > 0 ? 100.0 : ($net < 0 ? -100.0 : 0.0);
                    } else {
                        $momGrowth = round((($net - $prevMonthNet) / abs($prevMonthNet)) * 100, 1);
                    }
                }
                $prevMonthNet = $net;

                $topExpenseCat = null;
                $monthExpByCategory = $expTx->groupBy(fn ($item) => $item->category ?: 'Lain-lain')
                    ->map(fn ($group) => (float) $group->sum('amount'))
                    ->sortDesc();

                if ($monthExpByCategory->isNotEmpty() && $exp > 0) {
                    $topCatName = $monthExpByCategory->keys()->first();
                    $topCatAmount = (float) $monthExpByCategory->first();
                    $topExpenseCat = [
                        'name' => $topCatName,
                        'amount' => $topCatAmount,
                        'percent' => round(($topCatAmount / $exp) * 100, 1),
                    ];
                }

                $mDateId = $mDate->copy()->locale('id');

                $monthlyTrend[] = [
                    'period_key' => $mDate->format('Y-m'),
                    'month' => $mDateId->translatedFormat('M Y'),
                    'month_name' => $mDateId->translatedFormat('F Y'),
                    'month_short' => $mDateId->translatedFormat('M'),
                    'year' => $mDate->format('Y'),
                    'start_date' => $start,
                    'end_date' => $end,
                    'is_current_month' => ($i === 0),
                    'income' => $inc,
                    'expense' => $exp,
                    'net' => $net,
                    'status' => $net > 0 ? 'surplus' : ($net < 0 ? 'deficit' : 'balanced'),
                    'savings_rate' => $savingsRate,
                    'income_count' => $incTx->count(),
                    'expense_count' => $expTx->count(),
                    'total_tx_count' => $mTx->count(),
                    'mom_growth' => $momGrowth,
                    'top_expense_category' => $topExpenseCat,
                ];
            }

            $totalInc6m = (float) collect($monthlyTrend)->sum('income');
            $totalExp6m = (float) collect($monthlyTrend)->sum('expense');
            $totalNet6m = $totalInc6m - $totalExp6m;
            $avgIncMonthly = round($totalInc6m / 6, 2);
            $avgExpMonthly = round($totalExp6m / 6, 2);
            $avgNetMonthly = round($totalNet6m / 6, 2);
            $avgSavingsRate = $totalInc6m > 0 ? round(($totalNet6m / $totalInc6m) * 100, 1) : 0.0;

            $surplusMonthsCount = collect($monthlyTrend)->where('net', '>', 0)->count();
            $deficitMonthsCount = collect($monthlyTrend)->where('net', '<', 0)->count();

            $bestMonth = collect($monthlyTrend)->sortByDesc('net')->first();
            $highestExpenseMonth = collect($monthlyTrend)->sortByDesc('expense')->first();

            $trendSummary = [
                'total_income' => $totalInc6m,
                'total_expense' => $totalExp6m,
                'total_net' => $totalNet6m,
                'avg_monthly_income' => $avgIncMonthly,
                'avg_monthly_expense' => $avgExpMonthly,
                'avg_monthly_net' => $avgNetMonthly,
                'avg_savings_rate' => $avgSavingsRate,
                'surplus_months_count' => $surplusMonthsCount,
                'deficit_months_count' => $deficitMonthsCount,
                'best_month' => $bestMonth ? [
                    'month' => $bestMonth['month'],
                    'month_name' => $bestMonth['month_name'],
                    'net' => $bestMonth['net'],
                ] : null,
                'highest_expense_month' => $highestExpenseMonth ? [
                    'month' => $highestExpenseMonth['month'],
                    'month_name' => $highestExpenseMonth['month_name'],
                    'expense' => $highestExpenseMonth['expense'],
                ] : null,
            ];

            return [$monthlyTrend, $trendSummary];
        });

        return Inertia::render('Keuangan::Dashboard', [
            'metrics' => [
                'netWorth' => $netWorth,
                'totalWalletBalance' => $totalWalletBalance,
                'totalPiutang' => $totalPiutang,
                'totalUtang' => $totalUtang,
                'kasSiapPakai' => $kasSiapPakai,
                'piutangCount' => $activeReceivablesQuery->count(),
                'utangCount' => $activePayablesQuery->count(),
                'monthIncome' => $monthIncome,
                'monthExpense' => $monthExpense,
                'monthNet' => $monthIncome - $monthExpense,
                'monthSavingsRate' => $monthIncome > 0 ? round((($monthIncome - $monthExpense) / $monthIncome) * 100, 1) : 0.0,
            ],
            'wallets' => $wallets,
            'recentTransactions' => $recentTransactions,
            'monthlyTrend' => $monthlyTrend,
            'trendSummary' => $trendSummary,
            'radar' => [
                'receivables' => $receivablesList,
                'payables' => $payablesList,
            ],
            'budgetRadar' => $budgetRadar,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'accessible_modules' => $user->accessibleModules(),
            ],
        ]);
    }

    /**
     * Update user's monthly budget limit.
     */
    public function updateBudget(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'monthly_budget' => ['nullable', 'numeric', 'min:0'],
        ], [
            'monthly_budget.numeric' => 'Nominal batas anggaran harus berupa angka.',
            'monthly_budget.min' => 'Nominal batas anggaran tidak boleh kurang dari Rp 0.',
        ]);

        $budget = $validated['monthly_budget'] ?? null;
        if ($budget !== null && (float) $budget <= 0) {
            $budget = null;
        }

        $request->user()->update([
            'monthly_budget' => $budget,
        ]);

        return back()->with('success', 'Batas anggaran bulanan berhasil diperbarui.');
    }
}
