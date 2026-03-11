# AG-VOTE Integrations

## External Services

### Email (SMTP)

- **Library**: `symfony/mailer` ^8.0
- **Config**: `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM` env vars
- **Features**: Queue-based sending via Redis, email tracking with open/click events
- **Templates**: Customizable HTML templates stored in DB (`EmailTemplateService`)

### PostgreSQL Database

- **Version**: 16 (Docker: `postgres:16.8-alpine3.21`)
- **Connection**: PDO via `DB_DSN`, `DB_USER`, `DB_PASS` env vars
- **Provider**: `DatabaseProvider` manages singleton PDO instance

### Redis

- **Version**: 7.4 (Docker: `redis:7.4-alpine3.21`)
- **Connection**: `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` env vars
- **Uses**: Email queue, rate limiting, SSE pub/sub, caching
- **Fallback**: File-based SSE when Redis unavailable

## CDN Dependencies

| Library | CDN | Version |
|---------|-----|---------|
| Chart.js | jsDelivr | 4.4.1 |
| HTMX | unpkg | 1.9.12 |
| Google Fonts | Google | Bricolage Grotesque, Fraunces, JetBrains Mono |

## Vendored Libraries

| Library | Location | Purpose |
|---------|----------|---------|
| marked.js | `public/assets/js/vendor/marked.min.js` | Markdown rendering |

## PHP Dependencies (composer.json)

| Package | Purpose |
|---------|---------|
| `symfony/mailer` | SMTP email sending |
| `symfony/console` | CLI commands (`bin/console`) |
| `dompdf/dompdf` | HTML → PDF (meeting reports, PV) |
| `phpoffice/phpspreadsheet` | Excel/XLSX import and export |
| `erusev/parsedown` | Markdown → HTML (docs) |

## Deployment

### Render

- **Config**: `render.yaml`, `render-production.yaml`
- **Type**: Docker-based deployment

### Docker

- **Multi-stage build**: Node 20 (build assets) → PHP 8.4-FPM (runtime)
- **Compose**: Dev environment with app + postgres + redis containers
- **Security**: Non-root user, read-only filesystem, tmpfs mounts

## Internal Integrations

### SSE (Server-Sent Events)

- Endpoint: `public/api/sse.php`
- Client: `event-stream.js` with automatic reconnection
- Fallback: Polling when SSE unavailable

### Service Worker (PWA)

- `public/sw.js` — cache strategies for offline capability
- `public/manifest.json` — PWA installation metadata
