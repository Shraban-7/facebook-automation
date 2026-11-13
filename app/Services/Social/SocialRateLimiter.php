<?php

namespace App\Services\Social;

use App\Enums\ErrorClass;
use App\Exceptions\SocialProviderException;
use App\Models\QuotaUsage;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Cache;

class SocialRateLimiter
{
    public function checkAccountLimit(SocialAccount $account): void
    {
        $provider = $account->provider;
        $perMinute = (int) config("social.providers.{$provider}.rate.per_minute", 60);
        $key = sprintf('social_rl:%s:%d:%s', $provider, $account->id, now()->format('YmdHi'));

        $used = (int) Cache::get($key, 0);

        if ($used >= $perMinute) {
            throw new SocialProviderException(
                message: sprintf('Rate limit reached for %s account #%d (%d/%d calls/min).', $provider, $account->id, $used, $perMinute),
                errorClass: ErrorClass::RateLimited,
                httpStatus: 429,
                retryAfterSeconds: 60,
            );
        }

        Cache::put($key, $used + 1, 90);
    }

    /**
     * Check and consume YouTube Data API v3 daily project quota units.
     */
    public function consumeQuota(string $provider, string $scopeKey, int $units, int $dailyLimit): void
    {
        $today = now()->toDateString();

        $record = QuotaUsage::firstOrCreate(
            [
                'provider' => $provider,
                'scope_key' => $scopeKey,
                'date' => $today,
            ],
            ['units_used' => 0]
        );

        if (($record->units_used + $units) > $dailyLimit) {
            throw new SocialProviderException(
                message: sprintf('Daily %s API quota exhausted (%d/%d units used).', $provider, $record->units_used, $dailyLimit),
                errorClass: ErrorClass::RateLimited,
                httpStatus: 429,
                retryAfterSeconds: (int) now()->endOfDay()->diffInSeconds(now()),
            );
        }

        $record->increment('units_used', $units);
    }

    /**
     * @return array{used: int, limit: int, remaining: int, percent: int}
     */
    public function getDailyQuotaStatus(string $provider, string $scopeKey = 'default'): array
    {
        $limit = max(1, (int) config("social.providers.{$provider}.daily_quota", 10000));
        $used = (int) (QuotaUsage::query()
            ->where('provider', $provider)
            ->where('scope_key', $scopeKey)
            ->where('date', now()->toDateString())
            ->value('units_used') ?? 0);

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => max(0, $limit - $used),
            'percent' => (int) min(100, round(($used / $limit) * 100)),
        ];
    }
}
