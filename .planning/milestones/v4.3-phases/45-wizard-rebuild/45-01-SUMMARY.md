---
phase: 45-wizard-rebuild
plan: 01
subsystem: ui
tags: [wizard, multi-step-form, css-animations, design-tokens, horizontal-layout]

# Dependency graph
requires:
  - phase: 44-login-rebuild
    provides: design token conventions, .field-input, .btn patterns
  - phase: 43-dashboard-rebuild
    provides: ground-up rewrite approach, CSS token discipline
provides:
  - Rewritten wizard.htmx.html with 900px content track and horizontal field grids
  - Rewritten wizard.css with wizSlideIn/wizSlideOut transitions, refined stepper, error banners
  - Animation CSS framework for step transitions (slide-out class pattern ready for wizard.js update)
affects: [45-02-wizard-js-slide-wiring, any future wizard feature additions]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Step slot architecture: .wiz-content overflow:hidden clips slide animations; .wiz-step display:none / .wiz-step.active display:block"
    - "CSS-only dark mode: all wizard colors use var(--color-*) tokens, no hardcoded hex except #fff on colored badges"
    - "Horizontal field grids: form-grid-3 for type/date/time row, form-grid-2 for place/addr and vote rules"
    - "wiz-error-banner: hidden attr pattern, one per step at top, errBannerStepN + errBannerStepNText IDs"
    - "wiz-member-add-row: flexbox single-row layout with .field--flex and .field--w-narrow modifiers"

key-files:
  created: []
  modified:
    - public/wizard.htmx.html
    - public/assets/css/wizard.css

key-decisions:
  - "All DOM IDs used by wizard.js preserved exactly — JS not touched in this plan (49 unique IDs confirmed)"
  - "step0 gets class='wiz-step active', steps 1-3 get class='wiz-step' only — no style=display:none on step panels"
  - "No overflow-y:auto anywhere — steps grow naturally to content, Suivant button always visible"
  - "wiz-member-add-row replaces old .wiz-member-add-form + .row layout for true single-row form"
  - "wiz-error-banner placed before .wiz-step-body so it appears at top without padding offset"
  - "Stepper connector lines via ::before on :not(:first-child) to avoid ::after conflict with active indicator"

patterns-established:
  - "Pattern: Error banner placement above step body (outside wiz-step-body padding) for clean full-width display"
  - "Pattern: Token-only CSS — if a new wizard component needs color, use var(--color-*), never hex"

requirements-completed: [REB-03]

# Metrics
duration: 20min
completed: 2026-03-22
---

# Phase 45 Plan 01: Wizard Rebuild HTML+CSS Summary

**Wizard HTML and CSS rewritten from scratch: 900px content track, horizontal field grids (form-grid-3/2), wizSlideIn/Out CSS transitions, stepper connector lines, step-level error banners, all 49 wizard.js DOM IDs preserved**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-03-22T14:15:00Z
- **Completed:** 2026-03-22T14:35:49Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- Rewritten `wizard.htmx.html` with 900px `.wiz-content` track; step0 is active, steps 1-3 have no inline display:none
- `form-grid-3` used for type/date/time row; `form-grid-2` for place/addr and vote rules — fields fit in 1024px viewport without scroll
- Member add form uses new `.wiz-member-add-row` flexbox layout: name, email, voting-power, add button all on one horizontal line
- Step-level error banners (`errBannerStep0/1/2`) with `hidden` attr — visible without JS, hidden by default
- Rewritten `wizard.css` with `wizSlideIn` (220ms X-translate) and `wizSlideOut` (180ms X-translate) keyframes
- Stepper refined with connector lines via `::before` pseudo-elements; filled/active/pending states via tokens
- Zero `overflow-y:auto` in new CSS — steps grow to content height naturally
- All CSS uses design tokens only — dark mode works without any explicit overrides

## Task Commits

Each task was committed atomically:

1. **Task 1: Rewrite wizard.htmx.html** - `d67ca01` (feat)
2. **Task 2: Rewrite wizard.css** - `e37ed97` (feat)

## Files Created/Modified

- `public/wizard.htmx.html` — Complete rewrite: 900px track, 4 steps with correct IDs, horizontal grids, error banners, no inline display:none on steps
- `public/assets/css/wizard.css` — Complete rewrite: slide keyframes, step visibility classes, stepper with connectors, member add row, error banner, no overflow-y

## Decisions Made

- **DOM IDs preserved exactly**: wizard.js uses getElementById for ~20 IDs and querySelector for class selectors — none changed
- **No style=display:none on steps 1-3**: CSS class-based visibility is the correct approach; the existing wizard.js showStep() still sets inline style but the CSS framework is ready for the JS update in a future plan
- **wiz-error-banner outside .wiz-step-body**: Placed at top of `.wiz-step` div so banner appears full-width without the step body's padding creating indent
- **Stepper connectors via ::before on :not(:first-child)**: Avoids conflict with any potential ::after usage on active state

## Deviations from Plan

None — plan executed exactly as written. Both files rewritten to spec; all acceptance criteria verified via grep.

## Issues Encountered

None — all criteria passed on first verification pass.

## Next Phase Readiness

- HTML and CSS foundation ready for wizard.js update (slide-out class toggling in showStep())
- All DOM IDs in place; JS can be updated to add `.slide-out` class and remove `.active` for smooth transitions
- Error banners in DOM ready for JS to populate `errBannerStepNText` content and remove `hidden` attr on validation failure

---
*Phase: 45-wizard-rebuild*
*Completed: 2026-03-22*
