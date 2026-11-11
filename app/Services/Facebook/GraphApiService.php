<?php

namespace App\Services\Facebook;

use App\Exceptions\FacebookGraphException;
use App\Models\FacebookPage;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GraphApiService
{
    public function __construct(
        private readonly RateLimitService $rateLimiter,
        private readonly ActivityLogService $activityLogger,
    ) {}

    /**
     * Build the official Meta Facebook Login OAuth authorization URL requesting only required scopes.
     */
    public function getAuthorizationUrl(string $state, ?string $redirectUri = null): string
    {
        $version = (string) config('facebook.graph_version', 'v21.0');
        $oauthBase = rtrim((string) config('facebook.oauth_base_url', 'https://www.facebook.com'), '/');
        /** @var array<int, string> $scopes */
        $scopes = config('facebook.scopes', [
            'pages_show_list',
            'pages_manage_posts',
            'pages_read_engagement',
            'pages_messaging',
            'pages_manage_metadata',
        ]);

        $query = http_build_query([
            'client_id' => (string) config('facebook.app_id'),
            'redirect_uri' => $redirectUri ?? (string) config('facebook.redirect_uri'),
            'state' => $state,
            'response_type' => 'code',
            'scope' => implode(',', $scopes),
        ]);

        return sprintf('%s/%s/dialog/oauth?%s', $oauthBase, $version, $query);
    }

    /**
     * Exchange an OAuth authorization code for a short-lived User Access Token.
     *
     * @return array{access_token: string, token_type?: string, expires_in?: int}
     */
    public function exchangeCodeForUserToken(User $user, string $code, ?string $redirectUri = null): array
    {
        $response = $this->request(
            action: 'oauth.token.exchange',
            method: 'GET',
            endpoint: '/oauth/access_token',
            params: [
                'client_id' => (string) config('facebook.app_id'),
                'client_secret' => (string) config('facebook.app_secret'),
                'redirect_uri' => $redirectUri ?? (string) config('facebook.redirect_uri'),
                'code' => $code,
            ],
            user: $user,
        );

        return [
            'access_token' => (string) ($response['access_token'] ?? ''),
            'token_type' => isset($response['token_type']) ? (string) $response['token_type'] : 'bearer',
            'expires_in' => isset($response['expires_in']) ? (int) $response['expires_in'] : 3600,
        ];
    }

    /**
     * Exchange a short-lived User Access Token for a 60-day long-lived User Access Token.
     *
     * @return array{access_token: string, expires_in: int}
     */
    public function exchangeForLongLivedUserToken(User $user, string $shortLivedToken): array
    {
        $response = $this->request(
            action: 'oauth.token.long_lived',
            method: 'GET',
            endpoint: '/oauth/access_token',
            params: [
                'grant_type' => 'fb_exchange_token',
                'client_id' => (string) config('facebook.app_id'),
                'client_secret' => (string) config('facebook.app_secret'),
                'fb_exchange_token' => $shortLivedToken,
            ],
            user: $user,
        );

        return [
            'access_token' => (string) ($response['access_token'] ?? $shortLivedToken),
            'expires_in' => isset($response['expires_in']) ? (int) $response['expires_in'] : 5184000,
        ];
    }

    /**
     * Retrieve the Facebook Pages managed by the user along with their long-lived Page access tokens.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchManagedPages(User $user, string $longLivedUserToken): array
    {
        $response = $this->request(
            action: 'graph.pages.list',
            method: 'GET',
            endpoint: '/me/accounts',
            params: [
                'fields' => 'id,name,category,access_token,tasks,followers_count,picture{url}',
                'limit' => 100,
                'access_token' => $longLivedUserToken,
            ],
            user: $user,
        );

        /** @var array<int, array<string, mixed>> $pages */
        $pages = is_array($response['data'] ?? null) ? $response['data'] : [];

        return $pages;
    }

    /**
     * Subscribe a connected Facebook Page to the application's webhook fields.
     */
    public function subscribePageWebhook(FacebookPage $page): bool
    {
        $response = $this->request(
            action: 'graph.webhook.subscribe',
            method: 'POST',
            endpoint: sprintf('/%s/subscribed_apps', $page->page_id),
            params: [
                'subscribed_fields' => 'feed,messages,messaging_postbacks',
                'access_token' => $page->access_token,
            ],
            page: $page,
        );

        return (bool) ($response['success'] ?? false);
    }

    /**
     * Publish a text, link, or image post to a Facebook Page via the official Graph API.
     *
     * @return array{facebook_post_id: string, permalink_url: string}
     */
    public function publishPost(FacebookPage $page, Post $post): array
    {
        if ($post->type === 'image' && ! empty($post->media_urls)) {
            $firstImageUrl = (string) $post->media_urls[0];

            $response = $this->request(
                action: 'graph.post.publish_image',
                method: 'POST',
                endpoint: sprintf('/%s/photos', $page->page_id),
                params: [
                    'url' => $firstImageUrl,
                    'caption' => $post->content,
                    'published' => true,
                    'access_token' => $page->access_token,
                ],
                page: $page,
            );

            $fbPostId = (string) ($response['post_id'] ?? $response['id'] ?? '');
        } else {
            $payload = [
                'message' => $post->content,
                'published' => true,
                'access_token' => $page->access_token,
            ];

            if ($post->type === 'link' && ! empty($post->link_url)) {
                $payload['link'] = $post->link_url;
            }

            $response = $this->request(
                action: 'graph.post.publish_feed',
                method: 'POST',
                endpoint: sprintf('/%s/feed', $page->page_id),
                params: $payload,
                page: $page,
            );

            $fbPostId = (string) ($response['id'] ?? '');
        }

        return [
            'facebook_post_id' => $fbPostId,
            'permalink_url' => sprintf('https://www.facebook.com/%s', $fbPostId),
        ];
    }

    /**
     * Send a standard Messenger response within Meta's 24-hour messaging window.
     *
     * @return array{message_id: string, recipient_id: string}
     */
    public function sendMessengerReply(FacebookPage $page, string $recipientPsid, string $messageText): array
    {
        $response = $this->request(
            action: 'graph.messenger.send',
            method: 'POST',
            endpoint: sprintf('/%s/messages', $page->page_id),
            params: [
                'messaging_type' => 'RESPONSE',
                'recipient' => ['id' => $recipientPsid],
                'message' => ['text' => $messageText],
                'access_token' => $page->access_token,
            ],
            page: $page,
        );

        return [
            'message_id' => (string) ($response['message_id'] ?? ''),
            'recipient_id' => (string) ($response['recipient_id'] ?? $recipientPsid),
        ];
    }

    /**
     * Publish a reply comment to a Facebook Page post comment.
     *
     * @return array{comment_id: string}
     */
    public function replyToComment(FacebookPage $page, string $commentId, string $messageText): array
    {
        $response = $this->request(
            action: 'graph.comment.reply',
            method: 'POST',
            endpoint: sprintf('/%s/comments', $commentId),
            params: [
                'message' => $messageText,
                'access_token' => $page->access_token,
            ],
            page: $page,
        );

        return [
            'comment_id' => (string) ($response['id'] ?? ''),
        ];
    }

    /**
     * Hide or unhide a Page comment (Moderation).
     */
    public function moderateComment(FacebookPage $page, string $commentId, bool $isHidden = true): bool
    {
        $response = $this->request(
            action: 'graph.comment.moderate',
            method: 'POST',
            endpoint: sprintf('/%s', $commentId),
            params: [
                'is_hidden' => $isHidden,
                'access_token' => $page->access_token,
            ],
            page: $page,
        );

        return (bool) ($response['success'] ?? true);
    }

    /**
     * Fetch daily Page Insights metrics (reach, engagement, followers, views) over a date range.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchPageInsights(FacebookPage $page, CarbonInterface $since, CarbonInterface $until): array
    {
        $response = $this->request(
            action: 'graph.insights.fetch',
            method: 'GET',
            endpoint: sprintf('/%s/insights', $page->page_id),
            params: [
                'metric' => 'page_impressions_unique,page_impressions,page_post_engagements,page_follows,page_views_total',
                'period' => 'day',
                'since' => $since->startOfDay()->timestamp,
                'until' => $until->endOfDay()->timestamp,
                'access_token' => $page->access_token,
            ],
            page: $page,
        );

        /** @var array<int, array<string, mixed>> $data */
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];

        return $data;
    }

    /**
     * Execute a Graph API HTTP call with rate-limit checks, error classification, and full activity logging.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws FacebookGraphException
     */
    public function request(
        string $action,
        string $method,
        string $endpoint,
        array $params = [],
        ?User $user = null,
        ?FacebookPage $page = null,
    ): array {
        $resolvedUserId = $user->id ?? $page?->user_id;

        if ($resolvedUserId !== null) {
            $this->rateLimiter->ensureWithinLimits($resolvedUserId, $page?->id);
        }

        $version = trim((string) config('facebook.graph_version', 'v21.0'), '/');
        $normalizedEndpoint = '/'.ltrim($endpoint, '/');
        $versionedPath = sprintf('/%s%s', $version, $normalizedEndpoint);

        if (isset($params['access_token']) && is_string($params['access_token']) && $params['access_token'] !== '') {
            $appSecret = (string) config('facebook.app_secret', '');
            if ($appSecret !== '') {
                $params['appsecret_proof'] = hash_hmac('sha256', $params['access_token'], $appSecret);
            }
        }

        $startedAt = hrtime(true);

        try {
            $client = $this->httpClient();
            $response = match (strtoupper($method)) {
                'POST' => $client->post($versionedPath, $params),
                'DELETE' => $client->delete($versionedPath, $params),
                default => $client->get($versionedPath, $params),
            };
        } catch (ConnectionException $connectionException) {
            $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

            $this->activityLogger->logApiCall(
                action: $action,
                method: $method,
                endpoint: $versionedPath,
                httpStatus: 503,
                durationMs: $durationMs,
                message: 'Connection timeout or network failure calling Meta Graph API: '.$connectionException->getMessage(),
                user: $user,
                page: $page,
                graphErrorCode: 2,
                context: ['params' => $params],
            );

            throw new FacebookGraphException(
                message: 'Network failure while connecting to Meta Graph API: '.$connectionException->getMessage(),
                graphCode: 2,
                httpStatus: 503,
                previous: $connectionException,
            );
        }

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        $rateUsage = [];

        if ($resolvedUserId !== null) {
            $rateUsage = $this->rateLimiter->recordCallAndHeaders(
                userId: $resolvedUserId,
                pageId: $page?->id,
                headers: $response->headers(),
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = is_array($response->json()) ? $response->json() : [];

        if ($response->failed() || isset($payload['error'])) {
            $graphException = FacebookGraphException::fromResponse($payload, $response->status());

            $this->handleGraphErrorSideEffects($graphException, $resolvedUserId, $page);

            $this->activityLogger->logApiCall(
                action: $action,
                method: $method,
                endpoint: $versionedPath,
                httpStatus: $response->status(),
                durationMs: $durationMs,
                message: sprintf(
                    'Graph API error (#%d%s): %s',
                    $graphException->graphCode,
                    $graphException->graphSubcode ? '/'.$graphException->graphSubcode : '',
                    $graphException->getMessage()
                ),
                user: $user,
                page: $page,
                graphErrorCode: $graphException->graphCode,
                graphErrorSubcode: $graphException->graphSubcode,
                rateUsage: $rateUsage !== [] ? $rateUsage : null,
                context: [
                    'category' => $graphException->errorCategory(),
                    'remediation' => $graphException->remediationHint(),
                    'fbtrace_id' => $graphException->fbTraceId,
                    'params' => $params,
                ],
            );

            throw $graphException;
        }

        $this->activityLogger->logApiCall(
            action: $action,
            method: $method,
            endpoint: $versionedPath,
            httpStatus: $response->status(),
            durationMs: $durationMs,
            message: sprintf('Graph API %s %s succeeded (%dms)', strtoupper($method), $versionedPath, $durationMs),
            user: $user,
            page: $page,
            rateUsage: $rateUsage !== [] ? $rateUsage : null,
            context: ['params' => $params],
        );

        return $payload;
    }

    private function httpClient(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('facebook.graph_base_url', 'https://graph.facebook.com'), '/'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15);
    }

    private function handleGraphErrorSideEffects(
        FacebookGraphException $exception,
        ?int $userId,
        ?FacebookPage $page,
    ): void {
        if ($exception->isRateLimited() && $userId !== null) {
            $this->rateLimiter->markRateLimited($userId, $page?->id, 300);
        }

        if ($page === null) {
            return;
        }

        if ($exception->isTokenExpiredOrInvalid()) {
            $page->update([
                'status' => 'expired',
                'last_error_code' => $exception->graphCode,
                'last_error_message' => $exception->getMessage(),
            ]);
        } elseif ($exception->isRateLimited()) {
            $page->update([
                'status' => 'rate_limited',
                'last_error_code' => $exception->graphCode,
                'last_error_message' => $exception->getMessage(),
            ]);
        } else {
            $page->update([
                'last_error_code' => $exception->graphCode,
                'last_error_message' => $exception->getMessage(),
            ]);
        }
    }
}
