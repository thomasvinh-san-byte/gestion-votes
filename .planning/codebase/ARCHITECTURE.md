# Architecture

**Analysis Date:** 2026-03-16

## Pattern Overview

**Overall:** Layered MVC with PHP REST API backend + vanilla JS SPA-style frontend

**Key Characteristics:**
- PHP backend serves a REST JSON API (`/api/v1/`) with no server-side HTML rendering (except two special pages: vote form and doc viewer)
- Frontend is a collection of standalone HTML files (`.htmx.html`) that fetch data via `window.api()` and render entirely client-side
- No JavaScript framework — all frontend is vanilla JS with custom Web Components and IIFE modules
- Real-time updates delivered via Server-Sent Events (SSE) with Redis queue; graceful file-based fallback when Redis is unavailable
- Multi-tenant data isolation enforced at the repository layer using `tenant_id` on all queries

## Layers

**Frontend Shell (HTML + CSS):**
- Purpose: Static HTML pages that define page structure, load assets, and initialize JS
- Location: `public/*.htmx.html`
- Contains: HTML layout, CSS `<link>` tags, JS `<script>` tags, sidebar `data-include-sidebar` mount point
- Depends on: Core JS modules, page JS modules, design system CSS
- Used by: Browser — served directly as static files

**Core JS Modules:**
- Purpose: Shared runtime infrastructure loaded on every page
- Location: `public/assets/js/core/`
  - `utils.js` — global `window.api()` fetch wrapper with CSRF headers, `Utils.apiGet/Post`, `escapeHtml`, etc.
  - `shared.js` — sidebar partial loader (`/partials/sidebar.html`), role label maps, button loading helpers
  - `shell.js` — sidebar pin/expand/collapse, drawer system, mobile nav, theme toggle, notifications bell (60s polling), Ctrl+K global search
  - `event-stream.js` — SSE client connecting to `/api/v1/events.php`, auto-reconnects up to 10 times, falls back to polling
  - `page-components.js` — reusable UI classes: `TabManager`, `FilterManager`, `ViewToggle`, `CalendarView`, `CollapsibleSection`
- Depends on: Each other in load order — utils → shared → shell → page-specific
- Used by: All page JS modules

**Page JS Modules:**
- Purpose: Page-specific data loading, event binding, and DOM manipulation
- Location: `public/assets/js/pages/`
- Contains: One file per page/view (e.g., `operator-tabs.js` at 3,165 LOC, `meetings.js`, `vote.js`, `admin.js`)
- Depends on: Core JS modules, `MeetingContext`, `window.api()`
- Used by: The corresponding `.htmx.html` page

**Web Components:**
- Purpose: Reusable UI primitives as Custom Elements
- Location: `public/assets/js/components/`
- Contains: 20 components — `ag-kpi`, `ag-badge`, `ag-spinner`, `ag-toast`, `ag-modal`, `ag-quorum-bar`, `ag-vote-button`, `ag-pagination`, `ag-donut`, `ag-stepper`, `ag-confirm`, `ag-breadcrumb`, `ag-page-header`, `ag-mini-bar`, `ag-tooltip`, `ag-time-input`, `ag-tz-picker`, `ag-searchable-select`, `ag-popover`, `ag-scroll-top`
- Entry point: `public/assets/js/components/index.js`
- Depends on: Vanilla DOM APIs only

**Frontend Service Layer:**
- Purpose: Cross-page client-side state management
- Location: `public/assets/js/services/`
- Contains: `meeting-context.js` — singleton that stores and propagates `meeting_id` across pages via `sessionStorage`, URL params, and `CustomEvent('meetingcontext:change')`

**PHP Front Controller:**
- Purpose: Entry point for all HTTP requests, delegates to Router or fallback file routing
- Location: `public/index.php`
- Triggers: Apache/Nginx rewrites all requests here
- Responsibilities: URI normalization, bootstrap loading, router dispatch, file-based fallback for direct `.php` access, root redirect to `login.html`

**Application Bootstrap:**
- Purpose: Ordered initialization sequence for all providers
- Location: `app/Core/Application.php` (loaded via `app/bootstrap.php`)
- Boot sequence: EnvProvider → class aliases → config → error handling → SecurityProvider headers → CORS → DatabaseProvider (PDO) → SecurityProvider auth init → RedisProvider (optional) → EventDispatcher
- Also provides `bootCli()` for commands without HTTP headers/CORS

**Router:**
- Purpose: Exact-match and parameterized route dispatch with middleware pipeline
- Location: `app/Core/Router.php`
- Route config: `app/routes.php`
- Responsibilities: Map `METHOD /uri` to `ControllerClass::method`, build middleware pipeline (RoleMiddleware, RateLimitGuard), dispatch; O(1) exact match then O(n) parameterized check

**Middleware Pipeline:**
- Purpose: Cross-cutting request concerns run before the controller
- Location: `app/Core/MiddlewarePipeline.php`, `app/Core/Middleware/`
- Contains: `RoleMiddleware` (calls `AuthMiddleware::requireRole`), `RateLimitGuard`
- Security: `app/Core/Security/AuthMiddleware.php` (session + API key auth), `app/Core/Security/CsrfMiddleware.php`

**Controllers:**
- Purpose: Thin HTTP handlers — parse request, call services/repositories, call `api_ok()`/`api_fail()`
- Location: `app/Controller/` (37 controllers)
- Base class: `app/Controller/AbstractController.php` — wraps methods in standardized exception handling, provides `$this->repo()` factory access
- Rule: Controllers contain no business logic; all domain work is delegated to Services or Repositories

**Services:**
- Purpose: Business logic and domain operations spanning multiple repositories
- Location: `app/Services/`
- Key services:
  - `VoteEngine.php` — quorum/majority calculation; `computeDecision()` is a pure function (no I/O)
  - `MeetingWorkflowService.php` — pre-condition checks for state transitions (draft→scheduled→frozen→live→closed→validated)
  - `QuorumEngine.php` — quorum rules engine
  - `OfficialResultsService.php` — final result determination with policies
  - `BallotsService.php`, `AttendancesService.php`, `ProxiesService.php` — domain operations
  - `MailerService.php`, `EmailQueueService.php` — email delivery
  - `ExportService.php`, `MeetingReportService.php` — document generation
  - `NotificationsService.php`, `SpeechService.php`, `VoteTokenService.php`

**Repositories:**
- Purpose: All database access — no business logic, only data retrieval and persistence
- Location: `app/Repository/` (27 repositories)
- Base class: `app/Repository/AbstractRepository.php` — PDO helpers (`selectOne`, `selectAll`, `execute`, `scalar`, `insertReturning`, `buildInClause`)
- Factory: `app/Core/Providers/RepositoryFactory.php` — lazy-instantiation singleton, one instance per repository per request; accessed via `$this->repo()->meeting()`, etc.
- Traits: `app/Repository/Traits/` — `MotionFinderTrait`, `MotionListTrait`, `MotionWriterTrait`, `MotionAnalyticsTrait` (MotionRepository uses all four via composition)

**Event System:**
- Purpose: Domain events for real-time broadcasting; decouples business logic from delivery
- Location: `app/Event/` and `app/WebSocket/`
- Components:
  - `app/Event/VoteEvents.php` — event name string constants
  - `app/Event/AppEvent.php` — event payload wrapper
  - `app/WebSocket/EventBroadcaster.php` — static helpers per event type; queues to Redis (`sse:events:{meeting_id}` list) or file fallback (`/tmp/agvote-sse-{id}.json`)
  - `app/Event/Listener/WebSocketListener.php` — Symfony EventDispatcher subscriber
- Consumer: `public/api/v1/events.php` — SSE long-poll endpoint, reads from Redis per `meeting_id`, falls back to `EventBroadcaster::dequeueSseFile()`

**CLI Commands:**
- Purpose: Background jobs and maintenance tasks
- Location: `app/Command/`
- Contains: `EmailProcessQueueCommand.php`, `MonitoringCheckCommand.php`, `RateLimitCleanupCommand.php`, `RedisHealthCommand.php`
- Entry point: `bin/console`

**PHP Templates:**
- Purpose: Server-rendered HTML for the two non-API pages (vote form, doc viewer)
- Location: `app/Templates/` — `vote_form.php`, `vote_confirm.php`, `doc_page.php`, `email_invitation.php`, `email_report.php`, CSRF head partials
- Renderer: `app/View/HtmlView.php` — `HtmlView::render('template', $data)`

## Data Flow

**Standard Authenticated API Request:**

1. Browser calls `window.api('/api/v1/meetings.php', ...)` which attaches `X-CSRF-Token` header
2. Apache/Nginx rewrites to `public/index.php`
3. `Application::boot()` initializes providers (env, DB, auth, Redis)
4. `Router::dispatch()` matches the route; builds `MiddlewarePipeline`
5. `RoleMiddleware` calls `AuthMiddleware::requireRole()` — validates session or API key, enforces RBAC
6. Controller method is invoked; calls `$this->repo()->xxx()` for data and services for logic
7. Controller calls `api_ok(['data' => $result])` which throws `ApiResponseException`
8. Router catches it, sends `JsonResponse` as `application/json`
9. Frontend JS receives `{ ok: true, data: {...} }` and updates the DOM

**Vote Casting Flow:**

1. Voter on `vote.htmx.html` submits ballot via `window.api('/api/v1/ballots_cast.php', payload, 'POST')`
2. `BallotsController` validates token via `VoteTokenService`, records ballot via `BallotRepository`
3. `VoteEngine::computeDecision()` recalculates tally (pure function, no DB writes)
4. `EventBroadcaster::voteCast()` pushes `vote.cast` event to Redis list `sse:events:{meeting_id}`
5. Operator's `event-stream.js` EventSource receives SSE push from `events.php`, triggers `onEvent('vote.cast', data)` callback which updates live tally display

**Meeting State Transition Flow:**

1. Operator clicks "Open vote" on `operator.htmx.html`
2. `operator-exec.js` calls `window.api('/api/v1/meeting_transition.php', { to: 'live' }, 'POST')`
3. `MeetingWorkflowController` calls `MeetingWorkflowService::issuesBeforeTransition()` for pre-conditions
4. `AuthMiddleware::requireTransition()` checks RBAC from `Permissions::TRANSITIONS`
5. `MeetingRepository` updates meeting `status` column
6. `EventBroadcaster::meetingStatusChanged()` broadcasts to both meeting channel and tenant channel via Redis

**Real-time SSE Flow:**

1. `event-stream.js` opens `EventSource` to `/api/v1/events.php?meeting_id=xxx`
2. `events.php` holds connection, polls Redis list `sse:events:{meeting_id}`, flushes events as SSE messages
3. On Redis failure, reads from `EventBroadcaster::dequeueSseFile()` (per-meeting JSON file in `/tmp/`)
4. Browser `EventSource` dispatches events to `onEvent(type, data)` callbacks registered by page JS

**Frontend State Management:**

- `MeetingContext` is the single source of truth for `meeting_id` across page navigations
- Stored in `sessionStorage` key `meeting_id`, synced to URL params via `history.replaceState()`
- Propagated to all `<a href>` links (`.htmx.html` and `.php`) on set
- `CustomEvent('meetingcontext:change')` notifies page modules on change; cross-tab sync via `storage` event

## Key Abstractions

**ApiResponseException / api_ok / api_fail:**
- Purpose: Exception-based flow control guaranteeing response is sent regardless of call depth
- Location: `app/Core/Http/ApiResponseException.php`, `app/Core/Http/JsonResponse.php`, `app/api.php` (global functions)
- Pattern: `api_ok(['data' => $result])` and `api_fail('error_code', 422)` both `throw` — no `return` needed

**RepositoryFactory:**
- Purpose: Request-scoped singleton container for all 27 repositories; injectable for testing
- Location: `app/Core/Providers/RepositoryFactory.php`
- Pattern: `$this->repo()->meeting()`, `$this->repo()->ballot()` — lazy-instantiated per request; `RepositoryFactory::reset()` in tests

**Two-Level RBAC:**
- Purpose: System roles (permanent, account-level) + meeting roles (temporary, per-meeting assignment)
- Location: `app/Core/Security/AuthMiddleware.php`, `app/Core/Security/Permissions.php`
- System roles: `admin` (100) > `operator` (80) > `auditor` (50) > `viewer` (5)
- Meeting roles: `president` (70), `assessor` (60), `voter` (10) — assigned per meeting, no hierarchy
- Pattern: `effective_permissions = system_role_permissions ∪ meeting_role_permissions`; `admin` always bypasses

**MeetingContext (frontend):**
- Purpose: Cross-page meeting selection state with URL/sessionStorage persistence
- Location: `public/assets/js/services/meeting-context.js`
- Pattern: IIFE module exposing `MeetingContext.get()`, `.set()`, `.onChange()` — call `MeetingContext.init()` once on page load; propagates `meeting_id` to all nav links automatically

**EventBroadcaster:**
- Purpose: Decouple domain events from delivery; backend pushes to Redis, SSE endpoint pulls
- Location: `app/WebSocket/EventBroadcaster.php`
- Pattern: `EventBroadcaster::motionOpened($meetingId, $motionId, $data)` — static named helpers per event type, automatic Redis/file fallback

## Entry Points

**HTTP Front Controller:**
- Location: `public/index.php`
- Triggers: All web requests via Apache/Nginx rewrite
- Responsibilities: Bootstrap, router dispatch, fallback to direct `.php` files, root URL redirect

**Direct API Files (fallback routing):**
- Location: `public/api/v1/*.php` (150+ files)
- Triggers: Direct file access or router fallback; each file `require_once` `app/api.php` → `app/bootstrap.php`
- Note: This is the legacy routing path still used for many endpoints that haven't been fully migrated to the Router

**Vote Page (public, server-rendered):**
- Location: `public/vote.php`
- Triggers: Voter follows invitation link with `token=` query param
- Responsibilities: Render HTML vote form via `HtmlView::render('vote_form', $data)`

**CLI:**
- Location: `bin/console`
- Triggers: Cron jobs, `make` targets, manual maintenance
- Responsibilities: Calls `Application::bootCli()`, routes to Command classes

## Error Handling

**Strategy:** Exception-based flow control with standardized JSON error envelopes `{ ok: false, error: 'code' }`

**Patterns:**
- `ApiResponseException` is thrown by `api_ok()`/`api_fail()` and propagates to Router or the global `set_exception_handler`
- `AbstractController::handle()` catches: `InvalidArgumentException` → 422, `PDOException` → 500, `RuntimeException` → 400 (business rule violations), `Throwable` → 500
- `Application::configureErrors()` registers a global exception handler for unmatched exceptions → JSON 500
- Debug details (`message`, `file`, `line`) are included in responses only when `APP_DEBUG=1`; otherwise error messages are generic
- All errors are logged via `error_log()` with stack traces; no silent failures

## Cross-Cutting Concerns

**Logging:** `app/Core/Logger.php` — structured logger with per-request correlation ID (`X-Request-ID` header); methods: `Logger::warning()`, `Logger::info()`, `Logger::getRequestId()`; errors go to PHP `error_log()`

**Validation:** `app/Core/Validation/InputValidator.php` with JSON schemas in `app/Core/Validation/Schemas/`; also `app/Services/MeetingValidator.php` for domain-level meeting validation; global helpers `api_is_uuid()`, `api_require_fields()` defined in `app/api.php`

**Authentication:** PHP native sessions (30-minute timeout, 60s DB re-validation for revoked users, session ID regeneration on privilege change) + `X-Api-Key` header (HMAC-SHA256 against `APP_SECRET`); CSRF enforced via `X-CSRF-Token` header from `CsrfMiddleware`

**Multi-tenancy:** All repository queries filter by `tenant_id`; `DEFAULT_TENANT_ID` constant set at boot from `DEFAULT_TENANT_ID` env var; `AuthMiddleware::getCurrentTenantId()` provides per-request tenant isolation for all data access

---

*Architecture analysis: 2026-03-16*
