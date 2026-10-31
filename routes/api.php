<?php

use App\Http\Controllers\Api\StoreInboundMailController;
use App\Http\Middleware\VerifyMailWebhook;
use Illuminate\Support\Facades\Route;

Route::post('mail', StoreInboundMailController::class)
    ->middleware(VerifyMailWebhook::class)
    ->name('api.mail.store');
