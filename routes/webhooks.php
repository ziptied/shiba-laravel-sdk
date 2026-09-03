<?php

declare(strict_types=1);

use Bentonow\ShibaLaravel\Http\Controllers\WebhookController;
use Bentonow\ShibaLaravel\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Support\Facades\Route;

Route::post((string) config('shiba.webhook.path'), WebhookController::class)
    ->middleware(VerifyWebhookSignature::class)
    ->name('shiba.webhook');
