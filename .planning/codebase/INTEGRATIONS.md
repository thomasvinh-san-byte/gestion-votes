# External Integrations

**Analysis Date:** 2026-04-07

## APIs & External Services

**Email Tracking (Optional):**
- HTTP pixel tracking via email - Optional feature controlled by `EMAIL_TRACKING_ENABLED` env var
  - When enabled: Generates pixel URLs in outbound emails to track opens
  - Implementation: `app/Services/MailerService.php` injects tracking pixel as 1x1 transparent GIF
  - Configurable: Set `EMAIL_TRACKING_ENABLED=0` for RGPD-strict environments

**Monitoring Webhooks (Optional):**
- Generic webhook notifications for monitoring alerts
  - Service: `app/Services/MonitoringService.php`
  - Config: `MONITOR_WEBHOOK_URL` env var
  - Protocol: HTTP POST with JSON payload
  - Use cases: Slack, PagerDuty, Datadog, custom endpoints
  - No standard SDK used - raw cURL via `curl_init()`

## Data Storage

**Databases:**
- PostgreSQL 12+
  - Connection: `DB_DSN`, `DB_USER`, `DB_PASS` env vars
  - DSN format: `pgsql:host=localhost;port=5432;dbname=vote_app`
  - Production requires SSL: `pgsql:host=<host>;port=5432;dbname=vote_app;sslmode=require`
  - Client: PDO with pdo_pgsql extension
  - Schema: `database/schema-master.sql` (definitive source)
  - Migrations: `database/migrations/` directory (applied via `database/setup.sh`)

**File Storage:**
- Local filesystem only
  - Upload directory: `AGVOTE_UPLOAD_DIR` env var (default: `/var/agvote/uploads`)
  - Docker: Mounted as persistent volume
  - Development: Writable directory in project
  - Contents: User-uploaded files (meeting documents, etc.)

**Caching:**
- Redis (optional with automatic fallback)
  - Connection: `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_DATABASE`, `REDIS_PREFIX` env vars
  - Client: phpredis extension (pecl install redis)
  - Use cases:
    - Rate limiting: `AgVote\Core\Security\RateLimiter` uses Redis INCR/EXPIRE
    - Event queue: `AgVote\SSE\EventBroadcaster` uses Redis LPUSH/LRANGE for SSE events
    - Session storage: Optional (PHP session files default)
  - Fallback: If Redis unavailable, EventBroadcaster uses `/tmp/agvote-sse-queue.json` (file-based)
  - `AgVote\Core\Providers\RedisProvider::isAvailable()` checks if phpredis extension is loaded

## Authentication & Identity

**Auth Provider:**
- Custom session-based authentication
  - Implementation: `app/Core/Security/AuthMiddleware.php`
  - Mechanism: PHP sessions (`$_SESSION`) with email/password login
  - Password hashing: PHP `password_hash()` / `password_verify()`
  - Session timeout: Configurable per-tenant (default 30 minutes)
  - Session revalidation: User role/is_active checked every 60 seconds

**API Key Authentication (Optional):**
- Header-based API keys for programmatic access
  - Keys: `API_KEY_ADMIN`, `API_KEY_OPERATOR`, `API_KEY_TRUST` env vars
  - Delivery: HTTP header `X-API-Key`
  - Leave empty to disable
  - Roles tied to keys: different permissions per key type
  - Implementation: `AgVote\Core\Security\AuthMiddleware.php` checks `$_SERVER['HTTP_X_API_KEY']`

**Role-Based Access Control (RBAC):**
- Two-level permission system:
  1. System-level roles (in `users.role`): admin, operator, auditor, viewer, president
  2. Meeting-level roles (in `meeting_roles`): president, assessor, voter
  - Effective permissions: Union of system role + meeting roles
  - Implementation: `app/Core/Security/Permissions.php`

## Monitoring & Observability

**Error Tracking:**
- File-based logging only
  - Sink: PHP error_log (typically `/var/log/php-fpm.log` or syslog)
  - No external error tracking service (Sentry, Rollbar, etc.)

**Logs:**
- Structured logging via `AgVote\Core\Logger` static class
  - Methods: `info()`, `warning()`, `error()`, `debug()`
  - Output: PHP error_log with JSON context
  - Request tracing: `X-Request-ID` header generated per request
  - Audit trail: `AgVote\audit_log()` function logs user actions to `audit_events` table

**Monitoring & Alerts:**
- System monitoring service: `app/Services/MonitoringService.php`
  - Runs periodically via CLI command: `bin/console monitoring:check`
  - Metrics collected:
    - Database latency (ms)
    - Database active connections
    - Disk free space (bytes/percent)
    - Meeting/motion/vote token counts
    - Authentication failures (15-min window)
    - Email queue backlog
    - PHP memory usage
  - Alert thresholds (configurable via env):
    - `MONITOR_AUTH_FAILURES_THRESHOLD` (default: 5)
    - `MONITOR_DB_LATENCY_MS` (default: 2000)
    - `MONITOR_DISK_FREE_PCT` (default: 10)
    - `MONITOR_EMAIL_BACKLOG` (default: 100)
  - Notification channels:
    - Email: `MONITOR_ALERT_EMAILS` (comma-separated or "auto" for all admins)
    - Webhook: `MONITOR_WEBHOOK_URL` (HTTP POST with alert JSON)

## CI/CD & Deployment

**Hosting:**
- Docker container (primary)
  - Image: PHP 8.4-fpm-alpine + Nginx + Supervisor
  - Port: 8080 (HTTP only)
  - Health check: `GET /api/v1/health.php` (curl-based)
  - Entrypoint: `deploy/entrypoint.sh` → supervisord
  - Non-root user: www-data

**CI Pipeline:**
- GitHub Actions (implied by `.github/` directory)
- No explicit CI config provided (external)

**Build process:**
- Docker multi-stage:
  1. Asset minification (Node.js 20 Alpine) - terser + clean-css-cli
  2. Runtime image (PHP 8.4-fpm Alpine) - Composer install, app code, minified assets

## Environment Configuration

**Required env vars:**
- `APP_ENV` - development | demo | production
- `APP_SECRET` - 32-byte hex secret
- `APP_URL` - Base URL of application
- `DB_DSN` - PostgreSQL connection string
- `DB_USER`, `DB_PASS` - Database credentials
- `DEFAULT_TENANT_ID` - UUID for default organization (multi-tenant support)

**Security env vars:**
- `APP_AUTH_ENABLED` - 0|1 (default: 1)
- `CSRF_ENABLED` - 0|1 (default: 1)
- `RATE_LIMIT_ENABLED` - 0|1 (default: 1)
- `APP_LOGIN_MAX_ATTEMPTS` - Max login attempts (default: 5)
- `APP_LOGIN_WINDOW` - Lockout window in seconds (default: 300)

**SMTP/Email env vars:**
- `MAIL_HOST`, `MAIL_PORT` - SMTP server
- `MAIL_USER`, `MAIL_PASS` - SMTP credentials
- `MAIL_FROM`, `MAIL_FROM_NAME` - Sender address/name
- `MAIL_TLS` - starttls | tls (STARTTLS on 587 or TLS-first on 465)
- `MAIL_TIMEOUT` - Request timeout in seconds (default: 10)

**Redis env vars:**
- `REDIS_HOST`, `REDIS_PORT` - Redis server
- `REDIS_PASSWORD` - Redis auth password
- `REDIS_DATABASE` - Redis database number (default: 0)
- `REDIS_PREFIX` - Key prefix for agvote namespace (default: agvote:)

**Optional feature flags:**
- `LOAD_SEED_DATA` - 0|1 (load test data, must be 0 in production)
- `EMAIL_TRACKING_ENABLED` - 0|1 (email open tracking)
- `PUSH_ENABLED` - 0|1 (SSE real-time updates, requires Redis or file fallback)
- `PROXY_MAX_PER_RECEIVER` - Max proxies per voter (default: 3)

**Secrets location:**
- `.env` file (development only, contains secrets)
- `.env.production.example` - Template for production (no actual secrets)
- Docker: Pass via environment variables or .env file mounted at runtime
- Never committed: Secrets are in `.gitignore`

## Webhooks & Callbacks

**Incoming:**
- Email tracking pixel callback - `/api/v1/email-tracking` (implicit via HTTP GET pixel request)
  - No explicit webhook endpoint, just HTTP request logging

**Outgoing:**
- Monitoring alerts webhook - Optional `MONITOR_WEBHOOK_URL`
  - HTTP POST with JSON alert payload
  - Payload structure (from `MonitoringService::sendWebhook()`):
    ```json
    {
      "alert_type": "db_latency|auth_failure|disk_space|email_backlog",
      "severity": "high|medium",
      "message": "Alert description",
      "metrics": { "key": "value" }
    }
    ```
  - No retry mechanism - best-effort delivery
  - Uses cURL with 5-second timeout

**Server-Sent Events (SSE) Push:**
- Real-time event broadcasting via HTTP streaming
  - Protocol: HTTP/1.1 Server-Sent Events (RFC 6797)
  - Endpoint: `/api/v1/sse` (implicit, polling/SSE endpoint)
  - Backend: `app/SSE/EventBroadcaster.php` queues events to Redis or file
  - Events:
    - `motion.opened`, `motion.closed`, `motion.updated`
    - `vote.cast`, `vote.updated`
    - `attendance.updated`, `quorum.updated`
    - `meeting.status_changed`
    - `session.invalidated`
  - Storage: Redis list (LPUSH/LRANGE) or `/tmp/agvote-sse-queue.json` fallback

---

*Integration audit: 2026-04-07*
