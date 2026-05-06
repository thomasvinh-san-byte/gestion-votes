---
phase: 06-application-design-tokens
plan: 04
subsystem: ui
tags: [htmx, skeleton, loading-states, design-tokens, javascript]

# Dependency graph
requires:
  - phase: 06-01
    provides: design-system.css with .htmx-indicator and .skeleton-row CSS already defined

provides:
  - htmx-indicator skeleton rows wired into #agendaList (operator), #membersList (members), #meetingsList (meetings)
  - pv_sent canonical badge mapping in MEETING_STATUS_MAP

affects: [06-05, any phase touching meetings status display or list loading UX]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "htmx-indicator as first child of async list containers — CSS display toggled by .htmx-request parent class"
    - "skeleton-row inside htmx-indicator — reuse existing design-system.css classes, no new CSS"

key-files:
  created: []
  modified:
    - public/operator.htmx.html
    - public/members.htmx.html
    - public/meetings.htmx.html
    - public/assets/js/core/shared.js

key-decisions:
  - "skeleton-row elements inside .htmx-indicator wrapped block — existing bare skeleton-row in members.htmx.html moved inside indicator to be properly toggled"
  - "pv_sent placed after frozen in MEETING_STATUS_MAP — preserves alphabetical-ish grouping of info-variant statuses"

patterns-established:
  - "Loading indicator pattern: <div class='htmx-indicator' aria-hidden='true'> with 3+ skeleton-row children as first child of async container"

requirements-completed: [DESIGN-03, DESIGN-04]

# Metrics
duration: 5min
completed: 2026-04-07
---

# Phase 06 Plan 04: Loading States + pv_sent Badge Wiring Summary

**htmx-indicator skeleton rows injected into 3 priority list containers; pv_sent status mapped to badge-info in MEETING_STATUS_MAP**

## Performance

- **Duration:** ~5 min
- **Started:** 2026-04-07T00:00:00Z
- **Completed:** 2026-04-07T00:05:00Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments

- Wired `.htmx-indicator` + `.skeleton-row` CSS infrastructure (already in design-system.css) into `#agendaList` (operator), `#membersList` (members), `#meetingsList` (meetings) — zero new CSS required
- Added `pv_sent: { badge: 'badge-info', text: 'PV envoyé' }` to `MEETING_STATUS_MAP` in shared.js, closing the DESIGN-04 gap
- All existing IDs, classes, data-* attributes, and inline scripts preserved unchanged across all 3 HTML files

## Task Commits

Each task was committed atomically:

1. **Task 1: Wire skeleton loading indicators in operator, members, meetings HTML** - `175b2411` (feat)
2. **Task 2: Add pv_sent entry to MEETING_STATUS_MAP in shared.js** - `4b3482fb` (feat)

## Files Created/Modified

- `public/operator.htmx.html` - Added `.htmx-indicator` with 3 skeleton-row children as first child of `#agendaList`
- `public/members.htmx.html` - Wrapped existing 4 skeleton-row elements inside `.htmx-indicator` in `#membersList`
- `public/meetings.htmx.html` - Added `.htmx-indicator` with 3 skeleton-row children as first child of `#meetingsList`
- `public/assets/js/core/shared.js` - Added `pv_sent` entry to `MEETING_STATUS_MAP` after `frozen`

## Decisions Made

- In members.htmx.html, the existing bare `skeleton-row` elements were moved inside the new `.htmx-indicator` wrapper rather than adding separate rows alongside them — this prevents double-rendering and ensures the skeleton is properly CSS-toggled by the `.htmx-request` parent state.
- `pv_sent` placed after `frozen` in MEETING_STATUS_MAP to keep `badge-info` variant statuses grouped together (scheduled, frozen, pv_sent).

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- DESIGN-03 (loading states) and DESIGN-04 (pv_sent gap) are both satisfied
- The `.htmx-indicator` infrastructure is fully wired; any future async list container can follow the same pattern
- No blockers for remaining Phase 06 plans

## Self-Check

- `public/operator.htmx.html` exists with `htmx-indicator`: FOUND
- `public/members.htmx.html` exists with `htmx-indicator`: FOUND
- `public/meetings.htmx.html` exists with `htmx-indicator`: FOUND
- `public/assets/js/core/shared.js` with `pv_sent`: FOUND
- Commits 175b2411 and 4b3482fb: FOUND

## Self-Check: PASSED

---
*Phase: 06-application-design-tokens*
*Completed: 2026-04-07*
