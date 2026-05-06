---
phase: 02-gardes-backend
plan: 02
subsystem: api
tags: [idempotency, guard, workflow, controllers]

requires:
  - phase: 02-gardes-backend/01
    provides: "IdempotencyGuard class and pattern on MeetingsController/AgendaController/MembersController::create"
provides:
  - "IdempotencyGuard on MotionsController::createOrUpdate and createSimple"
  - "IdempotencyGuard on MembersController::bulk"
  - "Idempotent workflow transitions (launch + transition return success when already in target state)"
affects: [03-frontend-validation]

tech-stack:
  added: []
  patterns: ["already_in_target return pattern for idempotent workflow transitions"]

key-files:
  created: []
  modified:
    - app/Controller/MotionsController.php
    - app/Controller/MembersController.php
    - app/Services/MeetingTransitionService.php
    - app/Controller/MeetingWorkflowController.php
    - tests/Unit/MeetingWorkflowControllerTest.php

key-decisions:
  - "Workflow idempotence returns already_in_target flag in success response rather than 422 error"
  - "Race condition inside lockForUpdate transaction also returns idempotent success"

patterns-established:
  - "already_in_target pattern: service returns array with already_in_target=true, controller returns api_ok with no side effects"

requirements-completed: [IDEM-03, IDEM-04, IDEM-05]

duration: 5min
completed: 2026-04-20
---

# Phase 02 Plan 02: IdempotencyGuard on Motions/Members + Workflow Idempotence Summary

**IdempotencyGuard on 3 more routes (motions create/createSimple, members bulk) plus idempotent workflow transitions returning success when already in target state**

## Performance

- **Duration:** 5 min
- **Started:** 2026-04-20T07:15:54Z
- **Completed:** 2026-04-20T07:20:39Z
- **Tasks:** 3
- **Files modified:** 5

## Accomplishments
- MotionsController::createOrUpdate and createSimple protected by IdempotencyGuard check/store
- MembersController::bulk protected by IdempotencyGuard check/store
- MeetingTransitionService transition() and launch() return idempotent success when already in target state
- MeetingWorkflowController handles already_in_target at pre-check and inside transaction (race condition)
- Updated test to expect 200 idempotent success instead of 422 error

## Task Commits

Each task was committed atomically:

1. **Task 1: Add IdempotencyGuard to MotionsController and MembersController::bulk** - `cb1bc669` (feat)
2. **Task 2: Make workflow transitions idempotent (IDEM-05)** - `d6e5a7f0` (feat)
3. **Task 3: Run existing unit tests and update for IDEM-05 behavior change** - `93e24b39` (test)

## Files Created/Modified
- `app/Controller/MotionsController.php` - Added IdempotencyGuard import, check/store on createOrUpdate and createSimple
- `app/Controller/MembersController.php` - Added IdempotencyGuard check/store on bulk method
- `app/Services/MeetingTransitionService.php` - transition() returns already_in_target instead of throwing; launch() early-returns when already live
- `app/Controller/MeetingWorkflowController.php` - Handles already_in_target from service and from lockForUpdate race condition
- `tests/Unit/MeetingWorkflowControllerTest.php` - Updated testTransitionAlreadyInStatus to expect idempotent 200 success

## Decisions Made
- Workflow idempotence returns `already_in_target` flag in 200 success response rather than 422 error -- enables safe retries
- Race condition inside lockForUpdate transaction also returns idempotent success instead of api_fail -- prevents concurrent request errors
- Removed dead catch branches for "deja au statut" and "deja en cours" since service no longer throws for those cases

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
- Pre-existing test failures in MembersControllerTest::testDelete* (unrelated to changes, not fixed per scope boundary)
- Test assertion needed `body['data']` path instead of `body` for success responses (api_ok wraps in {ok:true, data:...})

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- All Critique-risk routes from audit now have IdempotencyGuard or idempotent behavior
- Ready for Phase 03 (Frontend + Validation) which adds X-Idempotency-Key header from HTMX

---
*Phase: 02-gardes-backend*
*Completed: 2026-04-20*
