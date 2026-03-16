# Codebase Structure

**Analysis Date:** 2026-03-16

## Directory Layout

```
gestion-votes/
├── app/                    # PHP backend application
│   ├── Command/            # CLI commands (4 commands)
│   ├── Controller/         # HTTP request handlers (37 controllers)
│   ├── Core/               # Framework infrastructure
│   │   ├── Http/           # Request, JsonResponse, ApiResponseException
│   │   ├── Middleware/      # MiddlewareInterface, RoleMiddleware, RateLimitGuard
│   │   ├── Providers/       # DatabaseProvider, RedisProvider, SecurityProvider, RepositoryFactory
│   │   ├── Security/        # AuthMiddleware, CsrfMiddleware, Permissions, RateLimiter, SessionHelper
│   │   └── Validation/      # InputValidator, Schemas/
│   ├── Event/              # Domain event constants and listeners
│   │   └── Listener/
│   ├── Repository/         # PDO data access objects (27 repositories)
│   │   └── Traits/         # MotionRepository composition traits (4 traits)
│   ├── Services/           # Business logic (17 services)
│   ├── Templates/          # Server-rendered PHP HTML templates (vote, doc, email)
│   ├── View/               # HtmlView renderer
│   ├── WebSocket/          # EventBroadcaster (SSE/Redis event queue)
│   ├── Application.php     # Application bootstrap orchestrator
│   ├── BallotSource.php    # Ballot source constants
│   ├── Logger.php          # Structured logger with request correlation
│   ├── MiddlewarePipeline.php
│   ├── Router.php          # Route dispatch engine
│   ├── api.php             # API helpers: api_ok, api_fail, api_is_uuid, CSRF setup
│   ├── bootstrap.php       # Thin autoload + Application::boot() wrapper
│   ├── config.php          # App configuration (reads env vars)
│   └── routes.php          # Central route table
├── bin/
│   └── console             # CLI entry point
├── database/
│   ├── migrations/         # SQL migration files (numbered and date-prefixed)
│   └── seeds/              # SQL seed data (test, demo, e2e fixtures)
├── deploy/                 # Deployment scripts
├── docs/
│   ├── api/                # API documentation
│   └── dev/                # Developer documentation
├── public/                 # Web root (Apache/Nginx document root)
│   ├── api/v1/             # 150+ direct PHP API endpoint files
│   ├── assets/
│   │   ├── css/            # Per-page CSS + design-system.css (25 files)
│   │   ├── images/
│   │   ├── js/
│   │   │   ├── components/ # 20 Web Components (Custom Elements)
│   │   │   ├── core/       # Shared JS infrastructure (5 modules)
│   │   │   ├── pages/      # Per-page JS modules (32 files)
│   │   │   ├── services/   # Cross-page JS services (meeting-context.js)
│   │   │   └── vendor/     # Bundled JS libs (chart.umd.js, htmx.min.js, marked.min.js)
│   │   └── vendor/         # Bundled CSS vendor assets
│   ├── errors/             # Custom error pages
│   ├── partials/           # HTML partials loaded via fetch (sidebar.html, operator tabs)
│   ├── *.htmx.html         # Full-page SPA views (28 pages)
│   ├── index.html          # Landing/redirect
│   ├── index.php           # Front controller
│   ├── login.html          # Login page
│   ├── vote.php            # Server-rendered vote form entry point
│   ├── doc.php             # Server-rendered documentation viewer entry point
│   ├── sw.js               # Service Worker (PWA)
│   └── manifest.json       # PWA manifest
├── scripts/                # Utility scripts (check-prod-readiness.sh, smoke_test.sh, etc.)
├── tests/
│   ├── Unit/               # PHPUnit unit tests
│   ├── Integration/        # PHPUnit integration tests
│   ├── e2e/                # End-to-end tests
│   ├── fixtures/           # Test fixture data
│   └── bootstrap.php       # Test bootstrap
├── .planning/
│   ├── codebase/           # GSD codebase analysis docs (STACK.md, ARCHITECTURE.md, etc.)
│   └── milestones/         # Phase planning docs
├── composer.json           # PHP dependencies
├── package.json            # Node.js dev dependencies (linting only)
├── phpunit.xml             # PHPUnit configuration
├── phpstan.neon            # PHPStan static analysis config
└── Makefile                # Build and dev commands
```

## Directory Purposes

**`app/Controller/`:**
- Purpose: HTTP request handlers; one class per domain area
- Contains: 37 controllers extending `AbstractController`
- Key files: `AbstractController.php`, `MeetingsController.php`, `MotionsController.php`, `BallotsController.php`, `OperatorController.php`, `AuthController.php`, `AdminController.php`
- Rule: No business logic — delegate to Services and Repositories

**`app/Core/`:**
- Purpose: Framework infrastructure not tied to any domain
- Key files:
  - `Application.php` — bootstrap orchestrator
  - `Router.php` — route dispatch
  - `Logger.php` — structured logger
  - `MiddlewarePipeline.php` — middleware chain executor
  - `Core/Security/AuthMiddleware.php` — session + API key auth + RBAC
  - `Core/Security/Permissions.php` — permission map and role hierarchy constants
  - `Core/Providers/RepositoryFactory.php` — lazy repository container
  - `Core/Http/Request.php`, `JsonResponse.php`, `ApiResponseException.php`

**`app/Repository/`:**
- Purpose: All PostgreSQL data access; no business logic
- Contains: 27 repositories, each handling one domain table (plus `AbstractRepository`)
- Key files: `MeetingRepository.php`, `MotionRepository.php`, `BallotRepository.php`, `MemberRepository.php`, `AttendanceRepository.php`, `UserRepository.php`
- Traits: `app/Repository/Traits/` — large `MotionRepository` is split into 4 traits

**`app/Services/`:**
- Purpose: Domain business logic; can use multiple repositories and call other services
- Key files: `VoteEngine.php`, `MeetingWorkflowService.php`, `QuorumEngine.php`, `BallotsService.php`, `ExportService.php`, `MailerService.php`

**`app/WebSocket/`:**
- Purpose: Real-time event infrastructure
- Key file: `EventBroadcaster.php` — static methods for each domain event type; handles Redis ↔ file queue fallback

**`app/Templates/`:**
- Purpose: PHP templates for the two server-rendered HTML pages (vote form, doc viewer)
- Contains: `vote_form.php`, `vote_confirm.php`, `doc_page.php`, `email_invitation.php`, `email_report.php`, `_csrf_head.php`, `_csrf_scripts.php`

**`public/api/v1/`:**
- Purpose: Direct PHP API endpoint files — 150+ files, each a self-contained API handler
- Pattern: Each file starts with `require_once __DIR__ . '/../../../app/api.php';` then calls `AuthMiddleware::requireRole()`, performs business logic, calls `api_ok()`/`api_fail()`
- Note: These coexist with the Router; the Router dispatches to Controllers, these files are the legacy/fallback path

**`public/*.htmx.html`:**
- Purpose: Full SPA pages — static HTML shells loaded in the browser; all data fetched client-side
- Pattern: Load `app.css` + page CSS, load core JS + page JS, contain static HTML structure with empty containers that JS populates
- Key pages: `dashboard.htmx.html`, `operator.htmx.html`, `meetings.htmx.html`, `vote.htmx.html`, `hub.htmx.html`, `wizard.htmx.html`, `postsession.htmx.html`, `admin.htmx.html`

**`public/assets/js/core/`:**
- Purpose: Shared JS runtime loaded on every page
- Load order: `utils.js` → `shared.js` → `shell.js` (all pages); `event-stream.js` and `page-components.js` loaded as needed
- Exposes globals: `window.Utils`, `window.api()`, `window.ShellDrawer`, `window.MobileNav`, `window.ThemeToggle`, `window.Notifications`, `window.GlobalSearch`

**`public/assets/js/pages/`:**
- Purpose: Per-page JS modules — handle all data fetching, rendering, and interaction for one page
- Naming: `[page-name].js` — e.g., `operator-tabs.js`, `operator-exec.js`, `operator-motions.js` (operator split into 5 files)

**`public/assets/js/components/`:**
- Purpose: Web Components (Custom Elements) library for reusable UI
- Entry: `index.js` imports and registers all 20 components
- Naming: `ag-[component-name].js` — e.g., `ag-modal.js`, `ag-toast.js`, `ag-kpi.js`

**`public/assets/css/`:**
- Purpose: Per-page CSS and shared design system
- Key files:
  - `design-system.css` (4,236 LOC) — design tokens (CSS custom properties), typography, base components, utility classes
  - `app.css` (745 LOC) — global layout: `.app-shell`, `.app-sidebar`, `.app-header`, `.app-main`
  - `pages.css` (1,334 LOC) — shared page-level patterns
  - Per-page CSS: `operator.css` (4,092 LOC), `vote.css` (1,606 LOC), `members.css` (1,142 LOC), etc.

**`public/partials/`:**
- Purpose: HTML fragments fetched via JS `fetch()` and inserted into the DOM
- Contains: `sidebar.html` (shared navigation), `operator-exec.html`, `operator-live-tabs.html`

**`database/migrations/`:**
- Purpose: SQL migration files applied sequentially
- Naming: Early files numbered `001_*.sql`; newer files date-prefixed `20260204_*.sql`
- Committed: Yes

**`database/seeds/`:**
- Purpose: SQL seed data for development, testing, and demos
- Contains: `01_minimal.sql`, `02_test_users.sql`, `03_demo.sql`, `04_e2e.sql`, `05_test_simple.sql`, `06_test_weighted.sql`, `07_test_incidents.sql`, `08_demo_az.sql`

**`tests/`:**
- Purpose: PHPUnit tests
- Contains: `Unit/` (isolated unit tests), `Integration/` (DB integration tests), `e2e/` (end-to-end), `fixtures/` (test data)

## Key File Locations

**Entry Points:**
- `public/index.php` — HTTP front controller
- `bin/console` — CLI entry point
- `public/vote.php` — Public vote form (server-rendered)
- `public/doc.php` — Documentation viewer (server-rendered)
- `public/login.html` — Login page

**Configuration:**
- `app/config.php` — Application configuration (reads env vars); loaded by `Application::boot()`
- `app/routes.php` — All route registrations mapped to controllers
- `.env.example` — Environment variable reference with all required vars
- `phpunit.xml` — PHPUnit test configuration
- `phpstan.neon` — Static analysis config

**Core Logic:**
- `app/Core/Application.php` — Bootstrap orchestrator
- `app/Core/Router.php` — Route dispatch
- `app/Core/Security/AuthMiddleware.php` — Auth + RBAC (most referenced file for security)
- `app/Core/Security/Permissions.php` — RBAC permission map and role hierarchy
- `app/Core/Providers/RepositoryFactory.php` — Repository access entry point
- `app/api.php` — `api_ok()`, `api_fail()`, validation helpers (required by all API files)
- `app/bootstrap.php` — Autoload + `Application::boot()` wrapper

**Design System:**
- `public/assets/css/design-system.css` — All CSS custom properties (design tokens), utility classes
- `public/assets/css/app.css` — Global layout structure
- `public/assets/js/core/utils.js` — Global `window.api()`, `Utils.*` helpers

**Testing:**
- `tests/bootstrap.php` — Test setup (calls `Application::bootCli()`)
- `tests/Unit/` — Unit tests for Services, Repositories, Core
- `tests/Integration/` — Integration tests against real DB

## Naming Conventions

**PHP Files:**
- Controllers: `PascalCase` + `Controller` suffix — `MeetingsController.php`
- Services: `PascalCase` + `Service` suffix — `VoteEngine.php` (engine) or `BallotsService.php` (service)
- Repositories: `PascalCase` + `Repository` suffix — `MeetingRepository.php`
- Core: Descriptive `PascalCase` — `AuthMiddleware.php`, `RepositoryFactory.php`

**PHP Namespaces:**
- `AgVote\Controller\` — `app/Controller/`
- `AgVote\Core\` — `app/Core/`
- `AgVote\Repository\` — `app/Repository/`
- `AgVote\Service\` — `app/Services/`
- `AgVote\WebSocket\` — `app/WebSocket/`
- `AgVote\Event\` — `app/Event/`

**API Endpoint Files (`public/api/v1/`):**
- `snake_case.php` — matches the URL path segment, e.g., `ballots_cast.php`, `meeting_transition.php`, `operator_workflow_state.php`

**HTML Pages:**
- `[page-name].htmx.html` — SPA pages, e.g., `dashboard.htmx.html`, `operator.htmx.html`

**JS Files:**
- Page modules: `kebab-case.js` — `operator-tabs.js`, `analytics-dashboard.js`
- Components: `ag-[name].js` — `ag-modal.js`, `ag-toast.js`
- CSS: `kebab-case.css` matching page name — `operator.css`, `email-templates.css`

**CSS Classes:**
- Layout: `app-shell`, `app-sidebar`, `app-header`, `app-main`
- Components: `btn`, `btn-primary`, `btn-sm`, `badge`, `badge-success`, `card`, `kpi-card`
- Utilities: `text-muted`, `text-sm`, `flex`, `gap-4`, `p-4` (Tailwind-like single-purpose classes)

## Where to Add New Code

**New API Endpoint:**
1. Add a controller method to an existing controller in `app/Controller/` OR create a new `[Domain]Controller.php` extending `AbstractController`
2. Register the route in `app/routes.php` using `$router->map('POST', '/api/v1/[path]', ControllerClass::class, 'methodName', ['role' => 'operator'])`
3. For direct-file access compatibility, also create `public/api/v1/[endpoint].php` that `require_once` `app/api.php` and calls the controller

**New Frontend Page:**
1. Create `public/[page-name].htmx.html` following the pattern: load `app.css`, page CSS, sidebar partial, core JS, page JS
2. Create `public/assets/css/[page-name].css` for page-specific styles
3. Create `public/assets/js/pages/[page-name].js` for page data/interaction logic

**New Web Component:**
1. Create `public/assets/js/components/ag-[name].js` as a Custom Element class
2. Register it in `public/assets/js/components/index.js`

**New Repository:**
1. Create `app/Repository/[Domain]Repository.php` extending `AbstractRepository`
2. Add a typed accessor method to `app/Core/Providers/RepositoryFactory.php`

**New Service:**
1. Create `app/Services/[Domain]Service.php` in namespace `AgVote\Service`
2. Accept repository dependencies via constructor, defaulting to `RepositoryFactory::getInstance()->xxx()`

**New CLI Command:**
1. Create `app/Command/[Name]Command.php`
2. Register it in `bin/console`

**New Migration:**
1. Create `database/migrations/[YYYYMMDD]_[description].sql` with `date +%Y%m%d` prefix

## Special Directories

**`vendor/`:**
- Purpose: Composer PHP dependencies
- Generated: Yes
- Committed: No

**`public/assets/vendor/`:**
- Purpose: Bundled third-party CSS assets
- Generated: No (manually bundled)
- Committed: Yes

**`public/assets/js/vendor/`:**
- Purpose: Bundled third-party JS (`chart.umd.js`, `htmx.min.js`, `marked.min.js`)
- Generated: No (manually bundled)
- Committed: Yes

**`.planning/`:**
- Purpose: GSD planning and analysis documents
- Generated: Partially (GSD commands write codebase docs)
- Committed: Yes

**`/tmp/agvote-*.json`:**
- Purpose: File-based event queue and SSE queue fallback when Redis is unavailable
- Generated: Yes, at runtime
- Committed: No

---

*Structure analysis: 2026-03-16*
