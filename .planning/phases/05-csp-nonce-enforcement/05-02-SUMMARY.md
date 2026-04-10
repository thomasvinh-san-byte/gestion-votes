---
phase: 05-csp-nonce-enforcement
plan: 02
subsystem: security
tags: [csp, nonce, strict-dynamic, report-only, security-headers, nginx, playwright]

# Dependency graph
requires:
  - phase: 05-csp-nonce-enforcement
    plan: 01
    provides: "SecurityProvider::nonce() + PageController nonce injection + %%CSP_NONCE%% placeholders"
provides:
  - "Content-Security-Policy-Report-Only header with nonce + strict-dynamic"
  - "buildReportOnlyCsp() testable static method for CSP string construction"
  - "Nginx CSP deduplication — PHP pages get CSP from PHP only"
  - "Playwright csp-enforcement.spec.js validating zero violations across 21 pages"
affects: [csp-enforcement-flip, security-headers]

# Tech tracking
tech-stack:
  added: []
  patterns: ["Report-only CSP alongside enforcing CSP for safe rollout", "Nginx CSP removed at server level, declared per-location for non-PHP responses"]

key-files:
  created:
    - tests/e2e/specs/csp-enforcement.spec.js
  modified:
    - app/Core/Providers/SecurityProvider.php
    - deploy/nginx.conf
    - deploy/nginx.conf.template
    - tests/Unit/SecurityProviderTest.php

key-decisions:
  - "Report-only CSP emitted alongside existing enforcing CSP (dual-header strategy for safe rollout)"
  - "buildReportOnlyCsp() extracted as public static method for testability without header() side effects"
  - "Nginx server-level CSP removed; CSP declared per-location for non-PHP responses (static assets, SSE, login, fallback)"
  - "script-src in report-only uses nonce + strict-dynamic only (no 'self' — strict-dynamic ignores it)"

patterns-established:
  - "Dual CSP headers: enforcing (script-src 'self') + report-only (nonce + strict-dynamic) for safe migration"
  - "Nginx CSP per-location pattern: PHP responses get CSP from SecurityProvider, non-PHP from nginx location blocks"

requirements-completed: [CSP-03, CSP-04]

# Metrics
duration: 3min
completed: 2026-04-10
---

# Phase 5 Plan 02: CSP Header Enforcement Summary

**Report-only CSP with nonce + strict-dynamic alongside existing enforcing header, nginx CSP deduplication, and Playwright zero-violation spec across 21 pages**

## Performance

- **Duration:** 3 min
- **Started:** 2026-04-10T09:08:28Z
- **Completed:** 2026-04-10T09:11:50Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments
- SecurityProvider emits Content-Security-Policy-Report-Only with nonce + strict-dynamic alongside existing enforcing CSP
- buildReportOnlyCsp() extracted as testable static method (6 new unit tests, 10 total passing)
- Nginx server-level CSP removed for PHP-served pages; CSP declared per-location for non-PHP responses
- Playwright csp-enforcement.spec.js covers 19 authenticated + 2 public pages with console listener + header assertions

## Task Commits

Each task was committed atomically:

1. **Task 1: CSP header upgrade + nginx deduplication + unit tests** - `ababb4ac` (feat)
2. **Task 2: Playwright CSP violation spec** - `b4900ca8` (feat)

## Files Created/Modified
- `app/Core/Providers/SecurityProvider.php` - Added buildReportOnlyCsp() and report-only header emission
- `deploy/nginx.conf` - Removed server-level CSP, added per-location CSP for login/fallback
- `deploy/nginx.conf.template` - Same changes as nginx.conf
- `tests/Unit/SecurityProviderTest.php` - 6 new tests for CSP header construction
- `tests/e2e/specs/csp-enforcement.spec.js` - 4 Playwright tests for CSP validation

## Decisions Made
- Report-only CSP emitted alongside existing enforcing CSP (dual-header strategy for safe rollout)
- buildReportOnlyCsp() extracted as public static method for testability without header() side effects
- Nginx server-level CSP removed; CSP declared per-location for non-PHP responses (static assets, SSE, login, fallback)
- script-src in report-only uses nonce + strict-dynamic only (no 'self' — strict-dynamic ignores it)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- CSP nonce infrastructure complete (Plan 01) with report-only validation (Plan 02)
- Report-only mode allows monitoring for violations before flipping to enforcement
- Phase 06 (Controller Refactoring) can proceed independently
- Future enforcement flip: change Content-Security-Policy-Report-Only to Content-Security-Policy and remove the old script-src 'self' enforcing header

---
*Phase: 05-csp-nonce-enforcement*
*Completed: 2026-04-10*
