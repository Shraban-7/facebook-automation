<?php

namespace App\Services\Social\Providers\Threads;

use App\Contracts\Social\SocialProvider;
use App\Contracts\Social\SupportsAnalytics;
use App\Contracts\Social\SupportsComments;
use App\DTO\Social\AuthState;
use App\DTO\Social\ConnectionResult;
use App\DTO\Social\DateRange;
use App\DTO\Social\DiscoveredAccount;
use App\DTO\Social\NormalizedMetric;
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
use App\Services\Social\TokenVault;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ThreadsProvider implements SocialProvider, SupportsAnalytics, SupportsComments
{
    public function __construct(
        private readonly TokenVault $tokenVault,
    ) {}

    public function key(): string
    {
        return 'threads';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            text: true,
            images: true,
            video: true,
            carousel: true,
            link: true,
            maxTextLength: 500,
            maxImages: 20,
            maxVideoSeconds: 300,
            supportsNativeScheduling: false,
            supportsMessaging: false,
            supportsComments: true,
            supportsAnalytics: true,
            supportsWebhooks: false,
            requiresPublicMediaUrl: true,
        );
    }

    public function authorizationUrl(AuthState $state): string
    {
        $oauthBase = rtrim((string) config('social.providers.threads.oauth_url', 'https://threads.net'), '/');
        /** @var array<int, string> $scopes */
        $scopes = config('social.providers.threads.scopes', []);

        return sprintf(
            '%s/oauth/authorize?%s',
            $oauthBase,
            http_build_query([
                'client_id' => (string) config('social.providers.threads.client_id'),
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
            throw new SocialProviderException('Missing Threads OAuth authorization code.', ErrorClass::Fatal, 400);
        }

        $baseUrl = rtrim((string) config('social.providers.threads.base_url', 'https://graph.threads.net'), '/');
        $version = trim((string) config('social.providers.threads.api_version', 'v1.0'), '/');

        $tokenRes = Http::asForm()->timeout(15)->post("{$baseUrl}/oauth/access_token", [
            'client_id' => (string) config('social.providers.threads.client_id'),
            'client_secret' => (string) config('social.providers.threads.client_secret'),
            'grant_type' => 'authorization_code',
            'redirect_uri' => $state->redirectUri,
            'code' => $code,
        ]);

        if ($tokenRes->failed()) {
            throw $this->mapHttpFailure($tokenRes->status(), $tokenRes->json(), 'Threads OAuth token exchange failed.');
        }

        $shortToken = (string) $tokenRes->json('access_token');

        $longTokenRes = Http::timeout(15)->get("{$baseUrl}/access_token", [
            'grant_type' => 'th_exchange_token',
            'client_secret' => (string) config('social.providers.threads.client_secret'),
            'access_token' => $shortToken,
        ]);

        $accessToken = $longTokenRes->successful()
            ? (string) $longTokenRes->json('access_token', $shortToken)
            : $shortToken;
        $expiresIn = $longTokenRes->successful()
            ? (int) $longTokenRes->json('expires_in', 5184000)
            : (int) $tokenRes->json('expires_in', 3600);

        $meRes = Http::timeout(15)->get("{$baseUrl}/{$version}/me", [
            'fields' => 'id,username,name,threads_profile_picture_url',
            'access_token' => $accessToken,
        ]);

        if ($meRes->failed()) {
            throw $this->mapHttpFailure($meRes->status(), $meRes->json(), 'Failed to fetch Threads profile.');
        }

        $profile = $meRes->json();
        $id = (string) ($profile['id'] ?? '');
        if ($id === '') {
            throw new SocialProviderException('Threads profile response missing id.', ErrorClass::Fatal, 422);
        }

        /** @var array<int, string> $scopes */
        $scopes = config('social.providers.threads.scopes', []);

        return new ConnectionResult(
            accounts: [
                new DiscoveredAccount(
                    externalId: $id,
                    name: (string) ($profile['name'] ?? $profile['username'] ?? 'Threads Profile'),
                    kind: AccountKind::Profile,
                    tokenSet: new TokenSet(
                        accessToken: $accessToken,
                        expiresAt: now()->addSeconds($expiresIn),
                        scopes: $scopes,
                    ),
                    username: isset($profile['username']) ? '@'.$profile['username'] : null,
                    avatarUrl: $profile['threads_profile_picture_url'] ?? null,
                ),
            ],
        );
    }

    public function refreshToken(SocialAccount $account): TokenSet
    {
        $currentToken = $this->tokenVault->getAccessToken($account);
        if (! $currentToken) {
            throw new SocialProviderException('No Threads token stored.', ErrorClass::AuthExpired, 401);
        }

        $baseUrl = rtrim((string) config('social.providers.threads.base_url', 'https://graph.threads.net'), '/');
        $res = Http::timeout(15)->get("{$baseUrl}/refresh_access_token", [
            'grant_type' => 'th_refresh_token',
            'access_token' => $currentToken,
        ]);

        if ($res->failed()) {
            throw $this->mapHttpFailure($res->status(), $res->json(), 'Failed to refresh Threads token.');
        }

        $expiresIn = (int) $res->json('expires_in', 5184000);

        return new TokenSet(
            accessToken: (string) $res->json('access_token', $currentToken),
            expiresAt: now()->addSeconds($expiresIn),
        );
    }

    public function revoke(SocialAccount $account): void
    {
        $this->tokenVault->purge($account);
    }

    public function validate(PostTarget $target): ValidationResult
    {
        $content = $target->content;
        $body = (string) ($target->body_override ?? $content->body ?? '');
        $errors = [];

        if (mb_strlen($body) > 500) {
            $errors[] = 'Threads text cannot exceed 500 characters.';
        }

        if ($body === '' && $content->mediaAssets->isEmpty()) {
            $errors[] = 'Threads post requires either text or media.';
        }

        return $errors === [] ? ValidationResult::ok() : ValidationResult::fail($errors);
    }

    public function publish(PostTarget $target, string $idempotencyKey): PublishResult
    {
        $validation = $this->validate($target);
        if (! $validation->valid) {
            throw new SocialProviderException(implode(' ', $validation->errors), ErrorClass::Fatal, 422);
        }

        $account = $target->socialAccount;
        $token = $this->tokenVault->getAccessToken($account);
        if (! $token) {
            throw new SocialProviderException('Missing Threads access token.', ErrorClass::AuthExpired, 401);
        }

        $baseUrl = rtrim((string) config('social.providers.threads.base_url', 'https://graph.threads.net'), '/');
        $version = trim((string) config('social.providers.threads.api_version', 'v1.0'), '/');
        $content = $target->content;
        $body = (string) ($target->body_override ?? $content->body ?? '');
        $media = $content->mediaAssets;

        $payload = [
            'access_token' => $token,
            'text' => $body,
        ];

        if ($media->isEmpty()) {
            $payload['media_type'] = 'TEXT';
        } elseif ($media->count() === 1) {
            $asset = $media->first();
            $url = Storage::disk($asset->disk)->url($asset->path);
            if (str_starts_with($asset->mime, 'video/')) {
                $payload['media_type'] = 'VIDEO';
                $payload['video_url'] = $url;
            } else {
                $payload['media_type'] = 'IMAGE';
                $payload['image_url'] = $url;
            }
        } else {
            $children = [];
            foreach ($media->take(20) as $asset) {
                $isVideo = str_starts_with($asset->mime, 'video/');
                $childRes = Http::timeout(20)->post("{$baseUrl}/{$version}/{$account->external_id}/threads", [
                    'access_token' => $token,
                    'is_carousel_item' => true,
                    'media_type' => $isVideo ? 'VIDEO' : 'IMAGE',
                    $isVideo ? 'video_url' : 'image_url' => Storage::disk($asset->disk)->url($asset->path),
                ]);
                if ($childRes->failed()) {
                    throw $this->mapHttpFailure($childRes->status(), $childRes->json(), 'Threads carousel item creation failed.');
                }
                $children[] = (string) $childRes->json('id');
            }
            $payload['media_type'] = 'CAROUSEL';
            $payload['children'] = implode(',', $children);
        }

        $containerRes = Http::timeout(20)->post("{$baseUrl}/{$version}/{$account->external_id}/threads", $payload);
        if ($containerRes->failed()) {
            throw $this->mapHttpFailure($containerRes->status(), $containerRes->json(), 'Threads container creation failed.');
        }

        $creationId = (string) $containerRes->json('id');

        $publishRes = Http::timeout(20)->post("{$baseUrl}/{$version}/{$account->external_id}/threads_publish", [
            'access_token' => $token,
            'creation_id' => $creationId,
        ]);

        if ($publishRes->failed()) {
            throw $this->mapHttpFailure($publishRes->status(), $publishRes->json(), 'Threads publish failed.');
        }

        $postId = (string) $publishRes->json('id', $creationId);

        return new PublishResult(
            externalId: $postId,
            externalUrl: "https://www.threads.net/post/{$postId}",
            rawResponse: $publishRes->json() ?? [],
        );
    }

    public function delete(PostTarget $target): void
    {
        if (! $target->external_id) {
            return;
        }
        $token = $this->tokenVault->getAccessToken($target->socialAccount);
        if (! $token) {
            return;
        }
        $baseUrl = rtrim((string) config('social.providers.threads.base_url', 'https://graph.threads.net'), '/');
        $version = trim((string) config('social.providers.threads.api_version', 'v1.0'), '/');

        Http::timeout(15)->delete("{$baseUrl}/{$version}/{$target->external_id}", [
            'access_token' => $token,
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    public function fetchComments(SocialAccount $account, ?string $externalPostId = null, ?string $cursor = null): array
    {
        $token = $this->tokenVault->getAccessToken($account);
        if (! $token || ! $externalPostId) {
            return [];
        }

        $baseUrl = rtrim((string) config('social.providers.threads.base_url', 'https://graph.threads.net'), '/');
        $version = trim((string) config('social.providers.threads.api_version', 'v1.0'), '/');

        $res = Http::timeout(15)->get("{$baseUrl}/{$version}/{$externalPostId}/replies", array_filter([
            'fields' => 'id,text,username,timestamp,hide_status',
            'access_token' => $token,
            'after' => $cursor,
        ]));

        if ($res->failed()) {
            throw $this->mapHttpFailure($res->status(), $res->json(), 'Failed to fetch Threads replies.');
        }

        return $res->json('data', []);
    }

    public function replyToComment(Comment $comment, string $text): Comment
    {
        $token = $this->tokenVault->getAccessToken($comment->socialAccount);
        if (! $token) {
            throw new SocialProviderException('Missing Threads access token.', ErrorClass::AuthExpired, 401);
        }

        $baseUrl = rtrim((string) config('social.providers.threads.base_url', 'https://graph.threads.net'), '/');
        $version = trim((string) config('social.providers.threads.api_version', 'v1.0'), '/');
        $accountId = $comment->socialAccount->external_id;

        $containerRes = Http::timeout(15)->post("{$baseUrl}/{$version}/{$accountId}/threads", [
            'media_type' => 'TEXT',
            'text' => $text,
            'reply_to_id' => $comment->external_id,
            'access_token' => $token,
        ]);

        if ($containerRes->failed()) {
            throw $this->mapHttpFailure($containerRes->status(), $containerRes->json(), 'Threads reply container failed.');
        }

        $publishRes = Http::timeout(15)->post("{$baseUrl}/{$version}/{$accountId}/threads_publish", [
            'creation_id' => $containerRes->json('id'),
            'access_token' => $token,
        ]);

        if ($publishRes->failed()) {
            throw $this->mapHttpFailure($publishRes->status(), $publishRes->json(), 'Threads reply publish failed.');
        }

        $comment->update(['replied_at' => now()]);

        return $comment;
    }

    public function setCommentVisibility(Comment $comment, bool $hidden): void
    {
        $token = $this->tokenVault->getAccessToken($comment->socialAccount);
        if (! $token) {
            throw new SocialProviderException('Missing Threads access token.', ErrorClass::AuthExpired, 401);
        }

        $baseUrl = rtrim((string) config('social.providers.threads.base_url', 'https://graph.threads.net'), '/');
        $version = trim((string) config('social.providers.threads.api_version', 'v1.0'), '/');

        Http::timeout(15)->post("{$baseUrl}/{$version}/{$comment->external_id}/manage_reply", [
            'hide' => $hidden,
            'access_token' => $token,
        ]);
    }

    public function fetchMetrics(SocialAccount $account, DateRange $range): iterable
    {
        $token = $this->tokenVault->getAccessToken($account);
        if (! $token) {
            return [];
        }

        $baseUrl = rtrim((string) config('social.providers.threads.base_url', 'https://graph.threads.net'), '/');
        $version = trim((string) config('social.providers.threads.api_version', 'v1.0'), '/');

        $res = Http::timeout(15)->get("{$baseUrl}/{$version}/{$account->external_id}/threads_insights", [
            'metric' => 'views,likes,replies,reposts,quotes,followers_count',
            'since' => $range->since->getTimestamp(),
            'until' => $range->until->getTimestamp(),
            'access_token' => $token,
        ]);

        if ($res->failed()) {
            return [];
        }

        $metrics = [];
        foreach ((array) $res->json('data', []) as $item) {
            $name = (string) ($item['name'] ?? '');
            $val = $item['total_value']['value'] ?? ($item['values'][0]['value'] ?? 0);
            if ($name !== '') {
                $key = match ($name) {
                    'views' => 'impressions',
                    'followers_count' => 'followers_total',
                    default => $name,
                };
                $metrics[] = new NormalizedMetric($key, $range->until, (float) $val);
            }
        }

        return $metrics;
    }

    /**
     * @return array<int, NormalizedMetric>
     */
    public function fetchPostMetrics(PostTarget $target): array
    {
        if (! $target->external_id) {
            return [];
        }
        $token = $this->tokenVault->getAccessToken($target->socialAccount);
        if (! $token) {
            return [];
        }

        $baseUrl = rtrim((string) config('social.providers.threads.base_url', 'https://graph.threads.net'), '/');
        $version = trim((string) config('social.providers.threads.api_version', 'v1.0'), '/');

        $res = Http::timeout(15)->get("{$baseUrl}/{$version}/{$target->external_id}/insights", [
            'metric' => 'views,likes,replies,reposts,quotes',
            'access_token' => $token,
        ]);

        if ($res->failed()) {
            return [];
        }

        $metrics = [];
        foreach ((array) $res->json('data', []) as $item) {
            $name = (string) ($item['name'] ?? '');
            $val = $item['values'][0]['value'] ?? 0;
            if ($name !== '') {
                $metrics[] = new NormalizedMetric($name === 'views' ? 'impressions' : $name, now(), (float) $val);
            }
        }

        return $metrics;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function mapHttpFailure(int $status, ?array $payload, string $defaultMessage): SocialProviderException
    {
        $error = $payload['error'] ?? [];
        $message = (string) ($error['message'] ?? $defaultMessage);
        $code = isset($error['code']) ? (string) $error['code'] : null;

        $class = match (true) {
            $status === 401, $code === '190' => ErrorClass::AuthExpired,
            $status === 429 => ErrorClass::RateLimited,
            $status >= 500 => ErrorClass::Retryable,
            default => ErrorClass::Fatal,
        };

        return new SocialProviderException($message, $class, $status, $code);
    }
}
