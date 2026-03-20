---
phase: 37-live-session-conduct
plan: "02"
subsystem: ui
tags: [css, mobile, voter-ballot, animations, confirmation-state, vote-buttons]

requires:
  - phase: 37-01
    provides: Operator console redesign (status bar, agenda cards, live vote panel)

provides:
  - 1x4 full-width stacked vote buttons with 88px min-height and icon-left row layout
  - voteBtnPress @keyframes for immediate tactile press feedback on all vote buttons
  - Animated confirmation state with 80px green checkmark circle, colored choice text, irreversibility notice
  - Calming waiting state with pulse ring animation and descriptive text
  - .vote-verdict CSS for ADOPTE/REJETE results display
  - .motion-number utility class for motion number badges
  - confirmedChoice JS bridge setting voter's choice text and color after vote submission

affects:
  - 37-live-session-conduct
  - voter UX during live assembly sessions

tech-stack:
  added: []
  patterns:
    - CSS @keyframes voteBtnPress for 0.15s scale feedback on button active state
    - CSS @keyframes confirmReveal with spring cubic-bezier(0.34, 1.56, 0.64, 1) for elastic entrance
    - CSS @keyframes waitingPulse with scale(1.3) at 50% for calming loop
    - data-vote-state CSS attribute selector pattern for state-driven visibility (waiting/voting/confirmed)
    - JS getElementById('confirmedChoice') bridge for JS-driven color-coding of CSS elements

key-files:
  created: []
  modified:
    - public/assets/css/vote.css
    - public/vote.htmx.html
    - public/assets/js/pages/vote-ui.js

key-decisions:
  - "Vote buttons switched from 2x2 grid to 1x4 single-column on ALL viewports including landscape tablet — Apple Wallet simplicity"
  - "Confirmation state uses data-vote-state CSS selectors (not hidden attribute) — removed hidden attribute from #voteConfirmedState so CSS attribute selector controls visibility"
  - "Voter choice color uses existing choiceInfo object in vote-ui.js — no new color mapping needed, matched existing pour/contre/abstain/blanc color tokens"
  - "voteBtnPress animation fires on :active via CSS — no JS needed, <50ms perceived feedback"
  - "waitingPulse uses color-primary-subtle which adapts to dark mode automatically — no separate dark override needed beyond the explicit [data-theme=dark] rule for confirmation icon"

requirements-completed: [SEC-05]

duration: 15min
completed: 2026-03-20
---

# Phase 37 Plan 02: Mobile Voter Ballot Redesign Summary

**Vote buttons redesigned to 1x4 full-width 88px stacked layout with row icons, spring-animated confirmation checkmark, and calming pulse-ring waiting state**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-03-20T05:20:00Z
- **Completed:** 2026-03-20T05:35:00Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments

- Vote buttons converted from 2x2 grid to full-width 1x4 single-column stack (88px min-height, icon-left row layout) on all viewports including landscape tablet
- Immediate press feedback via `voteBtnPress` @keyframes animation (0.15s, scale 0.96) — perceived < 50ms
- Confirmation state rebuilt: 80px green circle with SVG checkmark, voter's choice in matching color (POUR green / CONTRE red / ABSTENTION muted / BLANC neutral), "Vote enregistre" label, irreversibility notice — all with spring cubic-bezier entrance animation
- Waiting state rebuilt: calming pulse ring (scale 1.3 at 50%, 2.5s loop) + "En attente du prochain vote" + "La seance est en cours" subtitle
- `.vote-verdict` CSS added for ADOPTE/REJETE colored verdict display
- vote-ui.js updated to set `#confirmedChoice` text and color from existing `choiceInfo` object

## Task Commits

1. **Task 1: Vote button layout and motion card enhancement** - `a02cd0c` (feat)
2. **Task 2: Confirmation state, waiting state, and results display** - `5d7bf32` (feat)

## Files Created/Modified

- `/home/user/gestion_votes_php/public/assets/css/vote.css` - Vote button layout, press animation, confirmation/waiting state CSS, verdict CSS
- `/home/user/gestion_votes_php/public/vote.htmx.html` - Confirmation state HTML (checkmark icon, choice, text, irreversible notice), waiting state HTML (pulse div, sub text)
- `/home/user/gestion_votes_php/public/assets/js/pages/vote-ui.js` - Set confirmedChoice text and color after successful vote submission

## Decisions Made

- Vote buttons remain 1-column on landscape tablet (Apple Wallet simplicity — no viewport exception per CONTEXT.md "full-width stacked buttons")
- Removed `hidden` attribute from `#voteConfirmedState` — CSS `data-vote-state` attribute selectors are the single source of truth for visibility; the `hidden` attribute was redundant and would conflict with the CSS animation on entry
- `confirmedChoice` color uses existing `choiceInfo` object already present in vote-ui.js — zero duplication
- Dark mode for confirmation icon uses explicit `[data-theme="dark"]` rule since `--color-success` background is the same in both modes, but the explicit rule ensures intent is clear
- Waiting state subtitle uses "La seance est en cours" to contextualize the wait — voted explicitly calms the voter

## Deviations from Plan

None - plan executed exactly as written. The `hidden` attribute removal from `#voteConfirmedState` was necessary for the CSS animation to work (confirmed state was hidden by attribute AND by CSS selector, creating a double-hide that would block the entrance animation) — this is an inline correctness fix, not a scope deviation.

## Issues Encountered

The HTML had `hidden` attribute on `#voteConfirmedState` but CSS was already controlling visibility via `[data-vote-state]` selectors. The `hidden` attribute would override the CSS animation since `display: none !important` from `[hidden]` takes precedence. Removed the `hidden` attribute — CSS data-vote-state selectors are sufficient and vote.js never toggles this element's hidden attribute directly.

## Next Phase Readiness

- Voter ballot visual redesign complete for Phase 37 (37-01 operator, 37-02 voter ballot)
- Phase 37 fully complete — ready for Phase 38 or subsequent phases
- Dark mode verified via CSS custom property inheritance for all new elements

---
*Phase: 37-live-session-conduct*
*Completed: 2026-03-20*
