---
phase: 28-wizard-session-hub-ux-overhaul
plan: 02
subsystem: ui
tags: [hub, checklist, quorum, ag-quorum-bar, web-components, convocations, doc-badges, vanilla-js]

# Dependency graph
requires:
  - phase: 28-wizard-session-hub-ux-overhaul
    provides: 28-01 hub HTML/JS skeleton and research
  - phase: 25-pdf-infrastructure-foundation
    provides: loadDocBadges/renderDocBadge functions already in hub.js

provides:
  - ag-quorum-bar wired to hub sessionData (current/required/total attributes)
  - hub-motions-section with per-motion data-motion-doc-badge spans populated from API
  - hub-convocation-section with btnSendConvocations triggering AgConfirm + POST API
  - CHECKLIST_ITEMS blockedReason functions for convocations and documents items
  - renderChecklist() displays hub-check-blocked span with reason text when applicable
  - renderQuorumBar(), renderMotionsList(), setupConvocationBtn() functions in hub.js

affects: [28-03-hub-css, operator-console, postsession]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "blockedReason function on checklist items — returns null or string; renderChecklist conditionally shows hub-check-blocked vs hub-check-todo"
    - "ag-quorum-bar wired via setAttribute on current/required/total/label — reactively re-renders on attribute change"
    - "setupConvocationBtn scoped to sessionData closure — memberCount captured at load time, no DOM re-query needed"
    - "renderMotionsList calls loadDocBadges after innerHTML write — doc badges async-updated after motions list renders"

key-files:
  created: []
  modified:
    - public/hub.htmx.html
    - public/assets/js/pages/hub.js

key-decisions:
  - "ag-quorum-bar loaded as type=module script — uses window.AgQuorumBar indirectly via custom element registry; no JS import needed in hub.js IIFE"
  - "ag-confirm.js added to hub.htmx.html for convocation send confirmation dialog"
  - "hubConvocationSection hidden when convocationsSent=true OR memberCount=0 — no send button shown if already sent or no members to notify"
  - "motions array derived from data.resolutions first, data.motions fallback — API uses resolutions key in wizard_status response"
  - "quorumRequired derived from data.quorum_required first, then Math.ceil(memberCount*0.5)+1 from quorum_policy presence as fallback"

patterns-established:
  - "Pattern: blockedReason IIFE inside forEach for conditional HTML — avoids variable hoisting issues in IIFE+var pattern"

requirements-completed: [WIZ-06, WIZ-07, WIZ-08]

# Metrics
duration: 2min
completed: 2026-03-18
---

# Phase 28 Plan 02: Hub Functional Enhancements Summary

**ag-quorum-bar wired to hub sessionData, per-motion doc badges in motions list, blocked-reason text on checklist items, and one-click convocation send with AgConfirm dialog**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-18T16:44:49Z
- **Completed:** 2026-03-18T16:46:52Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Hub checklist items for convocations and documents now display contextual blocked-reason text ("Disponible apres ajout des membres/resolutions") instead of generic "A faire"
- ag-quorum-bar component wired to live sessionData with threshold tick, amber/green fill, and detailed label showing presences, threshold percentage, and required member count
- Motions list section renders per-motion document badges that async-load via existing loadDocBadges() API calls
- Convocation send button shows ag-confirm dialog before POST to meetings/{id}/convocations endpoint, with toast feedback and section hide-on-success

## Task Commits

Each task was committed atomically:

1. **Task 1: Hub HTML additions — ag-quorum-bar, motions list, convocation button, script tag** - `34c8de3` (feat)
2. **Task 2: Hub JS enhancements — checklist blocked reasons, quorum bar wiring, motions list, convocation flow** - `3ddb52c` (feat)

**Plan metadata:** (to be recorded in final docs commit)

## Files Created/Modified
- `/home/user/gestion_votes_php/public/hub.htmx.html` - Added hub-quorum-section, hub-motions-section, hub-convocation-section HTML elements and ag-quorum-bar.js + ag-confirm.js script tags
- `/home/user/gestion_votes_php/public/assets/js/pages/hub.js` - Added blockedReason to CHECKLIST_ITEMS, updated renderChecklist(), added renderQuorumBar(), renderMotionsList(), setupConvocationBtn(), updated mapApiDataToSession() and loadData()

## Decisions Made
- ag-quorum-bar and ag-confirm loaded as module scripts alongside existing ag-toast.js and ag-popover.js — consistent with project's module-script pattern for web components
- hubConvocationSection hidden when convocationsSent OR memberCount===0: avoids showing send button to operators who cannot complete the action
- motions field in sessionData derives from data.resolutions (wizard_status returns resolutions array), with data.motions as fallback for compatibility
- quorumRequired falls back to Math.ceil(memberCount*0.5)+1 when quorum_policy is present but quorum_required is absent — provides a working default

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None — all HTML elements and JS functions matched the plan specification exactly. Existing hub.js IIFE+var pattern was preserved throughout.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Hub HTML and JS enhancements complete; ready for Phase 28 Plan 03 (CSS rewrite for hub.css and wizard.css Notion-like aesthetic)
- ag-quorum-bar will show with 0 present / 0 required until real session data includes quorum fields from API — graceful: section is hidden when memberCount=0
- setupConvocationBtn relies on /api/v1/meetings/{id}/convocations POST endpoint — if endpoint absent, click shows AgConfirm then fails with error toast (handled via .catch)

---
*Phase: 28-wizard-session-hub-ux-overhaul*
*Completed: 2026-03-18*
