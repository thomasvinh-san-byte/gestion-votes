# Codebase Structure

**Analysis Date:** 2026-04-07

## Directory Layout

```
gestion_votes_php/
├── app/                    # PHP application source
│   ├── Command/            # CLI commands (6 files)
│   ├── Controller/         # HTTP controllers (51 PHP files)
│   ├── Core/               # Framework utilities (25 PHP files)
│   │   ├── Http/           # ApiResponseException, JsonResponse, Request
│   │   ├── Middleware/     # MiddlewareInterface, RateLimitGuard, RoleMiddleware
│   │   ├── Providers/      # DatabaseProvider, EnvProvider, RedisProvider, RepositoryFactory, SecurityProvider
│   │   ├── Security/       # AuthMiddleware, CsrfMiddleware, IdempotencyGuard, PermissionChecker, RateLimiter, SessionHelper
│   │   └── Validation/     # InputValidator + Schemas/ValidationSchemas
│   ├── Event/              # Domain events
│   │   └── Listener/       # SseListener
│   ├── Helper/             # PasswordValidator
│   ├── Repository/         # DB repositories (38 PHP files)
│   │   └── Traits/         # MotionAnalyticsTrait, MotionFinderTrait, MotionListTrait, MotionWriterTrait
│   ├── Services/           # Business logic (22 PHP files)
│   ├── SSE/                # EventBroadcaster (Redis SSE queue)
│   ├── Templates/          # PHP HTML templates (8 files)
│   ├── View/               # HtmlView renderer
│   ├── api.php             # Global HTTP helpers + session/auth init
│   ├── bootstrap.php       # Low-level boot (autoload, env, db helpers)
│   ├── config.php          # Config array (reads env vars)
│   └── routes.php          # Route table (383 lines, ~120 routes)
├── bin/
│   └── console             # CLI entry point
├── database/
│   ├── migrations/         # 25 SQL migration files
│   ├── seeds/              # Seed data
│   ├── schema-master.sql   # Full schema snapshot
│   └── setup.sh            # Database init script
├── docs/                   # Documentation (symlinked from public/docs)
├── public/                 # Web root
│   ├── api/
│   │   └── v1/             # 147 legacy PHP endpoint files
│   ├── assets/
│   │   ├── css/            # Stylesheets
│   │   ├── images/         # Static images
│   │   ├── js/
│   │   │   ├── core/       # Shared JS (utils, event-stream, shell, page-components)
│   │   │   └── pages/      # Per-page JS modules
│   │   └── vendor/         # Frontend vendor libs (HTMX, etc.)
│   ├── errors/             # Static error pages (403, 404, 500)
│   ├── partials/           # Reusable HTML partials (_csrf_head.php, sidebar.html, etc.)
│   ├── *.htmx.html         # 30 HTMX page files (one per route/view)
│   └── index.php           # Front controller
├── scripts/                # Deployment/maintenance scripts
├── tests/
│   ├── Unit/               # 94 PHPUnit unit test files
│   ├── Integration/        # 3 integration test files
│   ├── e2e/                # Playwright e2e tests
│   ├── fixtures/           # CSV fixtures for import tests
│   ├── manual/             # Manual test scripts
│   └── bootstrap.php       # PHPUnit bootstrap
├── composer.json
├── phpunit.xml             # PHPUnit config (test suite definitions)
├── phpstan.neon            # PHPStan level 5 config
├── phpstan-baseline.neon   # PHPStan allowed issues
├── .php-cs-fixer.dist.php  # PHP-CS-Fixer config
├── Dockerfile              # Multi-stage build (assets → runtime)
├── docker-compose.yml      # Development stack
└── docker-compose.prod.yml # Production stack
```

## Directory Purposes

**`app/Controller/` (51 PHP files):**
- Purpose: HTTP request handlers, one class per API resource or HTML page
- Contains: All `*Controller.php` classes plus `AbstractController.php`, and exception classes (`AccountRedirectException.php`, `EmailPixelSentException.php`, `FileServedOkException.php`, `PasswordResetRedirectException.php`, `SetupRedirectException.php`)
- Key files: `AbstractController.php`, `MeetingsController.php` (687 lines), `MotionsController.php` (720 lines), `MeetingReportsController.php` (727 lines), `OperatorController.php` (516 lines)

**`app/Services/` (22 PHP files):**
- Purpose: Business logic (voting, email, export, import, quorum, workflow)
- Contains: Domain services with no HTTP or PDO dependencies
- Key files: `ImportService.php` (791 lines), `ExportService.php` (770 lines), `EmailQueueService.php` (625 lines), `VoteEngine.php`, `QuorumEngine.php`, `MeetingWorkflowService.php`, `ErrorDictionary.php` (357 lines)

**`app/Repository/` (38 PHP files + 4 traits):**
- Purpose: Data access objects — typed query methods per entity
- Contains: `AbstractRepository.php` base, one repository per DB table/entity
- Key files: `MeetingRepository.php` (557 lines), `MemberRepository.php` (549 lines), `BallotRepository.php` (536 lines), `AnalyticsRepository.php` (410 lines)
- Traits: `MotionFinderTrait.php`, `MotionWriterTrait.php`, `MotionListTrait.php`, `MotionAnalyticsTrait.php` — compose `MotionRepository`

**`app/Core/` (25 PHP files):**
- Purpose: Framework-level classes, not business-specific
- Key files: `Application.php` (263 lines — boot orchestrator), `Router.php` (338 lines), `Security/AuthMiddleware.php` (871 lines — largest file), `Core/Validation/Schemas/ValidationSchemas.php` (512 lines), `Core/Validation/InputValidator.php` (464 lines)

**`app/Command/` (6 PHP files):**
- Purpose: CLI-only background tasks
- Contains: `DataRetentionCommand.php`, `EmailProcessQueueCommand.php`, `MonitoringCheckCommand.php`, `RateLimitCleanupCommand.php`, `RateLimitResetCommand.php`, `RedisHealthCommand.php`

**`app/Event/` + `app/SSE/`:**
- Purpose: Domain event definitions and SSE broadcasting
- Key files: `Event/VoteEvents.php` (event name constants), `Event/AppEvent.php` (payload), `Event/Listener/SseListener.php` (bridges dispatcher to broadcaster), `SSE/EventBroadcaster.php` (Redis queue writer)

**`app/Templates/` (8 PHP files):**
- Purpose: Server-rendered HTML pages for non-HTMX flows
- Contains: `account_form.php`, `setup_form.php`, `vote_confirm.php`, `vote_already_cast.php`, `reset_request_form.php`, `reset_newpassword_form.php`, `reset_success.php`, `doc_page.php`
- Rendered via: `AgVote\View\HtmlView::render()` in controllers that do NOT extend `AbstractController`

**`public/api/v1/` (147 PHP files):**
- Purpose: Legacy file-based API endpoints — each file corresponds to one endpoint
- Status: Coexists with router-based controllers; served as fallback from `public/index.php`
- Pattern: Each file bootstraps via `require` chain and processes the request

**`public/*.htmx.html` (30 files):**
- Purpose: HTMX SPA pages — static HTML shells that load dynamic content via HTMX requests
- Contains: `dashboard.htmx.html`, `meetings.htmx.html`, `members.htmx.html`, `vote.htmx.html`, `operator.htmx.html`, `admin.htmx.html`, `analytics.htmx.html`, etc.

**`tests/Unit/` (94 PHP files):**
- Purpose: PHPUnit unit tests — mock repositories, test services and controllers in isolation
- Contains one test file per class; base class `ControllerTestCase.php` shared by controller tests

**`tests/Integration/` (3 PHP files):**
- Purpose: Integration tests requiring live DB
- Contains: `RepositoryTest.php`, `WorkflowValidationTest.php`, `AdminCriticalPathTest.php`

**`database/migrations/` (25 SQL files):**
- Purpose: Incremental schema changes
- Naming: `001_*.sql` through numbered sequence + date-prefixed files (`20260204_*.sql`, `20260217_*.sql`)

## Key File Locations

**Entry Points:**
- `public/index.php`: HTTP front controller
- `bin/console`: CLI entry point
- `app/bootstrap.php`: Low-level PHP bootstrap (autoload, globals)
- `app/api.php`: HTTP layer bootstrap (session, auth, global helpers)

**Route Table:**
- `app/routes.php`: All ~120 registered routes (383 lines)

**Configuration:**
- `app/config.php`: Config array assembled from env vars
- `.env`: Runtime environment variables (not committed)
- `phpunit.xml`: PHPUnit test suites configuration
- `phpstan.neon`: Static analysis configuration

**Core Abstractions:**
- `app/Controller/AbstractController.php`: Base for all API controllers
- `app/Repository/AbstractRepository.php`: Base for all repositories
- `app/Core/Providers/RepositoryFactory.php`: Lazy repository registry (singleton)
- `app/Core/Security/AuthMiddleware.php`: Session validation + RBAC (871 lines)

**Error Handling:**
- `app/Core/Http/ApiResponseException.php`: Flow-control exception for API responses
- `app/Core/Http/JsonResponse.php`: JSON response sender
- `app/Services/ErrorDictionary.php`: French error code map

## Naming Conventions

**Files:**
- Controllers: `{Resource}Controller.php` in `app/Controller/`
- Services: `{Domain}Service.php` or `{Domain}Engine.php` in `app/Services/`
- Repositories: `{Entity}Repository.php` in `app/Repository/`
- Tests: `{Subject}Test.php` in `tests/Unit/`
- Legacy API endpoints: `{action_name}.php` (snake_case) in `public/api/v1/`

**Directories:**
- PHP source: PascalCase (`Controller/`, `Services/`, `Repository/`)
- Assets: lowercase (`css/`, `js/`, `images/`)

## Where to Add New Code

**New API endpoint (controller-based):**
1. Create `app/Controller/{Resource}Controller.php` extending `AbstractController`
2. Register route in `app/routes.php`: `$router->map('POST', '/api/v1/...', ResourceController::class, 'methodName', ['role' => 'operator'])`
3. Tests: `tests/Unit/{Resource}ControllerTest.php` extending `ControllerTestCase`

**New HTML page (non-API):**
1. Create `public/{pagename}.htmx.html` for the HTMX shell
2. If server-rendered: create `app/Templates/{pagename}.php` and render via `HtmlView::render()`
3. Controller (if needed): does NOT extend `AbstractController`, uses `HtmlView::render()` directly

**New service:**
1. Create `app/Services/{Name}Service.php` — `final class`, constructor with nullable repo params
2. Tests: `tests/Unit/{Name}ServiceTest.php`

**New repository:**
1. Create `app/Repository/{Entity}Repository.php` extending `AbstractRepository`
2. Register in `app/Core/Providers/RepositoryFactory.php`: add `use` import, add property, add accessor method
3. For large repositories: split into traits in `app/Repository/Traits/`

**New CLI command:**
1. Create `app/Command/{Name}Command.php`
2. Register in `bin/console` command map

**New migration:**
- Add `database/migrations/{YYYYMMDD}_{description}.sql` with `YYYYMMDD` prefix

**New domain event:**
1. Add constant to `app/Event/VoteEvents.php`
2. Add listener method in `app/Event/Listener/SseListener.php`
3. Register listener in `SseListener::subscribe()`

## Special Directories

**`vendor/`:**
- Purpose: Composer-managed PHP dependencies
- Generated: Yes
- Committed: No (`.gitignore`)

**`tests/e2e/`:**
- Purpose: Playwright browser-automation end-to-end tests
- Contains: Node.js test suite with `node_modules/` (not committed to main repo)

**`coverage-report/`:**
- Purpose: PHPUnit HTML coverage output
- Generated: Yes
- Committed: No

**`.planning/`:**
- Purpose: GSD workflow planning artifacts (phases, research, codebase docs)
- Committed: Yes

---

*Structure analysis: 2026-04-07*
