---
phase: 54-service-unit-tests-batch-2
plan: "01"
subsystem: testing
tags: [phpunit, unit-tests, mocks, error-dictionary, email-template, speech]

requires:
  - phase: 53-service-unit-tests-batch-1
    provides: VoteEngineTest, ImportServiceTest, NotificationsServiceTest patterns and bootstrap stubs

provides:
  - ErrorDictionaryTest: 16 tests covering getMessage (known + unknown fallback), hasMessage, getCodes, enrichError
  - EmailTemplateServiceTest: 16 tests covering render, validate, preview, getVariables, renderTemplate, listAvailableVariables, createDefaultTemplates
  - SpeechServiceTest: 19 tests covering getQueue, getMyStatus, toggleRequest, grant, endCurrent, cancelRequest, clearHistory
  - api_uuid4() stub added to tests/bootstrap.php

affects:
  - Phase 54-02 (remaining services: MonitoringService + ResolutionDocumentController)

tech-stack:
  added: []
  patterns:
    - "Static-class tests: no mocks, direct method calls on ErrorDictionary"
    - "Constructor-injection mocks: createMock() for 4 repos, passed via __construct to EmailTemplateService"
    - "Default mock setup in setUp(): meetingRepo/memberRepo return sensible defaults for SpeechService resolveTenant"

key-files:
  created:
    - tests/Unit/ErrorDictionaryTest.php
    - tests/Unit/EmailTemplateServiceTest.php
    - tests/Unit/SpeechServiceTest.php
  modified:
    - tests/bootstrap.php

key-decisions:
  - "api_uuid4() stub added to tests/bootstrap.php (Rule 2 auto-fix) — service calls it during toggleRequest/grant"
  - "SpeechServiceTest default mocks set meetingRepo to return a valid meeting so resolveTenant passes in all tests except the explicit failure test"
  - "EmailTemplateServiceTest uses 16 tests (plan specified 16 min) — all pass"

patterns-established:
  - "Service test setUp: mock all repos via createMock(), inject via constructor, set permissive defaults for shared validation logic (resolveTenant)"
  - "Static service tests: call class methods directly without instantiation"

requirements-completed: [TEST-09, TEST-06, TEST-07]

duration: 15min
completed: 2026-03-30
---

# Phase 54 Plan 01: Service Unit Tests Batch 2 Summary

**PHPUnit tests for ErrorDictionary (static), EmailTemplateService (4-repo mock injection), and SpeechService (3-repo mock injection) covering 51 tests total across message lookups, template rendering/validation, and speech queue state transitions**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-03-30T07:05:00Z
- **Completed:** 2026-03-30T07:20:00Z
- **Tasks:** 3/3
- **Files modified:** 4

## Accomplishments

- ErrorDictionaryTest: 16 tests for pure static French error message lookup and enrichment
- EmailTemplateServiceTest: 16 tests for template render, validate, preview, getVariables (URL building, first-name extraction), renderTemplate (found/not-found), createDefaultTemplates
- SpeechServiceTest: 19 tests for full speech queue lifecycle including resolveTenant guard, all toggleRequest states, grant (from queue/direct/next/empty), endCurrent, cancelRequest (4 paths), clearHistory
- Auto-fix: added `api_uuid4()` stub to `tests/bootstrap.php` (was missing, required by SpeechService)

## Task Commits

1. **Task 1: ErrorDictionaryTest** - `bbc6eef` (feat)
2. **Task 2: EmailTemplateServiceTest** - `3bb43ed` (feat)
3. **Task 3: SpeechServiceTest + api_uuid4 bootstrap stub** - `c820c1e` (feat)

## Files Created/Modified

- `tests/Unit/ErrorDictionaryTest.php` - 16 tests for static ErrorDictionary methods
- `tests/Unit/EmailTemplateServiceTest.php` - 16 tests with 4-repo mock injection
- `tests/Unit/SpeechServiceTest.php` - 19 tests with 3-repo mock injection and state transitions
- `tests/bootstrap.php` - Added `api_uuid4()` stub

## Decisions Made

- SpeechService setUp provides default `findByIdForTenant` returns on meetingRepo and memberRepo so that `resolveTenant` passes silently in all test methods except the explicit exception test, avoiding boilerplate in each test.
- EmailTemplateServiceTest uses exactly 16 tests (matching the plan's `min 16` requirement).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Added api_uuid4() stub to tests/bootstrap.php**
- **Found during:** Task 3 (SpeechServiceTest)
- **Issue:** `api_uuid4()` is called by `SpeechService::toggleRequest()` and `SpeechService::grant()` to generate UUIDs, but the function was not stubbed in `tests/bootstrap.php`. The plan described it as an available stub but it was absent.
- **Fix:** Added a UUID v4 generator stub using `mt_rand` under `if (!function_exists('api_uuid4'))` guard.
- **Files modified:** `tests/bootstrap.php`
- **Verification:** SpeechServiceTest `testToggleRequestCreatesWaitingWhenNoExisting` passes (calls toggleRequest → api_uuid4 internally)
- **Committed in:** `c820c1e` (Task 3 commit)

---

**Total deviations:** 1 auto-fixed (Rule 2 — missing critical stub)
**Impact on plan:** Necessary for SpeechService tests to run. No scope creep.

## Issues Encountered

- Pre-existing failure in `MonitoringServiceTest::testCheckCreatesEmailBacklogAlert` confirmed unrelated to this plan (verified via git stash). Not fixed per scope boundary rule.

## Next Phase Readiness

- Phase 54-02 can proceed: MonitoringService + ResolutionDocumentController tests remain
- api_uuid4() stub now available for any future service tests that invoke UUID generation

---
*Phase: 54-service-unit-tests-batch-2*
*Completed: 2026-03-30*
