# Master Prompt (updated, context-aware)

Paste this into Claude, Cursor, or similar. Use the phase prompts at the bottom to work incrementally.

```markdown
You are a senior Laravel architect. I have an EXISTING Laravel + Inertia (Vue 3) application that automates Facebook Pages via the official Graph API (OAuth connection, post scheduler, Messenger/comment auto-reply, analytics, activity logs). That code works and must keep working.

Your job: evolve it into a multi-platform social media automation platform supporting Facebook, Instagram, Threads, LinkedIn, YouTube, and later X, TikTok, Pinterest. Use official APIs only. No scraping, no browser automation, no unofficial endpoints.

## Stack (keep)
Laravel 12+, PHP 8.3+, MySQL 8, Redis, Horizon, Inertia v2, Vue 3 (Composition API + TypeScript), Tailwind. Custom authentication (do NOT introduce Breeze/Sanctum/Jetstream). Tests with Pest.

## Hard rules
- Refactor, don't rewrite. Wrap existing Facebook services behind the new provider contract.
- One `SocialProvider` contract + optional capability interfaces (messaging, comments, analytics, webhooks).
- Domain logic lives in Actions/Services, never in controllers.
- Every external API call runs in a queued job. Never call a platform in the HTTP request cycle (OAuth callback token exchange is the only exception).
- Publishing must be idempotent (idempotency key per post target) and must record every attempt.
- Tokens are encrypted at rest, never logged, never sent to the frontend.
- All scopes/permissions come from config per provider; request the minimum.
- Per-account rate limiting + circuit breaker in Redis.
- Every provider has a fake implementation for tests (no real HTTP in CI).

## Domain model
Workspace → members (roles: owner, admin, editor, viewer) → social_accounts (provider, kind: page|profile|channel|organization) → content (draft) → post_targets (one per account, with overrides) → publish_attempts. Unified inbox: conversations, messages, comments. Normalized analytics: metric catalog + daily snapshots.

## Features
1. Connect accounts (OAuth 2.0, PKCE where supported), token refresh, expiry alerts, reconnect flow, multiple accounts per provider.
2. Composer: write once, customize per platform, validate against platform capabilities (length, media type, aspect ratio, video duration) before scheduling.
3. Scheduler: calendar, queues, best-time slots, bulk CSV import, timezone-aware, drafts, approvals.
4. Media library: upload, validate, store (S3-compatible), chunked/resumable upload for video.
5. Unified inbox: DMs + comments, assignment, canned replies, keyword/intent rules, moderation (hide/delete where API allows).
6. Analytics: normalized metrics, 7/30/90 days, per-post and per-account, export to CSV.
7. Webhooks: per-provider signature verification, idempotent event storage, async processing.
8. Compliance: Meta data-deletion callback, disconnect = revoke + purge tokens, audit log.
9. Optional AI assistant: caption variants per platform, hashtag suggestions (provider-agnostic LLM interface).

## Deliverables (in order, confirm after each)
1. Gap analysis of the existing code vs. the target design.
2. Migration plan (see MIGRATION_FROM_FACEBOOK.md) with reversible migrations and a backfill command.
3. Provider contract, ProviderManager, capability DTOs.
4. FacebookProvider adapter around existing services (no behavior change; existing tests stay green).
5. Workspace + RBAC.
6. Composer + post_targets + publish pipeline.
7. Next provider (Instagram) to prove the abstraction.
8. Docs updates (ARCHITECTURE, DATABASE, API_INTEGRATIONS, SECURITY).

Start with deliverable 1. Ask me to paste any file you need to see.
```

## Phase prompts

**Phase 1 — Abstraction**
> Implement `SocialProvider`, `ProviderCapabilities`, `ProviderManager` (Laravel Manager pattern), and `FacebookProvider` wrapping my existing Graph API service. Add `social_accounts` and backfill from `facebook_pages`. Keep old routes working via a compatibility layer. Pest tests for the contract using a FakeProvider.

**Phase 2 — Composer & pipeline**
> Implement `contents`, `post_targets`, `publish_attempts`, `PublishPostTarget` job with idempotency key, exponential backoff, and error classification (retryable / token / permission / rate-limit / content-rejected). Include the Vue composer with per-platform preview and validation.

**Phase 3 — LinkedIn**
> Add `LinkedInProvider` using the versioned `/rest/posts` API with `Linkedin-Version` header. Support member profile and organization posting, image upload via `initializeUpload`, token refresh, and organization-admin discovery. Document which scopes require Community Management API approval.

**Phase 4 — YouTube**
> Add `YouTubeProvider`: Google OAuth with offline access, resumable video upload job (chunked, resumable after failure), scheduled publish via `status.publishAt`, comment sync, and quota tracking (units/day) with a hard stop before exhaustion.
