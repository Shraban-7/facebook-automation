<?php

namespace App\Services\Facebook;

use App\Models\FacebookPage;
use App\Models\Insight;
use App\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class InsightsService
{
    public function __construct(
        private readonly GraphApiService $graphApi,
    ) {}

    /**
     * Fetch Page insights from the official Graph API, persist daily rows, and invalidate the dashboard cache.
     */
    public function syncPageInsights(FacebookPage $page, int $days = 30): int
    {
        $until = now();
        $since = now()->subDays($days);

        $seriesList = $this->graphApi->fetchPageInsights($page, $since, $until);

        /** @var array<string, array<string, int>> $byDate */
        $byDate = [];

        foreach ($seriesList as $metricSeries) {
            $metricName = (string) ($metricSeries['name'] ?? '');
            /** @var array<int, array<string, mixed>> $values */
            $values = is_array($metricSeries['values'] ?? null) ? $metricSeries['values'] : [];

            foreach ($values as $point) {
                $endTime = isset($point['end_time']) ? Carbon::parse((string) $point['end_time'])->toDateString() : now()->toDateString();
                $val = is_numeric($point['value'] ?? null) ? (int) $point['value'] : 0;

                $byDate[$endTime] ??= [
                    'reach' => 0,
                    'impressions' => 0,
                    'engaged_users' => 0,
                    'followers' => $page->followers_count,
                    'page_views' => 0,
                ];

                match ($metricName) {
                    'page_impressions_unique' => $byDate[$endTime]['reach'] = $val,
                    'page_impressions' => $byDate[$endTime]['impressions'] = $val,
                    'page_post_engagements' => $byDate[$endTime]['engaged_users'] = $val,
                    'page_follows' => $byDate[$endTime]['followers'] = $val,
                    'page_views_total' => $byDate[$endTime]['page_views'] = $val,
                    default => null,
                };
            }
        }

        $upserted = 0;

        foreach ($byDate as $dateString => $metrics) {
            Insight::updateOrCreate(
                [
                    'facebook_page_id' => $page->id,
                    'metric_date' => $dateString,
                    'period' => 'day',
                ],
                [
                    'reach' => $metrics['reach'],
                    'impressions' => $metrics['impressions'],
                    'engaged_users' => $metrics['engaged_users'],
                    'engagements' => $metrics['engaged_users'],
                    'followers' => $metrics['followers'],
                    'page_views' => $metrics['page_views'],
                    'raw_metrics' => $metrics,
                ]
            );
            $upserted++;
        }

        $page->update(['last_synced_at' => now()]);
        $this->forgetCache($page->id);

        return $upserted;
    }

    /**
     * Retrieve cached analytics for 7, 30, or 90 days along with top-performing posts.
     *
     * @return array<string, mixed>
     */
    public function getCachedAnalytics(FacebookPage $page, int $days = 30): array
    {
        $validDays = in_array($days, [7, 30, 90], true) ? $days : 30;
        $cacheKey = $this->cacheKey($page->id, $validDays);

        /** @var array<string, mixed> $analytics */
        $analytics = Cache::remember($cacheKey, 900, function () use ($page, $validDays): array {
            $startDate = now()->subDays($validDays - 1)->toDateString();

            $insights = Insight::query()
                ->where('facebook_page_id', $page->id)
                ->where('metric_date', '>=', $startDate)
                ->orderBy('metric_date')
                ->get();

            $totalReach = (int) $insights->sum('reach');
            $totalEngagements = (int) $insights->sum('engaged_users');
            $totalImpressions = (int) $insights->sum('impressions');
            $totalPageViews = (int) $insights->sum('page_views');
            $netFollowers = (int) $insights->sum('net_followers');
            $lastInsight = $insights->last();
            $latestFollowers = (int) ($lastInsight !== null ? $lastInsight->followers : $page->followers_count);

            $engagementRate = $totalReach > 0
                ? round(($totalEngagements / $totalReach) * 100, 2)
                : 0.0;

            $series = $insights->map(fn (Insight $row): array => [
                'date' => $row->metric_date->format('M j'),
                'iso_date' => $row->metric_date->toDateString(),
                'reach' => $row->reach,
                'impressions' => $row->impressions,
                'engagement' => $row->engaged_users,
                'followers' => $row->followers,
                'net_followers' => $row->net_followers,
                'page_views' => $row->page_views,
            ])->values()->all();

            $topPosts = Post::query()
                ->where('facebook_page_id', $page->id)
                ->where('status', 'published')
                ->orderByDesc('published_at')
                ->get()
                ->sortByDesc(fn (Post $post): int => (int) (($post->metrics['reach'] ?? 0) + (($post->metrics['engagements'] ?? 0) * 3)))
                ->take(6)
                ->map(fn (Post $post): array => [
                    'id' => $post->id,
                    'facebook_post_id' => $post->facebook_post_id,
                    'type' => $post->type,
                    'content' => $post->content,
                    'published_at' => $post->published_at?->toIso8601String(),
                    'permalink_url' => $post->permalink_url,
                    'reach' => (int) ($post->metrics['reach'] ?? 0),
                    'engagements' => (int) ($post->metrics['engagements'] ?? 0),
                    'reactions' => (int) ($post->metrics['reactions'] ?? 0),
                    'comments' => (int) ($post->metrics['comments'] ?? 0),
                    'shares' => (int) ($post->metrics['shares'] ?? 0),
                ])
                ->values()
                ->all();

            return [
                'days' => $validDays,
                'summary' => [
                    'total_reach' => $totalReach,
                    'total_impressions' => $totalImpressions,
                    'total_engagements' => $totalEngagements,
                    'engagement_rate' => $engagementRate,
                    'followers' => $latestFollowers,
                    'net_followers' => $netFollowers,
                    'page_views' => $totalPageViews,
                ],
                'series' => $series,
                'top_posts' => $topPosts,
                'cached_at' => now()->toIso8601String(),
            ];
        });

        return $analytics;
    }

    public function forgetCache(int $pageId): void
    {
        foreach ([7, 30, 90] as $days) {
            Cache::forget($this->cacheKey($pageId, $days));
        }
    }

    private function cacheKey(int $pageId, int $days): string
    {
        return sprintf('fb_insights:page:%d:days:%d', $pageId, $days);
    }
}
