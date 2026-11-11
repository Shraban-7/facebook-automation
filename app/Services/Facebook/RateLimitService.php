<?php

namespace App\Services\Facebook;

use App\Exceptions\FacebookGraphException;
use Illuminate\Support\Facades\Cache;

class RateLimitService
{
    /**
     * Verify that the user and page are below the Graph API rate limit threshold before executing an API call.
     *
     * @throws FacebookGraphException
     */
    public function ensureWithinLimits(int $userId, ?int $pageId = null): void
    {
        $status = $this->getStatus($userId, $pageId);

        if ($status['is_throttled']) {
            throw new FacebookGraphException(
                message: sprintf(
                    'Per-user Graph API rate limit threshold reached (%d/%d calls used, header peak %d%%). Backing off until reset.',
                    $status['calls_used'],
                    $status['hourly_limit'],
                    $status['header_peak_percent'],
                ),
                graphCode: 4,
                graphSubcode: null,
                httpStatus: 429,
            );
        }
    }

    /**
     * Increment the user's hourly Graph API call counter and record Meta usage headers (`X-App-Usage` / `X-Page-Usage`).
     *
     * @param  array<string, array<int, string>|string>  $headers
     * @return array<string, int>
     */
    public function recordCallAndHeaders(int $userId, ?int $pageId = null, array $headers = []): array
    {
        $bucketKey = $this->hourlyBucketKey($userId);
        $ttl = now()->endOfHour()->diffInSeconds(now()) + 60;

        $callsUsed = (int) Cache::get($bucketKey, 0) + 1;
        Cache::put($bucketKey, $callsUsed, max(60, (int) $ttl));

        $parsedUsage = $this->parseUsageHeaders($headers);

        if ($parsedUsage !== []) {
            Cache::put($this->usageHeaderKey($userId, $pageId), $parsedUsage, 3600);
        }

        return $parsedUsage;
    }

    /**
     * Mark a user/page as explicitly throttled when Meta returns code 4, 17, 32, 613, or 80001.
     */
    public function markRateLimited(int $userId, ?int $pageId = null, int $cooldownSeconds = 300): void
    {
        Cache::put($this->cooldownKey($userId, $pageId), now()->addSeconds($cooldownSeconds)->toIso8601String(), $cooldownSeconds);
    }

    /**
     * Retrieve current rate limit status for dashboard and pre-flight checks.
     *
     * @return array{
     *     calls_used: int,
     *     hourly_limit: int,
     *     remaining: int,
     *     usage_percent: int,
     *     header_usage: array{call_count: int, total_cputime: int, total_time: int},
     *     header_peak_percent: int,
     *     is_throttled: bool,
     *     resets_at: string
     * }
     */
    public function getStatus(int $userId, ?int $pageId = null): array
    {
        $hourlyLimit = max(1, (int) config('facebook.rate_limit.per_user_hourly', 180));
        $thresholdPercent = (int) config('facebook.rate_limit.usage_header_threshold_percent', 80);

        $callsUsed = (int) Cache::get($this->hourlyBucketKey($userId), 0);
        /** @var array{call_count?: int, total_cputime?: int, total_time?: int} $headerUsage */
        $headerUsage = Cache::get($this->usageHeaderKey($userId, $pageId), [
            'call_count' => 0,
            'total_cputime' => 0,
            'total_time' => 0,
        ]);

        $normalizedHeader = [
            'call_count' => (int) ($headerUsage['call_count'] ?? 0),
            'total_cputime' => (int) ($headerUsage['total_cputime'] ?? 0),
            'total_time' => (int) ($headerUsage['total_time'] ?? 0),
        ];

        $headerPeak = max($normalizedHeader['call_count'], $normalizedHeader['total_cputime'], $normalizedHeader['total_time']);
        $cooldownUntil = Cache::get($this->cooldownKey($userId, $pageId));

        $isThrottled = $callsUsed >= $hourlyLimit
            || $headerPeak >= $thresholdPercent
            || $cooldownUntil !== null;

        $usagePercent = max(
            (int) min(100, round(($callsUsed / $hourlyLimit) * 100)),
            $headerPeak
        );

        return [
            'calls_used' => $callsUsed,
            'hourly_limit' => $hourlyLimit,
            'remaining' => max(0, $hourlyLimit - $callsUsed),
            'usage_percent' => $usagePercent,
            'header_usage' => $normalizedHeader,
            'header_peak_percent' => $headerPeak,
            'is_throttled' => $isThrottled,
            'resets_at' => is_string($cooldownUntil) ? $cooldownUntil : now()->endOfHour()->toIso8601String(),
        ];
    }

    /**
     * Parse Meta's `X-App-Usage` and `X-Page-Usage` JSON headers.
     *
     * @param  array<string, array<int, string>|string>  $headers
     * @return array<string, int>
     */
    private function parseUsageHeaders(array $headers): array
    {
        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);
        $rawHeader = $normalizedHeaders['x-page-usage'][0]
            ?? $normalizedHeaders['x-page-usage']
            ?? $normalizedHeaders['x-app-usage'][0]
            ?? $normalizedHeaders['x-app-usage']
            ?? null;

        if (! is_string($rawHeader) || $rawHeader === '') {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($rawHeader, true);

        if (! is_array($decoded)) {
            return [];
        }

        return [
            'call_count' => (int) ($decoded['call_count'] ?? 0),
            'total_cputime' => (int) ($decoded['total_cputime'] ?? 0),
            'total_time' => (int) ($decoded['total_time'] ?? 0),
        ];
    }

    private function hourlyBucketKey(int $userId): string
    {
        return sprintf('fb_rate_limit:user:%d:hour:%s', $userId, now()->format('YmdH'));
    }

    private function usageHeaderKey(int $userId, ?int $pageId): string
    {
        return sprintf('fb_rate_limit:headers:user:%d:page:%s', $userId, $pageId ?? 'global');
    }

    private function cooldownKey(int $userId, ?int $pageId): string
    {
        return sprintf('fb_rate_limit:cooldown:user:%d:page:%s', $userId, $pageId ?? 'global');
    }
}
