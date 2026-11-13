<?php

namespace App\Actions\Social;

use App\Models\SocialAccount;
use App\Services\Social\ProviderManager;
use App\Services\Social\TokenVault;
use Throwable;

class DisconnectAccount
{
    public function __construct(
        private readonly ProviderManager $providers,
        private readonly TokenVault $tokenVault,
    ) {}

    public function handle(SocialAccount $account): void
    {
        try {
            $this->providers->provider($account->provider)->revoke($account);
        } catch (Throwable) {
            // Always purge local credentials even if remote revocation fails.
        }

        $this->tokenVault->purge($account);

        $account->update([
            'status' => 'revoked',
        ]);
    }
}
