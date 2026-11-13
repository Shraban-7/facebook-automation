<?php

namespace Tests\Feature\Social;

use App\DTO\Social\TokenSet;
use App\Enums\AccountKind;
use App\Enums\WorkspaceRole;
use App\Models\Content;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\Social\TokenVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_or_delete_another_workspace_account_or_content(): void
    {
        $ownerA = User::factory()->create();
        $workspaceA = $ownerA->resolveCurrentWorkspace();

        $accountA = SocialAccount::query()->create([
            'workspace_id' => $workspaceA->id,
            'provider' => 'threads',
            'external_id' => 'th_owner_a',
            'kind' => AccountKind::Profile->value,
            'name' => 'Owner A Threads',
        ]);

        $contentA = Content::query()->create([
            'workspace_id' => $workspaceA->id,
            'author_id' => $ownerA->id,
            'body' => 'Private Workspace A content',
        ]);

        $ownerB = User::factory()->create();
        $ownerB->resolveCurrentWorkspace();

        $this->actingAs($ownerB)
            ->delete("/social/accounts/{$accountA->id}")
            ->assertForbidden();

        $this->actingAs($ownerB)
            ->delete("/social/composer/{$contentA->id}")
            ->assertForbidden();
    }

    public function test_viewer_role_cannot_publish_content(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->resolveCurrentWorkspace();

        $account = SocialAccount::query()->create([
            'workspace_id' => $workspace->id,
            'provider' => 'fake',
            'external_id' => 'fake_viewer_test',
            'kind' => AccountKind::Profile->value,
            'name' => 'Fake Acc',
        ]);

        $viewer = User::factory()->create(['active_workspace_id' => $workspace->id]);
        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $viewer->id,
            'role' => WorkspaceRole::Viewer->value,
            'accepted_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->post('/social/composer', [
                'body' => 'Viewer attempting publish',
                'targets' => [
                    ['social_account_id' => $account->id],
                ],
            ])
            ->assertForbidden();
    }

    public function test_oauth_tokens_are_never_exposed_in_inertia_props(): void
    {
        $user = User::factory()->create();
        $workspace = $user->resolveCurrentWorkspace();

        $account = SocialAccount::query()->create([
            'workspace_id' => $workspace->id,
            'provider' => 'threads',
            'external_id' => 'th_secret_test',
            'kind' => AccountKind::Profile->value,
            'name' => 'Secret Test',
        ]);

        $secretToken = 'SUPER_SECRET_ACCESS_TOKEN_XYZ_999';
        app(TokenVault::class)->store($account, new TokenSet($secretToken));

        $response = $this->actingAs($user)->get('/social/accounts');
        $response->assertOk();
        $this->assertStringNotContainsString($secretToken, (string) $response->getContent());
    }
}
