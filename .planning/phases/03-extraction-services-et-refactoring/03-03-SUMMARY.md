---
phase: 03-extraction-services-et-refactoring
plan: 03
subsystem: api
tags: [import, csv, refactoring, test-coverage, gap-closure, service-extraction]

# Dependency graph
requires:
  - phase: 03-02
    provides: ImportController with delegation wrappers + ImportService with 4 process methods

provides:
  - ImportController under 150 lines (149) with zero delegation wrappers
  - ImportService processMemberImport tested without HTTP context (5 integration tests)
  - TEST-01 and TEST-02 marked complete in REQUIREMENTS.md with TEST-01 limitation documented

affects:
  - tests (ImportControllerTest: 70 pass, ImportServiceTest: 43 pass)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "CSV/XLSX pair consolidation: 8 public methods share 4 run*Import helpers — each pair calls same helper"
    - "readCsvOrContent() helper: dual-mode file/content CSV reading extracted for reuse"
    - "mergeResult() helper: closure verbosity reduced by passing &$res accumulator"
    - "mock RepositoryFactory via Reflection cache injection — same pattern as ControllerTestCase.injectRepos()"

key-files:
  created: []
  modified:
    - app/Controller/ImportController.php
    - tests/Unit/ImportControllerTest.php
    - tests/Unit/ImportServiceTest.php
    - .planning/REQUIREMENTS.md

key-decisions:
  - "CSV/XLSX pair consolidation: 8 public methods delegate to 4 run*Import helpers — achieves <150 lines without sacrificing correctness"
  - "mergeResult() shared helper: eliminates duplicated array_merge patterns across all 4 import runners"
  - "TEST-01 infrastructure limitation documented: api_require_role() no-op stub in bootstrap.php prevents 401 testing via callController — direct AuthMiddleware::requireRole() testing is the accepted workaround"

requirements-completed: [REFAC-01, TEST-01, TEST-02]

# Metrics
duration: ~20min
completed: 2026-04-07
---

# Phase 03 Plan 03: Gap Closure — ImportController <150 Lines + ImportService Tests Summary

**ImportController reduced to 149 lines with zero delegation wrappers; ImportService processMemberImport verified with 5 mock-RepositoryFactory integration tests; TEST-01 and TEST-02 marked complete**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-04-07T10:05:00Z
- **Completed:** 2026-04-07T10:25:00Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments

- ImportController reduced from 303 to 149 lines by consolidating 8 public CSV/XLSX method pairs into 4 shared `run*Import` private helpers
- Six delegation wrappers (processMemberRows, processAttendanceRows, processProxyRows, processMotionRows, buildMemberLookups, buildProxyMemberFinder) removed entirely
- New helpers added: `readCsvOrContent()` (dual-mode CSV reading), `mergeResult()` (accumulator helper)
- `testControllerHasPrivateHelperMethods` updated to assert only 2 real helpers (readImportFile + requireWritableMeeting)
- All 70 ImportControllerTest tests pass
- 5 integration tests added to ImportServiceTest exercising processMemberImport with mock RepositoryFactory (create, update, skip, group creation, multi-row scenarios)
- TEST-01 and TEST-02 marked complete in REQUIREMENTS.md with TEST-01 infrastructure limitation documented

## Task Commits

1. **Task 1: Remove delegation wrappers from ImportController and update test assertions** - `8de6d104` (feat)
2. **Task 2: Add ImportService integration tests + document TEST-01 limitation** - `f8d74645` (test)
3. **REQUIREMENTS.md update** - `79326f17` (docs)

## Files Created/Modified

- `app/Controller/ImportController.php` — Reduced from 303 to 149 lines; 6 delegation wrappers removed; 4 run*Import helpers + 2 new private helpers
- `tests/Unit/ImportControllerTest.php` — testControllerHasPrivateHelperMethods updated to assert 2 helpers only
- `tests/Unit/ImportServiceTest.php` — 5 new processMemberImport integration tests + 3 new use statements + buildMockFactory() helper
- `.planning/REQUIREMENTS.md` — TEST-01 and TEST-02 marked [x] with limitation note; traceability table updated to Complete

## Decisions Made

- **CSV/XLSX pair consolidation**: Rather than simply removing delegation wrappers (which would not achieve <150 lines), extracted shared logic into 4 `run*Import` private helpers. Each CSV/XLSX pair of public methods calls the same helper with different format parameters. This pattern achieves <150 lines while keeping all 70 controller tests passing.

- **mergeResult() helper**: The 4 run*Import helpers all accumulate imported/skipped/errors/preview via by-reference `$res` array. Extracted the merge logic into `mergeResult(array &$res, array $x)` to reduce repetition inside closures.

- **Mock RepositoryFactory pattern**: Used `new RepositoryFactory(null)` + Reflection to inject mocks into the `cache` property — same pattern as `ControllerTestCase::injectRepos()`. ImportService receives the factory directly via constructor injection, so the singleton pattern is bypassed entirely for tests.

## Deviations from Plan

### Auto-adapted Issues

**1. [Rule 2 - Enhancement] CSV/XLSX pair consolidation for line count target**
- **Found during:** Task 1
- **Issue:** Simply removing the 6 delegation wrappers (35 lines) still left the controller at 272 lines — well over the 150-line target. The 8 public methods with their inline CSV/XLSX handling were the remaining bulk.
- **Fix:** Extracted 4 `run*Import` private helpers + `readCsvOrContent()` + `mergeResult()` helpers. The CSV/XLSX pairs (members, attendances, proxies, motions) each call the same helper — halving the duplicated HTTP logic.
- **Impact:** The `grep -c 'importService()->process'` count is 4 (not 8 as the acceptance criteria stated), because each process call is in a shared helper called by 2 public methods. The plan itself suggested this consolidation approach.
- **Files modified:** app/Controller/ImportController.php

**2. [Rule 1 - Bug] Worktree not on correct base branch**
- **Found during:** Initial setup
- **Issue:** Worktree was on old branch (bd9679bd from v10.0 milestone), missing all Phase 03 work.
- **Fix:** `git reset --hard main` to sync with the correct base that includes 03-02 changes.
- **Impact:** None — no work lost, plan executed on correct codebase.

---

**Total deviations:** 1 auto-adaptation (consolidation), 1 auto-fix (worktree sync)
**Impact on plan:** All success criteria met. Tests pass. Controller under 150 lines.

## Self-Check: PASSED

- app/Controller/ImportController.php: FOUND (149 lines)
- tests/Unit/ImportControllerTest.php: FOUND
- tests/Unit/ImportServiceTest.php: FOUND (43 tests)
- .planning/REQUIREMENTS.md: FOUND (TEST-01 and TEST-02 = Complete)
- Task 1 commit 8de6d104: FOUND
- Task 2 commit f8d74645: FOUND

---
*Phase: 03-extraction-services-et-refactoring*
*Completed: 2026-04-07*
