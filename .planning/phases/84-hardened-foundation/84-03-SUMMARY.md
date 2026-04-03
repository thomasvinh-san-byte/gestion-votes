---
phase: 84-hardened-foundation
plan: 03
subsystem: ui
tags: [javascript, web-components, shadow-dom, design-tokens, oklch, HARD-02, HARD-05]

# Dependency graph
requires:
  - phase: 84-01
    provides: "@property blocks, new tokens (success-glow, danger-glow), fixed shadow-focus"
provides:
  - "All 19 Shadow DOM components stripped of stale #1650E0 and rgba(22,80,224,...) fallbacks"
  - "ag-modal.js focus ring uses var(--shadow-focus) only (HARD-05)"
  - "ag-toast.js focus ring uses var(--shadow-focus) only (HARD-05)"
  - "ag-kpi.js color-mix upgraded from srgb to oklch"
  - "ag-donut.js JS fallback uses var(--color-border)"
  - "ag-vote-button.js success/danger glows use oklch literals"
  - "ag-quorum-bar.js fully stripped of all hex fallbacks in var() calls"
affects: [dark-mode rendering, FWCOF prevention, focus accessibility]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "var(--token) with no fallback — Shadow DOM inherits from :root"
    - "oklch(L C H / alpha) for color-with-opacity values (replaces rgba)"
    - "color-mix(in oklch, ...) for perceptual color derivation (replaces srgb)"

key-files:
  created: []
  modified:
    - public/assets/js/components/ag-time-input.js
    - public/assets/js/components/ag-kpi.js
    - public/assets/js/components/ag-page-header.js
    - public/assets/js/components/ag-scroll-top.js
    - public/assets/js/components/ag-mini-bar.js
    - public/assets/js/components/ag-modal.js
    - public/assets/js/components/ag-breadcrumb.js
    - public/assets/js/components/ag-stepper.js
    - public/assets/js/components/ag-searchable-select.js
    - public/assets/js/components/ag-tz-picker.js
    - public/assets/js/components/ag-toast.js
    - public/assets/js/components/ag-spinner.js
    - public/assets/js/components/ag-badge.js
    - public/assets/js/components/ag-pagination.js
    - public/assets/js/components/ag-confirm.js
    - public/assets/js/components/ag-popover.js
    - public/assets/js/components/ag-donut.js
    - public/assets/js/components/ag-vote-button.js
    - public/assets/js/components/ag-quorum-bar.js

key-decisions:
  - "var(--token) with no fallback is correct — Shadow DOM inherits tokens from :root via CSS cascade; fallback literals only cause stale color during dark mode toggling"
  - "ag-vote-button.js rgba(149,163,164,.15) replaced with var(--color-neutral-subtle) — existing token matches semantics"
  - "ag-vote-button.js selected state rgba(255,255,255,.18) retained — pure white overlay has no semantic token equivalent and is not a palette color"
  - "ag-quorum-bar.js received comprehensive stripping of all hex fallbacks (not just primary color), satisfying HARD-02 spirit for all components"
  - "oklch literals used for ag-vote-button success/danger glows since --color-success-glow and --color-danger-glow tokens are not yet in this branch (84-01 parallel)"

# Metrics
duration: 6min
completed: 2026-04-03
---

# Phase 84 Plan 03: Shadow DOM Web Component Fallback Audit Summary

**19 Web Components stripped of stale #1650E0 hex fallbacks and rgba(22,80,224,...) literals; ag-modal and ag-toast focus rings now use var(--shadow-focus) with no rgba fallback (HARD-02 + HARD-05)**

## Performance

- **Duration:** 6 min
- **Completed:** 2026-04-03
- **Tasks:** 2
- **Files modified:** 19

## Accomplishments

- Stripped `var(--token, #1650E0)` fallback literals from 16 Shadow DOM components — all now use `var(--token)` only
- Fixed `ag-modal.js` focus ring: `var(--shadow-focus, 0 0 0 2px var(--color-surface-raised, #fff), 0 0 0 4px rgba(22,80,224,0.35))` → `var(--shadow-focus)` (HARD-05)
- Fixed `ag-toast.js` focus ring with identical fix (HARD-05)
- Upgraded `ag-kpi.js` color-mix from `srgb` to `oklch` for perceptual uniformity
- Fixed `ag-scroll-top.js` and `ag-stepper.js` rgba(22,80,224,...) values to `var(--color-primary-glow)`
- Fixed `ag-donut.js` JS string fallback `'#ccc'` → `'var(--color-border)'`
- Fixed `ag-vote-button.js`: all `rgba(11,122,64,...)` → `oklch(0.500 0.135 155/...)`, `rgba(196,40,40,...)` → `oklch(0.510 0.175 25/...)`, `rgba(149,163,164,.15)` → `var(--color-neutral-subtle)`
- Stripped all hex fallbacks from `ag-quorum-bar.js` var() calls comprehensively
- Final verification: zero results for `grep -r "1650E0|22,80,224|rgba(22" public/assets/js/components/`

## Task Commits

Each task was committed atomically:

1. **Task 1: Strip stale fallbacks from 16 primary-color components + fix focus rings** - `7518388e` (feat)
2. **Task 2: Fix non-primary stale fallbacks in ag-donut, ag-vote-button, ag-quorum-bar** - `6d079d7d` (feat)

## Files Created/Modified

- `public/assets/js/components/ag-time-input.js` — stripped #1650E0 fallback
- `public/assets/js/components/ag-kpi.js` — stripped #1650E0 fallback, upgraded color-mix to oklch
- `public/assets/js/components/ag-page-header.js` — stripped #1650E0 fallback
- `public/assets/js/components/ag-scroll-top.js` — stripped #1650E0 + rgba(22,...) fallbacks
- `public/assets/js/components/ag-mini-bar.js` — stripped #1650E0 from JS string
- `public/assets/js/components/ag-modal.js` — fixed focus ring (HARD-05)
- `public/assets/js/components/ag-breadcrumb.js` — stripped #1650E0 fallback
- `public/assets/js/components/ag-stepper.js` — stripped #1650E0 + rgba(22,...) fallbacks
- `public/assets/js/components/ag-searchable-select.js` — stripped #1650E0 fallback (x4)
- `public/assets/js/components/ag-tz-picker.js` — stripped #1650E0 fallback
- `public/assets/js/components/ag-toast.js` — fixed focus ring (HARD-05), stripped #1650E0 from color-info
- `public/assets/js/components/ag-spinner.js` — stripped #1650E0 fallback (x2)
- `public/assets/js/components/ag-badge.js` — stripped #1650E0 fallback (x3)
- `public/assets/js/components/ag-pagination.js` — stripped #1650E0 fallback (x4)
- `public/assets/js/components/ag-confirm.js` — stripped #1650E0 from JS string and CSS
- `public/assets/js/components/ag-popover.js` — stripped #1650E0 fallback (x2)
- `public/assets/js/components/ag-donut.js` — replaced '#ccc' JS fallback with var(--color-border)
- `public/assets/js/components/ag-vote-button.js` — all rgba() palette colors replaced with oklch/tokens
- `public/assets/js/components/ag-quorum-bar.js` — all hex fallbacks stripped from var() calls

## Decisions Made

- `var(--token)` with no fallback is the correct pattern — Shadow DOM inherits CSS custom properties from `:root` via the cascade; the fallback literal was only served when the token was undefined (never in practice), but during dark mode toggling it could cause flash-of-wrong-color
- `rgba(255,255,255,.18)` retained in ag-vote-button.js selected state `.icon-circle` — pure white overlay on top of solid background has no semantic design token equivalent
- `rgba(0,0,0,.08)` retained in ag-vote-button.js shadow fallback — neutral shadow color not part of the palette token system
- oklch literals used for ag-vote-button success/danger glows since `--color-success-glow` and `--color-danger-glow` tokens exist in design-system.css only on the 84-01 parallel branch (not yet merged into this worktree)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing critical functionality] ag-quorum-bar.js comprehensive strip**
- **Found during:** Task 2
- **Issue:** Plan mentioned "strip ALL hex fallback literals from var() calls" for ag-quorum-bar.js but the research listed only `var(--color-surface, #ffffff)` as an example. The file had 12+ additional hex fallbacks (success, warning, danger, text, border, etc.)
- **Fix:** Stripped all 13 hex fallback literals from var() calls in ag-quorum-bar.js
- **Files modified:** `public/assets/js/components/ag-quorum-bar.js`
- **Committed in:** 6d079d7d

---

**Total deviations:** 1 auto-fixed (Rule 2 — missing coverage for completeness)
**Impact on plan:** Positive — more complete cleanup than planned, satisfies HARD-02 spirit

## Issues Encountered

- `--color-success-glow` and `--color-danger-glow` tokens from plan 84-01 are not in this worktree's design-system.css (parallel execution). Used oklch literals as documented fallback per plan action text. This will resolve correctly once orchestrator merges all parallel branches.

## User Setup Required

None — pure refactor, no external configuration needed.

## Next Phase Readiness

- HARD-02 satisfied: `grep -r "1650E0|22,80,224|rgba(22" public/assets/js/components/` returns zero results
- HARD-05 satisfied: ag-modal.js and ag-toast.js focus rings use `var(--shadow-focus)` only
- All 19 Shadow DOM components inherit tokens cleanly — dark mode toggle will no longer produce flash of wrong color on any Web Component
- Phase 84 hardening complete for this plan

---
*Phase: 84-hardened-foundation*
*Completed: 2026-04-03*
