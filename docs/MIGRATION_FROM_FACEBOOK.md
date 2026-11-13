# Migrating the Existing Facebook Implementation

Goal: move from Facebook-only tables/services to the provider-based design **with zero downtime and no behavior change for current users**.

## Strategy: strangler pattern
1. Add new structures alongside old ones.
2. Backfill data.
3. Introduce `FacebookProvider` as an adapter over the existing Graph service.
4. Switch reads, then writes, behind a feature flag.
5. Remove old code after a stable period.

## Old → new mapping

| Existing | New | Notes |
|---|---|---|
| `users` | `users` + `workspaces` + `workspace_members` | Create one personal workspace per existing user (owner) |
| `facebook_pages` | `social_accounts` (`provider=facebook`, `kind=page`) + `oauth_tokens` | Move encrypted page token into `oauth_tokens` |
| `posts` | `contents` | Body, link, media, status |
| `scheduled_posts` | `post_targets` (one per post, account = the page) | Keep `scheduled_at`, set `idempotency_key` |
| (publish logs) | `publish_attempts` | Backfill from activity logs if available |
| `auto_reply_rules` | `auto_reply_rules` (+ `workspace_id`, `trigger`, `action`) | Existing rules become `trigger=message`/`comment`, `action=reply` |
| `messages` | `conversations` + `messages` | Group by sender PSID per page |
| `webhook_events` | `webhook_events` (+ `provider`, `payload_hash`) | Backfill `provider=facebook` |
| `insights` | `metric_snapshots` | Map FB metric names to normalized keys |
| `activity_logs` | `activity_logs` (+ `workspace_id`) | Add columns, backfill |

## Step-by-step

### Step 0 — Safety net
- Full DB backup; run the existing test suite and record the baseline.
- Add characterization tests around current Facebook publish, webhook, and auto-reply behavior (these must stay green).

### Step 1 — Additive migrations
Create: `workspaces`, `workspace_members`, `social_accounts`, `oauth_tokens`, `contents`, `post_targets`, `publish_attempts`, `conversations`, new `messages` shape (use a temporary name like `inbox_messages` if the old table conflicts), `metric_snapshots`. **Do not drop anything yet.**

Add nullable columns first, then backfill, then enforce NOT NULL in a later migration.

```php
// Example: additive column on existing table
Schema::table('webhook_events', function (Blueprint $t) {
    $t->string('provider', 32)->default('facebook')->after('id');
    $t->char('payload_hash', 64)->nullable()->after('provider');
});
```

### Step 2 — Backfill command (idempotent, chunked)

```php
// php artisan social:backfill-facebook
User::chunkById(200, function ($users) {
    foreach ($users as $user) {
        DB::transaction(function () use ($user) {
            $ws = Workspace::firstOrCreate(
                ['owner_id' => $user->id],
                ['name' => $user->name."'s Workspace", 'slug' => Str::slug($user->name).'-'.$user->id]
            );
            WorkspaceMember::firstOrCreate(
                ['workspace_id' => $ws->id, 'user_id' => $user->id],
                ['role' => 'owner', 'accepted_at' => now()]
            );

            FacebookPage::where('user_id', $user->id)->each(function ($page) use ($ws, $user) {
                $acct = SocialAccount::updateOrCreate(
                    ['workspace_id' => $ws->id, 'provider' => 'facebook', 'external_id' => $page->page_id],
                    ['connected_by' => $user->id, 'kind' => 'page', 'name' => $page->name, 'status' => 'active']
                );
                OAuthToken::updateOrCreate(
                    ['social_account_id' => $acct->id],
                    ['access_token' => $page->access_token] // already encrypted? decrypt+re-encrypt via cast, do NOT double-encrypt
                );
            });
        });
    }
});
```

Checklist for backfill:
- [ ] Idempotent (safe to run multiple times)
- [ ] Dry-run flag with counts
- [ ] Don't double-encrypt tokens; confirm the old cast/format first
- [ ] Preserve original IDs in a `legacy_id` column for traceability (drop later)
- [ ] Verify counts: pages = accounts, scheduled = targets

### Step 3 — Provider abstraction
- Add `SocialProvider` + `ProviderManager`, register `facebook`.
- `FacebookProvider` **delegates to the existing Graph API service** (don't rewrite it). Convert exceptions to `ErrorClass`.
- Add `FakeProvider` and contract tests that every provider must pass.

### Step 4 — Dual-write / shadow mode
Behind `config('social.use_new_pipeline')` (per workspace or percentage):
- Old UI still writes old tables; an observer mirrors writes to the new tables, **or** new UI writes new tables and a sync mirrors back to old ones.
- Run the new publish job in **shadow/dry-run** (validate + log, do not call Facebook) and compare with the old pipeline outcome.

### Step 5 — Cutover
1. Enable the new pipeline for internal workspaces.
2. Enable for all; keep the old jobs registered but idle for one release.
3. Make sure old queued jobs drain (`PublishScheduledPost` old class must still exist until the queue is empty, so don't delete job classes prematurely).
4. Webhook route: point the existing Facebook webhook URL to the new gateway, keep the old URL as an alias forwarding to it (Meta app settings don't need to change).

### Step 6 — Cleanup (after a stable period)
- Remove compatibility routes/services and old models.
- Drop old tables in a separate, reversible-where-possible migration after a verified backup.
- Remove `legacy_id` columns.

## Routes & UI compatibility
- Keep `/pages/*` routes as redirects to `/accounts/*`.
- Vue: introduce `PlatformBadge`, `AccountSelector` and capability-driven composer. Facebook-specific components become `Providers/Facebook/*` previews.

## Risks & mitigations
| Risk | Mitigation |
|---|---|
| Double-posting during cutover | Idempotency key + status lock; never enable both pipelines for the same target |
| Token double-encryption/corruption | Test decrypt on a copy of production data before backfill |
| Meta webhook downtime | Keep old URL alive, deploy gateway first, verify signature handling with test events |
| Long-running migration on large tables | Chunk, run off-peak, avoid locking `ALTER`s (use online DDL / pt-osc if needed) |
| Metric name mismatch | `MetricMapper` with unit tests and fallback to raw JSON |

## Definition of done for the migration
- All Facebook functionality works through `FacebookProvider`.
- Old test suite green, plus contract tests.
- No reads/writes to legacy tables for 2 releases.
- Docs (`DATABASE.md`, `ARCHITECTURE.md`) match the live schema.
