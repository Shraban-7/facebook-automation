<?php

namespace Tests\Feature\Social;

use App\DTO\Social\AuthState;
use App\DTO\Social\TokenSet;
use App\Enums\AccountKind;
use App\Models\Content;
use App\Models\MediaAsset;
use App\Models\PostTarget;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\ProviderManager;
use App\Services\Social\TokenVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SocialProviderContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_manager_resolves_all_supported_providers(): void
    {
        $manager = app(ProviderManager::class);

        foreach (['fake', 'facebook', 'instagram', 'threads', 'linkedin', 'youtube'] as $key) {
            $provider = $manager->provider($key);
            $this->assertSame($key, $provider->key());
            $this->assertGreaterThan(0, $provider->capabilities()->maxTextLength);
        }
    }

    public function test_fake_provider_oauth_and_publish_contract(): void
    {
        $manager = app(ProviderManager::class);
        $provider = $manager->provider('fake');

        $state = new AuthState(
            state: 'state_123',
            workspaceId: 1,
            userId: 1,
            provider: 'fake',
            redirectUri: 'https://app.test/callback',
        );

        $this->assertStringContainsString('state_123', $provider->authorizationUrl($state));
        $conn = $provider->handleCallback(['code' => 'demo_code'], $state);
        $this->assertCount(1, $conn->accounts);

        $user = User::factory()->create();
        $workspace = $user->resolveCurrentWorkspace();
        $account = SocialAccount::query()->create([
            'workspace_id' => $workspace->id,
            'provider' => 'fake',
            'external_id' => 'fake_1',
            'kind' => AccountKind::Profile->value,
            'name' => 'Fake Profile',
        ]);

        $content = Content::query()->create([
            'workspace_id' => $workspace->id,
            'author_id' => $user->id,
            'body' => 'Hello multi-platform world!',
        ]);

        $target = PostTarget::query()->create([
            'content_id' => $content->id,
            'social_account_id' => $account->id,
            'idempotency_key' => (string) Str::ulid(),
        ]);

        $result = $provider->publish($target, $target->idempotency_key);
        $this->assertNotEmpty($result->externalId);
    }

    public function test_linkedin_and_threads_publish_via_http_fake(): void
    {
        Http::fake([
            'https://api.linkedin.com/rest/posts' => Http::response(['id' => 'urn:li:share:999'], 201, [
                'x-restli-id' => 'urn:li:share:999',
            ]),
            'https://graph.threads.net/*' => Http::response(['id' => 'th_12345'], 200),
        ]);

        $user = User::factory()->create();
        $workspace = $user->resolveCurrentWorkspace();
        $vault = app(TokenVault::class);
        $manager = app(ProviderManager::class);

        $liAccount = SocialAccount::query()->create([
            'workspace_id' => $workspace->id,
            'provider' => 'linkedin',
            'external_id' => 'urn:li:person:abc',
            'kind' => AccountKind::Profile->value,
            'name' => 'LinkedIn Test',
        ]);
        $vault->store($liAccount, new TokenSet('li_token_123'));

        $thAccount = SocialAccount::query()->create([
            'workspace_id' => $workspace->id,
            'provider' => 'threads',
            'external_id' => 'th_user_1',
            'kind' => AccountKind::Profile->value,
            'name' => 'Threads Test',
        ]);
        $vault->store($thAccount, new TokenSet('th_token_123'));

        $content = Content::query()->create([
            'workspace_id' => $workspace->id,
            'author_id' => $user->id,
            'body' => 'Shipping multi-platform support #launch @team!',
        ]);

        $liTarget = PostTarget::query()->create([
            'content_id' => $content->id,
            'social_account_id' => $liAccount->id,
            'idempotency_key' => (string) Str::ulid(),
        ]);
        $thTarget = PostTarget::query()->create([
            'content_id' => $content->id,
            'social_account_id' => $thAccount->id,
            'idempotency_key' => (string) Str::ulid(),
        ]);

        $liRes = $manager->provider('linkedin')->publish($liTarget, $liTarget->idempotency_key);
        $thRes = $manager->provider('threads')->publish($thTarget, $thTarget->idempotency_key);

        $this->assertSame('urn:li:share:999', $liRes->externalId);
        $this->assertSame('th_12345', $thRes->externalId);
    }

    public function test_youtube_validates_video_requirement(): void
    {
        $user = User::factory()->create();
        $workspace = $user->resolveCurrentWorkspace();

        $ytAccount = SocialAccount::query()->create([
            'workspace_id' => $workspace->id,
            'provider' => 'youtube',
            'external_id' => 'UC_123',
            'kind' => AccountKind::Channel->value,
            'name' => 'YouTube Channel',
        ]);

        $content = Content::query()->create([
            'workspace_id' => $workspace->id,
            'author_id' => $user->id,
            'body' => 'Text only fails YouTube validation',
        ]);

        $target = PostTarget::query()->create([
            'content_id' => $content->id,
            'social_account_id' => $ytAccount->id,
            'idempotency_key' => (string) Str::ulid(),
        ]);

        $provider = app(ProviderManager::class)->provider('youtube');
        $validation = $provider->validate($target);
        $this->assertFalse($validation->valid);

        $video = MediaAsset::query()->create([
            'workspace_id' => $workspace->id,
            'uploader_id' => $user->id,
            'disk' => 'public',
            'path' => 'videos/demo.mp4',
            'mime' => 'video/mp4',
            'size_bytes' => 2048,
            'checksum_sha256' => hash('sha256', 'demo'),
        ]);
        $content->mediaAssets()->attach($video->id, ['position' => 0]);
        $target->unsetRelation('content');

        $this->assertTrue($provider->validate($target)->valid);
    }
}
