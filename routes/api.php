<?php

use App\Http\Controllers\Compliance\MetaComplianceController;
use App\Http\Controllers\Facebook\WebhookController;
use App\Http\Controllers\Webhooks\SocialWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/webhooks/facebook', [WebhookController::class, 'verify'])
    ->name('api.webhooks.facebook.verify');

Route::post('/webhooks/facebook', [WebhookController::class, 'handle'])
    ->name('api.webhooks.facebook.handle');

Route::get('/webhooks/{provider}', [SocialWebhookController::class, 'verify'])
    ->whereIn('provider', ['facebook', 'instagram', 'threads', 'linkedin', 'youtube'])
    ->name('api.webhooks.social.verify');

Route::post('/webhooks/{provider}', [SocialWebhookController::class, 'receive'])
    ->whereIn('provider', ['facebook', 'instagram', 'threads', 'linkedin', 'youtube'])
    ->name('api.webhooks.social.receive');

Route::post('/compliance/meta/data-deletion', [MetaComplianceController::class, 'dataDeletion'])
    ->name('api.compliance.meta.data-deletion');

Route::post('/compliance/meta/deauthorize', [MetaComplianceController::class, 'deauthorize'])
    ->name('api.compliance.meta.deauthorize');
