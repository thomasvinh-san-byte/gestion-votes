# Architecture

**Analysis Date:** 2026-04-07

## Pattern Overview

**Overall:** Front Controller with layered MVC, middleware pipeline, and event-driven SSE broadcasting.

**Key Characteristics:**
- Single entry point `public/index.php` dispatches to typed controller classes via `AgVote\Core\Router`
- Legacy file-based routing in `public/api/v1/*.php` (147 files) coexists as fallback for uncovered routes
- Multi-tenant isolation: every data query is scoped by `tenant_id`
- Two-level RBAC: system roles (account-level) combined with meeting roles (per-session)
- SSE real-time updates via Redis queue, decoupled from controllers via Symfony EventDispatcher

## Entry Points

**HTTP Front Controller:**
- Location: `public/index.php`
- Triggers: All HTTP requests via URL rewriting (Apache `.htaccess` / nginx)
- Responsibilities:
  1. Resolves URI and HTTP method
  2. Loads `app/bootstrap.php` (env, DB, Redis, security providers, event dispatcher)
  3. Instantiates `Router`, loads route table from `app/routes.php`
  4. For non-bootstrap routes: defines `AG_ROUTER_ACTIVE` and loads `app/api.php` (session, auth, CSRF, global helpers)
  5. Calls `Router::dispatch()` — on match, builds middleware pipeline then calls controller
  6. Falls back to file-based routing: maps URI to `public/api/v1/{endpoint}.php`
  7. Returns 404 JSON if nothing matches

**Bootstrap-only routes (email tracking):**
- `public/index.php` skips `api.php` for `/api/v1/email_pixel` and `/api/v1/email_redirect`
- These routes only need DB access, not session/auth context

**Special raw routes (skip all middleware):**
- `/api/v1/doc_content` — served directly from `public/api/v1/doc_content.php` before the router runs

**CLI Entry Point:**
- Location: `bin/console` → `app/Command/`
- Triggers: Shell execution (cron, manual)
- Responsibilities: Long-running tasks (email queue, data retention, Redis health checks)
- Uses: `Application::bootCli()` — boots DB + Redis without HTTP headers or CORS

**HTML Views:**
- Location: `public/*.htmx.html` (30 files), `app/Templates/` (8 PHP templates)
- Triggers: Direct browser navigation
- Responsibilities: Serve HTMX-driven SPA pages; PHP templates rendered via `AgVote\View\HtmlView::render()`

## Layers

**Presentation — Controllers:**
- Purpose: Parse HTTP input, call services/repositories, emit JSON via `api_ok()` / `api_fail()`
- Location: `app/Controller/` (51 PHP files)
- Contains: Controller classes extending `AbstractController`; HTML controllers (login, setup, reset) using `HtmlView::render()` directly without extending `AbstractController`
- Depends on: Services, Repositories (via `$this->repo()`), Core HTTP helpers
- Used by: Router, legacy file-based includes

**HTTP Helpers Layer:**
- Purpose: Global request/response helpers available everywhere after `app/api.php` loads
- Location: `app/api.php`, `app/Core/Http/`
- Contains: `api_ok()`, `api_fail()`, `api_require_role()`, `api_current_user_id()`, `api_current_tenant_id()`, `api_transaction()`, `Request` class, `JsonResponse`
- Pattern: `api_ok()` and `api_fail()` throw `ApiResponseException` (never call `exit()` directly)

**Middleware Layer:**
- Purpose: Auth, CSRF, rate limiting, role enforcement
- Location: `app/Core/Middleware/`, `app/Core/Security/`
- Contains: `AuthMiddleware`, `CsrfMiddleware`, `RateLimitGuard`, `RoleMiddleware`, `PermissionChecker`, `IdempotencyGuard`
- Used by: Router pipeline, `api.php` (for legacy file-based access)

**Service Layer:**
- Purpose: Business logic — no HTTP awareness, no direct PDO
- Location: `app/Services/` (22 PHP files)
- Contains: `VoteEngine`, `QuorumEngine`, `BallotsService`, `MeetingWorkflowService`, `EmailQueueService`, `ExportService`, `ImportService`, `MailerService`, `OfficialResultsService`, `ProxiesService`, `RgpdExportService`, etc.
- Pattern: Injected via constructor with nullable parameters for tests

**Repository Layer:**
- Purpose: All database access, typed query methods, no business logic
- Location: `app/Repository/` (38 PHP files + `Traits/` with 4 trait files for `MotionRepository`)
- Contains: `MeetingRepository`, `BallotRepository`, `MemberRepository`, `UserRepository`, `AnalyticsRepository`, etc., all extending `AbstractRepository`
- Methods: `selectOne()`, `selectAll()`, `selectGenerator()` (streaming), `execute()`, `insertReturning()`, `scalar()`
- Accessed via: `RepositoryFactory::getInstance()->meeting()` (lazy per-request cache)

**Core Framework:**
- Purpose: Framework utilities — routing, logging, providers, validation
- Location: `app/Core/` (25 PHP files)
- Contains: `Application` (boot orchestrator), `Router`, `Logger`, `MiddlewarePipeline`, `DatabaseProvider`, `RedisProvider`, `EnvProvider`, `SecurityProvider`, `RepositoryFactory`, `InputValidator`, `ValidationSchemas`

## Dependency Injection Pattern

**RepositoryFactory singleton:**
```php
// Production — shared singleton per request
RepositoryFactory::getInstance()->meeting()->findByIdForTenant($id, $tenantId);

// Tests — inject custom PDO
$factory = new RepositoryFactory($mockPdo);
$factory->meeting()->findByIdForTenant($id, $tenantId);

// Reset singleton between tests
RepositoryFactory::reset();
```

**Controller repository access:**
```php
// AbstractController (app/Controller/AbstractController.php) provides:
protected function repo(): RepositoryFactory {
    return RepositoryFactory::getInstance();
}
// Usage in controllers:
$meeting = $this->repo()->meeting()->findByIdForTenant($id, $tenantId);
```

**Service constructor pattern (nullable for test injection):**
```php
public function __construct(
    ?MeetingRepository $meetingRepo = null,
    ?BallotsService $ballotsService = null,
) {
    $this->meetingRepo = $meetingRepo ?? RepositoryFactory::getInstance()->meeting();
    $this->ballotsService = $ballotsService ?? new BallotsService();
}
```

**AbstractRepository fallback:**
```php
// app/Repository/AbstractRepository.php
public function __construct(?PDO $pdo = null) {
    $this->pdo = $pdo ?? db(); // falls back to global db() helper
}
```

## Middleware Chain

**Router-based middleware pipeline (registered routes):**
1. `app/api.php` runs first — starts PHP session, validates auth via `AuthMiddleware::authenticate()`, enforces CSRF on write methods
2. `MiddlewarePipeline` executes per-route middleware in registration order:
   - `RoleMiddleware` → delegates to `api_require_role()` → calls `AuthMiddleware::requireRole()`
   - `RateLimitGuard` → Redis sliding window counter; throws `ApiResponseException(429)` on limit exceeded
3. Controller method executes after pipeline completes

**Route middleware configuration (in `app/routes.php`):**
```php
$router->map('GET', '/api/v1/meetings', MeetingsController::class, 'index', ['role' => 'viewer']);
$router->map('POST', '/api/v1/ballots', BallotsController::class, 'cast', [
    'role' => 'voter',
    'rate_limit' => ['public_vote', 10, 60],
]);
```

**Legacy file-based routes:**
- `Router::resolveMiddlewareConfig()` is called from `api.php` to apply the same role/rate-limit middleware even when `public/api/v1/*.php` files are accessed directly (bypassing the front controller)

**CSRF validation:**
- `CsrfMiddleware::validate()` skips GET/HEAD/OPTIONS
- Accepts token from: `X-CSRF-Token` header (HTMX/AJAX), POST field, or JSON body field `csrf_token`
- Token stored in `$_SESSION['csrf_token']` with sliding-window expiry tied to session timeout

## Multi-Tenant Architecture

**Tenant isolation:**
- Every user belongs to a tenant (`users.tenant_id`)
- Every repository query is scoped: `WHERE tenant_id = :tenant_id`
- Tenant ID available globally as `api_current_tenant_id()` after `AuthMiddleware` runs
- `DEFAULT_TENANT_ID` constant set from `DEFAULT_TENANT_ID` / `TENANT_ID` env vars

**Two-level RBAC:**
- **System roles** (permanent, stored in `users.role`): `admin`, `operator`, `auditor`, `viewer`, `president`
- **Meeting roles** (temporary, per-meeting, stored in `meeting_roles` table): `president`, `assessor`, `voter`
- **Effective permissions** = union of system role permissions + active meeting role permissions
- Role aliases for backward compatibility: `'trust' => 'assessor'`, `'readonly' => 'viewer'`

**Session context (populated by `AuthMiddleware`):**
- `$_SESSION['auth_user']` — current user array (id, email, role, tenant_id, is_active)
- `AuthMiddleware::getCurrentUser()` — static cached access
- `AuthMiddleware::setMeetingContext($meetingId)` — enables meeting role resolution
- Session timeout: read from `tenant_settings.settSessionTimeout` (minutes), clamped 5–480 min, default 30 min
- Re-validation interval: 60 seconds (checks `is_active` in DB against cached session)

**Session storage:** Redis (mandatory). `RedisProvider::connection()` is called during boot; throws `RuntimeException` if Redis is unavailable.

## Event System

**EventDispatcher (Symfony):**
- Location: `app/Core/Application.php::initEventDispatcher()`, `app/Event/`
- Bootstrap: `Application::boot()` creates `Symfony\Component\EventDispatcher\EventDispatcher` and registers `SseListener` via `SseListener::subscribe($dispatcher)`
- Accessed via: `Application::dispatcher()`

**Domain event constants (`app/Event/VoteEvents.php`):**
- `vote.cast`, `vote.updated`
- `motion.opened`, `motion.closed`, `motion.updated`
- `attendance.updated`, `quorum.updated`
- `meeting.status_changed`
- `speech.queue_updated`

**AppEvent payload carrier (`app/Event/AppEvent.php`):**
- Properties: `$meetingId`, `$tenantId`, `$data[]`

**SSE broadcast flow:**
1. Controller dispatches: `Application::dispatcher()->dispatch(new AppEvent(...), VoteEvents::VOTE_CAST)`
2. `SseListener::onVoteCast()` (`app/Event/Listener/SseListener.php`) receives the event
3. Calls `EventBroadcaster::voteCast()` (`app/SSE/EventBroadcaster.php`) → enqueues to Redis list `sse:event_queue` (max 1000 entries)
4. Long-polling SSE endpoint dequeues from Redis and streams `text/event-stream` to connected browser clients

## Error Handling Flow

**ApiResponseException (flow-control, not an error):**
- Location: `app/Core/Http/ApiResponseException.php`
- Thrown by `api_ok()` and `api_fail()` global helpers
- Carries a `JsonResponse` (status code + JSON body)
- **Never caught in controllers** — propagates through `AbstractController::handle()` which re-throws it
- Caught at the Router level: `Router::dispatch()` catches it and calls `$e->getResponse()->send()`

**AbstractController exception hierarchy (`app/Controller/AbstractController.php::handle()`):**
```php
try { $this->$method(); }
catch (ApiResponseException $e)     { throw $e; }              // re-throw (not an error)
catch (InvalidArgumentException $e) { api_fail('invalid_request', 422, ...) }
catch (PDOException $e)             { api_fail('internal_error', 500, ...) }
catch (RuntimeException $e)         { api_fail('business_error', 400, ...) }
catch (Throwable $e)                { api_fail('internal_error', 500, ...) }
```

**ErrorDictionary:**
- Location: `app/Services/ErrorDictionary.php` (357 lines)
- Centralized map of error codes to French messages
- Use: `api_fail('unauthorized', 401)` — pass error code, not raw message strings

**Global exception handler:**
- Set in `Application::configureErrors()` via `set_exception_handler()`
- Catches uncaught `Throwable`, logs stack trace, returns 500 JSON with generic French message
- In debug mode: includes `debug.message`, `debug.file`, `debug.line` in response

## Application Boot Sequence

Order in `Application::boot()` (`app/Core/Application.php`):
1. `EnvProvider::load()` — parse `.env`
2. `registerClassAliases()` — `CsrfMiddleware`, `AuthMiddleware`, `RateLimiter` registered as global class aliases
3. `loadConfig()` — load `app/config.php`, set constants (`APP_SECRET`, `DEFAULT_TENANT_ID`), validate production security settings
4. `configureErrors()` — set `display_errors`, register global exception handler
5. `SecurityProvider::headers()` — emit security response headers + `X-Request-ID`
6. `SecurityProvider::cors()` — handle CORS preflight
7. `DatabaseProvider::connect()` — establish PDO connection to PostgreSQL
8. `SecurityProvider::init()` — init `AuthMiddleware` and `RateLimiter`
9. `RedisProvider::configure()` + `RedisProvider::connection()` — mandatory; throws `RuntimeException` if unavailable
10. `initEventDispatcher()` — create Symfony EventDispatcher, register `SseListener`

---

*Architecture analysis: 2026-04-07*
