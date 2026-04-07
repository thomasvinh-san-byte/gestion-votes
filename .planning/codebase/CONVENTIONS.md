# Coding Conventions

**Analysis Date:** 2026-04-07

## Naming Patterns

**Files:**
- Service classes: `{Name}Service.php` (e.g., `MailerService.php`, `VoteEngine.php`, `QuorumEngine.php`) in `app/Services/`
- Repository classes: `{Name}Repository.php` (e.g., `MotionRepository.php`, `BallotRepository.php`) in `app/Repository/`
- Controller classes: `{Name}Controller.php` (e.g., `DashboardController.php`, `BallotsController.php`) in `app/Controller/`
- Repository trait helpers: `{Action}{Name}Trait.php` (e.g., `MotionFinderTrait.php`, `MotionWriterTrait.php`) in `app/Repository/Traits/`
- Test files: `{Subject}Test.php` matching the class they test (e.g., `QuorumEngineTest.php`, `EmailTemplateServiceTest.php`) in `tests/Unit/`

**Classes & Types:**
- PascalCase: `VoteEngine`, `MailerService`, `MotionRepository`
- Final classes: Prefix with `final` keyword (service and repository classes are final)
- Interfaces: `Interface` suffix if used (e.g., `TransportExceptionInterface`)

**Functions:**
- camelCase: `isConfigured()`, `computeDecision()`, `listForDashboard()`, `findByIdForTenant()`
- Private utility functions: prefix with underscore or `private` visibility (e.g., `private function sanitizeEmail()`)
- API helper functions (global): `api_*` pattern (e.g., `api_ok()`, `api_fail()`, `api_uuid4()`, `api_current_user_id()`)
- Static factory methods: `buildMailerConfig()`, `getInstance()`

**Variables & Properties:**
- camelCase: `$toEmail`, `$motionRepo`, `$eligibleWeight`, `$quorumMet`
- Private properties: `private` visibility with camelCase (e.g., `private array $smtp`, `private ?Mailer $mailer`)
- Constants in services: UPPERCASE (e.g., `DEFAULT_INVITATION_TEMPLATE`, `LEVELS`, `AVAILABLE_VARIABLES`)
- Short-lived loop variables: `$m`, `$t`, `$key` (acceptable in local scope)

**Namespaces:**
- Main: `AgVote\Service`, `AgVote\Repository`, `AgVote\Controller`
- Sub-namespaces: `AgVote\Core\*`, `AgVote\Core\Http\*`, `AgVote\Core\Security\*`, `AgVote\Core\Validation\*`
- Test namespace: `Tests\Unit` (must match autoload-dev in `composer.json`)

## Code Style

**Formatting:**
- Tool: PHP-CS-Fixer (configured in `.php-cs-fixer.dist.php`)
- Target: PSR-12 + PHP 8.2+ migration rules
- Run: `vendor/bin/php-cs-fixer fix --dry-run --diff` (check mode), `vendor/bin/php-cs-fixer fix` (apply)
- Array syntax: Short syntax `[]` (not `array()`)
- Trailing commas: Required in multiline arrays, arguments, parameters
- Line endings: Single blank line at EOF
- String quotes: Single quotes for string literals (`'string'`, not `"string"`)

**Type Declarations:**
- Strict types: `declare(strict_types=1);` required at top of every file
- Return types: Always include (e.g., `: void`, `: bool`, `: array`, `: ?Mailer`)
- Nullable types: Use `?Type` (e.g., `?MailerService`, `?array`)
- Union types (PHP 8+): Supported (e.g., `string|array`, `int|float`)
- Type hints in PHPDoc: For complex returns `@return array{key: type, ...}` (shape syntax)

**Spacing:**
- Braces position: Same line for functions/classes (`function test() {`)
- No extra blank lines around use statements
- No spaces around array offsets: `$array['key']`, not `$array[ 'key' ]`
- Single space around binary operators: `$a = $b + $c`, `$a && $b`
- No whitespace before commas in arrays: `[1, 2, 3]`
- One space after comma in arrays: `[1, 2, 3]`

**Linting:**
- Tool: PHPStan Level 5 (configured in `phpstan.neon`)
- Run: `vendor/bin/phpstan analyse` (requires full analysis)
- Global functions stubs: PHPStan ignores known global helper functions (api_*, audit_log, config, db)
- Baseline: `phpstan-baseline.neon` tracks allowed issues

## Import Organization

**Order:**
1. `use` statements for classes (alphabetically)
2. `use` statements for functions (if any)
3. `use` statements for constants (if any)

**Path Aliases:**
- Classes imported fully qualified: `use AgVote\Service\VoteEngine;`
- Standard library exceptions imported: `use InvalidArgumentException;`, `use RuntimeException;`, `use Throwable;`
- Symfony imports: `use Symfony\Component\Mailer\Mailer;`, `use Symfony\Component\Mime\Email;`
- No group use imports (each on own line)

**Example:**
```php
<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Core\Providers\RepositoryFactory;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\BallotRepository;
use InvalidArgumentException;
use RuntimeException;
```

## Error Handling

**Exceptions:**
- Standard library exceptions for validation: `InvalidArgumentException` (invalid input), `RuntimeException` (runtime failure)
- Custom exception: `ApiResponseException` in `app/Core/Http/ApiResponseException.php` for carrying JSON responses through exception flow
- Controllers throw `ApiResponseException` via `api_ok()` and `api_fail()` helpers (never call `exit()` or `header()` directly)
- Services throw standard exceptions; controllers catch and convert to API responses

**Error Dictionary:**
- Centralized French error codes in `app/Services/ErrorDictionary.php`
- Format: error_code => French message (e.g., `'unauthorized'` => `'Vous devez être connecté...'`)
- Used by controllers and services to return consistent error messages
- Always pass error code to `api_fail()`, not raw message: `api_fail('unauthorized', 401)`

**Try/Catch Pattern:**
- Catch specific exceptions first: `catch (TransportExceptionInterface $e)` before `catch (Throwable $e)`
- Log context when catching: pass context array to Logger methods
- Re-throw or convert to user-facing errors: `catch (Throwable $e) { return ['ok' => false, 'error' => 'code: ' . $e->getMessage()]; }`
- Database exceptions: Catch `PDOException` for database failures

## Logging

**Framework:** Logger class in `app/Core/Logger.php` (PSR-3 compatible)

**Methods:**
- Static methods: `Logger::debug()`, `Logger::info()`, `Logger::notice()`, `Logger::warning()`, `Logger::error()`, `Logger::critical()`, `Logger::alert()`, `Logger::emergency()`
- Context array optional: `Logger::info('message', ['key' => 'value', 'userId' => $userId])`
- Configuration: `Logger::configure(['file' => '/path/to/log', 'level' => 'error'])`

**Pattern:**
```php
Logger::info('Vote cast', [
    'motion_id' => $motionId,
    'member_id' => $memberId,
    'vote' => 'for',
]);
```

**When to Log:**
- Info: Major workflow transitions (meeting started, vote cast, member added)
- Warning: Recoverable issues (rate limit hit, retry needed)
- Error: Failures that affect functionality but don't crash (send failed, validation error)
- Debug: Implementation details (only in dev/test)

## Comments

**When to Comment:**
- Complex business logic: Quorum/majority calculation, weighted voting
- Non-obvious algorithms: Why a particular approach is used
- Public API docs: Always document public methods and classes
- Workarounds: Document why a workaround exists and link to issue

**JSDoc/PHPDoc:**
- Required: Public classes, public methods, return types with complex shapes
- Format: Start with short description (one line), follow with blank line if more detail needed
- Type hints in comments: `@return array{key: type, ...}` for array shapes, `@param Type $var Description`
- Example:
```php
/**
 * Computes motion results from ballots and policies.
 *
 * Returns both computed decision and official result data.
 * Delegates to OfficialResultsService if policies exist.
 *
 * @param string $motionId
 * @param string $tenantId
 *
 * @return array<string,mixed>
 */
public function computeMotionResult(string $motionId, string $tenantId): array {
```

## Function Design

**Size:**
- Target: Under 50 lines (exceptions for complex business logic with good structure)
- Measure: readability first, not strict limits
- Split large functions: Extract helper methods (private or public as needed)

**Parameters:**
- Constructor injection preferred: Services receive dependencies via constructor, not as parameters
- Nullable optional parameters for testing: `?RepositoryType $repo = null` allows tests to inject mocks
- No more than 5 parameters before considering refactoring to object

**Return Values:**
- Explicit return type always (no implicit null)
- Array returns documented in PHPDoc: `@return array{ok: bool, error: ?string, debug?: array}`
- Shape array example: Mixed types grouped (strings, bools, arrays) following logical grouping

**Example:**
```php
public function send(string $toEmail, string $subject, string $html, ?string $text = null): array {
    // Validation first
    if ($toEmail === '') {
        return ['ok' => false, 'error' => 'invalid_to_email'];
    }
    
    // Work
    try {
        // ...
        return ['ok' => true, 'error' => null];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'code: ' . $e->getMessage()];
    }
}
```

## Module Design

**Exports:**
- Services export via public methods (no static factories except singletons)
- Repositories export via public methods (queries, mutations)
- Controllers export via public handler methods matching HTTP verb
- No barrel files (no `index.php` re-exporting; use full paths)

**Visibility:**
- Public: User-facing methods, API endpoints
- Private: Helper methods, internal calculations
- Protected: Rare (used in base classes like AbstractRepository, AbstractController)
- Final: Services, repositories, controllers (prevent accidental extension)

**Single Responsibility:**
- Service = one concept (VoteEngine handles voting logic, MailerService handles SMTP)
- Repository = one table/entity (MotionRepository for motions, BallotRepository for ballots)
- Controller = one API resource or HTML page (BallotsController for ballot endpoints)
- Use traits for organizing large repositories: MotionFinderTrait, MotionWriterTrait, MotionListTrait, MotionAnalyticsTrait

---

*Convention analysis: 2026-04-07*
