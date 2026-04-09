---
phase: 16-accessibility-deep-audit
plan: 01
subsystem: testing
tags: [accessibility, axe-core, playwright, a11y, wcag]

# Dependency graph
requires:
  - phase: 07-tests-phase (baseline)
    provides: initial accessibility.spec.js with 7 hand-written axe tests
  - phase: 15-multi-browser-tests
    provides: Playwright multi-project config used by --list enumeration
provides:
  - WIP seed fixes committed (SettingsController unwrap, operator roles, settings a11y, axe debug, strict-mode fix)
  - axeAudit runner extended with extraDisabledRules option (D-10 plumbing)
  - Parametrized accessibility.spec.js covering 22 pages (login + 21 HTMX) via PAGES array
affects: [16-02-baseline-axe-run, 16-03-batch-fixes, 16-04-keyboard-nav, 16-05-report]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Data-driven axe matrix: PAGES array with { path, loginFn, requiredLocator, extraDisabled? }"
    - "Per-page waiver plumbing via axeAudit options.extraDisabledRules"

key-files:
  created: []
  modified:
    - app/Controller/SettingsController.php
    - public/assets/css/settings.css
    - public/assets/js/pages/settings.js
    - public/operator.htmx.html
    - tests/e2e/helpers/axeAudit.js
    - tests/e2e/specs/accessibility.spec.js

key-decisions:
  - "WIP fixes committed as atomic seed commit before extending infrastructure"
  - "axeAudit.extraDisabledRules merged with default ['color-contrast'] list, backward compatible"
  - "trust.htmx.html uses loginAsAdmin fallback (auditor/assessor not in auth fixtures) — to validate at baseline run"
  - "wizard.htmx.html scoped to step 1 only per Phase 16 research"
  - "4 unit-level 'Accessibility' tests preserved alongside parametrized axe matrix"

patterns-established:
  - "Pattern 1: Per-page waivers via axeAudit(page, path, { extraDisabledRules: [...] }) with inline A11Y-WAIVER comment"
  - "Pattern 2: PAGES array loop inside test.describe for data-driven Playwright matrices"

requirements-completed: [A11Y-01]

# Metrics
duration: 4min
completed: 2026-04-09
---

# Phase 16 Plan 01: A11y Audit Infrastructure Summary

**Axe audit matrix extended from 7 hand-written tests to a 22-page parametrized PAGES array with per-page waiver plumbing, unblocking A11Y-01 baseline.**

## Performance

- **Duration:** ~4 min
- **Started:** 2026-04-09T09:15:55Z
- **Completed:** 2026-04-09T09:19:45Z
- **Tasks:** 3
- **Files modified:** 6

## Accomplishments
- Seeded Phase 16 by committing 6 pre-diagnosed WIP fixes (Settings page a11y, operator live regions, axe debug, strict-mode fix)
- Extended `axeAudit()` to accept `options.extraDisabledRules` for per-page waivers (D-10), backward compatible
- Replaced 7 hand-written axe tests with a single parametrized `for` loop driven by a 22-entry `PAGES` array (login + 21 HTMX pages)
- `npx playwright test specs/accessibility.spec.js --list` now enumerates 22 axe tests per project (130 total across 5 browser projects + 4 unit-level accessibility tests)

## Task Commits

1. **Task 1: Commit WIP seed fixes** — `357d84c0` (fix)
2. **Task 2: Extend axeAudit.js with extraDisabledRules** — `1d645656` (feat)
3. **Task 3: Parametrize accessibility.spec.js to 22 pages** — `845abe90` (test)

## Files Created/Modified
- `app/Controller/SettingsController.php` — `api_request('GET','POST')` + `api_ok($data)` unwrap
- `public/assets/css/settings.css` — `.settings-panel[hidden]{display:none}` override flex
- `public/assets/js/pages/settings.js` — `aria-label` on quorum icon edit/delete buttons
- `public/operator.htmx.html` — `role="status"` on live dots, `role="progressbar"` on resolution progress
- `tests/e2e/helpers/axeAudit.js` — New `options.extraDisabledRules` param merged with default disable list; JSDoc updated
- `tests/e2e/specs/accessibility.spec.js` — Replaced 7-test block with `const PAGES = [...]` + `for` loop; kept 4 unit-level tests intact

## PAGES Array Shape

```js
const PAGES = [
  { path, loginFn, requiredLocator, extraDisabled? },
  // ...22 entries total
];
```

- `path`: page URL (login.html, *.htmx.html)
- `loginFn`: null (anonymous) or `loginAsOperator|loginAsAdmin|loginAsVoter`
- `requiredLocator`: CSS selector that must be visible before axe runs (HTMX hydration safety)
- `extraDisabled?`: optional string[] of axe rule ids to waive for that page (currently empty on all 22 entries — baseline run in plan 16-02 will populate waivers with justification)

## Decisions Made
- WIP committed as-is in Task 1 (no refactor) to give baseline run in plan 16-02 a clean starting point
- `axeAudit` default disable list remains `['color-contrast']` (D-04); new param extends it without removing defaults
- Kept the 4 unit-level 'Accessibility' describe tests (form/heading/keyboard/landmarks) — they complement the axe matrix without duplicating it

## Deviations from Plan

None — plan executed exactly as written.

Note: the `tests/e2e/.auth/*.json` files mentioned in Task 1's `<files>` list were not actually modified in the working tree at execution time (the initial status snapshot was stale); staging was limited to the 6 files that actually showed as modified. This does not affect the plan's outcome — those auth fixtures are not part of A11Y-01's scope.

## Issues Encountered
None.

## Known Open Questions (to validate in plan 16-02)

- **trust.htmx.html admin fallback**: plan assumes `loginAsAdmin` satisfies the `data-page-role="auditor,assessor"` gate. If the page still blocks admin, plan 16-02 will need to either (a) extend the fixture with an auditor session, or (b) mark the page as skipped with a waiver.
- **wizard.htmx.html step scope**: the PAGES entry audits step 1 only. If the wizard auto-advances on load in some environments, the baseline run may capture a different step.
- **vote.htmx.html requiredLocator**: `#meetingSelect` assumed present for voters — if the voter has no active meeting, locator may not appear and the test will fail at hydration wait (not at axe).

## User Setup Required
None.

## Next Phase Readiness

- **Ready for plan 16-02 (baseline run)**: `bin/test-e2e.sh specs/accessibility.spec.js` will now produce a 22-row axe matrix on chromium. Expected failures (from the WIP seed context) are concentrated on operator/settings; other pages are unknown until the run.
- **Downstream (16-03 batch fix, 16-04 keyboard nav, 16-05 report)** all depend on the baseline matrix from 16-02.

## Self-Check: PASSED

- FOUND: app/Controller/SettingsController.php (commit 357d84c0)
- FOUND: public/operator.htmx.html (commit 357d84c0)
- FOUND: tests/e2e/helpers/axeAudit.js (extraDisabledRules in 1d645656)
- FOUND: tests/e2e/specs/accessibility.spec.js (PAGES array in 845abe90)
- FOUND: commit 357d84c0 (Task 1)
- FOUND: commit 1d645656 (Task 2)
- FOUND: commit 845abe90 (Task 3)
- VERIFIED: `npx playwright test --list` enumerates 22 axe tests per project (130 total)

---
*Phase: 16-accessibility-deep-audit*
*Completed: 2026-04-09*
