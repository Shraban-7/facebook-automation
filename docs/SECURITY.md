# Security Design

## 1. Threat model (short)
| Asset | Threats | Primary controls |
|---|---|---|
| OAuth tokens | DB leak, log leak, XSS exfiltration, insider access | Encryption at rest, separate table, never exposed to frontend, log redaction, key rotation |
| User accounts | Credential stuffing, session hijack | Rate limiting, 2FA, secure cookies, session rotation |
| Webhooks | Forgery, replay, flooding | Signature verification, dedupe, rate limit, size cap |
| Publishing | Unauthorized posts, cross-tenant access | Policies, workspace scoping, approval flows, audit log |
| Media uploads | Malware, SSRF, storage abuse | MIME/magic-byte validation, size caps, no server-side fetch of arbitrary URLs without SSRF guard |
| Platform apps | Secret leak, app suspension | Secrets manager, least privilege scopes, compliance callbacks |

## 2. Token vault
- Store in `oauth_tokens`, **not** on `social_accounts`.
- Use Laravel `encrypted` casts (AES-256-CBC/GCM via `APP_KEY`). For higher assurance, use **envelope encryption**: a per-row data key encrypted by a KMS master key (AWS KMS, GCP KMS, or HashiCorp Vault). `key_version` column supports rotation.
- Rotate `APP_KEY` using Laravel's `APP_PREVIOUS_KEYS` support and re-encrypt in a batch command.
- `$hidden` on model; never `->toArray()` or Inertia-share tokens. Add a test asserting tokens never appear in Inertia props or API resources.
- Decrypt only inside the job that needs it, close to the HTTP call; do not pass tokens through queue payloads, pass `social_account_id`.
- On disconnect: call the platform's revoke endpoint when available, delete token rows, set account `revoked`, keep minimal audit data.
- Alert on tokens expiring soon and on abnormal refresh failures.

## 3. OAuth hardening
- `state`: random, single-use, bound to session + workspace + provider, expires in ≤10 minutes. Reject mismatches.
- **PKCE** wherever supported (X, TikTok, Google, LinkedIn if available).
- Exact-match redirect URIs registered per environment; no open redirects (`return_to` must be an allow-listed relative path).
- Request **minimum scopes**; request additional scopes incrementally. Persist granted scopes and compare to required scopes before enabling features.
- Verify the connecting user actually administers the Page/Organization/Channel before storing it.
- Do not log full callback URLs (they contain codes).

## 4. Application authentication (custom auth)
- Hash with Argon2id/bcrypt via Laravel `Hash`; rehash on login if cost changed.
- Rate-limit login/password reset per IP + per email; generic error messages.
- Email verification, secure password reset tokens (hashed, short-lived, single-use).
- **2FA (TOTP)** with recovery codes (hashed); require for workspace owners/admins.
- Session: `Secure`, `HttpOnly`, `SameSite=Lax`; regenerate on login/privilege change; invalidate other sessions on password change; list/revoke active sessions.
- CSRF on all state-changing routes (Inertia handles this); webhook routes are the explicit exception and are protected by signatures.
- Consider passkeys (WebAuthn) later.

## 5. Authorization
- Policies for every model; global workspace scope; tests that user A can't access workspace B resources (IDOR tests per route).
- Roles: `owner` (billing, delete workspace), `admin` (members, accounts), `editor` (create/schedule), `viewer` (read).
- Optional per-account permissions (editor limited to selected accounts).
- Approval workflow: editors can't publish to accounts marked "approval required".
- Use ULIDs in routes; never trust IDs from the client without policy checks.

## 6. Webhook security
| Provider | Verification |
|---|---|
| Meta (FB/IG/Threads) | `X-Hub-Signature-256` = `sha256=` + HMAC-SHA256(raw body, App Secret); `hub.verify_token` for the GET handshake. Compare with `hash_equals`. |
| LinkedIn | Signature header per current docs **(verify)**; validate with client secret |
| YouTube (PubSubHubbub) | Verify `hub.secret` HMAC if configured; validate topic and lease |
| Others | Follow the platform's documented signature scheme |

Controls: verify on the **raw** body, constant-time compare, max body size, store raw payload, dedupe, dispatch async, rate-limit by IP, optional IP allow-list if the platform publishes ranges, respond 200 quickly. Reject and log invalid signatures (counter + alert on spikes).

## 7. Input, media, and SSRF
- Validate all input with Form Requests; sanitize/escape any user content rendered back (Vue escapes by default; avoid `v-html`).
- Uploads: allowlist MIME **and** verify magic bytes; cap size; store outside public root; random filenames; strip EXIF GPS where appropriate; optional antivirus scan (ClamAV) for uploads.
- If you allow "import media from URL": resolve DNS, block private/link-local/loopback ranges (`10/8`, `172.16/12`, `192.168/16`, `127/8`, `169.254/16`, `::1`, `fc00::/7`), re-check after redirects, limit redirects/size/time.
- Signed, short-lived URLs for media the platforms must fetch; revoke after publish.

## 8. Secrets & configuration
- App IDs/secrets only in environment or secrets manager; never in the repo or frontend bundles. `.env` not committed; separate credentials per environment.
- Different Meta/Google/LinkedIn apps for dev, staging, production.
- Rotate platform secrets on staff changes and suspected leaks; document the procedure.
- Separate DB users: app (DML only) vs. migrations (DDL).

## 9. Logging, audit, privacy
- **Redact** `access_token`, `refresh_token`, `client_secret`, `Authorization`, `code`, `signed_request` in all logs (Monolog processor) and in `api_call_logs`/`publish_attempts.response_excerpt`.
- `activity_logs` is append-only: connect/disconnect account, role changes, scheduled/published/deleted posts, rule changes, exports, login events, 2FA changes.
- Data minimization: store only the inbox data needed; configurable retention (e.g., messages 90–365 days).
- GDPR/CCPA: data export and delete-account flows; workspace deletion purges tokens, media, and personal data after a grace period.

## 10. Platform compliance (non-negotiable for production)
- **Meta:** privacy policy URL, terms URL, **data deletion callback** (parse `signed_request`, verify signature, delete data, return `{url, confirmation_code}`), deauthorize callback, App Review, Business Verification where required, respect Messenger policies (windows, no spam, opt-out).
- **Google/YouTube:** OAuth consent screen verification, limited-use disclosure for Google user data, API Services Terms, compliance audit for quota/public uploads, provide a way to revoke access.
- **LinkedIn:** adhere to API Terms (no scraping, no unsolicited automation of member actions like connection requests/likes), approved use cases only.
- **All:** honor user revocation promptly; do not store data you aren't allowed to; no automated engagement that violates platform spam policies.

## 11. Abuse and automation safety
- Per-workspace publishing caps and per-account pacing to avoid platform spam flags.
- Duplicate-content detection warnings across accounts.
- Kill switch per account/provider (`paused`) and a global one for incidents.
- Auto-reply guardrails: cooldowns, max replies per user per day, loop prevention (ignore own page's messages), human handoff keywords.

## 12. Infrastructure
- HTTPS everywhere, HSTS, TLS 1.2+; secure headers (CSP, X-Frame-Options, Referrer-Policy, Permissions-Policy).
- Redis: password/ACL, TLS if networked, not publicly exposed. MySQL: private network, TLS, encrypted volumes and backups.
- Backups encrypted, tested restores, tokens included in encrypted backups only.
- Dependency scanning (`composer audit`, `npm audit`, Dependabot/Renovate), pinned lockfiles, CI secret scanning.
- Least-privilege cloud IAM for the media bucket (no public listing; signed URLs).

## 13. Security testing checklist
- [ ] Tokens never in logs, Inertia props, API responses, or queue payloads
- [ ] State/PKCE tamper tests; callback replay rejected
- [ ] Cross-workspace IDOR tests on every resource route
- [ ] Webhook: bad signature rejected, duplicate ignored, oversize rejected
- [ ] SSRF tests on URL-import features
- [ ] Rate limits on login, OAuth start, webhooks, public endpoints
- [ ] Disconnect revokes and purges
- [ ] Dependency audit clean in CI
- [ ] Pen test / threat model review before public launch

## 14. Incident response (summary)
1. Detect (alerts on auth-failure spikes, token refresh failures, invalid-signature spikes).
2. Contain: pause providers/accounts, rotate platform secrets and `APP_KEY`.
3. Invalidate: force token re-auth where needed; revoke at platform.
4. Notify affected users and platforms as required by law and terms.
5. Post-mortem; update this document and tests.
