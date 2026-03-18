---
phase: 26-guided-ux-components
plan: 03
subsystem: ui
tags: [ag-popover, ag-tooltip, ag-empty-state, web-components, ux, guided-help]

# Dependency graph
requires:
  - phase: 26-guided-ux-components
    provides: ag-popover, ag-tooltip, ag-empty-state web components built in plans 01-02

provides:
  - Contextual help panels on all 8 operator pages (ag-popover trigger=click replacing btnTour)
  - Disabled button tooltips on operator/postsession/members explaining why buttons are locked
  - Technical term popovers for Quorum, Majorite absolue, Scrutin secret
  - Tooltip text sync with button enabled/disabled state in operator-tabs.js

affects: [phase-27, phase-28, any future plan touching operator pages]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - ag-popover trigger=click with slot=trigger and slot=content for rich help panels
    - ag-tooltip wrapping disabled buttons with Disponible apres pattern
    - closest('ag-tooltip').setAttribute('text', '') pattern for tooltip enable/disable sync
    - ag-empty-state replacing Shared.emptyState() in div (non-table) contexts

key-files:
  created: []
  modified:
    - public/dashboard.htmx.html
    - public/hub.htmx.html
    - public/wizard.htmx.html
    - public/operator.htmx.html
    - public/members.htmx.html
    - public/postsession.htmx.html
    - public/analytics.htmx.html
    - public/meetings.htmx.html
    - public/assets/js/pages/operator-tabs.js

key-decisions:
  - "Help panels are user-initiated click popovers — no localStorage dismissal needed (GUX-08 satisfied by design)"
  - "ag-tooltip text cleared via closest('ag-tooltip').setAttribute('text','') when button is enabled — no stale tooltip"
  - "Scrutin secret popover added next to Vote secret label in wizard step 3 (term actually appears in markup)"
  - "Majorite absolue popover added to hub KPI Quorum requis label (closest relevant technical context)"

patterns-established:
  - "Help panel: <ag-popover trigger=click position=bottom width=320> with <button slot=trigger class=tour-trigger-btn> and <div slot=content>"
  - "Disabled tooltip: <ag-tooltip text='Disponible apres ...' position=bottom> wrapping disabled button"
  - "Tooltip sync: _syncXTooltip(isDisabled) helper calling closest('ag-tooltip').setAttribute('text', isDisabled ? MSG : '')"
  - "Technical term: inline <ag-popover title='...' content='...' position=top trigger=click> after term label"

requirements-completed: [GUX-04, GUX-05, GUX-07, GUX-08, GUX-03]

# Metrics
duration: 4min
completed: 2026-03-18
---

# Phase 26 Plan 03: Help Panels, Disabled Tooltips & Term Popovers Summary

**8-page btnTour replacement with contextual ag-popover help panels, disabled primary buttons wrapped in ag-tooltip with 'Disponible apres' explanations, and Quorum/Scrutin secret/Majorite absolue term popovers**

## Performance

- **Duration:** 4 min
- **Started:** 2026-03-18T14:28:54Z
- **Completed:** 2026-03-18T14:32:54Z
- **Tasks:** 2
- **Files modified:** 9

## Accomplishments
- Replaced all 8 btnTour buttons with click-triggered ag-popover help panels, each with page-specific contextual tips under "Aide" button label
- Wrapped 7 disabled primary action buttons across operator/postsession/members with ag-tooltip "Disponible apres..." explanations (GUX-03)
- Added 3 technical term popovers (Quorum on operator, Majorite absolue on hub, Scrutin secret on wizard) via inline ag-popover with default (?) trigger
- Synced ag-tooltip text with btnPrimary enabled/disabled state in operator-tabs.js via closest('ag-tooltip') pattern
- Migrated Shared.emptyState() in operator-tabs.js agendaList div context to ag-empty-state component

## Task Commits

Each task was committed atomically:

1. **Task 1: Replace btnTour with help panel popovers on all 8 pages** - `6cb8253` (feat)
2. **Task 2: Add disabled button tooltips and technical term popovers** - `f704456` (feat)

**Plan metadata:** _(docs commit to follow)_

## Files Created/Modified
- `public/dashboard.htmx.html` - btnTour replaced with ag-popover help panel; added ag-popover.js module script
- `public/hub.htmx.html` - btnTour replaced; ag-popover.js script added; Majorite absolue term popover on Quorum requis KPI
- `public/wizard.htmx.html` - btnTour replaced; ag-popover.js script added; Scrutin secret term popover on Vote secret label
- `public/operator.htmx.html` - btnTour replaced (already has index.js); btnPrimary/hubSendConvocation/hubSend2ndConvocation wrapped in ag-tooltip; Quorum KPI term popover
- `public/members.htmx.html` - btnTour replaced; btnImport wrapped in ag-tooltip
- `public/postsession.htmx.html` - btnTour replaced; ag-popover.js + ag-tooltip.js scripts added; btnSuivant/btnValidate/btnReject wrapped in ag-tooltip
- `public/analytics.htmx.html` - btnTour replaced
- `public/meetings.htmx.html` - btnTour replaced
- `public/assets/js/pages/operator-tabs.js` - updatePrimaryButton() syncs ag-tooltip on each enabled/disabled branch; agendaList empty state migrated to ag-empty-state

## Decisions Made
- Help panels are click-triggered by user — no localStorage dismissal needed (GUX-08 satisfied by user-initiated design)
- Tooltip text cleared to empty string (not removed) when button enables — ag-tooltip renders nothing when text="" so no visual artifact
- Quorum popover on operator goes on the live KPI label (line 337) rather than the already-decorated settings label (line 435)
- hub.htmx.html gets Majorite absolue term on Quorum requis KPI label (most visible context for the term)

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered
- None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- All 8 pages now have contextual help accessible via "Aide" button
- All disabled primary actions explain themselves on hover
- Technical terms are self-defining via (?) popovers
- GUX-03, GUX-04, GUX-05, GUX-07, GUX-08 requirements fully satisfied
- Phase 26 plan 04 (if any) can reference these patterns

---
*Phase: 26-guided-ux-components*
*Completed: 2026-03-18*
