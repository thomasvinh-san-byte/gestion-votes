---
phase: 80-pagination-quality
plan: "02"
subsystem: api
tags: [pdf, dompdf, immutability, snapshot, meeting-reports]

# Dependency graph
requires:
  - phase: 67-pv-officiel-pdf
    provides: generatePdf() with Dompdf and upsertFull() storage
  - phase: 80-01
    provides: MeetingReportRepository.findSnapshot() and upsertFull() method contracts
provides:
  - generatePdf() with QUAL-01 snapshot-first immutability — once validated, PDF is always rebuilt from stored HTML
  - X-PV-Snapshot: true response header when serving from snapshot
affects: [80-03, any feature touching MeetingReportsController.generatePdf]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Snapshot-first PDF serving: check stored HTML before re-querying DB, fall through on failure"
    - "Immutability guard: !$isPreview gates all snapshot read logic"

key-files:
  created: []
  modified:
    - app/Controller/MeetingReportsController.php
    - tests/Unit/MeetingReportsControllerTest.php

key-decisions:
  - "QUAL-01: generatePdf() serves from stored HTML snapshot for validated meetings, bypassing DB re-query"
  - "Preview calls (!$isPreview=false) always bypass snapshot — fresh generation always for brouillon"
  - "Snapshot failure is soft: catch Throwable, fall through to fresh generation — no disruption to first-time PDF generation"

patterns-established:
  - "Snapshot check block: after validation guard, before DB queries — standard position for future controllers"

requirements-completed: [QUAL-01]

# Metrics
duration: 15min
completed: 2026-04-02
---

# Phase 80 Plan 02: Snapshot-First PDF Immutability Summary

**generatePdf() now serves validated-meeting PDFs from stored HTML snapshot (QUAL-01), ensuring byte-for-byte idempotency after first generation via findSnapshot() + X-PV-Snapshot header**

## Performance

- **Duration:** 15 min
- **Started:** 2026-04-02T08:15:00Z
- **Completed:** 2026-04-02T08:34:03Z
- **Tasks:** 1
- **Files modified:** 2

## Accomplishments
- Inserted QUAL-01 snapshot-first block in generatePdf() after validation guard and before DB queries
- Preview calls explicitly bypass the snapshot — always regenerate fresh (brouillon stays brouillon)
- Snapshot read failures are soft: Throwable caught, falls through to full generation without disruption
- X-PV-Snapshot: true header set when serving from snapshot, enabling client-side cache awareness
- Added 3 targeted tests (snapshot present, no snapshot, preview ignores snapshot); all 58 controller tests pass

## Task Commits

Each task was committed atomically:

1. **Task 1: Add snapshot-first logic to generatePdf()** - `11d107c9` (feat)

**Plan metadata:** _(docs commit to follow)_

_Note: TDD task — tests written first in RED state, implementation written in GREEN state._

## Files Created/Modified
- `app/Controller/MeetingReportsController.php` - Added QUAL-01 snapshot-first block (40 lines) in generatePdf()
- `tests/Unit/MeetingReportsControllerTest.php` - Added 3 snapshot immutability tests

## Decisions Made
- Snapshot check uses `!empty($snap['html'])` guard — null or empty HTML falls through to full generation
- On snapshot path, upsertFull is NOT called — snapshot is already stored and must not be overwritten
- Filename on snapshot path uses same `PV_` prefix as fresh generation (consistent naming)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
- Worktree has no vendor/ directory — phpunit invoked via `/home/user/gestion_votes_php/vendor/bin/phpunit` (standard for this project's worktree setup)

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Snapshot immutability complete for generatePdf(); ready for Phase 80-03
- All 58 MeetingReportsControllerTest tests pass with no regressions

## Self-Check: PASSED
- controller: FOUND
- tests: FOUND
- summary: FOUND
- commit 11d107c9: FOUND

---
*Phase: 80-pagination-quality*
*Completed: 2026-04-02*
