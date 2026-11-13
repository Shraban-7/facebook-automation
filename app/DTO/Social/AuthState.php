<?php

namespace App\DTO\Social;

final readonly class AuthState
{
    public function __construct(
        public string $state,
        public int $workspaceId,
        public int $userId,
        public string $provider,
        public string $redirectUri,
        public ?string $codeVerifier = null,
        public ?string $returnTo = null,
    ) {}
}
