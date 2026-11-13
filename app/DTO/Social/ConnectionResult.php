<?php

namespace App\DTO\Social;

final readonly class ConnectionResult
{
    /**
     * @param  array<int, DiscoveredAccount>  $accounts
     */
    public function __construct(
        public array $accounts,
        public ?TokenSet $userTokenSet = null,
    ) {}
}
