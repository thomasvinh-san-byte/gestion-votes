---
phase: 05-js-audit-et-wiring-repair
plan: "02"
subsystem: ui
tags: [javascript, htmx, playwright, e2e, sidebar, shell]

# Dependency graph
requires: []
provides:
  - "sidebar:loaded custom event on both success and failure fetch paths in shared.js"
  - "auth-ui.js injection hardened with try/catch in shell.js"
  - "waitForHtmxSettled() Playwright helper for HTMX settle detection"
affects:
  - "tests/e2e/specs"
  - "shell.js consumers"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Custom DOM events for async load signalling (sidebar:loaded)"
    - "try/catch guard on critical script injection at IIFE end"
    - "Playwright helper module pattern in tests/e2e/helpers/ subdirectory"

key-files:
  created:
    - "tests/e2e/helpers/waitForHtmxSettled.js"
  modified:
    - "public/assets/js/core/shared.js"
    - "public/assets/js/core/shell.js"

key-decisions:
  - "sidebar:loaded event carries detail.ok boolean to distinguish success from failure"
  - "auth-ui.js injection uses try/catch (not try/finally) — catch with console.warn is the right pattern since there is no cleanup to guarantee"
  - "waitForHtmxSettled uses page.waitForFunction with an inner Promise to combine event listener and safety timeout"

patterns-established:
  - "HTMX settle detection: resolve immediately when window.htmx is falsy — handles non-HTMX pages without branching in callers"

requirements-completed:
  - WIRE-03
  - WIRE-04

# Metrics
duration: 10min
completed: 2026-04-07
---

# Phase 05 Plan 02: JS Async Hardening Summary

**sidebar:loaded custom event added to both async paths in shared.js, auth-ui.js injection guarded with try/catch in shell.js, and waitForHtmxSettled() Playwright helper created for HTMX-vs-non-HTMX page detection**

## Performance

- **Duration:** 10 min
- **Started:** 2026-04-07T13:46:00Z
- **Completed:** 2026-04-07T13:56:26Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments

- shared.js `initSidebar()` now dispatches `sidebar:loaded` with `{ ok: true }` on success and `{ ok: false }` on failure, giving dependent scripts a reliable async settlement signal
- shell.js auth-ui.js injection wrapped in try/catch so a runtime error in the injection path is caught and logged instead of silently failing
- New `tests/e2e/helpers/waitForHtmxSettled.js` helper resolves immediately on non-HTMX pages and waits for `htmx:afterSettle` with a 200ms safety timeout on HTMX pages

## Task Commits

1. **Task 1: Harden sidebar async timing** - `b27a7874` (feat)
2. **Task 2: Create waitForHtmxSettled() Playwright helper** - `5a6ac06c` (feat)

## Files Created/Modified

- `public/assets/js/core/shared.js` — Added `sidebar:loaded` CustomEvent dispatch on success and failure paths of `initSidebar()`
- `public/assets/js/core/shell.js` — Wrapped auth-ui.js script element injection in try/catch with console.warn
- `tests/e2e/helpers/waitForHtmxSettled.js` — New CommonJS module exporting `waitForHtmxSettled(page, timeout)` helper

## Decisions Made

- `sidebar:loaded` carries `detail: { ok: boolean }` so listeners can distinguish success from failure without a separate error event
- Used try/catch (not try/finally) for auth-ui.js — there is nothing to clean up on failure, so catch + warn is the minimal correct pattern
- `waitForHtmxSettled` uses `page.waitForFunction` with an inner `Promise` constructor to combine the event listener and the 200ms safety timeout in a single browser-side evaluation

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- WIRE-03 (sidebar:loaded event) and WIRE-04 (auth-ui.js hardening) satisfied
- `waitForHtmxSettled` is available for E2E specs on vote.htmx.html and postsession.htmx.html
- No blockers for subsequent JS audit/wiring plans

---
*Phase: 05-js-audit-et-wiring-repair*
*Completed: 2026-04-07*
