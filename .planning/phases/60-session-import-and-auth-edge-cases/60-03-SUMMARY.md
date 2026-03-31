---
phase: 60-session-import-and-auth-edge-cases
plan: 03
subsystem: auth
tags: [php, rate-limiting, session-expiry, audit-log, security, javascript]

requires:
  - phase: 60-01
    provides: session infrastructure and auth foundation

provides:
  - Expired session detection (session_expired error code) differentiated from unauthenticated
  - Frontend redirect to /login.html?expired=1 with French flash message on session expiry
  - Configurable login rate limiting (APP_LOGIN_MAX_ATTEMPTS / APP_LOGIN_WINDOW env vars)
  - 429 response with Retry-After header and French message when rate limit exceeded
  - audit_log('auth_rate_limited') with ip, attempt_count, window on every blocked attempt

affects: [61-final-cleanup, any phase touching AuthMiddleware, login flow, frontend auth-ui]

tech-stack:
  added: []
  patterns:
    - "Static flag pattern in AuthMiddleware for consuming one-shot state between authenticate() and deny()"
    - "RateLimiter::isLimited() check before action + RateLimiter::check(strict=false) to increment counter separately"
    - "ControllerTestCase resets APP_AUTH_ENABLED=0 in setUp to prevent env leakage from unit test files"

key-files:
  created: []
  modified:
    - app/Core/Security/AuthMiddleware.php
    - app/Controller/AuthController.php
    - public/assets/js/pages/auth-ui.js
    - public/assets/js/pages/login.js
    - public/assets/js/core/utils.js
    - tests/Unit/AuthMiddlewareTest.php
    - tests/Unit/AuthControllerTest.php
    - tests/Unit/ControllerTestCase.php

key-decisions:
  - "session_expired code uses a static $sessionExpired flag consumed in deny() — avoids passing extra parameters through the call chain"
  - "deny() resets the flag when consuming it so it cannot fire twice for a single request"
  - "Use ApiResponseException directly (not api_fail) for 429 to support custom Retry-After header"
  - "RateLimiter::check(strict=false) increments counter after isLimited() check — two-step pattern allows isLimited() to read-only while check() tracks the attempt"

patterns-established:
  - "TDD: tests use Reflection to directly set private static flags, enabling isolated unit tests without triggering full authenticate() flow"
  - "Rate limit test isolation: setUp/tearDown call RateLimiter::reset() per IP to prevent cross-test counter leakage"

requirements-completed: [AUTH-01, AUTH-02]

duration: 5min
completed: 2026-03-31
---

# Phase 60 Plan 03: Auth Edge Cases Summary

**Expired sessions return distinct `session_expired` error code (vs `authentication_required`), with frontend redirect to `/login.html?expired=1`; login rate-limited at 5 attempts/300s window (configurable via env vars) with 429/Retry-After and audit log.**

## Performance

- **Duration:** 5 min
- **Started:** 2026-03-31T09:56:17Z
- **Completed:** 2026-03-31T10:01:00Z
- **Tasks:** 2
- **Files modified:** 8

## Accomplishments

- AUTH-01: AuthMiddleware now flags expired sessions via `$sessionExpired` static property; `deny()` replaces `authentication_required` with `session_expired` when flag is set; flag is cleared on `reset()`
- AUTH-01: auth-ui.js intercepts `session_expired` from whoami and redirects to `/login.html?expired=1`; login.js shows French info message; utils.js adds error dict entry
- AUTH-02: `AuthController::login()` checks `RateLimiter::isLimited()` before credentials, returns 429 with `Retry-After` header, French message, and `audit_log('auth_rate_limited')` with ip/attempt_count/window

## Task Commits

1. **Task 1: Differentiate session expiry in AuthMiddleware** - `61bfe67b` (feat)
2. **Task 2: Rate limiting with audit log in AuthController** - `0f240d0e` (feat)

## Files Created/Modified

- `app/Core/Security/AuthMiddleware.php` - Added `$sessionExpired` static flag, set in expire block, consumed in `deny()`, cleared in `reset()`
- `app/Controller/AuthController.php` - Added rate limit check/increment at start of `login()` with env var thresholds and audit log
- `public/assets/js/pages/auth-ui.js` - Detects `session_expired` error in `boot()` and redirects to `/login.html?expired=1`
- `public/assets/js/pages/login.js` - Reads `?expired=1` param and shows French flash message in successBox
- `public/assets/js/core/utils.js` - Added `session_expired` entry to ERROR_MESSAGES dictionary
- `tests/Unit/AuthMiddlewareTest.php` - 3 new tests: expired returns session_expired code, non-expired returns authentication_required, reset clears flag
- `tests/Unit/AuthControllerTest.php` - 5 new tests: rate limit audits, env var thresholds, retry_after in body, Retry-After header, French message
- `tests/Unit/ControllerTestCase.php` - setUp resets `APP_AUTH_ENABLED=0` to prevent env leakage from AuthMiddlewareTest

## Decisions Made

- Used a static `$sessionExpired` flag rather than passing an extra parameter through `authenticate()` → `requireRole()` → `deny()`. The flag is consumed and cleared immediately in `deny()` to avoid double-firing.
- `ApiResponseException` used directly (not `api_fail`) for the 429 response because `api_fail()` does not support custom response headers, and `Retry-After` is required.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Rate limit counter state leaked between controller tests**
- **Found during:** Task 2 (AuthControllerTest regression)
- **Issue:** Rate limit tests with `APP_LOGIN_MAX_ATTEMPTS=1` incremented the file-based counter for `127.0.0.1`, causing subsequent login tests to receive 429 instead of expected 400/401
- **Fix:** Added `RateLimiter::reset('auth_login', ip)` in `AuthControllerTest::setUp()` and `tearDown()` for all test IPs; added `putenv('APP_LOGIN_MAX_ATTEMPTS=')` in tearDown
- **Files modified:** tests/Unit/AuthControllerTest.php
- **Verification:** Full suite passes: 2267 tests OK

**2. [Rule 1 - Bug] APP_AUTH_ENABLED env var leakage from AuthMiddlewareTest to AuthControllerTest**
- **Found during:** Task 2 verification (combined test run)
- **Issue:** AuthMiddlewareTest sets `APP_AUTH_ENABLED=1`, tearDown clears to empty string (not `'0'`); empty string causes `isEnabled()` to return true (deny-by-default), breaking whoami test expecting auth-disabled demo user
- **Fix:** Added `putenv('APP_AUTH_ENABLED=0')` to `ControllerTestCase::setUp()` so all controller tests start with auth disabled regardless of other test files' env state
- **Files modified:** tests/Unit/ControllerTestCase.php
- **Verification:** Full combined run passes: 55/55 for auth tests, 2267/2267 for full unit suite

---

**Total deviations:** 2 auto-fixed (both Rule 1 - test isolation bugs)
**Impact on plan:** Both fixes necessary for test correctness. No scope creep.

## Issues Encountered

None beyond the auto-fixed test isolation bugs.

## Next Phase Readiness

- AUTH-01 and AUTH-02 requirements fulfilled
- Phase 61 (final cleanup) can proceed — both Phase 59 and Phase 60 now complete
- No blockers

---
*Phase: 60-session-import-and-auth-edge-cases*
*Completed: 2026-03-31*

## Self-Check: PASSED

- SUMMARY.md: FOUND
- AuthMiddleware.php: FOUND
- AuthController.php: FOUND
- Task 1 commit (61bfe67b): FOUND
- Task 2 commit (0f240d0e): FOUND
