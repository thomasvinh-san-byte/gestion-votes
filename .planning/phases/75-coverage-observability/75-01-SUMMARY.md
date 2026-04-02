---
phase: 75-coverage-observability
plan: 01
subsystem: testing
tags: [phpunit, coverage, exit-refactor, exceptions, controllers]

# Dependency graph
requires:
  - phase: 65-attachment-upload-serve
    provides: MeetingAttachmentController and ResolutionDocumentController with serve() using exit()
  - phase: 62-smtp-template-engine
    provides: EmailTrackingController with outputPixel() using exit()
provides:
  - FileServedOkException: testable signal for file-serving controllers in PHPUNIT_RUNNING mode
  - EmailPixelSentException: testable signal for pixel output in PHPUNIT_RUNNING mode
  - Happy-path test coverage for MeetingAttachmentController::serve()
  - Happy-path test coverage for ResolutionDocumentController::serve()
  - Happy-path test coverage for EmailTrackingController::outputPixel()
  - coverage-check.sh CTRL_THRESHOLD raised from 60 to 70
affects: [coverage-check, CI pipeline, controller test suite]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "PHPUNIT_RUNNING exception pattern for exit()-based controllers (mirrors AccountRedirectException)"
    - "Call method directly (not via handle()) when testing RuntimeException subclasses"

key-files:
  created:
    - app/Controller/FileServedOkException.php
    - app/Controller/EmailPixelSentException.php
  modified:
    - app/Controller/MeetingAttachmentController.php
    - app/Controller/ResolutionDocumentController.php
    - app/Controller/EmailTrackingController.php
    - tests/Unit/MeetingAttachmentControllerTest.php
    - tests/Unit/ResolutionDocumentControllerTest.php
    - tests/Unit/EmailTrackingControllerTest.php
    - scripts/coverage-check.sh

key-decisions:
  - "FileServedOkException extends RuntimeException — tests call serve() directly (not via handle()) to bypass AbstractController's catch (RuntimeException) handler"
  - "Tests create real temp files under AG_UPLOAD_DIR/meetings/ and AG_UPLOAD_DIR/resolutions/ path structures because serve() calls file_exists()+is_readable() before serving"
  - "coverage-check.sh CTRL_THRESHOLD raised from 60 to 70 to reflect new floor after exit() refactoring"

patterns-established:
  - "PHPUNIT_RUNNING exception pattern: check defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING === true before exit() in controllers"
  - "Direct method call in tests: for methods that throw RuntimeException subclasses, call the method directly rather than via handle() to avoid RuntimeException catch in AbstractController"

requirements-completed: [DEBT-01]

# Metrics
duration: 15min
completed: 2026-04-02
---

# Phase 75 Plan 01: Coverage Observability — Exit() Refactor Summary

**FileServedOkException and EmailPixelSentException enable happy-path testing of three exit()-based controller methods, removing the structural 64.6% coverage ceiling**

## Performance

- **Duration:** 15 min
- **Started:** 2026-04-02T06:20:00Z
- **Completed:** 2026-04-02T06:30:00Z
- **Tasks:** 2
- **Files modified:** 7 (+ 2 created)

## Accomplishments
- Created two new exception classes following the AccountRedirectException pattern exactly
- Refactored `serve()` in MeetingAttachmentController and ResolutionDocumentController to throw `FileServedOkException` in PHPUNIT_RUNNING mode
- Refactored `outputPixel()` in EmailTrackingController to throw `EmailPixelSentException` in PHPUNIT_RUNNING mode
- Added happy-path tests for all three methods — 65 tests, all passing
- Raised `coverage-check.sh` CTRL_THRESHOLD from 60 to 70

## Task Commits

Each task was committed atomically:

1. **Task 1: Create FileServedOkException and EmailPixelSentException** - `3601f21b` (feat)
2. **Task 2: Refactor serve() and outputPixel() + new tests + threshold update** - `3b836e66` (feat)

## Files Created/Modified
- `app/Controller/FileServedOkException.php` - New exception thrown by serve() controllers in test mode
- `app/Controller/EmailPixelSentException.php` - New exception thrown by outputPixel() in test mode
- `app/Controller/MeetingAttachmentController.php` - serve() checks PHPUNIT_RUNNING before exit
- `app/Controller/ResolutionDocumentController.php` - serve() checks PHPUNIT_RUNNING before exit
- `app/Controller/EmailTrackingController.php` - outputPixel() checks PHPUNIT_RUNNING before exit
- `tests/Unit/MeetingAttachmentControllerTest.php` - Added testServeSuccessWithSessionUser()
- `tests/Unit/ResolutionDocumentControllerTest.php` - Added testServeSuccessWithSessionUser()
- `tests/Unit/EmailTrackingControllerTest.php` - Added testPixelOutputThrowsEmailPixelSentException()
- `scripts/coverage-check.sh` - CTRL_THRESHOLD raised from 60 to 70

## Decisions Made
- `FileServedOkException` and `EmailPixelSentException` extend `RuntimeException` (same as the AccountRedirectException pattern). However, `AbstractController::handle()` has a `catch (RuntimeException)` that converts them to `api_fail('business_error', 400)`. The fix: happy-path tests call `serve()` directly on the controller instance, bypassing `handle()`. This cleanly catches the exception.
- Tests create real files under the `AG_UPLOAD_DIR` path structure (defined in test bootstrap as `/tmp/ag-vote-test-uploads`) because `serve()` calls `file_exists()` and `is_readable()` before throwing — the file must exist on disk.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Tests calling serve() directly instead of via callController()**
- **Found during:** Task 2 — GREEN phase (tests still failing after controller refactor)
- **Issue:** `callController()` uses `handle()` which has `catch (RuntimeException $e)` — this catches `FileServedOkException` before it can propagate, converting it to a `business_error` 400 response. The `callController()` helper then found an `ApiResponseException` and returned `{status: 400}` instead of the `FileServedOkException`.
- **Fix:** Changed the happy-path serve() tests to call `$controller->serve()` directly instead of via `callController()`. This lets `FileServedOkException` propagate naturally.
- **Files modified:** tests/Unit/MeetingAttachmentControllerTest.php, tests/Unit/ResolutionDocumentControllerTest.php
- **Verification:** 65 tests pass, 0 failures
- **Committed in:** 3b836e66 (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 — bug in test approach)
**Impact on plan:** Fix was necessary for correctness. No scope creep.

## Issues Encountered
- AbstractController::handle() catches RuntimeException broadly — test helpers that route through handle() cannot observe RuntimeException subclasses thrown by the controller. Documented the direct-call pattern in patterns-established for future reference.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Plan 02 (admin KPI observability) is independent and ready to execute
- Controller coverage threshold now at 70% — future serve() refactoring can continue this pattern for any remaining exit()-based methods

## Self-Check: PASSED

All created files exist. All task commits verified.

---
*Phase: 75-coverage-observability*
*Completed: 2026-04-02*
