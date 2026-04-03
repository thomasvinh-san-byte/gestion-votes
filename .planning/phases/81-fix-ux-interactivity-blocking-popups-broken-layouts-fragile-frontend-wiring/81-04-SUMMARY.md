---
phase: 81-fix-ux-interactivity-blocking-popups-broken-layouts-fragile-frontend-wiring
plan: 04
subsystem: ui
tags: [wizard, sse, event-stream, animation, unsaved-changes, ag-modal, ag-toast, ag-confirm]

# Dependency graph
requires:
  - phase: 81-fix-ux-interactivity-blocking-popups-broken-layouts-fragile-frontend-wiring/81-01
    provides: ".sse-warning-banner CSS class in design-system.css + .form-grid utility"

provides:
  - "Wizard steps render form fields in 2-column grid on desktop (1fr 1fr)"
  - "SSE disconnect shows persistent .sse-warning-banner at top of page"
  - "SSE reconnect removes the banner automatically"
  - "Wizard beforeunload warning when dirty (form changes, members, or resolutions)"
  - "Settings template editor beforeunload warning when dirty"
  - "Shell.beforeNavigate intercept on wizard and settings using AgConfirm.ask"
  - "Animation timing aligned to UI-SPEC: modal 150ms spring, toast 200ms ease-out, confirm 150ms spring, wizard 300ms emphasized"
  - "prefers-reduced-motion support in ag-modal, ag-toast, ag-confirm, wizard.css"

affects: [wizard, operator-realtime, hub, vote, settings, ag-modal, ag-toast, ag-confirm]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "SSE warning banner centrally managed in event-stream.js — all consumers (operator, hub, vote) automatically get the banner"
    - "Dirty state tracking pattern: snapshot on init + input/change listeners set _dirty flag + beforeunload check"
    - "_wizardSubmitted flag prevents spurious beforeunload after successful form submission"

key-files:
  created: []
  modified:
    - "public/assets/css/wizard.css"
    - "public/assets/js/core/event-stream.js"
    - "public/assets/js/pages/wizard.js"
    - "public/assets/js/pages/settings.js"
    - "public/assets/js/components/ag-modal.js"
    - "public/assets/js/components/ag-toast.js"
    - "public/assets/js/components/ag-confirm.js"

key-decisions:
  - "SSE banner injected centrally in event-stream.js onerror/connected handlers — no changes to operator-realtime, hub, vote"
  - "wizard.js dirty check uses both FormData snapshot AND in-memory members/resolutions arrays to handle dynamic state not in DOM forms"
  - "settings.js dirty tracking scoped to template editor only — auto-save fields are already persisted on change"
  - "toast enter timing updated from 150ms (--duration-fast) to 200ms (--duration-normal) to match UI-SPEC slide-in contract"
  - "toast dismiss updated from .18s to 300ms (--duration-deliberate) for smoother exit"

patterns-established:
  - "Central SSE banner: showSseWarning/hideSseWarning in event-stream.js, CSS class in design-system.css"
  - "Unsaved changes: _dirty flag + beforeunload + Shell.beforeNavigate + AgConfirm.ask pattern"

requirements-completed: [D-04, D-11, D-12, D-15]

# Metrics
duration: 25min
completed: 2026-04-03
---

# Phase 81 Plan 04: Layout, SSE Banner, Unsaved Changes, Animation Timing Summary

**2-column wizard grid, SSE disconnect banner via event-stream.js, beforeunload unsaved-changes protection on wizard and settings, and UI-SPEC animation timing in all 3 overlay components**

## Performance

- **Duration:** 25 min
- **Started:** 2026-04-03T06:30:00Z
- **Completed:** 2026-04-03T06:55:00Z
- **Tasks:** 2
- **Files modified:** 7

## Accomplishments

- Wizard step content containers now use a 2-column CSS grid (1fr 1fr) on desktop with full-width exceptions for textareas, uploads, and data tables
- SSE disconnect/reconnect banner centrally managed in event-stream.js — all pages using EventStream.connect automatically show/hide the persistent warning banner
- Wizard and settings pages protect unsaved work via beforeunload event + Shell.beforeNavigate intercept with AgConfirm.ask dialog
- Animation timing aligned to UI-SPEC: ag-modal 150ms spring enter, ag-toast 200ms ease-out slide-in (was 150ms), ag-confirm 150ms spring, wizard 300ms emphasized enter; all have prefers-reduced-motion handling

## Task Commits

1. **Task 1: Wizard 2-column layout + SSE disconnect banner** - `b2b8c0f3` (feat)
2. **Task 2: Unsaved changes warnings + animation timing contracts** - `bfc92d2c` (feat)

## Files Created/Modified

- `public/assets/css/wizard.css` - 2-column grid for .step-content/.step-fields/.form-body, 960px max-width, UI-SPEC transition timing, prefers-reduced-motion
- `public/assets/js/core/event-stream.js` - showSseWarning() / hideSseWarning() functions, integrated into onerror and connected handlers, exported on window.EventStream
- `public/assets/js/pages/wizard.js` - dirty state tracking (_wizardDirty, isWizardDirty, captureWizardSnapshot), beforeunload handler, Shell.beforeNavigate intercept, _wizardSubmitted flag
- `public/assets/js/pages/settings.js` - _settingsDirty tracking scoped to template editor, beforeunload + Shell.beforeNavigate with AgConfirm.ask dialog
- `public/assets/js/components/ag-modal.js` - --ease-spring enter timing, prefers-reduced-motion disables backdrop transition and modal animation
- `public/assets/js/components/ag-toast.js` - 200ms (--duration-normal) enter, 300ms (--duration-deliberate) exit, prefers-reduced-motion disables all animation
- `public/assets/js/components/ag-confirm.js` - --ease-spring modal enter, --ease-standard backdrop fade, prefers-reduced-motion disables all animation

## Decisions Made

- SSE banner centralized in event-stream.js rather than per-page — all 3 consumer pages (operator-realtime, hub, vote) get it automatically
- Wizard dirty check covers both FormData snapshot AND in-memory `members`/`resolutions` arrays (these are app state not reflected in form DOM)
- Settings dirty tracking scoped to template editor only — the auto-save settings already persist on change, so a beforeunload warning would be misleading there
- Toast animation updated from 150ms to 200ms to match UI-SPEC slide-in specification
- merge conflict in STATE.md resolved by accepting upstream version (completed 81-02)

## Deviations from Plan

None — plan executed exactly as written. The merge conflict resolution in STATE.md was infrastructure maintenance, not a code change.

## Issues Encountered

- Git conflict in STATE.md on task 1 commit — another agent had modified it. Resolved by accepting the upstream version (more recent: completed 81-02) and adding current progress counts.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- All D-04, D-11, D-12, D-15 requirements fulfilled
- Phase 81 complete — all 4 plans executed

---
*Phase: 81-fix-ux-interactivity-blocking-popups-broken-layouts-fragile-frontend-wiring*
*Completed: 2026-04-03*
