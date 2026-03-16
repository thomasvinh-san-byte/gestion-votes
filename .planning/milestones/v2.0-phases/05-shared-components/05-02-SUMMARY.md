---
phase: 05-shared-components
plan: 02
subsystem: ui
tags: [web-components, css-custom-properties, design-tokens, dark-theme, badge, popover, progress-bar, empty-state]

# Dependency graph
requires:
  - phase: 04-design-tokens-theme
    provides: CSS custom property tokens (--color-*, --radius-*, --shadow-*, --duration-*) defined in design-system.css :root

provides:
  - ag-badge with --color-bg-subtle/--color-text-muted tokens replacing proprietary --tag-bg/--tag-text
  - ag-badge "warn" variant alias mapping to warning tokens
  - ag-popover with --color-surface-raised (elevated surface), --radius-lg, Phase 4-aligned fallback values
  - .empty-state-description using --color-text-muted (canonical muted token)
  - .empty-state .btn CTA styling with primary token and transition
  - .progress-bar / .progress-bar-fill CSS pattern with success/danger/warning semantic variants
  - ag-mini-bar with tokenized transition (--duration-normal) and gap: 1px segment separation

affects: [06-table-components, 07-operator-page, 08-votes-page, any page using ag-badge/ag-popover/empty-state/progress-bar]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Shadow DOM components use CSS custom properties exclusively — dark theme inherits automatically without any JS"
    - "Elevated surfaces (popovers, modals, cards) use --color-surface-raised; base surfaces use --color-surface"
    - "Proprietary component-scoped tokens (--tag-bg, --tag-text) replaced with design-system canonical tokens"
    - ".progress-bar CSS-only pattern for single-value quorum % display; ag-mini-bar handles multi-segment"

key-files:
  created: []
  modified:
    - public/assets/js/components/ag-badge.js
    - public/assets/js/components/ag-popover.js
    - public/assets/js/components/ag-mini-bar.js
    - public/assets/css/design-system.css

key-decisions:
  - "ag-popover uses --color-surface-raised (not --color-surface) since popovers are elevated UI elements"
  - "ag-mini-bar keeps CSS-only progress bar at design-system level; ag-mini-bar handles multi-segment charts"
  - ".empty-state-description changed to --color-text-muted from --color-text-secondary (secondary is near-black #151510, not appropriate for description text)"

patterns-established:
  - "Elevated surface pattern: popovers, dropdowns, cards use --color-surface-raised consistently"
  - "warn as variant alias: :host([variant=warn]) maps to warning tokens for flexibility"

requirements-completed: [COMP-04, COMP-05, COMP-06, COMP-07]

# Metrics
duration: 15min
completed: 2026-03-12
---

# Phase 5 Plan 02: Shared Components Token Alignment Summary

**ag-badge, ag-popover, ag-mini-bar, and empty state CSS fully tokenized with Phase 4 design tokens — no proprietary tokens remain in component files**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-03-12T10:25:00Z
- **Completed:** 2026-03-12T10:40:00Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments

- Removed all `--tag-bg`/`--tag-text` proprietary tokens from ag-badge; replaced with canonical `--color-bg-subtle`/`--color-text-muted`
- Added "warn" variant alias to ag-badge and aligned `--radius-full` fallback to 999px
- Upgraded ag-popover to `--color-surface-raised` for proper elevated surface semantics, `--radius-lg` border-radius, and Phase 4-aligned hex fallbacks
- Fixed `.empty-state-description` to use `--color-text-muted` (was using `--color-text-secondary` which is near-black `#151510`, incorrect for descriptive text)
- Added `.empty-state .btn` CTA button styling with primary token and hover state
- Added `.progress-bar` / `.progress-bar-fill` CSS-only pattern with success/danger/warning semantic variants
- Updated ag-mini-bar with `gap: 1px` segment separation and `var(--duration-normal, 300ms)` tokenized transition

## Task Commits

1. **Task 1: Align ag-badge and ag-popover on wireframe tokens** - `73b5793` (feat)
2. **Task 2: Align empty state CSS, ag-mini-bar, and add standard progress bar** - `6d34967` (feat)

## Files Created/Modified

- `public/assets/js/components/ag-badge.js` - Replaced --tag-bg/--tag-text with design system tokens; added warn alias; updated radius-full fallback
- `public/assets/js/components/ag-popover.js` - Elevated to --color-surface-raised; --radius-lg; Phase 4 hex fallbacks aligned
- `public/assets/js/components/ag-mini-bar.js` - Tokenized transition timing; added gap: 1px segment separation
- `public/assets/css/design-system.css` - Fixed empty state description token; added .btn CTA styles; added .progress-bar pattern

## Decisions Made

- ag-popover uses `--color-surface-raised` instead of `--color-surface`: popovers are elevated UI elements and should use the raised surface token for correct semantic layering
- CSS-only `.progress-bar` pattern added at design-system level (not a web component): simple single-value quorum display doesn't need a component; ag-mini-bar handles multi-segment cases
- `.empty-state-description` changed from `--color-text-secondary` to `--color-text-muted`: `--color-text-secondary` resolves to `#151510` (near-black) in light theme which is too dark for description text; `--color-text-muted` (`#857F72`) is semantically correct

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

- The Edit tool encountered linter interference when editing `design-system.css` (file was being modified between read and write). Resolved by using Python to make the atomic replacement — no functional issue.
- E2E tests for `ux-interactions.spec.js` require `@playwright/test` package installed locally — pre-existing environment limitation unrelated to these changes.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- All shared utility components now use Phase 4 design tokens exclusively
- Dark theme compatibility maintained — all components use CSS custom properties only, dark theme inherits automatically
- Consuming pages (operator, votes, members) can rely on consistent token usage in badges, popovers, empty states, and progress bars
- Ready for Phase 5 Plan 03 (table components) or Phase 6 (component CSS alignment)

---
*Phase: 05-shared-components*
*Completed: 2026-03-12*
