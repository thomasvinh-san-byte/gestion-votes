---
phase: 72-security-config
plan: 02
subsystem: auth
tags: [session, timeout, tenant_settings, security, csrf, php]

# Dependency graph
requires:
  - phase: 72-security-config
    provides: "Phase context for security configuration plans"
provides:
  - "Dynamic session timeout read from tenant_settings.settSessionTimeout (stored as minutes)"
  - "AuthMiddleware::getSessionTimeout() with per-request cache and DB fallback"
  - "CsrfMiddleware token lifetime aligned to dynamic session timeout"
  - "Settings UI field with proper range constraints (5-480 min, step 5)"
affects: [auth, security, session-management]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Test helper setSessionTimeoutForTest() for injecting timeout without mocking final RepositoryFactory"
    - "Bootstrap PSR-4 override to load worktree app/ over main repo in symlinked vendor"

key-files:
  created:
    - tests/Unit/AuthMiddlewareTimeoutTest.php
  modified:
    - app/Core/Security/AuthMiddleware.php
    - app/Core/Security/CsrfMiddleware.php
    - public/settings.htmx.html
    - tests/bootstrap.php

key-decisions:
  - "setSessionTimeoutForTest() test helper pattern used instead of mocking RepositoryFactory (class is final)"
  - "Value stored as minutes in tenant_settings, converted to seconds internally in getSessionTimeout()"
  - "Per-request static cache in AuthMiddleware (cleared by reset()) avoids repeated DB reads"
  - "CsrfMiddleware removes its own TOKEN_LIFETIME constant entirely, always delegates to AuthMiddleware"

patterns-established:
  - "Static cache pattern: $cachedSessionTimeout + $cachedTimeoutTenantId invalidated by reset()"
  - "Test helper pattern: setXxxForTest() bypasses final class mock restrictions"

requirements-completed: [SEC-02]

# Metrics
duration: 8min
completed: 2026-04-02
---

# Phase 72 Plan 02: Dynamic Session Timeout Summary

**Session timeout made configurable via tenant_settings: AuthMiddleware reads settSessionTimeout (minutes) from DB with 5-480 min clamp, CsrfMiddleware delegates to same value, settings UI updated with range constraints**

## Performance

- **Duration:** 8 min
- **Started:** 2026-04-02T05:34:29Z
- **Completed:** 2026-04-02T05:42:00Z
- **Tasks:** 2 completed
- **Files modified:** 5

## Accomplishments
- AuthMiddleware replaces hardcoded SESSION_TIMEOUT=1800 with getSessionTimeout() reading tenant_settings
- Value clamped to 5-480 minutes (300-28800 seconds); falls back to 1800 on missing/invalid value
- CsrfMiddleware removes TOKEN_LIFETIME, uses AuthMiddleware::getSessionTimeout() for alignment
- Settings UI: max=480, step=5, placeholder=30, helper hint text for admin clarity
- 5 unit tests covering default, custom, min clamp, max clamp, and DB error fallback

## Task Commits

Each task was committed atomically:

1. **Task 1: Backend dynamic session timeout** - `7738d226` (feat)
2. **Task 2: Settings UI constraints and hint** - `80ca9b37` (feat)

## Files Created/Modified
- `app/Core/Security/AuthMiddleware.php` - Added getSessionTimeout(), setSessionTimeoutForTest(), cache state, reset() updates
- `app/Core/Security/CsrfMiddleware.php` - Removed TOKEN_LIFETIME, delegates to AuthMiddleware::getSessionTimeout()
- `tests/Unit/AuthMiddlewareTimeoutTest.php` - 5 unit tests for all timeout scenarios
- `public/settings.htmx.html` - max=480, step=5, placeholder=30, helper span
- `tests/bootstrap.php` - PSR-4 override to load worktree app/ via spl_autoload_functions()

## Decisions Made
- Used setSessionTimeoutForTest() helper instead of mocking RepositoryFactory (it is final, cannot be doubled)
- Bootstrap.php updated to override PSR-4 paths using spl_autoload_functions() since require_once returns true when file already loaded by PHPUnit
- Value stored as minutes in DB (matching the UI input), converted to seconds internally

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Bootstrap PSR-4 override for worktree autoloading**
- **Found during:** Task 1 (TDD RED phase)
- **Issue:** Worktree has no vendor directory; after symlinking main vendor, autoloader mapped AgVote\ to main repo's app/, loading unmodified AuthMiddleware
- **Fix:** Updated tests/bootstrap.php to override PSR-4 paths via spl_autoload_functions() loop (require_once returns true if already loaded, so direct return capture failed)
- **Files modified:** tests/bootstrap.php
- **Verification:** Tests now load correct worktree classes; method not found errors resolved
- **Committed in:** 7738d226 (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Required for tests to run at all in worktree environment. No scope creep.

## Issues Encountered
- RepositoryFactory is `final` — cannot use createMock(). Resolved by adding setSessionTimeoutForTest() test helper (same pattern as existing setCurrentUser()) instead of attempting to mock the factory.

## Next Phase Readiness
- Session timeout is now dynamically configurable; admin can set it from /settings Securite tab
- Setting persists in tenant_settings table across server restarts
- Ready for any plan that depends on session configuration being tenant-aware

---
*Phase: 72-security-config*
*Completed: 2026-04-02*
