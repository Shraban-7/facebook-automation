<?php

use App\Http\Controllers\Facebook\ActivityLogController;
use App\Http\Controllers\Facebook\AnalyticsController;
use App\Http\Controllers\Facebook\AutoReplyRuleController;
use App\Http\Controllers\Facebook\DashboardController;
use App\Http\Controllers\Facebook\FacebookPageController;
use App\Http\Controllers\Facebook\PostSchedulerController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // 1. Facebook Page OAuth & Multi-Page Management
    Route::get('oauth/facebook/redirect', [FacebookPageController::class, 'redirectToFacebook'])
        ->name('facebook.oauth.redirect');
    Route::get('oauth/facebook/callback', [FacebookPageController::class, 'handleCallback'])
        ->name('facebook.oauth.callback');

    Route::prefix('facebook')->name('facebook.')->group(function () {
        Route::get('pages', [FacebookPageController::class, 'index'])->name('pages.index');
        Route::post('pages/manual', [FacebookPageController::class, 'storeManual'])->name('pages.manual');
        Route::post('pages/seed-demo', [FacebookPageController::class, 'seedDemo'])->name('pages.seed-demo');
        Route::post('pages/{facebookPage}/switch', [FacebookPageController::class, 'switchActive'])->name('pages.switch');
        Route::post('pages/{facebookPage}/refresh-token', [FacebookPageController::class, 'refreshToken'])->name('pages.refresh-token');
        Route::delete('pages/{facebookPage}', [FacebookPageController::class, 'destroy'])->name('pages.destroy');

        // 2. Post Scheduler & Calendar
        Route::get('scheduler', [PostSchedulerController::class, 'index'])->name('scheduler.index');
        Route::post('scheduler/posts', [PostSchedulerController::class, 'store'])->name('scheduler.store');
        Route::put('scheduler/posts/{post}', [PostSchedulerController::class, 'update'])->name('scheduler.update');
        Route::delete('scheduler/posts/{post}', [PostSchedulerController::class, 'destroy'])->name('scheduler.destroy');
        Route::post('scheduler/schedules/{scheduledPost}/retry', [PostSchedulerController::class, 'retry'])->name('scheduler.retry');

        // 3. Auto-Reply Rules (Messenger & Comments + Banned Word Moderation)
        Route::get('auto-replies', [AutoReplyRuleController::class, 'index'])->name('auto-replies.index');
        Route::post('auto-replies', [AutoReplyRuleController::class, 'store'])->name('auto-replies.store');
        Route::put('auto-replies/{autoReplyRule}', [AutoReplyRuleController::class, 'update'])->name('auto-replies.update');
        Route::post('auto-replies/{autoReplyRule}/toggle', [AutoReplyRuleController::class, 'toggle'])->name('auto-replies.toggle');
        Route::delete('auto-replies/{autoReplyRule}', [AutoReplyRuleController::class, 'destroy'])->name('auto-replies.destroy');
        Route::post('auto-replies/simulate', [AutoReplyRuleController::class, 'simulate'])->name('auto-replies.simulate');

        // 4. Analytics Dashboard (7/30/90-day Insights & Top Posts)
        Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::post('analytics/{facebookPage}/sync', [AnalyticsController::class, 'sync'])->name('analytics.sync');

        // 5. Activity Logs, Webhook Inspector & Rate Limits
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    });
});

require __DIR__.'/settings.php';
