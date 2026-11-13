<?php

namespace App\DTO\Social;

use Carbon\CarbonInterface;

final readonly class TokenSet
{
    /**
     * @param  array<int, string>  $scopes
     */
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken = null,
        public string $tokenType = 'Bearer',
        public array $scopes = [],
        public ?CarbonInterface $expiresAt = null,
        public ?CarbonInterface $refreshExpiresAt = null,
    ) {}
}
