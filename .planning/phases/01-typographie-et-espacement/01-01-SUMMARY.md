---
phase: 01-typographie-et-espacement
plan: 01
subsystem: ui
tags: [css, design-tokens, typography, spacing, form-labels]

# Dependency graph
requires: []
provides:
  - "--text-base elevated from 14px (0.875rem) to 16px (1rem) globally"
  - "body line-height switched to --leading-md (1.5) for 16px rhythm"
  - "--type-label-size now resolves to 16px via --text-base"
  - ".form-label: no uppercase, no letter-spacing, dark color, 16px"
  - "--form-gap (20px) and --section-gap (24px) aliases in :root"
  - "--space-field now delegates to --form-gap (20px, was 16px)"
affects:
  - 01-typographie-et-espacement
  - all pages using .form-label
  - all pages using --space-field or --form-gap

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Token aliasing: --space-field -> --form-gap -> --space-5 (indirection allows context-specific overrides)"
    - "Typography scale: --text-base = 1rem is now the UI baseline; --text-md also 1rem (fusion deferred)"

key-files:
  created: []
  modified:
    - public/assets/css/design-system.css

key-decisions:
  - "--text-base set to 1rem (not --text-md which was already 1rem) — keeps semantic distinction, fusion deferred"
  - "Mobile override also set to 1rem — removes the 15px intermediate, consistent with desktop"
  - "--form-gap indirection added so future per-context overrides don't touch --space-field directly"

patterns-established:
  - "Spacing alias indirection: --space-field -> --form-gap -> --space-N (allows semantic override without breaking cascade)"
  - "Label tokens: font-size via --type-label-size, color via --color-text-dark (no hardcoded values)"

requirements-completed:
  - TYPO-01
  - TYPO-02
  - TYPO-04

# Metrics
duration: 10min
completed: 2026-04-21
---

# Phase 1 Plan 01: Typographie et Espacement — Tokens Summary

**CSS design tokens updated: --text-base 14px->16px, .form-label sans uppercase/muted, --form-gap 20px alias introduced**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-04-21T06:47:00Z
- **Completed:** 2026-04-21T06:57:19Z
- **Tasks:** 2
- **Files modified:** 1

## Accomplishments

- `--text-base` elevated from 0.875rem (14px) to 1rem (16px) — affects all UI chrome that inherits body font-size
- `body` line-height switched from `--leading-base` (1.571) to `--leading-md` (1.5), matching the new 16px scale
- `.form-label` stripped of `text-transform: uppercase`, `letter-spacing: .5px`, `color: --color-text-muted` — now renders in sentence case, dark color, 16px
- New `--form-gap` (20px) and `--section-gap` (24px) aliases added to `:root`; `--space-field` now delegates to `--form-gap`

## Task Commits

Each task was committed atomically:

1. **Task 1: Update typography tokens and body line-height** - `4cf8e639` (feat)
2. **Task 2: Fix form-label styling and add spacing aliases** - `ded03089` (feat)

## Files Created/Modified

- `public/assets/css/design-system.css` — 10 line changes: 5 token updates, 3 form-label rule rewrites, 2 new spacing aliases

## Decisions Made

- `--text-base` set to `1rem` rather than pointing to `--text-md` (which was already `1rem`) — preserves semantic distinction between "UI chrome size" and "body reading text"; fusion deferred
- Mobile `@media (max-width: 480px)` override also set to `1rem` — the 15px intermediate step removed, keeps mobile consistent with desktop for users 55+
- `--space-field` now delegates via `--form-gap` rather than directly to `--space-5` — allows future per-context overrides without modifying the canonical token

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- All typography and spacing tokens are now at the correct values for 16px readability
- Every page that uses `.form-label`, `--space-field`, or inherits `body` font-size will automatically receive the new values via CSS cascade — no per-page changes needed for the token layer
- Phase 1 Plan 02 (sidebar/nav tokens) can proceed with confidence the base typography is correct

---
*Phase: 01-typographie-et-espacement*
*Completed: 2026-04-21*
