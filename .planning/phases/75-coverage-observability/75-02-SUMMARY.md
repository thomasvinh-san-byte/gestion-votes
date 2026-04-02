---
phase: 75-coverage-observability
plan: 02
subsystem: ui
tags: [javascript, admin, error-handling, observability]

# Dependency graph
requires: []
provides:
  - "Visible KPI error state in admin.js: unfilled cards show em dash in red with tooltip on catch"
  - "Toast notification via setNotif('error', ...) when loadAdminKpis() throws"
affects: [admin-page, kpi-cards]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Partial-failure KPI error handling: only overwrite cards still showing default placeholder '-'"

key-files:
  created: []
  modified:
    - public/assets/js/pages/admin.js

key-decisions:
  - "Only overwrite KPI cards still showing '-' — cards that loaded keep their values (partial failure case)"
  - "Use CSS variable --color-error with #DC2626 fallback — matches existing error color in admin"
  - "Surgical edit: only catch block changed, no other modifications to admin.js"

patterns-established:
  - "Partial-failure pattern: iterate over element IDs, check placeholder text before overwriting"

requirements-completed:
  - DEBT-02

# Metrics
duration: 5min
completed: 2026-04-01
---

# Phase 75 Plan 02: Coverage Observability — Admin KPI Error Handling Summary

**Silent KPI failure replaced with visible em-dash error state in red plus a French toast notification when loadAdminKpis() throws**

## Performance

- **Duration:** 5 min
- **Started:** 2026-04-01T00:00:00Z
- **Completed:** 2026-04-01T00:05:00Z
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments
- KPI cards still showing '-' after a catch now display '—' in red (#DC2626 via CSS variable)
- Each unfilled card gets a `title` tooltip with 'Erreur chargement indicateurs'
- `setNotif('error', 'Erreur lors du chargement des indicateurs')` toast shown immediately
- Cards that loaded successfully before the error keep their values (partial failure preserved)

## Task Commits

1. **Task 1: Replace silent KPI catch with visible error fallback** - `82e463be` (fix)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `public/assets/js/pages/admin.js` - Replaced silent catch comment with visible error state loop and toast call

## Decisions Made
- Only cards still showing the default '-' placeholder are updated — successfully loaded cards are untouched
- Used `--color-error` CSS variable with `#DC2626` fallback to match existing design token usage

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- DEBT-02 resolved: admin KPI silent failure is now observable to end users
- No blockers for subsequent phases

---
*Phase: 75-coverage-observability*
*Completed: 2026-04-01*
