# Operations, Testing & Deployment

## 1. Testing strategy (Pest)
| Level | What | How |
|---|---|---|
| Unit | Mappers (metrics, errors), capability validation, rule matching, DTOs | Pure PHP, no I/O |
| Contract | Every provider satisfies `SocialProvider` behavior (auth URL shape, error mapping, validation) | Shared test trait run against each provider with `Http::fake()` fixtures |
| Feature | Schedule → publish pipeline, webhook intake, inbox rules, RBAC/IDOR | `FakeProvider`, `Queue::fake()` / sync queue |
| Browser (optional) | Composer, calendar | Laravel Dusk or Playwright |
| Smoke | Real calls with dedicated test accounts, run manually per release | Checklist per provider |

Must-have tests: idempotent publish (retry doesn't double-post), token-expiry flow, rate-limit backoff, webhook duplicate/invalid signature, workspace isolation, timezone/DST scheduling.

## 2. CI pipeline
1. `composer install`, `npm ci`
2. Pint (style), PHPStan/Larastan (level 6+), ESLint + `vue-tsc`
3. Pest with coverage on `Services/Social` and `Actions`
4. `composer audit`, `npm audit`, secret scan
5. Build assets; deploy on green

## 3. Observability
- **Logs:** structured JSON with `workspace_id`, `provider`, `account_id`, `request_id`, `job_id`; redaction processor on.
- **Metrics/dashboards:** publish success rate per provider, queue latency/wait time, failed jobs, token-expiry counts, webhook lag, rate-limit hits, quota burn (YouTube).
- **Tools:** Horizon (queues), Pulse (app health), Sentry/Bugsnag (errors), uptime check on the webhook endpoint.
- **Alerts:** failed publish > threshold, queue wait > 2 min, invalid-signature spike, circuit breaker open, tokens expiring in 7 days with no refresh path, quota > 80%.

## 4. Scheduler (cron) commands
| Command | Frequency |
|---|---|
| `social:sweep-due-targets` | every minute (catch missed schedules) |
| `social:refresh-tokens` | hourly (refresh anything expiring within window) |
| `social:notify-expiring-tokens` | daily |
| `social:pull-metrics` | every 6–24h per provider limits |
| `social:sync-inbox` (polling providers) | every 1–5 min |
| `social:prune` (logs, webhook payloads, temp media) | daily |
| `horizon:snapshot` | every 5 min |

## 5. Deployment
- Zero-downtime deploys (Envoyer, Deployer, or containers with rolling updates).
- `horizon:terminate` on deploy so workers reload code; keep old job classes until queues drain.
- Run migrations before code that needs them; destructive migrations in a later release.
- Environments: local (ngrok/Expose for webhooks), staging (separate platform apps), production.
- Config caching, `php artisan optimize`, OPcache; Redis persistence (AOF) for queue durability or accept sweeper as the safety net.
- Object storage + CDN for media; private bucket with signed URLs.

## 6. Environment variables (excerpt)
```
META_APP_ID= META_APP_SECRET= META_GRAPH_VERSION= META_WEBHOOK_VERIFY_TOKEN=
LINKEDIN_CLIENT_ID= LINKEDIN_CLIENT_SECRET= LINKEDIN_API_VERSION=
GOOGLE_CLIENT_ID= GOOGLE_CLIENT_SECRET= YOUTUBE_DAILY_QUOTA=10000
X_CLIENT_ID= X_CLIENT_SECRET=
TIKTOK_CLIENT_KEY= TIKTOK_CLIENT_SECRET=
PINTEREST_APP_ID= PINTEREST_APP_SECRET=
MEDIA_DISK=s3  AWS_BUCKET=...  KMS_KEY_ID=...
SOCIAL_USE_NEW_PIPELINE=false
```

## 7. Runbooks
**A. Posts not publishing**
1. Horizon: are `publish` workers running? Queue wait time?
2. Check `social:sweep-due-targets` ran; check `post_targets` stuck in `publishing` (> 10 min) → reset to `scheduled` if no `external_id`.
3. Check provider circuit breaker and platform status page.
4. Check account status (`reauth_required`?).

**B. Webhooks stopped arriving**
1. Platform app dashboard: subscription active? Endpoint verified? Callback errors?
2. Check invalid-signature counter (secret rotated?).
3. Re-subscribe Page/IG accounts; run polling fallback.

**C. Token expiry wave**
1. List accounts by `token_expires_at`.
2. Trigger refresh where supported; otherwise send reconnect emails/in-app banners.

**D. Platform API version sunset**
1. Bump version in env on staging, run contract + smoke tests, review changelog, deploy.

**E. Suspected token leak**
1. Pause providers, rotate platform secrets + `APP_KEY`, revoke tokens at platform, force reconnect, follow incident steps in `SECURITY.md`.

## 8. Cost & scaling notes
- Horizontal scale workers by queue; media queue on separate machines (CPU/bandwidth).
- Batch metric pulls and cache dashboards (Redis, 5–15 min).
- Archive old `api_call_logs`/`webhook_events` to cold storage.
- Plan limits per workspace: accounts, scheduled posts, team members, AI credits.

## 9. Product backlog ideas (after Phase 7)
Best-time-to-post suggestions, link-in-bio page, RSS-to-post automation, UTM builder with click tracking, evergreen recycling, competitor benchmarking (public data only), Zapier/Make integration via outbound webhooks and public API, white-label for agencies.
