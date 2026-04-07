# Architecture

**Analysis Date:** 2026-04-07

## Pattern Overview

**Overall:** Layered MVC with centralized routing and dependency injection via service factories.

**Key Characteristics:**
- Request routing via `Router` class with exact-match and parameterized routes
- Controller-based request handling with centralized error handling in `AbstractController`
- Service layer (repositories + domain services) for business logic
- Dependency injection through `RepositoryFactory` singleton
- Two entry points: front controller (`index.php`) with router fallback to legacy file-based routing
- Multi-tenant architecture with session-based role-based access control (RBAC)
- Event-driven architecture with `EventDispatcher` for SSE and notifications

## Layers

**Presentation Layer:**
- Purpose: Handle HTTP requests and format responses (JSON APIs or HTML)
- Location: `app/Controller/`, `public/api/v1/`
- Contains: Controller classes extending `AbstractController`, view renderers (`HtmlView`)
- Depends on: Services, Repositories, Core utilities
- Used by: Router, front controller

**API Request/Response Layer:**
- Purpose: Global HTTP helpers (request parsing, response formatting, auth context)
- Location: `app/api.php`, `app/Core/Http/`
- Contains: Helper functions (`api_ok()`, `api_fail()`, `api_query()`), `Request` class, `JsonResponse`
- Depends on: AuthMiddleware, database connection
- Used by: Controllers, middleware

**Middleware & Security Layer:**
- Purpose: Request validation, authentication, authorization, rate limiting
- Location: `app/Core/Middleware/`, `app/Core/Security/`
- Contains: `AuthMiddleware` (session validation + RBAC), `RoleMiddleware`, `RateLimitGuard`, `CsrfMiddleware`, `SecurityProvider`
- Depends on: Repository layer for user/session lookup
- Used by: Router, SecurityProvider

**Service/Domain Logic Layer:**
- Purpose: Business logic for complex operations (voting, meetings, exports, notifications)
- Location: `app/Services/`
- Contains: Services like `BallotsService`, `MeetingWorkflowService`, `EmailQueueService`, `QuorumEngine`
- Depends on: Repositories, helpers
- Used by: Controllers

**Repository/Data Access Layer:**
- Purpose: Encapsulate all database access with typed query methods
- Location: `app/Repository/`
- Contains: Repository classes extending `AbstractRepository` (e.g., `MeetingRepository`, `UserRepository`, `BallotRepository`)
- Depends on: PDO database connection
- Used by: Services, Controllers

**Core Infrastructure Layer:**
- Purpose: Framework-level utilities (routing, providers, validation, logging)
- Location: `app/Core/`
- Contains: `Application` (boot orchestrator), `Router`, `Logger`, `DatabaseProvider`, `Providers/` (env, redis, security config)
- Depends on: Composer dependencies (Symfony Mailer, DOMPDF)
- Used by: All layers

**Bootstrap & Configuration:**
- Purpose: Initialize the application and configure dependencies
- Location: `app/bootstrap.php`, `app/Application.php`, `app/Providers/`
- Contains: Autoloading, env loading, database connection setup, security headers
- Depends on: Composer autoloader
- Used by: Front controller

## Data Flow

**Typical API Request Flow:**

1. **Request arrives** → `public/index.php` (front controller)
2. **Route resolution** → `Router::dispatch()` with exact-match then parameterized route matching
3. **Middleware chain** → `AuthMiddleware` validates session, `RoleMiddleware` checks permissions, `RateLimitGuard` enforces limits
4. **Controller instantiation** → Matched controller class is instantiated
5. **Request handling** → `AbstractController::handle()` wraps method execution in error handling
6. **Controller method execution** → Business logic with request validation via `api_request()`, `api_query()` helpers
7. **Service layer calls** → Controller invokes services for complex operations (e.g., `BallotsService::cast()`)
8. **Repository calls** → Services/Controllers query data via `RepositoryFactory::getInstance()->meeting()->find()`
9. **Database access** → Repositories execute prepared SQL statements via PDO
10. **Response formatting** → `api_ok()` or `api_fail()` sends JSON response
11. **Error handling** → `AbstractController::handle()` catches exceptions and converts to appropriate HTTP responses

**Public Vote Flow (HTML endpoint):**

1. Request to `/vote.php` or `/public.htmx.html`
2. HTML template rendered via `HtmlView::render()` from `app/Templates/`
3. Inline JavaScript makes API calls to `/api/v1/...` endpoints
4. Vote tokens validated via `VoteTokenRepository`
5. Ballots cast via `BallotsController::cast()` → `BallotsService::cast()`

**Email & Event Flow:**

1. Async event publishing via `EventDispatcher`
2. Email queued to `EmailQueueRepository`
3. Backend cron processes queue via `EmailQueueService`
4. SSE listeners (`SseListener`) push updates to connected clients

**State Management:**

- **Session state** → Redis (configured via `RedisProvider`) or database fallback
- **Request state** → Global variables populated by `AuthMiddleware` (current user, tenant, role)
- **Meeting workflow state** → Stored in `meetings.status` (setup, running, closed, validated, archived)
- **Vote state** → Atomic ballot records in `ballots` table with idempotency guards
- **Audit trail** → All modifications logged to `audit_events` table via `audit_log()` function

## Key Abstractions

**Router:**
- Purpose: Declarative route table with middleware config
- Examples: `app/routes.php`, `app/Core/Router.php`
- Pattern: Routes registered with `$router->map()` or `$router->mapAny()`, dispatch via `dispatch($method, $uri)`

**RepositoryFactory:**
- Purpose: Lazy-instantiate and cache repository instances per request
- Examples: `$this->repo()->meeting()->findByIdForTenant($id, $tenantId)`
- Pattern: Singleton factory with per-request cache, injectable for testing

**AbstractRepository:**
- Purpose: Encapsulate PDO usage and provide common query helpers
- Examples: `selectOne()`, `selectAll()`, `execute()`, `insertReturning()`
- Pattern: Each entity type has a typed repository (e.g., `MeetingRepository` only exposes meeting-related queries)

**Service Classes:**
- Purpose: Stateless business logic combining multiple repositories
- Examples: `BallotsService::cast()` validates quorum, checks proxies, updates ballot state, publishes event
- Pattern: Injected via constructor with nullable parameters for testing

**AbstractController:**
- Purpose: Wrap method execution in error handling and request validation
- Examples: All controllers extend this, call `$this->repo()` for data access
- Pattern: `handle()` catches exceptions and converts to API responses

**AuthMiddleware:**
- Purpose: Stateful session validation with two-level RBAC (system roles + meeting roles)
- Pattern: Validates session from Redis/DB, populates `$_SESSION`, sets static context for `api_current_user_id()` etc.

**EventDispatcher:**
- Purpose: Publish domain events for async processing (email, notifications, SSE)
- Examples: `SseListener` for real-time updates, email queue listener
- Pattern: Symfony `EventDispatcher`, listeners registered in `Application::initEventDispatcher()`

## Entry Points

**Web API (`public/index.php`):**
- Location: `public/index.php`
- Triggers: HTTP requests to `/api/v1/...` or legacy `/api/v1/files.php`
- Responsibilities: 
  - Resolve request URI and method
  - Load Router and configure routes
  - Determine if request needs full API layer (middleware) or just bootstrap
  - Dispatch via Router or fallback to file-based routing
  - Handle 404 responses

**CLI Commands (`bin/console`):**
- Location: `bin/console` → `app/Command/`
- Triggers: Manual execution via shell
- Responsibilities: Execute long-running tasks (email queue processing, cleanup)
- Uses: `Application::bootCli()` for database access without HTTP security headers

**Public HTML Templates:**
- Location: `public/*.htmx.html`, `app/Templates/`
- Triggers: Direct browser navigation
- Responsibilities: Render HTML with embedded JavaScript for HTMX interactions
- Uses: `HtmlView::render()` for template rendering

**Email Tracking (`/api/v1/email_pixel`, `/api/v1/email_redirect`):**
- Location: `public/api/v1/email_*.php`
- Triggers: Image tags and links in email
- Responsibilities: Track email opens and clicks without session auth
- Uses: Bootstrap routes that skip API middleware

## Error Handling

**Strategy:** Centralized exception-to-response mapping in `AbstractController::handle()`.

**Patterns:**

- **API Exceptions**: `ApiResponseException` thrown by validation → Router catches and sends response
- **Business Errors**: `RuntimeException` from services → converted to 400 `business_error` response
- **Invalid Input**: `InvalidArgumentException` from validation → converted to 422 `invalid_request` response
- **Database Errors**: `PDOException` → logged, converted to 500 `internal_error` response with generic message
- **Uncaught Exceptions**: `Throwable` → logged with full stack trace, converted to 500 response
- **Rate Limit Exceeded**: `RateLimitGuard` throws `ApiResponseException` with 429 status

**Error Response Format**:
```json
{
  "ok": false,
  "error": "error_code",
  "detail": "Human-readable message",
  "extra": {}
}
```

## Cross-Cutting Concerns

**Logging:**
- Global function `audit_log()` for all business-relevant events (user actions, state changes)
- Stored in `audit_events` table with tenant, user, action, resource type, and payload
- File logging via `error_log()` for technical errors (stack traces in `php error.log`)
- Request ID tracking via `Logger::getRequestId()` for correlation

**Validation:**
- Input validation via global helpers: `api_require_uuid()`, `api_query_int()`, etc.
- Request body validation via `Request::validate()` method
- Schema validation via `app/Core/Validation/Schemas/` classes
- Field-level validation in Services (e.g., `MeetingValidator::validate()`)

**Authentication:**
- Session-based via `AuthMiddleware` (reads from `$_SESSION`)
- Session timeout from tenant settings or environment
- Re-validation interval: 60 seconds (user could be disabled mid-session)
- Token-based for public voting via `VoteToken` (HMAC signed)
- API call context available via `api_current_user_id()`, `api_current_tenant_id()`, `api_current_role()`

**Authorization:**
- System roles: `admin`, `operator`, `auditor`, `viewer`, `president`
- Meeting roles: `president`, `assessor`, `voter`
- Middleware config keys in routes: `'role' => 'admin'` or `'role' => ['operator', 'admin']`
- `RoleMiddleware` checks effective permissions combining system + meeting roles

**CSRF Protection:**
- `CsrfMiddleware` validates CSRF tokens via `X-Csrf-Token` header or form field
- Tokens generated and stored per session
- Token rotation on each validation

**Rate Limiting:**
- Per-context windows (auth_login, admin_ops, public_vote, etc.)
- Redis-backed counters with sliding window
- Configurable per-route via middleware config: `'rate_limit' => [ctx, max, window]`

**Database Transactions:**
- Helper function `api_transaction()` wraps callable in `PDO::beginTransaction()` / `commit()` / `rollback()`
- Used for multi-table operations requiring atomicity (e.g., closing a vote)

**Idempotency:**
- `IdempotencyGuard` class for operations that must be idempotent
- Uses request fingerprint (method, URI, body hash) to detect retries
- Stored in database with TTL
