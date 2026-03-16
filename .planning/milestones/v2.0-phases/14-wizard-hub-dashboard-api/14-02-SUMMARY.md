---
phase: 14-wizard-hub-dashboard-api
plan: 02
subsystem: api
tags: [javascript, dashboard, hub, api, kpi, wizard_status]

# Dependency graph
requires:
  - phase: 14-wizard-hub-dashboard-api
    provides: "Plan 01 wired toast and fixed wizard api() call"
provides:
  - "Hub loads real single-meeting data via /api/v1/wizard_status endpoint"
  - "Dashboard KPIs computed from actual /api/v1/dashboard response shape"
  - "Urgent action card shows/hides based on live meetings from real API data"
affects: [hub, dashboard, operator-console]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Normalize API field names at the top of mapping functions before processing"
    - "Unwrap API response envelope (data.data) in .then() callbacks before accessing fields"
    - "Derive KPI counts by filtering meetings array on status field"

key-files:
  created: []
  modified:
    - public/assets/js/pages/hub.js
    - public/assets/js/pages/dashboard.js

key-decisions:
  - "hub.js: Use /api/v1/wizard_status?meeting_id=X (not /api/v1/meetings.php?id=X) as single-meeting endpoint — the meetings index endpoint ignores ?id= query param"
  - "hub.js: Normalize wizard_status field names (members_count, motions_total, meeting_title, meeting_status) via Object.assign at the top of mapApiDataToSession rather than updating every downstream field reference"
  - "dashboard.js: Compute KPI values from meetings array by filtering on status — avoids depending on non-existent upstream_count/live_count fields"
  - "dashboard.js: kpiConvoc shows 0 since convocation data is not in /api/v1/dashboard response"

patterns-established:
  - "API field normalization pattern: normalize field names at entry point of mapping function, not at each usage site"
  - "Envelope unwrapping: always extract data.data before accessing domain fields in Utils.apiGet callbacks"

requirements-completed: [HUB-01, HUB-02, HUB-03, HUB-04, HUB-05, DASH-01, DASH-02]

# Metrics
duration: 5min
completed: 2026-03-13
---

# Phase 14 Plan 02: Hub and Dashboard API Fix Summary

**Hub now loads real meeting data from /api/v1/wizard_status with field normalization; dashboard KPIs computed from actual meetings array in /api/v1/dashboard response**

## Performance

- **Duration:** 5 min
- **Started:** 2026-03-13T14:00:00Z
- **Completed:** 2026-03-13T14:05:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Hub calls `/api/v1/wizard_status?meeting_id=X` instead of the broken `/api/v1/meetings.php?id=X` endpoint
- Hub `mapApiDataToSession` normalizes `wizard_status` response field names (`members_count`, `motions_total`, `meeting_title`, `meeting_status`) so all downstream rendering works without changes
- Dashboard unwraps `{ ok, data }` API response envelope before reading domain fields
- Dashboard KPI counts derived from `data.data.meetings` array filtered by status (`draft`/`planned` for upcoming, `live`/`paused` for in-progress, `ended` for PV)
- Urgent action card shows when a live meeting exists, hides otherwise

## Task Commits

Each task was committed atomically:

1. **Task 1: Fix hub.js to use wizard_status endpoint** - `fdc06a5` (fix)
2. **Task 2: Fix dashboard.js KPI mapping to actual API response shape** - `fa9489b` (fix)

## Files Created/Modified
- `public/assets/js/pages/hub.js` - API endpoint changed to wizard_status, normalization block added to mapApiDataToSession
- `public/assets/js/pages/dashboard.js` - Response envelope unwrapped, KPIs computed from meetings array, urgent card driven by live meetings

## Decisions Made
- Used `Object.assign({}, data)` normalization pattern at the top of `mapApiDataToSession` to handle wizard_status field name differences (members_count, motions_total, meeting_title, meeting_status) without touching the rest of the function
- Dashboard kpiConvoc set to 0 since convocation data is absent from `/api/v1/dashboard` response — avoids showing incorrect numbers
- Upcoming meetings filter includes `draft` and `planned` statuses plus future `scheduled_at` as fallback for meetings without explicit upcoming status

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Hub and dashboard now display real data when a valid session ID is in the URL or API is reachable
- Both pages degrade gracefully to demo data when API is unavailable
- No further API wiring issues in hub.js or dashboard.js

---
*Phase: 14-wizard-hub-dashboard-api*
*Completed: 2026-03-13*
