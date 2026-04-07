
## Tests — Regles strictes
- TOUJOURS cibler les tests : `php vendor/bin/phpunit tests/Unit/FichierConcerne.php --no-coverage`
- JAMAIS lancer toute la suite de tests sauf demande explicite
- Timeout : `timeout 60 php vendor/bin/phpunit ...`
- Si un test echoue 2 fois de suite, arrete-toi et demande
- Maximum 3 executions de tests par tache

## PHP
- Verifier la syntaxe avant de committer : `php -l fichier.php`
- Respecter les namespaces existants : AgVote\Controller, AgVote\Service, AgVote\Repository

## Git
- Messages de commit en anglais, format : `type(phase-plan): description`
- Ne jamais committer .env ou credentials

## Architecture
- Controllers HTML (login, setup, reset) : NE PAS etendre AbstractController, utiliser HtmlView::render()
- Controllers API : etendre AbstractController
- DI par constructeur avec parametres optionnels nullable pour les tests

## Langue
- Tout le texte visible par l'utilisateur est en francais
- Jamais mentionner "copropriete" ou "syndic" — l'app cible associations et collectivites uniquement

<!-- GSD:project-start source:PROJECT.md -->
## Project

**AgVote — Reduction de la dette technique**

Application web de gestion de votes pour associations et collectivites, construite en PHP 8.4 avec PostgreSQL, Redis, et HTMX. Ce milestone vise a assainir le codebase existant : eliminer les risques de fiabilite en production, refactorer les composants surdimensionnes, et combler les lacunes de tests.

**Core Value:** L'application doit etre fiable en production — aucun crash lie a des fallbacks fichiers, des fuites memoire, ou des timeouts silencieux.

### Constraints

- **Stack**: PHP 8.4, PostgreSQL, Redis (obligatoire apres ce milestone)
- **Namespaces**: AgVote\Controller, AgVote\Service, AgVote\Repository
- **Architecture**: Controllers API etendent AbstractController, controllers HTML utilisent HtmlView::render()
- **DI**: Constructeur avec parametres optionnels nullable pour les tests
- **Langue**: Texte visible en francais, jamais "copropriete" ou "syndic"
- **Compatibilite**: Ne pas casser les APIs existantes
<!-- GSD:project-end -->

<!-- GSD:stack-start source:codebase/STACK.md -->
## Technology Stack

## Languages
- PHP 8.4+ - Application runtime, API handlers, controllers, services
- HTML/CSS - Frontend templates in `public/*.html` (HTMX-based server-driven UI)
- JavaScript - Frontend interactivity and HTMX enhancements in `public/assets/`
- SQL - PostgreSQL schema and migrations in `database/migrations/`
- Bash - DevOps scripts in `bin/` and `database/setup.sh`
## Runtime
- PHP 8.4-fpm (Alpine Linux 3.21 in Docker)
- Node 20.19 (Alpine) - Build-time only, for asset minification (discarded after build)
- Composer - PHP dependency management
- Lockfile: `composer.lock` (present)
## Frameworks
- Custom router: `AgVote\Core\Router` - Lightweight URL routing without external framework dependency
- Symfony components:
- Custom HTML Views - `AgVote\View\HtmlView` - Server-side HTML rendering
- HTMX - Client-side dynamic updates via HTTP requests (no WebSocket)
- PHPUnit ^10.5 - Unit and integration tests in `tests/Unit/`
- PHP-CS-Fixer ^3.0 - Code style fixing (configured in `.php-cs-fixer.dist.php`)
- PHPStan ^2.1 - Static analysis for type checking
## Key Dependencies
- `dompdf/dompdf` ^3.1 - PDF generation for procuration documents (service: `ProcurationPdfService`)
- `phpoffice/phpspreadsheet` ^1.29 - Excel/XLSX export for voting results (service: `ExportService`)
- `erusev/parsedown` ^1.8 - Markdown parsing for email templates and documentation
- `symfony/mailer` ^8.0 - SMTP email delivery (service: `MailerService`) with STARTTLS/TLS
- Redis extension (phpredis) - In-process Redis client for cache, queues, rate limiting, and SSE event broadcast
- PDO with PostgreSQL driver - Database connectivity
- cURL - HTTP requests for webhook notifications and event tracking pixels (service: `MonitoringService`, `MailerService`)
## Configuration
- `.env` file (development/demo mode)
- `.env.example` - Template with documentation
- `.env.production.example` - Production configuration template
- Configuration loaded via `AgVote\Core\Providers\EnvProvider::load()`
- `APP_ENV` - development|demo|production
- `APP_SECRET` - Application secret (32-byte hex)
- `DB_DSN`, `DB_USER`, `DB_PASS` - PostgreSQL connection
- `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS` - SMTP server
- `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` - Redis connection (optional, with filesystem fallback)
- `API_KEY_ADMIN`, `API_KEY_OPERATOR`, `API_KEY_TRUST` - API key authentication (optional)
- `CORS_ALLOWED_ORIGINS` - Comma-separated origins for CORS
- `AGVOTE_UPLOAD_DIR` - File upload directory (persistent volume)
- `Dockerfile` - Multi-stage build (assets minification → runtime)
- `docker-compose.yml` (not in codebase, external)
- `.htaccess` - Apache URL rewriting (legacy fallback, router is primary)
## Platform Requirements
- PHP 8.4+ CLI
- Composer
- PostgreSQL 12+ (local or remote)
- Redis (optional, filesystem fallback for SSE)
- Docker/Docker Compose (optional)
- Docker container or PHP 8.4-FPM + Nginx
- PostgreSQL 12+ with SSL support (`sslmode=require`)
- Redis (recommended for performance, optional with filesystem fallback)
- Minimum 512MB RAM, 1GB free disk for uploads
- pdo_pgsql - Database connectivity
- pgsql - PostgreSQL client functions
- gd - Image processing (for email tracking pixels)
- zip - Archive support (xlsx export)
- intl - Internationalization
- mbstring - Multibyte string handling
- redis - Redis client (optional, gracefully disabled if not available)
- Zend OPcache - Performance optimization
<!-- GSD:stack-end -->

<!-- GSD:conventions-start source:CONVENTIONS.md -->
## Conventions

## Naming Patterns
- Service classes: `{Name}Service.php` (e.g., `MailerService.php`, `VoteEngine.php`, `QuorumEngine.php`) in `app/Services/`
- Repository classes: `{Name}Repository.php` (e.g., `MotionRepository.php`, `BallotRepository.php`) in `app/Repository/`
- Controller classes: `{Name}Controller.php` (e.g., `DashboardController.php`, `BallotsController.php`) in `app/Controller/`
- Repository trait helpers: `{Action}{Name}Trait.php` (e.g., `MotionFinderTrait.php`, `MotionWriterTrait.php`) in `app/Repository/Traits/`
- Test files: `{Subject}Test.php` matching the class they test (e.g., `QuorumEngineTest.php`, `EmailTemplateServiceTest.php`) in `tests/Unit/`
- PascalCase: `VoteEngine`, `MailerService`, `MotionRepository`
- Final classes: Prefix with `final` keyword (service and repository classes are final)
- Interfaces: `Interface` suffix if used (e.g., `TransportExceptionInterface`)
- camelCase: `isConfigured()`, `computeDecision()`, `listForDashboard()`, `findByIdForTenant()`
- Private utility functions: prefix with underscore or `private` visibility (e.g., `private function sanitizeEmail()`)
- API helper functions (global): `api_*` pattern (e.g., `api_ok()`, `api_fail()`, `api_uuid4()`, `api_current_user_id()`)
- Static factory methods: `buildMailerConfig()`, `getInstance()`
- camelCase: `$toEmail`, `$motionRepo`, `$eligibleWeight`, `$quorumMet`
- Private properties: `private` visibility with camelCase (e.g., `private array $smtp`, `private ?Mailer $mailer`)
- Constants in services: UPPERCASE (e.g., `DEFAULT_INVITATION_TEMPLATE`, `LEVELS`, `AVAILABLE_VARIABLES`)
- Short-lived loop variables: `$m`, `$t`, `$key` (acceptable in local scope)
- Main: `AgVote\Service`, `AgVote\Repository`, `AgVote\Controller`
- Sub-namespaces: `AgVote\Core\*`, `AgVote\Core\Http\*`, `AgVote\Core\Security\*`, `AgVote\Core\Validation\*`
- Test namespace: `Tests\Unit` (must match autoload-dev in `composer.json`)
## Code Style
- Tool: PHP-CS-Fixer (configured in `.php-cs-fixer.dist.php`)
- Target: PSR-12 + PHP 8.2+ migration rules
- Run: `vendor/bin/php-cs-fixer fix --dry-run --diff` (check mode), `vendor/bin/php-cs-fixer fix` (apply)
- Array syntax: Short syntax `[]` (not `array()`)
- Trailing commas: Required in multiline arrays, arguments, parameters
- Line endings: Single blank line at EOF
- String quotes: Single quotes for string literals (`'string'`, not `"string"`)
- Strict types: `declare(strict_types=1);` required at top of every file
- Return types: Always include (e.g., `: void`, `: bool`, `: array`, `: ?Mailer`)
- Nullable types: Use `?Type` (e.g., `?MailerService`, `?array`)
- Union types (PHP 8+): Supported (e.g., `string|array`, `int|float`)
- Type hints in PHPDoc: For complex returns `@return array{key: type, ...}` (shape syntax)
- Braces position: Same line for functions/classes (`function test() {`)
- No extra blank lines around use statements
- No spaces around array offsets: `$array['key']`, not `$array[ 'key' ]`
- Single space around binary operators: `$a = $b + $c`, `$a && $b`
- No whitespace before commas in arrays: `[1, 2, 3]`
- One space after comma in arrays: `[1, 2, 3]`
- Tool: PHPStan Level 5 (configured in `phpstan.neon`)
- Run: `vendor/bin/phpstan analyse` (requires full analysis)
- Global functions stubs: PHPStan ignores known global helper functions (api_*, audit_log, config, db)
- Baseline: `phpstan-baseline.neon` tracks allowed issues
## Import Organization
- Classes imported fully qualified: `use AgVote\Service\VoteEngine;`
- Standard library exceptions imported: `use InvalidArgumentException;`, `use RuntimeException;`, `use Throwable;`
- Symfony imports: `use Symfony\Component\Mailer\Mailer;`, `use Symfony\Component\Mime\Email;`
- No group use imports (each on own line)
## Error Handling
- Standard library exceptions for validation: `InvalidArgumentException` (invalid input), `RuntimeException` (runtime failure)
- Custom exception: `ApiResponseException` in `app/Core/Http/ApiResponseException.php` for carrying JSON responses through exception flow
- Controllers throw `ApiResponseException` via `api_ok()` and `api_fail()` helpers (never call `exit()` or `header()` directly)
- Services throw standard exceptions; controllers catch and convert to API responses
- Centralized French error codes in `app/Services/ErrorDictionary.php`
- Format: error_code => French message (e.g., `'unauthorized'` => `'Vous devez être connecté...'`)
- Used by controllers and services to return consistent error messages
- Always pass error code to `api_fail()`, not raw message: `api_fail('unauthorized', 401)`
- Catch specific exceptions first: `catch (TransportExceptionInterface $e)` before `catch (Throwable $e)`
- Log context when catching: pass context array to Logger methods
- Re-throw or convert to user-facing errors: `catch (Throwable $e) { return ['ok' => false, 'error' => 'code: ' . $e->getMessage()]; }`
- Database exceptions: Catch `PDOException` for database failures
## Logging
- Static methods: `Logger::debug()`, `Logger::info()`, `Logger::notice()`, `Logger::warning()`, `Logger::error()`, `Logger::critical()`, `Logger::alert()`, `Logger::emergency()`
- Context array optional: `Logger::info('message', ['key' => 'value', 'userId' => $userId])`
- Configuration: `Logger::configure(['file' => '/path/to/log', 'level' => 'error'])`
- Info: Major workflow transitions (meeting started, vote cast, member added)
- Warning: Recoverable issues (rate limit hit, retry needed)
- Error: Failures that affect functionality but don't crash (send failed, validation error)
- Debug: Implementation details (only in dev/test)
## Comments
- Complex business logic: Quorum/majority calculation, weighted voting
- Non-obvious algorithms: Why a particular approach is used
- Public API docs: Always document public methods and classes
- Workarounds: Document why a workaround exists and link to issue
- Required: Public classes, public methods, return types with complex shapes
- Format: Start with short description (one line), follow with blank line if more detail needed
- Type hints in comments: `@return array{key: type, ...}` for array shapes, `@param Type $var Description`
- Example:
## Function Design
- Target: Under 50 lines (exceptions for complex business logic with good structure)
- Measure: readability first, not strict limits
- Split large functions: Extract helper methods (private or public as needed)
- Constructor injection preferred: Services receive dependencies via constructor, not as parameters
- Nullable optional parameters for testing: `?RepositoryType $repo = null` allows tests to inject mocks
- No more than 5 parameters before considering refactoring to object
- Explicit return type always (no implicit null)
- Array returns documented in PHPDoc: `@return array{ok: bool, error: ?string, debug?: array}`
- Shape array example: Mixed types grouped (strings, bools, arrays) following logical grouping
## Module Design
- Services export via public methods (no static factories except singletons)
- Repositories export via public methods (queries, mutations)
- Controllers export via public handler methods matching HTTP verb
- No barrel files (no `index.php` re-exporting; use full paths)
- Public: User-facing methods, API endpoints
- Private: Helper methods, internal calculations
- Protected: Rare (used in base classes like AbstractRepository, AbstractController)
- Final: Services, repositories, controllers (prevent accidental extension)
- Service = one concept (VoteEngine handles voting logic, MailerService handles SMTP)
- Repository = one table/entity (MotionRepository for motions, BallotRepository for ballots)
- Controller = one API resource or HTML page (BallotsController for ballot endpoints)
- Use traits for organizing large repositories: MotionFinderTrait, MotionWriterTrait, MotionListTrait, MotionAnalyticsTrait
<!-- GSD:conventions-end -->

<!-- GSD:architecture-start source:ARCHITECTURE.md -->
## Architecture

## Pattern Overview
- Request routing via `Router` class with exact-match and parameterized routes
- Controller-based request handling with centralized error handling in `AbstractController`
- Service layer (repositories + domain services) for business logic
- Dependency injection through `RepositoryFactory` singleton
- Two entry points: front controller (`index.php`) with router fallback to legacy file-based routing
- Multi-tenant architecture with session-based role-based access control (RBAC)
- Event-driven architecture with `EventDispatcher` for SSE and notifications
## Layers
- Purpose: Handle HTTP requests and format responses (JSON APIs or HTML)
- Location: `app/Controller/`, `public/api/v1/`
- Contains: Controller classes extending `AbstractController`, view renderers (`HtmlView`)
- Depends on: Services, Repositories, Core utilities
- Used by: Router, front controller
- Purpose: Global HTTP helpers (request parsing, response formatting, auth context)
- Location: `app/api.php`, `app/Core/Http/`
- Contains: Helper functions (`api_ok()`, `api_fail()`, `api_query()`), `Request` class, `JsonResponse`
- Depends on: AuthMiddleware, database connection
- Used by: Controllers, middleware
- Purpose: Request validation, authentication, authorization, rate limiting
- Location: `app/Core/Middleware/`, `app/Core/Security/`
- Contains: `AuthMiddleware` (session validation + RBAC), `RoleMiddleware`, `RateLimitGuard`, `CsrfMiddleware`, `SecurityProvider`
- Depends on: Repository layer for user/session lookup
- Used by: Router, SecurityProvider
- Purpose: Business logic for complex operations (voting, meetings, exports, notifications)
- Location: `app/Services/`
- Contains: Services like `BallotsService`, `MeetingWorkflowService`, `EmailQueueService`, `QuorumEngine`
- Depends on: Repositories, helpers
- Used by: Controllers
- Purpose: Encapsulate all database access with typed query methods
- Location: `app/Repository/`
- Contains: Repository classes extending `AbstractRepository` (e.g., `MeetingRepository`, `UserRepository`, `BallotRepository`)
- Depends on: PDO database connection
- Used by: Services, Controllers
- Purpose: Framework-level utilities (routing, providers, validation, logging)
- Location: `app/Core/`
- Contains: `Application` (boot orchestrator), `Router`, `Logger`, `DatabaseProvider`, `Providers/` (env, redis, security config)
- Depends on: Composer dependencies (Symfony Mailer, DOMPDF)
- Used by: All layers
- Purpose: Initialize the application and configure dependencies
- Location: `app/bootstrap.php`, `app/Application.php`, `app/Providers/`
- Contains: Autoloading, env loading, database connection setup, security headers
- Depends on: Composer autoloader
- Used by: Front controller
## Data Flow
- **Session state** → Redis (configured via `RedisProvider`) or database fallback
- **Request state** → Global variables populated by `AuthMiddleware` (current user, tenant, role)
- **Meeting workflow state** → Stored in `meetings.status` (setup, running, closed, validated, archived)
- **Vote state** → Atomic ballot records in `ballots` table with idempotency guards
- **Audit trail** → All modifications logged to `audit_events` table via `audit_log()` function
## Key Abstractions
- Purpose: Declarative route table with middleware config
- Examples: `app/routes.php`, `app/Core/Router.php`
- Pattern: Routes registered with `$router->map()` or `$router->mapAny()`, dispatch via `dispatch($method, $uri)`
- Purpose: Lazy-instantiate and cache repository instances per request
- Examples: `$this->repo()->meeting()->findByIdForTenant($id, $tenantId)`
- Pattern: Singleton factory with per-request cache, injectable for testing
- Purpose: Encapsulate PDO usage and provide common query helpers
- Examples: `selectOne()`, `selectAll()`, `execute()`, `insertReturning()`
- Pattern: Each entity type has a typed repository (e.g., `MeetingRepository` only exposes meeting-related queries)
- Purpose: Stateless business logic combining multiple repositories
- Examples: `BallotsService::cast()` validates quorum, checks proxies, updates ballot state, publishes event
- Pattern: Injected via constructor with nullable parameters for testing
- Purpose: Wrap method execution in error handling and request validation
- Examples: All controllers extend this, call `$this->repo()` for data access
- Pattern: `handle()` catches exceptions and converts to API responses
- Purpose: Stateful session validation with two-level RBAC (system roles + meeting roles)
- Pattern: Validates session from Redis/DB, populates `$_SESSION`, sets static context for `api_current_user_id()` etc.
- Purpose: Publish domain events for async processing (email, notifications, SSE)
- Examples: `SseListener` for real-time updates, email queue listener
- Pattern: Symfony `EventDispatcher`, listeners registered in `Application::initEventDispatcher()`
## Entry Points
- Location: `public/index.php`
- Triggers: HTTP requests to `/api/v1/...` or legacy `/api/v1/files.php`
- Responsibilities: 
- Location: `bin/console` → `app/Command/`
- Triggers: Manual execution via shell
- Responsibilities: Execute long-running tasks (email queue processing, cleanup)
- Uses: `Application::bootCli()` for database access without HTTP security headers
- Location: `public/*.htmx.html`, `app/Templates/`
- Triggers: Direct browser navigation
- Responsibilities: Render HTML with embedded JavaScript for HTMX interactions
- Uses: `HtmlView::render()` for template rendering
- Location: `public/api/v1/email_*.php`
- Triggers: Image tags and links in email
- Responsibilities: Track email opens and clicks without session auth
- Uses: Bootstrap routes that skip API middleware
## Error Handling
- **API Exceptions**: `ApiResponseException` thrown by validation → Router catches and sends response
- **Business Errors**: `RuntimeException` from services → converted to 400 `business_error` response
- **Invalid Input**: `InvalidArgumentException` from validation → converted to 422 `invalid_request` response
- **Database Errors**: `PDOException` → logged, converted to 500 `internal_error` response with generic message
- **Uncaught Exceptions**: `Throwable` → logged with full stack trace, converted to 500 response
- **Rate Limit Exceeded**: `RateLimitGuard` throws `ApiResponseException` with 429 status
```json
```
## Cross-Cutting Concerns
- Global function `audit_log()` for all business-relevant events (user actions, state changes)
- Stored in `audit_events` table with tenant, user, action, resource type, and payload
- File logging via `error_log()` for technical errors (stack traces in `php error.log`)
- Request ID tracking via `Logger::getRequestId()` for correlation
- Input validation via global helpers: `api_require_uuid()`, `api_query_int()`, etc.
- Request body validation via `Request::validate()` method
- Schema validation via `app/Core/Validation/Schemas/` classes
- Field-level validation in Services (e.g., `MeetingValidator::validate()`)
- Session-based via `AuthMiddleware` (reads from `$_SESSION`)
- Session timeout from tenant settings or environment
- Re-validation interval: 60 seconds (user could be disabled mid-session)
- Token-based for public voting via `VoteToken` (HMAC signed)
- API call context available via `api_current_user_id()`, `api_current_tenant_id()`, `api_current_role()`
- System roles: `admin`, `operator`, `auditor`, `viewer`, `president`
- Meeting roles: `president`, `assessor`, `voter`
- Middleware config keys in routes: `'role' => 'admin'` or `'role' => ['operator', 'admin']`
- `RoleMiddleware` checks effective permissions combining system + meeting roles
- `CsrfMiddleware` validates CSRF tokens via `X-Csrf-Token` header or form field
- Tokens generated and stored per session
- Token rotation on each validation
- Per-context windows (auth_login, admin_ops, public_vote, etc.)
- Redis-backed counters with sliding window
- Configurable per-route via middleware config: `'rate_limit' => [ctx, max, window]`
- Helper function `api_transaction()` wraps callable in `PDO::beginTransaction()` / `commit()` / `rollback()`
- Used for multi-table operations requiring atomicity (e.g., closing a vote)
- `IdempotencyGuard` class for operations that must be idempotent
- Uses request fingerprint (method, URI, body hash) to detect retries
- Stored in database with TTL
<!-- GSD:architecture-end -->

<!-- GSD:workflow-start source:GSD defaults -->
## GSD Workflow Enforcement

Before using Edit, Write, or other file-changing tools, start work through a GSD command so planning artifacts and execution context stay in sync.

Use these entry points:
- `/gsd:quick` for small fixes, doc updates, and ad-hoc tasks
- `/gsd:debug` for investigation and bug fixing
- `/gsd:execute-phase` for planned phase work

Do not make direct repo edits outside a GSD workflow unless the user explicitly asks to bypass it.
<!-- GSD:workflow-end -->

<!-- GSD:profile-start -->
## Developer Profile

> Profile not yet configured. Run `/gsd:profile-user` to generate your developer profile.
> This section is managed by `generate-claude-profile` -- do not edit manually.
<!-- GSD:profile-end -->
