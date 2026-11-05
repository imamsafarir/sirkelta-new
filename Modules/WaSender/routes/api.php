<?php

use Illuminate\Support\Facades\Route;
use Modules\WaSender\Http\Controllers\Api\ExtensionAuthController;
use Modules\WaSender\Http\Controllers\Api\ExtensionDataController;

// Extension Chrome Authentication, Sync & Atomic Session Endpoints
Route::prefix('extension')->group(function () {
    // Public extension login
    Route::post('/login', [ExtensionAuthController::class, 'login']);

    // Authenticated extension routes
    Route::middleware('extension.auth')->group(function () {
        Route::post('/heartbeat', [ExtensionAuthController::class, 'heartbeat']);
        Route::post('/logout', [ExtensionAuthController::class, 'logout']);
        Route::get('/me', [ExtensionAuthController::class, 'me']);
        Route::post('/validate-batch', [ExtensionAuthController::class, 'validateBatch']);
        Route::post('/record-sent', [ExtensionAuthController::class, 'recordSent']);

        // Data Sync for Extension
        Route::get('/contacts', [ExtensionDataController::class, 'contacts']);
        Route::post('/contacts', [ExtensionDataController::class, 'saveContacts']);
        Route::get('/templates', [ExtensionDataController::class, 'templates']);
    });
});
