---
phase: 12-page-by-page-mvp-sweep
plan: 12
subsystem: ui
tags: [css, playwright, e2e, admin, width, design-tokens]

# Dependency graph
requires: []
provides:
  - admin page full-width layout (720px cap removed)
  - Playwright function gate for admin.htmx.html page interactions
affects: [12-page-by-page-mvp-sweep]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "E2E page spec pattern: loginAsAdmin + observable DOM assertions (no form submit to avoid DB side effects)"
    - "Width fix pattern: replace max-width + margin-inline: auto with width: 100% for applicative pages"

key-files:
  created:
    - tests/e2e/specs/critical-path-admin-page.spec.js
  modified:
    - public/assets/css/admin.css

key-decisions:
  - "admin-content width: 100% — applicative pages (KPI dashboard + management tables) must fill viewport width, not be constrained by content-narrow token"
  - "Spec named critical-path-admin-PAGE.spec.js to avoid collision with critical-path-admin.spec.js (admin ROLE flow)"
  - "E2E test does NOT submit create user form — fills fields then clears, avoiding test user creation in DB"

patterns-established:
  - "Applicative pages use width: 100%, not max-width content tokens"
  - "Password strength assertion: check for class attribute matching weak|fair|good|strong"

requirements-completed: [MVP-01, MVP-02, MVP-03]

# Metrics
duration: 15min
completed: 2026-04-09
---

# Phase 12 Plan 12: Admin Page MVP Sweep Summary

**Admin page fixed to full-width (720px cap removed) and gated by Playwright spec asserting KPI load, search, role filter, create form + password strength, refresh, and zero horizontal scroll**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-04-09T04:48:00Z
- **Completed:** 2026-04-09T05:03:38Z
- **Tasks:** 2 / 2
- **Files modified:** 2

## Accomplishments

- Removed `max-width: var(--content-narrow, 720px)` and `margin-inline: auto` from `.admin-content`, replaced with `width: 100%` — admin page now uses full viewport width
- Verified admin.css has zero hex/oklch/rgba color literals (all values are design-system tokens)
- Created `critical-path-admin-page.spec.js` covering 7 interaction categories with observable DOM assertions
- E2E test passes: 1 passed in 7.8s

## Task Commits

1. **Task 1: Width gate — fix admin.css 720px container cap** - `697bf499` (fix)
2. **Task 2: Function gate — Playwright spec admin page interactions** - `62dcfa3b` (feat)

**Plan metadata:** See final docs commit.

## Files Created/Modified

- `public/assets/css/admin.css` - Removed 720px max-width cap, replaced with `width: 100%`
- `tests/e2e/specs/critical-path-admin-page.spec.js` - Playwright function gate: KPI load, search, role filter, create form, password strength, refresh, width check

## Decisions Made

- Named spec `critical-path-admin-page.spec.js` (not `critical-path-admin.spec.js`) to avoid collision with existing admin ROLE flow spec
- E2E test fills create form fields but does NOT click submit — avoids creating test users in database
- Used 3s `waitForTimeout` after navigation to allow async KPI fetch to complete before asserting

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

The E2E test runner (`bin/test-e2e.sh`) mounts the main repo root (`/home/user/gestion_votes_php`) into Docker, not the worktree. Spec was temporarily copied to the main repo test directory for the test run to confirm it passes (exit 0), then removed. The spec file lives canonically in the worktree commit.

## E2E Test Run Output

```
Running 1 test using 1 worker
  1 passed (7.8s)
```

## Width Fix Detail

Before:
```css
.admin-content {
  max-width: var(--content-narrow, 720px);
  margin-inline: auto;
  padding: var(--space-6);
```

After:
```css
.admin-content {
  width: 100%;
  padding: var(--space-6);
```

## Token Gate Result

Zero hex/oklch/rgba literals in admin.css — all color values use design-system `var(--*)` tokens. Already clean from v4.3 rebuild.

## Next Phase Readiness

- Admin page passes all 3 MVP gates (width, design tokens, function)
- Remaining wave 3 pages can follow same pattern

---
*Phase: 12-page-by-page-mvp-sweep*
*Completed: 2026-04-09*

## Self-Check: PASSED

- [x] `public/assets/css/admin.css` exists and contains `width: 100%`
- [x] `tests/e2e/specs/critical-path-admin-page.spec.js` exists with 110 lines
- [x] Commit `697bf499` exists (width fix)
- [x] Commit `62dcfa3b` exists (spec)
- [x] E2E test ran and exited 0 (1 passed, 7.8s)
