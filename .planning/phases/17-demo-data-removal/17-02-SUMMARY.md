---
phase: 17-demo-data-removal
plan: 02
subsystem: ui
tags: [audit, javascript, api-integration, error-handling]

# Dependency graph
requires: []
provides:
  - audit.js with zero demo data, real API integration to /api/v1/audit_log.php
  - showAuditError() function with toast, error banner, and retry button
  - Field mapping from API response shape (action_label, actor, created_at) to rendering shape (event, user, timestamp)
  - Guidance empty state when no meeting_id in URL
  - Retry-once pattern on API failure (2s delay, then showAuditError)
affects: [phase 22-final-sweep, any future audit page work]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Error state pattern: showAuditError() mirrors hub.js showHubError() — toast + banner + retry button"
    - "Defensive field mapping: accept both real API shape and legacy field names in items mapping"
    - "Retry-once: first failure → 2s delay → retry; second failure → show error (no infinite loop)"
    - "Guard against missing URL param: check meeting_id before calling API, show guidance if absent"

key-files:
  created: []
  modified:
    - public/assets/js/pages/audit.js

key-decisions:
  - "Use promise-based tryLoad(attempt) instead of async/await to maintain ES5-compatible style of surrounding code"
  - "Reset KPI values to dash on error so stale counts from a prior load are not displayed"
  - "Guidance state returns early without calling populateKPIs or applyFilters to keep state clean"

patterns-established:
  - "Error state: show toast + inline banner + retry button, never leave stale data visible"

requirements-completed: [CLN-03]

# Metrics
duration: 3min
completed: 2026-03-16
---

# Phase 17 Plan 02: Audit Demo Data Removal Summary

**Removed 252-line SEED_EVENTS constant from audit.js, fixed API URL to audit_log.php, added field mapping and showAuditError() with retry-once pattern**

## Performance

- **Duration:** 3 min
- **Started:** 2026-03-16T14:35:50Z
- **Completed:** 2026-03-16T14:38:40Z
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments
- Deleted entire SEED_EVENTS constant (252 lines, 25 hardcoded demo event objects)
- Fixed API endpoint from `/api/v1/audit.php` to `/api/v1/audit_log.php?meeting_id=UUID`
- Added defensive field mapping: `action_label`→`event`, `actor`→`user`, `created_at`→`timestamp` with fallbacks to legacy field names
- Added `showAuditError()` following the hub.js pattern: Shared.showToast + error banner in both table and timeline views + retry button
- Added guidance empty state when no `meeting_id` is in the URL (avoids broken API call)
- Added retry-once pattern: first failure waits 2s and retries; second failure calls showAuditError()
- KPI values reset to dash on error to prevent stale data display

## Task Commits

Each task was committed atomically:

1. **Task 1: Remove SEED_EVENTS and fix audit API integration** - `5f4d981` (feat)

**Plan metadata:** (docs commit pending)

## Files Created/Modified
- `public/assets/js/pages/audit.js` - Removed SEED_EVENTS, rewrote loadData(), added showAuditError()

## Decisions Made
- Used promise chaining (`tryLoad(attempt)`) instead of async/await to match the ES5-compatible coding style of the surrounding file
- KPI values are reset to dash (`—`) on error so users do not see stale counts from any previous successful load
- The guidance empty state (no meeting_id) returns early before calling `populateKPIs` or `applyFilters` to keep module state clean and consistent

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- audit.js is now fully demo-free and wired to the real API
- CLN-03 requirement fulfilled
- Phase 17 demo removal complete (both dashboard.js and audit.js cleaned)
- Ready to proceed to Phase 18 SSE infrastructure

---
*Phase: 17-demo-data-removal*
*Completed: 2026-03-16*
