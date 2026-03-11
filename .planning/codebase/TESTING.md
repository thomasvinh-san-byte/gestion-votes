# AG-VOTE Testing

## Test Infrastructure

### PHPUnit (Backend)

- **Framework**: PHPUnit 10.5
- **Location**: `tests/Unit/` (63 files), `tests/Integration/` (3 files)
- **Run**: `make test` or `vendor/bin/phpunit`
- **Config**: `phpunit.xml` with Unit + Integration suites, HTML coverage reports

#### Key Unit Tests

| Test | Target |
|------|--------|
| `ApiHelpersTest.php` | Global API helper functions |
| `MeetingsControllerTest.php` | Meeting CRUD |
| `MeetingWorkflowControllerTest.php` | Meeting state transitions |
| `VotePublicControllerTest.php` | Public voting endpoints |
| `BallotsServiceTest.php` | Ballot processing |
| `OfficialResultsServiceTest.php` | Vote result calculation |
| `ExportServiceTest.php` | XLSX/CSV export |
| `ProxiesServiceTest.php` | Proxy vote handling |
| `PermissionCheckerTest.php` | RBAC permissions |
| `RateLimiterTest.php` | Rate limiting logic |
| `StateTransitionCoherenceTest.php` | Meeting state machine |

#### Integration Tests

| Test | Target |
|------|--------|
| `AdminCriticalPathTest.php` | Admin critical path |
| `RepositoryTest.php` | Repository layer |
| `WorkflowValidationTest.php` | Workflow validation |

### Playwright (E2E)

- **Location**: `tests/e2e/`
- **Config**: `tests/e2e/playwright.config.js`
- **14 spec files** covering all major pages:

| Spec | Scope |
|------|-------|
| `auth.spec.js` | Login/logout flows |
| `navigation.spec.js` | Page navigation, sidebar |
| `admin.spec.js` | Admin settings |
| `meetings.spec.js` | Meeting CRUD |
| `members.spec.js` | Member management |
| `analytics.spec.js` | Dashboard charts |
| `vote.spec.js` | Voting flows |
| `trust.spec.js` | Audit trail |
| `docs.spec.js` | Documentation viewer |
| `email-templates.spec.js` | Email template editor |
| `postsession.spec.js` | Post-session review |
| `public-display.spec.js` | Public projector display |
| `accessibility.spec.js` | ARIA, keyboard nav |
| `api-security.spec.js` | API security checks |

### Static Analysis

- **PHPStan** for PHP type checking
- **ESLint 9** with custom `agvote/no-inner-html` rule for XSS prevention
- **php-cs-fixer** for PHP code formatting

### CI Pipeline

- **GitHub Actions**: `.github/workflows/docker-build.yml`
- Triggered on: push to main, tags `v*.*.*`, PRs to main
- Steps: Composer validate → PHPUnit → PHP syntax check → Docker build → smoke test → push to GHCR
- Playwright tests **not** currently in CI

## Testing Patterns

- Unit tests mock repositories and services
- Controllers tested through simulated HTTP requests
- Integration tests validate critical paths
- E2E specs test full user flows in browser

## Coverage Gaps

- **No frontend unit tests**: No Jest/Vitest for JS logic
- **Playwright not in CI**: E2E specs exist but don't run automatically
- **Web Components untested**: No component-level unit tests
- **SSE/real-time untested**: No automated tests for event streams
- **No ESLint/PHPStan in CI**: Only PHPUnit runs in the pipeline
