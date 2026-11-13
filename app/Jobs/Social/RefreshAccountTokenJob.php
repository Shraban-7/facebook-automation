<?php

namespace App\Jobs\Social;

use App\Exceptions\SocialProviderException;
use App\Models\SocialAccount;
use App\Services\Social\ProviderManager;
use App\Services\Social\TokenVault;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshAccountTokenJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public readonly int $socialAccountId,
    ) {
        $this->onQueue('tokens');
    }

    public function handle(ProviderManager $providers, TokenVault $tokenVault): void
    {
        $account = SocialAccount::query()->find($this->socialAccountId);
        if (! $account || $account->status === 'revoked') {
            return;
        }

        try {
            $tokenSet = $providers->provider($account->provider)->refreshToken($account);
            $tokenVault->store($account, $tokenSet);

            $account->update([
                'status' => 'active',
                'token_expires_at' => $tokenSet->expiresAt,
                'last_synced_at' => now(),
            ]);
        } catch (SocialProviderException $e) {
            if ($e->errorClass->requiresReauth()) {
                $account->update([
                    'status' => 'reauth_required',
                    'last_error' => $e->getMessage(),
                    'last_error_at' => now(),
                ]);
            }
        }
    }
}
