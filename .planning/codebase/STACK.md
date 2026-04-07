# Technology Stack

**Analysis Date:** 2026-04-07

## Languages

**Primary:**
- PHP 8.4+ — Application runtime, controllers, services, repositories, CLI commands
- SQL — PostgreSQL schema and migrations in `database/migrations/`

**Secondary:**
- HTML/CSS — Frontend templates in `public/*.htmx.html` and `app/Templates/`
- JavaScript — HTMX-based frontend interactivity in `public/assets/`
- Bash — DevOps scripts in `bin/` and `database/setup.sh`

## Runtime

**Environment:**
- PHP 8.4-FPM (Alpine Linux 3.21 in Docker, `php:8.4-fpm-alpine3.21`)
- Node 20.19 (Alpine) — Build-time only for asset minification (`node:20.19-alpine3.21`), discarded after build

**Package Manager:**
- Composer 2 (installed from `composer:2` image, removed from runtime layer)
- Lockfile: `composer.lock` (present and required — Dockerfile enforces `--no-dev`)

## Frameworks

**Core:**
- Custom `AgVote\Core\Router` — Lightweight URL routing without external framework
- Symfony Console ^8.0 (v8.0.4) — CLI command infrastructure (`app/Command/`)
- Symfony EventDispatcher ^8.0 (v8.0.4) — Domain event dispatch and SSE fan-out
- HTMX — Client-side dynamic updates via HTTP requests (no WebSocket, no SPA)

**View Layer:**
- Custom `AgVote\View\HtmlView` — Server-side PHP template rendering
- No Twig, no Blade

**Testing:**
- PHPUnit ^10.5 (v10.5.63) — Unit tests in `tests/Unit/`

**Code Quality:**
- PHP-CS-Fixer ^3.0 (v3.94.0) — PSR-12 style enforcement, configured in `.php-cs-fixer.dist.php`
- PHPStan ^2.1 (v2.1.39) — Static analysis at Level 5, configured in `phpstan.neon`

**Build/Dev:**
- Terser 5 — JavaScript minification (build stage only)
- clean-css-cli 5 — CSS minification (build stage only)
- Supervisor — Process management for PHP-FPM + Nginx in container, config at `deploy/supervisord.conf`
- Nginx — HTTP server in container, config at `deploy/nginx.conf`

## Key Dependencies

**Critical (runtime):**
- `dompdf/dompdf` v3.1.4 — PDF generation for proxy (procuration) documents; `app/Services/ProcurationPdfService.php`; renders HTML to A4 PDF with DejaVu Sans font; remote resources disabled (`isRemoteEnabled = false`)
- `openspout/openspout` v5.6.0 — Streaming XLSX export via `OpenSpout\Writer\XLSX\Writer`; constant memory regardless of row count; primary export path in `app/Services/ExportService.php`
- `phpoffice/phpspreadsheet` v1.30.2 — Non-streaming styled XLSX workbooks for complete meeting exports; secondary export path in `app/Services/ExportService.php`
- `symfony/mailer` v8.0.4 — SMTP delivery with STARTTLS/TLS; wrapped in `app/Services/MailerService.php`; supports `smtp://` (STARTTLS port 587) and `smtps://` (TLS port 465) DSN schemes; lazy-initialized and cached per service instance
- `erusev/parsedown` v1.8.0 — Markdown-to-HTML for in-app documentation rendering; `app/Controller/DocController.php`; safe mode enabled

**Infrastructure:**
- `symfony/console` v8.0.4 — CLI commands: email queue processing, monitoring, Redis health checks, data retention; entry point `bin/console`
- `symfony/event-dispatcher` v8.0.4 — Domain event bus; listeners registered in `app/Core/Application.php::initEventDispatcher()`
- phpredis (PECL, via `pecl install redis`) — In-process Redis client; provides `Redis` class used throughout the application
- PDO with `pdo_pgsql` driver — All database access through `AgVote\Repository\AbstractRepository`

## PHP Extensions

All verified at container startup (fail-fast guard in `Dockerfile`):

| Extension | Purpose |
|-----------|---------|
| `pdo_pgsql` | PostgreSQL connectivity via PDO |
| `pgsql` | PostgreSQL client functions |
| `gd` | Image processing (1x1 email tracking pixel generation) |
| `zip` | Archive support (XLSX export internals) |
| `intl` | Internationalization, `transliterator_transliterate()` for filenames |
| `mbstring` | Multibyte string handling |
| `redis` | phpredis client (mandatory — app refuses to start without connection) |
| `Zend OPcache` | Performance optimization, 128MB cache, 4000 accelerated files |

## Configuration

**Environment:**
- `.env` file loaded by `AgVote\Core\Providers\EnvProvider::load()` from project root
- `.env.example` — documented template for development/demo
- `.env.production.example` — production configuration template
- Key variables:
  - App: `APP_ENV` (development|demo|production), `APP_SECRET` (32-byte hex), `APP_URL`, `APP_DEBUG`
  - Database: `DB_DSN`, `DB_USER`, `DB_PASS`
  - Redis: `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_DATABASE`, `REDIS_PREFIX`
  - Mail: `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`, `MAIL_TLS`, `MAIL_FROM`, `MAIL_FROM_NAME`, `MAIL_TIMEOUT`
  - Security: `APP_AUTH_ENABLED`, `CSRF_ENABLED`, `RATE_LIMIT_ENABLED`, `APP_LOGIN_MAX_ATTEMPTS`, `APP_LOGIN_WINDOW`, `CORS_ALLOWED_ORIGINS`
  - Features: `PUSH_ENABLED`, `EMAIL_TRACKING_ENABLED`, `PROXY_MAX_PER_RECEIVER`, `LOAD_SEED_DATA`
  - API keys: `API_KEY_ADMIN`, `API_KEY_OPERATOR`, `API_KEY_TRUST`
  - Storage: `AGVOTE_UPLOAD_DIR`
  - Monitoring: `MONITOR_ALERT_EMAILS`, `MONITOR_WEBHOOK_URL`, `MONITOR_AUTH_FAILURES_THRESHOLD`, `MONITOR_DB_LATENCY_MS`, `MONITOR_DISK_FREE_PCT`, `MONITOR_EMAIL_BACKLOG`
  - Multi-tenant: `DEFAULT_TENANT_ID`

**PHP Runtime (`deploy/php.ini`):**
- `memory_limit = 256M`
- `upload_max_filesize = 10M`, `post_max_size = 12M`
- `max_execution_time = 60`
- `session.save_path = "/tmp"` (file-based sessions — NOT Redis-backed)
- `session.cookie_httponly = 1`, `session.cookie_samesite = Lax`, `session.use_strict_mode = 1`
- `opcache.enable = 1`, `opcache.memory_consumption = 128`, `opcache.max_accelerated_files = 4000`
- `date.timezone = Europe/Paris`
- `expose_php = Off`, `allow_url_include = Off`

**Build:**
- `Dockerfile` — Two-stage: Node 20.19 assets minification stage, then PHP 8.4-FPM runtime stage
- Container exposes port `8080` (HTTP only)
- Health check: `curl -sf http://127.0.0.1:${PORT:-8080}/api/v1/health.php`
- Runs as `www-data` (non-root)
- Upload volume: `/var/agvote/uploads`
- Temp directory: `/tmp/ag-vote`

## Platform Requirements

**Development:**
- PHP 8.4+ CLI with all extensions listed above
- Composer 2
- PostgreSQL 12+
- Redis (mandatory — application boot throws `RuntimeException` if Redis is unreachable)
- Docker + Docker Compose (optional but recommended)

**Production:**
- Docker container or PHP 8.4-FPM + Nginx
- PostgreSQL 12+ with `sslmode=require`
- Redis with password authentication
- Minimum 512MB RAM, 1GB free disk
- Persistent Docker volume mounted at `AGVOTE_UPLOAD_DIR`

---

*Stack analysis: 2026-04-07*
