<?php

namespace App\Enums;

enum ErrorClass: string
{
    case Retryable = 'retryable';
    case RateLimited = 'rate_limited';
    case AuthExpired = 'auth_expired';
    case PermissionMissing = 'permission_missing';
    case ContentRejected = 'content_rejected';
    case Fatal = 'fatal';

    public function isRetryable(): bool
    {
        return match ($this) {
            self::Retryable, self::RateLimited => true,
            default => false,
        };
    }

    public function requiresReauth(): bool
    {
        return $this === self::AuthExpired;
    }
}
