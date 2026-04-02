---
phase: 47-hub-rebuild
plan: 03
subsystem: api
tags: [php, javascript, wizard_status, motions, hub]

# Dependency graph
requires:
  - phase: 47-02
    provides: hub.js with renderMotionsList, mapApiDataToSession, and all lifecycle wiring
provides:
  - wizard_status API returns scheduled_at, location, meeting_type fields
  - hub.js mapApiDataToSession maps date/place/type_label from real API data
  - hub.js loadData fetches motions from motions_for_meeting endpoint
affects: [48-final-audit]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Additive API extension: new fields appended to existing SELECT + api_ok without touching existing logic"
    - "Async side-fetch: secondary API calls launched after primary data ready, using .then/.catch (not Promise.all)"

key-files:
  created: []
  modified:
    - app/Repository/WizardRepository.php
    - app/Controller/DashboardController.php
    - public/assets/js/pages/hub.js

key-decisions:
  - "Motions loaded via separate motions_for_meeting fetch (not extending wizard_status array) — avoids over-loading the status polling endpoint"
  - "scheduled_at formatted with toLocaleDateString fr-FR in JS, not pre-formatted in PHP — keeps raw ISO value in API for future use"
  - "type_label derived client-side from meeting_type via replace(/_/g, ' ').toUpperCase() — no server-side label table needed"

patterns-established:
  - "Pattern 1: getMeetingBasics() is the single source of meeting row data; add columns here, expose in api_ok — no duplication"

requirements-completed: [REB-05, WIRE-01]

# Metrics
duration: 8min
completed: 2026-03-22
---

# Phase 47 Plan 03: Hub API Gap Closure Summary

**wizard_status API extended with date/place/type fields; hub.js wired to motions_for_meeting for real motion titles in hub card**

## Performance

- **Duration:** 8 min
- **Started:** 2026-03-22T17:35:00Z
- **Completed:** 2026-03-22T17:43:00Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- WizardRepository.getMeetingBasics() SQL now returns scheduled_at, location, meeting_type — closing the backend data gap
- DashboardController.wizardStatus() api_ok payload now includes all three new fields — hub hero meta can display real date, place, and type
- hub.js mapApiDataToSession now maps scheduled_at to fr-FR dateDisplay, data.location to place, meeting_type to type_label
- hub.js loadData now fires a separate async motions_for_meeting fetch to populate the motions card with real titles

## Task Commits

1. **Task 1: Extend wizard_status API to return date, place, and meeting_type** - `23cfa56` (feat)
2. **Task 2: Update hub.js to map new API fields and load motions from motions_for_meeting** - `d1dd434` (feat)

**Plan metadata:** see final docs commit (docs)

## Files Created/Modified
- `app/Repository/WizardRepository.php` - getMeetingBasics() SELECT extended with scheduled_at, location, meeting_type
- `app/Controller/DashboardController.php` - wizardStatus() api_ok payload includes 3 new fields
- `public/assets/js/pages/hub.js` - mapApiDataToSession updated; loadData fires motions_for_meeting fetch

## Decisions Made
- Motions loaded via separate motions_for_meeting call rather than extending wizard_status — avoids bloating the lightweight status polling endpoint with arrays
- scheduled_at formatted in JS with toLocaleDateString fr-FR rather than pre-formatted in PHP, keeping the raw ISO timestamp in the API response for any future callers
- type_label derived purely client-side (replace underscores, uppercase) — no server-side label lookup required for now

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Phase 47 fully complete: hub HTML+CSS+JS rewritten, all API wiring verified, hero meta and motions card now show real data
- Requirements REB-05 and WIRE-01 fully satisfied
- Ready for Phase 48 final audit

---
*Phase: 47-hub-rebuild*
*Completed: 2026-03-22*
