---
phase: 05-shared-components
plan: "03"
subsystem: ui
tags: [css, design-tokens, session-management, guided-tour, dark-theme]

# Dependency graph
requires:
  - phase: 04-design-tokens-theme
    provides: Design tokens (--color-warning-subtle, --color-surface-raised, --color-text-dark, --color-text-muted, --color-success, --radius, --duration-fast)

provides:
  - Session expiry warning CSS block (.session-expiry-warning) with full dark theme support via CSS custom properties
  - Refactored showSessionWarning() using CSS classes instead of inline styles with Rester connecte + Deconnexion actions
  - Tokenized guided tour CSS (tour bubble, arrow, spotlight, progress dots) using design system tokens
  - color-mix() spotlight glow for dark theme compatibility

affects: [06-page-shells, auth-ui, guided-tour]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "CSS class on dynamic elements instead of inline styles (style.cssText pattern eliminated)"
    - "color-mix(in srgb, ...) for theme-adaptive rgba replacements"
    - "Elevated surfaces use --color-surface-raised, base surfaces use --color-surface"

key-files:
  created: []
  modified:
    - public/assets/css/design-system.css
    - public/assets/js/pages/auth-ui.js

key-decisions:
  - "Session expiry warning uses CSS class (session-expiry-warning) for full design token support and dark theme compatibility"
  - "Two-button UX: Rester connecte (extend session) + Deconnexion (logout) replaces single Prolonger button per wireframe"
  - "Tour bubble uses --color-surface-raised (elevated) rather than --color-surface (base) to convey elevation hierarchy"
  - "Tour spotlight glow uses color-mix(in srgb, --color-primary 25%, transparent) to adapt in dark theme"
  - "Tour progress dot done state uses --color-success (completion green) rather than --color-primary-subtle"

patterns-established:
  - "Expired state: add .expired class to warning element rather than replacing textContent for both visual and semantic change"
  - "Logout handler in session warning mirrors existing auth-banner logout pattern (api auth_logout.php then redirect)"

requirements-completed: [COMP-08, COMP-09]

# Metrics
duration: 12min
completed: 2026-03-12
---

# Phase 5 Plan 03: Session Expiry Warning and Guided Tour CSS Tokenization Summary

**Session expiry warning refactored from inline styles to CSS class with two-action UX; guided tour CSS fully tokenized with --color-surface-raised elevation and color-mix dark-theme spotlight glow**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-03-12T10:50:00Z
- **Completed:** 2026-03-12T11:02:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Removed all inline styles from `showSessionWarning()` — replaced with `.session-expiry-warning` CSS class
- Added "Rester connecte" and "Deconnexion" two-button UX with SVG clock icon and `.expired` state class
- Replaced hardcoded `rgba(22, 80, 224, 0.25)` spotlight glow with `color-mix(in srgb, var(--color-primary) 25%, transparent)` for dark theme
- Upgraded `.tour-bubble` and `::before` arrow from `--color-surface` to `--color-surface-raised` (elevation hierarchy)
- Replaced `--color-text-secondary` with `--color-text-muted` in `.tour-bubble-text` (canonical token)
- Added explicit `color: var(--color-text-dark)` to `.tour-bubble-title`
- Updated `.tour-progress-dot.done` from `--color-primary-subtle` to `--color-success` (completion semantics)
- Zero hardcoded hex colors remain in the tour CSS section

## Task Commits

Each task was committed atomically:

1. **Task 1: Redesign session expiry warning with CSS classes and wireframe styling** - `9bfe56e` (feat)
2. **Task 2: Tokenize guided tour CSS in design-system.css** - Included in `6d34967` (feat) — changes verified present in HEAD

**Plan metadata:** (docs commit created below)

## Files Created/Modified
- `public/assets/css/design-system.css` - Added .session-expiry-warning block (44 lines); tokenized tour CSS (overlay opacity, spotlight color-mix, bubble surface-raised, title/text colors, progress dot done state)
- `public/assets/js/pages/auth-ui.js` - Refactored showSessionWarning() — CSS classes, two buttons, SVG icon, expired state, logout handler

## Decisions Made
- Two-button UX (Rester connecte + Deconnexion) matches wireframe v3.19.2 requirement captured in plan
- Logout in session warning uses same API pattern as auth-banner logout (api auth_logout.php then redirect to /)
- Tour bubble uses --color-surface-raised to distinguish it from page surface (elevation semantics)
- color-mix() approach chosen over explicit dark-theme overrides to keep CSS DRY

## Deviations from Plan

None - plan executed exactly as written. Task 2 CSS changes were verified present in HEAD (committed in 6d34967 which also modified design-system.css for 05-02 plan work — the changes overlap was detected and verified correct).

## Issues Encountered
- Task 2 edits to design-system.css were detected as already committed in HEAD (via 6d34967 from prior plan 05-02 agent session that also modified design-system.css). Verified all required changes present via manual diff inspection. No separate commit created as file was clean.

## User Setup Required
None - no external service configuration required.

## Self-Check: PASSED

All files present, all commits verified in git history.

## Next Phase Readiness
- Session expiry warning fully tokenized and ready for dark theme testing
- Tour system CSS tokenized — no functionality changes, JS-depended class names unchanged
- Ready for Phase 6 page shell work

---
*Phase: 05-shared-components*
*Completed: 2026-03-12*
