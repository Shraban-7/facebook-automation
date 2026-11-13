<?php

namespace App\Actions\Social;

use App\Enums\ErrorClass;
use App\Enums\PostStatus;
use App\Exceptions\SocialProviderException;
use App\Jobs\Social\PublishPostTargetJob;
use App\Models\Content;
use App\Models\PostTarget;
use App\Models\PublishAttempt;
use App\Services\Social\CircuitBreaker;
use App\Services\Social\ProviderManager;
use App\Services\Social\SocialRateLimiter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class PublishPostTarget
{
    public function __construct(
        private readonly ProviderManager $providers,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly SocialRateLimiter $rateLimiter,
    ) {}

    public function handle(PostTarget $target): void
    {
        if ($target->status === PostStatus::Published->value) {
            return;
        }

        $claimed = DB::transaction(function () use ($target): bool {
            $updated = PostTarget::query()
                ->whereKey($target->id)
                ->whereIn('status', [
                    PostStatus::Queued->value,
                    PostStatus::Scheduled->value,
                    PostStatus::Failed->value,
                ])
                ->update(['status' => PostStatus::Publishing->value]);

            return $updated === 1;
        });

        if (! $claimed) {
            return;
        }

        $target->refresh();
        $target->load(['content.mediaAssets', 'socialAccount']);
        $account = $target->socialAccount;
        $providerKey = $account->provider;
        $attemptNo = $target->attempts + 1;
        $startedAt = CarbonImmutable::now('UTC');

        if ($this->circuitBreaker->isOpen($providerKey)) {
            $this->scheduleRetry($target, $attemptNo, $startedAt, ErrorClass::Retryable, 'Provider circuit breaker is open.', 503);

            return;
        }

        try {
            $this->rateLimiter->checkAccountLimit($account);

            $result = $this->providers
                ->provider($providerKey)
                ->publish($target, $target->idempotency_key);

            PublishAttempt::query()->create([
                'post_target_id' => $target->id,
                'attempt_no' => $attemptNo,
                'started_at' => $startedAt,
                'finished_at' => CarbonImmutable::now('UTC'),
                'outcome' => 'succeeded',
                'provider_request_id' => $result->providerRequestId,
            ]);

            $target->update([
                'status' => PostStatus::Published->value,
                'external_id' => $result->externalId,
                'external_url' => $result->externalUrl,
                'published_at' => now(),
                'attempts' => $attemptNo,
                'error_class' => null,
                'error_message' => null,
                'next_retry_at' => null,
            ]);

            $this->circuitBreaker->recordSuccess($providerKey);
            $this->syncParentContentStatus($target->content);
        } catch (SocialProviderException $e) {
            if ($e->errorClass === ErrorClass::Retryable) {
                $this->circuitBreaker->recordFailure($providerKey);
            }

            if ($e->errorClass->requiresReauth()) {
                $account->update([
                    'status' => 'reauth_required',
                    'last_error' => $e->getMessage(),
                    'last_error_at' => now(),
                ]);
            }

            $this->handleFailure(
                $target,
                $attemptNo,
                $startedAt,
                $e->errorClass,
                $e->getMessage(),
                $e->httpStatus,
                $e->providerRequestId,
                $e->retryAfterSeconds
            );
        } catch (Throwable $e) {
            $this->circuitBreaker->recordFailure($providerKey);
            $this->handleFailure($target, $attemptNo, $startedAt, ErrorClass::Retryable, $e->getMessage());
        }
    }

    private function handleFailure(
        PostTarget $target,
        int $attemptNo,
        CarbonImmutable $startedAt,
        ErrorClass $errorClass,
        string $message,
        ?int $httpStatus = null,
        ?string $requestId = null,
        ?int $retryAfterSeconds = null,
    ): void {
        /** @var array<int, int> $backoffSchedule */
        $backoffSchedule = config('social.publishing.backoff_seconds', [30, 120, 600, 1800, 7200]);
        $maxAttempts = (int) config('social.publishing.max_attempts', 5);

        if ($errorClass->isRetryable() && $attemptNo < $maxAttempts) {
            $delay = $retryAfterSeconds ?? ($backoffSchedule[$attemptNo - 1] ?? 7200);
            $this->scheduleRetry($target, $attemptNo, $startedAt, $errorClass, $message, $httpStatus, $requestId, $delay);

            return;
        }

        PublishAttempt::query()->create([
            'post_target_id' => $target->id,
            'attempt_no' => $attemptNo,
            'started_at' => $startedAt,
            'finished_at' => CarbonImmutable::now('UTC'),
            'outcome' => 'failed',
            'error_class' => $errorClass->value,
            'http_status' => $httpStatus,
            'response_excerpt' => mb_substr($message, 0, 1000),
            'provider_request_id' => $requestId,
        ]);

        $target->update([
            'status' => PostStatus::Failed->value,
            'attempts' => $attemptNo,
            'error_class' => $errorClass->value,
            'error_message' => mb_substr($message, 0, 1000),
            'next_retry_at' => null,
        ]);

        $this->syncParentContentStatus($target->content);
    }

    private function scheduleRetry(
        PostTarget $target,
        int $attemptNo,
        CarbonImmutable $startedAt,
        ErrorClass $errorClass,
        string $message,
        ?int $httpStatus = null,
        ?string $requestId = null,
        int $delaySeconds = 30,
    ): void {
        $nextRetry = now()->addSeconds($delaySeconds);

        PublishAttempt::query()->create([
            'post_target_id' => $target->id,
            'attempt_no' => $attemptNo,
            'started_at' => $startedAt,
            'finished_at' => CarbonImmutable::now('UTC'),
            'outcome' => 'retrying',
            'error_class' => $errorClass->value,
            'http_status' => $httpStatus,
            'response_excerpt' => mb_substr($message, 0, 1000),
            'provider_request_id' => $requestId,
        ]);

        $target->update([
            'status' => PostStatus::Queued->value,
            'attempts' => $attemptNo,
            'error_class' => $errorClass->value,
            'error_message' => mb_substr($message, 0, 1000),
            'next_retry_at' => $nextRetry,
        ]);

        PublishPostTargetJob::dispatch($target->id)->delay($nextRetry);
    }

    private function syncParentContentStatus(Content $content): void
    {
        $statuses = $content->targets()->pluck('status');
        if ($statuses->isEmpty()) {
            return;
        }

        $allPublished = $statuses->every(fn ($s): bool => $s === PostStatus::Published->value);
        $anyPublished = $statuses->contains(fn ($s): bool => $s === PostStatus::Published->value);
        $anyPending = $statuses->contains(fn ($s): bool => in_array($s, [
            PostStatus::Queued->value,
            PostStatus::Scheduled->value,
            PostStatus::Publishing->value,
        ], true));

        $newStatus = match (true) {
            $allPublished => PostStatus::Published,
            $anyPending => PostStatus::Publishing,
            $anyPublished => PostStatus::Partial,
            default => PostStatus::Failed,
        };

        $content->update(['status' => $newStatus->value]);
    }
}
