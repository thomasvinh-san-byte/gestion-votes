# Phase 3: Extraction Services et Refactoring - Research

**Researched:** 2026-04-07
**Domain:** PHP refactoring, characterization testing, controller extraction
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Tests de caracterisation obligatoires AVANT toute extraction
- ImportController (921 lignes) → logique metier dans services dedies
- Controller = orchestration HTTP uniquement (< 150 lignes)
- DI par constructeur avec parametres optionnels nullable pour les tests
- Namespaces: AgVote\Service pour nouveaux services
- RgpdExportController: tester scope validation, acces non autorise, compliance donnees
- AuthMiddleware: tester lifecycle session complet, transitions d'etat 10+ statics

### Claude's Discretion
All implementation choices are at Claude's discretion — pure infrastructure phase.

### Deferred Ideas (OUT OF SCOPE)
- AuthMiddleware static → SessionContext DI (v2 — REFAC-02)
- Split MeetingReportsController/MotionsController (Phase 4)
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| REFAC-01 | Logique metier de ImportController (921 lignes) extraite dans des services dedies, controller = orchestration HTTP uniquement | ImportController anatomy maps cleanly to 4 service methods; existing ImportService already exists as target |
| TEST-01 | RgpdExportController a des tests unitaires couvrant scope validation, acces non autorise, et compliance donnees personnelles | RgpdExportController (45 lines) has no controller test; RgpdExportService is already tested; controller test needs ControllerTestCase pattern |
| TEST-02 | AuthMiddleware a des tests couvrant le lifecycle session complet et les transitions d'etat des 10+ variables statiques | reset() covers 10 statics; session lifecycle (timeout, revalidation, expiry) not yet tested; test injection pattern exists via setSessionTimeoutForTest + Reflection |
</phase_requirements>

## Summary

Phase 3 is a pure infrastructure refactoring and test-coverage phase on three files. ImportController is 921 lines because it contains both HTTP orchestration and all business logic (validation, column matching, row processing, group upserts, proxy chain checks). The extraction target is to push every non-HTTP concern into dedicated service methods, leaving the controller as a thin dispatcher under 150 lines.

AuthMiddleware at 871 lines manages all session state via 10 static properties. The existing test files (AuthMiddlewareTest.php, AuthMiddlewareTimeoutTest.php) cover the stateless utility methods and the session-expired-flag differentiation, but do NOT cover the session lifecycle paths inside `authenticate()`: timeout expiry, DB revalidation interval, role change detection and session_regenerate_id, or the complete reset() of all 10 static fields.

RgpdExportController (45 lines) has no controller-level test file — only RgpdExportServiceTest.php exists. The controller test must cover: correct HTTP method enforcement (GET), auth scope enforcement (api_require_role), cross-tenant scope (user_id + tenant_id from session), and absence of sensitive fields in exported data.

**Primary recommendation:** Write characterization tests first (AuthMiddleware session lifecycle, RgpdExportController), then extract ImportController row-processing methods into a new `MemberImportService` (or extend existing `ImportService`), verify all existing ImportControllerTest still passes.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHPUnit | 10.5.x (installed) | Test framework | Already in use project-wide |
| PHP 8.3 | 8.3.6 | Runtime | Matches production |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| ReflectionClass | stdlib | Accessing private/static state in tests | Required for AuthMiddleware static injection |
| PDO mock | PHPUnit MockObject | DB-free testing of services | Established pattern in RgpdExportServiceTest |
| RepositoryFactory::reset() | project | Clean test isolation | Required in every controller test setUp |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Extend ImportService | Create new MemberImportService | ImportService is already the home for all import parsing; extending it keeps the namespace tidy |
| Extend ImportService | Create 4 separate services | Over-engineering for this phase; ImportService already exists as `AgVote\Service\ImportService` |

**Installation:** No new packages needed — all dependencies already present.

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Controller/
│   └── ImportController.php   # ≤ 150 lines — HTTP orchestration only
├── Services/
│   ├── ImportService.php      # Extend with processMemberRows, processAttendanceRows, processProxyRows, processMotionRows
│   └── RgpdExportService.php  # Unchanged
tests/
├── Unit/
│   ├── AuthMiddlewareTest.php              # Extend with session lifecycle tests
│   ├── RgpdExportControllerTest.php        # NEW — controller-level tests
│   └── ImportControllerTest.php            # Unchanged (already 70 tests passing)
```

### Pattern 1: Characterization Tests Before Extraction
**What:** Write tests that document the current observable behavior of a class before changing it.
**When to use:** BEFORE any extraction — the tests become the regression safety net.
**Example:**
```php
// tests/Unit/AuthMiddlewareTest.php — session lifecycle group
public function testAuthenticateExpiresSessionAfterTimeout(): void {
    // Inject session with old auth_last_activity
    $_SESSION['auth_user'] = ['id' => 'u1', 'role' => 'operator', 'tenant_id' => $tid];
    $_SESSION['auth_last_activity'] = time() - 99999; // way past timeout

    AuthMiddleware::setSessionTimeoutForTest($tid, 300); // 5 minutes

    $result = AuthMiddleware::authenticate();
    $this->assertNull($result);
    $this->assertEmpty($_SESSION);
}
```

### Pattern 2: Controller Extraction — HTTP Orchestration Shell
**What:** Controller calls service method with domain objects; service returns result array.
**When to use:** When controller method > 30 lines of non-HTTP logic.
**Example:**
```php
// BEFORE — controller contains row-processing logic (560 lines of private helpers)
// AFTER — controller delegates:
public function membersCsv(): void {
    $in = api_request('POST');
    [$headers, $rows] = $this->readImportFile('csv');
    $tenantId = api_current_tenant_id();

    $result = ImportService::processMemberImport($rows, $headers, $tenantId);

    audit_log('members_import', 'member', null, [...]);
    api_ok($result);
}
```

### Pattern 3: RgpdExportController Test via ControllerTestCase
**What:** Use ControllerTestCase to inject mock repo/service and exercise HTTP paths.
**When to use:** Testing controller authorization without a real DB.
**Example:**
```php
// tests/Unit/RgpdExportControllerTest.php
class RgpdExportControllerTest extends ControllerTestCase {
    public function testDownloadRequiresAuthentication(): void {
        $this->setHttpMethod('GET');
        putenv('APP_AUTH_ENABLED=1'); // re-enable to test guard

        $response = $this->callController(RgpdExportController::class, 'download');
        $this->assertSame(401, $response['status']);
    }
}
```

### Pattern 4: Static State Testing with Reflection
**What:** Use ReflectionClass to read or set private static properties on AuthMiddleware.
**When to use:** Verifying that `reset()` clears all 10 static fields, or seeding session state.
**Example:**
```php
// Seed auth_last_activity into session (simulate idle timeout)
$_SESSION['auth_user'] = ['id' => 'u1', 'role' => 'operator', 'tenant_id' => $tid];
$_SESSION['auth_last_activity'] = time() - 4000; // 4000s > 1800s default
$result = AuthMiddleware::authenticate();
$this->assertNull($result);
```

### Anti-Patterns to Avoid
- **Extracting and breaking existing tests:** The 70 ImportControllerTest tests must still pass after extraction. Extract behavior must be kept exactly equivalent.
- **Moving HTTP concerns into services:** Services must never call `api_fail()`, `api_ok()`, or access `$_SERVER`. They return plain arrays or throw domain exceptions.
- **Skipping reset() in test setUp:** AuthMiddleware's statics are process-global. Each test must call `AuthMiddleware::reset()` in setUp to avoid cross-test contamination.
- **Using `session_start()` in unit tests:** AuthMiddleware::authenticate() calls `SessionHelper::start()`. Tests should seed `$_SESSION` directly without starting a real PHP session; the guard `session_status() === PHP_SESSION_NONE` allows this.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| HTTP-free CSV testing | Simulate HTTP request in tests | Extract row-processing to ImportService methods callable without HTTP context | Clean separation, no mock needed |
| Auth injection in tests | Mock AuthMiddleware | `AuthMiddleware::setCurrentUser()` + `AuthMiddleware::reset()` already exist | Pattern established in ControllerTestCase::setAuth() |
| Session state seeding | Mock PHP session | Seed `$_SESSION` directly in setUp before calling authenticate() | PHP sessions are arrays; no special mock needed |
| PDO in controller tests | Inject real DB | PDO MockObject via constructor injection (nullable param pattern) | RgpdExportService already uses this pattern |

**Key insight:** The project already has all the test infrastructure (ControllerTestCase, AuthMiddleware::reset(), setSessionTimeoutForTest) to test these components without any new tooling.

## Common Pitfalls

### Pitfall 1: session_destroy() in Tests
**What goes wrong:** `authenticate()` calls `session_destroy()` when a session expires. If session is not active, this throws a warning.
**Why it happens:** PHP's `session_destroy()` requires an active session.
**How to avoid:** In setUp, do NOT call `session_start()`. Instead, seed `$_SESSION` directly. When `authenticate()` calls `SessionHelper::start()`, it will be a no-op if `$_SESSION` is already set as a plain array (PHP allows `$_SESSION` to be set without a live session in CLI/test mode).
**Warning signs:** "Warning: session_destroy(): Trying to destroy uninitialized session" in test output.

### Pitfall 2: Cross-Test Static Contamination in AuthMiddleware
**What goes wrong:** A test that calls `authenticate()` and sets `self::$currentUser` will contaminate the next test if `reset()` is not called.
**Why it happens:** 10 static properties, process-global.
**How to avoid:** ALWAYS call `AuthMiddleware::reset()` in setUp AND tearDown. The existing AuthMiddlewareTimeoutTest already does this correctly.
**Warning signs:** Tests pass individually but fail when run in suite order.

### Pitfall 3: ImportController Uses `$this->repo()` — Not Static
**What goes wrong:** The private row-processing methods (`processMemberRows`, `processAttendanceRows`, etc.) use `$this->repo()->member()`, `$this->repo()->motion()` etc., which is an instance method from AbstractController. Extracting these methods to ImportService (which is final and uses only static methods) requires adapting the repository access pattern.
**Why it happens:** ImportService currently uses no constructor — all methods are static. But extracted row-processing needs repository access.
**How to avoid:** ImportService must gain a constructor accepting a RepositoryFactory (or individual repos) with nullable defaults — matching the established DI pattern. This is explicitly required by the project convention: "DI par constructeur avec parametres optionnels nullable pour les tests."
**Warning signs:** "Call to undefined method ImportService::member()" or "Cannot access $this from static context" after extraction.

### Pitfall 4: `api_fail()` Calls Inside Private Helpers
**What goes wrong:** Several private helpers in ImportController call `api_fail()` directly (which throws `ApiResponseException`). These cannot be called from inside a pure service.
**Why it happens:** The helpers were written as controller methods, not service methods.
**How to avoid:** Replace `api_fail()` calls in extracted methods with return values or domain exceptions. The controller catches those and calls `api_fail()` itself.
**Warning signs:** `api_fail` called from inside `AgVote\Service\ImportService` — `api_fail` is a global function defined in the HTTP bootstrap, not available in CLI/test context.

### Pitfall 5: membersCsv Has Duplicate File-Reading Logic
**What goes wrong:** `membersCsv()` and `proxiesCsv()` both contain their own inline CSV reading + temp file logic (they don't use `readImportFile()`). After extraction, this duplication will be visible and should be unified.
**Why it happens:** `readImportFile()` was added later; the members and proxies endpoints pre-date it. Both also support `csv_content` as a string parameter (inline CSV), which `readImportFile()` does not handle.
**How to avoid:** The extraction must handle both code paths. Do not silently drop `csv_content` support — it is used by the test suite.
**Warning signs:** ImportControllerTest tests for `csv_content` mode fail after extraction.

## Code Examples

Verified patterns from official sources (all sourced from existing project code):

### Nullable Constructor DI Pattern (established in RgpdExportService)
```php
// Source: app/Services/RgpdExportService.php
final class RgpdExportService {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? DatabaseProvider::pdo();
    }
}
```

### AuthMiddleware Reset Pattern (established in AuthMiddlewareTimeoutTest)
```php
// Source: tests/Unit/AuthMiddlewareTimeoutTest.php
protected function setUp(): void {
    AuthMiddleware::reset();
    RepositoryFactory::reset();
}

protected function tearDown(): void {
    AuthMiddleware::reset();
    RepositoryFactory::reset();
}
```

### Static State Seeding via Reflection (established in AuthMiddlewareTest)
```php
// Source: tests/Unit/AuthMiddlewareTest.php
$ref = new ReflectionClass(AuthMiddleware::class);
$prop = $ref->getProperty('sessionExpired');
$prop->setAccessible(true);
$prop->setValue(null, true);
```

### Controller Test with Auth Injection (established in ControllerTestCase)
```php
// Source: tests/Unit/ControllerTestCase.php
protected function setAuth(string $userId, string $role, string $tenantId): void {
    AuthMiddleware::setCurrentUser([
        'id' => $userId,
        'role' => $role,
        'tenant_id' => $tenantId,
        'name' => 'Test User',
        'is_active' => true,
    ]);
}
```

### Extracting Row Processing — New ImportService Instance Pattern
```php
// Target pattern for ImportService after extraction
final class ImportService {
    private RepositoryFactory $repos;

    public function __construct(?RepositoryFactory $repos = null) {
        $this->repos = $repos ?? RepositoryFactory::getInstance();
    }

    public function processMemberImport(
        array $rows,
        array $headers,
        string $tenantId
    ): array { // ['imported' => int, 'skipped' => int, 'errors' => array]
        // ... extracted logic from processMemberRows(), no api_fail() calls
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| ImportController doing everything | Controller as HTTP shell, Service for logic | This phase | ImportController drops from 921 to <150 lines |
| AuthMiddleware tests only cover stateless utilities | Tests cover full session lifecycle | This phase | TEST-02 requirement satisfied |
| RgpdExportController untested at HTTP layer | RgpdExportControllerTest covers auth+scope | This phase | TEST-01 requirement satisfied |

**Deprecated/outdated:**
- Inline CSV reading in `membersCsv()` and `proxiesCsv()`: should be unified through `readImportFile()` or a new service method that supports both file upload and `csv_content` string input.
- ImportService as pure-static class: after extraction it needs a constructor (DI pattern).

## Open Questions

1. **Should extracted methods go into ImportService (extending it) or into a new MemberImportService?**
   - What we know: ImportService already has `processMemberRows`-adjacent logic (column maps, CSV/XLSX reading, parseBoolean, parseVotingPower). The four row-processing methods naturally belong alongside this.
   - What's unclear: ImportService is currently 347 lines and final. Adding 4 row-processing methods + RepositoryFactory injection will grow it to ~550 lines.
   - Recommendation: Extend ImportService with instance methods. The namespace is `AgVote\Service` and the service already owns import concerns. Do NOT create a separate service unless line count exceeds 600 lines.

2. **How to handle `csv_content` (string input) in the extracted service?**
   - What we know: `membersCsv()` and `proxiesCsv()` accept `csv_content` as a JSON/FormData string. `readImportFile()` only handles file uploads. This is tested in ImportControllerTest.
   - What's unclear: Whether the temp-file + unlink pattern should stay in the controller or move to the service.
   - Recommendation: Keep file-reading (including `csv_content` temp-file handling) in the controller helper `readImportFile()` extended to support string input. The service receives `[$headers, $rows]` only — never file paths.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.5.63 |
| Config file | `/home/user/gestion_votes_php/phpunit.xml` |
| Quick run command | `timeout 60 php vendor/bin/phpunit tests/Unit/AuthMiddlewareTest.php --no-coverage` |
| Full suite command | `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| TEST-01 | RgpdExportController enforces GET only | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/RgpdExportControllerTest.php --no-coverage` | Wave 0 |
| TEST-01 | RgpdExportController returns 401 when unauthenticated | unit | same | Wave 0 |
| TEST-01 | RgpdExportController scopes export to session user_id + tenant_id | unit | same | Wave 0 |
| TEST-01 | Exported data excludes password_hash | unit | same | Wave 0 |
| TEST-02 | authenticate() returns null and clears session after timeout | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/AuthMiddlewareTest.php --no-coverage` | existing file |
| TEST-02 | authenticate() revokes session for deactivated user (via DB revalidation mock) | unit | same | existing file |
| TEST-02 | authenticate() regenerates session ID on role change | unit | same | existing file |
| TEST-02 | reset() clears all 10 static variables | unit | same | existing file |
| REFAC-01 | ImportController < 150 lines after extraction | structural | `wc -l app/Controller/ImportController.php` | — |
| REFAC-01 | Full CSV import (create, update, group matching) runs without HTTP context | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/ImportControllerTest.php --no-coverage` | ✅ |

### Sampling Rate
- **Per task commit:** `timeout 60 php vendor/bin/phpunit tests/Unit/AuthMiddlewareTest.php tests/Unit/ImportControllerTest.php --no-coverage`
- **Per wave merge:** `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Unit/RgpdExportControllerTest.php` — covers TEST-01 (scope validation, unauthorized access, data compliance)

## Sources

### Primary (HIGH confidence)
- Direct codebase analysis — `app/Controller/ImportController.php` (921 lines, fully read)
- Direct codebase analysis — `app/Core/Security/AuthMiddleware.php` (871 lines, fully read)
- Direct codebase analysis — `app/Controller/RgpdExportController.php` (45 lines, fully read)
- Direct codebase analysis — `app/Services/ImportService.php` (347 lines, fully read)
- Direct codebase analysis — `app/Services/RgpdExportService.php` (115 lines, fully read)
- Direct codebase analysis — `tests/Unit/ControllerTestCase.php` (established injection patterns)
- Direct codebase analysis — `tests/Unit/AuthMiddlewareTest.php` (22 tests, all passing)
- Direct codebase analysis — `tests/Unit/AuthMiddlewareTimeoutTest.php` (timeout tests)
- Direct codebase analysis — `tests/Unit/ImportControllerTest.php` (70 tests, all passing)
- Direct codebase analysis — `tests/Unit/RgpdExportServiceTest.php` (service-level tests exist)
- Live test execution — all existing tests confirmed green

### Secondary (MEDIUM confidence)
- CONTEXT.md constraints — all implementation decisions confirmed by author

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries are already installed and in use
- Architecture: HIGH — patterns extracted directly from existing code conventions
- Pitfalls: HIGH — identified from direct code reading, not speculation

**Research date:** 2026-04-07
**Valid until:** 2026-05-07 (stable internal codebase)
