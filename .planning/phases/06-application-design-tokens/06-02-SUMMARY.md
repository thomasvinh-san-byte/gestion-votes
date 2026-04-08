---
phase: 06-application-design-tokens
plan: 02
subsystem: ui
tags: [css-grid, login, two-panel, responsive, design-tokens, htmx]

# Dependency graph
requires:
  - phase: 06-01
    provides: Design tokens applied to design-system.css — color-primary-subtle, color-primary-glow, color-surface-raised, space-* tokens all available

provides:
  - Login page restructured as 2-panel grid: branding left, form right
  - .login-panel-brand and .login-panel-form HTML wrapper elements
  - CSS grid layout (1fr 1fr) with 768px collapse to single column
  - Orb animation scoped to branding panel (position: absolute, not fixed)
  - Tagline updated to "Gestion des votes pour votre association" (no copropriété/syndic)

affects: [06-03, 06-04, milestone-v10-visual-identity]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "2-panel login layout via CSS grid — branding left / form right, single column below 768px"
    - "Animated orb scoped to panel with position: absolute (not viewport-fixed)"
    - "Panel isolation: overflow: hidden on .login-panel-brand prevents orb from escaping"

key-files:
  created: []
  modified:
    - public/login.html
    - public/assets/css/login.css

key-decisions:
  - ".login-orb changed from position: fixed (viewport-scoped) to position: absolute (panel-scoped) — orb now confined to branding panel only"
  - "Tagline copy updated to 'Gestion des votes pour votre association' per DESIGN-02 and memory feedback_no_copropriete.md"
  - ".login-brand receives z-index: 1 to render above orb within the stacking context of .login-panel-brand"
  - "480px breakpoint targets .login-panel-form padding instead of .login-page padding (which no longer has padding)"

requirements-completed: [DESIGN-02]

# Metrics
duration: 15min
completed: 2026-04-07
---

# Phase 6 Plan 02: Login 2-Panel Layout Summary

**CSS grid 2-panel login layout — branding with animated orb on left, 420px form card on right — collapses to single column below 768px with brand panel hidden**

## Performance

- **Duration:** 15 min
- **Started:** 2026-04-07T00:00:00Z
- **Completed:** 2026-04-07T00:15:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Restructured login.html with `.login-panel-brand` (left) and `.login-panel-form` (right) panels inside `.login-page`
- Relocated `.login-orb` from viewport sibling to inside `.login-panel-brand` — orb animation now panel-scoped
- Rewrote `.login-page` layout from flex-column to CSS grid `1fr 1fr` with `min-height: 100vh`
- Added responsive breakpoint at 768px: single column grid, brand panel hidden, form takes full width
- Updated tagline text to satisfy copywriting contract (no copropriété/syndic references)
- Zero raw hex or oklch() literals introduced — all CSS values reference design tokens only

## Task Commits

Each task was committed atomically:

1. **Task 1: Restructure login.html into brand + form panels** - `6791c656` (feat)
2. **Task 2: Rewrite .login-page layout + new panel styles in login.css** - `b30f9046` (feat)

**Plan metadata:** _(docs commit follows)_

## Files Created/Modified
- `public/login.html` - Restructured DOM: new .login-panel-brand and .login-panel-form wrappers, orb and brand content inside brand panel, card/trust/demo/footer inside form panel
- `public/assets/css/login.css` - .login-page now grid 1fr 1fr, added .login-panel-brand and .login-panel-form rules, .login-orb position: absolute, responsive 768px collapse

## Decisions Made
- Changed `.login-orb` from `position: fixed` to `position: absolute` — the orb must stay inside the branding panel, not overflow the whole viewport
- Added `z-index: 1` to `.login-brand` so it renders above the orb within the panel stacking context
- Updated tagline to "Gestion des votes pour votre association" — previous text "Gestion des assemblees deliberatives" is acceptable but spec requires association-focused copy

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None — no external service configuration required.

## Next Phase Readiness
- Login 2-panel layout complete — DESIGN-02 satisfied
- Plan 06-03 can proceed (per-page token cleanup for hub.css, meetings.css, operator.css)
- Orb animation scoped correctly — no viewport overflow at any breakpoint

---
*Phase: 06-application-design-tokens*
*Completed: 2026-04-07*
