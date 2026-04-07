# External Integrations

**Analysis Date:** 2026-04-07

## APIs & External Services

**Monitoring Webhooks (outgoing):**
- Any Slack, PagerDuty, Datadog, or compatible webhook URL
  - Triggered by: `MonitoringService::sendWebhook()` in `app/Services/MonitoringService.php`
  - Payload: `{ event: "system_alert", timestamp, code, severity, message, details, source: "ag-vote" }`
  - Auth: None (URL is the secret)
  - Config: `MONITOR_WEBHOOK_URL` env var (optional — disabled when empty)
  - Transport: cURL with 10s timeout, 5s connect timeout
  - Trigger: Alert thresholds crossed (auth failures, slow DB, low disk, email backlog)

## Data Storage

**Database:**
- PostgreSQL 12+
  - Connection: `DB_DSN` (pgsql DSN), `DB_USER`, `DB_PASS`
  - Client: PDO with `pdo_pgsql` + `pgsql` extensions
  - Provider: `app/Core/Providers/DatabaseProvider.php` — singleton PDO, fail-fast on connection error
  - Options set: `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES = false`, `STRINGIFY_FETCHES = false`, `ATTR_TIMEOUT = 10`
  - Statement timeout: `SET statement_timeout = {DB_STATEMENT_TIMEOUT_MS}` (default 30,000ms)
  - Repository base: `AgVote\Repository\AbstractRepository` — provides `selectOne()`, `selectAll()`, `execute()`, `insertReturning()`
  - Migrations: Sequential SQL files in `database/migrations/` (001–20260310 series), applied via `database/setup.sh`
  - Production: Add `sslmode=require` to `DB_DSN`

**File Storage:**
- Local filesystem at `AGVOTE_UPLOAD_DIR` (default `/var/agvote/uploads`)
- Must be a persistent Docker volume in production
- Used for: meeting attachments, uploaded documents
- Working temp dir: `/tmp/ag-vote`

**Caching:**
- Redis (see Redis section below) — used for rate limit counters, SSE queues, session heartbeats
- No separate object cache layer (no Memcached, no APCu for shared data)

## Redis Integration

Redis is **mandatory** — the application refuses to start if Redis is unreachable (throws `RuntimeException` in `app/Core/Application.php` boot sequence).

**Provider:** `app/Core/Providers/RedisProvider.php`
- Singleton connection with auto-reconnect on ping failure
- Config: `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_DATABASE` (default 0), `REDIS_PREFIX` (default `agvote:`)
- Options: `OPT_PREFIX` set to prefix, `OPT_SERIALIZER` set to `SERIALIZER_JSON` by default
- Connect timeout: 2.0 seconds

**Use Cases:**

1. **Rate Limiting** (`app/Core/Security/RateLimiter.php`)
   - Keys: `ratelimit:{context}:{sha256(identifier)}`
   - Atomic Lua script: `INCR` + `EXPIRE` in a single Redis command slot (eliminates race conditions)
   - Returns `{current_count, ttl}`; throws `ApiResponseException` with 429 when limit exceeded
   - Per-context windows: `auth_login`, `admin_ops`, `public_vote`, etc.
   - No filesystem fallback — Redis is required

2. **SSE Event Queue** (`app/SSE/EventBroadcaster.php`)
   - Global queue key: `sse:event_queue` — capped at 1,000 events (`lTrim`)
   - Per-consumer queue keys: `sse:queue:{meetingId}:{consumerId}` — capped at 100 events, TTL 60s
   - Consumer registry: `sse:consumers:{meetingId}` (Redis Set)
   - Fan-out: Pipeline `RPUSH` + `EXPIRE` + `LTRIM` per registered consumer
   - Heartbeat key: `sse:server:active` — checked by `EventBroadcaster::isServerRunning()`
   - Push can be disabled via `PUSH_ENABLED=0` env var

3. **SSE Domain Events via EventDispatcher** (`app/Event/Listener/SseListener.php`)
   - Events broadcast: `vote.cast`, `vote.updated`, `motion.opened`, `motion.closed`, `motion.updated`, `attendance.updated`, `quorum.updated`, `meeting.status_changed`, `speech.queue_updated`, `document.added`, `document.removed`
   - Target scopes: per-meeting (`toMeeting`) and per-tenant (`toTenant`)
   - Dispatched by controllers via `Application::dispatcher()` (Symfony EventDispatcher)
   - SseListener registered in `app/Core/Application.php::initEventDispatcher()`

## Authentication & Identity

**Auth Provider:**
- Custom session-based authentication (no OAuth, no external IdP)
- Implementation: `app/Core/Security/AuthMiddleware.php`
- Sessions stored in PHP file sessions (`session.save_path = /tmp` — NOT Redis-backed)
- Session timeout: configurable per-tenant from DB settings, fallback to env default (30 minutes)
- Re-validation interval: 60 seconds (DB lookup to check `is_active`, role changes)
- Session ID regenerated on privilege escalation (session fixation prevention)
- Password auth: bcrypt hashing via `password_hash()`/`password_verify()`
- API key auth: `API_KEY_ADMIN`, `API_KEY_OPERATOR`, `API_KEY_TRUST` env vars (optional)
- Public voting: HMAC-signed `VoteToken` via `app/Services/VoteTokenService.php` (no session required)
- RBAC: Two-level — system roles (`admin`, `operator`, `auditor`, `viewer`) + meeting roles (`president`, `assessor`, `voter`)

## Email

**SMTP via Symfony Mailer** (`app/Services/MailerService.php`):
- Package: `symfony/mailer` v8.0.4
- DSN built at runtime from config: `smtp://user:pass@host:port` (STARTTLS) or `smtps://...` (TLS)
- TLS modes: `starttls` (port 587, Symfony auto-negotiates), `ssl`/`smtps` (port 465 implicit TLS), `none` (disables peer verification — dev only)
- Mailer instance lazy-initialized and cached per `MailerService` instance
- SMTP settings merge: DB tenant settings override env vars (via `MailerService::buildMailerConfig()` + `SettingsRepository`)
- Config env vars: `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`, `MAIL_TLS`, `MAIL_FROM`, `MAIL_FROM_NAME`, `MAIL_TIMEOUT`

**Email Queue** (`app/Services/EmailQueueService.php`):
- Queue stored in PostgreSQL (`email_queue` table)
- Processed by `app/Command/EmailProcessQueueCommand.php` (CLI/cron)
- Batch size: 25 emails per run
- Stuck processing reset: emails stuck for 30+ minutes are re-queued
- Repository: `app/Repository/EmailQueueRepository.php`

**Email Tracking** (`app/Controller/EmailTrackingController.php`):
- Open tracking: 1x1 GD pixel served via `GET /api/v1/email_pixel.php`
- Click tracking: redirect via `GET /api/v1/email_redirect.php`
- Controlled by `EMAIL_TRACKING_ENABLED` env var (default 1, set to 0 for strict GDPR)
- Events stored in `email_events` table via `app/Repository/EmailEventRepository.php`
- Skips API middleware (no session auth required for pixel/redirect endpoints)

**Email Templates** (`app/Services/EmailTemplateService.php`):
- Templates stored in `email_templates` table (DB-driven, per-tenant)
- Markdown support via `erusev/parsedown` for template body rendering
- Repository: `app/Repository/EmailTemplateRepository.php`

## PDF Generation

**DOMPDF** (`app/Services/ProcurationPdfService.php`):
- Package: `dompdf/dompdf` v3.1.4
- Purpose: Generate proxy (procuration) documents as PDF for download
- Options: HTML5 parser enabled, remote resources disabled (`isRemoteEnabled = false`), default font DejaVu Sans
- Paper: A4 portrait
- Input: HTML rendered by `ProcurationPdfService::renderHtml()` — inline CSS only
- Output: Binary PDF string via `$dompdf->output()`

## XLSX/Spreadsheet Export

**OpenSpout — streaming** (`app/Services/ExportService.php`):
- Package: `openspout/openspout` v5.6.0
- Class: `OpenSpout\Writer\XLSX\Writer`
- Method: `ExportService::streamXlsx()` and `ExportService::streamFullXlsx()`
- Memory: constant regardless of row count (generator/iterable support)
- Use: preferred path for large exports (attendance, votes, full meeting export)
- Multi-sheet: up to 4 sheets (Resume, Emargement, Resultats, Votes) in `streamFullXlsx()`

**PhpSpreadsheet — styled** (`app/Services/ExportService.php`):
- Package: `phpoffice/phpspreadsheet` v1.30.2
- Class: `PhpOffice\PhpSpreadsheet\Spreadsheet`
- Method: `ExportService::createSpreadsheet()`, `ExportService::createFullExportSpreadsheet()`
- Features: bold headers, grey fill, auto-column width, frozen first row
- Use: smaller datasets requiring styling; loaded entirely in memory

## Monitoring & Observability

**Internal Monitoring** (`app/Services/MonitoringService.php`):
- Invoked by `app/Command/MonitoringCheckCommand.php` (cron every 5 minutes recommended)
- Metrics collected: DB latency, active connections, disk space, auth failures (15m), email backlog, counts (meetings, motions, tokens, audit events), PHP version, memory usage
- Metrics persisted to `system_metrics` table
- Alerts deduplicated by 10-minute window in `system_alerts` table
- Alert channels: email (to `MONITOR_ALERT_EMAILS` or all admin users) + outgoing webhook

**Error Logging:**
- `error_log()` for technical errors to PHP error log
- `AgVote\Core\Logger` static methods (`debug`, `info`, `warning`, `error`, etc.) — PSR-3 level structure
- Request ID tracking via `Logger::getRequestId()` in `X-Request-ID` response header
- Audit trail: `audit_log()` global function — writes to `audit_events` table for all business events

**Health Check:**
- Endpoint: `GET /api/v1/health.php`
- Used by Docker healthcheck every 30s

## CI/CD & Deployment

**Hosting:**
- Docker container (primary); PHP 8.4-FPM + Nginx (alternative)
- Image source: `Dockerfile` in project root (multi-stage build)

**CI Pipeline:**
- Not detected in repository (no `.github/workflows/`, no `.gitlab-ci.yml`)

## Webhooks & Callbacks

**Incoming:**
- None detected (no inbound webhook endpoints)

**Outgoing:**
- `MONITOR_WEBHOOK_URL` — Alert notifications to Slack/PagerDuty/Datadog (optional, via cURL in `MonitoringService`)

## Environment Configuration Summary

**Required (application will not start without these):**
- `DB_DSN`, `DB_USER`, `DB_PASS` — PostgreSQL connection
- `REDIS_HOST`, `REDIS_PORT` — Redis connection (password optional in dev)
- `APP_SECRET` — Session security (must be 32-byte hex in production)

**Optional (features degraded or disabled when absent):**
- `MAIL_HOST`, `MAIL_PORT` — Email disabled if not set (`MailerService::isConfigured()` returns false)
- `MONITOR_WEBHOOK_URL` — Webhook alerts disabled
- `MONITOR_ALERT_EMAILS` — Email alerts disabled
- `API_KEY_*` — API key auth disabled (session auth still works)
- `EMAIL_TRACKING_ENABLED=0` — Disables open/click tracking pixels

---

*Integration audit: 2026-04-07*
