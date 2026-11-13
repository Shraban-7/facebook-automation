<?php

namespace App\Services\Social\Providers\Instagram;

use App\Contracts\Social\SocialProvider;
use App\Contracts\Social\SupportsAnalytics;
use App\Contracts\Social\SupportsComments;
use App\DTO\Social\AuthState;
use App\DTO\Social\ConnectionResult;
use App\DTO\Social\DateRange;
use App\DTO\Social\DiscoveredAccount;
use App\DTO\Social\ProviderCapabilities;
use App\DTO\Social\PublishResult;
use App\DTO\Social\TokenSet;
use App\DTO\Social\ValidationResult;
use App\Enums\AccountKind;
use App\Enums\ErrorClass;
use App\Exceptions\SocialProviderException;
use App\Models\Comment;
use App\Models\PostTarget;
use App\Models\SocialAccount;
use App\Services\Social\MetricMapper;
use App\Services\Social\TokenVault;
use Illuminate\Support\Facades\Http;

class InstagramProvider implements SocialProvider, SupportsAnalytics, SupportsComments
{
    public function __construct(
        private readonly TokenVault $tokenVault,
        private readonly MetricMapper $metricMapper,
    ) {}

    public function key(): string
    {
        return 'instagram';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            text: false,
            images: true,
            video: true,
            carousel: true,
            link: false,
            maxTextLength: 2200,
            maxImages: 10,
            maxVideoSeconds: 90,
            supportsNativeScheduling: false,
            supportsMessaging: true,
            supportsComments: true,
            supportsAnalytics: true,
            supportsWebhooks: true,
            requiresPublicMediaUrl: true,
        );
    }

    public function authorizationUrl(AuthState $state): string
    {
        $version = (string) config('social.providers.instagram.api_version', 'v21.0');
        $oauthBase = rtrim((string) config('social.providers.instagram.oauth_url', 'https://www.facebook.com'), '/');
        /** @var array<int, string> $scopes */
        $scopes = config('social.providers.instagram.scopes', []);

        return sprintf(
            '%s/%s/dialog/oauth?%s',
            $oauthBase,
            $version,
            http_build_query([
                'client_id' => (string) config('social.providers.instagram.client_id'),
                'redirect_uri' => $state->redirectUri,
                'state' => $state->state,
                'response_type' => 'code',
                'scope' => implode(',', $scopes),
            ])
        );
    }

    public function handleCallback(array $query, AuthState $state): ConnectionResult
    {
        $code = (string) ($query['code'] ?? '');
        if ($code === '') {
            throw new SocialProviderException('Missing Instagram OAuth authorization code.', ErrorClass::Fatal, 400);
        }

        $baseUrl = rtrim((string) config('social.providers.instagram.base_url', 'https://graph.facebook.com'), '/');
        $version = trim((string) config('social.providers.instagram.api_version', 'v21.0'), '/');

        $tokenRes = Http::timeout(15)->get("{$baseUrl}/{$version}/oauth/access_token", [
            'client_id' => (string) config('social.providers.instagram.client_id'),
            'client_secret' => (string) config('social.providers.instagram.client_secret'),
            'redirect_uri' => $state->redirectUri,
            'code' => $code,
        ]);

        if ($tokenRes->failed()) {
            throw new SocialProviderException('Failed to exchange Instagram OAuth code.', ErrorClass::AuthExpired, $tokenRes->status());
        }

        $accessToken = (string) ($tokenRes->json('access_token') ?? '');
        $pagesRes = Http::timeout(15)->get("{$baseUrl}/{$version}/me/accounts", [
            'fields' => 'id,name,access_token,instagram_business_account{id,username,name,profile_picture_url,followers_count}',
            'access_token' => $accessToken,
        ]);

        if ($pagesRes->failed()) {
            throw new SocialProviderException('Failed to discover linked Instagram Business accounts.', ErrorClass::Fatal, $pagesRes->status());
        }

        $accounts = [];
        /** @var array<int, string> $scopes */
        $scopes = config('social.providers.instagram.scopes', []);
        $tokenSet = new TokenSet(accessToken: $accessToken, scopes: $scopes, expiresAt: now()->addDays(60));

        foreach ((array) ($pagesRes->json('data') ?? []) as $page) {
            $ig = $page['instagram_business_account'] ?? null;
            if (! is_array($ig) || empty($ig['id'])) {
                continue;
            }

            $accounts[] = new DiscoveredAccount(
                externalId: (string) $ig['id'],
                name: (string) ($ig['name'] ?? $ig['username'] ?? 'Instagram Account'),
                kind: AccountKind::Business,
                tokenSet: new TokenSet(
                    accessToken: (string) ($page['access_token'] ?? $accessToken),
                    scopes: $scopes,
                    expiresAt: now()->addDays(60),
                ),
                username: isset($ig['username']) ? (string) $ig['username'] : null,
                avatarUrl: isset($ig['profile_picture_url']) ? (string) $ig['profile_picture_url'] : null,
                parentExternalId: (string) $page['id'],
                settings: ['followers_count' => (int) ($ig['followers_count'] ?? 0)],
            );
        }

        return new ConnectionResult(accounts: $accounts, userTokenSet: $tokenSet);
    }

    public function refreshToken(SocialAccount $account): TokenSet
    {
        $token = $this->tokenVault->getAccessToken($account);
        if ($token === null) {
            throw new SocialProviderException('Missing Instagram access token.', ErrorClass::AuthExpired, 401);
        }

        return new TokenSet(
            accessToken: $token,
            scopes: config('social.providers.instagram.scopes', []),
            expiresAt: now()->addDays(60),
        );
    }

    public function revoke(SocialAccount $account): void
    {
        // Revoked via parent Meta app permissions
    }

    public function validate(PostTarget $target): ValidationResult
    {
        $errors = [];
        $body = $target->effectiveBody();
        $caps = $this->capabilities();

        if (mb_strlen($body) > $caps->maxTextLength) {
            $errors[] = "Caption exceeds Instagram's {$caps->maxTextLength} character limit.";
        }

        $mediaUrls = $target->options['media_urls'] ?? [];
        if (! is_array($mediaUrls) || $mediaUrls === []) {
            $errors[] = 'Instagram requires at least one public HTTPS image or video URL.';
        } elseif (count($mediaUrls) > $caps->maxImages) {
            $errors[] = "Instagram carousels support at most {$caps->maxImages} items.";
        }

        return $errors === [] ? ValidationResult::ok() : ValidationResult::fail($errors);
    }

    /**
     * Instagram 2-step container publish flow:
     * 1. Create media container (`/{ig-user-id}/media`)
     * 2. Publish container (`/{ig-user-id}/media_publish`)
     */
    public function publish(PostTarget $target, string $idempotencyKey): PublishResult
    {
        $account = $target->socialAccount;
        $token = $this->tokenVault->getAccessToken($account);
        if ($token === null) {
            throw new SocialProviderException('Missing Instagram access token.', ErrorClass::AuthExpired, 401);
        }

        $baseUrl = rtrim((string) config('social.providers.instagram.base_url', 'https://graph.facebook.com'), '/');
        $version = trim((string) config('social.providers.instagram.api_version', 'v21.0'), '/');
        $mediaUrls = is_array($target->options['media_urls'] ?? null) ? $target->options['media_urls'] : [];

        if ($mediaUrls === []) {
            throw new SocialProviderException('Instagram requires a public image_url or video_url.', ErrorClass::ContentRejected, 400);
        }

        $containerRes = Http::timeout(20)->post("{$baseUrl}/{$version}/{$account->external_id}/media", [
            'image_url' => (string) $mediaUrls[0],
            'caption' => $target->effectiveBody(),
            'access_token' => $token,
        ]);

        if ($containerRes->failed()) {
            throw $this->mapHttpError($containerRes->status(), $containerRes->json() ?? []);
        }

        $creationId = (string) ($containerRes->json('id') ?? '');

        $publishRes = Http::timeout(20)->post("{$baseUrl}/{$version}/{$account->external_id}/media_publish", [
            'creation_id' => $creationId,
            'access_token' => $token,
        ]);

        if ($publishRes->failed()) {
            throw $this->mapHttpError($publishRes->status(), $publishRes->json() ?? []);
        }

        $mediaId = (string) ($publishRes->json('id') ?? $creationId);

        return new PublishResult(
            externalId: $mediaId,
            externalUrl: 'https://www.instagram.com/',
            rawResponse: $publishRes->json() ?? [],
        );
    }

    public function delete(PostTarget $target): void
    {
        // Instagram Graph API does not support deleting published feed media via API
    }

    public function replyToComment(Comment $comment, string $text): Comment
    {
        $token = $this->tokenVault->getAccessToken($comment->socialAccount);
        if ($token === null) {
            throw new SocialProviderException('Missing Instagram token.', ErrorClass::AuthExpired, 401);
        }

        $baseUrl = rtrim((string) config('social.providers.instagram.base_url', 'https://graph.facebook.com'), '/');
        $version = trim((string) config('social.providers.instagram.api_version', 'v21.0'), '/');

        $res = Http::timeout(15)->post("{$baseUrl}/{$version}/{$comment->external_id}/replies", [
            'message' => $text,
            'access_token' => $token,
        ]);

        if ($res->failed()) {
            throw $this->mapHttpError($res->status(), $res->json() ?? []);
        }

        $comment->update(['replied_at' => now()]);

        return $comment;
    }

    public function setCommentVisibility(Comment $comment, bool $hidden): void
    {
        $token = $this->tokenVault->getAccessToken($comment->socialAccount);
        if ($token === null) {
            throw new SocialProviderException('Missing Instagram token.', ErrorClass::AuthExpired, 401);
        }

        $baseUrl = rtrim((string) config('social.providers.instagram.base_url', 'https://graph.facebook.com'), '/');
        $version = trim((string) config('social.providers.instagram.api_version', 'v21.0'), '/');

        $res = Http::timeout(15)->post("{$baseUrl}/{$version}/{$comment->external_id}", [
            'hide' => $hidden,
            'access_token' => $token,
        ]);

        if ($res->failed()) {
            throw $this->mapHttpError($res->status(), $res->json() ?? []);
        }

        $comment->update(['is_hidden' => $hidden]);
    }

    public function fetchMetrics(SocialAccount $account, DateRange $range): iterable
    {
        $token = $this->tokenVault->getAccessToken($account);
        if ($token === null) {
            throw new SocialProviderException('Missing Instagram token.', ErrorClass::AuthExpired, 401);
        }

        $baseUrl = rtrim((string) config('social.providers.instagram.base_url', 'https://graph.facebook.com'), '/');
        $version = trim((string) config('social.providers.instagram.api_version', 'v21.0'), '/');

        $res = Http::timeout(15)->get("{$baseUrl}/{$version}/{$account->external_id}/insights", [
            'metric' => 'reach,impressions,profile_views',
            'period' => 'day',
            'since' => $range->since->timestamp,
            'until' => $range->until->timestamp,
            'access_token' => $token,
        ]);

        if ($res->failed()) {
            throw $this->mapHttpError($res->status(), $res->json() ?? []);
        }

        return $this->metricMapper->normalizeMetaSeries((array) ($res->json('data') ?? []));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapHttpError(int $status, array $payload): SocialProviderException
    {
        $code = (int) ($payload['error']['code'] ?? $status);
        $message = (string) ($payload['error']['message'] ?? 'Instagram Graph API error');

        $class = match (true) {
            $code === 190 || $status === 401 => ErrorClass::AuthExpired,
            $status === 403 || ($code >= 200 && $code <= 299) => ErrorClass::PermissionMissing,
            $status === 429 || in_array($code, [4, 17, 32, 613], true) => ErrorClass::RateLimited,
            $status >= 500 => ErrorClass::Retryable,
            default => ErrorClass::ContentRejected,
        };

        return new SocialProviderException($message, $class, $status, (string) $code);
    }
}
