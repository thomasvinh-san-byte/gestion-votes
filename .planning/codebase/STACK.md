# Technology Stack

**Analysis Date:** 2026-04-07

## Languages

**Primary:**
- PHP 8.4+ - Application runtime, API handlers, controllers, services
- HTML/CSS - Frontend templates in `public/*.html` (HTMX-based server-driven UI)
- JavaScript - Frontend interactivity and HTMX enhancements in `public/assets/`

**Secondary:**
- SQL - PostgreSQL schema and migrations in `database/migrations/`
- Bash - DevOps scripts in `bin/` and `database/setup.sh`

## Runtime

**Environment:**
- PHP 8.4-fpm (Alpine Linux 3.21 in Docker)
- Node 20.19 (Alpine) - Build-time only, for asset minification (discarded after build)

**Package Manager:**
- Composer - PHP dependency management
- Lockfile: `composer.lock` (present)

## Frameworks

**Core:**
- Custom router: `AgVote\Core\Router` - Lightweight URL routing without external framework dependency
- Symfony components:
  - `symfony/mailer` ^8.0 - SMTP email sending with STARTTLS/TLS support
  - `symfony/console` ^8.0 - CLI command infrastructure for background jobs

**Templating:**
- Custom HTML Views - `AgVote\View\HtmlView` - Server-side HTML rendering
- HTMX - Client-side dynamic updates via HTTP requests (no WebSocket)

**Testing:**
- PHPUnit ^10.5 - Unit and integration tests in `tests/Unit/`

**Build/Dev:**
- PHP-CS-Fixer ^3.0 - Code style fixing (configured in `.php-cs-fixer.dist.php`)
- PHPStan ^2.1 - Static analysis for type checking

## Key Dependencies

**Critical:**
- `dompdf/dompdf` ^3.1 - PDF generation for procuration documents (service: `ProcurationPdfService`)
- `phpoffice/phpspreadsheet` ^1.29 - Excel/XLSX export for voting results (service: `ExportService`)
- `erusev/parsedown` ^1.8 - Markdown parsing for email templates and documentation
- `symfony/mailer` ^8.0 - SMTP email delivery (service: `MailerService`) with STARTTLS/TLS

**Infrastructure:**
- Redis extension (phpredis) - In-process Redis client for cache, queues, rate limiting, and SSE event broadcast
- PDO with PostgreSQL driver - Database connectivity
- cURL - HTTP requests for webhook notifications and event tracking pixels (service: `MonitoringService`, `MailerService`)

## Configuration

**Environment:**
- `.env` file (development/demo mode)
- `.env.example` - Template with documentation
- `.env.production.example` - Production configuration template
- Configuration loaded via `AgVote\Core\Providers\EnvProvider::load()`

**Key configs required:**
- `APP_ENV` - development|demo|production
- `APP_SECRET` - Application secret (32-byte hex)
- `DB_DSN`, `DB_USER`, `DB_PASS` - PostgreSQL connection
- `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS` - SMTP server
- `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` - Redis connection (optional, with filesystem fallback)
- `API_KEY_ADMIN`, `API_KEY_OPERATOR`, `API_KEY_TRUST` - API key authentication (optional)
- `CORS_ALLOWED_ORIGINS` - Comma-separated origins for CORS
- `AGVOTE_UPLOAD_DIR` - File upload directory (persistent volume)

**Build:**
- `Dockerfile` - Multi-stage build (assets minification → runtime)
  - Stage 1: Node.js - Minify CSS/JS with terser and clean-css-cli
  - Stage 2: PHP 8.4-fpm-alpine - Runtime with nginx, supervisor, and extensions
- `docker-compose.yml` (not in codebase, external)
- `.htaccess` - Apache URL rewriting (legacy fallback, router is primary)

## Platform Requirements

**Development:**
- PHP 8.4+ CLI
- Composer
- PostgreSQL 12+ (local or remote)
- Redis (optional, filesystem fallback for SSE)
- Docker/Docker Compose (optional)

**Production:**
- Docker container or PHP 8.4-FPM + Nginx
- PostgreSQL 12+ with SSL support (`sslmode=require`)
- Redis (recommended for performance, optional with filesystem fallback)
- Minimum 512MB RAM, 1GB free disk for uploads

**Extensions loaded at runtime:**
- pdo_pgsql - Database connectivity
- pgsql - PostgreSQL client functions
- gd - Image processing (for email tracking pixels)
- zip - Archive support (xlsx export)
- intl - Internationalization
- mbstring - Multibyte string handling
- redis - Redis client (optional, gracefully disabled if not available)
- Zend OPcache - Performance optimization

---

*Stack analysis: 2026-04-07*
