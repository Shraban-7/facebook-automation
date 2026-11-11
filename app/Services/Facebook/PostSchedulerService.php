<?php

namespace App\Services\Facebook;

use App\Jobs\PublishScheduledPostJob;
use App\Models\FacebookPage;
use App\Models\Post;
use App\Models\ScheduledPost;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostSchedulerService
{
    public function __construct(
        private readonly ActivityLogService $activityLogger,
    ) {}

    /**
     * Create a new Post (either as a draft, scheduled for future, or queued for immediate publishing).
     *
     * @param  array<string, mixed>  $data
     */
    public function createPost(User $user, FacebookPage $page, array $data): Post
    {
        return DB::transaction(function () use ($user, $page, $data): Post {
            $isDraft = (bool) ($data['save_as_draft'] ?? false) || empty($data['scheduled_for']);
            $publishNow = (bool) ($data['publish_now'] ?? false);

            $scheduledFor = ! empty($data['scheduled_for'])
                ? Carbon::parse((string) $data['scheduled_for'])
                : ($publishNow ? now() : null);

            $status = match (true) {
                $publishNow => 'scheduled',
                $isDraft => 'draft',
                default => 'scheduled',
            };

            $post = Post::create([
                'user_id' => $user->id,
                'facebook_page_id' => $page->id,
                'type' => (string) ($data['type'] ?? 'text'),
                'content' => (string) $data['content'],
                'media_urls' => ! empty($data['media_urls']) ? array_values((array) $data['media_urls']) : null,
                'link_url' => ! empty($data['link_url']) ? (string) $data['link_url'] : null,
                'link_title' => ! empty($data['link_title']) ? (string) $data['link_title'] : null,
                'status' => $status,
            ]);

            if ($scheduledFor !== null) {
                $scheduledPost = ScheduledPost::create([
                    'post_id' => $post->id,
                    'facebook_page_id' => $page->id,
                    'user_id' => $user->id,
                    'scheduled_for' => $scheduledFor,
                    'timezone' => (string) ($data['timezone'] ?? config('app.timezone', 'UTC')),
                    'status' => 'pending',
                    'attempts' => 0,
                    'max_attempts' => 4,
                    'idempotency_key' => 'sched_'.$post->id.'_'.Str::uuid()->toString(),
                ]);

                $this->activityLogger->record([
                    'user_id' => $user->id,
                    'facebook_page_id' => $page->id,
                    'category' => 'scheduler',
                    'action' => $publishNow ? 'post.queued_immediate' : 'post.scheduled',
                    'level' => 'info',
                    'message' => sprintf(
                        'Post #%d (%s) scheduled for %s on %s',
                        $post->id,
                        $post->type,
                        $scheduledFor->toDateTimeString(),
                        $page->name,
                    ),
                    'context' => [
                        'post_id' => $post->id,
                        'scheduled_post_id' => $scheduledPost->id,
                        'scheduled_for' => $scheduledFor->toIso8601String(),
                    ],
                ]);

                if ($publishNow || $scheduledFor->lte(now())) {
                    PublishScheduledPostJob::dispatch($scheduledPost->id);
                }
            }

            return $post->load('scheduledPost');
        });
    }

    /**
     * Update an existing draft or scheduled post.
     *
     * @param  array<string, mixed>  $data
     */
    public function updatePost(Post $post, array $data): Post
    {
        return DB::transaction(function () use ($post, $data): Post {
            $saveAsDraft = (bool) ($data['save_as_draft'] ?? false);
            $scheduledFor = ! empty($data['scheduled_for'])
                ? Carbon::parse((string) $data['scheduled_for'])
                : null;

            $status = ($saveAsDraft || $scheduledFor === null) ? 'draft' : 'scheduled';

            $post->update([
                'type' => (string) ($data['type'] ?? $post->type),
                'content' => (string) ($data['content'] ?? $post->content),
                'media_urls' => array_key_exists('media_urls', $data)
                    ? (! empty($data['media_urls']) ? array_values((array) $data['media_urls']) : null)
                    : $post->media_urls,
                'link_url' => array_key_exists('link_url', $data) ? $data['link_url'] : $post->link_url,
                'link_title' => array_key_exists('link_title', $data) ? $data['link_title'] : $post->link_title,
                'status' => $status,
                'last_error' => null,
            ]);

            if ($status === 'draft') {
                $post->scheduledPost()
                    ->whereIn('status', ['pending', 'failed'])
                    ->update(['status' => 'cancelled']);
            } elseif ($scheduledFor !== null) {
                $existingSchedule = $post->scheduledPost;

                if ($existingSchedule !== null && in_array($existingSchedule->status, ['pending', 'failed', 'cancelled'], true)) {
                    $existingSchedule->update([
                        'scheduled_for' => $scheduledFor,
                        'timezone' => (string) ($data['timezone'] ?? $existingSchedule->timezone),
                        'status' => 'pending',
                        'next_retry_at' => null,
                        'error_code' => null,
                        'error_message' => null,
                    ]);
                } else {
                    ScheduledPost::create([
                        'post_id' => $post->id,
                        'facebook_page_id' => $post->facebook_page_id,
                        'user_id' => $post->user_id,
                        'scheduled_for' => $scheduledFor,
                        'timezone' => (string) ($data['timezone'] ?? 'UTC'),
                        'status' => 'pending',
                        'attempts' => 0,
                        'max_attempts' => 4,
                        'idempotency_key' => 'sched_'.$post->id.'_'.Str::uuid()->toString(),
                    ]);
                }
            }

            return $post->fresh(['scheduledPost']) ?? $post;
        });
    }

    /**
     * Find all due `ScheduledPost` records and dispatch their publishing jobs.
     */
    public function dispatchDuePosts(): int
    {
        $duePosts = ScheduledPost::query()
            ->where('status', 'pending')
            ->where(function ($query): void {
                $query->where(function ($q): void {
                    $q->whereNull('next_retry_at')
                        ->where('scheduled_for', '<=', now());
                })->orWhere(function ($q): void {
                    $q->whereNotNull('next_retry_at')
                        ->where('next_retry_at', '<=', now());
                });
            })
            ->orderBy('scheduled_for')
            ->limit(100)
            ->get();

        $dispatched = 0;

        foreach ($duePosts as $scheduledPost) {
            PublishScheduledPostJob::dispatch($scheduledPost->id);
            $dispatched++;
        }

        return $dispatched;
    }
}
