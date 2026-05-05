---
phase: 04-query-n1-http-cache
plan: 01
subsystem: performance

tags: [n+1, batch-queries, postgres, repository-pattern, htmx, dashboard]

# Dependency graph
requires:
  - phase: 03-pre-v2.7
    provides: Stable Repository layer with AbstractRepository::buildInClause helper
provides:
  - .planning/v2.7-N+1-AUDIT.md (cartographie N+1 dans app/Controller/)
  - BallotRepository::countByMotionIds(): batch count for dashboard hot path
  - InvitationRepository::findStatusesByMeetingAndMembers(): batch status lookup for bulk email
  - AttendanceRepository::upsertModeBulk(): batch INSERT...ON CONFLICT for attendance
  - VoteTokenRepository::deleteUnusedByMotionAndMembers() + insertMany(): batch token ops
  - MemberGroupRepository::findManyByIds(): batch group existence lookup
affects: [04-query-n1-http-cache (plan 04-02 — HTTP cache layer touches DashboardController)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Batch repository methods returning maps indexed by entity ID"
    - "Empty-input guards: short-circuit return [] without prepare() call"
    - "Default-zero pre-fill via array_fill_keys() for missing IDs"
    - "PHPUnit PDO mock with prepare() expects(once()) gate as Nyquist regression test"

key-files:
  created:
    - .planning/v2.7-N+1-AUDIT.md
    - tests/Unit/Repository/BallotRepositoryBatchCountsTest.php
    - tests/Unit/Repository/InvitationRepositoryBatchStatusTest.php
    - tests/Unit/Repository/AttendanceRepositoryBatchUpsertTest.php
    - tests/Unit/Repository/VoteTokenRepositoryBatchTest.php
    - tests/Unit/Repository/MemberGroupRepositoryBatchFindTest.php
    - tests/Unit/Controller/DashboardControllerN1Test.php
  modified:
    - app/Repository/BallotRepository.php
    - app/Repository/InvitationRepository.php
    - app/Repository/AttendanceRepository.php
    - app/Repository/VoteTokenRepository.php
    - app/Repository/MemberGroupRepository.php
    - app/Controller/DashboardController.php
    - app/Controller/EmailController.php
    - app/Controller/AttendancesController.php
    - app/Controller/VoteTokenController.php
    - app/Controller/MemberGroupsController.php

key-decisions:
  - "BallotRepository::countByMotionIds returns map with 0-default via array_fill_keys (not absent keys) — keeps caller code simple via direct lookup"
  - "VoteTokenController split into 2 batch round-trips (DELETE then INSERT) instead of fused MERGE — preserves existing ON CONFLICT semantics and audit clarity"
  - "Batch upsertModeBulk uses RETURNING (xmax = 0) AS inserted — same created/updated detection as single-row variant"
  - "Audit found only 5 real N+1 sites in app/Controller/ across 55 foreach occurrences — codebase already largely eager-loaded"
  - "Soft conflict with plan 04-02 in DashboardController.php: this plan modifies foreach L118-132, plan 04-02 swaps the api_ok($data) call. Different lines, low merge risk. Flagged for orchestrator awareness."

patterns-established:
  - "Pattern: All new batch methods follow signature `(array $ids, ...$context): array<id, value>` with empty-input guard"
  - "Pattern: Nyquist gate test — `mockPdo->expects($this->once())->method('prepare')` proves N->1 round-trip reduction"
  - "Pattern: Test file lives in tests/Unit/Repository/ subdir with `Tests\\Unit\\Repository` namespace, uses PDO+PDOStatement mocks"

requirements-completed: [PERF-V27-01, PERF-V27-02]

# Metrics
duration: 35min
completed: 2026-05-05
---

# Phase 4 Plan 01: N+1 Audit + Hot Path Batch Refactor Summary

**Audit livré + 5 hot paths refactorés via méthodes batch (DashboardController, EmailController, AttendancesController, VoteTokenController, MemberGroupsController) — tous prouvés N->1 query par tests PHPUnit avec PDO mock counter.**

## Performance

- **Duration:** ~35 min
- **Started:** 2026-05-05T11:33:00Z (approximate)
- **Completed:** 2026-05-05T12:08:32Z
- **Tasks:** 2 (audit + refactor TDD)
- **Files modified:** 17 (5 repos, 5 controllers, 1 audit doc, 6 test files)

## Accomplishments

- **Audit cartographique** : 55 foreach occurrences classées dans 25 controllers ; 5 vraies N+1 + 1 deferred (DevSeed cold path) + 4 PASS-EAGER + 45 PASS pures.
- **Flagship fix** : `DashboardController::index()` ligne 120 — supprimé le `foreach + countForMotion` (était 1 + N queries) → batch `countByMotionIds` (2 queries). Pour 10 motions closed : **11 queries → 2 queries (5x reduction)**.
- **EmailController::sendBulkInvitations()** : pre-fetch des statuts en une requête quand `only_unsent=true` (était N findStatusByMeetingAndMember calls).
- **AttendancesController::bulkUpdate()** : batch `INSERT … ON CONFLICT … RETURNING (xmax=0)` (était N upsertMode calls).
- **VoteTokenController::generate()** : 2 round-trips au total (1 DELETE batch + 1 INSERT batch) au lieu de 2*N (était delete+insert par voter).
- **MemberGroupsController::assignMemberToGroups()** : batch `findManyByIds` pour valider l'existence de tous les groupes en une requête.
- **20 nouveaux tests PHPUnit** dont 6 Nyquist gates (`prepare()->expects($this->once())`) prouvant que la réduction N->1 est régression-protégée.

## Task Commits

1. **Audit doc** — `199ce65` (docs)
2. **RED tests (Task 2 TDD)** — `21d6123` (test) — 6 tests failed as expected (undefined method `countByMotionIds`)
3. **Fix 1: DashboardController flagship** — `b7b62e0` (perf) — GREEN, 6/6 tests pass
4. **Fix 2: EmailController invitation status** — `3742242` (perf) — 3 batch tests
5. **Fix 3: AttendancesController bulk upsert** — `6109d75` (perf) — 3 batch tests
6. **Fix 4: VoteTokenController batch del+ins** — `9f11569` (perf) — 4 batch tests
7. **Fix 5: MemberGroupsController batch find** — `2f33725` (perf) — 4 batch tests

**Total:** 7 atomic commits, 39 tests pass (20 new + 19 pre-existing dashboard tests).

## Files Created/Modified

### Created
- `.planning/v2.7-N+1-AUDIT.md` — Audit document with hot/warm/cold classification
- `tests/Unit/Repository/BallotRepositoryBatchCountsTest.php` — 4 tests, Nyquist gate
- `tests/Unit/Repository/InvitationRepositoryBatchStatusTest.php` — 3 tests, Nyquist gate
- `tests/Unit/Repository/AttendanceRepositoryBatchUpsertTest.php` — 3 tests, Nyquist gate
- `tests/Unit/Repository/VoteTokenRepositoryBatchTest.php` — 4 tests, 2 Nyquist gates
- `tests/Unit/Repository/MemberGroupRepositoryBatchFindTest.php` — 4 tests, Nyquist gate
- `tests/Unit/Controller/DashboardControllerN1Test.php` — 2 tests (mock-based regression)

### Modified
- `app/Repository/BallotRepository.php` — Added `countByMotionIds()` batch method
- `app/Repository/InvitationRepository.php` — Added `findStatusesByMeetingAndMembers()` batch
- `app/Repository/AttendanceRepository.php` — Added `upsertModeBulk()` batch INSERT...ON CONFLICT
- `app/Repository/VoteTokenRepository.php` — Added `deleteUnusedByMotionAndMembers()` + `insertMany()`
- `app/Repository/MemberGroupRepository.php` — Added `findManyByIds()` batch lookup
- `app/Controller/DashboardController.php` — Pre-fetch ballotsCounts before foreach $closed
- `app/Controller/EmailController.php` — Pre-fetch statusesByMember when only_unsent
- `app/Controller/AttendancesController.php` — Single batch call inside api_transaction
- `app/Controller/VoteTokenController.php` — Build rows array, then 2 batch calls
- `app/Controller/MemberGroupsController.php` — UUID validation loop + single batch lookup

## Decisions Made

1. **Batch return shape: map indexed by ID with 0-default.** `BallotRepository::countByMotionIds()` returns `array_fill_keys($motionIds, 0)` then overlays found rows. This lets the caller use `$map[$id] ?? 0` without needing to handle missing keys explicitly.
2. **Empty-input guard at every batch method.** `if (count($ids) === 0) return [];` short-circuits so that callers never pay for an empty `IN ()` clause (which would also be invalid SQL).
3. **VoteTokenController: 2 batch calls (DELETE + INSERT) not 1 fused MERGE.** Preserves the existing semantics (`ON CONFLICT (token_hash) DO NOTHING`) and keeps the controller logic readable. The audit trail event order is unchanged.
4. **Test pattern: PDO mock with `expects($this->once())->method('prepare')`.** Borrowed from existing `MeetingStatsRepositoryTest::testGetDashboardStatsExecutesExactlyOneQuery()` — proven pattern in the codebase, no new test infra introduced.
5. **DashboardControllerN1Test uses `BallotRepository` mock with `expects($this->never())->method('countForMotion')`** — guards against regression where someone re-introduces the per-motion call inside the loop.

## Deviations from Plan

None significant. Plan executed as written with one small adaptation:

### Plan adaptation (not a deviation)

The plan suggested *either* adding `countByMotionIds` *or* enriching `MotionListTrait::listClosedWithManualTally` with a JOIN. Chose the dedicated batch method (option A) because:
- Cleaner separation of concerns (motion listing doesn't conflate with ballot counting)
- The new batch method is reusable by other call-sites
- Easier to write a focused single-responsibility test

## Issues Encountered

None. All tests passed on first GREEN run for each of the 5 fixes.

## Soft Conflict Notice (for orchestrator awareness)

**Plan 04-02 (HTTP cache) will also modify `app/Controller/DashboardController.php`.**
- This plan (04-01) modified lines 118-135 (the closed-motions foreach + new batch pre-fetch).
- Plan 04-02 will swap the final `api_ok($data)` call (around line 142) to add HTTP cache headers.
- Different lines, **low merge risk**. Standard 3-way merge should resolve cleanly.
- Flagged here so the orchestrator can dispatch 04-02 after this plan and watch for the merge cleanly resolving the unrelated edits.

## User Setup Required

None - no external service configuration required. All changes are internal repository + controller refactors, no DB migrations, no env vars.

## Next Phase Readiness

- 5 hot N+1 paths eliminated. Production dashboard now serves a meeting with N closed motions in 2 queries instead of N+1 queries.
- All batch methods follow a consistent signature pattern, ready for future call-sites to adopt.
- Plan 04-02 (HTTP cache) can proceed in parallel — only soft conflict in DashboardController which will resolve via 3-way merge.
- PERF-V27-01 (audit) and PERF-V27-02 (refactor 5+ hot paths) both fully completed.

---

## Self-Check: PASSED

Verified files exist:
- FOUND: .planning/v2.7-N+1-AUDIT.md
- FOUND: tests/Unit/Repository/BallotRepositoryBatchCountsTest.php
- FOUND: tests/Unit/Repository/InvitationRepositoryBatchStatusTest.php
- FOUND: tests/Unit/Repository/AttendanceRepositoryBatchUpsertTest.php
- FOUND: tests/Unit/Repository/VoteTokenRepositoryBatchTest.php
- FOUND: tests/Unit/Repository/MemberGroupRepositoryBatchFindTest.php
- FOUND: tests/Unit/Controller/DashboardControllerN1Test.php

Verified commits exist:
- FOUND: 199ce65 (audit)
- FOUND: 21d6123 (RED tests)
- FOUND: b7b62e0 (DashboardController GREEN)
- FOUND: 3742242 (EmailController fix)
- FOUND: 6109d75 (AttendancesController fix)
- FOUND: 9f11569 (VoteTokenController fix)
- FOUND: 2f33725 (MemberGroupsController fix)

Verified phase-level checks (from PLAN.md `<verification>`):
- audit doc present with DashboardController references: PASS (3 mentions)
- `countForMotion` no longer in DashboardController: PASS (0 occurrences)
- `public function countByMotionIds` in BallotRepository: PASS (1 occurrence)
- All targeted tests pass: PASS (39/39 across 7 test files)
- `php -l` clean on all modified PHP files: PASS

---
*Phase: 04-query-n1-http-cache*
*Completed: 2026-05-05*
