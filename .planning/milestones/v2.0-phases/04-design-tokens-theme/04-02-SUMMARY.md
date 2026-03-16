---
phase: 04-design-tokens-theme
plan: 02
subsystem: ui
tags: [css-custom-properties, design-tokens, dark-theme, wireframe-alignment]

# Dependency graph
requires:
  - phase: 04-design-tokens-theme plan 01
    provides: Light theme token alignment, --color-surface-alt token added to :root, validate-tokens.sh script
provides:
  - Complete dark theme token alignment with wireframe v3.19.2
  - --color-surface-alt defined in [data-theme="dark"] block
  - All semantic color tokens (danger/success/warn/purple) with bg/border variants in dark theme
  - Verified dark shadows using rgba(0,0,0,...) base
  - Full 64/64 token validation passing for both themes
  - Sidebar button background fix for dark theme
affects: [05-layout-shell, 06-component-css, all UI phases using design-system.css]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Dark theme tokens defined in [data-theme=dark] block as overrides; radius/transition inherit from :root"
    - "Semantic color triples: --color-{semantic}, --color-{semantic}-subtle, --color-{semantic}-border"
    - "Dark shadows use rgba(0,0,0,...); light shadows use rgba(21,21,16,...)"

key-files:
  created: []
  modified:
    - public/assets/css/design-system.css

key-decisions:
  - "Dark theme --color-surface-alt: #1B2030 mirrors light theme addition from Plan 01 for consistent surface elevation API"
  - "Sidebar button elements need explicit background:transparent in dark theme to avoid browser UA style bleed-through"

patterns-established:
  - "Token parity: any new token added to :root must also be explicitly set in [data-theme=dark] if it has a different dark value"
  - "Validation script (validate-tokens.sh --full) is the canonical check before committing design-system.css changes"

requirements-completed: [DS-02, DS-04]

# Metrics
duration: ~30min
completed: 2026-03-12
---

# Phase 4 Plan 02: Dark Theme Token Alignment Summary

**Dark theme tokens in design-system.css fully aligned with wireframe v3.19.2: --color-surface-alt added, semantic color triples (danger/success/warn/purple) verified, rgba(0,0,0,...) shadows confirmed, 64/64 validation passing.**

## Performance

- **Duration:** ~30 min
- **Started:** 2026-03-12
- **Completed:** 2026-03-12
- **Tasks:** 2 (1 auto + 1 human-verify checkpoint)
- **Files modified:** 1

## Accomplishments

- Added `--color-surface-alt: #1B2030` to `[data-theme="dark"]` block, completing the surface elevation token set in both themes
- Verified and confirmed all 12 semantic color sub-tokens (danger, success, warning, purple with -subtle and -border variants) match wireframe v3.19.2 exactly
- Confirmed all 5 dark shadow tokens use `rgba(0,0,0,...)` base (not the light theme's `rgba(21,21,16,...)`)
- Fixed sidebar `.nav-item` button elements missing `background: transparent; border: none` causing visual artifacts in dark theme
- User visually confirmed both light and dark themes render correctly without artifacts

## Task Commits

Each task was committed atomically:

1. **Task 1: Align dark theme tokens with wireframe v3.19.2** - `ee30689` (feat)
2. **Deviation fix: sidebar button background** - `31fbfc2` (fix)
3. **Task 2: Visual verification checkpoint** - approved by user (no code change)

## Files Created/Modified

- `public/assets/css/design-system.css` - Added `--color-surface-alt` to dark theme block; all dark tokens verified against wireframe v3.19.2

## Decisions Made

- Dark theme `--color-surface-alt: #1B2030` was added to maintain token API parity with the light theme addition from Plan 01
- Sidebar button fix applied as Rule 1 auto-fix (visual bug discovered during verification): `button.nav-item` was inheriting UA default button background in dark theme, requiring explicit `background: transparent; border: none` reset

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed .nav-item button elements showing default browser background in dark theme sidebar**
- **Found during:** Task 2 (Visual verification checkpoint)
- **Issue:** Button elements styled as `.nav-item` in the sidebar were not receiving `background: transparent` and `border: none` in the dark theme CSS, causing browser UA stylesheet defaults to bleed through visually
- **Fix:** Added explicit `background: transparent; border: none` reset for `button.nav-item` selectors in the dark theme sidebar rules
- **Files modified:** `public/assets/css/design-system.css`
- **Verification:** Visual inspection confirmed sidebar renders correctly in both themes after fix
- **Committed in:** `31fbfc2`

---

**Total deviations:** 1 auto-fixed (Rule 1 - Bug)
**Impact on plan:** Fix was necessary for visual correctness in dark theme. No scope creep.

## Issues Encountered

None beyond the sidebar button fix documented above.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Both light and dark themes in `design-system.css` are fully aligned with wireframe v3.19.2
- Validation script passes 64/64 tokens
- Design token foundation is complete and ready for Phase 5 (Layout Shell) and Phase 6 (Component CSS)
- No blockers

---
*Phase: 04-design-tokens-theme*
*Completed: 2026-03-12*
