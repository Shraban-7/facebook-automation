<?php

namespace App\Contracts\Social;

use App\DTO\Social\AuthState;
use App\DTO\Social\ConnectionResult;
use App\DTO\Social\ProviderCapabilities;
use App\DTO\Social\PublishResult;
use App\DTO\Social\TokenSet;
use App\DTO\Social\ValidationResult;
use App\Models\PostTarget;
use App\Models\SocialAccount;

interface SocialProvider
{
    public function key(): string;

    public function capabilities(): ProviderCapabilities;

    public function authorizationUrl(AuthState $state): string;

    /**
     * @param  array<string, mixed>  $query
     */
    public function handleCallback(array $query, AuthState $state): ConnectionResult;

    public function refreshToken(SocialAccount $account): TokenSet;

    public function revoke(SocialAccount $account): void;

    public function validate(PostTarget $target): ValidationResult;

    public function publish(PostTarget $target, string $idempotencyKey): PublishResult;

    public function delete(PostTarget $target): void;
}
