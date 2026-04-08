---
phase: 11-backend-wiring-fixes
plan: 05
subsystem: api
tags: [dashboard, performance, repository, php]

# Dependency graph
requires:
  - phase: 02-optimisations-memoire-et-requetes
    provides: MeetingStatsRepository::getDashboardStats() — single SQL round-trip for all dashboard counts
provides:
  - DashboardController::index() wired to getDashboardStats() — v1.0 perf win realized
  - data.stats block exposed in dashboard API response
  - PHPUnit test suite proving getDashboardStats is called exactly once with correct args
affects: [frontend dashboard consumption, monitoring query-count per request]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Aggregate query pattern: replace N scalar COUNT queries with one getDashboardStats() call"
    - "Spy pattern: expects($this->never())->method('countOpenMotions') proves removed code path"

key-files:
  created: []
  modified:
    - app/Controller/DashboardController.php
    - tests/Unit/DashboardControllerTest.php

key-decisions:
  - "present_count sourced from getDashboardStats; present_weight still from dashboardSummary (not in aggregated query)"
  - "Full stats dict exposed as data.stats in response to unlock frontend consumption without future controller changes"
  - "proxy()->countActive() removed entirely — proxy_count available from getDashboardStats"
  - "countOpenMotions() removed from controller — open_motions available from getDashboardStats"

patterns-established:
  - "getDashboardStats replaces all scalar COUNT calls in DashboardController::index"

requirements-completed: [DEBT-01]

# Metrics
duration: 12min
completed: 2026-04-07
---

# Phase 11 Plan 05: Wire getDashboardStats in DashboardController Summary

**getDashboardStats() wired into DashboardController::index(), reducing dashboard DB round-trips from 3+ scalar COUNTs to 1 aggregated query; PHPUnit mock proves the call and value mapping.**

## Performance

- **Duration:** 12 min
- **Started:** 2026-04-07T00:00:00Z
- **Completed:** 2026-04-07T00:12:00Z
- **Tasks:** 1
- **Files modified:** 2

## Accomplishments

- Removed `proxy()->countActive()` and `statsRepo->countOpenMotions()` from `DashboardController::index()` — both replaced by the pre-existing `getDashboardStats()` call
- Added `$data['stats'] = $stats` to surface the full 12-key stats dict in the API response
- `present_count` now flows from `getDashboardStats`; `present_weight` kept from `dashboardSummary` (the only key not in the aggregated query)
- Updated 5 existing tests (replaced `countOpenMotions` stubs with `getDashboardStats` stubs)
- Added `testIndexCallsGetDashboardStatsAndMapsResponse` — uses `expects($this->once())` with arg assertions and 6 concrete value assertions
- Added `testIndexDoesNotCallCountOpenMotions` — uses `expects($this->never())` on the removed code path

## Query Count: Before vs After

| Phase | Calls per dashboard load (with meeting_id) |
|-------|---------------------------------------------|
| Before | `dashboardSummary` + `proxy()->countActive()` + `countOpenMotions()` = 3+ COUNT queries |
| After  | `getDashboardStats()` = 1 aggregated query + `dashboardSummary` (for `present_weight` only) |

Net reduction: 2 fewer DB round-trips per dashboard request.

## Task Commits

1. **Task 1: Wire getDashboardStats + test** - `45f877bf` (feat)

## Files Created/Modified

- `app/Controller/DashboardController.php` — wired getDashboardStats, removed 2 individual COUNT calls, added stats block to response
- `tests/Unit/DashboardControllerTest.php` — updated 5 existing tests, added 2 new tests (19 tests total, 56 assertions, all green)

## Decisions Made

- Kept `attRepo->dashboardSummary()` in place because `present_weight` is not returned by `getDashboardStats` (would require a schema addition to the aggregated query — out of scope)
- Exposed `data.stats` as a flat dict in the response rather than inlining keys, to allow frontend to access all 12 stats fields without further controller changes

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- DEBT-01 closed — getDashboardStats is now actually used in production code path
- The `data.stats` key is new in the dashboard API response — frontend can consume it without a breaking change
- `ProxyRepository` is still injected via `injectIndexRepos` helper but no longer called by index(); the helper parameter can be cleaned up in a future refactor

---
*Phase: 11-backend-wiring-fixes*
*Completed: 2026-04-07*
