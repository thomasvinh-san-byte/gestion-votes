---
phase: 31-component-refresh
plan: "01"
subsystem: ui
tags: [css, design-tokens, design-system, components, css-custom-properties]

# Dependency graph
requires:
  - phase: 30-token-foundation
    provides: "Complete token system — primitives, semantics, component aliases — in design-system.css :root"
provides:
  - "8 component CSS specs (buttons, cards, forms, tables, modals, toasts, badges, steppers) updated to use Phase 30 tokens"
  - "New component alias tokens: --btn-height, --input-height, --toast-width, --toast-accent-width, --stepper-dot-size, --stepper-connector-height"
  - "Unified focus ring via var(--shadow-focus) across all focusable elements"
  - "Connector-line stepper visual system replacing card-box pattern"
  - ".col-num utility for right-aligned monospace numeric table columns"
affects: ["32-page-layouts-core", "33-page-layouts-secondary", "plan 31-02 web component shadow dom reconciliation"]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Component alias tokens pattern: --btn-height, --input-height etc. as component-specific dimension tokens in :root COMPONENT ALIASES layer"
    - "Inset box-shadow for toast left-accent (not border-left) — works correctly with border-radius"
    - "Connector-line stepper: ::after pseudo-element on .stepper-item for horizontal connector between dots"
    - "color-mix() for table alternating rows — dark-mode compatible without hardcoded rgba"

key-files:
  created: []
  modified:
    - "public/assets/css/design-system.css"

key-decisions:
  - "translateY(-1px) lift reserved for clickable cards only — buttons use shadow deepening (var(--shadow-md)) instead"
  - "Stepper connector-line pattern chosen over card-box pattern — matches ag-stepper.js rendering and industry standard"
  - "Toast accent via inset box-shadow not border-left — inset respects border-radius at corners"
  - "var(--shadow-focus) unifies double-ring focus pattern across buttons, inputs, selects, textareas"
  - "--radius-badge updated to var(--radius-full) for pill shape per locked CONTEXT.md decision"
  - "Table heights: 40px headers (--space-10), 48px rows (--space-12) for enterprise density"

patterns-established:
  - "Component alias tokens: use --radius-btn, --radius-card, --radius-toast, --radius-badge, --btn-height, --input-height for all component radius/height references"
  - "Focus ring: all focusable elements use box-shadow: var(--shadow-focus) or var(--shadow-focus-danger)"
  - "Toast accent: box-shadow: var(--shadow-lg), inset var(--toast-accent-width) 0 0 var(--color-color)"

requirements-completed: [CMP-01, CMP-02, CMP-03, CMP-04, CMP-05, CMP-06, CMP-07, CMP-08]

# Metrics
duration: 25min
completed: 2026-03-19
---

# Phase 31 Plan 01: Component CSS Refresh Summary

**All 8 component CSS specs tokenized — new dimension aliases, unified double-ring focus, inset toast accents, connector-line stepper, and card/button hover differentiation**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-03-19T05:45:00Z
- **Completed:** 2026-03-19T06:05:00Z
- **Tasks:** 2
- **Files modified:** 1

## Accomplishments

- Added 6 new component alias tokens (`--btn-height`, `--input-height`, `--toast-width`, `--toast-accent-width`, `--stepper-dot-size`, `--stepper-connector-height`) to `:root` COMPONENT ALIASES section
- Unified focus ring across all 5 focusable component types via `var(--shadow-focus)` / `var(--shadow-focus-danger)` — replaces 4 different hardcoded box-shadow formulas
- Eliminated all `translateY(-1px)` lift from button hover states; replaced with `var(--shadow-md)` deepening
- Converted toast left-accent from `border-left: 4px solid` to `inset box-shadow` pattern on all 4 toast variants
- Replaced stepper card-box visual system with connector-line pattern (28px dots, `::after` connector, done state turns connector green)
- Updated `.modal-backdrop` to use `var(--color-backdrop)` token instead of hardcoded `rgba(0,0,0,.35)`
- Added `.col-num` utility class for right-aligned JetBrains Mono tabular numeric columns in tables
- Fixed `--radius-badge` from `var(--radius-sm)` to `var(--radius-full)` (pill shape)

## Task Commits

Each task was committed atomically:

1. **Task 1: Add component alias tokens and update buttons, cards, tables, forms** - `3bf99bf` (feat)
2. **Task 2: Update modal, toast, badge, stepper CSS in design-system.css** - `606fccb` (feat)

**Plan metadata:** _(docs commit follows)_

## Files Created/Modified

- `public/assets/css/design-system.css` — Updated `:root` COMPONENT ALIASES block and all 8 component CSS sections within `@layer components`

## Decisions Made

- **Stepper canonical system:** Chose connector-line style (dots + horizontal connector via `::after`) over card-box style — matches ag-stepper.js production rendering and is industry standard (shadcn Steps, MUI Stepper, Ant Design)
- **Button hover model:** Removed `translateY(-1px)` lift from `.btn-primary`, `.btn-success`, `.btn-danger` hover states. Used `var(--shadow-md)` + inset highlight instead. Active press (`scale(.98) translateY(0)`) kept as-is.
- **Table row heights:** Used `height` property (not `padding` alone) on `th` and `td` to enforce 40px/48px density — matches Linear/Jira enterprise table density
- **Toast width:** Fixed `width: var(--toast-width)` = 356px replacing `min-width/max-width` range — consistent with modal's `min(520px, 100%)` fixed-width approach

## Deviations from Plan

None — plan executed exactly as written. All changes matched the specified acceptance criteria.

## Issues Encountered

None. The design-system.css component sections were at the expected line numbers and required straightforward edits.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- All 8 CSS component specs use component alias tokens — ready for Web Component Shadow DOM reconciliation in plan 31-02
- `--radius-btn`, `--radius-card`, `--radius-toast`, `--radius-badge`, `--btn-height`, `--input-height`, `--toast-width`, `--stepper-dot-size` all defined in `:root` for Shadow DOM consumption
- Plan 31-02 will update ag-modal.js, ag-toast.js, ag-badge.js, ag-stepper.js internal Shadow DOM styles to consume these tokens

---
*Phase: 31-component-refresh*
*Completed: 2026-03-19*
