---
phase: 01-contrast-aa-remediation
plan: 03
subsystem: ui
tags: [css, oklch, wcag, contrast, accessibility, axe-core, playwright, design-tokens]

# Dependency graph
requires:
  - phase: 01
    plan: 01
    provides: "oklch shift of --color-text-muted and --color-primary-on-subtle companion token"
  - phase: 01
    plan: 02
    provides: "Shadow DOM fallback-free state — 23/23 Web Components use var(--color-*) without second operand"
provides:
  - "316 contrast violations reduced to 0 across 22 pages (empirically verified via axe-core)"
  - "9 companion on-subtle tokens for text-on-subtle-background contrast AA compliance"
  - "v1.3-A11Y-REPORT.md declares WCAG 2.1 AA CONFORME (was partiellement conforme)"
  - "contrast-audit.spec.js improved with cache-busting CDP, animation settle wait, totalViolations/uniquePairs aggregation"
affects:
  - "Future design token changes — L* values documented, on-subtle pattern established"
  - "Phase 2+ CSS work — muted/primary/success tokens are now darker than original"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Companion on-subtle token pattern: for each semantic color, add --color-X-on-subtle for text on X-subtle backgrounds"
    - "CDP Network.setCacheDisabled in Playwright to bypass immutable CSS headers in audit specs"
    - "500ms animation settle wait before axe analysis to avoid mid-fadeIn contrast false positives"

key-files:
  created:
    - .planning/phases/01-contrast-aa-remediation/01-03-VERIFICATION.md
  modified:
    - public/assets/css/design-system.css
    - public/assets/css/wizard.css
    - public/assets/css/members.css
    - public/assets/css/meetings.css
    - public/assets/css/doc.css
    - public/assets/css/help.css
    - public/assets/css/pages.css
    - public/assets/css/report.css
    - public/assets/css/users.css
    - public/assets/css/settings.css
    - tests/e2e/specs/contrast-audit.spec.js
    - .planning/v1.3-CONTRAST-AUDIT.json
    - .planning/v1.3-A11Y-REPORT.md

key-decisions:
  - "--color-text-muted iteratively darkened oklch L* 0.470 -> 0.340 (5 iterations) to pass AA on all warm backgrounds including striped rows and mid-animation states"
  - "--color-primary darkened L* 0.520 -> 0.480 to ensure white-on-primary button text meets 4.5:1"
  - "Companion on-subtle token pattern adopted for success, accent, purple — mirrors --color-primary-on-subtle from plan 01-01"
  - "opacity rules on wizard steps and onboarding labels removed — opacity degrades contrast and tokens are now dark enough to stand alone"
  - "CDP cache-disable required because nginx immutable header prevents CSS revalidation within same browser session"
  - "500ms animation settle wait added to audit spec to avoid axe measuring contrast during fadeIn transitions"

patterns-established:
  - "On-subtle companion pattern: --color-X-on-subtle for text on X-subtle bg — prevents brand token darkening"
  - "Audit spec must disable cache and wait for animations before measuring contrast"

requirements-completed: [CONTRAST-01, CONTRAST-04]

# Metrics
duration: 20min
completed: 2026-04-10
---

# Phase 01 Plan 03: Empirical contrast verification Summary

**316 contrast violations iteratively reduced to 0 across 22 pages via 5 axe-core runs, 26 micro-adjustments to 10 CSS files + 9 new on-subtle companion tokens, A11Y-REPORT declared CONFORME**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-04-10T05:04:00Z
- **Completed:** 2026-04-10T05:24:00Z
- **Tasks:** 3 complete (Task 4 checkpoint pending, Task 5 merged into commit flow)
- **Files modified:** 13

## Accomplishments

- Achieved 0 contrast violations across all 22 pages verified empirically by axe-core
- Iteratively darkened --color-text-muted from L*0.470 to L*0.340 over 5 audit runs
- Added 6 new companion tokens (success-on-subtle, accent-on-subtle, purple-on-subtle) in both themes
- Darkened 3 existing text tokens (success-text, danger-text, accent-text) for AA on subtle backgrounds
- Rewired ~20 CSS elements to use on-subtle tokens instead of raw semantic colors
- Fixed CSS caching issue in Playwright audit by adding CDP cache-disable
- Fixed animation timing issue by adding 500ms settle wait before axe analysis
- Confirmed 0 regression: accessibility.spec.js 26 passed, keyboard-nav.spec.js 6 passed
- Updated v1.3-A11Y-REPORT.md: "partiellement conforme" -> "CONFORME"

## Task Commits

1. **Task 1: Contrast audit + iterative remediation** - `04a90287` (fix), `7fe099b1` (fix), `b2a4e109` (fix)
2. **Task 2: Regression specs** - verified inline (26+6 passed), log in `68bbb000` (docs)
3. **Task 3: A11Y-REPORT update** - `f28b831b` (docs)

## Files Created/Modified

**Created:**
- `.planning/phases/01-contrast-aa-remediation/01-03-VERIFICATION.md` — detailed log of 5 audit runs, token adjustments, regression results

**Modified:**
- `public/assets/css/design-system.css` — 9 token value changes (muted, primary, success-text, danger-text, accent-text) + 6 new companion tokens + tag/badge rewiring
- `public/assets/css/wizard.css` — removed opacity:0.6 from .wiz-step-item
- `public/assets/css/members.css` — removed opacity:0.7 from .onboarding-optional, rewired done/action steps
- `public/assets/css/meetings.css` — filter-pill-count dark tint instead of white mix
- `public/assets/css/doc.css` — .prose a + .doc-breadcrumb a -> --color-primary-on-subtle
- `public/assets/css/help.css` — .tour-launch + .tour-icon -> --color-primary-on-subtle
- `public/assets/css/pages.css` — .dashboard-urgent__eyebrow -> --color-danger-text
- `public/assets/css/report.css` — .pv-timeline-step.done .pv-step-label -> --color-success-on-subtle
- `public/assets/css/users.css` — all 8 avatar/badge/status variants -> on-subtle tokens
- `public/assets/css/settings.css` — .settings-sidenav-item.active -> --color-primary-on-subtle
- `tests/e2e/specs/contrast-audit.spec.js` — CDP cache-disable, animation wait, totalViolations/uniquePairs output
- `.planning/v1.3-CONTRAST-AUDIT.json` — regenerated with totalViolations: 0
- `.planning/v1.3-A11Y-REPORT.md` — S3 + S6 updated, CONFORME declared

## Decisions Made

- **Iterative L* darkening over 5 runs**: Started conservative (L*0.470) and darkened in 4 steps to L*0.340. Each step fixed a subset of violations. Going lower than 0.340 would make muted text indistinguishable from regular text.
- **Primary darkened L*0.520 -> 0.480**: Minimal brand impact (imperceptible), ensures white button text meets 4.5:1. Only light mode affected; dark mode primary untouched.
- **Companion on-subtle pattern**: Rather than darkening semantic tokens globally (which would change button/border appearances), add dedicated on-subtle variants for text contexts on subtle backgrounds.
- **Opacity removal over color darkening**: wizard step items had opacity:0.6 which reduced contrast multiplicatively. Removing opacity and relying on the darker muted token gives direct control over the contrast ratio.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] CSS browser cache prevented token changes from being read by Playwright**
- **Found during:** Task 1 (Run 4)
- **Issue:** Nginx serves CSS with `Cache-Control: public, immutable`. Auth-setup loaded CSS first, browser cached it, subsequent page navigations in the same test used stale tokens.
- **Fix:** Added `Network.setCacheDisabled` via CDP session in contrast-audit.spec.js
- **Files modified:** tests/e2e/specs/contrast-audit.spec.js
- **Committed in:** 7fe099b1

**2. [Rule 1 - Bug] CSS fadeIn animation caused axe to measure contrast mid-transition**
- **Found during:** Task 1 (Run 5 analysis)
- **Issue:** `.animate-fade-in` elements on archives/audit pages start at opacity:0 and transition to opacity:1. Axe captured the element at partial opacity, computing a lighter foreground color.
- **Fix:** Added 500ms `page.waitForTimeout()` after page load to let animations settle before axe analysis
- **Files modified:** tests/e2e/specs/contrast-audit.spec.js
- **Committed in:** b2a4e109

**3. [Rule 2 - Missing critical] Multiple element types used raw semantic tokens on subtle backgrounds**
- **Found during:** Task 1 (Runs 2-5)
- **Issue:** Role badges, user avatars, filter pills, tour chips, settings tabs, docs links all used `--color-primary`/`--color-success`/`--color-purple` directly on their subtle backgrounds, failing AA.
- **Fix:** Created on-subtle companion tokens and rewired ~20 CSS selectors across 10 files
- **Files modified:** design-system.css, users.css, help.css, doc.css, settings.css, members.css, meetings.css, pages.css, report.css
- **Committed in:** 04a90287, 7fe099b1

---

**Total deviations:** 3 auto-fixed (1 blocking, 1 bug, 1 missing critical)
**Impact on plan:** All deviations were necessary to achieve the 0-violation target. The plan anticipated "micro-adjustments" but underestimated the breadth of elements using raw tokens on subtle backgrounds. The companion on-subtle pattern scales well for future color additions.

## Issues Encountered

- **Nginx immutable cache headers**: Prevented CSS updates from reaching the Playwright browser within a single test session. Resolved with CDP cache-disable. This is a dev-only issue; production rebuilds the image.
- **Session-card-meta hex fallback**: Found `var(--color-text-muted, #95a3a4)` in design-system.css — a stale hex fallback that was overriding the oklch token. Stripped it (Pitfall #1 pattern, same as Plan 01-02 but in CSS files instead of Web Components).

## User Setup Required

None.

## Next Phase Readiness

Phase 1 (Contrast AA Remediation) is complete:
- 316 -> 0 contrast violations (empirically verified)
- 110 Shadow DOM hex fallbacks stripped
- v1.3-A11Y-REPORT.md declares WCAG 2.1 AA CONFORME
- accessibility.spec.js 26/26, keyboard-nav.spec.js 6/6 — no regressions
- Task 4 (human-verify checkpoint) pending: visual review of 5 canary pages in light+dark mode

Ready for Phase 2 (Overlay Hittest Sweep) after checkpoint approval.

## Self-Check: PASSED

Verified post-commit:
- [x] `.planning/phases/01-contrast-aa-remediation/01-03-VERIFICATION.md` exists
- [x] `.planning/phases/01-contrast-aa-remediation/01-03-SUMMARY.md` exists (this file)
- [x] `.planning/v1.3-CONTRAST-AUDIT.json` exists with `totalViolations: 0`
- [x] `.planning/v1.3-A11Y-REPORT.md` contains "CONFORME" (3 occurrences)
- [x] Commits 04a90287, 7fe099b1, b2a4e109, 68bbb000, f28b831b all exist in git log

---
*Phase: 01-contrast-aa-remediation*
*Completed: 2026-04-10*
