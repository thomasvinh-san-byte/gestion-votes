# Testing Patterns

**Analysis Date:** 2026-04-07

## Test Framework

**Runner:**
- PHPUnit 10.5 (configured in `phpunit.xml`)
- Config file: `phpunit.xml` at project root
- Coverage reports: HTML to `coverage-report/`, text to stdout, Clover to `coverage.xml`

**Assertion Library:**
- PHPUnit TestCase built-in assertions: `assertTrue()`, `assertFalse()`, `assertSame()`, `assertContains()`, `assertEquals()`

**Run Commands:**
```bash
# Run single test file (REQUIRED per CLAUDE.md)
php vendor/bin/phpunit tests/Unit/FileName.php --no-coverage

# Run with timeout (REQUIRED per CLAUDE.md)
timeout 60 php vendor/bin/phpunit tests/Unit/FileName.php --no-coverage

# Run all tests with coverage
php vendor/bin/phpunit

# Watch mode (not standard PHPUnit; use IDE integration or manual reruns)
```

**Environment Setup:**
- Bootstrap: `tests/bootstrap.php` (auto-loaded before tests run)
- Test constants: `PROJECT_ROOT`, `PHPUNIT_RUNNING`, `APP_SECRET`, `DEFAULT_TENANT_ID`
- Global stubs: API helpers (`api_ok`, `api_fail`, `api_request`, etc.) are stubbed for tests
- Auth disabled: `APP_AUTH_ENABLED=0` by default in test env

## Test File Organization

**Location:**
- All tests in `tests/Unit/` or `tests/Integration/`
- Co-located with test suite structure, not near source code
- Separated by concern: controller tests, service tests, repository tests

**Naming:**
- Pattern: `{Subject}Test.php` (e.g., `QuorumEngineTest.php`, `EmailTemplateServiceTest.php`, `BallotsControllerTest.php`)
- Namespace: `Tests\Unit` (autoload-dev in `composer.json`)
- Class names: `{Subject}Test extends TestCase` (or `ControllerTestCase` for controllers)

**Structure:**
```
tests/
├── bootstrap.php              # Global test setup and helper stubs
├── Unit/
│   ├── ControllerTestCase.php # Base class for all controller tests
│   ├── QuorumEngineTest.php   # Service tests
│   ├── BallotsControllerTest.php # Controller tests
│   └── EmailTemplateServiceTest.php
└── Integration/
    └── [future integration tests]
```

## Test Structure

**Suite Organization:**
```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Service\MailerService;
use PHPUnit\Framework\TestCase;

class MailerServiceTest extends TestCase {
    // Setup
    protected function setUp(): void {
        parent::setUp();
        // Initialize test state
    }

    protected function tearDown(): void {
        // Clean up after test
        parent::tearDown();
    }

    // Test methods grouped by feature
    public function testFeatureWorks(): void {
        // Arrange
        $service = new MailerService(['smtp' => [...]]);
        
        // Act
        $result = $service->send('test@example.com', 'Subject', '<p>HTML</p>');
        
        // Assert
        $this->assertTrue($result['ok']);
    }
}
```

**Test Method Naming:**
- Pattern: `test{Feature}{Scenario}` (e.g., `testRenderSubstitutesVariables`, `testListForMotionNotFound`)
- Readable names describe what is being tested and expected outcome
- Group related tests with section comments: `// ========= SECTION NAME ==========`

**Patterns:**
- Setup: `setUp()` initializes shared test state (mocks, fixtures, auth)
- Teardown: `tearDown()` resets global state (RepositoryFactory singleton, superglobals, AuthMiddleware)
- Assertions: Use specific assertions (`assertSame` for exact equality, `assertContains` for membership)

## Mocking

**Framework:** PHPUnit's built-in `createMock()` and `createStub()`

**Mock Repositories:**
```php
protected function setUp(): void {
    parent::setUp();
    
    $this->motionRepo = $this->createMock(MotionRepository::class);
    $this->ballotRepo = $this->createMock(BallotRepository::class);
    
    // Set up return values
    $this->motionRepo->method('findByIdForTenant')
        ->willReturn(['id' => 'motion-1', 'title' => 'Motion 1']);
}
```

**Injecting Mocks into Services:**
```php
$service = new VoteEngine(
    $this->motionRepo,      // Inject mock
    $this->ballotRepo,      // Inject mock
    $this->memberRepo,      // Inject mock
    null,                   // Constructor accepts nullable params for testing
    null,
);
```

**Injecting into Controllers:**
Controller tests extend `ControllerTestCase` which provides `injectRepos(array)`:
```php
$mockMeeting = $this->createMock(MeetingRepository::class);
$mockMotion = $this->createMock(MotionRepository::class);

$this->injectRepos([
    MeetingRepository::class => $mockMeeting,
    MotionRepository::class => $mockMotion,
]);

// Now any controller code calling $this->repo()->meeting() gets $mockMeeting
```

**Pattern: RepositoryFactory Injection (Controllers Only)**
- RepositoryFactory is final and cannot be mocked directly
- Create real `RepositoryFactory(null)` and use Reflection to inject mocks into its `cache` property
- Set this factory as the singleton via Reflection on `instance` property
- Cache-first pattern in RepositoryFactory ensures mocks are returned before real DB calls
- Implementation is in `ControllerTestCase::injectRepos()` — use it for all controller tests

**What to Mock:**
- External dependencies: Database (repositories), third-party libraries (Mailer), file systems
- Expensive operations: Email sending, file generation, HTTP calls
- Another service's behavior: When testing service A that depends on service B, mock B

**What NOT to Mock:**
- Validation logic: Test real validators unless they're external
- Core business logic: Test vote calculation without mocks to verify accuracy
- Helper utilities: String formatting, UUID generation (unless randomness matters)
- Constants and enums: Use real constants

## Fixtures and Factories

**Test Data:**
No dedicated fixture/factory files in current codebase. Instead:
- Hard-coded test data in test methods: `const TENANT_ID = 'tenant-ballots-test';`
- Sample returns in setUp: `$this->repo->method('find')->willReturn(['id' => '...', ...])`
- Use realistic test UUIDs: `'11110000-1111-2222-3333-000000000001'` (repeating pattern, easy to spot in test output)

**Example Pattern:**
```php
class BallotsControllerTest extends ControllerTestCase {
    private const TENANT_ID  = 'tenant-ballots-test';
    private const MEETING_ID = '11110000-1111-2222-3333-000000000001';
    private const MOTION_ID  = '22220000-1111-2222-3333-000000000002';
    private const MEMBER_ID  = '33330000-1111-2222-3333-000000000003';
    
    protected function setUp(): void {
        parent::setUp();
        $this->setAuth('operator-01', 'operator', self::TENANT_ID);
    }
}
```

**Location:**
- Test data constants: Class-level constants at top of test class
- Sample mock returns: Inline in test method (keep test self-contained)
- Shared test utilities: Methods like `createMockResult()` in test class itself (not separate files)

## Coverage

**Requirements:** Not enforced; configured to report but not fail

**View Coverage:**
```bash
# HTML report (opens in browser)
open coverage-report/index.html

# Text output (from standard run)
php vendor/bin/phpunit
```

**Coverage Scope (from phpunit.xml):**
- Included: `app/Core/`, `app/Services/`, `app/Repository/`, `app/SSE/`, `app/Templates/`, `app/Controller/`
- Excluded: `app/api.php`, `app/bootstrap.php` (entry points, not testable)
- Output formats: HTML report, text to stdout, Clover XML for CI tools

## Test Types

**Unit Tests:**
- Scope: Single class/method in isolation
- Dependencies: Mocked (repositories, external services)
- Database: None (mocked)
- Location: `tests/Unit/`
- Examples: `QuorumEngineTest` tests static `computeDecision()` with pure inputs; `EmailTemplateServiceTest` mocks repositories

**Integration Tests:**
- Scope: Multiple classes working together
- Dependencies: Real or partially mocked
- Database: Real (if repo tests needed) or test SQLite
- Location: `tests/Integration/`
- Status: Directory exists but empty; future tests

**E2E Tests:**
- Framework: Not used
- Why: Out of scope for this codebase (would require browser automation or full HTTP server)

## Common Patterns

**Async Testing:**
Not applicable in synchronous PHP. Use transaction rollback or test isolation.

**Error Testing:**
```php
public function testCastVoteWithInvalidMotion(): void {
    $this->setHttpMethod('POST');
    $this->injectJsonBody(['motion_id' => 'invalid-uuid', ...]);
    
    $resp = $this->callController(BallotsController::class, 'cast');
    $this->assertSame(422, $resp['status']);
    $this->assertSame('invalid_motion_id', $resp['body']['error']);
}
```

**Testing Service Methods with Dependencies:**
```php
public function testRenderSubstitutesVariables(): void {
    $service = new EmailTemplateService(
        ['app' => ['url' => 'https://votes.test']],
        $this->createMock(EmailTemplateRepository::class),
        $this->createMock(MeetingRepository::class),
        $this->createMock(MemberRepository::class),
        $this->createMock(MeetingStatsRepository::class),
    );
    
    $result = $service->render('Hello {{member_name}}', ['{{member_name}}' => 'Jean']);
    $this->assertSame('Hello Jean', $result);
}
```

**Testing Controller Methods:**
```php
public function testListForMotionReturnsMotionData(): void {
    $this->setHttpMethod('GET');
    $this->setQueryParams(['motion_id' => self::MOTION_ID]);
    
    $motionRepo = $this->createMock(MotionRepository::class);
    $motionRepo->method('findByIdForTenant')
        ->willReturn(['id' => self::MOTION_ID, 'title' => 'Motion 1']);
    
    $ballotRepo = $this->createMock(BallotRepository::class);
    $ballotRepo->method('listForMotion')
        ->willReturn([['member_id' => self::MEMBER_ID, 'value' => 'for']]);
    
    $this->injectRepos([
        MotionRepository::class => $motionRepo,
        BallotRepository::class => $ballotRepo,
    ]);
    
    $resp = $this->callController(BallotsController::class, 'listForMotion');
    $this->assertSame(200, $resp['status']);
    $this->assertArrayHasKey('ballots', $resp['body']);
}
```

**Testing with Reflection (Advanced):**
Used in `ControllerTestCase` to inject mocks into final RepositoryFactory singleton:
```php
$ref = new ReflectionClass(RepositoryFactory::class);
$cacheProp = $ref->getProperty('cache');
$cacheProp->setAccessible(true);
$cacheProp->setValue($factory, ['RepoClass::class' => $mockRepo]);
```

## Controller Test Base Class

**ControllerTestCase (tests/Unit/ControllerTestCase.php):**
Provides infrastructure for all controller tests:

**Methods:**
- `setUp()`: Reset RepositoryFactory, superglobals, Request cache, AuthMiddleware
- `tearDown()`: Clean up all global state
- `injectRepos(array<class-string, object> $repoMocks)`: Inject mock repositories into RepositoryFactory singleton
- `callController(string $class, string $method): array{status: int, body: array}`: Instantiate controller and call handle(), catching ApiResponseException
- `injectJsonBody(array)`: Set Request::cachedRawBody for JSON body tests
- `setHttpMethod(string)`: Set $_SERVER['REQUEST_METHOD']
- `setQueryParams(array)`: Set $_GET
- `setAuth(string $userId, string $role, string $tenantId)`: Inject test user into AuthMiddleware

**Example Usage:**
```php
class BallotsControllerTest extends ControllerTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->setAuth('operator-01', 'operator', 'tenant-1');
    }

    public function testCastVote(): void {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['motion_id' => self::MOTION_ID, 'value' => 'for']);
        
        $mockMotion = $this->createMock(MotionRepository::class);
        $this->injectRepos([MotionRepository::class => $mockMotion]);
        
        $resp = $this->callController(BallotsController::class, 'cast');
        $this->assertSame(200, $resp['status']);
    }
}
```

## Test Execution Rules (from CLAUDE.md)

**Strict Rules:**
- Always target specific test files: `php vendor/bin/phpunit tests/Unit/FichierConcerne.php --no-coverage`
- Never run full suite except on explicit request
- Use timeout: `timeout 60 php vendor/bin/phpunit tests/Unit/FichierConcerne.php --no-coverage`
- If test fails 2 times in a row: STOP and ask for clarification
- Maximum 3 test executions per task

---

*Testing analysis: 2026-04-07*
