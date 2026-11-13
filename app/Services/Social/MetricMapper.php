<?php

namespace App\Services\Social;

use App\DTO\Social\NormalizedMetric;
use Illuminate\Support\Carbon;

class MetricMapper
{
    /**
     * Map Meta (Facebook/Instagram) insight metric names to normalized `metric_definitions` keys.
     */
    public function mapMetaMetricName(string $nativeName): ?string
    {
        return match ($nativeName) {
            'page_impressions_unique', 'reach' => 'reach',
            'page_impressions', 'impressions', 'views' => 'impressions',
            'page_post_engagements', 'total_interactions' => 'engagements',
            'page_follows', 'follower_count' => 'followers',
            'page_views_total', 'profile_views' => 'page_views',
            default => null,
        };
    }

    /**
     * Normalize a Meta Graph API insights response series into `NormalizedMetric` DTOs.
     *
     * @param  array<int, array<string, mixed>>  $seriesList
     * @return array<int, NormalizedMetric>
     */
    public function normalizeMetaSeries(array $seriesList): array
    {
        $results = [];

        foreach ($seriesList as $series) {
            $nativeName = (string) ($series['name'] ?? '');
            $normalizedKey = $this->mapMetaMetricName($nativeName);

            if ($normalizedKey === null) {
                continue;
            }

            /** @var array<int, array<string, mixed>> $values */
            $values = is_array($series['values'] ?? null) ? $series['values'] : [];

            foreach ($values as $point) {
                if (! is_numeric($point['value'] ?? null)) {
                    continue;
                }

                $date = isset($point['end_time'])
                    ? Carbon::parse((string) $point['end_time'])->startOfDay()
                    : now()->startOfDay();

                $results[] = new NormalizedMetric(
                    metricKey: $normalizedKey,
                    date: $date,
                    value: (float) $point['value'],
                    raw: ['native_name' => $nativeName, 'point' => $point],
                );
            }
        }

        return $results;
    }
}
