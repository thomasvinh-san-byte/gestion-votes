# Testing Patterns

**Analysis Date:** 2026-03-16

## Test Framework

### PHP Backend

**Runner:** PHPUnit 10.5
- Config: `phpunit.xml`
- Bootstrap: `tests/bootstrap.php`
- Colors enabled; `failOnRisky`, `failOnWarning` active; `stopOnFailure` disabled

**Run Commands:**
```bash
make test                               # All tests, no coverage (fast)
make test-ci                            # CI mode: coverage + deprecation display
./bin/test.sh                           # Direct: all tests
./bin/test.sh Unit                      # One suite by directory
./bin/test.sh --filter=BallotsCast      # Filter by test name
vendor/bin/phpunit --no-coverage        # Raw PHPUnit
vendor/bin/phpunit --coverage-html coverage-report  # HTML coverage
```

**Test Suites:**
```xml
<testsuite name="Unit">        <!-- tests/Unit/ -->
<testsuite name="Integration"> <!-- tests/Integration/ -->
```

**Coverage Source** (what counts toward coverage):
- `app/Core/` — core framework classes
- `app/Services/` — all service/engine classes
- `app/Repository/` — all repository classes
- `app/WebSocket/` — event broadcaster
- `app/Templates/` — email/vote templates

Excluded from coverage: `app/api.php`, `app/bootstrap.php` (runtime entry points).

**Static Analysis:**
```bash
vendor/bin/phpstan analyse --no-progress  # Level 5, config: phpstan.neon
```

### JavaScript Frontend (E2E)

**Runner:** Playwright 1.50+
- Config: `tests/e2e/playwright.config.js`
- Test dir: `tests/e2e/specs/`

**Run Commands:**
```bash
npm run test:e2e                         # All browsers
npm run test:e2e:chromium                # Chromium only
npx playwright test --config=tests/e2e/playwright.config.js --headed  # Visual
```

---

## Test File Organization

### PHP Unit Tests

Location: `tests/Unit/` (65 files, ~38,000 lines)

Naming: `{ClassName}Test.php` — one test class per source class.

Structure: separate file per domain area, not co-located with source:
```
tests/
├── Unit/
│   ├── BallotsControllerTest.php       (2009 lines)
│   ├── MeetingWorkflowControllerTest.php (2126 lines)
│   ├── MeetingsControllerTest.php      (1801 lines)
│   ├── MotionsControllerTest.php       (1561 lines)
│   ├── VoteEngineTest.php
│   ├── QuorumEngineTest.php
│   └── ...
├── Integration/
│   ├── AdminCriticalPathTest.php
│   ├── RepositoryTest.php
│   └── WorkflowValidationTest.php
├── fixtures/
│   └── csv/                            # CSV import test fixtures
└── bootstrap.php
```

### E2E Tests

Location: `tests/e2e/specs/` (18 spec files, ~1,656 lines)

Naming: `{feature}.spec.js`

```
tests/e2e/
├── playwright.config.js
├── helpers.js                          # Shared login helpers + test credentials
└── specs/
    ├── workflow-meeting.spec.js        # Full meeting lifecycle
    ├── api-security.spec.js            # Security endpoint tests
    ├── ux-interactions.spec.js         # UI interaction tests
    ├── audit-regression.spec.js        # Audit trail regression
    ├── mobile-viewport.spec.js         # Mobile/tablet viewports
    ├── accessibility.spec.js           # ARIA/keyboard nav
    ├── vote.spec.js
    ├── auth.spec.js
    └── ...
```

---

## PHP Test Structure

### Controller Test Pattern

Controller tests intercept `ApiResponseException` (thrown by `api_ok`/`api_fail`)
to inspect HTTP status and response body without a real HTTP server:

```php
class BallotsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset superglobals
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
        $_GET = $_POST = $_REQUEST = [];

        // Reset cached raw body (reflection)
        $ref  = new \ReflectionClass(\AgVote\Core\Http\Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        \AgVote\Core\Security\AuthMiddleware::reset();
    }

    protected function tearDown(): void
    {
        \AgVote\Core\Security\AuthMiddleware::reset();
        // Reset Request cache, unset idempotency key...
        parent::tearDown();
    }

    private function callControllerMethod(string $method): array
    {
        $controller = new BallotsController();
        try {
            $controller->handle($method);
            $this->fail('Expected ApiResponseException was not thrown');
        } catch (ApiResponseException $e) {
            return [
                'status' => $e->getResponse()->getStatusCode(),
                'body'   => $e->getResponse()->getBody(),
            ];
        }
    }
}
```

### Service Test Pattern (Mock Injection)

Services that accept optional repository constructor arguments are tested
using PHPUnit mock objects injected via constructor:

```php
class MeetingWorkflowServiceTest extends TestCase {
    private const TENANT  = 'aaaaaaaa-1111-2222-3333-444444444444';
    private const MEETING = 'bbbbbbbb-1111-2222-3333-444444444444';

    private MeetingRepository&MockObject $meetingRepo;
    private MeetingWorkflowService $service;

    protected function setUp(): void {
        $this->meetingRepo = $this->createMock(MeetingRepository::class);
        // ... other mocks ...
        $this->service = new MeetingWorkflowService(
            $this->meetingRepo,
            $this->motionRepo,
            $this->attendanceRepo,
            $this->userRepo,
            $this->statsRepo,
        );
    }

    public function testIssuesBeforeTransitionReturnsErrorWhenMeetingNotFound(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn(null);

        $result = $this->service->issuesBeforeTransition(self::MEETING, self::TENANT, 'scheduled');

        $this->assertFalse($result['can_proceed']);
        $this->assertCount(1, $result['issues']);
        $this->assertSame('meeting_not_found', $result['issues'][0]['code']);
    }
}
```

### Pure Logic Test Pattern

For services with static pure methods (no I/O), tests call the method directly
with varied inputs to cover edge cases:

```php
// VoteEngineTest.php — tests VoteEngine::computeDecision() static method
public function testComputeDecisionSimpleMajorityAdopted(): void {
    $result = VoteEngine::computeDecision(
        quorumPolicy: null,
        votePolicy: ['base' => 'expressed', 'threshold' => 0.5],
        forWeight: 60.0,
        againstWeight: 30.0,
        abstainWeight: 10.0,
        expressedWeight: 100.0,
        expressedMembers: 10,
        eligibleMembers: 20,
        eligibleWeight: 200.0,
    );

    $this->assertTrue($result['majority']['met']);
    $this->assertEqualsWithDelta(60.0 / 90.0, $result['majority']['ratio'], 0.0001);
    $this->assertFalse($result['quorum']['applied']);
}
```

### Section Organization Pattern

All test files use `// ===` banner comments to divide logical test groups:

```php
// =========================================================================
// DECISION STATUS CALCULATION TESTS
// =========================================================================

// =========================================================================
// MAJORITY CALCULATION TESTS
// =========================================================================

// =========================================================================
// EDGE CASES
// =========================================================================
```

### Test Naming Convention

Descriptive `test` prefix + action description:
- `testResultStructureKeys()`
- `testDecisionStatusNoQuorum()`
- `testComputeDecisionAbstentionAsAgainst()`
- `testIssuesBeforeTransitionReturnsErrorWhenMeetingNotFound()`

---

## Mocking

**Framework:** PHPUnit's built-in `createMock()` / `MockObject`

**What to mock:**
- All repository classes (`*Repository`) when testing services
- `AuthMiddleware` state via `AuthMiddleware::reset()` + session/superglobal setup

**What NOT to mock:**
- Pure static methods (`VoteEngine::computeDecision()` — call directly)
- `InputValidator` — use real instances
- `CsrfMiddleware` — use real class with clean session state

**Session and superglobal reset in setUp:**
```php
$_SESSION = [];
$_POST    = [];
$_SERVER  = [
    'REQUEST_METHOD' => 'GET',
    'REMOTE_ADDR'    => '127.0.0.1',
    'REQUEST_URI'    => '/test',
];
```

---

## Test Bootstrap

`tests/bootstrap.php` provides test stubs for global functions that normally
require a real database or HTTP context:

| Stub | Behavior |
|------|----------|
| `db()` | Throws RuntimeException — forces explicit PDO injection |
| `api_transaction(callable $fn)` | Executes `$fn()` directly (no real transaction) |
| `config(string $key)` | Returns hardcoded test defaults |
| `audit_log(...)` | No-op |
| `api_ok(...)` | Throws `ApiResponseException` (captured by tests) |
| `api_fail(...)` | Throws `ApiResponseException` (captured by tests) |
| `api_method()`, `api_request()`, etc. | Reads from `$_SERVER`/`$_GET`/`$_POST` |
| `api_guard_meeting_not_validated()` | No-op |
| `api_require_role()` | No-op |

`RateLimiter` is configured to use `sys_get_temp_dir()` for file-based storage
in tests to avoid production Redis dependency.

---

## Integration Tests

`tests/Integration/` — tests that require a real PostgreSQL database.

**Skip condition:** Tests automatically skip when no `DATABASE_URL` or
`PG_DSN` env var is set. This allows the CI pipeline to run unit tests
without a database service.

**Run locally:**
```bash
DATABASE_URL="pgsql:host=localhost;dbname=agvote_test;user=agvote;password=agvote" \
  vendor/bin/phpunit tests/Integration/RepositoryTest.php
```

**Setup:** `setUpBeforeClass()` loads the full schema from
`database/schema-master.sql` into the test database on first run.

**Test IDs:** Use deterministic UUID constants matching the test bootstrap:
```php
private const TENANT_ID  = 'aaaaaaaa-1111-2222-3333-444444444444';
private const MEETING_ID = 'bbbbbbbb-1111-2222-3333-444444444444';
private const MOTION_ID  = 'cccccccc-1111-2222-3333-444444444444';
private const MEMBER_ID  = 'dddddddd-1111-2222-3333-444444444444';
```

---

## E2E Tests (Playwright)

### Configuration

File: `tests/e2e/playwright.config.js`

**Projects (browsers):**
- `chromium` — Desktop Chrome
- `firefox` — Desktop Firefox
- `webkit` — Desktop Safari
- `mobile-chrome` — Pixel 5
- `tablet` — iPad (gen 7)

**Settings:**
- `fullyParallel: true`
- CI: `retries: 2`, `workers: 1`; dev: no retries, unlimited workers
- `trace: 'on-first-retry'`, `screenshot: 'only-on-failure'`
- `forbidOnly: !!process.env.CI` — prevents `.only` in CI

**Local dev server:** `php -S localhost:8000 -t public` (auto-started by Playwright)

### Login Helpers

Shared helpers in `tests/e2e/helpers.js` (CommonJS module):

```js
const CREDENTIALS = {
  operator:  { email: 'operator@ag-vote.local',  password: 'Operator2026!'  },
  admin:     { email: 'admin@ag-vote.local',      password: 'Admin2026!'    },
  voter:     { email: 'votant@ag-vote.local',     password: 'Votant2026!'   },
  president: { email: 'president@ag-vote.local',  password: 'President2026!' },
};

// Usage in specs:
const { loginAsOperator } = require('../helpers');
await loginAsOperator(page);
```

Login flow navigates to `/login.html`, fills `#email` + `#password`,
clicks `#submitBtn`, waits for redirect away from `/login`.

### E2E Test Structure

```js
// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator, E2E_MEETING_ID } = require('../helpers');

test.describe('Feature Name', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsOperator(page);
  });

  test('should do something specific', async ({ page }) => {
    await page.goto('/operator.htmx.html');
    await page.waitForLoadState('networkidle');

    const element = page.locator('#someId');
    await expect(element).toBeVisible({ timeout: 10000 });
  });

  // API-level tests use request fixture (no page/browser)
  test('should reject unauthenticated request', async ({ request }) => {
    const response = await request.post('/api/v1/ballots_cast.php', {
      data: { motion_id: 'bad', value: 'for' },
    });
    expect(response.status()).toBeGreaterThanOrEqual(400);
  });
});
```

### E2E Fixtures

The E2E seed `database/seeds/04_e2e.sql` provides deterministic test data.
Key IDs available from `helpers.js`:

```js
const E2E_MEETING_ID = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001';
const E2E_MOTION_1   = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00301';
const E2E_MOTION_2   = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00302';
const E2E_MEMBER_1   = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00101';
```

### CSV Test Fixtures

`tests/fixtures/csv/` — CSV files for import testing:
- `valid_basic.csv`, `valid_with_groups.csv`, `valid_large_50.csv`
- `invalid_missing_name.csv`, `invalid_mixed_errors.csv`
- `edge_accents_unicode.csv`, `valid_english_headers.csv`

---

## Coverage

**Requirements:** No enforced minimum coverage target.

**Output formats:** HTML report (`coverage-report/`) and stdout text.
Coverage only collected when running `make test-ci` or with explicit
`--coverage-html` flag. Default `make test` skips coverage for speed.

**What is measured:** `app/Core/`, `app/Services/`, `app/Repository/`,
`app/WebSocket/`, `app/Templates/`.

**What is NOT measured:** Controllers (`app/Controller/`), entry points
(`app/api.php`, `app/bootstrap.php`), templates, views.

---

## CI Integration

File: `.github/workflows/docker-build.yml`
Trigger: push to `main`, tags `v*.*.*`, PRs to `main`

**`validate` job** (runs PHPUnit):
1. `composer validate --strict`
2. `composer check-platform-reqs --no-dev`
3. `composer install --optimize-autoloader`
4. `vendor/bin/phpunit --no-coverage`  ← **all unit + integration tests**
5. `vendor/bin/phpstan analyse --no-progress`
6. `find app/ -name '*.php' | xargs -n1 php -l` (syntax check)
7. PHP version parity check (Dockerfile vs `composer.json`)

**`lint-js` job** (runs ESLint — parallel to `validate`):
1. `npm ci`
2. `npm run lint:ci` (errors fail; up to 289 innerHTML warnings allowed)

**`build` job** (depends on both above):
1. Docker build + smoke test (PHP extensions, autoload)
2. Push to GHCR (on non-PR only)

**E2E tests are NOT in CI.** Playwright tests require a running app server
with seeded database and are intended for local pre-deploy verification.

---

## Test Coverage Gaps

| Gap | Files affected | Risk | Priority |
|-----|----------------|------|----------|
| Controllers not in coverage source | `app/Controller/` (44 files) | Behavior regressions caught only by integration/E2E | Medium |
| E2E not in CI pipeline | `tests/e2e/specs/` (18 files) | UI regressions ship undetected | High |
| No JS unit tests | `public/assets/js/` | Page script bugs undetectable without browser | Medium |
| Web Components untested | `public/assets/js/components/` (20 components) | Component regression | Low |
| SSE/real-time not tested | `public/assets/js/core/event-stream.js` | Live update failures | Low |
| Integration tests skip without DB | `tests/Integration/` (3 files) | Repository SQL errors undetected in CI | Medium |

---

*Testing analysis: 2026-03-16*
