<?php

use App\Http\Controllers\Api\ForwardEmail\ForwardEmailBounceController;
use App\Http\Controllers\Api\ForwardEmail\ForwardEmailInboundController;
use App\Http\Middleware\VerifyMailWebhook;
use Illuminate\Support\Facades\Route;

Route::prefix('webhooks/forwardemail')
    ->middleware(VerifyMailWebhook::class)
    ->group(function (): void {
        Route::post('mail', ForwardEmailInboundController::class)
            ->name('api.webhooks.forwardemail.mail');
        Route::post('bounces', ForwardEmailBounceController::class)
            ->name('api.webhooks.forwardemail.bounces');
    });

// Deprecated ForwardEmail-shaped inbound URL — same adapter until alias switchover.
Route::post('mail', ForwardEmailInboundController::class)
    ->middleware(VerifyMailWebhook::class)
    ->name('api.mail.store');
