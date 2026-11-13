<?php

namespace App\DTO\Social;

use App\Enums\AccountKind;

final readonly class DiscoveredAccount
{
    /**
     * @param  array<int, string>  $capabilities
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public string $externalId,
        public string $name,
        public AccountKind $kind,
        public TokenSet $tokenSet,
        public ?string $username = null,
        public ?string $avatarUrl = null,
        public ?string $parentExternalId = null,
        public array $capabilities = [],
        public array $settings = [],
    ) {}
}
