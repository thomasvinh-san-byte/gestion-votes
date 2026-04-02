---
phase: 79-sse-async-robustness
plan: 01
subsystem: ui
tags: [sse, javascript, event-stream, operator-realtime, public, error-handling, cleanup]

# Dependency graph
requires: []
provides:
  - SSE EventSource cleanup on pagehide in public.js (no orphaned connections)
  - .catch() error handlers on all .then() chains in operator-realtime.js handleSSEEvent
  - onFallback callback in event-stream.js triggered when MAX_RECONNECT_ATTEMPTS exceeded
  - Persistent French warning toast in operator-realtime.js when SSE falls back to polling
  - Fallback toast dismissed when SSE reconnects
affects: [operator-realtime, public, event-stream]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Store EventStream handle for pagehide cleanup
    - Attach .catch() to all async .then() chains in SSE event handlers
    - onFallback callback pattern for EventStream max-reconnect notification

key-files:
  created: []
  modified:
    - public/assets/js/pages/public.js
    - public/assets/js/pages/operator-realtime.js
    - public/assets/js/core/event-stream.js

key-decisions:
  - "AgToast.show() return value stored in _sseFallbackToastEl for programmatic dismiss on reconnect"
  - "onFallback fallback path uses setNotif() if AgToast not defined — defensive dual-path"

patterns-established:
  - "EventStream handle pattern: store return value of EventStream.connect() and register pagehide for close()"
  - "SSE async error pattern: all .then() chains in SSE handlers get .catch(fn(err){ setNotif('error',...) })"
  - "onFallback pattern: EventStream calls opts.onFallback() after close() when max reconnects exceeded"

requirements-completed: [FE-01, FE-03, FE-04]

# Metrics
duration: 2min
completed: 2026-04-02
---

# Phase 79 Plan 01: SSE & Async Robustness Summary

**EventSource cleanup on pagehide, .catch() on all SSE async chains, and persistent French fallback toast when SSE exceeds MAX_RECONNECT_ATTEMPTS**

## Performance

- **Duration:** 2 min
- **Started:** 2026-04-02T08:15:31Z
- **Completed:** 2026-04-02T08:17:13Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- FE-01: public.js stores EventStream handle in `_publicSseStream` and calls `.close()` on `pagehide` — no orphaned EventSource connections
- FE-03: All `.then()` chains in `handleSSEEvent` (vote.cast, motion.opened x2 inner chains, motion.closed/updated) now have `.catch()` calling `setNotif('error', ...)`
- FE-04: event-stream.js invokes `handlers.onFallback()` after closing on max reconnects; operator-realtime.js shows persistent AgToast warning "Connexion temps reel interrompue — passage en mode poll" and dismisses it on SSE reconnect

## Task Commits

Each task was committed atomically:

1. **Task 1: Fix EventSource cleanup in public.js (FE-01) and async error swallowing in operator-realtime.js (FE-03)** - `285ca76c` (fix)
2. **Task 2: SSE fallback notification via onFallback callback (FE-04)** - `ba27d2f0` (feat)

## Files Created/Modified
- `public/assets/js/pages/public.js` - Added `_publicSseStream` variable, stored EventStream handle, registered `pagehide` cleanup listener
- `public/assets/js/pages/operator-realtime.js` - Added `_sseFallbackToastEl` var, `.catch()` on all `.then()` chains in `handleSSEEvent`, `onFallback` handler in `connectSSE()` with dismiss-on-reconnect logic
- `public/assets/js/core/event-stream.js` - Added `@param {Function} [opts.onFallback]` JSDoc, invoke `handlers.onFallback()` after `close()` in `source.onerror`

## Decisions Made
- AgToast.show() returns the DOM element — stored in `_sseFallbackToastEl` to call `.dismiss()` on reconnect
- Dual-path fallback: `AgToast.show()` if available, else `setNotif()` — defensive for environments where AgToast may not be loaded

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- SSE robustness layer complete for both public.js and operator-realtime.js
- EventStream now fully observable: onConnect, onDisconnect, onFallback lifecycle covered

---
*Phase: 79-sse-async-robustness*
*Completed: 2026-04-02*
