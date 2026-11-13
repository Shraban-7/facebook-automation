# API Integrations

> **Verify before building.** Quotas, scopes, pricing, API versions, and review requirements change frequently. Treat every number here as a starting point and confirm in the platform's current official docs. Items marked **(verify)** are the most volatile.

Common to all providers: store versions in config, map errors to `ErrorClass`, log request IDs, never log tokens, and wrap calls in the rate limiter + circuit breaker.

## Summary matrix

| Platform | Auth | Publish | Native schedule | Messaging | Comments | Analytics | Approval needed |
|---|---|---|---|---|---|---|---|
| Facebook Pages | OAuth 2.0 (Facebook Login) | text, photo, video, link | Yes (`scheduled_publish_time`) | Messenger Platform | Yes | Page insights | Meta App Review + Business Verification for most |
| Instagram (Business/Creator) | Meta OAuth | image, video/Reels, carousel | No (use our queue) | IG Messaging | Yes | IG insights | App Review |
| Threads | Meta OAuth | text, image, video, carousel | No | Limited | Replies | Insights | App Review |
| LinkedIn | OAuth 2.0 (3-legged) | text, image, video, article link, docs | No | Not for general use | Limited | Org analytics | Community Management API approval for org pages |
| YouTube | Google OAuth 2.0 (offline) | video upload | Yes (`publishAt`) | n/a | Yes | YouTube Analytics API | API audit for public uploads/quota |
| X | OAuth 2.0 PKCE | text, media | No | DM (tier-dependent) | Replies | Tier-dependent | Paid access **(verify)** |
| TikTok | OAuth 2.0 | video/photo via Content Posting API | No | No | Limited | Limited | App audit for public posting |
| Pinterest | OAuth 2.0 | pins | No | No | Limited | Yes | Trial → standard access |

---

## 1. Facebook Pages (Graph API) — already implemented

- **Base:** `https://graph.facebook.com/{version}/` (version from config).
- **Login:** Facebook Login OAuth → short-lived user token → exchange for long-lived user token → fetch Page tokens via `GET /me/accounts`. Page tokens derived from a long-lived user token do not expire in the usual flow, but can be invalidated (password change, permission removal); handle `190` errors.
- **Typical scopes (config-driven):** `pages_show_list`, `pages_manage_posts`, `pages_read_engagement`, `pages_manage_metadata`, `pages_messaging`, `pages_manage_engagement`, `read_insights` (verify which your use case actually needs).

| Action | Call |
|---|---|
| List Pages | `GET /me/accounts?fields=id,name,access_token,category` |
| Text/link post | `POST /{page-id}/feed` (`message`, `link`) |
| Scheduled post | `POST /{page-id}/feed` with `published=false`, `scheduled_publish_time` (UNIX) |
| Photo | `POST /{page-id}/photos` (`url`, `caption`) |
| Video | `POST /{page-id}/videos` (use resumable upload for large files) |
| Delete post | `DELETE /{post-id}` |
| Comments list | `GET /{post-id}/comments` |
| Reply to comment | `POST /{comment-id}/comments` (`message`) |
| Hide comment | `POST /{comment-id}` (`is_hidden=true`) |
| Send message | `POST /me/messages` (`recipient`, `message`, `messaging_type`) with Page token |
| Insights | `GET /{page-id}/insights?metric=...&period=day` |
| Webhooks | Subscribe app to Page (`POST /{page-id}/subscribed_apps` with `subscribed_fields`) |

**Notes**
- Webhook verify: `GET` handshake with `hub.mode`, `hub.verify_token`, `hub.challenge`; `POST` signed with `X-Hub-Signature-256` (HMAC-SHA256 of raw body using App Secret).
- Messaging policy: respect the 24-hour standard messaging window; outside it only approved tags/message types **(verify)**.
- Page Insights metric names have been changing and older metrics are being retired **(verify)**. Isolate in `MetricMapper` so a change doesn't touch core code.
- Rate limits: read `X-App-Usage` and `X-Business-Use-Case-Usage` response headers.
- Required for production: App Review per permission, Business Verification, privacy policy URL, **data deletion callback URL**.

## 2. Instagram (via Meta)

- Connect IG Business/Creator accounts linked to a Facebook Page (or use Instagram Login flavor if you don't need Page features **(verify current options)**).
- **Publish is a two-step container flow:**
  1. `POST /{ig-user-id}/media` with `image_url` or `video_url` (+ `media_type=REELS` for Reels, `caption`)
  2. Poll `GET /{container-id}?fields=status_code` until `FINISHED`
  3. `POST /{ig-user-id}/media_publish` with `creation_id`
- Carousel: create child containers (`is_carousel_item=true`), then a parent with `children`.
- Media must be fetchable by Meta from a **public HTTPS URL** (use short-lived signed URLs).
- Daily API-publish cap per account exists **(verify current number)**; track and surface to the user.
- Comments/messages via the same Meta webhook app; subscribe to IG fields.

## 3. Threads

- `POST /{threads-user-id}/threads` (`media_type=TEXT|IMAGE|VIDEO`, `text`, `image_url`) → `POST /{threads-user-id}/threads_publish` (`creation_id`).
- Scopes like `threads_basic`, `threads_content_publish`, `threads_manage_replies`, `threads_read_replies` **(verify)**.
- Text limit and daily publish limits **(verify)**.

## 4. LinkedIn

- **Base:** `https://api.linkedin.com/rest/` with headers:
  - `Linkedin-Version: YYYYMM` (config)
  - `X-Restli-Protocol-Version: 2.0.0`
  - `Authorization: Bearer {token}`
- **Auth:** OAuth 2.0 authorization code. Access tokens are typically ~60 days; refresh tokens are only available to approved programs **(verify)**, so plan a **reconnect flow** and expiry reminders at 14/7/1 days.
- **Scopes:** member posting `w_member_social`; organization posting/reading `w_organization_social`, `r_organization_social`, plus admin discovery scopes. Organization/page management generally needs **Community Management API** approval **(verify)**.
- **Author URN:** `urn:li:person:{id}` or `urn:li:organization:{id}`.

| Action | Call |
|---|---|
| Create text post | `POST /rest/posts` body: `author`, `commentary`, `visibility: "PUBLIC"`, `distribution: {feedDistribution:"MAIN_FEED"}`, `lifecycleState:"PUBLISHED"` |
| Image upload | `POST /rest/images?action=initializeUpload` → `PUT` bytes to returned `uploadUrl` → use `image` URN in post content |
| Video upload | `POST /rest/videos?action=initializeUpload` → chunked PUT → `finalizeUpload` |
| Delete post | `DELETE /rest/posts/{urn-encoded}` |
| Org admin discovery | organization ACLs endpoint to list orgs the user administers |
| Analytics | organization share statistics / follower statistics endpoints |

**Notes:** the `commentary` field requires escaping of reserved characters (`(`, `)`, `[`, `]`, `{`, `}`, `<`, `>`, `@`, `#`, `*`, `~`, `_`, `\`, `|`) when sending plain text. Native scheduling is not supported; schedule in our queue. Comment and webhook access is limited **(verify)**, so use polling for some data.

## 5. YouTube (Data API v3 + Analytics API)

- **Auth:** Google OAuth 2.0 with `access_type=offline` and `prompt=consent` on first connect to obtain a refresh token. Scopes: `youtube.upload`, `youtube.readonly` (or `youtube`), `youtube.force-ssl` for comments, `yt-analytics.readonly` for analytics.
- **Upload:** resumable protocol.
  1. `POST https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status` with metadata → returns session URI
  2. `PUT` chunks (multiple of 256 KB) with `Content-Range`; on interruption, query the session to resume
- **Scheduling:** upload as `privacyStatus=private` with `status.publishAt` (ISO 8601 UTC).
- **Other calls:** `videos.update` (metadata), `thumbnails.set`, `playlistItems.insert`, `commentThreads.list`, `comments.insert`, `comments.setModerationStatus`.
- **Quota:** default project quota is 10,000 units/day; `videos.insert` is expensive **(verify current cost in the quota calculator)**. Track in `quota_usage`; request quota extension via the audit form for production.
- **Unverified API projects:** uploads from unaudited projects may be restricted to private **(verify)**. Plan the compliance audit early.
- **Realtime:** YouTube push notifications use PubSubHubbub for new uploads/updates on feeds; comments are best polled.
- **Analytics:** YouTube Analytics API `reports.query` (views, estimatedMinutesWatched, subscribersGained, etc.).

## 6. X (Twitter)

- OAuth 2.0 with PKCE; `POST /2/tweets` for posts; media upload endpoints for images/video.
- Access is **paid and tier/pay-per-use based; pricing and limits change** **(verify)**. Build X behind a feature flag and per-workspace plan check.
- Strict rate limits per user/app; map `429` and `x-rate-limit-reset`.

## 7. TikTok

- Content Posting API: `POST /v2/post/publish/video/init/` (direct post or upload-to-inbox), chunked upload, then status polling.
- Unaudited apps are typically restricted to private visibility; **app audit required** for public posting **(verify)**.
- Creator info query is required before posting (privacy options, max duration).

## 8. Pinterest

- API v5: `POST /v5/pins` (board_id, media_source, title, description, link); `GET /v5/boards`; analytics via `/v5/user_account/analytics`.
- Trial access before standard access **(verify)**.

---

## Error mapping (all providers)

| Raw signal | `ErrorClass` | Action |
|---|---|---|
| 401, Meta code 190, Google `invalid_grant` | `AuthExpired` | Mark account `reauth_required`, notify, stop retries |
| 403 permission/scope | `PermissionMissing` | Notify with the missing permission, stop retries |
| 429, rate-limit codes, quota exceeded | `RateLimited` | Delay until reset, open breaker if repeated |
| 5xx, timeout, connection reset | `Retryable` | Backoff retry |
| 400 with content policy / invalid media | `ContentRejected` | Fail, show message to user |
| Anything else | `Fatal` | Fail, alert ops |

## Capability validation examples (pre-flight)
- Text length vs. `capabilities.maxTextLength`
- Image aspect ratio and size, video duration/codec
- Hashtag count, link allowed with media or not
- YouTube: title ≤ 100 chars, description limit, category id, `madeForKids` declared
- Instagram: media must be reachable via public URL

## Sandbox/testing strategy
- Use a `FakeProvider` for CI.
- Use `Http::fake()` with recorded fixtures per provider for adapter tests.
- Keep a manual smoke-test checklist per provider using dedicated test accounts and apps in development mode.
