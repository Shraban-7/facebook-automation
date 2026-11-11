<?php

namespace App\Services\Facebook;

use App\Models\ActivityLog;
use App\Models\FacebookPage;
use App\Models\User;

class ActivityLogService
{
    /**
     * Record a structured activity log entry while scrubbing sensitive credentials.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function record(array $attributes): ActivityLog
    {
        $context = is_array($attributes['context'] ?? null)
            ? $this->sanitizeContext($attributes['context'])
            : null;

        return ActivityLog::create([
            'user_id' => $attributes['user_id'] ?? null,
            'facebook_page_id' => $attributes['facebook_page_id'] ?? null,
            'category' => (string) ($attributes['category'] ?? 'api_call'),
            'action' => (string) ($attributes['action'] ?? 'graph.request'),
            'level' => (string) ($attributes['level'] ?? 'info'),
            'method' => isset($attributes['method']) ? (string) $attributes['method'] : null,
            'endpoint' => isset($attributes['endpoint']) ? (string) $attributes['endpoint'] : null,
            'http_status' => isset($attributes['http_status']) ? (int) $attributes['http_status'] : null,
            'graph_error_code' => isset($attributes['graph_error_code']) ? (int) $attributes['graph_error_code'] : null,
            'graph_error_subcode' => isset($attributes['graph_error_subcode']) ? (int) $attributes['graph_error_subcode'] : null,
            'duration_ms' => isset($attributes['duration_ms']) ? (int) $attributes['duration_ms'] : null,
            'rate_limit_usage' => is_array($attributes['rate_limit_usage'] ?? null) ? $attributes['rate_limit_usage'] : null,
            'message' => (string) ($attributes['message'] ?? ''),
            'context' => $context,
            'ip_address' => isset($attributes['ip_address']) ? (string) $attributes['ip_address'] : request()->ip(),
        ]);
    }

    /**
     * Log an outbound Graph API call or failure.
     *
     * @param  array<string, mixed>|null  $rateUsage
     * @param  array<string, mixed>  $context
     */
    public function logApiCall(
        string $action,
        string $method,
        string $endpoint,
        int $httpStatus,
        int $durationMs,
        string $message,
        ?User $user = null,
        ?FacebookPage $page = null,
        ?int $graphErrorCode = null,
        ?int $graphErrorSubcode = null,
        ?array $rateUsage = null,
        array $context = [],
    ): ActivityLog {
        $level = match (true) {
            $graphErrorCode === 190 => 'critical',
            $httpStatus >= 400 || $graphErrorCode !== null => 'error',
            default => 'info',
        };

        return $this->record([
            'user_id' => $user->id ?? $page?->user_id,
            'facebook_page_id' => $page?->id,
            'category' => $level === 'info' ? 'api_call' : 'error',
            'action' => $action,
            'level' => $level,
            'method' => $method,
            'endpoint' => $endpoint,
            'http_status' => $httpStatus,
            'graph_error_code' => $graphErrorCode,
            'graph_error_subcode' => $graphErrorSubcode,
            'duration_ms' => $durationMs,
            'rate_limit_usage' => $rateUsage,
            'message' => $message,
            'context' => $context,
        ]);
    }

    /**
     * Recursively redact tokens and secrets from logged context arrays.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $data): array
    {
        $sensitiveKeys = ['access_token', 'client_secret', 'appsecret_proof', 'fb_exchange_token', 'code'];
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeContext($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
