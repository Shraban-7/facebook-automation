<?php

namespace App\Services\Social\Providers\Facebook;

use App\Contracts\Social\SocialProvider;
use App\Contracts\Social\SupportsAnalytics;
use App\Contracts\Social\SupportsComments;
use App\Contracts\Social\SupportsMessaging;
use App\Contracts\Social\SupportsWebhooks;
use App\DTO\Social\AuthState;
use App\DTO\Social\ConnectionResult;
use App\DTO\Social\DateRange;
use App\DTO\Social\DiscoveredAccount;
use App\DTO\Social\NormalizedEvent;
use App\DTO\Social\ProviderCapabilities;
use App\DTO\Social\PublishResult;
use App\DTO\Social\TokenSet;
use App\DTO\Social\ValidationResult;
use App\Enums\AccountKind;
use App\Enums\ErrorClass;
use App\Exceptions\FacebookGraphException;
use App\Exceptions\SocialProviderException;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\FacebookPage;
use App\Models\InboxMessage;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Facebook\GraphApiService;
use App\Services\Facebook\WebhookService;
use App\Services\Social\MetricMapper;
use App\Services\Social\TokenVault;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Adapter wrapping the existing GraphApiService & WebhookService behind the unified SocialProvider contract.
 */
class FacebookProvider implements SocialProvider, SupportsAnalytics, SupportsComments, SupportsMessaging, SupportsWebhooks
{
    public function __construct(
        private readonly GraphApiService $graphApi,
        private readonly WebhookService $webhookService,
        private readonly TokenVault $tokenVault,
        private readonly MetricMapper $metricMapper,
    ) {}

    public function key(): string
    {
        return 'facebook';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            text: true,
            images: true,
            video: true,
            carousel: true,
            link: true,
            maxTextLength: 63206,
            maxImages: 10,
            maxVideoSeconds: 14400,
            supportsNativeScheduling: true,
            supportsMessaging: true,
            supportsComments: true,
            supportsAnalytics: true,
            supportsWebhooks: true,
            requiresPublicMediaUrl: false,
        );
    }

    public function authorizationUrl(AuthState $state): string
    {
        $version = (string) config('social.providers.facebook.api_version', 'v21.0');
        $oauthBase = rtrim((string) config('social.providers.facebook.oauth_url', 'https://www.facebook.com'), '/');
        /** @var array<int, string> $scopes */
        $scopes = config('social.providers.facebook.scopes', []);

        return sprintf(
            '%s/%s/dialog/oauth?%s',
            $oauthBase,
            $version,
            http_build_query([
                'client_id' => (string) config('social.providers.facebook.client_id'),
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
            throw new SocialProviderException('Missing OAuth authorization code.', ErrorClass::Fatal, 400);
        }

        $user = User::findOrFail($state->userId);

        try {
            $short = $this->graphApi->exchangeCodeForUserToken($user, $code, $state->redirectUri);
            $long = $this->graphApi->exchangeForLongLivedUserToken($user, $short['access_token']);
            $expiresAt = now()->addSeconds($long['expires_in']);
            /** @var array<int, string> $scopes */
            $scopes = config('social.providers.facebook.scopes', []);

            $userTokenSet = new TokenSet(
                accessToken: $long['access_token'],
                scopes: $scopes,
                expiresAt: $expiresAt,
            );

            $rawPages = $this->graphApi->fetchManagedPages($user, $long['access_token']);
            $accounts = [];

            foreach ($rawPages as $raw) {
                $pageId = (string) ($raw['id'] ?? '');
                $pageToken = (string) ($raw['access_token'] ?? '');
                if ($pageId === '' || $pageToken === '') {
                    continue;
                }

                $avatarUrl = is_array($raw['picture']['data'] ?? null)
                    ? (string) ($raw['picture']['data']['url'] ?? '')
                    : null;

                $accounts[] = new DiscoveredAccount(
                    externalId: $pageId,
                    name: (string) ($raw['name'] ?? 'Facebook Page'),
                    kind: AccountKind::Page,
                    tokenSet: new TokenSet(
                        accessToken: $pageToken,
                        refreshToken: $long['access_token'],
                        scopes: $scopes,
                        expiresAt: $expiresAt,
                    ),
                    avatarUrl: $avatarUrl ?: null,
                    capabilities: is_array($raw['tasks'] ?? null) ? array_values($raw['tasks']) : [],
                    settings: [
                        'category' => $raw['category'] ?? null,
                        'followers_count' => (int) ($raw['followers_count'] ?? 0),
                    ],
                );
            }

            return new ConnectionResult(accounts: $accounts, userTokenSet: $userTokenSet);
        } catch (FacebookGraphException $e) {
            throw $this->mapGraphException($e);
        }
    }

    public function refreshToken(SocialAccount $account): TokenSet
    {
        $refreshToken = $this->tokenVault->getRefreshToken($account)
            ?? $this->tokenVault->getAccessToken($account);

        if ($refreshToken === null || $refreshToken === '') {
            throw new SocialProviderException('Missing source token for Facebook refresh.', ErrorClass::AuthExpired, 401);
        }

        $user = $account->connectedBy ?? User::findOrFail($account->workspace->owner_id);

        try {
            $long = $this->graphApi->exchangeForLongLivedUserToken($user, $refreshToken);
            $expiresAt = now()->addSeconds($long['expires_in']);
            $pages = $this->graphApi->fetchManagedPages($user, $long['access_token']);

            foreach ($pages as $rawPage) {
                if ((string) ($rawPage['id'] ?? '') === $account->external_id && ! empty($rawPage['access_token'])) {
                    return new TokenSet(
                        accessToken: (string) $rawPage['access_token'],
                        refreshToken: $long['access_token'],
                        scopes: config('social.providers.facebook.scopes', []),
                        expiresAt: $expiresAt,
                    );
                }
            }

            return new TokenSet(
                accessToken: (string) ($this->tokenVault->getAccessToken($account) ?? $long['access_token']),
                refreshToken: $long['access_token'],
                expiresAt: $expiresAt,
            );
        } catch (FacebookGraphException $e) {
            throw $this->mapGraphException($e);
        }
    }

    public function revoke(SocialAccount $account): void
    {
        $token = $this->tokenVault->getAccessToken($account);
        if ($token === null) {
            return;
        }

        $version = trim((string) config('social.providers.facebook.api_version', 'v21.0'), '/');
        $baseUrl = rtrim((string) config('social.providers.facebook.base_url', 'https://graph.facebook.com'), '/');

        Http::timeout(10)->delete("{$baseUrl}/{$version}/me/permissions", [
            'access_token' => $token,
        ]);
    }

    public function validate(PostTarget $target): ValidationResult
    {
        $errors = [];
        $body = $target->effectiveBody();
        $caps = $this->capabilities();

        if (mb_strlen($body) > $caps->maxTextLength) {
            $errors[] = "Post body exceeds Facebook's {$caps->maxTextLength} character limit.";
        }

        $mediaUrls = $target->options['media_urls'] ?? [];
        if (is_array($mediaUrls) && count($mediaUrls) > $caps->maxImages) {
            $errors[] = "Facebook supports at most {$caps->maxImages} images per post.";
        }

        return $errors === [] ? ValidationResult::ok() : ValidationResult::fail($errors);
    }

    public function publish(PostTarget $target, string $idempotencyKey): PublishResult
    {
        $account = $target->socialAccount;
        $token = $this->tokenVault->getAccessToken($account);

        if ($token === null || $token === '') {
            throw new SocialProviderException('Missing Facebook Page access token.', ErrorClass::AuthExpired, 401);
        }

        $legacyPage = new FacebookPage([
            'user_id' => $account->connected_by ?? $account->workspace->owner_id,
            'page_id' => $account->external_id,
            'name' => $account->name,
            'access_token' => $token,
        ]);
        $legacyPage->id = $account->legacy_facebook_page_id ?? $account->id;

        $mediaUrls = is_array($target->options['media_urls'] ?? null) ? $target->options['media_urls'] : [];
        $linkUrl = $target->options['link_url'] ?? $target->content->link_url;
        $type = $mediaUrls !== [] ? 'image' : (! empty($linkUrl) ? 'link' : 'text');

        $post = new Post([
            'type' => $type,
            'content' => $target->effectiveBody(),
            'media_urls' => $mediaUrls,
            'link_url' => $linkUrl,
        ]);

        try {
            $result = $this->graphApi->publishPost($legacyPage, $post);

            return new PublishResult(
                externalId: $result['facebook_post_id'],
                externalUrl: $result['permalink_url'],
                rawResponse: $result,
            );
        } catch (FacebookGraphException $e) {
            throw $this->mapGraphException($e);
        }
    }

    public function delete(PostTarget $target): void
    {
        if (empty($target->external_id)) {
            return;
        }

        $token = $this->tokenVault->getAccessToken($target->socialAccount);
        if ($token === null) {
            return;
        }

        try {
            $this->graphApi->request(
                action: 'graph.post.delete',
                method: 'DELETE',
                endpoint: '/'.$target->external_id,
                params: ['access_token' => $token],
            );
        } catch (FacebookGraphException $e) {
            throw $this->mapGraphException($e);
        }
    }

    public function sendMessage(Conversation $conversation, string $body): InboxMessage
    {
        if (! $conversation->isWithinMessagingWindow()) {
            throw new SocialProviderException(
                'Cannot send standard Messenger reply outside Meta 24-hour messaging window.',
                ErrorClass::ContentRejected,
                400,
                '2018278',
            );
        }

        $account = $conversation->socialAccount;
        $token = $this->tokenVault->getAccessToken($account);

        if ($token === null) {
            throw new SocialProviderException('Missing Facebook Page token.', ErrorClass::AuthExpired, 401);
        }

        $legacyPage = new FacebookPage([
            'user_id' => $account->connected_by ?? $account->workspace->owner_id,
            'page_id' => $account->external_id,
            'name' => $account->name,
            'access_token' => $token,
        ]);

        try {
            $res = $this->graphApi->sendMessengerReply($legacyPage, $conversation->participant_external_id, $body);

            return InboxMessage::create([
                'conversation_id' => $conversation->id,
                'external_id' => $res['message_id'] ?: 'fb_out_'.uniqid(),
                'direction' => 'out',
                'body' => $body,
                'delivered_at' => now(),
                'status' => 'sent',
            ]);
        } catch (FacebookGraphException $e) {
            throw $this->mapGraphException($e);
        }
    }

    public function replyToComment(Comment $comment, string $text): Comment
    {
        $account = $comment->socialAccount;
        $token = $this->tokenVault->getAccessToken($account);

        if ($token === null) {
            throw new SocialProviderException('Missing Facebook Page token.', ErrorClass::AuthExpired, 401);
        }

        $legacyPage = new FacebookPage([
            'user_id' => $account->connected_by ?? $account->workspace->owner_id,
            'page_id' => $account->external_id,
            'name' => $account->name,
            'access_token' => $token,
        ]);

        try {
            $this->graphApi->replyToComment($legacyPage, $comment->external_id, $text);
            $comment->update(['replied_at' => now()]);

            return $comment;
        } catch (FacebookGraphException $e) {
            throw $this->mapGraphException($e);
        }
    }

    public function setCommentVisibility(Comment $comment, bool $hidden): void
    {
        $account = $comment->socialAccount;
        $token = $this->tokenVault->getAccessToken($account);

        if ($token === null) {
            throw new SocialProviderException('Missing Facebook Page token.', ErrorClass::AuthExpired, 401);
        }

        $legacyPage = new FacebookPage([
            'user_id' => $account->connected_by ?? $account->workspace->owner_id,
            'page_id' => $account->external_id,
            'name' => $account->name,
            'access_token' => $token,
        ]);

        try {
            $this->graphApi->moderateComment($legacyPage, $comment->external_id, $hidden);
            $comment->update(['is_hidden' => $hidden]);
        } catch (FacebookGraphException $e) {
            throw $this->mapGraphException($e);
        }
    }

    public function fetchMetrics(SocialAccount $account, DateRange $range): iterable
    {
        $token = $this->tokenVault->getAccessToken($account);
        if ($token === null) {
            throw new SocialProviderException('Missing Facebook Page token.', ErrorClass::AuthExpired, 401);
        }

        $legacyPage = new FacebookPage([
            'user_id' => $account->connected_by ?? $account->workspace->owner_id,
            'page_id' => $account->external_id,
            'name' => $account->name,
            'access_token' => $token,
        ]);

        try {
            $series = $this->graphApi->fetchPageInsights($legacyPage, $range->since, $range->until);

            return $this->metricMapper->normalizeMetaSeries($series);
        } catch (FacebookGraphException $e) {
            throw $this->mapGraphException($e);
        }
    }

    public function verify(Request $request): bool
    {
        return $this->webhookService->verifySignature(
            $request->getContent(),
            $request->header('X-Hub-Signature-256'),
        );
    }

    public function normalize(Request $request): iterable
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        /** @var array<int, array<string, mixed>> $entries */
        $entries = is_array($payload['entry'] ?? null) ? $payload['entry'] : [];
        $events = [];

        foreach ($entries as $entry) {
            $pageId = (string) ($entry['id'] ?? '');
            foreach ((array) ($entry['messaging'] ?? []) as $msg) {
                if (! is_array($msg) || ($msg['message']['is_echo'] ?? false)) {
                    continue;
                }
                $mid = (string) ($msg['message']['mid'] ?? uniqid('fb_msg_'));
                $events[] = new NormalizedEvent(
                    provider: 'facebook',
                    eventId: 'msg_'.$mid,
                    type: 'message.received',
                    accountExternalId: $pageId,
                    actorExternalId: (string) ($msg['sender']['id'] ?? ''),
                    actorName: isset($msg['sender']['name']) ? (string) $msg['sender']['name'] : null,
                    text: (string) ($msg['message']['text'] ?? ''),
                    occurredAt: isset($msg['timestamp']) ? Carbon::createFromTimestampMs((int) $msg['timestamp']) : now(),
                    payload: $msg,
                );
            }

            foreach ((array) ($entry['changes'] ?? []) as $chg) {
                if (! is_array($chg) || ($chg['field'] ?? '') !== 'feed') {
                    continue;
                }
                $val = is_array($chg['value'] ?? null) ? $chg['value'] : [];
                if (($val['item'] ?? '') === 'comment' && ($val['verb'] ?? 'add') === 'add') {
                    $commentId = (string) ($val['comment_id'] ?? uniqid('fb_cmt_'));
                    $events[] = new NormalizedEvent(
                        provider: 'facebook',
                        eventId: 'chg_'.$commentId,
                        type: 'comment.created',
                        accountExternalId: $pageId,
                        actorExternalId: (string) ($val['from']['id'] ?? ''),
                        actorName: isset($val['from']['name']) ? (string) $val['from']['name'] : null,
                        text: (string) ($val['message'] ?? ''),
                        parentExternalId: isset($val['post_id']) ? (string) $val['post_id'] : null,
                        occurredAt: now(),
                        payload: $chg,
                    );
                }
            }
        }

        return $events;
    }

    private function mapGraphException(FacebookGraphException $e): SocialProviderException
    {
        $errorClass = match (true) {
            $e->isTokenExpiredOrInvalid() => ErrorClass::AuthExpired,
            $e->isPermissionMissing() => ErrorClass::PermissionMissing,
            $e->isRateLimited() => ErrorClass::RateLimited,
            $e->isOutsideMessagingWindow() => ErrorClass::ContentRejected,
            $e->isRetryable() => ErrorClass::Retryable,
            default => ErrorClass::Fatal,
        };

        return new SocialProviderException(
            message: $e->getMessage(),
            errorClass: $errorClass,
            httpStatus: $e->httpStatus,
            providerCode: (string) $e->graphCode,
            retryAfterSeconds: $errorClass === ErrorClass::RateLimited ? 300 : null,
            providerRequestId: $e->fbTraceId,
            previous: $e,
        );
    }
}
