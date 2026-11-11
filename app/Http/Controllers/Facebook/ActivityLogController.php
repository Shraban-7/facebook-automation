<?php

namespace App\Http\Controllers\Facebook;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Facebook\RateLimitService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request, RateLimitService $rateLimitService): Response
    {
        /** @var User $user */
        $user = $request->user();
        $activePage = $user->resolveCurrentPage();

        $category = $request->string('category')->toString();
        $level = $request->string('level')->toString();

        $logs = ActivityLog::query()
            ->with('facebookPage:id,name,page_id')
            ->where('user_id', $user->id)
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->when($level !== '', fn ($q) => $q->where('level', $level))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        $webhookEvents = WebhookEvent::query()
            ->when($activePage !== null, fn ($q) => $q->where('facebook_page_id', $activePage->id))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return Inertia::render('facebook/activity-logs', [
            'activePage' => $activePage ? [
                'id' => $activePage->id,
                'name' => $activePage->name,
                'page_id' => $activePage->page_id,
            ] : null,
            'filters' => [
                'category' => $category,
                'level' => $level,
            ],
            'logs' => $logs,
            'webhookEvents' => $webhookEvents,
            'rateLimit' => $rateLimitService->getStatus($user->id, $activePage?->id),
        ]);
    }
}
