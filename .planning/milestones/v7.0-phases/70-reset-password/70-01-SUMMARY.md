---
phase: 70-reset-password
plan: 01
subsystem: auth
tags: [php, password-reset, hmac-sha256, email-queue, rate-limiting]

requires:
  - phase: 62-smtp-template-engine
    provides: Symfony Mailer + EmailQueueRepository for queuing emails
  - phase: 44-login-rebuild
    provides: AuthController + login page as entry point
provides:
  - PasswordResetRepository (insert, findByHash, markUsed, deleteForUser, deleteExpired)
  - PasswordResetService (requestReset, validateToken, resetPassword with HMAC-SHA256 tokens)
  - PasswordResetController (GET/POST /reset-password with 3 HTML templates)
  - password_resets migration and schema
  - Unit tests for service (7) and controller (5)
affects: [70-02]

tech-stack:
  added: []
  patterns: [hmac-sha256-token-hashing, silent-enumeration-protection, html-controller-pattern]

key-files:
  created:
    - database/migrations/20260401_password_resets.sql
    - app/Repository/PasswordResetRepository.php
    - app/Services/PasswordResetService.php
    - app/Controller/PasswordResetController.php
    - app/Controller/PasswordResetRedirectException.php
    - app/Templates/reset_request_form.php
    - app/Templates/reset_newpassword_form.php
    - app/Templates/reset_success.php
    - tests/Unit/PasswordResetServiceTest.php
    - tests/Unit/PasswordResetControllerTest.php
  modified:
    - database/schema-master.sql
    - app/Core/Providers/RepositoryFactory.php
    - app/routes.php

key-decisions:
  - "HMAC-SHA256 token hashing via APP_SECRET (same pattern as VoteTokenService)"
  - "Silent success on unknown/inactive emails — no user enumeration"
  - "Removed final from PasswordResetService to allow PHPUnit mocking"
  - "Rate limiting on request form: 5 attempts per 5 minutes per IP"

patterns-established:
  - "HTML controller pattern: no AbstractController, uses HtmlView::render() + redirect exception for testing"
  - "Token-based flow: generate raw token → hash with HMAC → store hash → email raw → validate by re-hashing"

requirements-completed: [RESET-01, RESET-02, RESET-03]

duration: 15min
completed: 2026-04-01
---

# Plan 70-01: Password Reset Backend Summary

**Secure token-based password reset with HMAC-SHA256 hashing, email queuing, and anti-enumeration protection**

## Performance

- **Duration:** 15 min
- **Started:** 2026-04-01T10:20:00Z
- **Completed:** 2026-04-01T12:30:00Z
- **Tasks:** 2
- **Files modified:** 14

## Accomplishments
- password_resets table with token_hash, expires_at, used_at columns + indexes
- PasswordResetService generates 32-byte tokens, hashes with HMAC-SHA256, queues French email via EmailQueueRepository
- PasswordResetController serves 3 HTML pages (request form, new password form, success) with login.css styling
- Silent behavior on unknown/inactive emails prevents user enumeration
- Tokens expire after 1 hour and are single-use (markUsed + deleteForUser)
- 12 unit tests (7 service + 5 controller) all passing

## Task Commits

1. **Task 1: Migration + Repository + Service + Controller + Routes** - `689e26a8` (feat)
2. **Task 2: Unit tests + final class fix** - `7926b17c` (test)

## Files Created/Modified
- `database/migrations/20260401_password_resets.sql` - password_resets table DDL
- `database/schema-master.sql` - Added password_resets to master schema
- `app/Repository/PasswordResetRepository.php` - CRUD for password_resets
- `app/Core/Providers/RepositoryFactory.php` - Registered passwordReset()
- `app/Services/PasswordResetService.php` - Token generation, validation, password update
- `app/Controller/PasswordResetController.php` - HTTP handler for /reset-password
- `app/Controller/PasswordResetRedirectException.php` - Test-friendly redirect
- `app/Templates/reset_request_form.php` - Email input form
- `app/Templates/reset_newpassword_form.php` - New password form with eye toggles
- `app/Templates/reset_success.php` - Success confirmation page
- `app/routes.php` - /reset-password route registration
- `tests/Unit/PasswordResetServiceTest.php` - 7 service unit tests
- `tests/Unit/PasswordResetControllerTest.php` - 5 controller unit tests

## Decisions Made
- Used HMAC-SHA256 with APP_SECRET for token hashing (consistent with VoteTokenService)
- Removed `final` from PasswordResetService to allow PHPUnit createMock()
- Rate limiting via RateLimiter (5 req / 5 min per IP) on request form POST

## Deviations from Plan

### Auto-fixed Issues

**1. Fixed $this->run() calls in controller test**
- **Found during:** Task 2 (unit tests)
- **Issue:** Tests 2-5 called `$this->run()` (PHPUnit method) instead of `$this->invoke()`, causing infinite recursion
- **Fix:** Replaced all `$this->run()` with `$this->invoke()`
- **Verification:** All 5 controller tests pass

---

**Total deviations:** 1 auto-fixed
**Impact on plan:** Bug fix in test code only. No scope creep.

## Issues Encountered
None beyond the test method name bug.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- /reset-password endpoint fully functional
- Ready for 70-02 to wire login page forgot link

---
*Phase: 70-reset-password*
*Completed: 2026-04-01*
