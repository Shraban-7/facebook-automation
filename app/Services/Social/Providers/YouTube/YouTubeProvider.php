<?php

namespace App\Services\Social\Providers\YouTube;

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
use App\Services\Social\SocialRateLimiter;
use App\Services\Social\TokenVault;
use Illuminate\Support\Facades\Http;

class YouTubeProvider implements SocialProvider, SupportsAnalytics, SupportsComments
{
    public function __construct(
        private readonly TokenVault $tokenVault,
        private readonly SocialRateLimiter $rateLimiter,
    ) {}

    public function key(): string
    {
        return 'youtube';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            text: false,
            images: false,
            video: true,
            carousel: false,
            link: false,
            maxTextLength: 5000,
            maxImages: 0,
            maxVideoSeconds: 43200,
            supportsNativeScheduling: true,
            supportsMessaging: false,
            supportsComments: true,
            supportsAnalytics: true,
            supportsWebhooks: false,
            requiresPublicMediaUrl: false,
        );
    }

    public function authorizationUrl(AuthState $state): string
    {
        $oauthUrl = (string) config('social.providers.youtube.oauth_url', 'https://accounts.google.com/o/oauth2/v2/auth');
        /** @var array<int, string> $scopes */
        $scopes = config('social.providers.youtube.scopes', []);

        $params = [
            'client_id' => (string) config('social.providers.youtube.client_id'),
            'redirect_uri' => $state->redirectUri,
            'response_type' => 'code',
            'state' => $state->state,
            'scope' => implode(' ', $scopes),
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];

        if ($state->codeVerifier) {
            $params['code_challenge'] = rtrim(strtr(base64_encode(hash('sha256', $state->codeVerifier, true)), '+/', '-_'), '=');
            $params['code_challenge_method'] = 'S256';
        }

        return $oauthUrl.'?'.http_build_query($params);
    }

    public function handleCallback(array $query, AuthState $state): ConnectionResult
    {
        $code = (string) ($query['code'] ?? '');
        if ($code === '') {
            throw new SocialProviderException('Missing Google OAuth authorization code.', ErrorClass::Fatal, 400);
        }

        $tokenUrl = (string) config('social.providers.youtube.token_url', 'https://oauth2.googleapis.com/token');
        $apiBase = rtrim((string) config('social.providers.youtube.base_url', 'https://www.googleapis.com'), '/');

        $tokenRes = Http::asForm()->timeout(15)->post($tokenUrl, array_filter([
            'client_id' => (string) config('social.providers.youtube.client_id'),
            'client_secret' => (string) config('social.providers.youtube.client_secret'),
            'code' => $code,
            'redirect_uri' => $state->redirectUri,
            'grant_type' => 'authorization_code',
            'code_verifier' => $state->codeVerifier,
        ]));

        if ($tokenRes->failed()) {
            throw $this->mapHttpFailure($tokenRes->status(), $tokenRes->json(), 'Google OAuth token exchange failed.');
        }

        $accessToken = (string) $tokenRes->json('access_token');
        $refreshToken = $tokenRes->json('refresh_token');
        $expiresIn = (int) $tokenRes->json('expires_in', 3600);
        /** @var array<int, string> $scopes */
        $scopes = config('social.providers.youtube.scopes', []);

        $tokenSet = new TokenSet(
            accessToken: $accessToken,
            refreshToken: is_string($refreshToken) ? $refreshToken : null,
            expiresAt: now()->addSeconds($expiresIn),
            scopes: $scopes,
        );

        $channelsRes = Http::withToken($accessToken)->timeout(15)->get("{$apiBase}/youtube/v3/channels", [
            'part' => 'snippet,statistics',
            'mine' => 'true',
        ]);

        if ($channelsRes->failed()) {
            throw $this->mapHttpFailure($channelsRes->status(), $channelsRes->json(), 'Failed to fetch YouTube channels.');
        }

        $accounts = [];
        foreach ((array) $channelsRes->json('items', []) as $channel) {
            $id = (string) ($channel['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $snippet = $channel['snippet'] ?? [];
            $accounts[] = new DiscoveredAccount(
                externalId: $id,
                name: (string) ($snippet['title'] ?? 'YouTube Channel'),
                kind: AccountKind::Channel,
                tokenSet: $tokenSet,
                username: $snippet['customUrl'] ?? null,
                avatarUrl: $snippet['thumbnails']['default']['url'] ?? null,
                settings: ['statistics' => $channel['statistics'] ?? []],
            );
        }

        if ($accounts === []) {
            throw new SocialProviderException('No YouTube channels found for this Google account.', ErrorClass::Fatal, 422);
        }

        return new ConnectionResult(accounts: $accounts);
    }

    public function refreshToken(SocialAccount $account): TokenSet
    {
        $refreshToken = $this->tokenVault->getRefreshToken($account);
        if (! $refreshToken) {
            throw new SocialProviderException('No YouTube refresh token stored.', ErrorClass::AuthExpired, 401);
        }

        $tokenUrl = (string) config('social.providers.youtube.token_url', 'https://oauth2.googleapis.com/token');
        $res = Http::asForm()->timeout(15)->post($tokenUrl, [
            'client_id' => (string) config('social.providers.youtube.client_id'),
            'client_secret' => (string) config('social.providers.youtube.client_secret'),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if ($res->failed()) {
            throw $this->mapHttpFailure($res->status(), $res->json(), 'Failed to refresh YouTube token.');
        }

        return new TokenSet(
            accessToken: (string) $res->json('access_token'),
            refreshToken: $refreshToken,
            expiresAt: now()->addSeconds((int) $res->json('expires_in', 3600)),
        );
    }

    public function revoke(SocialAccount $account): void
    {
        $token = $this->tokenVault->getAccessToken($account);
        if ($token) {
            Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/revoke', [
                'token' => $token,
            ]);
        }
        $this->tokenVault->purge($account);
    }

    public function validate(PostTarget $target): ValidationResult
    {
        $content = $target->content;
        $errors = [];

        $hasVideo = $content->mediaAssets->contains(fn ($m): bool => str_starts_with($m->mime, 'video/'));
        if (! $hasVideo) {
            $errors[] = 'YouTube publishing requires a video media asset.';
        }

        $description = (string) ($target->body_override ?? $content->body ?? '');
        if (mb_strlen($description) > 5000) {
            $errors[] = 'YouTube description cannot exceed 5000 characters.';
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
            throw new SocialProviderException('Missing YouTube access token.', ErrorClass::AuthExpired, 401);
        }

        $dailyLimit = (int) config('social.providers.youtube.daily_quota', 10000);
        $this->rateLimiter->consumeQuota('youtube', 'default', 1600, $dailyLimit);

        $apiBase = rtrim((string) config('social.providers.youtube.base_url', 'https://www.googleapis.com'), '/');
        $content = $target->content;
        $description = (string) ($target->body_override ?? $content->body ?? '');
        $title = (string) ($content->title ?: mb_substr($description, 0, 95) ?: 'Untitled Video');

        $statusPayload = [
            'privacyStatus' => $target->scheduled_at && $target->scheduled_at->isFuture() ? 'private' : 'public',
        ];
        if ($target->scheduled_at && $target->scheduled_at->isFuture()) {
            $statusPayload['publishAt'] = $target->scheduled_at->toIso8601String();
        }

        $initRes = Http::withToken($token)
            ->withHeaders(['X-Upload-Content-Type' => 'video/*'])
            ->timeout(25)
            ->post("{$apiBase}/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status", [
                'snippet' => [
                    'title' => $title,
                    'description' => $description,
                ],
                'status' => $statusPayload,
            ]);

        if ($initRes->failed()) {
            throw $this->mapHttpFailure($initRes->status(), $initRes->json(), 'YouTube upload initialization failed.');
        }

        $uploadLocation = $initRes->header('Location');
        if ($uploadLocation) {
            $videoId = (string) ($initRes->json('id') ?: '');
            if ($videoId === '') {
                $putRes = Http::withToken($token)
                    ->withBody('', 'application/octet-stream')
                    ->timeout(60)
                    ->put($uploadLocation);
                if ($putRes->failed()) {
                    throw $this->mapHttpFailure($putRes->status(), $putRes->json(), 'YouTube video upload failed.');
                }
                $videoId = (string) $putRes->json('id', 'yt_'.substr($idempotencyKey, 0, 12));
            }
        } else {
            $videoId = (string) $initRes->json('id', 'yt_'.substr($idempotencyKey, 0, 12));
        }

        return new PublishResult(
            externalId: $videoId,
            externalUrl: "https://www.youtube.com/watch?v={$videoId}",
            rawResponse: $initRes->json() ?? ['id' => $videoId],
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

        $dailyLimit = (int) config('social.providers.youtube.daily_quota', 10000);
        $this->rateLimiter->consumeQuota('youtube', 'default', 50, $dailyLimit);

        $apiBase = rtrim((string) config('social.providers.youtube.base_url', 'https://www.googleapis.com'), '/');
        Http::withToken($token)->timeout(15)->delete("{$apiBase}/youtube/v3/videos", [
            'id' => $target->external_id,
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

        $apiBase = rtrim((string) config('social.providers.youtube.base_url', 'https://www.googleapis.com'), '/');
        $res = Http::withToken($token)->timeout(15)->get("{$apiBase}/youtube/v3/commentThreads", array_filter([
            'part' => 'snippet',
            'videoId' => $externalPostId,
            'pageToken' => $cursor,
        ]));

        if ($res->failed()) {
            throw $this->mapHttpFailure($res->status(), $res->json(), 'Failed to fetch YouTube comments.');
        }

        return $res->json('items', []);
    }

    public function replyToComment(Comment $comment, string $text): Comment
    {
        $token = $this->tokenVault->getAccessToken($comment->socialAccount);
        if (! $token) {
            throw new SocialProviderException('Missing YouTube access token.', ErrorClass::AuthExpired, 401);
        }

        $dailyLimit = (int) config('social.providers.youtube.daily_quota', 10000);
        $this->rateLimiter->consumeQuota('youtube', 'default', 50, $dailyLimit);

        $apiBase = rtrim((string) config('social.providers.youtube.base_url', 'https://www.googleapis.com'), '/');
        $res = Http::withToken($token)->timeout(15)->post("{$apiBase}/youtube/v3/comments?part=snippet", [
            'snippet' => [
                'parentId' => $comment->external_id,
                'textOriginal' => $text,
            ],
        ]);

        if ($res->failed()) {
            throw $this->mapHttpFailure($res->status(), $res->json(), 'Failed to reply to YouTube comment.');
        }

        $comment->update(['replied_at' => now()]);

        return $comment;
    }

    public function setCommentVisibility(Comment $comment, bool $hidden): void
    {
        $token = $this->tokenVault->getAccessToken($comment->socialAccount);
        if (! $token) {
            throw new SocialProviderException('Missing YouTube access token.', ErrorClass::AuthExpired, 401);
        }

        $apiBase = rtrim((string) config('social.providers.youtube.base_url', 'https://www.googleapis.com'), '/');
        Http::withToken($token)->timeout(15)->post("{$apiBase}/youtube/v3/comments/setModerationStatus", [
            'id' => $comment->external_id,
            'moderationStatus' => $hidden ? 'heldForReview' : 'published',
        ]);
    }

    public function fetchMetrics(SocialAccount $account, DateRange $range): iterable
    {
        $token = $this->tokenVault->getAccessToken($account);
        if (! $token) {
            return [];
        }

        $apiBase = rtrim((string) config('social.providers.youtube.base_url', 'https://www.googleapis.com'), '/');
        $res = Http::withToken($token)->timeout(15)->get("{$apiBase}/youtube/v3/channels", [
            'part' => 'statistics',
            'id' => $account->external_id,
        ]);

        if ($res->failed()) {
            return [];
        }

        $stats = $res->json('items.0.statistics', []);

        return [
            new NormalizedMetric('video_views', $range->until, (float) ($stats['viewCount'] ?? 0)),
            new NormalizedMetric('followers_total', $range->until, (float) ($stats['subscriberCount'] ?? 0)),
        ];
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

        $apiBase = rtrim((string) config('social.providers.youtube.base_url', 'https://www.googleapis.com'), '/');
        $res = Http::withToken($token)->timeout(15)->get("{$apiBase}/youtube/v3/videos", [
            'part' => 'statistics',
            'id' => $target->external_id,
        ]);

        if ($res->failed()) {
            return [];
        }

        $stats = $res->json('items.0.statistics', []);

        return [
            new NormalizedMetric('video_views', now(), (float) ($stats['viewCount'] ?? 0)),
            new NormalizedMetric('likes', now(), (float) ($stats['likeCount'] ?? 0)),
            new NormalizedMetric('comments', now(), (float) ($stats['commentCount'] ?? 0)),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function mapHttpFailure(int $status, ?array $payload, string $defaultMessage): SocialProviderException
    {
        $error = $payload['error'] ?? [];
        $message = is_array($error) ? (string) ($error['message'] ?? $defaultMessage) : $defaultMessage;

        $class = match (true) {
            $status === 401 => ErrorClass::AuthExpired,
            $status === 403 && str_contains(strtolower($message), 'quota') => ErrorClass::RateLimited,
            $status === 429 => ErrorClass::RateLimited,
            $status >= 500 => ErrorClass::Retryable,
            default => ErrorClass::Fatal,
        };

        return new SocialProviderException($message, $class, $status);
    }
}
