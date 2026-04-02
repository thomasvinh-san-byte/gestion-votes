---
phase: 47-hub-rebuild
plan: 02
subsystem: ui
tags: [javascript, hub, api-wiring, checklist, sse]

# Dependency graph
requires:
  - phase: 47-01
    provides: Hub HTML with hero card, two-column layout, 3-item checklist DOM, all preserved IDs
provides:
  - Hub JS wired to real backend: wizard_status, invitations_stats, meeting_workflow_check
  - Fixed dead /meetings/{id}/convocations endpoint — now uses /api/v1/invitations_send_bulk
  - 3-item checklist rendering with done/blocked/pending states from live API data
  - Quorum bar with real present_count vs required threshold
  - Lifecycle CTA buttons wired: freeze/open via meeting_transition API, operator href
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Promise.all() for parallel API calls on page load (wizard_status + invitations_stats + workflow_check)"
    - "hidden attribute pattern throughout (no style.display)"
    - "Checklist state via classList modifiers on data-check items (not innerHTML rewrite)"
    - "setupConvocationBtn uses cloneNode to remove stale listeners before re-attaching"

key-files:
  created: []
  modified:
    - public/assets/js/pages/hub.js

key-decisions:
  - "renderChecklist() updates existing DOM elements via classList/textContent (not innerHTML rewrite) — avoids losing pre-rendered SVG check icons"
  - "loadData() uses Promise.all for 3 parallel fetches on page load for speed"
  - "setupConvocationBtn() hides section if all sent (pending===0 and sent>0) or no stats"
  - "hubMainBtn uses data-action attribute for JS-driven actions (freeze/open); href for navigation actions"
  - "mapApiDataToSession() maps quorum_met from wizard_status data.quorum_met (boolean)"

patterns-established:
  - "API parallel loading: wizard_status + invitations_stats + workflow_check fired simultaneously"
  - "Lifecycle button pattern: data-action attr drives JS transition, href attr drives navigation"

requirements-completed: [REB-05, WIRE-01]

# Metrics
duration: 10min
completed: 2026-03-22
---

# Phase 47 Plan 02: Hub JS Wire-up Summary

**Hub.js rewritten: 3-item checklist from live API data, dead convocations endpoint fixed to invitations_send_bulk, quorum bar and lifecycle CTAs fully wired**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-03-22T16:50:00Z
- **Completed:** 2026-03-22T17:00:00Z
- **Tasks:** 2 (1 auto + 1 human-verify checkpoint)
- **Files modified:** 1

## Accomplishments

- Removed all old 6-step stepper logic (HUB_STEPS, renderStatusBar, renderStepper, renderAction, CHECKLIST_ITEMS, currentStep, setupDetails, renderKpis, renderDocuments)
- Rewrote renderChecklist() to update 3 pre-rendered HTML items (data-check="convocation/quorum/agenda") with correct state modifiers from real API data
- Added loadInvitationStats() and loadWorkflowCheck() with parallel loading via Promise.all
- Fixed WIRE-01: replaced dead /meetings/{id}/convocations POST with /api/v1/invitations_send_bulk
- Replaced all style.display with hidden attribute (setAttribute/removeAttribute) throughout
- Wired hubOperatorBtn and hubMainBtn with status-aware text and actions
- Added setupLifecycleBtn() for freeze/open transitions via /api/v1/meeting_transition

## Task Commits

Each task was committed atomically:

1. **Task 1: Update hub.js for new DOM and fix dead endpoint** - `3006ee3` (feat)
2. **Task 2: Browser verification** - checkpoint approved by user (no code changes needed)

## Files Created/Modified

- `public/assets/js/pages/hub.js` — Complete JS rewrite: removed 6-step stepper, added 3-item checklist renderer, parallel API loading, fixed dead endpoint, hidden attribute pattern throughout

## Decisions Made

- renderChecklist() updates pre-rendered elements in-place rather than replacing innerHTML — this preserves the SVG checkmark icons and avoids DOM churn
- Promise.all fires wizard_status + invitations_stats + workflow_check in parallel to minimize page load time
- setupConvocationBtn() uses cloneNode to safely remove any stale click listeners before attaching new ones
- hubMainBtn uses `data-action` attribute to distinguish between JS-driven (freeze/open) and href-based (navigate) behaviors

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## Next Phase Readiness

- Phase 47 (Hub Rebuild) is fully complete — browser checkpoint approved
- Hub page verified functional: real data loads, no console errors, no 404s, dark mode works, responsive layout stacks at 768px
- All WIRE-01 dead endpoint issues resolved; ready for Phase 48

---
*Phase: 47-hub-rebuild*
*Completed: 2026-03-22*
