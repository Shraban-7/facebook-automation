<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\MetricSnapshot;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SocialAnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $workspace = $user->resolveCurrentWorkspace();
        $days = in_array((int) $request->query('days', 30), [7, 30, 90], true)
            ? (int) $request->query('days', 30)
            : 30;
        $providerFilter = $request->query('provider');

        $accountsQuery = SocialAccount::query()->where('workspace_id', $workspace->id);
        if (is_string($providerFilter) && $providerFilter !== '' && $providerFilter !== 'all') {
            $accountsQuery->where('provider', $providerFilter);
        }

        $accountIds = $accountsQuery->pluck('id');
        $since = now()->subDays($days)->toDateString();

        $snapshots = MetricSnapshot::query()
            ->whereIn('social_account_id', $accountIds)
            ->where('date', '>=', $since)
            ->with('socialAccount')
            ->orderBy('date')
            ->get();

        $totals = [
            'impressions' => (float) $snapshots->where('metric_key', 'impressions')->sum('value'),
            'engagements' => (float) $snapshots->where('metric_key', 'engagements')->sum('value'),
            'clicks' => (float) $snapshots->where('metric_key', 'clicks')->sum('value'),
            'followers_total' => (float) $snapshots
                ->where('metric_key', 'followers_total')
                ->groupBy('social_account_id')
                ->sum(fn ($group) => $group->last()->value),
        ];

        $byDate = $snapshots
            ->groupBy(fn (MetricSnapshot $s): string => $s->date->toDateString())
            ->map(fn ($group, string $date): array => [
                'date' => $date,
                'impressions' => (float) $group->where('metric_key', 'impressions')->sum('value'),
                'engagements' => (float) $group->where('metric_key', 'engagements')->sum('value'),
            ])
            ->values();

        $byProvider = $snapshots
            ->groupBy(fn (MetricSnapshot $s): string => $s->socialAccount->provider)
            ->map(fn ($group, string $provider): array => [
                'provider' => $provider,
                'impressions' => (float) $group->where('metric_key', 'impressions')->sum('value'),
                'engagements' => (float) $group->where('metric_key', 'engagements')->sum('value'),
            ])
            ->values();

        return Inertia::render('social/analytics', [
            'days' => $days,
            'providerFilter' => is_string($providerFilter) ? $providerFilter : 'all',
            'totals' => $totals,
            'series' => $byDate,
            'byProvider' => $byProvider,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $workspace = $user->resolveCurrentWorkspace();
        $accountIds = SocialAccount::query()
            ->where('workspace_id', $workspace->id)
            ->pluck('id');

        $snapshots = MetricSnapshot::query()
            ->whereIn('social_account_id', $accountIds)
            ->with('socialAccount')
            ->orderByDesc('date')
            ->get();

        return response()->streamDownload(function () use ($snapshots): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['date', 'provider', 'account', 'metric_key', 'value']);
            foreach ($snapshots as $row) {
                fputcsv($out, [
                    $row->date->toDateString(),
                    $row->socialAccount->provider,
                    $row->socialAccount->name,
                    $row->metric_key,
                    $row->value,
                ]);
            }
            fclose($out);
        }, 'social-analytics.csv', ['Content-Type' => 'text/csv']);
    }
}
