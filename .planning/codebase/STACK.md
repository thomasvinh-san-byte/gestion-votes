# AG-VOTE Technology Stack

## Languages

- **PHP 8.4** (`declare(strict_types=1)` throughout, named arguments, match expressions, enums)
- **JavaScript ES2022+** (ESLint `ecmaVersion: 'latest'`, Web Components via `customElements`, IIFE module pattern)
- **SQL** (PostgreSQL dialect)

## Backend

- **No framework** -- custom PHP application built from scratch
- Custom router (`AgVote\Core\Router`) with middleware pipeline (`MiddlewarePipeline`)
- Service provider pattern (`app/Core/Providers/`): `DatabaseProvider`, `EnvProvider`, `RedisProvider`, `SecurityProvider`, `RepositoryFactory`
- Repository pattern for all DB access (`app/Repository/`)
- Controller layer (`app/Controller/`) with `AbstractController` base class
- PSR-4 autoloading via Composer (namespace `AgVote\`)
- CLI commands via `symfony/console` (`bin/console`, `app/Command/`)

## Database

- **PostgreSQL 16** (via PDO `pdo_pgsql`)
- Schema in `database/schema-master.sql`, migrations in `database/migrations/`, seeds in `database/seeds/`
- Multi-tenant by `tenant_id` UUID column
- Docker Compose uses `postgres:16.8-alpine3.21`

## Cache / Queue / Rate Limiting

- **Redis 7.4** (`redis:7.4-alpine3.21`, phpredis extension)
- Used for: email queue, rate limiting, SSE pub/sub, cache
- File-based fallback for SSE when Redis unavailable

## Frontend

- **No JS framework** -- vanilla JS with Web Components
- **20 custom elements** (`<ag-kpi>`, `<ag-modal>`, `<ag-toast>`, `<ag-pagination>`, etc.) in `public/assets/js/components/`
- IIFE module pattern (no bundler, no ES module imports at runtime except component index)
- Global namespace objects: `Shared`, `PageComponents`, `Utils`, `Auth`, `ShellDrawer`, `MobileNav`, `MeetingContext`
- SSE (Server-Sent Events) for real-time updates (`event-stream.js`), polling fallback
- Service Worker (`sw.js`) for offline-capable PWA with cache strategies
- PWA manifest (`manifest.json`)

## CSS Architecture

- **Custom design system** (`design-system.css`) -- no CSS framework
- CSS custom properties (design tokens) for theming
- Dark/light theme via `data-theme` attribute on `<html>`
- 20+ page-specific stylesheets (`vote.css`, `operator.css`, `meetings.css`, etc.)
- No preprocessor (Sass/Less) -- plain CSS

## Typography (CDN)

- **Google Fonts**: Bricolage Grotesque, Fraunces, JetBrains Mono

## Build Tools

- **No JS bundler** (no webpack, vite, rollup, esbuild)
- Docker multi-stage build minifies assets at build time:
  - `terser@5` for JS minification
  - `clean-css-cli@5` for CSS minification
  - Node 20.19-alpine used only in build stage, discarded
- **Composer 2** for PHP dependencies
- **ESLint 9** (flat config) for JS linting with custom `agvote/no-inner-html` rule
- **php-cs-fixer 3** for PHP formatting
- **PHPStan 2.1** for static analysis
- **PHPUnit 10.5** for tests
- **Makefile** for dev workflow commands

## Key PHP Dependencies (composer.json)

| Package | Version | Purpose |
|---------|---------|---------|
| `symfony/mailer` | ^8.0 | Email sending (SMTP) |
| `symfony/console` | ^8.0 | CLI commands |
| `dompdf/dompdf` | ^3.1 | PDF generation (meeting reports, PV) |
| `phpoffice/phpspreadsheet` | ^1.29 | Excel/XLSX import and export |
| `erusev/parsedown` | ^1.8 | Markdown parsing (documentation viewer) |

## JS Vendor Libraries

| Library | Version | Source | Purpose |
|---------|---------|--------|---------|
| `marked.min.js` | bundled | `public/assets/js/vendor/` | Markdown rendering |
| `chart.js` | 4.4.1 | CDN (jsdelivr) | Analytics charts |
| `htmx.org` | 1.9.12 | CDN (unpkg) | HTML-over-the-wire (trust page) |

## Infrastructure

- **Docker**: multi-stage Dockerfile (node:20-alpine build + php:8.4-fpm-alpine runtime)
- **Nginx** as reverse proxy (rate limiting, static file serving)
- **PHP-FPM** as application server
- **Supervisord** manages nginx + php-fpm in single container
- **Render** deployment support (`render.yaml`, `render-production.yaml`)
- Runs as non-root (`www-data`), read-only container filesystem, tmpfs mounts
