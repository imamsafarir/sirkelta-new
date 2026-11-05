<?php

use Illuminate\Support\Facades\Route;
use Modules\Keuangan\Http\Controllers\AdminController;
use Modules\Keuangan\Http\Controllers\DashboardController;
use Modules\Keuangan\Http\Controllers\DebtRadarController;
use Modules\Keuangan\Http\Controllers\ReportController;
use Modules\Keuangan\Http\Controllers\TransactionController;
use Modules\Keuangan\Http\Controllers\WalletController;

Route::middleware(['auth', 'verified', 'role:superadmin|admin_keuangan|user_keuangan'])
    ->prefix('keuangan')
    ->name('keuangan.')
    ->group(function () {
        // Dashboard & Budgeting
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('/dashboard/budget', [DashboardController::class, 'updateBudget'])->name('dashboard.budget');

        // Wallets
        Route::get('/wallets', [WalletController::class, 'index'])->name('wallets.index');
        Route::post('/wallets', [WalletController::class, 'store'])->name('wallets.store');
        Route::get('/wallets/{wallet}', [WalletController::class, 'show'])->name('wallets.show');
        Route::put('/wallets/{wallet}', [WalletController::class, 'update'])->name('wallets.update');
        Route::delete('/wallets/{wallet}', [WalletController::class, 'destroy'])->name('wallets.destroy');
        Route::post('/wallets/{wallet}/shares', [WalletController::class, 'shareWallet'])->name('wallets.shares.store');
        Route::delete('/wallets/{wallet}/shares/{share}', [WalletController::class, 'revokeShare'])->name('wallets.shares.destroy');
        Route::post('/wallets/shares/{share}/accept', [WalletController::class, 'acceptShare'])->name('wallets.shares.accept');
        Route::post('/wallets/shares/{share}/reject', [WalletController::class, 'rejectShare'])->name('wallets.shares.reject');
        Route::patch('/wallets/shares/{share}/toggle', [WalletController::class, 'toggleShare'])->name('wallets.shares.toggle');

        // Transactions
        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/transactions/export-csv', [TransactionController::class, 'exportCsv'])->middleware('throttle:exports')->name('transactions.export');
        Route::post('/transactions', [TransactionController::class, 'store'])->middleware('throttle:transactions')->name('transactions.store');
        Route::put('/transactions/{transaction}', [TransactionController::class, 'update'])->middleware('throttle:transactions')->name('transactions.update');
        Route::post('/transactions/bulk-destroy', [TransactionController::class, 'bulkDestroy'])->middleware('throttle:bulk-operations')->name('transactions.bulk-destroy');
        Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->middleware('throttle:transactions')->name('transactions.destroy');

        // Debts & Receivables Radar
        Route::get('/radar-utang-piutang', [DebtRadarController::class, 'index'])->name('debts.index');
        Route::patch('/radar-utang-piutang/{transaction}/status', [DebtRadarController::class, 'updateStatus'])->name('debts.update-status');

        // Reports & Export
        Route::get('/laporan', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/laporan/export-csv', [ReportController::class, 'exportCsv'])->middleware('throttle:exports')->name('reports.export');

        // Admin Keuangan Hub (restricted to superadmin and admin_keuangan)
        Route::middleware('role:superadmin|admin_keuangan')->group(function () {
            Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
            Route::patch('/admin/referral-logs/{referralLog}/status', [AdminController::class, 'updateReferralStatus'])->name('admin.referrals.update-status');
        });
    });
