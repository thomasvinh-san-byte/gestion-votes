---
phase: 84-hardened-foundation
plan: 01
subsystem: ui
tags: [css, design-system, oklch, css-properties, tokens, critical-tokens]

# Dependency graph
requires:
  - phase: 82-token-foundation-palette-shift
    provides: oklch primitive tokens and semantic token structure in design-system.css
  - phase: 83-component-geometry-chrome-cleanup
    provides: unified radius/shadow scale and alpha borders
provides:
  - "@property registrations for 8 core color tokens enabling CSS transition interpolation"
  - "5 new semantic tokens: success-glow, danger-glow, danger-focus, backdrop-heavy, text-on-primary-muted"
  - "Fixed focus ring using oklch instead of stale rgba(22,80,224,...)"
  - "Fixed shadow-focus-danger using var(--color-danger-focus) token reference"
  - "All 21 htmx.html critical-tokens inline styles synced to oklch values"
affects: [84-02, 84-03, downstream per-page CSS, Shadow DOM components]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "@property registration for animatable color tokens (syntax: '<color>', inherits: true)"
    - "oklch() with alpha channel for all color-with-opacity values (replaces rgba)"
    - "var() token references in shadow definitions (no rgba literals in semantic tokens)"
    - "critical-tokens inline style block uses oklch for FWCOF prevention"

key-files:
  created: []
  modified:
    - public/assets/css/design-system.css
    - public/admin.htmx.html
    - public/analytics.htmx.html
    - public/archives.htmx.html
    - public/audit.htmx.html
    - public/dashboard.htmx.html
    - public/docs.htmx.html
    - public/email-templates.htmx.html
    - public/help.htmx.html
    - public/hub.htmx.html
    - public/meetings.htmx.html
    - public/members.htmx.html
    - public/operator.htmx.html
    - public/postsession.htmx.html
    - public/public.htmx.html
    - public/report.htmx.html
    - public/settings.htmx.html
    - public/trust.htmx.html
    - public/users.htmx.html
    - public/validate.htmx.html
    - public/vote.htmx.html
    - public/wizard.htmx.html

key-decisions:
  - "@property registered for 8 core color tokens only — derived tokens (color-mix) excluded per spec (no var() in initial-value)"
  - "All rgba(22,80,224,...) and rgba(196,40,40,...) replaced with oklch equivalents, not just ring-color and shadow-focus-danger (stricter acceptance criteria honored)"
  - "HARD-03: 21 htmx.html critical-tokens blocks updated from hex to oklch (research was incorrect — files had stale hex values)"

patterns-established:
  - "@property pattern: syntax '<color>', inherits true, oklch initial-value with no var() references"
  - "oklch alpha pattern: oklch(L C H / alpha) replaces rgba() throughout design-system.css"

requirements-completed: [HARD-03, HARD-04, HARD-05]

# Metrics
duration: 3min
completed: 2026-04-03
---

# Phase 84 Plan 01: Token Foundation — @property + Critical-Tokens Sync Summary

**8 @property color registrations enabling CSS transition engine, 5 new glow/focus/backdrop tokens, and 21 htmx pages synced from stale hex to oklch critical-tokens**

## Performance

- **Duration:** 3 min
- **Started:** 2026-04-03T10:29:50Z
- **Completed:** 2026-04-03T10:32:33Z
- **Tasks:** 2
- **Files modified:** 22

## Accomplishments
- Added 8 `@property` color registrations before `@layer` declaration, enabling browser color interpolation in CSS transitions for core tokens
- Added 5 new semantic tokens (`--color-success-glow`, `--color-danger-glow`, `--color-danger-focus`, `--color-backdrop-heavy`, `--color-text-on-primary-muted`) in `:root` for downstream plans 02 and 03
- Fixed all stale `rgba(22,80,224,...)` and `rgba(196,40,40,...)` literals to `oklch()` equivalents — ring-color, shadow-focus-danger, border-focus, primary-muted, primary-glow, sidebar-active, component box-shadows
- Updated all 21 `.htmx.html` critical-tokens inline styles from hex to oklch, eliminating flash-of-wrong-color on page load

## Task Commits

Each task was committed atomically:

1. **Task 1: Register @property blocks and add new tokens to design-system.css** - `7915851a` (feat)
2. **Task 2: Verify HARD-03 critical-tokens in sync (updated all 21 files)** - `959f4274` (chore)

**Plan metadata:** (docs commit below)

## Files Created/Modified
- `public/assets/css/design-system.css` - 8 @property blocks, 5 new tokens, all rgba replaced with oklch
- `public/*.htmx.html` (21 files) - critical-tokens inline styles updated hex→oklch

## Decisions Made
- `@property` registration limited to 8 direct color tokens — derived tokens using `color-mix()` are excluded because `initial-value` does not accept `var()` references per CSS spec
- All `rgba(22,80,224,...)` and `rgba(196,40,40,...)` occurrences fixed, not just ring-color and shadow-focus-danger (acceptance criteria were stricter than action description)
- HARD-03 required actual updates to all 21 htmx.html files — research claim that files already carried oklch values was incorrect; they still had hex values

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed all rgba occurrences, not just ring-color and shadow-focus-danger**
- **Found during:** Task 1 (design-system.css modification)
- **Issue:** Acceptance criteria required zero `rgba(22,80,224,...)` and `rgba(196,40,40,...)` results in design-system.css, but the plan action only described fixing ring-color and shadow-focus-danger specifically
- **Fix:** Replaced all 7 remaining `rgba(22,80,224,...)` and 1 `rgba(196,40,40,...)` occurrences with `oklch()` equivalents in tokens and components
- **Files modified:** `public/assets/css/design-system.css`
- **Verification:** `grep "rgba(22, 80, 224" design-system.css` and `grep "rgba(196, 40, 40" design-system.css` both return 0 results
- **Committed in:** 7915851a (Task 1 commit)

**2. [Rule 1 - Bug] HARD-03 files had stale hex values — required updates to all 21 files**
- **Found during:** Task 2 (HARD-03 verification)
- **Issue:** Research claimed all 21 .htmx.html files already carried correct oklch values, but verification showed all files still had hex values (`#EDECE6`, `#FAFAF7`, etc.)
- **Fix:** Replaced critical-tokens inline style blocks in all 21 files with correct oklch equivalents
- **Files modified:** All 21 `public/*.htmx.html` files
- **Verification:** `grep -rl "color-bg: oklch(0.922" public/*.htmx.html | wc -l` returns 21
- **Committed in:** 959f4274 (Task 2 commit)

---

**Total deviations:** 2 auto-fixed (both Rule 1 - bug/incorrect state detection)
**Impact on plan:** Both fixes essential for correctness. Plan acceptance criteria accurately described the desired end-state; execution correctly achieved it.

## Issues Encountered
- Research in plan was incorrect about critical-tokens sync status — all 21 files had stale hex values. Plan completion required updating all files (2 task commits instead of 1).

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Token foundation complete: `@property` registrations enable smooth color transitions for plan 02 (component focus rings) and plan 03 (page-specific glow effects)
- All 5 new tokens (`--color-danger-focus`, `--color-success-glow`, etc.) available for consumption in downstream plans
- No blockers

---
*Phase: 84-hardened-foundation*
*Completed: 2026-04-03*
