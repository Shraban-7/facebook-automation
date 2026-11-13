<?php

namespace Tests\Feature\Social;

use App\DTO\Social\TokenSet;
use App\Enums\AccountKind;
use App\Models\FacebookPage;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\TokenVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetaComplianceAndBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_data_deletion_verifies_signed_request_and_purges_tokens(): void
    {
        config()->set('social.providers.facebook.client_secret', 'meta_test_secret');

        $user = User::factory()->create();
        $workspace = $user->resolveCurrentWorkspace();

        $account = SocialAccount::query()->create([
            'workspace_id' => $workspace->id,
            'provider' => 'facebook',
            'external_id' => 'meta_user_777',
            'kind' => AccountKind::Page->value,
            'name' => 'Meta Compliance Page',
        ]);

        app(TokenVault::class)->store($account, new TokenSet('fb_secret_token'));

        $payload = rtrim(strtr(base64_encode(json_encode(['user_id' => 'meta_user_777'], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, 'meta_test_secret', true)), '+/', '-_'), '=');
        $signedRequest = "{$sig}.{$payload}";

        $this->postJson('/api/compliance/meta/data-deletion', ['signed_request' => 'bad.request'])
            ->assertStatus(400);

        $res = $this->postJson('/api/compliance/meta/data-deletion', ['signed_request' => $signedRequest]);
        $res->assertOk()
            ->assertJsonStructure(['url', 'confirmation_code']);

        $account->refresh();
        $this->assertSame('revoked', $account->status);
        $this->assertNull(app(TokenVault::class)->getAccessToken($account));
    }

    public function test_backfill_facebook_supports_dry_run_and_is_idempotent_without_double_encryption(): void
    {
        $user = User::factory()->create();

        FacebookPage::query()->create([
            'user_id' => $user->id,
            'page_id' => 'fb_legacy_page_1',
            'name' => 'Legacy Brand Page',
            'access_token' => 'plaintext_legacy_page_token_123',
            'status' => 'active',
        ]);

        $this->artisan('social:backfill-facebook --dry-run')->assertExitCode(0);
        $this->assertSame(0, SocialAccount::query()->where('external_id', 'fb_legacy_page_1')->count());

        $this->artisan('social:backfill-facebook')->assertExitCode(0);
        $this->artisan('social:backfill-facebook')->assertExitCode(0);

        $accounts = SocialAccount::query()->where('external_id', 'fb_legacy_page_1')->get();
        $this->assertCount(1, $accounts);

        $account = $accounts->firstOrFail();
        $decrypted = app(TokenVault::class)->getAccessToken($account);
        $this->assertSame('plaintext_legacy_page_token_123', $decrypted);
    }
}
