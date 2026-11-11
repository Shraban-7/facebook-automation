<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class FacebookGraphException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $rawError
     */
    public function __construct(
        string $message,
        public readonly int $graphCode = 0,
        public readonly ?int $graphSubcode = null,
        public readonly int $httpStatus = 400,
        public readonly ?string $fbTraceId = null,
        public readonly array $rawError = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $graphCode, $previous);
    }

    /**
     * Build a structured exception from a Graph API error response payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromResponse(array $payload, int $httpStatus = 400): self
    {
        /** @var array<string, mixed> $error */
        $error = is_array($payload['error'] ?? null) ? $payload['error'] : $payload;

        $code = (int) ($error['code'] ?? $httpStatus);
        $subcode = isset($error['error_subcode']) ? (int) $error['error_subcode'] : null;
        $message = (string) ($error['message'] ?? 'Unknown Meta Graph API error');
        $traceId = isset($error['fbtrace_id']) ? (string) $error['fbtrace_id'] : null;

        return new self(
            message: $message,
            graphCode: $code,
            graphSubcode: $subcode,
            httpStatus: $httpStatus,
            fbTraceId: $traceId,
            rawError: $error,
        );
    }

    /**
     * Determine if the error indicates an expired, revoked, or invalid OAuth token (Code 190).
     */
    public function isTokenExpiredOrInvalid(): bool
    {
        return $this->graphCode === 190 || $this->httpStatus === 401;
    }

    /**
     * Determine if the error indicates missing Graph API permissions (Code 10 or 200-299).
     */
    public function isPermissionMissing(): bool
    {
        if ($this->graphCode === 10 && $this->graphSubcode !== 2018278) {
            return true;
        }

        return $this->graphCode >= 200 && $this->graphCode <= 299;
    }

    /**
     * Determine if the error indicates a Meta Graph API or Page rate limit was reached.
     */
    public function isRateLimited(): bool
    {
        return in_array($this->graphCode, [4, 17, 32, 613, 80000, 80001, 80002, 80006], true)
            || $this->httpStatus === 429;
    }

    /**
     * Determine if the error indicates a violation of the 24-hour standard messaging window.
     */
    public function isOutsideMessagingWindow(): bool
    {
        return ($this->graphCode === 10 && $this->graphSubcode === 2018278)
            || $this->graphCode === 551
            || $this->graphSubcode === 2018001;
    }

    /**
     * Determine if the failure is transient and safe for queued retry with exponential backoff.
     */
    public function isRetryable(): bool
    {
        if ($this->isTokenExpiredOrInvalid() || $this->isPermissionMissing() || $this->isOutsideMessagingWindow()) {
            return false;
        }

        if ($this->isRateLimited()) {
            return true;
        }

        return in_array($this->graphCode, [1, 2], true) || $this->httpStatus >= 500;
    }

    /**
     * Human-readable category for UI and logs.
     */
    public function errorCategory(): string
    {
        return match (true) {
            $this->isTokenExpiredOrInvalid() => 'token_expired',
            $this->isPermissionMissing() => 'permission_missing',
            $this->isRateLimited() => 'rate_limited',
            $this->isOutsideMessagingWindow() => 'outside_24h_window',
            $this->isRetryable() => 'transient_server_error',
            default => 'graph_api_error',
        };
    }

    /**
     * Actionable remediation guidance for operators.
     */
    public function remediationHint(): string
    {
        return match ($this->errorCategory()) {
            'token_expired' => 'Reconnect the Facebook Page via OAuth or trigger a token refresh using a valid user token.',
            'permission_missing' => 'Ensure pages_manage_posts, pages_read_engagement, pages_messaging, and pages_manage_metadata are granted and approved in Meta App Review.',
            'rate_limited' => 'Graph API quota reached. Queued jobs will back off automatically until the hourly window resets.',
            'outside_24h_window' => 'Meta Messenger policy prohibits standard replies more than 24 hours after the user\'s last message.',
            'transient_server_error' => 'Temporary Meta Graph API instability. The job will retry automatically with exponential backoff.',
            default => 'Check the Graph API payload parameters and Page status in the Activity Log.',
        };
    }
}
