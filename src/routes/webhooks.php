<?php

use App\Http\Controllers\Billing\XenditWebhookController;
use Illuminate\Support\Facades\Route;

// No "web" group: no session, CSRF, cookies or not.blocked.
Route::post('/webhooks/xendit', XenditWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.xendit');

Route::post('/webhooks/xendit/payments', [XenditWebhookController::class, 'payments'])
    ->middleware('throttle:120,1')
    ->name('webhooks.xendit.payments');
