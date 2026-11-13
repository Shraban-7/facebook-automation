<?php

namespace App\Actions\Social;

use App\DTO\Social\DiscoveredAccount;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Social\TokenVault;
use Illuminate\Support\Facades\DB;

class ConnectAccount
{
    public function __construct(
        private readonly TokenVault $tokenVault,
    ) {}

    public function handle(Workspace $workspace, string $providerKey, DiscoveredAccount $discovered): SocialAccount
    {
        return DB::transaction(function () use ($workspace, $providerKey, $discovered): SocialAccount {
            $account = SocialAccount::query()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'provider' => $providerKey,
                    'external_id' => $discovered->externalId,
                ],
                [
                    'kind' => $discovered->kind->value,
                    'name' => $discovered->name,
                    'username' => $discovered->username,
                    'avatar_url' => $discovered->avatarUrl,
                    'status' => 'active',
                    'capabilities' => $discovered->capabilities,
                    'settings' => $discovered->settings,
                    'token_expires_at' => $discovered->tokenSet->expiresAt,
                    'last_synced_at' => now(),
                ]
            );

            $this->tokenVault->store($account, $discovered->tokenSet);

            return $account->refresh();
        });
    }
}
