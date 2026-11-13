<?php

namespace App\Exceptions;

use App\Enums\ErrorClass;
use RuntimeException;
use Throwable;

class SocialProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ErrorClass $errorClass = ErrorClass::Fatal,
        public readonly int $httpStatus = 400,
        public readonly ?string $providerCode = null,
        public readonly ?int $retryAfterSeconds = null,
        public readonly ?string $providerRequestId = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }

    public function isRetryable(): bool
    {
        return $this->errorClass->isRetryable();
    }
}
