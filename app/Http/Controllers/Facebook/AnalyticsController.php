<?php

namespace App\Http\Controllers\Facebook;

use App\Http\Controllers\Controller;
use App\Jobs\SyncPageInsightsJob;
use App\Models\FacebookPage;
use App\Models\User;
use App\Services\Facebook\InsightsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(Request $request, InsightsService $insightsService): Response
    {
        /** @var User $user */
        $user = $request->user();
        $activePage = $user->resolveCurrentPage();
        $days = in_array($request->integer('days', 30), [7, 30, 90], true)
            ? $request->integer('days', 30)
            : 30;

        $analytics = $activePage !== null
            ? $insightsService->getCachedAnalytics($activePage, $days)
            : null;

        return Inertia::render('facebook/analytics', [
            'activePage' => $activePage ? [
                'id' => $activePage->id,
                'name' => $activePage->name,
                'page_id' => $activePage->page_id,
                'followers_count' => $activePage->followers_count,
                'last_synced_at' => $activePage->last_synced_at?->toIso8601String(),
            ] : null,
            'days' => $days,
            'analytics' => $analytics,
        ]);
    }

    public function sync(Request $request, FacebookPage $facebookPage): RedirectResponse
    {
        Gate::authorize('update', $facebookPage);

        $days = in_array($request->integer('days', 30), [7, 30, 90], true)
            ? $request->integer('days', 30)
            : 30;

        SyncPageInsightsJob::dispatch($facebookPage->id, $days);

        return back()->with('success', sprintf('Queued %d-day Graph API Insights pull for %s.', $days, $facebookPage->name));
    }
}
