<?php

namespace App\Jobs\Social;

use App\Contracts\Social\SupportsAnalytics;
use App\DTO\Social\DateRange;
use App\Models\MetricSnapshot;
use App\Models\SocialAccount;
use App\Services\Social\ProviderManager;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PullAccountMetricsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public readonly int $socialAccountId,
        public readonly int $days = 7,
    ) {
        $this->onQueue('analytics');
    }

    public function handle(ProviderManager $providers): void
    {
        $account = SocialAccount::query()->find($this->socialAccountId);
        if (! $account || $account->status !== 'active') {
            return;
        }

        $provider = $providers->provider($account->provider);
        if (! $provider instanceof SupportsAnalytics) {
            return;
        }

        $range = new DateRange(
            since: CarbonImmutable::now('UTC')->subDays($this->days)->startOfDay(),
            until: CarbonImmutable::now('UTC'),
        );

        $metrics = $provider->fetchMetrics($account, $range);
        foreach ($metrics as $metric) {
            MetricSnapshot::query()->updateOrCreate(
                [
                    'social_account_id' => $account->id,
                    'metric_key' => $metric->metricKey,
                    'date' => $metric->date->toDateString(),
                ],
                [
                    'value' => $metric->value,
                    'raw' => $metric->raw,
                ]
            );
        }

        $account->update(['last_synced_at' => now()]);
    }
}
