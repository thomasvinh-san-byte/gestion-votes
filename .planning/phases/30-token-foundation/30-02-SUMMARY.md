---
phase: 30-token-foundation
plan: "02"
subsystem: ui
tags: [css, typography, design-tokens, font-size, design-system]

# Dependency graph
requires:
  - phase: 30-01
    provides: "--text-md token defined at 1rem (16px), design-system.css token scaffold"
provides:
  - "--text-base: 0.875rem (14px) — primary UI chrome size"
  - "--text-md: 1rem (16px) — body reading text preserved"
  - "14px base migration complete across all 25 page CSS files"
affects: [31-component-refresh, 32-page-layouts-core, 33-page-layouts-secondary]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Two-tier typography: --text-base (14px) for UI chrome, --text-md (16px) for reading text"
    - "Body element defaults to var(--text-base) with var(--leading-base) line-height"
    - "iOS zoom prevention uses var(--text-md) to maintain >= 16px requirement"

key-files:
  created: []
  modified:
    - public/assets/css/design-system.css
    - public/assets/css/login.css
    - public/assets/css/members.css
    - public/assets/css/operator.css
    - public/assets/css/pages.css
    - public/assets/css/public.css
    - public/assets/css/wizard.css

key-decisions:
  - "--text-base flipped to 0.875rem (14px); body line-height updated to --leading-base (1.571)"
  - "op-guidance-text and meeting-picker-sub (voter-facing) identified as reading contexts, migrated to --text-md"
  - "Mobile override --text-base: 0.9375rem (15px) at max-width 480px retained for small-screen readability"
  - "iOS zoom prevention in login.css and search input uses var(--text-md) / explicit 16px (functional requirement)"
  - "Icon glyphs (readiness-arrow, onboarding-arrow, tour-bubble-close, reso-drag-handle) tokenised to --text-md to keep at 16px"

patterns-established:
  - "Typography audit pattern: categorise each font-size as UI chrome (--text-base) or reading text (--text-md) before flipping token"
  - "Explicit 16px preserved via --text-md token; no bare 1rem or 16px for body text in page CSS"

requirements-completed: [TKN-03]

# Metrics
duration: 25min
completed: 2026-03-19
---

# Phase 30 Plan 02: Token Foundation — 14px Base Migration Summary

**--text-base flipped from 16px to 14px (0.875rem), with reading-text contexts explicitly protected via --text-md (1rem) across all 25 page CSS files**

## Performance

- **Duration:** 25 min
- **Started:** 2026-03-19T05:00:00Z
- **Completed:** 2026-03-19T05:25:00Z
- **Tasks:** 1/1
- **Files modified:** 7

## Accomplishments

- Flipped `--text-base` from `1rem` (16px) to `0.875rem` (14px) — UI chrome now renders at 14px matching data-dense app conventions (Linear, GitHub, Jira)
- Updated body line-height from `--leading-normal` to `--leading-base` (1.571) optimised for 14px base
- Audited all 25 page CSS files — 27 `var(--text-base)` references confirmed as UI chrome (titles, labels, buttons, inputs, headings)
- Migrated reading-text contexts to `--text-md`: operator guidance text, voter meeting-picker subtitle, urgent card title, drag handle icon, arrow glyphs, iOS zoom prevention
- Body reading text stays 16px via explicit `--text-md` on contexts that need it

## Task Commits

Each task was committed atomically:

1. **Task 1: Audit --text-base usage, protect reading-text contexts, flip --text-base to 14px** - `ee49309` (feat)

## Files Created/Modified

- `public/assets/css/design-system.css` — --text-base: 0.875rem, body line-height: --leading-base, tour-bubble-close and onboarding-banner-dismiss tokenised
- `public/assets/css/login.css` — iOS zoom prevention updated to var(--text-md)
- `public/assets/css/members.css` — .onboarding-arrow tokenised to var(--text-md)
- `public/assets/css/operator.css` — .readiness-arrow tokenised, .op-guidance-text migrated to --text-md (reading context)
- `public/assets/css/pages.css` — .urgent-card-title migrated to var(--text-md) (explicitly 16px for prominence)
- `public/assets/css/public.css` — .meeting-picker-sub migrated to --text-md (voter-facing reading text)
- `public/assets/css/wizard.css` — .members-total-votes and .reso-drag-handle tokenised to var(--text-md)

## Decisions Made

- **Reading text classification:** Only two selectors in page CSS were classified as true reading-text contexts needing `--text-md`: `.op-guidance-text` (descriptive paragraph with relaxed line-height) and `.meeting-picker-sub` (voter-facing subtitle). All other `--text-base` usages are UI chrome.
- **Icon elements at 16px:** Icon glyphs (arrows, drag handles, close buttons) that were hardcoded at `1rem`/`16px` are tokenised to `var(--text-md)` — they're sized for visual balance with surrounding 16px text, not because they're reading text.
- **Mobile override retained:** `--text-base: 0.9375rem` at max-width 480px stays — bumps chrome to 15px on small phones for readability, intentional.
- **urgent-card-title at --text-md:** The `font-size: 16px` hardcode was replaced with `var(--text-md)` to maintain explicit 16px prominence while removing bare pixel values.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Tokenised icon elements using bare 1rem/16px values**
- **Found during:** Task 1 (audit phase)
- **Issue:** Several elements using `font-size: 1rem` or `font-size: 16px` that weren't classified as reading text but would have been affected by body inheritance. Converting to `var(--text-md)` makes intent explicit and removes bare pixel values.
- **Fix:** `.onboarding-arrow`, `.readiness-arrow`, `.tour-bubble-close`, `.onboarding-banner-dismiss`, `.reso-drag-handle`, `.members-total-votes`, `.urgent-card-title` all updated to `var(--text-md)`
- **Files modified:** design-system.css, members.css, operator.css, pages.css, wizard.css
- **Verification:** No remaining `font-size: 1rem` or `font-size: 16px` in page CSS except iOS zoom prevention (functional requirement, explicit comment)
- **Committed in:** ee49309 (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (Rule 2 — missing explicit tokenisation for icon elements)
**Impact on plan:** Improvement over plan — no bare pixel values remain. All 16px usages are now explicit `var(--text-md)`.

## Issues Encountered

None — migration was straightforward. The two-stage approach (add --text-md in plan 30-01, sweep in plan 30-02) worked as designed.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Token foundation complete: --text-base (14px) + --text-md (16px) two-tier typography in place
- All page CSS inherits 14px for UI chrome via body default
- Reading-text contexts explicitly use --text-md (16px)
- Phase 31 (Component Refresh) can proceed — typography tokens stable

---
*Phase: 30-token-foundation*
*Completed: 2026-03-19*
