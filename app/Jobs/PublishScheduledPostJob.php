<?php

namespace App\Jobs;

use App\Exceptions\FacebookGraphException;
use App\Models\ScheduledPost;
use App\Services\Facebook\ActivityLogService;
use App\Services\Facebook\GraphApiService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class PublishScheduledPostJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [15, 60, 300, 900];

    public function __construct(
        public readonly int $scheduledPostId,
    ) {}

    public function uniqueId(): string
    {
        return 'publish_scheduled_post_'.$this->scheduledPostId;
    }

    public function handle(GraphApiService $graphApi, ActivityLogService $activityLogger): void
    {
        /** @var ScheduledPost|null $scheduledPost */
        $scheduledPost = DB::transaction(function (): ?ScheduledPost {
            $record = ScheduledPost::query()
                ->with(['post', 'facebookPage'])
                ->where('id', $this->scheduledPostId)
                ->lockForUpdate()
                ->first();

            if ($record === null || in_array($record->status, ['published', 'cancelled'], true)) {
                return null;
            }

            $record->update([
                'status' => 'processing',
                'attempts' => $record->attempts + 1,
                'last_attempted_at' => now(),
            ]);

            $record->post?->update(['status' => 'publishing']);

            return $record;
        });

        if ($scheduledPost === null || $scheduledPost->post === null || $scheduledPost->facebookPage === null) {
            return;
        }

        $post = $scheduledPost->post;
        $page = $scheduledPost->facebookPage;

        try {
            $result = $graphApi->publishPost($page, $post);

            DB::transaction(function () use ($scheduledPost, $post, $result): void {
                $now = now();

                $scheduledPost->update([
                    'status' => 'published',
                    'published_at' => $now,
                    'facebook_post_id' => $result['facebook_post_id'],
                    'next_retry_at' => null,
                    'error_code' => null,
                    'error_message' => null,
                ]);

                $post->update([
                    'status' => 'published',
                    'facebook_post_id' => $result['facebook_post_id'],
                    'permalink_url' => $result['permalink_url'],
                    'published_at' => $now,
                    'last_error' => null,
                ]);
            });

            $activityLogger->record([
                'user_id' => $scheduledPost->user_id,
                'facebook_page_id' => $page->id,
                'category' => 'scheduler',
                'action' => 'post.published',
                'level' => 'info',
                'message' => sprintf('Published scheduled %s post #%d to %s (Graph ID: %s)', $post->type, $post->id, $page->name, $result['facebook_post_id']),
                'context' => [
                    'post_id' => $post->id,
                    'scheduled_post_id' => $scheduledPost->id,
                    'facebook_post_id' => $result['facebook_post_id'],
                ],
            ]);
        } catch (FacebookGraphException $exception) {
            $attemptIndex = max(0, $scheduledPost->attempts - 1);
            $backoffSeconds = $this->backoff[$attemptIndex] ?? 900;
            $canRetry = $exception->isRetryable() && $scheduledPost->attempts < $scheduledPost->max_attempts;
            $nextRetryAt = $canRetry ? now()->addSeconds($backoffSeconds) : null;

            $scheduledPost->appendFailureLog([
                'graph_code' => $exception->graphCode,
                'graph_subcode' => $exception->graphSubcode,
                'http_status' => $exception->httpStatus,
                'category' => $exception->errorCategory(),
                'message' => $exception->getMessage(),
                'remediation' => $exception->remediationHint(),
                'retry_scheduled' => $canRetry,
                'backoff_seconds' => $canRetry ? $backoffSeconds : null,
                'next_retry_at' => $nextRetryAt?->toIso8601String(),
            ]);

            $scheduledPost->update([
                'status' => $canRetry ? 'pending' : 'failed',
                'next_retry_at' => $nextRetryAt,
                'error_code' => $exception->graphCode,
                'error_message' => $exception->getMessage(),
                'failure_log' => $scheduledPost->failure_log,
            ]);

            $post->update([
                'status' => $canRetry ? 'scheduled' : 'failed',
                'last_error' => $exception->getMessage(),
            ]);

            if (! $canRetry) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $scheduledPost = ScheduledPost::with('post')->find($this->scheduledPostId);

        if ($scheduledPost === null || $scheduledPost->status === 'published') {
            return;
        }

        $scheduledPost->update([
            'status' => 'failed',
            'next_retry_at' => null,
            'error_message' => $exception?->getMessage() ?? $scheduledPost->error_message,
        ]);

        $scheduledPost->post?->update([
            'status' => 'failed',
            'last_error' => $exception?->getMessage() ?? $scheduledPost->error_message,
        ]);
    }
}
