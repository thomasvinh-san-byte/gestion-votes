# AG-VOTE Testing

## Test Infrastructure

### PHPUnit (Backend)

- **Framework**: PHPUnit 10.5
- **Location**: `tests/Unit/`
- **Run**: `make test` or `vendor/bin/phpunit`
- **Coverage**: ~20 test files covering controllers and services

#### Test Files

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
| `LoggerTest.php` | Logging |

### Static Analysis

- **PHPStan** level 2.1 for PHP type checking
- **ESLint 9** with custom `agvote/no-inner-html` rule for XSS prevention
- **php-cs-fixer** for PHP code formatting

### Playwright (Frontend — ad-hoc)

- No permanent Playwright test suite in the repo
- Ad-hoc test scripts created for UX audit verification
- Screenshots + DOM assertions for visual regression

## Testing Patterns

- Unit tests mock repositories and services
- Controllers tested through simulated HTTP requests
- No integration tests with real database
- No end-to-end test suite committed to repo
- Manual testing via `make dev` + browser

## Coverage Gaps

- **No frontend unit tests**: No Jest, Vitest, or similar JS test framework
- **No E2E test suite**: Playwright scripts are ad-hoc, not committed
- **No API integration tests**: Tests mock the DB layer
- **Web Components untested**: No component-level tests
- **SSE/real-time untested**: No automated tests for event streams
- **No CI pipeline**: Makefile targets only, no GitHub Actions
