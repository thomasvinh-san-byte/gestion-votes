# Codebase Structure

**Analysis Date:** 2026-04-07

## Directory Layout

```
gestion_votes_php/
├── app/                        # Application code (PSR-4: AgVote\)
│   ├── bootstrap.php           # Autoloading, app boot orchestration
│   ├── api.php                 # Global API helpers (api_ok, api_fail, etc.)
│   ├── routes.php              # Centralized route table
│   ├── Controller/             # Request handlers
│   ├── Services/               # Business logic
│   ├── Repository/             # Data access (PDO wrappers)
│   ├── Core/                   # Framework infrastructure
│   ├── Event/                  # Event publishing & listeners
│   ├── View/                   # HTML view rendering
│   ├── Templates/              # HTML templates (public pages)
│   ├── Command/                # CLI commands
│   ├── Helper/                 # Utility functions
│   ├── SSE/                    # Server-sent events
│   └── ...
├── public/                     # Web root
│   ├── index.php               # Front controller
│   ├── *.htmx.html             # HTMX UI pages (admin, operator, etc.)
│   ├── login.html              # Login page
│   ├── vote.php                # Public voting endpoint
│   ├── api/v1/                 # Legacy file-based API routes
│   ├── assets/                 # CSS, JS, fonts
│   ├── exports/                # Generated PDFs, CSVs (writable)
│   └── ...
├── tests/                      # PHPUnit tests
│   ├── bootstrap.php           # Test autoloading
│   └── Unit/                   # Unit tests (one per controller/service)
├── database/                   # Database schema & migration helpers
│   ├── schema-master.sql       # Current schema
│   ├── migrations/             # Migration files
│   ├── seeds/                  # Seed data
│   └── setup.sh                # Database initialization script
├── bin/                        # Executable scripts
│   ├── console                 # CLI entry point
│   ├── test.sh                 # Run tests
│   ├── dev.sh                  # Development server
│   └── ...
├── docs/                       # User documentation
├── .planning/                  # GSD planning documents
│   └── codebase/               # Architecture analysis (this dir)
└── vendor/                     # Composer dependencies
```

## Directory Purposes

**`app/`:**
- Purpose: All application code organized by layer and concern
- Contains: Controllers, services, repositories, core framework, templates
- Key files: `bootstrap.php` (entry point), `routes.php` (route table), `api.php` (global helpers)

**`app/Controller/`:**
- Purpose: HTTP request handlers
- Contains: One class per logical resource/feature (e.g., `MeetingsController`, `BallotsController`)
- Pattern: Each extends `AbstractController`, contains pure business logic, delegates to services/repos
- Examples: `MeetingsController.php`, `AuthController.php`, `VotePublicController.php`

**`app/Services/`:**
- Purpose: Domain business logic and stateless operations
- Contains: Complex workflows combining multiple repositories
- Examples: `BallotsService.php` (voting logic), `MeetingWorkflowService.php` (state transitions), `EmailQueueService.php`
- Pattern: Injected into controllers via constructor, no shared state

**`app/Repository/`:**
- Purpose: Data access abstraction layer
- Contains: Repository classes encapsulating PDO queries
- Pattern: One repository per entity (Ballot, Meeting, User, etc.), extends `AbstractRepository`
- Examples: `BallotRepository.php`, `MeetingRepository.php`, `UserRepository.php`
- Traits: `app/Repository/Traits/` contains reusable query fragments

**`app/Core/`:**
- Purpose: Framework-level infrastructure
- Subdirectories:
  - `Core/Http/` — Request object, response wrappers, exceptions
  - `Core/Middleware/` — Role authorization, rate limiting
  - `Core/Security/` — Authentication, CSRF, session handling
  - `Core/Validation/` — Input validation schemas
  - `Core/Providers/` — Dependency injection factories (Database, Redis, Security, etc.)
- Key files: `Application.php` (boot orchestrator), `Router.php` (route dispatcher)

**`app/Event/`:**
- Purpose: Domain event publishing for async operations
- Contains: Event classes, `SseListener` for real-time updates
- Pattern: Controllers publish events, listeners handle async work (email, notifications)

**`app/View/`:**
- Purpose: HTML template rendering
- Contains: `HtmlView.php` static renderer
- Used by: Public pages (vote.php, public.htmx.html) that return HTML instead of JSON

**`app/Templates/`:**
- Purpose: PHP templates for HTML pages
- Contains: Template files included via `HtmlView::render()`
- Examples: Templates for vote interface, document viewer, etc.

**`public/`:**
- Purpose: Web-accessible directory (document root)
- Contains: Front controller, static assets, generated exports
- Subdirectories:
  - `api/v1/` — Legacy file-based API routes (each .php file instantiates a controller)
  - `assets/` — CSS, JavaScript, fonts, images
  - `exports/` — Generated PDFs, CSVs (writable, world-readable)
  - `partials/` — HTML snippets served to frontend

**`public/api/v1/`:**
- Purpose: Legacy file-based API routing (backward compatibility)
- Pattern: Each endpoint is a .php file that requires `api.php` and instantiates a controller
- Example: `public/api/v1/meetings.php` contains 3 lines:
  ```php
  require __DIR__ . '/../../../app/api.php';
  $c = new MeetingsController();
  match(api_method()) { ... }
  ```
- Migration: New routes should be registered in `app/routes.php` instead, served via front controller

**`tests/`:**
- Purpose: PHPUnit unit tests
- Pattern: One test file per controller/service/repository
- Naming: `{Class}Test.php` (e.g., `MeetingsControllerTest.php`)
- Location: Tests are **colocated** with their subject in subdirectories by type
- Examples: `tests/Unit/MeetingsControllerTest.php`, `tests/Unit/BallotsServiceTest.php`
- Bootstrap: `tests/bootstrap.php` sets up test database and fixtures

**`database/`:**
- Purpose: Schema definition and migration helpers
- Files:
  - `schema-master.sql` — Complete current schema
  - `migrations/` — Individual migration SQL files
  - `seeds/` — Sample data for development
  - `setup.sh` — Database initialization script
- Pattern: Schema managed manually; migrations executed via `setup.sh`

**`bin/`:**
- Purpose: Command-line scripts and entry points
- Files:
  - `console` — CLI entry point (Symfony Console wrapper)
  - `test.sh` — Run PHPUnit
  - `dev.sh` — Start development server
  - `validate-env` — Check environment configuration
- Execution: All scripts use absolute paths to `app/bootstrap.php` for initialization

## Key File Locations

**Entry Points:**
- `public/index.php` — Web front controller (routes HTTP requests)
- `bin/console` — CLI entry point (dispatches to commands in `app/Command/`)
- `public/vote.php` — Public voting interface entry point
- `public/login.html` — Login page (HTML template)

**Configuration & Bootstrap:**
- `app/bootstrap.php` — Initializes autoloading, environment, database
- `app/Application.php` — Boot orchestrator (loads providers, configures security)
- `.env` — Environment variables (database URL, app secret, etc.) **[NOT COMMITTED]**

**Core Logic:**
- `app/routes.php` — Route table with middleware declarations
- `app/api.php` — Global API helpers and middleware chain setup
- `app/Core/Router.php` — Route dispatcher with exact-match + parameterized matching
- `app/Controller/AbstractController.php` — Base class with error handling
- `app/Core/Security/AuthMiddleware.php` — Session validation and RBAC

**Database:**
- `app/Core/Providers/DatabaseProvider.php` — PDO connection management
- `app/Repository/AbstractRepository.php` — Base class for all repositories
- `database/schema-master.sql` — Complete database schema

**Testing:**
- `tests/bootstrap.php` — Test setup (in-memory database, fixtures)
- `tests/Unit/{Class}Test.php` — Unit tests colocated with subject

## Naming Conventions

**Files:**

- Controllers: `{Resource}Controller.php` (e.g., `MeetingsController.php`, `BallotsController.php`)
- Services: `{Domain}Service.php` (e.g., `BallotsService.php`, `EmailQueueService.php`)
- Repositories: `{Entity}Repository.php` (e.g., `MeetingRepository.php`, `BallotRepository.php`)
- Commands: `{Action}Command.php` (e.g., `ProcessEmailQueueCommand.php`)
- Tests: `{Subject}Test.php` (e.g., `MeetingsControllerTest.php`)
- Templates: Lowercase with underscores (e.g., `email_preview.php`)
- API routes: Lowercase with underscores matching endpoint name (e.g., `ballots_cast.php`)

**Directories:**

- Resource folders plural: `Controller/`, `Services/`, `Repository/`
- Feature folders lowercase: `Event/`, `Helper/`, `SSE/`, `Command/`
- Namespaces match directory structure with first-letter uppercase: `AgVote\Controller\`, `AgVote\Service\`

**Classes:**

- PascalCase: `MeetingsController`, `BallotsService`, `MeetingRepository`
- Abstract base classes prefix `Abstract`: `AbstractController`, `AbstractRepository`
- Interfaces suffix `Interface` (rarely used, most patterns duck-typed)
- Constants UPPER_SNAKE_CASE: `DEFAULT_SESSION_TIMEOUT`, `MAX_UPLOAD_SIZE`

**Functions:**

- Global helpers snake_case: `api_ok()`, `api_fail()`, `api_current_user_id()`
- Namespace-qualified helpers in `Helper/` directory: `\AgVote\Helper\Crypto::hash()`

**Database:**

- Tables: Lowercase plural (e.g., `meetings`, `ballots`, `audit_events`)
- Columns: Lowercase snake_case (e.g., `meeting_id`, `created_at`, `is_active`)
- Primary keys: `id` (UUID format for most tables)
- Foreign keys: `{table}_id` (e.g., `meeting_id` in ballots table)
- Timestamps: `created_at`, `updated_at`, `deleted_at` (nullable for soft deletes)

## Where to Add New Code

**New Feature (e.g., voting mechanism change):**
- Primary code: `app/Services/{Feature}Service.php` (business logic)
- Controller: `app/Controller/{Feature}Controller.php` (if new resource) or modify existing
- Routes: Register in `app/routes.php` instead of creating new file in `public/api/v1/`
- Tests: `tests/Unit/{Feature}Test.php`
- Database: New migrations in `database/migrations/`

**New Component/Module (e.g., new user type):**
- Implementation: `app/Services/{Component}Service.php`
- Data access: `app/Repository/{Entity}Repository.php`
- Tests: `tests/Unit/{Component}Test.php`

**New Middleware (e.g., custom authorization):**
- Implementation: `app/Core/Middleware/{Feature}Middleware.php`
- Register: Add to middleware chain in `app/api.php` or route-specific in `app/routes.php`

**New Database Entity:**
- Schema: Add to `database/schema-master.sql`, also create migration in `database/migrations/`
- Repository: Create `app/Repository/{Entity}Repository.php` extending `AbstractRepository`
- Factory: Add accessor in `app/Core/Providers/RepositoryFactory.php`

**Utilities:**
- Global helpers: `app/Helper/{Category}.php` with functions or static methods
- Shared traits: `app/Repository/Traits/{Trait}.php` for reusable query fragments
- Domain logic: Services rather than standalone classes

**Email/Notifications:**
- Template: `app/Templates/emails/{name}.php`
- Queuing: Use `EmailQueueService::queue()` (async via queue processing)
- Listeners: `app/Event/Listener/{Feature}Listener.php` subscribes to domain events

## Special Directories

**`public/exports/`:**
- Purpose: Generated PDF, CSV, XLSX files (world-readable, volatile)
- Generated: On-demand by `ExportController`
- Committed: No (in `.gitignore`)
- Writable: Yes (web server must have write permission)

**`public/assets/`:**
- Purpose: Static assets (CSS, JavaScript, fonts)
- Committed: Yes (part of application)
- Served: Directly by web server, not through PHP

**`.planning/codebase/`:**
- Purpose: GSD analysis documents (ARCHITECTURE.md, STRUCTURE.md, etc.)
- Committed: Yes (reference docs for future phases)
- Generated: By `/gsd:map-codebase` command

**`database/migrations/`:**
- Purpose: SQL migration files (incremental schema changes)
- Pattern: Numbered files (e.g., `001_initial.sql`, `002_add_users.sql`)
- Execution: Via `database/setup.sh`
- Committed: Yes (schema history)

**`tests/fixtures/` (if used):**
- Purpose: Mock data, factories, test doubles
- Pattern: Reusable across test files
- Examples: Sample meeting data, user factories, etc.

## Code Organization Principles

1. **One class per file** — Each PHP file contains exactly one class (PSR-1)
2. **Namespace mirrors directory** — `app/Services/Ballots/VotingService.php` → `AgVote\Service\Ballots\VotingService`
3. **Layers don't skip** — Controllers → Services → Repositories → Database (no direct DB access from controllers)
4. **No circular dependencies** — Repository doesn't depend on Service, Service doesn't import Controller
5. **Public API explicit** — Repository methods represent the public interface for that entity
6. **Testability** — Services accept dependencies via constructor (DI), nullable for tests
7. **Single responsibility** — Controller handles HTTP semantics, Service handles business logic, Repository handles SQL
