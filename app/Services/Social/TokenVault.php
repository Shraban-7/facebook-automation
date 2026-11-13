<?php

namespace App\Services\Social;

use App\DTO\Social\TokenSet;
use App\Models\OAuthToken;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\DB;

class TokenVault
{
    /**
     * Persist or rotate an encrypted token set in the isolated `oauth_tokens` vault.
     */
    public function store(SocialAccount $account, TokenSet $tokenSet, int $keyVersion = 1): OAuthToken
    {
        return DB::transaction(function () use ($account, $tokenSet, $keyVersion): OAuthToken {
            $token = OAuthToken::updateOrCreate(
                ['social_account_id' => $account->id],
                [
                    'access_token' => $tokenSet->accessToken,
                    'refresh_token' => $tokenSet->refreshToken,
                    'token_type' => $tokenSet->tokenType,
                    'scopes' => $tokenSet->scopes,
                    'expires_at' => $tokenSet->expiresAt,
                    'refresh_expires_at' => $tokenSet->refreshExpiresAt,
                    'key_version' => $keyVersion,
                    'rotated_at' => now(),
                ]
            );

            $account->update([
                'token_expires_at' => $tokenSet->expiresAt,
            ]);

            return $token;
        });
    }

    /**
     * Retrieve the decrypted access token inside a queued job or provider adapter.
     * Never pass this value to the frontend or logs.
     */
    public function getAccessToken(SocialAccount $account): ?string
    {
        /** @var OAuthToken|null $token */
        $token = $account->oauthToken()->first();

        return $token?->access_token;
    }

    public function getRefreshToken(SocialAccount $account): ?string
    {
        /** @var OAuthToken|null $token */
        $token = $account->oauthToken()->first();

        return $token?->refresh_token;
    }

    /**
     * Purge all token rows for an account upon disconnect or Meta data-deletion request.
     */
    public function purge(SocialAccount $account): void
    {
        $account->oauthToken()->delete();
        $account->update([
            'status' => 'revoked',
            'token_expires_at' => null,
        ]);
    }
}
