---
phase: 17-demo-data-removal
plan: 01
subsystem: ui
tags: [javascript, dashboard, error-handling, empty-state]

# Dependency graph
requires:
  - phase: 16-data-foundation
    provides: Real /api/v1/dashboard endpoint with meetings data
provides:
  - Dashboard JS with zero demo fallback — real API data, error state, or empty state only
affects: [18-sse-infra, 19-operator, 20-live-vote]

# Tech tracking
tech-stack:
  added: []
  patterns: [hub-error-pattern for dashboard error banners, retry-once (2s) before error state]

key-files:
  created: []
  modified:
    - public/assets/js/pages/dashboard.js

key-decisions:
  - "Use 'hub-error dashboard-error' CSS class for error banner so existing .hub-error CSS applies without duplication"
  - "Retry-once pattern: attempt 1 failure waits 2s then tries again; attempt 2 failure shows error banner"
  - "Tasks panel shows Shared.emptyState() on every successful API load since no task data exists in the API"

patterns-established:
  - "showDashboardError(): toast + remove existing banner + create .hub-error.dashboard-error banner + Réessayer button calling loadDashboard()"
  - "loadDashboard() wraps logic in tryLoad(attempt) for retry-once behaviour"

requirements-completed: [HUB-03, HUB-04]

# Metrics
duration: pre-executed
completed: 2026-03-16
---

# Phase 17 Plan 01: Dashboard Demo Data Removal Summary

**Dashboard JS rewritten to serve only real API data: KPIs from live meetings, error banner with retry on failure, and Shared.emptyState() for the tasks panel**

## Performance

- **Duration:** pre-executed (committed before this execution run)
- **Started:** 2026-03-16
- **Completed:** 2026-03-16
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments
- Deleted `showFallback()` and all hardcoded demo KPI values (3/1/12/3), fake meeting rows, and fake task rows
- Added `showDashboardError()` mirroring hub.js error pattern with toast, duplicate-safe banner, and "Réessayer" retry button
- Wrapped `loadDashboard()` in `tryLoad(attempt)` for retry-once pattern (2s delay) before surfacing error
- Tasks panel shows `Shared.emptyState()` on every successful load (no task data in API)
- KPIs computed from real meetings array: upcoming count, live count, ended count, 0 for convocations (not in API)

## Task Commits

Each task was committed atomically:

1. **Task 1: Replace dashboard demo fallback with error/empty states** - `7f05a89` (feat)

**Plan metadata:** (this execution — docs commit follows)

## Files Created/Modified
- `public/assets/js/pages/dashboard.js` - Replaced showFallback() with showDashboardError() + retry-once pattern + tasks empty state

## Decisions Made
- Followed hub.js error pattern exactly per user decision recorded in project context
- `'hub-error dashboard-error'` class reuses existing CSS without new style rules
- Tasks panel always shows empty state on success (not tied to a tasks API that doesn't exist)

## Deviations from Plan

None - plan executed exactly as written. The file was already correctly implemented when this execution run began (committed as `7f05a89`).

## Issues Encountered

None. Verification confirmed:
- 0 occurrences of `showFallback` or `SEED_` in dashboard.js
- 4 occurrences of `showDashboardError` (definition + 3 call sites)
- `Réessayer` button present (encoded as `\u00e9essayer` Unicode escape)
- `node -c` passes — valid JavaScript syntax

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Dashboard is ready: shows real data, handles API errors gracefully, shows empty states for missing data
- Plan 17-02 (audit.js demo removal) can proceed immediately
- Phase 18 (SSE infra) depends on this cleanup being complete — dashboard is ready

---
*Phase: 17-demo-data-removal*
*Completed: 2026-03-16*
