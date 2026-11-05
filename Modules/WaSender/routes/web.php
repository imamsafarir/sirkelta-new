<?php

use Illuminate\Support\Facades\Route;
use Modules\WaSender\Http\Controllers\Api\ExtensionAuthController;
use Modules\WaSender\Http\Controllers\ContactController;
use Modules\WaSender\Http\Controllers\DashboardController;
use Modules\WaSender\Http\Controllers\MessageTemplateController;

// Extension Web Session Auto-Authentication & Synchronization
Route::match(['get', 'post'], '/extension-auth/sync', [ExtensionAuthController::class, 'syncWebSession'])
    ->name('extension.auth.sync');
Route::post('/extension-auth/login', [ExtensionAuthController::class, 'unifiedLogin'])
    ->name('extension.auth.login');
Route::match(['get', 'post'], '/extension-auth/logout', [ExtensionAuthController::class, 'unifiedLogout'])
    ->name('extension.auth.logout');

// Global download route alias
Route::get('/download/extension', [DashboardController::class, 'downloadExtension'])
    ->name('download.extension');

// WaSender Admin / User Portal
Route::middleware(['auth', 'verified', 'role:superadmin|admin_wasender|user_wasender'])
    ->prefix('wasender')
    ->name('wasender.')
    ->group(function () {
        // Dashboard & Session Guard Control
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('/dashboard/extension-session/revoke', [DashboardController::class, 'revokeExtensionSession'])
            ->name('dashboard.extension-session.revoke');
        Route::get('/download/extension', [DashboardController::class, 'downloadExtension'])
            ->name('download.extension');

        // Contact Management
        Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
        Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
        Route::post('/contacts/import-csv', [ContactController::class, 'importCsv'])->name('contacts.import');
        Route::get('/contacts/export-csv', [ContactController::class, 'exportCsv'])->name('contacts.export');
        Route::put('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
        Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

        // Message Templates Management
        Route::get('/templates', [MessageTemplateController::class, 'index'])->name('templates.index');
        Route::post('/templates', [MessageTemplateController::class, 'store'])->name('templates.store');
        Route::put('/templates/{template}', [MessageTemplateController::class, 'update'])->name('templates.update');
        Route::delete('/templates/{template}', [MessageTemplateController::class, 'destroy'])->name('templates.destroy');
    });
