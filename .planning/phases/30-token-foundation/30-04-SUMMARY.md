---
phase: 30-token-foundation
plan: "04"
subsystem: ui
tags: [css, design-tokens, design-system]

# Dependency graph
requires:
  - phase: 30-01
    provides: Initial token structure in design-system.css (PRIMITIVES + SEMANTIC sections)
  - phase: 30-VERIFICATION
    provides: Gap analysis identifying TKN-01/04/05/06 failures
provides:
  - Fixed --type-section-title-weight at 600 (semibold), not 700 (bold)
  - Three semantic layout spacing aliases: --space-section, --space-card, --space-field
  - Corrected radius values: --radius-sm at 4px, --radius-card at 12px
  - Distinct COMPONENT ALIASES section as the third visible layer in :root
affects:
  - Phase 31 (Component Refresh — uses component alias tokens for radius/typography)
  - Phase 32/33 (Page Layouts — use --space-section, --space-card, --space-field for spacing)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Three-layer :root structure: PRIMITIVES (raw palettes/sizes) > SEMANTIC (colors/spacing/shadows/transitions) > COMPONENT ALIASES (radius-btn, type-page-title, etc.)"
    - "Semantic radius aliases define component-specific intent; raw primitives stay in SEMANTIC"
    - "--space-section/card/field provide layout-role naming contract for spacing"

key-files:
  created: []
  modified:
    - public/assets/css/design-system.css

key-decisions:
  - "--radius-badge and --radius-tooltip now resolve to 4px (via updated --radius-sm); this is intentional and acceptable per TKN-06"
  - "--radius-panel and --radius-toast remain at 8px (--radius-lg); only --radius-card was elevated to 12px"
  - "Typography roles moved from SEMANTIC to COMPONENT ALIASES; --space-section/card/field stay in SEMANTIC (layout-role aliases, not component-specific)"

patterns-established:
  - "Component alias tokens (radius-*, type-*) live in COMPONENT ALIASES section — not in SEMANTIC"
  - "Spacing aliases with layout-role names (--space-section, --space-card, --space-field) live in SEMANTIC alongside other spacing primitives"

requirements-completed: [TKN-01, TKN-04, TKN-05, TKN-06]

# Metrics
duration: 10min
completed: 2026-03-19
---

# Phase 30 Plan 04: Gap Closure Summary

**Four TKN compliance gaps closed in design-system.css: section-title weight corrected to 600 (semibold), three semantic spacing aliases added, radius values aligned to spec, and a distinct COMPONENT ALIASES third layer extracted from SEMANTIC**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-03-19T05:10:00Z
- **Completed:** 2026-03-19T05:20:00Z
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments
- Corrected `--type-section-title-weight` to `var(--weight-semibold)` (600) — restoring the page-title=700 > section-title=600 weight hierarchy required by TKN-04
- Added `--space-section: var(--space-12)` (48px), `--space-card: var(--space-6)` (24px), `--space-field: var(--space-4)` (16px) as semantic layout-role aliases for TKN-05 compliance
- Fixed `--radius-sm` from 5px to 4px and `--radius-card` from 8px to 12px to match TKN-06 spec values
- Created standalone COMPONENT ALIASES section by moving radius component aliases (`--radius-btn`, `--radius-badge`, `--radius-card`, etc.) and all `--type-*` typography role tokens from SEMANTIC into a visible third layer — completing TKN-01's three-layer requirement

## Task Commits

Each task was committed atomically:

1. **Task 1: Fix TKN-04/05/06 values and TKN-01 component layer** - `fb7d27f` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `public/assets/css/design-system.css` — four targeted changes: weight, spacing aliases, radius values, and section restructure

## Decisions Made
- Typography roles (`--type-*`) moved to COMPONENT ALIASES alongside radius aliases — they compose semantic tokens for specific UI component contexts
- Semantic layout spacing aliases (`--space-section/card/field`) kept in SEMANTIC section, not COMPONENT ALIASES — they describe layout roles, not specific UI components
- `--radius-tooltip` now resolves to 4px (via `--radius-sm`) — acceptable for tooltips alongside the badge 4px fix
- `--radius-panel` and `--radius-toast` remain at 8px (`--radius-lg`) — only card elevation was raised to 12px as TKN-06 specifies

## Deviations from Plan

None — plan executed exactly as written. All four changes were straightforward token value and structural edits.

## Issues Encountered
None

## User Setup Required
None — no external service configuration required.

## Next Phase Readiness
- All Phase 30 TKN requirements now pass verification (TKN-01 partial→resolved, TKN-04 failed→fixed, TKN-05 failed→fixed, TKN-06 partial→resolved)
- Token foundation is complete and trustworthy for Phase 31 (Component Refresh)
- Components in Phase 31 should reference `--radius-btn`, `--radius-card`, `--type-page-title-*` etc. from the COMPONENT ALIASES layer

---
*Phase: 30-token-foundation*
*Completed: 2026-03-19*
