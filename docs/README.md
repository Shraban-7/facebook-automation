# Social Automation Platform — Documentation Index

Multi-platform social media automation (Facebook, Instagram, Threads, LinkedIn, YouTube, X, TikTok, Pinterest) built on **Laravel + Inertia (Vue 3) + MySQL + Redis**.

The original Facebook-only module is already implemented. These docs describe how to evolve it into a provider-based, multi-platform system **without rewriting what works**.

## Documents

| File | Purpose |
|---|---|
| `PROMPT.md` | Updated master prompt for AI coding tools (context-aware of the existing Facebook code) |
| `ARCHITECTURE.md` | Layers, provider contract, publishing pipeline, queues, rate limits, webhooks |
| `DATABASE.md` | Full schema, ERD, indexes, migration notes |
| `API_INTEGRATIONS.md` | Per-platform endpoints, scopes, limits, and review requirements |
| `SECURITY.md` | Token vault, OAuth hardening, webhook verification, compliance |
| `MIGRATION_FROM_FACEBOOK.md` | Step-by-step refactor of the existing code into the new design |
| `OPERATIONS.md` | Testing, observability, deployment, runbooks |

## Review of the original prompt (what was wrong or missing)

1. **Single platform, hard-wired.** `facebook_pages`, `FacebookService`, etc. cannot absorb LinkedIn or YouTube without copy-paste. Needs a provider abstraction.
2. **No team model.** Real tools need workspaces, roles, and approval flows, not just `user_id` on everything.
3. **Post model was per-platform.** Modern tools write one *content draft* and fan it out into per-platform *targets* with platform-specific overrides (caption length, hashtags, aspect ratio).
4. **No media pipeline.** Video (YouTube, TikTok, Reels) needs chunked/resumable uploads, transcoding checks, and storage abstraction.
5. **No idempotent publishing.** Retried jobs can double-post. Needs idempotency keys and a publish-attempt ledger.
6. **Unified inbox missing.** DMs and comments from all platforms should land in one conversation model.
7. **Analytics tied to Facebook metric names.** Meta has been retiring older Page insight metrics; use a normalized metric catalog and map per provider.
8. **Security was one line.** Needs envelope-encrypted token vault, key rotation, PKCE, webhook verification per provider, audit logs, and data-deletion callbacks.
9. **Outdated defaults.** Prefer Laravel 12+, Inertia v2, Vue 3 + TypeScript, Pest, Horizon, and Pulse/Telescope for observability.
10. **No operational story.** No circuit breakers, dead-letter handling, token-expiry alerts, or platform-outage behavior.

## Phased roadmap

| Phase | Scope | Status |
|---|---|---|
| 0 | Facebook Pages: OAuth, scheduler, auto-reply, insights | **Done** |
| 1 | Provider abstraction + `social_accounts` migration + workspaces | Next |
| 2 | Unified composer (content → targets), media library, calendar v2 | |
| 3 | Instagram + Threads (same Meta app, low incremental effort) | |
| 4 | LinkedIn (profile + company page) | |
| 5 | YouTube (resumable upload, comments, analytics) | |
| 6 | Unified inbox, rules engine, approvals | |
| 7 | X, TikTok, Pinterest, AI caption assistant, public API/webhooks | |

> **Important:** Platform APIs, quotas, and pricing change often. Everything in `API_INTEGRATIONS.md` must be verified against each platform's current official docs before implementation. Items marked **(verify)** are the most volatile.
