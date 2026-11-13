<?php

namespace App\Services\Social\Providers\Fake;

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
use App\DTO\Social\NormalizedMetric;
use App\DTO\Social\ProviderCapabilities;
use App\DTO\Social\PublishResult;
use App\DTO\Social\TokenSet;
use App\DTO\Social\ValidationResult;
use App\Enums\AccountKind;
use App\Enums\ErrorClass;
use App\Exceptions\SocialProviderException;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\InboxMessage;
use App\Models\PostTarget;
use App\Models\SocialAccount;
use Illuminate\Http\Request;

class FakeProvider implements SocialProvider, SupportsAnalytics, SupportsComments, SupportsMessaging, SupportsWebhooks
{
    public static ?ErrorClass $simulateError = null;

    public static int $publishCallCount = 0;

    public static function reset(): void
    {
        self::$simulateError = null;
        self::$publishCallCount = 0;
    }

    public function key(): string
    {
        return 'fake';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            text: true,
            images: true,
            video: true,
            carousel: true,
            link: true,
            maxTextLength: 5000,
            maxImages: 10,
            maxVideoSeconds: 600,
            supportsNativeScheduling: false,
            supportsMessaging: true,
            supportsComments: true,
            supportsAnalytics: true,
            supportsWebhooks: true,
        );
    }

    public function authorizationUrl(AuthState $state): string
    {
        return 'https://fake-social.test/oauth/authorize?state='.urlencode($state->state);
    }

    public function handleCallback(array $query, AuthState $state): ConnectionResult
    {
        $tokenSet = new TokenSet(
            accessToken: 'fake_access_token_'.($query['code'] ?? '123'),
            refreshToken: 'fake_refresh_token',
            scopes: ['read', 'write'],
            expiresAt: now()->addDays(60),
        );

        return new ConnectionResult(
            accounts: [
                new DiscoveredAccount(
                    externalId: 'fake_acc_1',
                    name: 'Fake Brand Account',
                    kind: AccountKind::Page,
                    tokenSet: $tokenSet,
                    username: 'fakebrand',
                ),
            ],
            userTokenSet: $tokenSet,
        );
    }

    public function refreshToken(SocialAccount $account): TokenSet
    {
        return new TokenSet(
            accessToken: 'fake_refreshed_token_'.$account->id,
            refreshToken: 'fake_refresh_token',
            scopes: ['read', 'write'],
            expiresAt: now()->addDays(60),
        );
    }

    public function revoke(SocialAccount $account): void
    {
        // No-op in fake provider
    }

    public function validate(PostTarget $target): ValidationResult
    {
        $body = $target->effectiveBody();
        if (mb_strlen($body) > $this->capabilities()->maxTextLength) {
            return ValidationResult::fail(['Body exceeds maximum length.']);
        }

        return ValidationResult::ok();
    }

    public function publish(PostTarget $target, string $idempotencyKey): PublishResult
    {
        self::$publishCallCount++;

        if (self::$simulateError !== null) {
            throw new SocialProviderException(
                message: 'Simulated provider failure: '.self::$simulateError->value,
                errorClass: self::$simulateError,
                httpStatus: self::$simulateError === ErrorClass::RateLimited ? 429 : 400,
                retryAfterSeconds: self::$simulateError === ErrorClass::RateLimited ? 60 : null,
            );
        }

        return new PublishResult(
            externalId: 'fake_post_'.$target->id.'_'.$idempotencyKey,
            externalUrl: 'https://fake-social.test/posts/'.$target->id,
            providerRequestId: 'req_fake_'.$target->id,
        );
    }

    public function delete(PostTarget $target): void
    {
        // No-op in fake provider
    }

    public function sendMessage(Conversation $conversation, string $body): InboxMessage
    {
        return InboxMessage::create([
            'conversation_id' => $conversation->id,
            'external_id' => 'fake_msg_'.uniqid(),
            'direction' => 'out',
            'body' => $body,
            'delivered_at' => now(),
            'status' => 'sent',
        ]);
    }

    public function replyToComment(Comment $comment, string $text): Comment
    {
        $comment->update(['replied_at' => now()]);

        return $comment;
    }

    public function setCommentVisibility(Comment $comment, bool $hidden): void
    {
        $comment->update(['is_hidden' => $hidden]);
    }

    public function fetchMetrics(SocialAccount $account, DateRange $range): iterable
    {
        return [
            new NormalizedMetric('reach', now()->startOfDay(), 1500),
            new NormalizedMetric('impressions', now()->startOfDay(), 2400),
            new NormalizedMetric('engagements', now()->startOfDay(), 180),
            new NormalizedMetric('followers', now()->startOfDay(), (float) $account->followers_count),
        ];
    }

    public function verify(Request $request): bool
    {
        return $request->header('X-Fake-Signature') === 'valid';
    }

    public function normalize(Request $request): iterable
    {
        $payload = $request->json()->all();

        return [
            new NormalizedEvent(
                provider: 'fake',
                eventId: (string) ($payload['event_id'] ?? uniqid('fake_evt_')),
                type: (string) ($payload['type'] ?? 'message.received'),
                accountExternalId: (string) ($payload['account_id'] ?? 'fake_acc_1'),
                actorExternalId: (string) ($payload['actor_id'] ?? 'actor_1'),
                actorName: (string) ($payload['actor_name'] ?? 'Fake User'),
                text: (string) ($payload['text'] ?? 'Hello'),
                occurredAt: now(),
                payload: $payload,
            ),
        ];
    }
}
