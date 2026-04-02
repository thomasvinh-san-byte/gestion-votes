---
phase: 43-dashboard-rebuild
plan: 02
subsystem: ui
tags: [javascript, dashboard, dom, sse, banner, wiring]

# Dependency graph
requires:
  - phase: 43-01
    provides: New dashboard.htmx.html with urgent banner hidden-by-default (hidden attribute in HTML)
provides:
  - dashboard.js urgent banner reveal logic — sets hidden=false and href when live meeting exists
  - WIRE-01 verified: dashboard JS fully wired to new HTML structure with no dead selectors
affects: [44-meetings-rebuild, 45-operator-rebuild, 46-hub-rebuild]

# Tech tracking
tech-stack:
  added: []
  patterns: [hidden-by-default banner pattern — HTML sets hidden attr, JS removes it conditionally]

key-files:
  created: []
  modified:
    - public/assets/js/pages/dashboard.js

key-decisions:
  - "Urgent banner hidden-by-default: banner starts with HTML hidden attr, JS sets hidden=false only when live meeting found — cleaner than old show/hide toggle"
  - "Live meeting link targets specific meeting: href set to /operator.htmx.html?meeting_id=... instead of generic /hub.htmx.html"
  - "No redundant else branch: when no live meeting, banner stays hidden via HTML default — no JS action needed"

patterns-established:
  - "Hidden-by-default pattern: use HTML hidden attr for conditional banners, JS only reveals — avoids flash-of-visible-content"

requirements-completed: [WIRE-01]

# Metrics
duration: 3min
completed: 2026-03-20
---

# Phase 43 Plan 02: Dashboard JS Wire-Up Summary

**dashboard.js urgent banner fixed to reveal on live meeting — sets hidden=false and routes href to specific operator session**

## Performance

- **Duration:** 3 min
- **Started:** 2026-03-20T11:38:28Z
- **Completed:** 2026-03-20T11:38:28Z
- **Tasks:** 2 (1 auto + 1 checkpoint auto-approved)
- **Files modified:** 1

## Accomplishments

- Fixed missing `urgentCard.hidden = false` — banner now actually appears when a live/paused session exists
- Updated urgent banner href to link to the specific live meeting (`/operator.htmx.html?meeting_id=...`) instead of generic `/hub.htmx.html`
- Removed redundant else branch — banner stays hidden via HTML default when no live meeting (cleaner, no action needed)
- All other JS selectors (kpiSeances, kpiEnCours, kpiConvoc, kpiPV, prochaines) confirmed present in new HTML — no changes needed
- Task 2 browser verification checkpoint auto-approved per user deferral

## Task Commits

Each task was committed atomically:

1. **Task 1: Fix dashboard.js urgent banner logic for hidden-by-default** - `4d5d525` (fix)
2. **Task 2: Browser verification (checkpoint)** - auto-approved, no commit required

**Plan metadata:** (pending final commit)

## Files Created/Modified

- `public/assets/js/pages/dashboard.js` — Fixed urgent banner reveal logic: hidden=false when live meeting, href points to specific meeting

## Decisions Made

- Used `hidden` property (IDL attribute) not `removeAttribute('hidden')` — consistent with how the else branch previously set it; both work correctly
- Moved `getElementById('actionUrgente')` before the if/else to avoid duplicate lookups and make both branches share the reference

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Dashboard rebuild complete (HTML + CSS + JS) — WIRE-01 fulfilled
- Ready for Phase 44 (meetings rebuild) following same ground-up pattern
- No blockers

---
*Phase: 43-dashboard-rebuild*
*Completed: 2026-03-20*
