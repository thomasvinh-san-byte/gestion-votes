# AG-VOTE Architecture

## Overview

AG-VOTE is a multi-tenant web application for managing general assembly votes. It follows a **PHP backend API + vanilla JS frontend** architecture with no framework on either side.

## Architecture Pattern

**Layered MVC** with service layer:

```
Browser (HTML/JS/CSS)
  ↓ fetch() / SSE
Nginx (reverse proxy, rate limiting, static files)
  ↓ FastCGI
PHP-FPM (Application)
  ├── Router → Middleware Pipeline → Controller
  │     ├── Services (business logic)
  │     ├── Repositories (data access)
  │     └── Providers (infrastructure)
  ↓ PDO / phpredis
PostgreSQL / Redis
```

## Request Flow

1. **Static pages**: Nginx serves `public/*.html` and `public/*.htmx.html` directly
2. **API calls**: `public/index.php` (front controller) → `Router::dispatch()` → `MiddlewarePipeline` → `Controller::method()`
3. **SSE streams**: `public/api/sse.php` → persistent connection with Redis pub/sub or file-based fallback

### Middleware Pipeline

Executed in order for each API route:
1. `AuthMiddleware` — JWT cookie validation, session refresh
2. `CsrfMiddleware` — CSRF token validation (state-changing requests)
3. `RateLimitGuard` — Redis-based rate limiting per context
4. `RoleMiddleware` — Role-based access control (admin, operator, member, viewer)
5. `IdempotencyGuard` — Prevents duplicate submissions

## Authentication Flow

- JWT-based session stored in HttpOnly cookie
- Login: `POST /api/auth/login` → validates credentials → sets JWT cookie
- Session refresh via `POST /api/auth/refresh`
- `APP_ENV=demo` mode: bypasses authentication for development
- Roles: `admin`, `operator`, `member`, `viewer`
- Permissions defined in `Permissions.php` enum

## Frontend Architecture

**Page-per-file SPA-like pattern**:
- Each `.htmx.html` page is a standalone HTML document
- Shared shell (sidebar, header) included via inline HTML in each page
- Per-page JS module (`pages/<name>.js`) initializes on `DOMContentLoaded`
- Global utilities in `shared.js`, `utils.js`, `auth-ui.js`
- 20 Web Components (`<ag-kpi>`, `<ag-modal>`, etc.) for reusable UI
- SSE (`event-stream.js`) for real-time meeting updates

**State management**: No global store. Per-page closures with `fetch()` calls. Meeting state managed via `MeetingContext` global object during operator sessions.

## Data Flow Patterns

- **CRUD**: `fetch('/api/...')` → JSON response → DOM update via vanilla JS
- **Real-time**: SSE stream → event listeners → DOM update
- **File operations**: FormData uploads → PHP processing → filesystem or DB storage
- **Reports**: PHP generates HTML → DomPDF renders PDF → download or iframe display
- **Exports**: PHP generates XLSX/CSV via PhpSpreadsheet → download

## Multi-tenancy

- `tenant_id` UUID column on all tables
- Set at authentication time, carried through session
- All repository queries scoped by tenant_id

## Key Architectural Decisions

1. **No JS framework**: Vanilla JS + Web Components for zero build step and minimal dependencies
2. **No PHP framework**: Custom router/middleware for lightweight, purpose-built stack
3. **SSE over WebSocket**: Simpler to deploy, works through proxies
4. **File-based SSE fallback**: Works when Redis is unavailable
5. **Docker single container**: Supervisord manages nginx + php-fpm together
6. **CDN for Chart.js + HTMX**: Only these two large libs loaded externally
