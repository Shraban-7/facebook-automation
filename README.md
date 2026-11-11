# MetaPulse — Facebook Page Automation Studio

An enterprise-grade Facebook Page automation platform built on **Meta's Official Graph API (`v21.0`) and Messenger Platform**. Zero browser automation, scraping, or unofficial endpoints are used.

---

## 1. Architecture & Project Structure Overview

```text
├── app/
│   ├── Console/Commands/
│   │   ├── DispatchDueScheduledPostsCommand.php   # Runs every minute to queue due scheduled posts
│   │   ├── RefreshExpiringPageTokensCommand.php   # Runs daily to refresh tokens expiring within 7 days
│   │   └── SyncAllPagesInsightsCommand.php        # Runs hourly to pull Page Insights & warm cache
│   ├── Exceptions/
│   │   └── FacebookGraphException.php             # Classifies Graph error codes (#190, #4, #10, #200, #551)
│   ├── Http/
│   │   ├── Controllers/Facebook/
│   │   │   ├── DashboardController.php            # Command overview & workspace status
│   │   │   ├── FacebookPageController.php         # Meta OAuth 2.0 flow & multi-page management
│   │   │   ├── PostSchedulerController.php        # Calendar, drafts, schedules & retry actions
│   │   │   ├── AutoReplyRuleController.php        # Keyword/fallback rules, comment moderation & simulator
│   │   │   ├── AnalyticsController.php            # 7/30/90-day cached insights & top posts
│   │   │   ├── ActivityLogController.php          # API call logs, webhook inspector & rate-limit gauge
│   │   │   └── WebhookController.php              # Verify-token handshake & X-Hub-Signature-256 handler
│   │   └── Requests/Facebook/                     # Form Request validation & authorization
│   ├── Jobs/
│   │   ├── ConnectFacebookPagesJob.php            # Exchanges OAuth code -> 60d token -> Page tokens
│   │   ├── RefreshFacebookPageTokenJob.php        # Refreshes expiring long-lived Page tokens
│   │   ├── PublishScheduledPostJob.php            # Publishes posts with exponential backoff & failure_log
│   │   ├── ProcessWebhookEventJob.php             # Processes deduplicated webhook events asynchronously
│   │   ├── SendAutoReplyJob.php                   # Sends Messenger & comment replies via Graph API
│   │   ├── ModerateCommentJob.php                 # Hides comments matching banned words
│   │   └── SyncPageInsightsJob.php                # Pulls daily Page insights & invalidates cache
│   ├── Models/                                    # User, FacebookPage, Post, ScheduledPost, AutoReplyRule,
│   │                                              # Message, WebhookEvent, Insight, ActivityLog
│   ├── Policies/                                  # FacebookPagePolicy, PostPolicy, AutoReplyRulePolicy
│   └── Services/Facebook/
│       ├── GraphApiService.php                    # Central Graph API client with appsecret_proof & logging
│       ├── WebhookService.php                     # HMAC-SHA256 verification & event_id deduplication
│       ├── AutoReplyService.php                   # Keyword matching, 24h window guard & moderation
│       ├── PostSchedulerService.php               # Transactional post scheduling & due-post dispatcher
│       ├── InsightsService.php                    # Insights aggregation & 7/30/90-day Redis caching
│       ├── RateLimitService.php                   # Per-user limits & X-Page-Usage / X-App-Usage parser
│       ├── ActivityLogService.php                 # Redacted audit logging of all API & webhook events
│       └── DemoWorkspaceSeederService.php         # Sandbox seeder for instant local evaluation
├── config/facebook.php                            # .env-driven Graph API, OAuth & rate-limit config
├── database/migrations/                           # 8 domain migrations with foreign keys & composite indexes
├── resources/js/pages/
│   ├── dashboard.tsx                              # Command Overview
│   └── facebook/
│       ├── pages.tsx                              # OAuth Connection & Multi-Page Manager
│       ├── scheduler.tsx                          # Interactive Monthly Calendar, Live Preview & Failure Logs
│       ├── auto-replies.tsx                       # Rule Builder, 24h Window Monitor & Webhook Simulator
│       ├── analytics.tsx                          # 7/30/90-Day Charts & Top-Performing Posts
│       └── activity-logs.tsx                      # Audit Logs, Rate-Limit Telemetry & Webhook Inspector
└── tests/
    ├── Feature/Facebook/
    │   ├── WebhookHandlerTest.php                 # Handshake, HMAC verification, idempotency, 24h window
    │   └── PostSchedulerTest.php                  # Scheduling, backoff failure_log, Graph #4 & #190 handling
    └── Unit/Facebook/
        └── GraphApiAndRateLimitTest.php           # Encrypted token casts, X-Page-Usage throttling, error codes
```

---

## 2. Setup Guide: Meta App, Webhooks, App Review & Local Testing with `ngrok`

### Step 1: Environment Configuration
Copy `.env.example` to `.env` and configure your Meta App credentials:

```env
FACEBOOK_APP_ID=your_meta_app_id
FACEBOOK_APP_SECRET=your_meta_app_secret
FACEBOOK_REDIRECT_URI="${APP_URL}/oauth/facebook/callback"
FACEBOOK_WEBHOOK_VERIFY_TOKEN=fb-automation-verify-token
FACEBOOK_GRAPH_VERSION=v21.0
FACEBOOK_RATE_LIMIT_HOURLY=180
FACEBOOK_USAGE_THRESHOLD_PERCENT=80
```

Run migrations, seed the sandbox data, and build frontend assets:

```bash
composer install
php artisan migrate --seed
npm install && npm run build
```

Default sandbox login credentials:
- **Email**: `test@example.com`
- **Password**: `password`

### Step 2: Creating the Meta App
1. Go to [Meta for Developers](https://developers.facebook.com/apps/) and click **Create App**.
2. Select **Other** → **Business** as the app type.
3. Add the **Facebook Login for Business** and **Webhooks** products, plus **Messenger** if using Messenger auto-replies.
4. Under **Facebook Login → Settings**, add your Valid OAuth Redirect URI:
   - Local: `http://localhost:8000/oauth/facebook/callback` (or your `ngrok` HTTPS URL)
5. Copy the **App ID** and **App Secret** from **App Settings → Basic** into your `.env`.

### Step 3: Local Webhook Testing with `ngrok`
1. Start the Laravel app, queue worker (or Horizon), and scheduler:
   ```bash
   php artisan serve --port=8000
   php artisan queue:work
   php artisan schedule:work
   ```
2. Expose port `8000` via `ngrok`:
   ```bash
   ngrok http 8000
   ```
3. In the Meta App Dashboard, navigate to **Webhooks**, select the **Page** object, and click **Subscribe to this object**:
   - **Callback URL**: `https://<your-subdomain>.ngrok-free.app/api/webhooks/facebook`
   - **Verify Token**: value of `FACEBOOK_WEBHOOK_VERIFY_TOKEN` in `.env`
4. Subscribe to the following Page webhook fields:
   - `feed` (for new post comments and comment moderation)
   - `messages` (for inbound Messenger DMs)
   - `messaging_postbacks`

### Step 4: App Review Permissions
In **Development Mode**, all permissions work immediately for users who have an **Admin, Developer, or Tester** role on the Meta App and manage the target Facebook Page. To serve public users in **Live Mode**, submit the following 5 permissions for **Meta App Review**:
- `pages_show_list` — List the user's managed Pages during OAuth setup.
- `pages_manage_posts` — Publish and manage scheduled text, image, and link posts.
- `pages_read_engagement` — Pull Page Insights (`reach`, `engagement`, `followers`) and read comments.
- `pages_messaging` — Send automated Messenger responses within the 24-hour window.
- `pages_manage_metadata` — Subscribe the Page to webhook events (`/{page-id}/subscribed_apps`) and moderate/hide comments.

---

## 3. Platform Limitations & Policy Compliance

1. **Meta App Review Requirement**: Until `pages_manage_posts`, `pages_read_engagement`, `pages_messaging`, and `pages_manage_metadata` pass Meta App Review and Business Verification, only App Admins/Developers/Testers can connect Pages.
2. **24-Hour Standard Messaging Window**: Meta's Messenger Platform policy strictly prohibits sending standard automated replies (`messaging_type: RESPONSE`) more than **24 hours** after the user's last inbound interaction. `AutoReplyService` and `SendAutoReplyJob` automatically verify `Message::isWithin24HourWindow()` and mark expired conversations as `skipped_outside_window` (and handle Graph Error `#10` / subcode `2018278`).
3. **Graph API Rate Limits (`X-Page-Usage` & `X-App-Usage`)**: Meta enforces dynamic rolling 1-hour rate limits (`200 * engaged_users` per day/hour) reported via `X-Page-Usage` and `X-App-Usage` headers (`call_count`, `total_cputime`, `total_time`). `RateLimitService` parses these headers on every call and preemptively throttles outbound jobs at 80% capacity to prevent app-wide blocks.
4. **Token Expiry & Revocation**: Short-lived user tokens expire in 1–2 hours; exchanging them via `fb_exchange_token` produces 60-day long-lived user tokens, which in turn yield long-lived Page access tokens. However, if a user changes their Facebook password or revokes app permissions, Meta immediately invalidates the token with **Graph Error `#190`** (`OAuthException`), requiring the user to re-authenticate via OAuth.
