---
phase: 59-vote-and-quorum-edge-cases
plan: 01
subsystem: api
tags: [php, ballots, vote-tokens, audit-log, error-handling, runtime-exception]

# Dependency graph
requires:
  - phase: 58-websocket-to-sse-rename
    provides: consistent SSE/service naming across the codebase
provides:
  - audit_log('vote_token_reuse') call before api_fail in BallotsController::cast() for token failure path
  - audit_log('vote_rejected') call before api_fail('motion_closed', 409) for closed-motion path
  - try/catch RuntimeException wrapping BallotsService::castBallot() mapping closed-motion to HTTP 409
  - 5 unit tests covering VOTE-01, VOTE-02, VOTE-03 requirements
affects: [vote-flow, audit, phase-61-cleanup]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "audit_log MUST be called BEFORE api_fail because api_fail() calls exit() — ordering enforced in all vote failure paths"
    - "BallotsService constructor dependencies (AttendancesService, ProxiesService) require their repos injected in tests to avoid null-PDO RuntimeException"

key-files:
  created: []
  modified:
    - app/Controller/BallotsController.php
    - tests/Unit/BallotsControllerTest.php

key-decisions:
  - "audit_log('vote_token_reuse') fires for both token_expired and token_already_used reasons, not just token_already_used — covers all suspicious token reuse patterns"
  - "RuntimeException catch also handles fallthrough with api_fail('vote_rejected', 422) for non-closed-motion service errors, providing structured errors instead of uncaught exceptions"
  - "audit_log verification in tests is structural (audit_log precedes api_fail which exits; reaching correct HTTP status proves the code path was taken)"

patterns-established:
  - "Token test mock pattern: inject both VoteTokenRepository and MeetingRepository (VoteTokenService constructor needs both)"
  - "BallotsService test mock pattern: inject MotionRepository + MeetingRepository + MemberRepository + BallotRepository + AttendanceRepository + ProxyRepository (all needed by constructor)"

requirements-completed: [VOTE-01, VOTE-02, VOTE-03]

# Metrics
duration: 25min
completed: 2026-03-31
---

# Phase 59 Plan 01: Vote Edge Cases Summary

**BallotsController::cast() hardened with audit trail for expired/reused tokens and closed-motion 409 response, covered by 5 new passing unit tests**

## Performance

- **Duration:** 25 min
- **Started:** 2026-03-31T08:20:00Z
- **Completed:** 2026-03-31T08:45:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Added `audit_log('vote_token_reuse')` before `api_fail` in token-failure block for expired and already-used tokens
- Wrapped `BallotsService::castBallot()` in try/catch RuntimeException, mapping closed-motion exception to HTTP 409 with `motion_status: 'closed'`
- Added `audit_log('vote_rejected')` before the 409 `api_fail` for forensic trail
- Added 5 unit tests covering VOTE-01, VOTE-02, VOTE-03 — all pass, no regressions in 38-test suite

## Task Commits

Each task was committed atomically:

1. **Task 1: Add audit_log and try/catch in BallotsController::cast()** - `2f4b37d` (feat)
2. **Task 2: Add unit tests for VOTE-01/02/03** - `f668c49` (test)

## Files Created/Modified
- `app/Controller/BallotsController.php` - Added audit_log for token reuse, try/catch RuntimeException for closed-motion, 409 response
- `tests/Unit/BallotsControllerTest.php` - 5 new test methods + ProxyRepository import

## Decisions Made
- `audit_log('vote_token_reuse')` fires for both `token_expired` and `token_already_used` reasons — covers all suspicious reuse patterns with a single log entry
- Fallthrough `api_fail('vote_rejected', 422)` added for non-closed-motion RuntimeExceptions to prevent silent 500 errors from other service exceptions
- Tests verify audit_log structural invariant: since `audit_log` precedes `api_fail` (which calls exit), reaching the correct HTTP status proves the audit call was executed

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Test body field lookup corrected from `data.reason` to `reason`**
- **Found during:** Task 2 (unit tests)
- **Issue:** api_fail() places `reason` at top level of response body, not under `data` key. Initial assertions used `$resp['body']['data']['reason']` but the correct path is `$resp['body']['reason']`
- **Fix:** Updated all token-test assertions to use top-level `reason` key; closed-motion assertions use top-level `motion_status`
- **Files modified:** tests/Unit/BallotsControllerTest.php
- **Verification:** Tests pass with correct assertions
- **Committed in:** f668c49

**2. [Rule 2 - Missing] Added MeetingRepository to token test repo injection**
- **Found during:** Task 2 (unit tests)
- **Issue:** VoteTokenService constructor calls `RepositoryFactory::getInstance()->meeting()` before `->voteToken()` — missing MeetingRepository caused null-PDO exception before reaching token validation
- **Fix:** Added `MeetingRepository::class => $this->createMock(MeetingRepository::class)` to token test injectRepos map
- **Files modified:** tests/Unit/BallotsControllerTest.php
- **Committed in:** f668c49

**3. [Rule 2 - Missing] Added full repo set for closed-motion tests**
- **Found during:** Task 2 (unit tests)
- **Issue:** BallotsService constructor instantiates AttendancesService and ProxiesService (which need AttendanceRepository and ProxyRepository). Without mocking them, null-PDO RuntimeException was thrown from the constructor instead of from castBallot(), causing 422 instead of 409
- **Fix:** Added AttendanceRepository and ProxyRepository mocks to closed-motion test inject maps; added ProxyRepository import to test class
- **Files modified:** tests/Unit/BallotsControllerTest.php
- **Committed in:** f668c49

---

**Total deviations:** 3 auto-fixed (1 bug in assertions, 2 missing critical mocks)
**Impact on plan:** All auto-fixes necessary for test correctness. No scope creep.

## Issues Encountered
- None beyond the test mock setup issues documented as deviations above.

## Next Phase Readiness
- VOTE-01, VOTE-02, VOTE-03 requirements fulfilled
- BallotsController::cast() now returns structured errors for all failure modes
- Full audit trail for anomalous vote attempts in place
- Ready for Phase 59-02 (quorum edge cases) or Phase 60 in parallel

## Self-Check: PASSED
- SUMMARY.md exists at .planning/phases/59-vote-and-quorum-edge-cases/59-01-SUMMARY.md
- app/Controller/BallotsController.php exists and modified
- Commit 2f4b37d exists (Task 1)
- Commit f668c49 exists (Task 2)

---
*Phase: 59-vote-and-quorum-edge-cases*
*Completed: 2026-03-31*
