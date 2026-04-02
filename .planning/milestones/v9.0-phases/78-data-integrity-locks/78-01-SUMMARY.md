---
phase: 78-data-integrity-locks
plan: 01
subsystem: database
tags: [postgresql, locking, toctou, transactions, proxy, ballot]

# Dependency graph
requires:
  - phase: 59-vote-and-quorum-edge-cases
    provides: BallotsService, ProxiesService, VotePublicController with existing FOR UPDATE patterns
provides:
  - ProxyRepository::hasActiveProxyForUpdate() with FOR UPDATE SELECT
  - ProxiesService::hasActiveProxyForUpdate() delegating to repo
  - BallotsService in-transaction proxy re-check using FOR UPDATE
  - VotePublicController motion row lock before ballot insert
  - DataIntegrityLocksTest covering DATA-01 and DATA-02 paths
affects: [ballot casting, proxy validation, token voting, concurrent vote handling]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - FOR UPDATE SELECT in ProxyRepository for in-transaction re-validation
    - motion row lock acquired before insertFromToken in token-vote transaction

key-files:
  created:
    - tests/Unit/DataIntegrityLocksTest.php
  modified:
    - app/Repository/ProxyRepository.php
    - app/Services/ProxiesService.php
    - app/Services/BallotsService.php
    - app/Controller/VotePublicController.php
    - tests/Unit/BallotsServiceTest.php

key-decisions:
  - "hasActiveProxyForUpdate added to ProxyRepository uses selectAll+FOR UPDATE (not scalar count), consistent with existing countActiveAsGiverForUpdate pattern"
  - "BallotsService in-transaction proxy re-check now calls proxiesService->hasActiveProxyForUpdate instead of hasActiveProxy"
  - "VotePublicController locks motion row before consumeIfValid to prevent concurrent motion close race"
  - "motion_closed_concurrent exception caught and returns 409 Ce vote est clos (same pattern as token_already_used)"

patterns-established:
  - "FOR UPDATE methods: repo exposes ForUpdate variant; service wraps it with same tenantId default logic"
  - "In-transaction re-validation always uses FOR UPDATE variant to prevent TOCTOU"

requirements-completed: [DATA-01, DATA-02]

# Metrics
duration: 3min
completed: 2026-04-02
---

# Phase 78 Plan 01: Data Integrity Locks Summary

**TOCTOU races closed: ProxyRepository gains hasActiveProxyForUpdate() (FOR UPDATE SELECT), BallotsService uses it for in-transaction proxy re-check, and VotePublicController locks the motion row before ballot insert**

## Performance

- **Duration:** 3 min
- **Started:** 2026-04-02T08:02:28Z
- **Completed:** 2026-04-02T08:05:30Z
- **Tasks:** 3
- **Files modified:** 5

## Accomplishments
- ProxyRepository::hasActiveProxyForUpdate() added with FOR UPDATE SELECT, preventing race conditions where proxy is revoked between pre-check and transaction lock acquisition
- ProxiesService::hasActiveProxyForUpdate() added, delegating to repo and using same tenantId default pattern as hasActiveProxy()
- BallotsService::castBallot() in-transaction re-check updated to call hasActiveProxyForUpdate (DATA-01 fix)
- VotePublicController::doVote() transaction now locks motion row via findByIdForTenantForUpdate before consumeIfValid and insertFromToken (DATA-02 fix)
- DataIntegrityLocksTest.php created with 5 passing tests covering both DATA-01 and DATA-02 paths

## Task Commits

Each task was committed atomically:

1. **Task 1: Add hasActiveProxyForUpdate to ProxyRepository and update BallotsService** - `b36bc3e5` (feat)
2. **Task 2: Lock motion row in VotePublicController token-vote transaction** - `4ad97695` (feat)
3. **Task 3: Unit tests for DATA-01 and DATA-02 lock paths** - `efd65e73` (test)

**Plan metadata:** _(docs commit below)_

## Files Created/Modified
- `app/Repository/ProxyRepository.php` - Added hasActiveProxyForUpdate() with FOR UPDATE SELECT
- `app/Services/ProxiesService.php` - Added hasActiveProxyForUpdate() delegating to repo
- `app/Services/BallotsService.php` - Updated in-transaction proxy re-check to use hasActiveProxyForUpdate
- `app/Controller/VotePublicController.php` - Added findByIdForTenantForUpdate lock before insertFromToken; handle motion_closed_concurrent
- `tests/Unit/BallotsServiceTest.php` - Updated testCastBallotThrowsWhenProxyRevokedInsideTransaction to use hasActiveProxyForUpdate for in-transaction path
- `tests/Unit/DataIntegrityLocksTest.php` - 5 new tests covering DATA-01 and DATA-02 paths

## Decisions Made
- Used `selectAll(...FOR UPDATE)` pattern (not scalar count) for hasActiveProxyForUpdate — consistent with existing countActiveAsGiverForUpdate pattern in the same repo
- Motion lock placed before consumeIfValid inside the transaction — ensures the lock is acquired before the irreversible token consumption
- Source-level assertion for DATA-02 VotePublicController (controller uses exit() so cannot be executed in tests)

## Deviations from Plan

**Auto-fix: Updated BallotsServiceTest**

The existing `testCastBallotThrowsWhenProxyRevokedInsideTransaction` test used consecutive calls on `hasActiveProxy` to simulate the in-transaction revocation. After switching BallotsService to `hasActiveProxyForUpdate` for the in-transaction path, this test needed updating to mock `hasActiveProxy` (returns true for pre-check) and `hasActiveProxyForUpdate` (returns false for in-transaction check) separately. Updated inline with Task 1 commit.

---

**Total deviations:** 1 auto-fixed (Rule 1 - test alignment with code change)
**Impact on plan:** Required for test correctness. No scope creep.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- DATA-01 and DATA-02 TOCTOU races are fully closed
- All 73 tests pass (BallotsServiceTest, ProxiesServiceTest, VotePublicControllerTest, DataIntegrityLocksTest)
- No blockers for subsequent phases

---
*Phase: 78-data-integrity-locks*
*Completed: 2026-04-02*
