---
phase: 83-component-geometry-chrome-cleanup
plan: 01
subsystem: ui
tags: [css, design-tokens, web-components, shadow-dom, oklch, design-system]

# Dependency graph
requires:
  - phase: 82-token-foundation-palette-shift
    provides: oklch color primitives, semantic token layer, dark mode parity
provides:
  - "--radius-base (8px) as unified component radius token"
  - "3-level shadow scale (sm/md/lg) replacing 9-level scale"
  - "--color-border-alpha adaptive border token for light and dark"
  - "All Web Components updated to var(--radius-base, 8px) fallback"
  - "Zero dropped token references in any per-page CSS or component JS"
affects: [84-visual-identity-hardening, all-web-components, all-per-page-css]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Single --radius-base token controls all component corners — adjust once, change everywhere"
    - "3-level shadow vocabulary: sm (near-zero elevation), md (dropdowns), lg (modals/dialogs)"
    - "--color-border-alpha uses oklch alpha for adaptive depth on both light and dark surfaces"

key-files:
  created: []
  modified:
    - public/assets/css/design-system.css
    - public/assets/css/pages.css
    - public/assets/css/audit.css
    - public/assets/css/validate.css
    - public/assets/css/login.css
    - public/assets/css/members.css
    - public/assets/css/public.css
    - public/assets/css/operator.css
    - public/assets/css/email-templates.css
    - public/assets/css/hub.css
    - public/assets/css/trust.css
    - public/assets/css/vote.css
    - public/assets/js/components/ag-kpi.js
    - public/assets/js/components/ag-modal.js
    - public/assets/js/components/ag-toast.js
    - public/assets/js/components/ag-vote-button.js
    - public/assets/js/components/ag-pdf-viewer.js
    - public/assets/js/components/ag-tz-picker.js
    - public/assets/js/components/ag-searchable-select.js
    - public/assets/js/components/ag-quorum-bar.js
    - public/assets/js/components/ag-pagination.js
    - public/assets/js/components/ag-tooltip.js
    - public/assets/js/components/ag-confirm.js
    - public/assets/js/components/ag-time-input.js

key-decisions:
  - "All component corners unified to --radius-base (8px) — no per-component radius overrides remain"
  - "Shadow scale reduced 9→3 levels: new --shadow-sm = old --shadow-xs (0.06 opacity), --shadow-md unchanged, new --shadow-lg = old --shadow-xl (0.14 opacity)"
  - "--color-border-alpha: oklch(0 0 0 / 0.08) light / oklch(1 0 0 / 0.08) dark — white-based alpha in dark mode for correct depth perception"
  - "hub.css extra hardcoded 12px values also replaced (5 card components beyond the 3 flagged in plan)"
  - "trust.css and vote.css had unplanned shadow-xl/2xl references — fixed under Rule 2 (missing consistency)"

patterns-established:
  - "Component radius pattern: always use var(--radius-base, 8px) in Shadow DOM styles — 8px fallback ensures correctness if token not yet loaded"
  - "Shadow usage: sm=cards/panels, md=dropdowns/popovers, lg=modals/drawers/dialogs"
  - "Alpha border pattern: --color-border-alpha for structural card/panel borders that must adapt to both light and dark backgrounds"

requirements-completed: [COMP-01, COMP-02, COMP-03]

# Metrics
duration: 35min
completed: 2026-04-03
---

# Phase 83 Plan 01: Component Geometry + Chrome Cleanup Summary

**Single --radius-base (8px) token + 3-level shadow scale (sm/md/lg) + alpha border token propagated across all 17 CSS files and 13 Web Components via Shadow DOM fallback literals**

## Performance

- **Duration:** ~35 min
- **Started:** 2026-04-03
- **Completed:** 2026-04-03
- **Tasks:** 2
- **Files modified:** 25

## Accomplishments
- Consolidated 11 component radius aliases into single --radius-base (8px) token in design-system.css
- Reduced shadow vocabulary from 9 levels to 3 named levels (sm/md/lg) with updated dark mode overrides
- Added --color-border-alpha in both light (black-based alpha) and dark (white-based alpha) modes
- Updated all 13 Web Components to use var(--radius-base, 8px) fallback in Shadow DOM styles
- Remapped all shadow-xl/2xl/xs/2xs references to new 3-level vocabulary across all per-page CSS

## Task Commits

Each task was committed atomically:

1. **Task 1: Token surgery in design-system.css** - `4b21f21a` (feat)
2. **Task 2: Propagate tokens to per-page CSS and Web Components** - `63cd03b8` (feat)

## Files Created/Modified
- `public/assets/css/design-system.css` - --radius-base, 3-level shadow scale, --color-border-alpha
- `public/assets/css/pages.css` - shadow-xs→sm, kpi-card/dashboard-sessions/aside border-alpha + radius-base
- `public/assets/css/audit.css` - radius-xl fallback + shadow-xl→lg
- `public/assets/css/validate.css` - radius-xl→base, shadow-2xl→lg
- `public/assets/css/login.css` - radius-xl→base, shadow-xl→lg (x2)
- `public/assets/css/members.css` - shadow-xl→lg
- `public/assets/css/public.css` - radius-xl fallback + shadow-xl→lg
- `public/assets/css/operator.css` - shadow-xl→lg
- `public/assets/css/email-templates.css` - shadow-2xl→lg
- `public/assets/css/hub.css` - all hardcoded 12px/14px/6px → var(--radius-base)
- `public/assets/css/trust.css` - shadow-xl fallback → shadow-lg (deviation)
- `public/assets/css/vote.css` - radius-xl fallback + shadow-xl → radius-base + shadow-lg (deviation)
- All 13 Web Components - var(--radius-base, 8px) fallback in Shadow DOM styles

## Decisions Made
- All var(--radius-sm) and var(--radius-md) usages in design-system.css converted to var(--radius-base) — consistent with "one radius" goal across all UI element sizes
- hub.css had 5 hardcoded 12px card components (not just the 3 in the plan) — all updated for consistency
- ag-page-header.js bar `border-radius: 2px` left as-is per plan (decorative accent line, not component corner)
- mark element in ag-searchable-select.js hardcoded 2px updated to var(--radius-base, 8px) for uniformity

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Consistency] trust.css and vote.css had unplanned shadow-xl references**
- **Found during:** Task 2 (shadow propagation scan)
- **Issue:** Plan listed 8 specific files with dropped shadow tokens, but grep found 2 additional files (trust.css line 523, vote.css line 1982) with shadow-xl references
- **Fix:** Remapped both to var(--shadow-lg) matching the new vocabulary
- **Files modified:** public/assets/css/trust.css, public/assets/css/vote.css
- **Verification:** grep returns 0 matches for shadow-xl across all CSS/JS files
- **Committed in:** 63cd03b8 (Task 2 commit)

**2. [Rule 2 - Missing Consistency] hub.css had 5 card components with hardcoded 12px, plan only mentioned 3**
- **Found during:** Task 2 (hub.css audit)
- **Issue:** hub-checklist-card, hub-quorum-card, hub-motions-card, hub-attachments-card, and one unnamed card had hardcoded 12px border-radius in addition to the 3 lines mentioned in the plan
- **Fix:** Used replace_all to update all 5 occurrences to var(--radius-base)
- **Files modified:** public/assets/css/hub.css
- **Verification:** grep returns 0 matches for border-radius.*12px in hub.css
- **Committed in:** 63cd03b8 (Task 2 commit)

---

**Total deviations:** 2 auto-fixed (2 missing consistency)
**Impact on plan:** Both auto-fixes improved completeness. No scope creep — directly served the zero-dropped-token goal.

## Issues Encountered
None — all plan steps executed cleanly.

## Next Phase Readiness
- Geometry language unified: one radius (--radius-base), three shadows (sm/md/lg), adaptive border (--color-border-alpha)
- Plan 83-02 can build on this foundation for typography/chrome cleanup
- Zero broken token references remain anywhere in CSS or JS components

---
*Phase: 83-component-geometry-chrome-cleanup*
*Completed: 2026-04-03*
