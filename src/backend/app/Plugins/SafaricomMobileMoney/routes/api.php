<?php

use Illuminate\Support\Facades\Route;
use App\Plugins\SafaricomMobileMoney\Http\Controllers\SafaricomTransactionController;
use App\Plugins\SafaricomMobileMoney\Http\Controllers\SafaricomWebhookController;

Route::group(['prefix' => 'safaricom'], function () {
    // Transaction endpoints
    Route::post('/stk-push', [SafaricomTransactionController::class, 'initiateSTKPush']);
    Route::get('/transaction/{referenceId}/status', [SafaricomTransactionController::class, 'checkStatus']);

    // Webhook endpoints
    Route::post('/webhook/stk-push-result', [SafaricomWebhookController::class, 'handleSTKPushResult']);
    Route::post('/webhook/validation', [SafaricomWebhookController::class, 'handleValidation']);
    Route::post('/webhook/confirmation', [SafaricomWebhookController::class, 'handleConfirmation']);
});
