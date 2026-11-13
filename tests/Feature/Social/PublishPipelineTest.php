<?php

namespace Tests\Feature\Social;

use App\Actions\Social\PublishPostTarget;
use App\Actions\Social\SchedulePost;
use App\DTO\Social\TokenSet;
use App\Enums\AccountKind;
use App\Enums\ErrorClass;
use App\Enums\PostStatus;
use App\Jobs\Social\PublishPostTargetJob;
use App\Models\PostTarget;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\CircuitBreaker;
use App\Services\Social\TokenVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublishPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_post_creates_ulid_targets_and_dispatches_job(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $workspace = $user->resolveCurrentWorkspace();

        $account = SocialAccount::query()->create([
            'workspace_id' => $workspace->id,
            'provider' => 'fake',
            'external_id' => 'fake_acc_1',
            'kind' => AccountKind::Profile->value,
            'name' => 'Fake Acc',
        ]);

        $content = app(SchedulePost::class)->handle($workspace, $user, [
            'title' => 'Test Launch',
            'body' => 'Immediate dispatch test',
            'targets' => [
                ['social_account_id' => $account->id],
            ],
        ]);

        $this->assertCount(1, $content->targets);
        $target = $content->targets->first();
        $this->assertNotNull($target);
        $this->assertNotEmpty($target->idempotency_key);
        Bus::assertDispatched(PublishPostTargetJob::class);
    }

    public function test_publish_post_target_is_idempotent_and_records_attempt(): void
    {
        $user = User::factory()->create();
        $workspace = $user->resolveCurrentWorkspace();

        $account = SocialAccount::query()->create([
            'workspace_id' => $workspace->id,
            'provider' => 'fake',
            'external_id' => 'fake_acc_2',
            'kind' => AccountKind::Profile->value,
            'name' => 'Fake Acc 2',
        ]);

        $content = app(SchedulePost::class)->handle($workspace, $user, [
            'body' => 'Idempotent publish',
            'targets' => [
                ['social_account_id' => $account->id],
            ],
        ]);

        /** @var PostTarget $target */
        $target = $content->targets->firstOrFail();
        $publisher = app(PublishPostTarget::class);

        $publisher->handle($target);
        $target->refresh();
        $this->assertSame(PostStatus::Published->value, $target->status);
        $this->assertSame(1, $target->attempts);

        // Second call must be a no-op
        $publisher->handle($target);
        $target->refresh();
        $this->assertSame(1, $target->attempts);
    }

    public function test_auth_expired_halts_retries_and_flags_account_reauth(): void
    {
        Bus::fake();
        Http::fake([
            'https://graph.threads.net/*' => Http::response([
                'error' => ['message' => 'Token expired', 'code' => 190],
            ], 401),
        ]);

        $user = User::factory()->create();
        $workspace = $user->resolveCurrentWorkspace();

        $account = SocialAccount::query()->create([
            'workspace_id' => $workspace->id,
            'provider' => 'threads',
            'external_id' => 'th_expired',
            'kind' => AccountKind::Profile->value,
            'name' => 'Expired Threads',
        ]);
        app(TokenVault::class)->store($account, new TokenSet('bad_token'));

        $content = app(SchedulePost::class)->handle($workspace, $user, [
            'body' => 'Should fail with AuthExpired',
            'targets' => [
                ['social_account_id' => $account->id],
            ],
        ]);

        /** @var PostTarget $target */
        $target = $content->targets->firstOrFail();
        app(PublishPostTarget::class)->handle($target);

        $target->refresh();
        $account->refresh();

        $this->assertSame(PostStatus::Failed->value, $target->status);
        $this->assertSame(ErrorClass::AuthExpired->value, $target->error_class);
        $this->assertSame('reauth_required', $account->status);
    }

    public function test_circuit_breaker_opens_after_failure_threshold(): void
    {
        $cb = app(CircuitBreaker::class);
        $threshold = (int) config('social.circuit_breaker.failure_threshold', 5);

        $this->assertFalse($cb->isOpen('threads'));
        for ($i = 0; $i < $threshold; $i++) {
            $cb->recordFailure('threads');
        }
        $this->assertTrue($cb->isOpen('threads'));

        $cb->recordSuccess('threads');
        $this->assertFalse($cb->isOpen('threads'));
    }
}
