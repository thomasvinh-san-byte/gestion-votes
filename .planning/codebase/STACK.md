# Technology Stack

**Analysis Date:** 2026-03-16

## Languages

**Primary:**
- PHP 8.4 — All backend logic, API endpoints, CLI commands (`declare(strict_types=1)` throughout; match expressions, named arguments, enums)
- SQL (PostgreSQL dialect) — Schema, migrations, seeds

**Secondary:**
- JavaScript ES2022+ — Frontend UI; no transpilation, no bundler, vanilla Web Components + IIFE modules
- CSS (plain, no preprocessor) — Per-page stylesheets + custom design system

## Runtime

**Environment:**
- PHP 8.4-FPM — Alpine 3.21 Docker image (`php:8.4-fpm-alpine3.21`)
- Node.js 20.19 — Alpine 3.21, build stage only (asset minification); discarded after Docker build

**Package Manager:**
- Composer 2 — PHP dependencies
  - `composer.json` and `composer.lock` both present
- npm — JS dev dependencies and vendor-copy script
  - `package.json` and `package-lock.json` both present

## Frameworks

**Backend core (custom):**
- No full-stack PHP framework — hand-rolled micro-framework in `app/Core/`
  - `app/Core/Application.php` — Static bootstrap orchestrator (boot sequence, env, DB, Redis, security)
  - `app/Core/Router.php` — URL routing
  - `app/Core/MiddlewarePipeline.php` — Middleware chain (auth, CSRF, rate limiting)
  - `app/Core/Http/Request.php`, `app/Core/Http/JsonResponse.php` — HTTP abstractions
  - `app/Core/Providers/` — Service providers: `DatabaseProvider`, `EnvProvider`, `RedisProvider`, `SecurityProvider`, `RepositoryFactory`

**Symfony components (not Symfony full-stack):**
- `symfony/console` v8.0.4 — CLI command runner (`bin/console`, `app/Command/`)
- `symfony/event-dispatcher` v8.0.4 — Internal domain event dispatch
- `symfony/mailer` v8.0.4 — SMTP email transport (STARTTLS, implicit TLS, plain)
- `symfony/mime` v8.0.5 — Email composition (Address, Email objects)
- `psr/event-dispatcher` v1.0.0 — PSR-14 interface

**Frontend:**
- htmx.org v1.9.12 — HTML-over-the-wire AJAX attributes (used on trust page and select views; vendored to `public/assets/vendor/htmx.min.js`)
- chart.js v4.4.1 — Analytics and dashboard charts (vendored to `public/assets/vendor/chart.umd.js`)
- marked.min.js v12.0.0 — Client-side Markdown rendering on the docs page (vendored to `public/assets/js/vendor/marked.min.js`)
- Lucide icons — SVG icon library (`lucide.createIcons()`), used in operator motion views; loaded via `operator-motions.js`
- **No JS framework** — 20+ custom Web Components with `ag-*` prefix (`customElements.define`), IIFE module pattern for page scripts

**Testing:**
- PHPUnit v10.5.63 — PHP unit and integration tests (config: `phpunit.xml`)
- Playwright v1.50.0 — E2E browser automation (Chromium, Firefox, WebKit + mobile; config: `tests/e2e/playwright.config.js`)
- phpstan/phpstan v2.1.39 — Static analysis at level 5 (config: `phpstan.neon`)
- friendsofphp/php-cs-fixer v3.94.0 — PHP code style enforcement
- ESLint v9 (flat config, `eslint.config.mjs`) — JS linting with custom `agvote/no-inner-html` rule

**Build/Dev:**
- terser v5 — JS minification (Docker multi-stage build stage only)
- clean-css-cli v5 — CSS minification (Docker multi-stage build stage only)
- Supervisord — In-container process manager (`deploy/supervisord.conf`): nginx, php-fpm, email-queue daemon, monitoring daemon, ratelimit-cleanup daemon

## Key Dependencies

**Critical:**
- `dompdf/dompdf` v3.1.4 — PDF generation for meeting reports and PVs (`app/Controller/MeetingReportsController.php`)
- `phpoffice/phpspreadsheet` v1.30.2 — XLSX import and export for members, motions, attendances, proxies (`app/Services/ExportService.php`, `app/Services/ImportService.php`, `app/Controller/ExportController.php`)
- `erusev/parsedown` v1.8.0 — Server-side Markdown to HTML for documentation viewer (`app/Controller/DocContentController.php`)
- phpredis (PECL extension) — PHP Redis client, installed via `pecl install redis` in Dockerfile; `app/Core/Providers/RedisProvider.php` wraps the connection

**Infrastructure:**
- `thecodingmachine/safe` v3.3.0 — Type-safe PHP stdlib wrappers
- `ezyang/htmlpurifier` v4.19.0 — HTML sanitization (phpspreadsheet dependency)
- `maennchen/zipstream-php` v3.2.1 — Streaming ZIP download (phpspreadsheet dependency)

## PHP Extensions Required

Built into Docker image:

| Extension | Purpose |
|-----------|---------|
| `pdo_pgsql` | PostgreSQL via PDO |
| `pgsql` | Native PostgreSQL functions |
| `redis` (PECL) | phpredis client for Redis |
| `gd` | Image processing (Freetype + JPEG) |
| `zip` | ZIP file handling |
| `intl` | Internationalization |
| `mbstring` | Multibyte string support |
| `opcache` | Opcode cache (128MB, validate_timestamps off in prod) |

## Configuration

**Environment:**
- `.env` file loaded at boot by `app/Core/Providers/EnvProvider` (called from `Application::boot()`)
- `.env.example` at project root documents all variables with comments
- Required in all environments: `DB_PASS`, `APP_SECRET`, `DEFAULT_TENANT_ID`
- Required in production: `APP_AUTH_ENABLED=1`, `CSRF_ENABLED=1`, `RATE_LIMIT_ENABLED=1`, `LOAD_SEED_DATA=0`
- Optional: `REDIS_PASSWORD`, `MAIL_HOST`/`MAIL_PORT`/`MAIL_USER`/`MAIL_PASS`, `PUSH_ENABLED`, `MONITOR_WEBHOOK_URL`, `MONITOR_ALERT_EMAILS`
- Config values accessed via `app/config.php` which returns an array from `getenv()` calls

**Build:**
- `Dockerfile` — Multi-stage: Node.js 20 (minify assets) → PHP 8.4-FPM runtime
- `docker-compose.yml` — Local dev stack: app + db + redis
- `docker-compose.prod.yml` — Production Docker Compose variant
- `deploy/nginx.conf` — Nginx server block (rate limit zones, CSP headers, FastCGI, static caching)
- `deploy/php.ini` — Production PHP overrides (opcache, session security, memory/upload limits)
- `deploy/php-fpm.conf` — PHP-FPM pool settings
- `deploy/supervisord.conf` — Process supervisor config
- `deploy/entrypoint.sh` — Container init script (DB readiness wait, schema apply, migrations, runtime php.ini)

## Platform Requirements

**Development:**
- Docker + Docker Compose (primary local setup via `docker-compose.yml`)
- Alternatively: PHP 8.4 + Composer 2 + PostgreSQL 16+ + Redis 7+
- Run tests: `vendor/bin/phpunit`, `npx playwright test`

**Production:**
- Single Docker container (Nginx + PHP-FPM + Supervisord)
- Exposed on port 8080 by default; overridable via `PORT` env var (Render.com injects `PORT=10000`)
- Supported cloud platforms: Render.com (primary, referenced in config comments), any Docker host behind Cloudflare or HAProxy
- Container resource limits: 512MB RAM, 1 CPU (app); 256MB (db); 128MB (redis)
- Timezone: `Europe/Paris` (set in `deploy/php.ini`)

---

*Stack analysis: 2026-03-16*
