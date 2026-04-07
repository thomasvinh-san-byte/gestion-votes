---
phase: 03-extraction-services-et-refactoring
plan: 02
subsystem: api
tags: [import, csv, xlsx, refactoring, service-extraction, dependency-injection]

# Dependency graph
requires:
  - phase: 03-01
    provides: Characterization tests for AuthMiddleware and RgpdExportController

provides:
  - ImportService with DI constructor and 4 public process methods (members, attendances, proxies, motions)
  - ImportService.checkDuplicateEmails as public static returning duplicate list
  - ImportController reduced from 921 to 303 lines (67% reduction)
  - Business logic separated from HTTP orchestration

affects:
  - 03-03 (MotionsController extraction will follow same delegation wrapper pattern)
  - tests (ImportControllerTest: all 70 pass, no changes needed)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Nullable DI constructor: ImportService(?RepositoryFactory $repos = null) — same pattern as RgpdExportService"
    - "Delegation wrapper: controller private method delegates to service, keeps method signature for test compatibility"
    - "Service process methods return value arrays instead of mutating by reference"

key-files:
  created: []
  modified:
    - app/Services/ImportService.php
    - app/Controller/ImportController.php

key-decisions:
  - "Delegation wrappers kept in ImportController to satisfy testControllerHasPrivateHelperMethods test — private methods kept as 1-3 line stubs that call ImportService"
  - "checkDuplicateEmails changed from throwing InvalidArgumentException to returning duplicate list — allows controller to pass full duplicate_emails array in api_fail response (required by test assertions)"
  - "Process methods return value arrays (imported/skipped/errors/preview) instead of mutating by-reference params — cleaner service API"

patterns-established:
  - "Service extraction with delegation wrappers: when tests assert private methods exist in controller, keep thin wrappers that delegate to service rather than removing them"
  - "Static check methods return data (array) rather than throwing or calling HTTP functions — caller decides response format"

requirements-completed: [REFAC-01]

# Metrics
duration: ~10min
completed: 2026-04-07
---

# Phase 03 Plan 02: ImportController Service Extraction Summary

**ImportController reduced 67% (921 to 303 lines) by extracting all business logic into ImportService with nullable DI constructor and 4 typed process methods**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-04-07T09:51:00Z
- **Completed:** 2026-04-07T09:59:25Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- ImportService gains nullable DI constructor (same pattern as RgpdExportService) enabling testability without HTTP context
- 4 public process methods added: processMemberImport, processAttendanceImport, processProxyImport, processMotionImport — each returns a value array instead of mutating references
- checkDuplicateEmails moved to ImportService as public static, returns duplicate list (no HTTP concerns)
- ImportController reduced from 921 to 303 lines with all business logic delegated to ImportService
- All 70 existing ImportControllerTest tests pass without modification

## Task Commits

1. **Task 1: Extract business logic into ImportService with DI constructor** - `a45a0b45` (feat)
2. **Task 2: Slim ImportController to HTTP orchestration shell** - `dd2aa0a7` (feat)

**Plan metadata:** (to be committed with SUMMARY.md)

## Files Created/Modified
- `app/Services/ImportService.php` — Added DI constructor, 4 public process methods, checkDuplicateEmails static, private buildMemberLookups and buildProxyMemberFinder helpers
- `app/Controller/ImportController.php` — Reduced from 921 to 303 lines; public methods delegate to importService(); private methods kept as delegation wrappers

## Decisions Made

- **Delegation wrappers pattern**: The test `testControllerHasPrivateHelperMethods` asserts that processMemberRows, processAttendanceRows, processProxyRows, processMotionRows, buildMemberLookups, and buildProxyMemberFinder exist as private methods in ImportController. Rather than modifying the test, kept these as 1-3 line delegation stubs. This is the pragmatic approach: tests pass, business logic is in the service.

- **checkDuplicateEmails returns array**: Originally threw InvalidArgumentException. Changed to return the duplicate list so the controller can pass `duplicate_emails` array directly in the api_fail response — required by test assertions checking `$result['body']['duplicate_emails']`.

## Deviations from Plan

### Plan Constraint Not Fully Met

**1. [Spec conflict] ImportController is 303 lines, not under 150 as targeted**
- **Found during:** Task 2
- **Issue:** The plan says "controller under 150 lines" AND "all 70 tests pass unchanged". The test `testControllerHasPrivateHelperMethods` requires processMemberRows, processAttendanceRows, processProxyRows, processMotionRows, buildMemberLookups, and buildProxyMemberFinder to exist as private methods in ImportController. Removing these methods breaks the test. Keeping them as delegation wrappers adds ~40 lines.
- **Decision:** Prioritized "all 70 tests pass" (hard success criterion) over the 150-line target. Controller is still 67% smaller than original (921 → 303 lines).
- **Impact:** Business logic is still fully extracted to ImportService. The delegation wrappers are minimal (1-3 lines each). The 150-line target was aspirational given the test constraint.

### Auto-adapted Issues

**2. [Rule 1 - Bug] checkDuplicateEmails signature change**
- **Found during:** Task 2 (test run)
- **Issue:** Two tests (`testMembersCsvDuplicateEmails`, `testMembersCsvDuplicateEmailsCaseInsensitive`) check that `$result['body']['duplicate_emails']` contains the list. Original design threw exception with message only — response lacked the array.
- **Fix:** Changed `checkDuplicateEmails` to return `array` of duplicates (empty if none). Controller checks the return and calls `api_fail` with full `duplicate_emails` array.
- **Files modified:** app/Services/ImportService.php, app/Controller/ImportController.php
- **Committed in:** dd2aa0a7 (Task 2 commit)

---

**Total deviations:** 1 spec conflict (line count), 1 auto-fix (signature)
**Impact on plan:** Core extraction goal achieved. Tests all pass. Business logic fully separated.

## Issues Encountered
None beyond the plan contradiction documented above.

## Next Phase Readiness
- REFAC-01 satisfied: ImportController is pure HTTP dispatch, ImportService holds all business logic
- Pattern established for delegation wrappers when tests assert private method existence
- Ready for 03-03 (MotionsController or AuthMiddleware extraction)

## Self-Check: PASSED

- app/Services/ImportService.php: FOUND
- app/Controller/ImportController.php: FOUND
- 03-02-SUMMARY.md: FOUND
- Task 1 commit a45a0b45: FOUND
- Task 2 commit dd2aa0a7: FOUND

---
*Phase: 03-extraction-services-et-refactoring*
*Completed: 2026-04-07*
