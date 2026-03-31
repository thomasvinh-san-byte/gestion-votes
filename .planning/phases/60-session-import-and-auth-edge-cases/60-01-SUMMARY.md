---
phase: 60-session-import-and-auth-edge-cases
plan: 01
subsystem: auth
tags: [state-machine, error-handling, php, unit-tests]

requires:
  - phase: 58-websocket-to-sse-rename
    provides: Stable AuthMiddleware base for extension

provides:
  - Invalid transition 422 response with structured detail (from_status, to_status, human-readable message)
  - Live-session delete guard returning 409 with actionable hint to close first
  - Unit tests for both new behaviors plus regression test for closed-session delete

affects: [61-cleanup, any phase using AuthMiddleware::requireTransition or MeetingsController::deleteMeeting]

tech-stack:
  added: []
  patterns:
    - "api_fail() preferred over self::deny() when extra fields must always be visible (not debug-only)"
    - "Live-specific guard branch before generic non-draft check in deleteMeeting"

key-files:
  created: []
  modified:
    - app/Core/Security/AuthMiddleware.php
    - app/Controller/MeetingsController.php
    - tests/Unit/MeetingWorkflowControllerTest.php
    - tests/Unit/MeetingsControllerTest.php

key-decisions:
  - "Use api_fail() instead of self::deny() in requireTransition() so detail, from_status, to_status are always in response body (deny() hides extras behind debug flag)"
  - "Do NOT audit-log invalid transitions — they are normal user errors, not security events"
  - "Live-specific 409 with meeting_live_cannot_delete precedes generic meeting_not_draft check to give actionable hint"

patterns-established:
  - "State machine error: always include from_status and to_status in body for operator tooling"
  - "Delete guard order: live-specific first, then generic non-draft"

requirements-completed: [SESS-01, SESS-02]

duration: 12min
completed: 2026-03-31
---

# Phase 60 Plan 01: Session Auth Edge Cases Summary

**AuthMiddleware invalid transition returns 422 with structured from_status/to_status detail; live-session delete rejected with 409 and actionable close-first hint**

## Performance

- **Duration:** 12 min
- **Started:** 2026-03-31T10:00:00Z
- **Completed:** 2026-03-31T10:12:00Z
- **Tasks:** 1
- **Files modified:** 4

## Accomplishments
- `AuthMiddleware::requireTransition()` now calls `api_fail()` directly so `detail`, `from_status`, `to_status`, and `allowed` fields are always in the response body (not hidden behind debug mode)
- `MeetingsController::deleteMeeting()` adds a live-specific 409 branch before the generic non-draft check, returning `meeting_live_cannot_delete` with "Fermez d'abord la seance" hint
- 3 new unit tests: invalid transition detail message, live-session delete hint, closed-session delete regression

## Task Commits

Each task was committed atomically:

1. **Task 1: Enrich invalid transition error and add live-session delete guard** - `7e9dd43e` (feat)

**Plan metadata:** (to be committed with SUMMARY)

_Note: TDD task — RED then GREEN phases confirmed before implementation_

## Files Created/Modified
- `app/Core/Security/AuthMiddleware.php` - requireTransition() uses api_fail() with always-visible structured error fields
- `app/Controller/MeetingsController.php` - deleteMeeting() adds live-specific 409 guard before generic check
- `tests/Unit/MeetingWorkflowControllerTest.php` - testInvalidTransitionReturnsDetailMessage added
- `tests/Unit/MeetingsControllerTest.php` - testDeleteLiveMeetingReturns409WithHint, testDeleteClosedMeetingStillRejects added; testDeleteMeetingNonDraftReturns409 updated to use scheduled status

## Decisions Made
- Used `api_fail()` instead of `self::deny()` for invalid transitions because `deny()` gates extra fields behind `self::$debug`, making structured error data invisible in production
- Invalid transitions are not audit-logged (normal user input errors, not security events)
- Updated existing `testDeleteMeetingNonDraftReturns409` to use status='scheduled' (previously used 'live' which now hits the new live-specific branch)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## Next Phase Readiness
- SESS-01 and SESS-02 complete — Phase 60 plan 01 ready for Phase 61 cleanup
- All 310 targeted tests pass; full unit suite at 2262 tests with only 2 pre-existing ImportController failures (unrelated, tracked in 60-02)

---
*Phase: 60-session-import-and-auth-edge-cases*
*Completed: 2026-03-31*
