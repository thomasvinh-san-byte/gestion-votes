# External Integrations

**Analysis Date:** 2026-03-16

## APIs & External Services

**Monitoring Webhooks (optional):**
- Any HTTP webhook endpoint — Slack, PagerDuty, Datadog, or generic JSON
  - Config env var: `MONITOR_WEBHOOK_URL`
  - Triggered by: `app/Services/MonitoringService.php` via `curl_init()` when alert thresholds are crossed
  - Payload: JSON `{ type, severity, message, metric, value, threshold, timestamp }`
  - Set to empty string to disable

**Alert Emails (optional):**
- Config env var: `MONITOR_ALERT_EMAILS` — comma-separated emails, or `"auto"` to email all admin users
  - Dispatched by: `app/Services/MonitoringService.php` via `MailerService`
  - Triggered every 5 minutes by the `monitoring` supervisord daemon

## Data Storage

**Primary Database:**
- PostgreSQL 16.8
  - Connection env vars: `DB_DSN` (PDO DSN, e.g. `pgsql:host=db;port=5432;dbname=vote_app`), `DB_USER`, `DB_PASS`
  - In production add `sslmode=require` to `DB_DSN` for cloud-hosted PostgreSQL
  - Client: PDO (`pdo_pgsql` extension), singleton managed by `app/Core/Providers/DatabaseProvider.php`
  - Schema: `database/schema-master.sql` (30 tables)
  - Migrations: `database/migrations/` (20 migration files), applied via `deploy/entrypoint.sh`
  - Seeds: `database/seeds/01_minimal.sql`, `02_test_users.sql`, `03_demo.sql`
  - Docker image: `postgres:16.8-alpine3.21`

**Redis:**
- Redis 7.4
  - Connection env vars: `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_DATABASE`, `REDIS_PREFIX`
  - Client: phpredis (PECL), singleton managed by `app/Core/Providers/RedisProvider.php`
  - Uses:
    - Email queue: list key `agvote:email_queue` (drained by `email:process-queue` CLI command)
    - Rate limiting: `app/Core/Security/RateLimiter.php`
    - Idempotency guard: `app/Core/Security/IdempotencyGuard.php`
    - SSE event bus: per-meeting list keys `agvote:sse:events:{meeting_id}` (60s TTL, 100-event cap)
    - General event queue: `agvote:ws:event_queue` (drained by `EventBroadcaster::dequeue()`)
  - Fallback: all Redis paths fall back to file-based alternatives in `/tmp/ag-vote/` if Redis is unavailable
  - Docker image: `redis:7.4-alpine3.21`

**File Storage:**
- Local filesystem only — no object storage integration
  - Working directory: `/tmp/ag-vote/` (Docker volume `app-storage` mounted at `/tmp/ag-vote`)
  - Used for: SSE file queue (`/tmp/agvote-sse-{meeting_id}.json`), event file queue (`/tmp/agvote-ws-queue.json`), PDF cache, log files
  - Exports (`public/exports/`) — generated XLSX/PDF files served directly by Nginx

**Caching:**
- No dedicated cache layer beyond Redis (Redis used for idempotency keys and SSE event lists)
- PHP OPcache — bytecode cache, 128MB, `validate_timestamps=0` in production

## Email (SMTP)

**Provider:** Any SMTP server (no provider lock-in)
- Config env vars: `MAIL_HOST`, `MAIL_PORT` (default 587), `MAIL_USER`, `MAIL_PASS`, `MAIL_FROM`, `MAIL_FROM_NAME`
- TLS mode: `MAIL_TLS=starttls` (default) | `ssl` (port 465) | `none` (dev only)
- Client: `symfony/mailer` v8.0.4 via `app/Services/MailerService.php`
- Queue: emails stored in `email_queue` PostgreSQL table, drained every 60s by `email:process-queue` supervisord process
- Templates: customizable HTML stored in `email_templates` PostgreSQL table (`app/Services/EmailTemplateService.php`)
- Tracking: open/click events stored in `email_events` PostgreSQL table (`EMAIL_TRACKING_ENABLED` env var)

## Authentication & Identity

**Auth Provider:** Custom session-based auth — no third-party identity provider
- Implementation: email + password login (`app/Core/Security/AuthMiddleware.php`, `app/Core/Security/SessionHelper.php`)
- Sessions: PHP native sessions stored in `/tmp` (configured in `deploy/php.ini`)
- Session cookies: `HttpOnly`, `SameSite=Lax`, `Secure` forced on in non-dev environments by entrypoint
- CSRF: token-based, enforced by `app/Core/Security/CsrfMiddleware.php` (toggle: `CSRF_ENABLED`)
- Roles: RBAC via `app/Core/Security/PermissionChecker.php` and `app/Core/Security/Permissions.php`
- Rate limiting on login endpoint: 3 req/s, burst 5 (Nginx `login` zone) + `app/Core/Security/RateLimiter.php`
- Toggle via env: `APP_AUTH_ENABLED=0` disables auth for local dev only (blocked in production)

## Monitoring & Observability

**Error Tracking:**
- No third-party error tracking (no Sentry, Bugsnag, etc.)
- PHP `error_log()` to stderr → captured by Supervisord → Docker container stdout/stderr

**Metrics & Alerts:**
- Internal monitoring service: `app/Services/MonitoringService.php`
- Metrics collected: DB latency, active connections, disk free %, auth failure counts, meeting/motion/ballot counts
- Stored in `system_metrics` and `system_alerts` PostgreSQL tables
- Dispatched every 5 minutes via `monitor:check` supervisord daemon (`deploy/supervisord.conf`)
- Alert channels: email (`MONITOR_ALERT_EMAILS`) and/or webhook (`MONITOR_WEBHOOK_URL`)
- Thresholds: `MONITOR_AUTH_FAILURES_THRESHOLD` (default 5), `MONITOR_DB_LATENCY_MS` (default 2000), `MONITOR_DISK_FREE_PCT` (default 10), `MONITOR_EMAIL_BACKLOG` (default 100)

**Logs:**
- PHP error log → stderr
- Nginx access/error logs → stdout/stderr
- All streams captured by Supervisord and forwarded to Docker container logs
- `app/Core/Logger.php` — structured logging with request correlation ID (`X-Request-ID` header)

## CI/CD & Deployment

**Hosting:**
- Primary target: Render.com (Docker-based deployment; `PORT` env var support for dynamic binding)
- Also supports: any Docker host behind Cloudflare, HAProxy, or other reverse proxies
- HTTPS termination: at reverse proxy edge; Nginx handles `X-Forwarded-Proto` redirect and HSTS injection

**Container security:**
- Runs as non-root user `www-data`
- Read-only container filesystem (`read_only: true` in `docker-compose.yml`)
- tmpfs mounts for writable directories: `/tmp` (128M), `/var/run` (1M), `/var/log/nginx` (10M)
- `no-new-privileges: true` security option

**CI Pipeline:**
- Not detected (no `.github/workflows/`, `.gitlab-ci.yml`, or similar in repository root)

## Real-Time Communication

**Server-Sent Events (SSE):**
- Endpoint: `public/api/v1/events.php` (not found in listing but referenced in event-stream.js)
- Backend: `app/WebSocket/EventBroadcaster.php` — publishes to Redis list keys `sse:events:{meeting_id}`; file fallback at `/tmp/agvote-sse-{meeting_id}.json`
- Client: `public/assets/js/core/event-stream.js` — `EventSource` with auto-reconnect (max 10 attempts), polling fallback when SSE unavailable
- Toggle: `PUSH_ENABLED=0` disables SSE entirely (polling only)
- Events: `motion.opened`, `motion.closed`, `vote.cast`, `attendance.updated`, `quorum.updated`, `meeting.status_changed`, `speech.queue_updated`

## External CDN (Frontend)

| Resource | Provider | Version | What loads it |
|----------|----------|---------|---------------|
| Google Fonts (Bricolage Grotesque, Fraunces, JetBrains Mono) | `fonts.googleapis.com` + `fonts.gstatic.com` | latest | All `.htmx.html` pages via `<link>` |

Note: chart.js and htmx are **vendored locally** (`public/assets/vendor/`) — they are not loaded from CDN at runtime despite the CSP `https://cdn.jsdelivr.net https://unpkg.com` allowlist (that allowlist is a permissive fallback, not an active dependency).

## Environment Configuration

**Required env vars (application will not function without these):**
- `DB_PASS` — PostgreSQL password (Docker Compose fails if unset)
- `APP_SECRET` — HMAC/session secret (min 32 chars in production)
- `DEFAULT_TENANT_ID` — UUID of the organization (mono-tenant MVP)

**Required in production (entrypoint enforces these):**
- `APP_AUTH_ENABLED=1`
- `CSRF_ENABLED=1`
- `RATE_LIMIT_ENABLED=1`
- `LOAD_SEED_DATA=0`
- `APP_DEBUG=0`

**Optional but recommended:**
- `REDIS_PASSWORD` — Redis auth password
- `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`, `MAIL_FROM` — Email sending
- `MONITOR_ALERT_EMAILS` and/or `MONITOR_WEBHOOK_URL` — Alert notifications
- `CORS_ALLOWED_ORIGINS` — Production domain(s) for CORS

**Secrets location:**
- `.env` file at project root (not committed to git)
- Template: `.env.example`

## Webhooks & Callbacks

**Incoming:**
- None — no external service pushes webhooks into this application

**Outgoing:**
- `MONITOR_WEBHOOK_URL` — alert notifications pushed to Slack/PagerDuty/Datadog/generic HTTP endpoint from `app/Services/MonitoringService.php`

---

*Integration audit: 2026-03-16*
