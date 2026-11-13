<?php

namespace App\Services\Social;

use Illuminate\Support\Facades\Cache;

class CircuitBreaker
{
    public function isOpen(string $provider): bool
    {
        $openedUntil = Cache::get($this->openKey($provider));

        if (! is_string($openedUntil)) {
            return false;
        }

        return now()->lessThan($openedUntil);
    }

    public function recordSuccess(string $provider): void
    {
        Cache::forget($this->failureKey($provider));
        Cache::forget($this->openKey($provider));
    }

    public function recordFailure(string $provider): void
    {
        $threshold = max(1, (int) config('social.circuit_breaker.failure_threshold', 5));
        $cooldownSeconds = max(10, (int) config('social.circuit_breaker.cooldown_seconds', 120));

        $failures = (int) Cache::get($this->failureKey($provider), 0) + 1;
        Cache::put($this->failureKey($provider), $failures, $cooldownSeconds * 2);

        if ($failures >= $threshold) {
            Cache::put(
                $this->openKey($provider),
                now()->addSeconds($cooldownSeconds)->toIso8601String(),
                $cooldownSeconds
            );
        }
    }

    /**
     * @return array{provider: string, state: string, failures: int, open_until: ?string}
     */
    public function status(string $provider): array
    {
        $openUntil = Cache::get($this->openKey($provider));
        $isOpen = is_string($openUntil) && now()->lessThan($openUntil);

        return [
            'provider' => $provider,
            'state' => $isOpen ? 'open' : 'closed',
            'failures' => (int) Cache::get($this->failureKey($provider), 0),
            'open_until' => $isOpen ? $openUntil : null,
        ];
    }

    private function failureKey(string $provider): string
    {
        return "social_cb:failures:{$provider}";
    }

    private function openKey(string $provider): string
    {
        return "social_cb:open:{$provider}";
    }
}
