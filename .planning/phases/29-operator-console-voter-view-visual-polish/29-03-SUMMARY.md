---
phase: 29-operator-console-voter-view-visual-polish
plan: 03
subsystem: ui
tags: [voter-view, sse, optimistic-ui, css, accessibility, full-screen]

# Dependency graph
requires:
  - phase: 29-01
    provides: design token additions (color-mix tints, v4 CSS layer) used by vote.css

provides:
  - data-vote-state attribute-driven state machine on #voteApp (waiting/voting/confirmed)
  - setVoteAppState() JS function wired to refresh() and castVoteOptimistic()
  - castVoteOptimistic() with instant visual feedback, background POST, rollback on error
  - Full-screen ballot mode: all chrome hidden when data-vote-state="voting"
  - Vote buttons min-height 72px, full-width in voting state, 8px gap
  - Inline irreversibility warning (replaces confirmation overlay flow)
  - Waiting state ("En attente d'un vote") and confirmed state ("Vote enregistre") CSS
  - Inline style block (VIS-08) moved from vote.htmx.html to vote.css

affects: [vote-ui, vote.htmx.html, operator-console, post-session-pv]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - data-vote-state attribute selector pattern for CSS-driven state visibility (no JS class toggling)
    - Optimistic commit pattern: instant DOM update, background POST, rollback on error
    - castVoteOptimistic() wraps cast() with setVoteSelected()/rollbackVote() for <50ms feedback

key-files:
  created: []
  modified:
    - public/vote.htmx.html
    - public/assets/js/pages/vote.js
    - public/assets/css/vote.css

key-decisions:
  - "castVoteOptimistic() replaces confirmation overlay flow — buttons wire directly to optimistic pattern; #confirmationOverlay HTML kept in DOM as accessibility fallback"
  - "Vote buttons rendered full-width via flex column on .vote-buttons in voting state (overrides grid layout) rather than removing grid globally"
  - "data-vote-state driven by refresh() call outcome — voting on motion present, waiting on no motion or error; confirmed set by showConfirmationState() after successful POST"
  - "Inline irreversibility notice replaces blocking confirmation dialog per VOT-03 — text above buttons, hidden in non-voting states"
  - "VIS-08: inline <style> block with #btnConsultDocument and .motion-card-footer rules moved to vote.css, skeleton margin helpers added as utility classes"

requirements-completed: [VOT-01, VOT-02, VOT-03, VOT-04, VOT-05, VOT-06]

# Metrics
duration: 3min
completed: 2026-03-18
---

# Phase 29 Plan 03: Voter View Full-Screen Ballot & Optimistic Vote Flow Summary

**data-vote-state attribute machine drives full-screen ballot mode with optimistic vote casting (instant visual, background POST, rollback) and waiting/confirmed states via CSS attribute selectors**

## Performance

- **Duration:** 3 min
- **Started:** 2026-03-18T18:08:02Z
- **Completed:** 2026-03-18T18:10:58Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments

- Full-screen ballot mode: when `data-vote-state="voting"`, all navigation chrome (header, context bar, progress dots, bottom nav, footer, speech panel, speaker banner) is hidden via CSS attribute selectors — voter sees only the ballot card
- Optimistic vote flow: `castVoteOptimistic()` applies instant visual selection (`.vote-btn-selected`) and disables buttons synchronously, then submits in background via `cast()`; on error, `rollbackVote()` re-enables buttons and shows inline error
- State machine: `setVoteAppState()` sets `data-vote-state` attribute on `#voteApp`; `refresh()` drives waiting/voting transitions; `showConfirmationState()` drives confirmed→waiting with 3-second timeout
- Vote buttons in voting state: full-width stacked flex layout, min-height 72px, 8px gap, scale(0.98) on active
- VIS-08 compliance: moved inline `<style>` block from vote.htmx.html to vote.css; skeleton margin helpers added as CSS utility classes

## Task Commits

Each task was committed atomically:

1. **Task 1: Voter full-screen mode JS + HTML state management** - `9e86b07` (feat)
2. **Task 2: Voter view CSS — full-screen mode, 72px buttons, state visibility** - `6111edc` (feat)

## Files Created/Modified

- `public/vote.htmx.html` - Added `data-vote-state="waiting"` on `#voteApp`, waiting/confirmed state containers, inline irreversibility notice, removed inline `<style>` block, replaced skeleton inline margin styles with CSS classes
- `public/assets/js/pages/vote.js` - Added `setVoteAppState()`, `castVoteOptimistic()`, `setVoteSelected()`, `rollbackVote()`, `showConfirmationState()`, `showInlineError()`; wired vote buttons to optimistic flow; wired state transitions in `refresh()`
- `public/assets/css/vote.css` - Added VOT-01 full-screen hide rules, VOT-02 72px button sizing, VOT-03 irreversibility notice, VOT-04 waiting state, VOT-05 confirmed state, `.vote-btn-selected` ring, `.vote-inline-error`, mobile 375px media query, VIS-08 moved styles

## Decisions Made

- `castVoteOptimistic()` replaces the confirmation overlay wiring — the `#confirmationOverlay` HTML stays in DOM as accessibility fallback but the button click handlers now go directly to optimistic flow
- Vote buttons rendered full-width via `flex-direction: column` on `.vote-buttons` in voting state (overrides the existing 2x2 grid) to satisfy VOT-02 full-width requirement
- `data-vote-state` driven by outcome of `refresh()` API call: voting when motion present, waiting when no motion or error; confirmed set by `showConfirmationState()` post-successful POST
- VIS-08: removed inline `<style>` block entirely; `#btnConsultDocument { margin-top: 8px; font-size: 13px }` and `.motion-card-footer` rules moved to vote.css

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Voter full-screen ballot experience complete through Phase 29-03
- All VOT-01 through VOT-06 requirements satisfied
- CSS has no `@layer` (unlayered page CSS — correct)
- `wireConsultDocBtn()` verified intact (VOT-06: PDF viewer bottom-sheet wiring)

---
*Phase: 29-operator-console-voter-view-visual-polish*
*Completed: 2026-03-18*

## Self-Check: PASSED

- public/vote.htmx.html: FOUND
- public/assets/js/pages/vote.js: FOUND
- public/assets/css/vote.css: FOUND
- .planning/phases/29-operator-console-voter-view-visual-polish/29-03-SUMMARY.md: FOUND
- commit 9e86b07: FOUND
- commit 6111edc: FOUND
