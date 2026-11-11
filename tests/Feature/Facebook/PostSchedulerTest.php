<?php

namespace Tests\Feature\Facebook;

use App\Exceptions\FacebookGraphException;
use App\Jobs\PublishScheduledPostJob;
use App\Models\FacebookPage;
use App\Models\Post;
use App\Models\ScheduledPost;
use App\Models\User;
use App\Services\Facebook\ActivityLogService;
use App\Services\Facebook\GraphApiService;
use App\Services\Facebook\PostSchedulerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PostSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'facebook.app_id' => 'test-app-id',
            'facebook.app_secret' => 'test-app-secret',
            'facebook.graph_version' => 'v21.0',
            'facebook.graph_base_url' => 'https://graph.facebook.com',
        ]);
    }

    public function test_user_can_create_scheduled_post_and_scheduler_dispatches_due_jobs(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $page = FacebookPage::create([
            'user_id' => $user->id,
            'page_id' => '99887766',
            'name' => 'Design Page',
            'access_token' => 'encrypted-long-lived-token',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post('/facebook/scheduler/posts', [
                'facebook_page_id' => $page->id,
                'type' => 'link',
                'content' => 'Check out our new architectural guide.',
                'link_url' => 'https://example.com/guide',
                'link_title' => '2026 Guide',
                'scheduled_for' => now()->addHours(2)->toIso8601String(),
                'timezone' => 'UTC',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'facebook_page_id' => $page->id,
            'type' => 'link',
            'status' => 'scheduled',
        ]);

        // Future post should not dispatch yet
        $dispatchedFuture = app(PostSchedulerService::class)->dispatchDuePosts();
        $this->assertSame(0, $dispatchedFuture);

        // Travel forward 3 hours so the post is due
        $this->travel(3)->hours();

        $dispatchedDue = app(PostSchedulerService::class)->dispatchDuePosts();
        $this->assertSame(1, $dispatchedDue);
        Queue::assertPushed(PublishScheduledPostJob::class, 1);
    }

    public function test_publish_scheduled_post_job_publishes_via_graph_api_and_records_post_id(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v21.0/99887766/feed' => Http::response([
                'id' => '99887766_123456789',
            ], 200, [
                'X-Page-Usage' => json_encode(['call_count' => 12, 'total_cputime' => 5, 'total_time' => 8]),
            ]),
        ]);

        $user = User::factory()->create();
        $page = FacebookPage::create([
            'user_id' => $user->id,
            'page_id' => '99887766',
            'name' => 'Design Page',
            'access_token' => 'valid-page-token',
            'status' => 'active',
        ]);

        $post = Post::create([
            'user_id' => $user->id,
            'facebook_page_id' => $page->id,
            'type' => 'text',
            'content' => 'Hello from scheduled job!',
            'status' => 'scheduled',
        ]);

        $scheduledPost = ScheduledPost::create([
            'post_id' => $post->id,
            'facebook_page_id' => $page->id,
            'user_id' => $user->id,
            'scheduled_for' => now()->subMinute(),
            'status' => 'pending',
            'idempotency_key' => 'sched_test_1',
        ]);

        $job = new PublishScheduledPostJob($scheduledPost->id);
        $job->handle(app(GraphApiService::class), app(ActivityLogService::class));

        $scheduledPost->refresh();
        $post->refresh();

        $this->assertSame('published', $scheduledPost->status);
        $this->assertSame('99887766_123456789', $scheduledPost->facebook_post_id);
        $this->assertSame('published', $post->status);
        $this->assertSame('99887766_123456789', $post->facebook_post_id);
    }

    public function test_publish_scheduled_post_job_logs_backoff_on_rate_limit_error_and_halts_on_expired_token(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v21.0/99887766/feed' => Http::sequence()
                ->push([
                    'error' => [
                        'message' => '(#4) Application request limit reached',
                        'type' => 'OAuthException',
                        'code' => 4,
                        'error_subcode' => 80001,
                    ],
                ], 429)
                ->push([
                    'error' => [
                        'message' => 'Error validating access token: Session has expired',
                        'type' => 'OAuthException',
                        'code' => 190,
                        'error_subcode' => 463,
                    ],
                ], 401),
        ]);

        $user = User::factory()->create();
        $page = FacebookPage::create([
            'user_id' => $user->id,
            'page_id' => '99887766',
            'name' => 'Design Page',
            'access_token' => 'token-under-test',
            'status' => 'active',
        ]);

        $post = Post::create([
            'user_id' => $user->id,
            'facebook_page_id' => $page->id,
            'type' => 'text',
            'content' => 'Rate limit test post',
            'status' => 'scheduled',
        ]);

        $scheduledPost = ScheduledPost::create([
            'post_id' => $post->id,
            'facebook_page_id' => $page->id,
            'user_id' => $user->id,
            'scheduled_for' => now()->subMinute(),
            'status' => 'pending',
            'max_attempts' => 4,
            'idempotency_key' => 'sched_test_ratelimit',
        ]);

        // 1st attempt hits HTTP 429 (Code 4): should record failure_log and schedule next_retry_at
        try {
            $job = new PublishScheduledPostJob($scheduledPost->id);
            $job->handle(app(GraphApiService::class), app(ActivityLogService::class));
            $this->fail('Expected FacebookGraphException to be thrown for retryable rate limit.');
        } catch (FacebookGraphException $e) {
            $this->assertTrue($e->isRateLimited());
            $this->assertTrue($e->isRetryable());
        }

        $scheduledPost->refresh();
        $this->assertSame('pending', $scheduledPost->status);
        $this->assertNotNull($scheduledPost->next_retry_at);
        $this->assertCount(1, $scheduledPost->failure_log ?? []);
        $this->assertSame(4, $scheduledPost->failure_log[0]['graph_code']);

        // Clear rate limit cache so 2nd attempt hits the HTTP 401 (Code 190) expired token response
        cache()->flush();

        $job2 = new PublishScheduledPostJob($scheduledPost->id);
        $job2->handle(app(GraphApiService::class), app(ActivityLogService::class));

        $scheduledPost->refresh();
        $page->refresh();

        $this->assertSame('failed', $scheduledPost->status);
        $this->assertSame(190, $scheduledPost->error_code);
        $this->assertSame('expired', $page->status);
    }
}
