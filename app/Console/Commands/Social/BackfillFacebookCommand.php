<?php

namespace App\Console\Commands\Social;

use App\DTO\Social\TokenSet;
use App\Enums\AccountKind;
use App\Enums\PostStatus;
use App\Enums\Provider;
use App\Models\AutoReplyRule;
use App\Models\Content;
use App\Models\FacebookPage;
use App\Models\Insight;
use App\Models\MetricSnapshot;
use App\Models\PostTarget;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Social\TokenVault;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackfillFacebookCommand extends Command
{
    protected $signature = 'social:backfill-facebook {--dry-run : Report counts without writing any changes}';

    protected $description = 'Idempotently backfill legacy Facebook pages, posts, insights, rules, and webhooks into multi-platform tables.';

    public function handle(TokenVault $tokenVault): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $pagesCount = 0;
        $postsCount = 0;
        $insightsCount = 0;
        $rulesCount = 0;
        $webhooksCount = 0;

        $run = function () use ($dryRun, $tokenVault, &$pagesCount, &$postsCount, &$insightsCount, &$rulesCount, &$webhooksCount): void {
            /** @var array<int, SocialAccount> $pageAccountMap */
            $pageAccountMap = [];

            FacebookPage::withTrashed()->with('user')->chunkById(50, function ($pages) use ($dryRun, $tokenVault, &$pagesCount, &$pageAccountMap): void {
                foreach ($pages as $page) {
                    $owner = $page->user ?? User::query()->first();
                    if (! $owner) {
                        continue;
                    }
                    $workspace = $owner->resolveCurrentWorkspace();
                    $pagesCount++;

                    if ($dryRun) {
                        continue;
                    }

                    $account = SocialAccount::query()->updateOrCreate(
                        [
                            'workspace_id' => $workspace->id,
                            'provider' => Provider::Facebook->value,
                            'external_id' => $page->page_id,
                        ],
                        [
                            'kind' => AccountKind::Page->value,
                            'name' => $page->name,
                            'username' => $page->category,
                            'avatar_url' => $page->avatar_url,
                            'followers_count' => (int) ($page->followers_count ?? 0),
                            'status' => $page->trashed()
                                ? 'revoked'
                                : ($page->status === 'expired' ? 'reauth_required' : ($page->status ?? 'active')),
                            'legacy_facebook_page_id' => $page->id,
                            'token_expires_at' => $page->token_expires_at ? Carbon::parse($page->token_expires_at) : null,
                            'last_synced_at' => $page->last_synced_at,
                        ]
                    );

                    // Accessing $page->access_token decrypts via Eloquent cast, so TokenVault encrypts plaintext once.
                    $plainToken = $page->access_token;
                    if ($plainToken !== '') {
                        $rawPermissions = $page->permissions;
                        /** @var array<int, string> $scopes */
                        $scopes = $rawPermissions ? (array) json_decode((string) $rawPermissions, true) : [];
                        $tokenVault->store($account, new TokenSet(
                            accessToken: $plainToken,
                            expiresAt: $page->token_expires_at ? Carbon::parse($page->token_expires_at) : null,
                            scopes: $scopes,
                        ));
                    }

                    $pageAccountMap[$page->id] = $account;
                }
            });

            ScheduledPost::query()->with(['post', 'facebookPage'])->chunkById(50, function ($scheduledPosts) use ($dryRun, &$postsCount, $pageAccountMap): void {
                foreach ($scheduledPosts as $sp) {
                    $account = $pageAccountMap[$sp->facebook_page_id] ?? null;
                    if (! $account) {
                        continue;
                    }
                    $postsCount++;
                    if ($dryRun) {
                        continue;
                    }

                    $legacyPost = $sp->post;
                    $content = Content::query()->firstOrCreate(
                        [
                            'workspace_id' => $account->workspace_id,
                            'legacy_post_id' => $sp->post_id,
                        ],
                        [
                            'author_id' => $legacyPost->user_id,
                            'body' => $legacyPost->message ?? '',
                            'link_url' => $legacyPost->link ?? null,
                            'status' => $sp->status === 'published' ? PostStatus::Published->value : PostStatus::Scheduled->value,
                            'scheduled_at' => $sp->scheduled_for,
                        ]
                    );

                    PostTarget::query()->firstOrCreate(
                        [
                            'content_id' => $content->id,
                            'social_account_id' => $account->id,
                        ],
                        [
                            'idempotency_key' => (string) Str::ulid(),
                            'legacy_scheduled_post_id' => $sp->id,
                            'status' => match ($sp->status) {
                                'published' => PostStatus::Published->value,
                                'failed' => PostStatus::Failed->value,
                                default => PostStatus::Scheduled->value,
                            },
                            'scheduled_at' => $sp->scheduled_for,
                            'published_at' => $sp->published_at,
                            'external_id' => $sp->facebook_post_id ?? null,
                            'attempts' => (int) ($sp->attempts ?? 0),
                            'error_message' => $sp->error_message ?? null,
                        ]
                    );
                }
            });

            Insight::query()->chunkById(100, function ($insights) use ($dryRun, &$insightsCount, $pageAccountMap): void {
                foreach ($insights as $insight) {
                    $account = $pageAccountMap[$insight->facebook_page_id] ?? null;
                    if (! $account) {
                        continue;
                    }
                    $insightsCount++;
                    if ($dryRun) {
                        continue;
                    }

                    $date = $insight->date?->toDateString() ?? now()->toDateString();
                    $metrics = [
                        'impressions' => (float) ($insight->page_impressions ?? 0),
                        'engagements' => (float) ($insight->page_engaged_users ?? 0),
                        'followers_total' => (float) ($insight->page_fans ?? 0),
                    ];
                    foreach ($metrics as $key => $val) {
                        MetricSnapshot::query()->updateOrCreate(
                            [
                                'social_account_id' => $account->id,
                                'metric_key' => $key,
                                'date' => $date,
                            ],
                            ['value' => $val]
                        );
                    }
                }
            });

            AutoReplyRule::query()->whereNull('social_account_id')->chunkById(50, function ($rules) use ($dryRun, &$rulesCount, $pageAccountMap): void {
                foreach ($rules as $rule) {
                    $account = $pageAccountMap[$rule->facebook_page_id] ?? null;
                    if (! $account) {
                        continue;
                    }
                    $rulesCount++;
                    if (! $dryRun) {
                        $rule->update([
                            'workspace_id' => $account->workspace_id,
                            'social_account_id' => $account->id,
                        ]);
                    }
                }
            });

            WebhookEvent::query()->whereNull('provider')->chunkById(100, function ($events) use ($dryRun, &$webhooksCount, $pageAccountMap): void {
                foreach ($events as $event) {
                    $webhooksCount++;
                    if (! $dryRun) {
                        $account = $event->facebook_page_id ? ($pageAccountMap[$event->facebook_page_id] ?? null) : null;
                        $event->update([
                            'provider' => Provider::Facebook->value,
                            'social_account_id' => $account?->id,
                        ]);
                    }
                }
            });
        };

        DB::transaction($run);

        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $this->info("{$prefix}Backfill summary: pages={$pagesCount}, posts={$postsCount}, insights={$insightsCount}, rules={$rulesCount}, webhooks={$webhooksCount}");

        return self::SUCCESS;
    }
}
