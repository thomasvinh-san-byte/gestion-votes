---
phase: 18-sse-infrastructure
plan: 01
subsystem: infra
tags: [sse, redis, nginx, php-fpm, real-time, fan-out]

# Dependency graph
requires:
  - phase: 17-demo-data-removal
    provides: Clean demo-free codebase with auth/session infrastructure
provides:
  - Per-consumer SSE fan-out via Redis SET + per-consumer RPUSH queues
  - Nginx dedicated SSE location block with fastcgi_buffering off
  - PHP-FPM SSE worker sizing documentation
affects: [19-operator-console, 20-live-vote, operator-realtime.js, events.php consumers]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Per-consumer Redis fan-out: publisher reads sse:consumers:{meetingId} SET, RPUSHes to sse:queue:{meetingId}:{consumerId} per consumer"
    - "Consumer lifecycle: sAdd on connect + sRem+del on graceful exit + TTL safety net for ungraceful exits"
    - "Nginx exact-match location (location = /path) overrides regex location for SSE-specific config"

key-files:
  created: []
  modified:
    - app/WebSocket/EventBroadcaster.php
    - public/api/v1/events.php
    - deploy/nginx.conf
    - deploy/php-fpm.conf

key-decisions:
  - "Per-consumer Redis lists chosen over Redis Pub/Sub — avoids blocking subscribe in PHP-FPM, works with existing pipeline pattern"
  - "Consumer ID = session_id() with md5(REMOTE_ADDR:PID) fallback when auth disabled"
  - "SSE location block exempt from rate limiting — EventSource API auto-reconnects every 30s; rate limiting would cause 503 storms on reconnect blips"
  - "File-based fallback stays single-consumer — multi-consumer only with Redis (acceptable for demo/Render without concurrent SSE consumers)"
  - "max_children=10 documented: supports 1 operator + 1 projection + 5 voters + 3 API slots"

patterns-established:
  - "SSE consumer registration: sAdd on connect, sRem on graceful exit, TTL=120s as safety net"
  - "Publisher fan-out: sMembers consumer SET, pipeline rPush+expire+lTrim to each personal queue"
  - "Nginx SSE block: location = for exact match, fastcgi_buffering off, re-declare security headers (nginx inheritance rule)"

requirements-completed: [SSE-01, SSE-02, SSE-03, SSE-04]

# Metrics
duration: 15min
completed: 2026-03-16
---

# Phase 18 Plan 01: SSE Infrastructure Summary

**Multi-consumer SSE fan-out via per-consumer Redis lists fixing event loss when operator, voters, and projection screens connect to the same meeting**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-03-16T15:08:00Z
- **Completed:** 2026-03-16T15:23:00Z
- **Tasks:** 4
- **Files modified:** 4

## Accomplishments

- Fixed multi-consumer SSE event loss: publisher now fans out to all consumers via per-consumer Redis queues instead of one shared list
- Added nginx dedicated SSE location block with `fastcgi_buffering off`, 35s timeouts, and no rate limiting
- Added PHP-FPM SSE sizing documentation with formula and production recommendation for >5 concurrent voters

## Task Commits

Each task was committed atomically:

1. **Task 1: Multi-consumer fan-out in EventBroadcaster** - `6d8accb` (feat)
2. **Task 2: Consumer registration + personal queue in events.php** - `cbd6777` (feat)
3. **Task 3: Nginx dedicated SSE location block** - `ae5d62e` (feat)
4. **Task 4: PHP-FPM SSE sizing documentation** - `255ff58` (chore)

## Files Created/Modified

- `app/WebSocket/EventBroadcaster.php` - publishToSse() now reads sse:consumers:{meetingId} SET and pipelines RPUSH to each consumer's personal queue
- `public/api/v1/events.php` - Consumer registration (sAdd on connect), personal queue dequeue, cleanup on exit (sRem+del)
- `deploy/nginx.conf` - location = /api/v1/events.php with fastcgi_buffering off, 35s timeouts, security headers, no rate limiting
- `deploy/php-fpm.conf` - Inline SSE sizing comments documenting worker calculation and production formula

## Decisions Made

- Per-consumer Redis lists over Redis Pub/Sub: avoids blocking subscribe in PHP-FPM workers, works with existing pipeline pattern
- Consumer ID derived from session_id() (stable across the 30s loop), fallback to md5(REMOTE_ADDR:PID) when auth disabled
- SSE endpoint exempt from rate limiting: EventSource auto-reconnects every 30s; rate limiting would cause 503 storms during simultaneous reconnects after network blips
- File-based fallback stays single-consumer: Redis is expected in production; Render/demo deployments without concurrent consumers are unaffected

## Deviations from Plan

None - Tasks 1 and 2 were already committed (from a previous session starting this phase). Tasks 3 and 4 were implemented as planned with no deviations.

## Issues Encountered

Tasks 1 and 2 were already implemented and committed (`6d8accb`, `cbd6777`) before this execution session. The verification checks confirmed correctness and the remaining Tasks 3 and 4 were applied as specified.

Note: The plan verification criterion `grep -c "sMembers\|sse:consumers" app/WebSocket/EventBroadcaster.php ≥2` returns 1 because both patterns appear on the same line (line 164: `$redis->sMembers("sse:consumers:{$meetingId}")`). The fan-out logic is correct and complete — this is a counting artifact, not a missing implementation.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- SSE pipeline is now safe for concurrent consumers (operator + voters + projection screen all receive all events)
- Nginx and PHP-FPM configured for long-lived SSE connections
- Ready for Phase 19 (operator console) which consumes SSE events from operator-realtime.js

## Self-Check: PASSED

All files verified present. All task commits verified in git log:
- `6d8accb` - Task 1: multi-consumer fan-out in EventBroadcaster
- `cbd6777` - Task 2: consumer registration + personal queue in events.php
- `ae5d62e` - Task 3: nginx SSE location block
- `255ff58` - Task 4: php-fpm SSE sizing docs
- `35783db` - Metadata commit (SUMMARY.md, STATE.md, ROADMAP.md, REQUIREMENTS.md)

---
*Phase: 18-sse-infrastructure*
*Completed: 2026-03-16*
