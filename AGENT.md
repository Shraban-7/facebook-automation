You are a senior full-stack engineer. Build a Facebook Page automation tool using Meta's official Graph API and Messenger Platform only. Do not use browser automation, scraping, or unofficial endpoints.

## Tech stack
- Backend: Laravel 11 (PHP 8.3), MySQL, Redis (queues + cache), Laravel Horizon
- Frontend: Inertia.js + Vue 3 (Composition API, TypeScript), Tailwind CSS
- Auth: Custom Auth, with Facebook Login (OAuth) to connect Pages

## Core features
1. Page connection
   - Facebook Login OAuth flow, request only the permissions needed (pages_show_list, pages_manage_posts, pages_read_engagement, pages_messaging, pages_manage_metadata)
   - Store long-lived Page access tokens encrypted; handle token expiry and refresh
   - Support multiple Pages per user

2. Post scheduler
   - Create text, image, and link posts; schedule for a future date/time
   - Calendar view in Vue, plus drafts, edit, and delete
   - Laravel scheduler + queued jobs to publish at the scheduled time
   - Retry with backoff on API failures, with a clear failure log

3. Auto-reply (Messenger and comments)
   - Webhook endpoint with signature verification (X-Hub-Signature-256) and the verify-token handshake
   - Keyword-based rules (e.g., "price" → send a canned reply), with fallback reply
   - Respect the 24-hour messaging window and Meta's messaging policies
   - Comment auto-reply plus optional moderation (hide comments with banned words)

4. Analytics dashboard
   - Pull Page insights (reach, engagement, followers) on a schedule and cache them
   - Charts for the last 7/30/90 days, top-performing posts

5. Activity logs and admin
   - Log every API call, webhook event, and error
   - Per-user rate-limit handling that respects Graph API limits

## Database
Design migrations for: users, facebook_pages, posts, scheduled_posts, auto_reply_rules, messages, webhook_events, insights, activity_logs. Include indexes and foreign keys.

## Requirements
- Service classes for Graph API calls (no logic in controllers), Form Requests for validation, Policies for authorization
- Queue every external API call; never call Facebook in the request cycle
- Idempotent webhook handling (dedupe by event ID)
- .env-based config for App ID, App Secret, webhook verify token, API version
- Feature and unit tests for the scheduler and webhook handler
- Clear error handling for Graph API error codes (expired token, permission missing, rate limit)

## Output
1. Project structure overview
2. Migrations and models
3. Services, jobs, controllers, routes
4. Vue pages/components
5. Setup guide: creating the Meta App, configuring webhooks, App Review permissions, and local testing with ngrok
6. A list of limitations (e.g., permissions that need App Review)

Build it step by step, starting with the OAuth connection and the database, and confirm each stage before moving on.
