<?php

use App\Plugins\SafaricomMobileMoney\Http\Controllers\SafaricomCredentialController;
use App\Plugins\SafaricomMobileMoney\Http\Controllers\SafaricomTransactionController;
use App\Plugins\SafaricomMobileMoney\Http\Controllers\SafaricomWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('safaricom')->group(function () {
    Route::get('/credential', [SafaricomCredentialController::class, 'show']);
    Route::put('/credential', [SafaricomCredentialController::class, 'update']);

    Route::get('/transactions', [SafaricomTransactionController::class, 'getTransactions']);
    Route::get('/transactions/{id}', [SafaricomTransactionController::class, 'getTransaction']);
    Route::post('/stk-push', [SafaricomTransactionController::class, 'initiateStkPush']);

    // Daraja webhook endpoints — companyId is in the URL so the ApiResolver
    // can pick the right tenant for unauthenticated callbacks.
    Route::post('/webhook/stk-push-result/{companyId}', [SafaricomWebhookController::class, 'handleSTKPushResult']);
    Route::post('/webhook/validation', [SafaricomWebhookController::class, 'handleValidation']);
    Route::post('/webhook/confirmation', [SafaricomWebhookController::class, 'handleConfirmation']);
});
