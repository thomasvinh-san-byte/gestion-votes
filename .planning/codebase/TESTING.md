# Testing Patterns

**Analysis Date:** 2026-04-07

## Test Framework

**Runner:**
- PHPUnit ^10.5
- Config: `phpunit.xml`
- Bootstrap: `tests/bootstrap.php`

**Assertion Library:**
- PHPUnit built-in assertions (`assertEquals`, `assertSame`, `assertArrayHasKey`, etc.)

**Run Commands:**
```bash
# Run specific test file (preferred — per CLAUDE.md)
timeout 60 php vendor/bin/phpunit tests/Unit/SomeTest.php --no-coverage

# Run all unit tests
timeout 60 php vendor/bin/phpunit --testsuite Unit --no-coverage

# Run all tests (unit + integration — requires DB + Redis)
timeout 60 php vendor/bin/phpunit --no-coverage

# Run tests tagged @group redis
timeout 60 php vendor/bin/phpunit --group redis --no-coverage

# Run with coverage (slow)
php vendor/bin/phpunit --coverage-html coverage-report
```

## Test Suites

- **Unit** — `tests/Unit/` — No database or Redis required (all dependencies mocked)
- **Integration** — `tests/Integration/` — Requires live PostgreSQL and Redis

Integration tests (3 files):
- `tests/Integration/AdminCriticalPathTest.php` — Admin workflow + permission checking
- `tests/Integration/RepositoryTest.php` — Repository layer against real DB
- `tests/Integration/WorkflowValidationTest.php` — End-to-end meeting lifecycle

## Test File Organization

**Location pattern:** Co-located by concern, not by class hierarchy.
- Controller tests: `tests/Unit/{Name}ControllerTest.php` extends `ControllerTestCase`
- Service tests: `tests/Unit/{Name}ServiceTest.php` extends `PHPUnit\Framework\TestCase`
- Core/logic tests: `tests/Unit/{Concept}Test.php` extends `PHPUnit\Framework\TestCase`

**Total:** 91 test files, ~2299 test methods (Unit suite only)

## Test File Inventory (Unit)

Sorted by method count (top 20):

| Methods | File |
|---------|------|
| 158 | `tests/Unit/MeetingsControllerTest.php` |
| 155 | `tests/Unit/MeetingWorkflowControllerTest.php` |
| 144 | `tests/Unit/MotionsControllerTest.php` |
| 70 | `tests/Unit/ImportControllerTest.php` |
| 55 | `tests/Unit/MeetingReportsControllerTest.php` |
| 54 | `tests/Unit/ImportServiceTest.php` |
| 52 | `tests/Unit/ExportServiceTest.php` |
| 51 | `tests/Unit/AdminControllerTest.php` |
| 45 | `tests/Unit/VoteEngineTest.php` |
| 44 | `tests/Unit/QuorumEngineTest.php` |
| 44 | `tests/Unit/BallotsServiceTest.php` |
| 41 | `tests/Unit/MeetingWorkflowServiceTest.php` |
| 38 | `tests/Unit/MonitoringServiceTest.php` |
| 38 | `tests/Unit/BallotsControllerTest.php` |
| 37 | `tests/Unit/OfficialResultsServiceTest.php` |
| 36 | `tests/Unit/InputValidatorTest.php` |
| 35 | `tests/Unit/MemberGroupsControllerTest.php` |
| 35 | `tests/Unit/ExportControllerTest.php` |
| 34 | `tests/Unit/EmailQueueServiceTest.php` |
| 33 | `tests/Unit/AuthControllerTest.php` |

Remaining files (all with 3–32 tests each — full list):
`SpeechControllerTest.php` (32), `EmailControllerTest.php` (32), `DocContentControllerTest.php` (32),
`DevicesControllerTest.php` (31), `MeetingReportServiceTest.php` (29), `MeetingAttachmentControllerTest.php` (29),
`AuditControllerTest.php` (29), `VoteTokenServiceTest.php` (28), `AuthMiddlewareTest.php` (28),
`AttendancesServiceTest.php` (28), `ProxiesControllerTest.php` (27+), `AnalyticsControllerTest.php` (25),
`ResolutionDocumentControllerTest.php` (24), `MeetingTransitionTest.php` (24), `DocControllerTest.php` (24),
`AttendancesControllerTest.php` (24), `VoteLogicTest.php` (23), `QuorumLogicTest.php` (23),
`QuorumControllerTest.php` (23), `ReminderControllerTest.php` (22), `NotificationsServiceTest.php` (22),
`ExportTemplatesControllerTest.php` (22), `SpeechServiceTest.php` (21), `OperatorControllerTest.php` (21),
`MailerServiceTest.php` (21), `LoggerTest.php` (21), `InvitationsControllerTest.php` (21),
`EmergencyControllerTest.php` (21), `PermissionCheckerTest.php` (20), `AgendaControllerTest.php` (20),
`TrustControllerTest.php` (18), `RateLimiterTest.php` (18), `PoliciesControllerTest.php` (18),
`DashboardControllerTest.php` (17), `ErrorDictionaryTest.php` (16), `EmailTemplateServiceTest.php` (16),
`VoteTokenControllerTest.php` (15), `UploadSecurityTest.php` (15), `TenantIsolationTest.php` (15),
`CsrfMiddlewareTest.php` (15), `ProxiesServiceTest.php` (14), `EventBroadcasterTest.php` (14),
`DevSeedControllerTest.php` (14), `DataRetentionCommandTest.php` (14), `ProjectorControllerTest.php` (13),
`EmailTrackingControllerTest.php` (12), `ProcurationPdfControllerTest.php` (11), `NotificationsControllerTest.php` (11),
`MeetingValidatorTest.php` (11), `VotePublicControllerTest.php` (10), `SetupControllerTest.php` (10),
`RelaxRoleTransitionsTest.php` (10), `EmailProcessQueueCommandTest.php` (9), `SettingsControllerTest.php` (8),
`RouterTest.php` (8), `ProcurationPdfServiceTest.php` (7), `PasswordResetServiceTest.php` (7),
`EmailQueueRepositoryRetryTest.php` (7), `AccountControllerTest.php` (6), `StateTransitionCoherenceTest.php` (5),
`SecurityHardeningTest.php` (5), `RgpdExportServiceTest.php` (5), `PasswordResetControllerTest.php` (5),
`DataIntegrityLocksTest.php` (5), `AuthMiddlewareTimeoutTest.php` (5), `ApiHelpersTest.php` (5),
`RgpdExportControllerTest.php` (4), `DatabaseProviderTest.php` (4), `WeightedVoteRegressionTest.php` (3),
`MeetingStatsRepositoryTest.php` (3), `ApplicationBootTest.php` (2)

## Base Test Classes

### `ControllerTestCase` (`tests/Unit/ControllerTestCase.php`)

Base class for all controller tests. Provides:

**Setup/teardown (automatic per test):**
- Resets `RepositoryFactory` singleton
- Clears superglobals (`$_GET`, `$_POST`, `$_FILES`, `$_SERVER`)
- Resets `Request::cachedRawBody` via Reflection
- Resets `AuthMiddleware` state
- Sets `APP_AUTH_ENABLED=0`

**Helper methods:**

```php
// Inject mock repositories into RepositoryFactory singleton
$this->injectRepos([MeetingRepository::class => $mockMeeting]);

// Call a controller method and capture the ApiResponseException response
$response = $this->callController(BallotsController::class, 'listForMotion');
// Returns: ['status' => int, 'body' => array]

// Set JSON request body
$this->injectJsonBody(['motion_id' => 'uuid-here', 'choice' => 'for']);

// Set HTTP method
$this->setHttpMethod('POST');

// Set GET query parameters
$this->setQueryParams(['meeting_id' => 'uuid-here']);

// Inject authenticated user into AuthMiddleware
$this->setAuth('user-id', 'operator', 'tenant-id');
```

## Test Patterns

### Controller Test Pattern

```php
namespace Tests\Unit;

use AgVote\Controller\BallotsController;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;

final class BallotsControllerTest extends ControllerTestCase
{
    private const TENANT_ID  = 'tenant-ballots-test';
    private const MEETING_ID = '11110000-1111-2222-3333-000000000001';

    protected function setUp(): void
    {
        parent::setUp(); // always call parent
        $this->setAuth('operator-01', 'operator', self::TENANT_ID);
    }

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(BallotsController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testListForMotionRequiresGet(): void
    {
        $this->setHttpMethod('POST');
        $response = $this->callController(BallotsController::class, 'listForMotion');
        $this->assertSame(405, $response['status']);
    }

    public function testListForMotionReturnsItems(): void
    {
        $mockMotion = $this->createMock(MotionRepository::class);
        $mockMotion->method('findByIdForTenant')->willReturn(['id' => self::MEETING_ID]);

        $this->injectRepos([MotionRepository::class => $mockMotion]);
        $this->setQueryParams(['motion_id' => self::MEETING_ID]);

        $response = $this->callController(BallotsController::class, 'listForMotion');
        $this->assertSame(200, $response['status']);
    }
}
```

### Service Test Pattern

```php
namespace AgVote\Tests\Unit;  // Note: service tests sometimes use AgVote\Tests\Unit namespace

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BallotsServiceTest extends TestCase
{
    private BallotRepository&MockObject $ballotRepo;
    private BallotsService $service;

    protected function setUp(): void
    {
        $this->ballotRepo = $this->createMock(BallotRepository::class);
        // Build real instances of final services with mocked repos
        $this->service = new BallotsService($this->ballotRepo, ...);
    }

    public function testCastThrowsWhenMotionNotFound(): void
    {
        $this->ballotRepo->method('findMotion')->willReturn(null);
        $this->expectException(\RuntimeException::class);
        $this->service->cast([...]);
    }
}
```

### Standard Controller Structure Test

Every controller test file includes these structural assertions:

```php
public function testControllerIsFinal(): void
{
    $ref = new ReflectionClass(SomeController::class);
    $this->assertTrue($ref->isFinal());
}

public function testControllerExtendsAbstractController(): void
{
    $ref = new ReflectionClass(SomeController::class);
    $this->assertSame('AgVote\\Controller\\AbstractController', $ref->getParentClass()->getName());
}

public function testHasExpectedPublicMethods(): void
{
    $ref = new ReflectionClass(SomeController::class);
    $this->assertTrue($ref->hasMethod('methodName'));
}
```

## Mocking

**Framework:** PHPUnit built-in MockObject (`$this->createMock()`)

**Repository injection for controllers:**
```php
// Repositories are NOT final — they CAN be mocked
$mockMeeting = $this->createMock(MeetingRepository::class);
$mockMeeting->method('findByIdForTenant')->willReturn(['id' => $meetingId, 'status' => 'running']);
$this->injectRepos([MeetingRepository::class => $mockMeeting]);
```

**Mocking final service classes (cannot be mocked directly):**
```php
// AttendancesService is final — create a real instance with mocked repos instead
$attendancesService = new AttendancesService(
    $this->createMock(AttendanceRepository::class),
    $this->createMock(MeetingRepository::class),
    $this->createMock(MemberRepository::class),
);
```

**What to mock:** All repository dependencies, external service dependencies.
**What NOT to mock:** `RepositoryFactory` (it's final — use `injectRepos()` instead), final service classes (build real instances with mocked repos).

## Bootstrap Stubs

`tests/bootstrap.php` stubs these global functions to enable unit testing without infrastructure:

| Function | Stub Behavior |
|----------|---------------|
| `db()` | Throws `RuntimeException` (forces explicit PDO injection) |
| `api_transaction()` | Executes callable directly (no real DB transaction) |
| `config()` | Returns sensible defaults (e.g., `proxy_max_per_receiver` = 99) |
| `audit_log()` | No-op |
| `api_uuid4()` | Returns deterministic random v4 UUID |
| `api_ok()` | Throws `ApiResponseException` (same as production) |
| `api_fail()` | Throws `ApiResponseException` (same as production) |
| `api_method()` | Reads `$_SERVER['REQUEST_METHOD']` |
| `api_request()` | Validates method, returns merged GET+body |
| `api_query()` | Reads `$_GET[$key]` |
| `api_query_int()` | Reads `$_GET[$key]` as int |
| `api_is_uuid()` | Full UUID regex check |
| `api_require_uuid()` | Full validation (same as production) |
| `api_current_user_id()` | Delegates to `AuthMiddleware::getCurrentUserId()` |
| `api_current_role()` | Delegates to `AuthMiddleware::getCurrentRole()` |
| `api_current_tenant_id()` | Delegates to `AuthMiddleware::getCurrentTenantId()` |
| `api_require_role()` | **No-op** — authorization NOT enforced in unit tests |
| `api_guard_meeting_not_validated()` | No-op |
| `api_guard_meeting_exists()` | Delegates to mocked `MeetingRepository` |
| `api_file()` | Reads from `$_FILES` |

**Constants defined by bootstrap:**
- `APP_SECRET` = `'test-secret-for-unit-tests-only-32chars!'`
- `DEFAULT_TENANT_ID` = `'aaaaaaaa-1111-2222-3333-444444444444'`
- `AG_UPLOAD_DIR` = `sys_get_temp_dir() . '/ag-vote-test-uploads'`
- `PHPUNIT_RUNNING` = `true` (causes HTML controllers to throw instead of calling `header()`/`exit()`)

## Redis-Dependent Tests

Two test files require a live Redis instance and are tagged `@group redis`:

- `tests/Unit/RateLimiterTest.php` (18 tests) — Tests `RateLimiter` with real Redis Lua scripts
- `tests/Unit/EventBroadcasterTest.php` (14 tests) — Tests SSE event queue via `RedisProvider`

Both files connect using `getenv('REDIS_HOST') ?: '127.0.0.1'` and `getenv('REDIS_PORT') ?: 6379`. They flush their test keys in `tearDown()`. These tests fail gracefully when Redis is unavailable (they do not skip — they throw).

To exclude Redis tests locally when Redis is unavailable:
```bash
timeout 60 php vendor/bin/phpunit --testsuite Unit --exclude-group redis --no-coverage
```

## Known Testing Limitations

**1. `api_require_role()` is a no-op stub.**
Authorization (role-based access control) cannot be tested via `callController()`. Authentication enforcement via `AuthMiddleware::requireRole()` is directly testable (see `RgpdExportControllerTest`). Role bypass tests must call `AuthMiddleware::requireRole()` directly with `APP_AUTH_ENABLED=1`.

**2. Final service classes cannot be mocked.**
`AttendancesService`, `BallotsService`, `ProxiesService`, and other `final` services must be instantiated with mocked repositories rather than being mocked as a whole. This is by design (final enforces single implementation).

**3. `RepositoryFactory` is final and cannot be mocked.**
Use `injectRepos()` in `ControllerTestCase` which injects via Reflection into the singleton's cache.

**4. Database-dependent service methods yield 500 in test env.**
Controllers that reach the service layer without full repository mocking will return 500 (`RuntimeException` from stub `db()`). This is expected and acceptable for auth guard tests that only care about reaching the service layer.

**5. No coverage for `app/api.php` and `app/bootstrap.php`.**
These files are excluded from coverage source in `phpunit.xml` (`<exclude>` block).

**6. HTML controllers tested via PHPUNIT_RUNNING flag.**
`SetupController`, `PasswordResetController`, `AccountController` use `PHPUNIT_RUNNING` constant to throw exceptions instead of calling `header()`/`exit()`. Tests assert thrown exceptions or use `callController()` on hybrid controllers.

## Coverage Configuration

**Coverage source directories (from `phpunit.xml`):**
- `app/Core`
- `app/Services`
- `app/Repository`
- `app/SSE`
- `app/Templates`
- `app/Controller`

**Excluded from coverage:**
- `app/api.php`
- `app/bootstrap.php`

**Coverage formats:** HTML (`coverage-report/`), Clover XML (`coverage.xml`), text to stdout.

**No enforced coverage thresholds** — coverage is informational only.

## Areas with Strong Coverage

- **Core business logic:** `VoteEngineTest` (45 tests), `QuorumEngineTest` (44 tests), `BallotsServiceTest` (44 tests)
- **Meeting lifecycle:** `MeetingsControllerTest` (158 tests), `MeetingWorkflowControllerTest` (155 tests), `MeetingTransitionTest` (24 tests)
- **Auth/security:** `AuthMiddlewareTest` (28 tests), `CsrfMiddlewareTest` (15 tests), `PermissionCheckerTest` (20 tests)
- **All controllers:** Every controller class has a corresponding `*ControllerTest.php`
- **Error handling:** `ErrorDictionaryTest` (16 tests), `ApiHelpersTest` (5 tests)

## Coverage Gaps

**Not unit-tested:**
- `app/api.php` global functions (excluded from coverage; integration path only)
- `app/bootstrap.php` startup sequence
- Direct file-based legacy routes in `public/api/v1/*.php`
- HTML view templates in `app/Templates/`
- SSE streaming behavior beyond broadcaster (no stream-output tests)
- Database migrations in `database/migrations/`

**Partially tested:**
- `RateLimiter` — covered only when Redis is available (`@group redis`)
- `EventBroadcaster` — covered only when Redis is available (`@group redis`)
- Repository queries — `MeetingStatsRepositoryTest` has only 3 tests; most repository SQL is exercised via integration tests or via mocked return values in controller tests

---

*Testing analysis: 2026-04-07*
