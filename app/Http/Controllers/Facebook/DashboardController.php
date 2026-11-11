<?php

namespace App\Http\Controllers\Facebook;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AutoReplyRule;
use App\Models\Message;
use App\Models\ScheduledPost;
use App\Models\User;
use App\Services\Facebook\InsightsService;
use App\Services\Facebook\RateLimitService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        InsightsService $insightsService,
        RateLimitService $rateLimitService,
    ): Response {
        /** @var User $user */
        $user = $request->user();
        $activePage = $user->resolveCurrentPage();

        $pages = $user->facebookPages()
            ->orderBy('name')
            ->get()
            ->map(fn ($page): array => [
                'id' => $page->id,
                'page_id' => $page->page_id,
                'name' => $page->name,
                'category' => $page->category,
                'avatar_url' => $page->avatar_url,
                'followers_count' => $page->followers_count,
                'status' => $page->status,
                'webhook_subscribed' => $page->webhook_subscribed,
                'token_expires_at' => $page->token_expires_at?->toIso8601String(),
                'is_expiring_soon' => $page->isTokenExpiringSoon(),
                'last_synced_at' => $page->last_synced_at?->toIso8601String(),
            ]);

        $analytics = $activePage !== null
            ? $insightsService->getCachedAnalytics($activePage, 30)
            : null;

        $upcomingSchedules = $activePage !== null
            ? ScheduledPost::query()
                ->with('post')
                ->where('facebook_page_id', $activePage->id)
                ->whereIn('status', ['pending', 'failed'])
                ->orderBy('scheduled_for')
                ->limit(6)
                ->get()
            : collect();

        $recentMessages = $activePage !== null
            ? Message::query()
                ->with('autoReplyRule')
                ->where('facebook_page_id', $activePage->id)
                ->orderByDesc('created_at')
                ->limit(6)
                ->get()
            : collect();

        $activeRulesCount = $activePage !== null
            ? AutoReplyRule::query()
                ->where('facebook_page_id', $activePage->id)
                ->where('is_active', true)
                ->count()
            : 0;

        $recentLogs = ActivityLog::query()
            ->where('user_id', $user->id)
            ->when($activePage !== null, fn ($q) => $q->where('facebook_page_id', $activePage->id))
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return Inertia::render('dashboard', [
            'pages' => $pages,
            'activePage' => $activePage ? [
                'id' => $activePage->id,
                'page_id' => $activePage->page_id,
                'name' => $activePage->name,
                'category' => $activePage->category,
                'avatar_url' => $activePage->avatar_url,
                'followers_count' => $activePage->followers_count,
                'status' => $activePage->status,
                'webhook_subscribed' => $activePage->webhook_subscribed,
                'token_expires_at' => $activePage->token_expires_at?->toIso8601String(),
                'last_synced_at' => $activePage->last_synced_at?->toIso8601String(),
            ] : null,
            'analytics' => $analytics,
            'upcomingSchedules' => $upcomingSchedules,
            'recentMessages' => $recentMessages,
            'activeRulesCount' => $activeRulesCount,
            'rateLimit' => $rateLimitService->getStatus($user->id, $activePage?->id),
            'recentLogs' => $recentLogs,
        ]);
    }
}
