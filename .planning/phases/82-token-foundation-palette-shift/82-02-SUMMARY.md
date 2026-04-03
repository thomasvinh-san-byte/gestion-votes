---
phase: 82-token-foundation-palette-shift
plan: 02
subsystem: ui
tags: [css, design-system, oklch, dark-mode, color-tokens, palette, critical-tokens]

# Dependency graph
requires:
  - phase: 82-01
    provides: "Light-mode semantic tokens in oklch, all component-level color-mix(in srgb) upgraded to oklch"
provides:
  - "Dark mode surfaces warmed from cool hue ~260 to warm hue 78 (oklch)"
  - "All dark mode rgba() tokens converted to oklch alpha syntax"
  - "Dark mode hover tokens mix toward white (lightening direction) not black"
  - "All 21 .htmx.html critical-tokens blocks synced to matching oklch values"
  - "Zero color-mix(in srgb) calls remain anywhere in design-system.css"
affects:
  - 83-dark-mode-geometry
  - 84-hardening

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Dark mode surface hue 78 (warm-neutral): oklch(L C H) with H=78 for bg/surface tokens"
    - "Dark mode hover direction: color-mix(in oklch, base 88%, white) — lightens in dark context"
    - "Critical-tokens inline: oklch values matching design-system.css for flash-of-wrong-color prevention"
    - "oklch alpha transparency: oklch(L C H / alpha) for all overlay/subtle tokens"

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
  - "Dark mode surface hue set to 78 (warm-neutral) vs. prior ~260 (cool blue) — completes warm identity in both light and dark modes"
  - "Dark mode hover direction uses color-mix(in oklch, base 88%, white) — lightening in dark = perceived interactivity cue; prior hardcoded hex values were already lighter but now computed perceptually"
  - "Persona tokens left as hex values in dark mode — Phase 84 scope per plan"
  - "Sidebar bg kept at hue 260 (oklch(0.060 0.012 260)) — intentionally cool for visual contrast vs warm content area"
  - "Critical-tokens text --color-text uses oklch(0.908 0.015 265) (cool-white) for readability on warm-dark background — warm text on warm bg would reduce contrast"

patterns-established:
  - "Warm dark mode: bg/surface/border tokens at hue 78; text/primary at hue 265 — contrast through hue shift"
  - "Dark mode hover lightens via color-mix(in oklch, color 88%, white) — opposite direction from light mode (88%, black)"
  - "Critical-tokens inline style always matches design-system.css values exactly — single source of truth"

requirements-completed: [COLOR-02, COLOR-05]

# Metrics
duration: 5min
completed: 2026-04-03
---

# Phase 82 Plan 02: Warm Dark Mode Surfaces + Critical-Tokens Sync Summary

**Warm-neutral dark mode surfaces (hue 78 oklch) replacing cool blue-tinted dark (hue ~260), all dark rgba() converted to oklch alpha, and 21 critical-tokens blocks synced to prevent flash-of-wrong-color**

## Performance

- **Duration:** ~5 min
- **Started:** 2026-04-03T08:10:54Z
- **Completed:** 2026-04-03T08:16:00Z
- **Tasks:** 2 (task 3 is checkpoint:human-verify)
- **Files modified:** 22

## Accomplishments

- Replaced all dark mode surface tokens from cool hue ~260 (navy/slate) to warm hue 78 (dark chocolate/espresso) — dark mode now shares the warm-neutral identity with light mode
- Converted all dark mode rgba() tokens to oklch alpha syntax — eliminating last sRGB color specification in the dark theme block
- Fixed hover direction for dark mode: primary/success/warning/danger/info hovers now mix toward `white` (lightening = correct for dark context) instead of previous hardcoded hex that happened to be lighter
- Synced all 21 .htmx.html critical-tokens inline blocks with oklch values matching design-system.css — prevents flash-of-wrong-color on page load in both modes

## Task Commits

Each task was committed atomically:

1. **Task 1: Warm dark mode surfaces + convert dark rgba to oklch + fix derived tokens** - `c9561bff` (feat)
2. **Task 2: Sync critical-tokens inline blocks in all 21 .htmx.html files** - `3b394d94` (feat)

## Files Created/Modified

- `public/assets/css/design-system.css` - Dark mode [data-theme="dark"] block fully migrated: surfaces to hue 78, rgba() to oklch alpha, hover direction fixed to white
- `public/*.htmx.html` (21 files) - critical-tokens inline style updated from hex to oklch values matching design-system.css

## Decisions Made

- Dark mode sidebar background kept at `oklch(0.060 0.012 260)` (cool hue 260) — intentional contrast vs. warm hue 78 content area; sidebar uses cool blue as visual distinction
- Dark mode text tokens remain at hue 265 (cool white) — warm text on warm-dark background would reduce readability contrast; hue shift (265 vs 78) provides the contrast axis
- Persona tokens in dark mode left as hex values — Phase 84 scope per plan, decorative not semantic

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Rebased worktree onto main to incorporate 82-01 changes**
- **Found during:** Task 1 pre-execution
- **Issue:** Worktree branch was based on origin/main before 82-01 commits; design-system.css still had color-mix(in srgb) calls that 82-01 had already fixed on main
- **Fix:** Stashed local changes, rebased worktree-agent-a6fd8da3 onto main (which includes 82-01), resolved minor conflict in VIS-07 derived tint token comment, reapplied changes
- **Files modified:** public/assets/css/design-system.css
- **Verification:** git log shows 82-01 commits in history, srgb count = 0 after rebase
- **Committed in:** Conflict resolution committed via `git add` during rebase

---

**Total deviations:** 1 auto-fixed (1 blocking — worktree sync)
**Impact on plan:** Required rebase to get 82-01 foundation. Zero scope creep.

## Issues Encountered

Worktree was created from origin/main before 82-01 was committed. Resolved with `git stash` + `git rebase main` + conflict resolution. The conflict was trivial (comment text in the VIS-07 derived tint section — both sides had same oklch values, only differed in comment wording and spacing alignment).

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- Phase 82 complete: oklch token foundation (82-01) + warm dark mode + critical-tokens sync (82-02)
- Phase 83 (dark mode geometry) can proceed: all color token semantics are stable
- Checkpoint Task 3 (human visual verification) still pending — user should verify warm palette in browser before Phase 83

---
*Phase: 82-token-foundation-palette-shift*
*Completed: 2026-04-03*
