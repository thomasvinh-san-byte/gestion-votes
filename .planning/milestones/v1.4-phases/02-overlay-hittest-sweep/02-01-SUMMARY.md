---
phase: 02-overlay-hittest-sweep
plan: 01
subsystem: ui
tags: [css, hidden-attribute, specificity, where-selector, overlay, design-system]

# Dependency graph
requires:
  - phase: 01-contrast-aa-remediation
    provides: design-system.css @layer base structure with oklch tokens
provides:
  - "Global :where([hidden]) { display: none !important } rule in @layer base"
  - "16 redundant per-selector [hidden] overrides removed from 10 CSS files"
  - "Codebase-wide overlay hittest audit at docs/audits/v1.4-overlay-hittest.md"
affects: [02-overlay-hittest-sweep]

# Tech tracking
tech-stack:
  added: []
  patterns: ["Global :where([hidden]) reset pattern replaces per-selector overrides"]

key-files:
  created:
    - docs/audits/v1.4-overlay-hittest.md
  modified:
    - public/assets/css/design-system.css
    - public/assets/css/meetings.css
    - public/assets/css/validate.css
    - public/assets/css/members.css
    - public/assets/css/public.css
    - public/assets/css/vote.css
    - public/assets/css/trust.css
    - public/assets/css/settings.css
    - public/assets/css/operator.css
    - public/assets/css/wizard.css
    - public/assets/css/login.css

key-decisions:
  - "Single :where([hidden]) rule with !important in @layer base replaces all 16 per-selector overrides"
  - ":not([hidden]) selectors in design-system.css (transition reveal animations) intentionally preserved"

patterns-established:
  - "Global hidden reset: never add per-selector [hidden] { display: none } overrides -- the global rule handles all cases"

requirements-completed: [OVERLAY-01, OVERLAY-02]

# Metrics
duration: 3min
completed: 2026-04-10
---

# Phase 2 Plan 1: Overlay Hittest Sweep Summary

**Global :where([hidden]) { display: none !important } rule added to @layer base; 16 redundant per-selector overrides removed across 10 CSS files; codebase-wide audit documenting 25 conflict sites**

## Performance

- **Duration:** 3 min
- **Started:** 2026-04-10T06:04:16Z
- **Completed:** 2026-04-10T06:07:32Z
- **Tasks:** 2
- **Files modified:** 12

## Accomplishments
- Added global `:where([hidden]) { display: none !important }` to `@layer base` in design-system.css, ensuring the HTML `[hidden]` attribute always produces `display: none` regardless of author CSS
- Removed all 16 per-selector `[hidden] { display: none }` overrides from meetings.css, validate.css, members.css, public.css, vote.css, trust.css, settings.css, operator.css, wizard.css, and login.css
- Produced comprehensive audit document cross-referencing 727 display:flex/grid declarations with 217 JS hidden toggles, identifying 25 active conflict sites all protected by the global rule

## Task Commits

Each task was committed atomically:

1. **Task 1: Add global :where([hidden]) rule and remove 16 redundant overrides** - `2517a754` (fix)
2. **Task 2: Produce codebase-wide overlay hittest audit document** - `77fc3301` (docs)

## Files Created/Modified
- `public/assets/css/design-system.css` - Added `:where([hidden]) { display: none !important }` to @layer base reset section
- `public/assets/css/meetings.css` - Removed `.onboarding-banner[hidden]` override
- `public/assets/css/validate.css` - Removed `.validate-modal-backdrop[hidden]` override
- `public/assets/css/members.css` - Removed `.members-onboarding[hidden]` override
- `public/assets/css/public.css` - Removed `.meeting-picker-overlay[hidden]` and `.app-footer[hidden]` overrides
- `public/assets/css/vote.css` - Removed 5 overrides (offline-banner, current-speaker-banner, vote-hint, app-footer, blocked-overlay)
- `public/assets/css/trust.css` - Removed `.audit-modal-overlay[hidden]` override
- `public/assets/css/settings.css` - Removed `.settings-panel[hidden]` override
- `public/assets/css/operator.css` - Removed `.op-transition-card[hidden]` and `.op-quorum-overlay[hidden]` overrides
- `public/assets/css/wizard.css` - Removed `.wiz-error-banner[hidden]` override
- `public/assets/css/login.css` - Removed `.demo-panel[hidden]` override
- `docs/audits/v1.4-overlay-hittest.md` - Complete conflict site inventory and shadow DOM analysis

## Decisions Made
- Single `:where([hidden])` rule with `!important` in `@layer base` replaces all 16 per-selector overrides. `:where()` keeps specificity at (0,0,0); `!important` ensures it wins over any author `display` declaration.
- `:not([hidden])` selectors in design-system.css (lines 5292-5299, used for CSS transition reveal animations on `.op-post-vote-guidance` and `.op-end-of-agenda`) were intentionally preserved -- they are positive selectors, not `[hidden]` overrides.

## Deviations from Plan

None -- plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None -- no external service configuration required.

## Next Phase Readiness
- Global rule is in place, preventing future `[hidden]` + `display:flex` conflicts
- Audit document provides reference for future CSS additions
- Ready for 02-02 (Playwright smoke test for computed style assertions on [hidden] elements)

---
*Phase: 02-overlay-hittest-sweep*
*Completed: 2026-04-10*
