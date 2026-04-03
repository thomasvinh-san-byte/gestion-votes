---
phase: quick
plan: 260403-gtt
subsystem: ui
tags: [css, oklch, color-mix, design-system, persona-tokens]

requires:
  - phase: 82-token-foundation-palette-shift
    provides: oklch token foundation in design-system.css
  - phase: 84-hardened-foundation
    provides: hardcoded hex/rgba stripped from 17 CSS files

provides:
  - 76 color-mix calls upgraded from srgb to oklch interpolation across 10 page CSS files
  - 7 dark mode persona-subtle tokens using color-mix(in oklch, var(--persona-X) 15%, transparent)
  - Zero remaining color-mix(in srgb) calls in entire CSS codebase

affects: [design-system, vote-ui, members-ui, operator-ui, persona-tokens]

tech-stack:
  added: []
  patterns:
    - "color-mix(in oklch) for perceptually uniform color interpolation — complete codebase adoption"
    - "Persona-subtle tokens derive from persona base vars dynamically via color-mix, not hardcoded rgba"

key-files:
  created: []
  modified:
    - public/assets/css/vote.css
    - public/assets/css/members.css
    - public/assets/css/operator.css
    - public/assets/css/pages.css
    - public/assets/css/public.css
    - public/assets/css/meetings.css
    - public/assets/css/landing.css
    - public/assets/css/login.css
    - public/assets/css/postsession.css
    - public/assets/css/archives.css
    - public/assets/css/design-system.css

key-decisions:
  - "color-mix(in oklch) adopted codebase-wide — srgb interpolation fully eliminated"
  - "Persona-subtle dark mode tokens now derive from var(--persona-X) base colors, eliminating duplicate hardcoded values"

patterns-established:
  - "All color derivations use color-mix(in oklch) — never srgb — for perceptually uniform blending"

requirements-completed: []

duration: 8min
completed: 2026-04-03
---

# Quick Task 260403-gtt: Upgrade color-mix srgb to oklch Summary

**76 color-mix(in srgb) calls replaced with oklch across 10 page CSS files, plus 7 rgba() dark mode persona-subtle tokens replaced with dynamic color-mix(in oklch) expressions referencing persona base variables**

## Performance

- **Duration:** ~8 min
- **Started:** 2026-04-03T00:00:00Z
- **Completed:** 2026-04-03
- **Tasks:** 2
- **Files modified:** 11

## Accomplishments

- Replaced all 76 `color-mix(in srgb,` occurrences with `color-mix(in oklch,` across 10 per-page CSS files (vote.css 43, members.css 12, operator.css 6, pages.css 4, public.css 4, meetings.css 2, landing.css 2, login.css 1, postsession.css 1, archives.css 1)
- Replaced 7 hardcoded `rgba()` persona-subtle dark mode tokens in design-system.css with `color-mix(in oklch, var(--persona-X) 15%, transparent)` — tokens now derive from persona base variables dynamically
- oklch color space now fully consistent across the entire CSS codebase — no srgb color-mix anywhere

## Task Commits

Each task was committed atomically:

1. **Task 1: Replace color-mix(in srgb) with color-mix(in oklch) in 10 page CSS files** - `8f4c4460` (feat)
2. **Task 2: Replace rgba() persona-subtle tokens with color-mix(in oklch) in design-system.css** - `e477c062` (feat)

## Files Created/Modified

- `public/assets/css/vote.css` - 43 color-mix srgb → oklch replacements
- `public/assets/css/members.css` - 12 replacements
- `public/assets/css/operator.css` - 6 replacements
- `public/assets/css/pages.css` - 4 replacements
- `public/assets/css/public.css` - 4 replacements
- `public/assets/css/meetings.css` - 2 replacements
- `public/assets/css/landing.css` - 2 replacements
- `public/assets/css/login.css` - 1 replacement
- `public/assets/css/postsession.css` - 1 replacement
- `public/assets/css/archives.css` - 1 replacement
- `public/assets/css/design-system.css` - 7 persona-subtle dark mode tokens converted from rgba() to color-mix(in oklch)

## Decisions Made

None - followed plan as specified.

## Deviations from Plan

### Verify Condition Discrepancy (documentation only — not a code fix)

The plan's Task 2 verify command checks for zero `rgba()` in design-system.css. However, the file contains ~52 additional rgba() calls in box-shadows, sidebar styling, component rules, and scrollbar definitions that were never part of this task's scope (and were not addressed in Phase 84 either). The verify condition as written would always fail regardless of the persona token replacements.

**Outcome:** The 7 persona-subtle tokens targeted by the plan were successfully replaced. The remaining rgba() values are structural CSS properties (box-shadow, background overlays, scrollbar styling) that are out of scope for an oklch token migration. No code fix was applied — this is a plan verify condition inaccuracy.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- oklch migration is now complete across all CSS layers: design-system tokens, page CSS color-mix calls, and persona-subtle tokens
- Remaining rgba() in design-system.css are structural (box-shadows, overlays) — acceptable as they do not represent color tokens
- Codebase ready for any future color system changes knowing oklch is uniformly applied

---
*Phase: quick*
*Completed: 2026-04-03*
