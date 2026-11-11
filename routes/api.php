<?php

use App\Http\Controllers\Facebook\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/webhooks/facebook', [WebhookController::class, 'verify'])
    ->name('api.webhooks.facebook.verify');

Route::post('/webhooks/facebook', [WebhookController::class, 'handle'])
    ->name('api.webhooks.facebook.handle');
