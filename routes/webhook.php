<?php

use HRC\NectaPay\Http\Controllers\NectaPayWebhookController;
use Illuminate\Support\Facades\Route;

Route::post(config('nectapay.webhook_path', 'webhook/nectapay'), [NectaPayWebhookController::class, 'handle'])
    ->name('nectapay.webhook');
