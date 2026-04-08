---
phase: 02-optimisations-memoire-et-requetes
plan: "03"
subsystem: email
tags: [pagination, memory, batch-processing, email-queue, memberrepository]

requires:
  - phase: 02-optimisations-memoire-et-requetes
    provides: "Context for memory/query optimisation work on email scheduling"

provides:
  - "MemberRepository::listActiveWithEmailPaginated() with LIMIT/OFFSET/ORDER BY id"
  - "EmailQueueService schedule methods use do-while paginated loop (batch=25) instead of full table load"
  - "processQueue() default batch size reduced from 50 to 25"
  - "sendInvitationsNow() uses paginated fetch instead of array_slice on full dataset"

affects: [email, members, email-queue-worker]

tech-stack:
  added: []
  patterns:
    - "do-while paginated loop: listActiveWithEmailPaginated($tenantId, 25, $offset) with offset += batchSize until count < batchSize"
    - "Single batch for limited sends: listActiveWithEmailPaginated($tenantId, $limit, 0)"

key-files:
  created: []
  modified:
    - app/Repository/MemberRepository.php
    - app/Services/EmailQueueService.php
    - tests/Unit/EmailQueueServiceTest.php

key-decisions:
  - "ORDER BY id (not full_name) in listActiveWithEmailPaginated for stable OFFSET pagination"
  - "OFFSET pagination acceptable here because email scheduling is idempotent (onlyUnsent check skips already-sent)"
  - "sendInvitationsNow batching extracted to private sendInvitationsNowBatch() helper to avoid nested loops"
  - "processQueue batch size 25 satisfies PERF-04 requirement (was 50)"

patterns-established:
  - "do-while paginated loop pattern for unbounded member iteration in email scheduling"

requirements-completed: [PERF-04]

duration: 5min
completed: 2026-04-07
---

# Phase 02 Plan 03: Optimisations memoire et requetes — Email batch processing Summary

**Replace unbounded member table loads in EmailQueueService with LIMIT/OFFSET paginated batches of 25, preventing OOM for large associations (500+ members)**

## Performance

- **Duration:** 5 min
- **Started:** 2026-04-07T08:09:59Z
- **Completed:** 2026-04-07T08:14:56Z
- **Tasks:** 1
- **Files modified:** 3

## Accomplishments

- Added `MemberRepository::listActiveWithEmailPaginated()` — LIMIT/OFFSET query with stable ORDER BY id
- Refactored all four schedule methods (`scheduleInvitations`, `scheduleReminders`, `scheduleResults`, `sendInvitationsNow`) to use do-while paginated loop — no full table scan
- Reduced `processQueue()` default batch size from 50 to 25 (PERF-04 requirement)
- Preserved original `listActiveWithEmail()` for other callers (EmailController, etc.)
- Extended test suite: 2 new batch-verification tests including a 30-member two-batch test; all 34 tests pass

## Task Commits

1. **Task 1: Add paginated member fetch and update EmailQueueService batch processing** - `b6eeb7f5` (feat)

**Plan metadata:** _(pending final commit)_

## Files Created/Modified

- `app/Repository/MemberRepository.php` — Added `listActiveWithEmailPaginated(tenantId, limit, offset)` after existing `listActiveWithEmail()`
- `app/Services/EmailQueueService.php` — processQueue default 50→25; scheduleInvitations/Reminders/Results refactored to do-while paginated; sendInvitationsNow uses paginated fetch via private helper
- `tests/Unit/EmailQueueServiceTest.php` — All `listActiveWithEmail` mocks updated to `listActiveWithEmailPaginated`; added `testProcessQueueDefaultBatchSizeIs25` and `testScheduleInvitationsUsesPaginatedFetchInTwoBatches`

## Decisions Made

- ORDER BY id chosen over ORDER BY full_name for stable OFFSET pagination — row positions don't shift as names change
- OFFSET pagination accepted as idempotent — email scheduling already skips already-sent members via onlyUnsent check
- `sendInvitationsNow` extracted inner loop to `sendInvitationsNowBatch()` private helper to avoid duplicating the member-processing logic between the `limit > 0` and full-scan branches
- Batch size fixed at 25 in service layer (not configurable) — consistent with processQueue default and PERF-04 requirement

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None — all tests passed on first run.

## Next Phase Readiness

- PERF-04 requirement satisfied: email scheduling no longer loads all members into memory at once
- Pattern established for other unbounded-load candidates in Phase 02 (e.g. ExportService)
- Original `listActiveWithEmail()` kept — any other callers (EmailController) continue to work unchanged

---
*Phase: 02-optimisations-memoire-et-requetes*
*Completed: 2026-04-07*
