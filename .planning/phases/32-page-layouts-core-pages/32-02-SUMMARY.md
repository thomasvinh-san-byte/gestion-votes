---
phase: 32-page-layouts-core-pages
plan: "02"
subsystem: ui
tags: [css-grid, sticky, layout, wizard, operator, design-system]

# Dependency graph
requires:
  - phase: 31-component-refresh
    provides: Design tokens, component styles, --space-card, --color-surface-raised
  - phase: 32-01
    provides: Dashboard max-width and app-main padding baselines
provides:
  - Wizard centered 680px track with sticky stepper and sticky step navigation
  - Operator console CSS Grid (explicit display:grid fixing latent flex/grid bug)
  - 3-row operator grid (statusbar / tabnav / main) with 280px scrollable agenda sidebar
affects: [33-page-layouts-secondary, 34-quality-assurance]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "position: sticky on stepper (top: 0) + nav (bottom: 0) inside scrollable .app-main"
    - "Nested CSS Grid: outer 3-row grid (app-shell) + inner 2-column grid (app-main with 280px sidebar)"
    - "Explicit display: grid required when overriding design-system flex container"

key-files:
  created: []
  modified:
    - public/assets/css/wizard.css
    - public/wizard.htmx.html
    - public/assets/css/operator.css
    - public/operator.htmx.html

key-decisions:
  - "Sticky .step-nav per-step instead of extracted shared .wiz-footer — JS manages step visibility, each card's .step-nav stays inside its card"
  - "Nested grid approach for operator: .app-shell is the 3-row grid, .app-main becomes 2-column grid (280px sidebar + 1fr)"
  - "Fixed sidebar (.app-sidebar) excluded from grid flow via position: fixed — only statusbar/tabnav/main participate"

patterns-established:
  - "Bug fix pattern: always add explicit display:grid when defining grid-template-* on elements that have display:flex in design-system"
  - "Wizard layout: wiz-content (680px centered) > wiz-step cards > wiz-step-body (scroll) + step-nav (sticky bottom)"

requirements-completed: [LAY-02, LAY-03]

# Metrics
duration: 15min
completed: 2026-03-19
---

# Phase 32 Plan 02: Wizard + Operator Layout Summary

**Wizard 680px centered track with sticky stepper/footer; operator console CSS Grid bug fixed with 3-row layout and 280px scrollable agenda sidebar.**

## Performance

- **Duration:** 15 min
- **Started:** 2026-03-19T07:15:00Z
- **Completed:** 2026-03-19T07:30:00Z
- **Tasks:** 2/2
- **Files modified:** 4

## Accomplishments

- Fixed the latent CSS Grid bug in operator.css where grid-template-* properties were set on a flex container (design-system.css sets `.app-shell { display: flex }`), making all grid properties silently ignored
- Rebuilt operator console as proper 3-row CSS Grid (statusbar / tabnav / main) with nested 280px agenda sidebar beside fluid main content
- Added sticky stepper (top: 0) and sticky step navigation (bottom: 0) to wizard
- Centered wizard form track at 680px with 480px field cap and `.wiz-content` wrapper

## Task Commits

Each task was committed atomically:

1. **Task 1: Wizard centered track + sticky stepper/footer (LAY-02)** - `bdaa2bd` (feat)
2. **Task 2: Operator console CSS Grid fix + 3-row layout with sidebar (LAY-03)** - `194d92d` (feat)

**Plan metadata:** (pending final commit)

## Files Created/Modified

- `public/assets/css/wizard.css` — Added sticky to .wiz-progress-wrap, new .wiz-content (680px centered, 80px bottom pad), 480px field cap, .wiz-footer rule, sticky .step-nav
- `public/wizard.htmx.html` — Wrapped all step cards in `<div class="wiz-content">`
- `public/assets/css/operator.css` — Replaced broken grid with explicit `display: grid`, 3-row template, nested .app-main grid (280px sidebar), .op-agenda and .op-main-content styles, responsive collapse at 768px
- `public/operator.htmx.html` — Added `<aside class="op-agenda">` placeholder as first child of .app-main, wrapped remaining content in `.op-main-content`

## Decisions Made

- **Sticky .step-nav per-step, not extracted**: Each wizard step card has its own `.step-nav` that is shown/hidden by JS. Extracting to a shared footer would require significant JS refactoring. Applied `position: sticky; bottom: 0` directly to `.step-nav` inside each card — cleaner and functionally equivalent.
- **Nested grid approach for operator**: `.app-shell` is the outer 3-row grid; `.app-main` becomes an inner 2-column grid (280px + 1fr). The fixed `.app-sidebar` stays out of both grids via `position: fixed`.
- **Minimal agenda placeholder**: Created `<aside class="op-agenda">` with heading and empty state. The existing `.op-agenda-*` styles in operator.css already handle the populated state via JS.

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## Next Phase Readiness

- Wizard layout complete: sticky stepper + centered 680px track + sticky footer
- Operator layout complete: working CSS Grid with status bar, tab nav, 280px sidebar
- Phase 33 (secondary pages) can proceed — layout patterns established
- The `.op-agenda-list` is populated by JS; the sidebar placeholder is ready

---
*Phase: 32-page-layouts-core-pages*
*Completed: 2026-03-19*
