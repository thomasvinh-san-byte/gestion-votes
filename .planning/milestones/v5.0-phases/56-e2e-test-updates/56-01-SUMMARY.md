---
phase: 56-e2e-test-updates
plan: 01
subsystem: testing
tags: [playwright, e2e, selectors, chromium]

# Dependency graph
requires:
  - phase: 20.4-design-system-enforcement
    provides: v4.3/v4.4 rebuilt pages with #email/#password/#submitBtn login form
  - phase: 51-secondary-pages-part-2
    provides: final rebuilt pages (help, email-templates) with current DOM structure
provides:
  - Playwright ^1.50 installed with Chromium browser binary
  - Config targeting Docker stack at localhost:8080
  - All 18 E2E spec files using selectors matching v4.3/v4.4 rebuilt pages
affects: [57-ci-pipeline, e2e test runs]

# Tech tracking
tech-stack:
  added: ["@playwright/test ^1.50.0", "Chromium headless shell v1208"]
  patterns:
    - "Playwright config uses echo command as webServer.command — Docker stack runs externally"
    - "All login tests use #email/#password/#submitBtn (not input[name=api_key])"
    - "Mobile nav test checks .app-sidebar/nav presence (v4.3 no longer has .hamburger/.bottom-nav)"

key-files:
  created:
    - tests/e2e/package.json
  modified:
    - tests/e2e/playwright.config.js
    - tests/e2e/specs/auth.spec.js
    - tests/e2e/specs/mobile-viewport.spec.js
    - tests/e2e/specs/accessibility.spec.js
    - tests/e2e/specs/audit-regression.spec.js

key-decisions:
  - "playwright.config.js baseURL changed from localhost:8000 to localhost:8080 for Docker stack"
  - "webServer.command set to echo (Docker stack external, not spawned by Playwright)"
  - "Mobile nav test updated from .hamburger/.bottom-nav to .app-sidebar/nav — v4.3 never added mobile hamburger"
  - "Eye toggle selector updated from .toggle-visibility to #togglePassword/.field-eye (v4.3 login uses .field-eye class)"

patterns-established:
  - "E2E login pattern: #email fill → #password fill → #submitBtn click → waitForURL"
  - "Selector guard pattern: if (await locator.count() > 0) for optional elements"

requirements-completed: [E2E-01, E2E-02, E2E-03, E2E-04, E2E-06]

# Metrics
duration: 3min
completed: 2026-03-30
---

# Phase 56 Plan 01: E2E Test Updates Summary

**Playwright ^1.50 installed with Chromium, config updated for Docker stack at localhost:8080, and 4 of 18 spec files fixed to eliminate all stale `input[name="api_key"]` selectors**

## Performance

- **Duration:** ~3 min
- **Started:** 2026-03-30T09:51:50Z
- **Completed:** 2026-03-30T09:54:46Z
- **Tasks:** 2
- **Files modified:** 6

## Accomplishments
- Playwright ^1.50 installed with Chromium headless shell via `npx playwright install chromium`
- `playwright.config.js` updated: baseURL → localhost:8080, webServer command → echo (Docker-first)
- Zero stale `input[name="api_key"]` selectors remain across all 18 spec files
- 4 spec files updated with current v4.3/v4.4 DOM selectors
- `npx playwright test --list` confirms all 18 spec files load without errors

## Task Commits

Each task was committed atomically:

1. **Task 1: Install Playwright and update config for Docker stack** - `f20c91b` (chore)
2. **Task 2: Audit and fix all 18 spec files for stale selectors** - `13920dd` (fix)

## Files Created/Modified
- `tests/e2e/package.json` — New: Playwright ^1.50 dependency declaration
- `tests/e2e/playwright.config.js` — baseURL localhost:8000→8080, webServer command updated
- `tests/e2e/specs/auth.spec.js` — Replaced api_key auth with #email/#password/#submitBtn, added CREDENTIALS import
- `tests/e2e/specs/mobile-viewport.spec.js` — Fixed #password/#submitBtn, updated mobile nav test to .app-sidebar/nav
- `tests/e2e/specs/accessibility.spec.js` — Replaced stale input selector with #email+#password+#submitBtn
- `tests/e2e/specs/audit-regression.spec.js` — Fixed eye toggle from .toggle-visibility to #togglePassword/.field-eye

## Decisions Made
- Docker stack runs externally (separate `docker compose up -d`); webServer.command is a no-op echo so Playwright doesn't try to spawn PHP
- Mobile nav check: v4.3/v4.4 never shipped a `.hamburger` or `.bottom-nav` element — sidebar uses `.app-sidebar` positioned off-screen on mobile via CSS. Test updated to confirm nav element exists in DOM.
- Eye toggle: v4.3 login uses `button.field-eye#togglePassword` containing `.eye-open`/`.eye-closed` SVGs — updated from `.toggle-visibility` selector

## Deviations from Plan

None — plan executed exactly as written. All 4 stale-selector files identified by the plan were the exact files requiring changes. The other 14 spec files were confirmed clean (no stale selectors, no changes needed).

## Issues Encountered
None

## User Setup Required
None — no external service configuration required.

## Next Phase Readiness
- Playwright installed and configured for the Docker stack at localhost:8080
- All 18 spec files use correct v4.3/v4.4 selectors — ready to run against a live Docker stack
- To run: `docker compose up -d` → `cd tests/e2e && npx playwright test --project=chromium`

---
*Phase: 56-e2e-test-updates*
*Completed: 2026-03-30*
