# Coding Conventions

**Analysis Date:** 2026-04-07

## Naming Patterns

**Files:**
- Service classes: `{Name}Service.php` in `app/Services/` (e.g., `BallotsService.php`, `VoteEngine.php`)
- Repository classes: `{Name}Repository.php` in `app/Repository/` (e.g., `MotionRepository.php`)
- Controller classes: `{Name}Controller.php` in `app/Controller/` (e.g., `BallotsController.php`)
- Repository trait helpers: `{Action}{Name}Trait.php` in `app/Repository/Traits/` (e.g., `MotionFinderTrait.php`)
- Test files: `{Subject}Test.php` in `tests/Unit/` (e.g., `QuorumEngineTest.php`)

**Classes:**
- PascalCase for all classes: `VoteEngine`, `MailerService`, `MotionRepository`
- All service and repository classes are declared `final`
- Interface suffix if used: `TransportExceptionInterface`

**Methods and Functions:**
- camelCase for all methods: `isConfigured()`, `computeDecision()`, `listForDashboard()`
- Global API helpers use `api_*` prefix: `api_ok()`, `api_fail()`, `api_uuid4()`, `api_require_uuid()`
- Repository lookups follow `findByIdForTenant($id, $tenantId)` pattern (always tenant-scoped)
- Static factory methods: `buildMailerConfig()`, `getInstance()`

**Variables:**
- camelCase: `$toEmail`, `$motionRepo`, `$eligibleWeight`, `$quorumMet`
- Private properties with `private` visibility (not underscore prefix)
- Short-lived loop variables acceptable: `$m`, `$t`, `$key`
- Constants UPPERCASE: `DEFAULT_INVITATION_TEMPLATE`, `LEVELS`, `AVAILABLE_VARIABLES`

**Namespaces:**
- Main: `AgVote\Service`, `AgVote\Repository`, `AgVote\Controller`
- Sub-namespaces: `AgVote\Core\*`, `AgVote\Core\Http\*`, `AgVote\Core\Security\*`, `AgVote\Core\Validation\*`
- Test namespace: `Tests\Unit` (maps to `tests/Unit/` via autoload-dev in `composer.json`)

## Code Style

**Formatting:**
- Tool: PHP-CS-Fixer (`vendor/bin/php-cs-fixer`)
- Config: `.php-cs-fixer.dist.php`
- Target: PSR-12 + PHP 8.2+ migration rules
- Run check: `vendor/bin/php-cs-fixer fix --dry-run --diff`
- Run fix: `vendor/bin/php-cs-fixer fix`

**Required file headers:**
```php
<?php

declare(strict_types=1);

namespace AgVote\Service; // or AgVote\Controller, AgVote\Repository, etc.
```

**Style rules:**
- Short array syntax: `[]` not `array()`
- Single quotes for string literals: `'string'` not `"string"`
- Trailing commas required in multiline arrays, arguments, parameters
- No spaces around array offsets: `$array['key']`
- Braces on same line: `function test() {`
- Return types always explicit: `: void`, `: bool`, `: array`, `: ?string`
- Nullable types use `?Type`: `?MailerService`, `?array`

**Static analysis:**
- Tool: PHPStan Level 5 (`vendor/bin/phpstan analyse`)
- Config: `phpstan.neon`
- Baseline: `phpstan-baseline.neon` (tracks allowed suppressions)

## Import Organization

- One `use` statement per line (no group imports)
- Fully qualified class imports: `use AgVote\Service\VoteEngine;`
- Standard exceptions imported: `use InvalidArgumentException;`, `use RuntimeException;`, `use Throwable;`
- Symfony imports: `use Symfony\Component\Mailer\Mailer;`

## Error Handling

**API response pattern — never call `exit()` or `header()` directly:**
```php
// Success response — throws ApiResponseException internally
api_ok(['items' => $items]);

// Failure response — throws ApiResponseException internally
api_fail('motion_not_found', 404, ['detail' => 'Motion introuvable']);
```

**`ApiResponseException` flow:**
- Defined in `app/Core/Http/ApiResponseException.php`
- `api_ok()` and `api_fail()` always throw it (return type `never`)
- Router catches it and sends the `JsonResponse`
- Controllers must NOT catch `ApiResponseException` themselves

**Error codes:**
- All error codes live in `app/Services/ErrorDictionary.php`
- Codes are snake_case strings: `'unauthorized'`, `'motion_not_found'`, `'vote_token_invalid'`
- French messages returned automatically by `ErrorDictionary::getMessage($code)`
- Always pass code to `api_fail()`, never raw French text: `api_fail('unauthorized', 401)`
- Over 100 codes defined covering auth, meetings, motions, votes, members, proxies, exports

**Exception hierarchy in services:**
- `InvalidArgumentException` — invalid input validation failures
- `RuntimeException` — runtime/operational failures
- `PDOException` — database errors (log + convert to 500 `internal_error`)
- Catch specific exceptions before generic: `catch (TransportExceptionInterface $e)` before `catch (Throwable $e)`

## Logging

**Class:** `AgVote\Core\Logger` (`app/Core/Logger.php`)

**Static methods (PSR-3 inspired):**
```php
Logger::debug('message', ['key' => 'value']);
Logger::info('Vote cast', ['motionId' => $motionId, 'memberId' => $memberId]);
Logger::warning('Rate limit hit', ['ip' => $ip, 'context' => 'auth_login']);
Logger::error('Email send failed', ['error' => $e->getMessage()]);
Logger::critical('message', $context);
Logger::alert('message', $context);
Logger::emergency('message', $context);
```

**When to use each level:**
- `debug` — Implementation details (dev/test only)
- `info` — Major workflow transitions (meeting started, vote cast, member added)
- `warning` — Recoverable issues (rate limit hit, retry needed)
- `error` — Failures affecting functionality but not crashing (send failed, validation error)
- `critical` and above — System-level failures

**Configuration:**
```php
Logger::configure(['file' => '/path/to/log', 'level' => 'error']);
```

## API Helper Functions

Defined in `app/api.php` as global functions. Stubbed identically in `tests/bootstrap.php`.

**Response:**
```php
api_ok(array $data = [], int $code = 200): never      // Always throws ApiResponseException
api_fail(string $error, int $code = 400, array $extra = []): never  // Always throws ApiResponseException
```

**Request parsing:**
```php
api_request(string ...$methods): array   // Validates HTTP method, returns merged GET+body
api_method(): string                     // Returns uppercase HTTP method
api_query(string $key, string $default = ''): string
api_query_int(string $key, int $default = 0): int
api_file(string ...$keys): ?array        // Returns uploaded file from $_FILES
```

**Validation:**
```php
api_is_uuid(string $v): bool
api_require_uuid(array $in, string $key): string   // Throws 400 if missing or invalid UUID
```

**Auth context (read-only, populated by AuthMiddleware):**
```php
api_current_user(): ?array
api_current_user_id(): ?string
api_current_role(): string
api_current_tenant_id(): string
```

**Authorization:**
```php
api_require_role(string|array $roles): void   // Validates CSRF for mutating requests + RBAC check
api_guard_meeting_exists(string $meetingId): array   // 404 if meeting not found for tenant
api_guard_meeting_not_validated(string $meetingId): void  // 409 if meeting is validated/archived
```

## Repository Trait Pattern

Large repositories are split into focused traits in `app/Repository/Traits/`:

```
app/Repository/Traits/
├── MotionFinderTrait.php     # Single-record lookups (findByIdForTenant, findBySlugForTenant)
├── MotionListTrait.php       # Multi-record queries (listForMeeting, listOpen)
├── MotionWriterTrait.php     # Mutations (create, update, open, close, delete)
└── MotionAnalyticsTrait.php  # Aggregations and analytics queries
```

**Usage in repository class:**
```php
final class MotionRepository extends AbstractRepository {
    use MotionFinderTrait;
    use MotionListTrait;
    use MotionWriterTrait;
    use MotionAnalyticsTrait;
}
```

All trait methods delegate to `AbstractRepository` helpers: `selectOne()`, `selectAll()`, `execute()`, `insertReturning()`.

## Function Design

- Target: under 50 lines per method
- Constructor injection preferred: dependencies received via constructor, not method parameters
- Nullable optional parameters for testing: `?RepositoryType $repo = null`
- No more than 5 parameters before refactoring to object
- Always explicit return type (no implicit null)

**Constructor DI pattern:**
```php
final class BallotsService {
    public function __construct(
        private readonly BallotRepository $ballotRepo,
        private readonly MotionRepository $motionRepo,
        private readonly ?AttendancesService $attendancesService = null,
    ) {}
}
```

## Comments

**When to comment:**
- Complex business logic: quorum/majority calculation, weighted voting
- Non-obvious algorithms: document the why
- Public methods and classes: always
- Workarounds: explain why and reference issue if possible

**PHPDoc for array shapes:**
```php
/**
 * @return array{ok: bool, error: ?string, items: list<array>}
 */
```

**Section headers in long files:**
```php
// =========================================================================
// SECTION NAME
// =========================================================================
```

## Module Design

- Services: one concept per service (`VoteEngine` handles voting, `MailerService` handles SMTP)
- Repositories: one table/entity per repository (`MotionRepository` for motions only)
- Controllers: one API resource per controller (`BallotsController` for ballot endpoints)
- Exports via public methods (no static factories except singletons like `RepositoryFactory`)
- No barrel files: use full import paths
- API controllers extend `AbstractController` (`app/Controller/AbstractController.php`)
- HTML-only controllers (login, setup, reset): do NOT extend `AbstractController`, use `HtmlView::render()`

---

*Convention analysis: 2026-04-07*
