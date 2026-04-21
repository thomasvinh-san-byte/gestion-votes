---
phase: 02-sidebar-navigation
plan: "02"
subsystem: testing
tags: [playwright, e2e, sidebar, voter, nav]

# Dependency graph
requires:
  - phase: 02-sidebar-navigation/02-01
    provides: Static 200px sidebar with Voter and Mon compte nav items in sidebar.html
provides:
  - Updated E2E voter sidebar test asserting /vote visible, Mon compte visible, admin Parametres hidden, sidebar width 200px
affects: [02-sidebar-navigation]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Use attribute selector [data-requires-role] to disambiguate two nav items with same href"
    - "Playwright hasText option to select by text content when multiple elements share same href"

key-files:
  created: []
  modified:
    - tests/e2e/specs/critical-path-votant.spec.js

key-decisions:
  - "Use a[href=\"/settings\"][data-requires-role=\"admin\"] in mustBeHidden to avoid hiding Mon compte entry which shares same href"
  - "Use sidebar.locator('a[href=\"/settings\"]', { hasText: 'Mon compte' }) for positive assertion of Mon compte visibility"

patterns-established:
  - "hasText option in Playwright locator to disambiguate elements with identical href but different text"

requirements-completed: [NAV-01, NAV-02, NAV-03]

# Metrics
duration: 15min
completed: 2026-04-21
---

# Phase 2 Plan 02: Sidebar Navigation E2E Test Update Summary

**E2E voter sidebar test updated with /vote visibility, Mon compte assertion, wizard hidden check, and 200px width assertion via boundingBox()**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-04-21T08:00:00Z
- **Completed:** 2026-04-21T08:15:00Z
- **Tasks:** 1 of 2 (Task 2 is checkpoint:human-verify — awaiting human verification)
- **Files modified:** 1

## Accomplishments
- Updated `mustBeVisible` array to include `/vote` (voter role included) and `/dashboard` (no role restriction)
- Updated `mustBeHidden` to use `[data-requires-role="admin"]` attribute selector for Parametres entry, distinguishing it from Mon compte (same href)
- Added `/wizard` to `mustBeHidden` (requires admin,operator, hidden for voter)
- Added separate `monCompte` assertion using `hasText: 'Mon compte'` to verify voter can see Mon compte link
- Added NAV-01 sidebar width assertion: `boundingBox().width === 200`

## Task Commits

1. **Task 1: Update E2E voter sidebar test with new nav item assertions and sidebar width check** - `425a6a25` (feat)

## Files Created/Modified
- `tests/e2e/specs/critical-path-votant.spec.js` - Added /vote, /dashboard to mustBeVisible; updated settings selector; added wizard to mustBeHidden; added Mon compte and width assertions

## Decisions Made
- Used `a[href="/settings"][data-requires-role="admin"]` in mustBeHidden instead of generic `a[href="/settings"]` — sidebar.html has two entries pointing to /settings (admin Parametres with data-requires-role="admin" and Mon compte with no role restriction); generic selector would incorrectly assert Mon compte is hidden
- Used `{ hasText: 'Mon compte' }` Playwright locator option for positive visibility assertion — cleanest way to target the specific entry without relying on DOM order

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

Playwright chromium headless shell fails to launch in this environment due to missing system libraries (libatk-1.0.so.0, libasound.so.2, etc.). This is a pre-existing infrastructure constraint unrelated to the test changes. Test syntax verified as valid via `node --check`. All acceptance criteria grep checks pass (Mon compte >= 1, href="/vote" >= 1, boundingBox >= 1).

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- Task 2 (checkpoint:human-verify) awaiting human visual verification of 200px sidebar
- After human approval, plan 02-02 is complete and phase 02 is done
- Phase 03 (Typography) can proceed after verification

---
*Phase: 02-sidebar-navigation*
*Completed: 2026-04-21 (partial — Task 2 checkpoint pending)*
