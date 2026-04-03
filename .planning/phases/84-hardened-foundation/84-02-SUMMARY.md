---
phase: 84-hardened-foundation
plan: 02
subsystem: ui
tags: [css, design-tokens, oklch, color-system]

# Dependency graph
requires:
  - phase: 84-01
    provides: new design tokens --color-success-glow, --color-danger-glow, --color-danger-focus, --color-backdrop-heavy, --color-text-on-primary-muted
provides:
  - Zero hardcoded hex or rgba() values in any per-page CSS file (HARD-01 satisfied)
  - All 17 CSS files converted to var(--token) or oklch() literals
  - Token name mismatches corrected (--color-text-on-primary -> --color-primary-text, --color-primary-contrast -> --color-primary-text)
affects: [84-03, future per-page CSS files, dark mode theme changes]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "oklch() literals for white/black alpha values: oklch(1 0 0 / 0.XX) and oklch(0 0 0 / 0.XX)"
    - "Strip var() fallback literals — tokens exist in design-system.css, fallbacks are redundant"
    - "Token name --color-primary-text for text on primary-colored backgrounds (not --color-text-on-primary or --color-primary-contrast)"

key-files:
  created: []
  modified:
    - public/assets/css/operator.css
    - public/assets/css/users.css
    - public/assets/css/meetings.css
    - public/assets/css/analytics.css
    - public/assets/css/hub.css
    - public/assets/css/audit.css
    - public/assets/css/wizard.css
    - public/assets/css/settings.css
    - public/assets/css/report.css
    - public/assets/css/public.css
    - public/assets/css/vote.css
    - public/assets/css/postsession.css
    - public/assets/css/login.css
    - public/assets/css/landing.css
    - public/assets/css/email-templates.css
    - public/assets/css/archives.css
    - public/assets/css/app.css
    - public/assets/css/trust.css

key-decisions:
  - "Token name --color-primary-text is the canonical name for text on primary backgrounds — not --color-text-on-primary (which does not exist) and not --color-primary-contrast (which does not exist)"
  - "oklch() literals used for rgba(255,255,255,N) and rgba(0,0,0,N) patterns where no semantic token fits"
  - "Print media query in analytics.css uses var(--color-text/--color-bg) to respect dark mode token overrides even in print context"

patterns-established:
  - "oklch(1 0 0 / 0.XX) for semi-transparent white overlays"
  - "oklch(0 0 0 / 0.XX) for semi-transparent black shadows"
  - "Strip all var(--token, fallback-literal) patterns — fallback literals are dead code once tokens exist"

requirements-completed: [HARD-01]

# Metrics
duration: 11min
completed: 2026-04-03
---

# Phase 84 Plan 02: Hardened Foundation — Token Sweep Summary

**HARD-01 fully satisfied: zero hardcoded hex/rgba values across all 17 per-page CSS files, replaced with var(--token) references or oklch() literals**

## Performance

- **Duration:** ~11 min
- **Started:** 2026-04-03T10:33:46Z
- **Completed:** 2026-04-03T10:44:44Z
- **Tasks:** 2
- **Files modified:** 18 (17 planned + 1 auto-fixed)

## Accomplishments

- Eliminated all 66+ hardcoded hex/rgba occurrences across 16 planned CSS files
- Corrected token name mismatches: --color-text-on-primary and --color-primary-contrast both mapped to --color-primary-text
- Fixed analytics.css print media query to use var(--color-text/--color-bg) instead of bare #000/#fff
- Applied oklch() literals for white/black alpha values where no semantic token exists
- Auto-discovered and fixed trust.css (not in plan) bringing HARD-01 count to zero

## Task Commits

Each task was committed atomically:

1. **Task 1: Strip hardcoded hex/rgba from high-count CSS files (operator, users, meetings, analytics, hub)** - `54881dc0` (feat)
2. **Task 2: Strip hardcoded hex/rgba from remaining 12 CSS files** - `d90e059e` (feat)

## Files Created/Modified

- `public/assets/css/operator.css` — SSE status dots, overlay backdrops, primary-text references
- `public/assets/css/users.css` — purple/primary-subtle/success-subtle var() fallbacks stripped
- `public/assets/css/meetings.css` — status badge colors, shadow-lg fallback stripped
- `public/assets/css/analytics.css` — primary-text token fix, print media #000/#fff -> tokens, anomaly icon colors
- `public/assets/css/hub.css` — primary-glow, success/warning/primary/danger-subtle fallbacks stripped
- `public/assets/css/audit.css` — rgba overlays -> var(--color-backdrop)/oklch(), primary-contrast -> primary-text
- `public/assets/css/wizard.css` — 3x --color-text-on-primary -> --color-primary-text
- `public/assets/css/settings.css` — warning/border-strong fallbacks stripped, rgba -> oklch()
- `public/assets/css/report.css` — rgba(255,255,255,...) -> oklch() literals
- `public/assets/css/public.css` — obsolete rgba(--color-primary-rgb) pattern replaced, glow fallback stripped
- `public/assets/css/vote.css` — primary-glow fallback stripped
- `public/assets/css/postsession.css` — shadow-md fallback stripped
- `public/assets/css/login.css` — primary-muted fallback stripped
- `public/assets/css/landing.css` — rgba(255,255,255,0.85) -> var(--color-text-on-primary-muted)
- `public/assets/css/email-templates.css` — backdrop and shadow-2xl fallbacks stripped
- `public/assets/css/archives.css` — warning-text fallback stripped
- `public/assets/css/app.css` — --color-text-on-primary -> --color-primary-text
- `public/assets/css/trust.css` — shadow-xl fallback stripped (auto-fix, not in plan)

## Decisions Made

- Used oklch() literal form for white/black alpha values: `oklch(1 0 0 / 0.XX)` for white, `oklch(0 0 0 / 0.XX)` for black
- Token --color-primary-text is the correct name for text displayed on primary-colored backgrounds
- Print media query should use semantic tokens, not bare #000/#fff, to respect future theme changes

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed unlisted trust.css containing one hardcoded rgba()**
- **Found during:** Task 2 verification (final HARD-01 grep)
- **Issue:** `trust.css` not listed in plan had `var(--shadow-xl, 0 20px 60px rgba(0,0,0,.15))` — would have failed HARD-01 grep
- **Fix:** Stripped fallback: `var(--shadow-xl)`
- **Files modified:** `public/assets/css/trust.css`
- **Verification:** HARD-01 grep returns 0 results
- **Committed in:** d90e059e (Task 2 commit)

**2. [Rule 1 - Bug] Extra occurrence in email-templates.css (2 instead of 1 per plan)**
- **Found during:** Task 2 (email-templates.css)
- **Issue:** Plan noted 1 occurrence; actual file had 2 (--color-backdrop and --shadow-2xl fallbacks)
- **Fix:** Both fallbacks stripped
- **Files modified:** `public/assets/css/email-templates.css`
- **Verification:** File count = 0
- **Committed in:** d90e059e (Task 2 commit)

---

**Total deviations:** 2 auto-fixed (both Rule 1 - missed files / missed occurrences in research)
**Impact on plan:** Both fixes required for HARD-01 compliance. No scope creep.

## Issues Encountered

None — plan executed cleanly once the two extra occurrences were discovered during verification.

## Next Phase Readiness

- HARD-01 requirement fully satisfied: `grep -rn --include='*.css' -E "#[0-9a-fA-F]{3,6}|rgba\(" public/assets/css/ | grep -v design-system.css` returns 0 results
- 84-03 (HARD-03 or remaining hardening tasks) can proceed with confidence that the CSS token layer is clean

---
*Phase: 84-hardened-foundation*
*Completed: 2026-04-03*
