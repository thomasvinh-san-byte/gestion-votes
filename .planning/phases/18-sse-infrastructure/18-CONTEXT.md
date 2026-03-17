# Phase 18: SSE Infrastructure - Context

**Gathered:** 2026-03-16
**Status:** Ready for planning

<domain>
## Phase Boundary

Make the SSE pipeline safe for concurrent consumers (operator + voters + projection screen all connected to the same meeting) and configure nginx/PHP-FPM properly for long-lived SSE connections. The current `events.php` uses a destructive LRANGE+DEL pattern that causes event loss when multiple consumers poll the same meeting queue.

**Requirements:** SSE-01, SSE-02, SSE-03, SSE-04

**Success Criteria:**
1. Operator console and voter view connected to the same meeting's SSE stream both receive all events — no event is silently dropped for one consumer
2. Nginx has a dedicated location block for events.php with `fastcgi_buffering off`
3. PHP-FPM pool sizing for SSE is documented with a concrete `max_children` recommendation
4. After a voter casts a ballot, the operator console tally updates within 3 seconds via SSE

</domain>

<decisions>
## Implementation Decisions

### Multi-consumer strategy
- **Fan-out to per-consumer Redis lists at publish time** — when an event is published, `EventBroadcaster::publishToSse()` iterates over registered consumers for that meeting and RPUSHes to each consumer's personal queue
- Consumer ID = PHP session ID (`session_id()`) — already available in events.php after auth, no client changes needed
- Each consumer registers itself in a Redis SET on connect: `SADD sse:consumers:{meetingId} {sessionId}`
- Each consumer dequeues from its own list: `sse:queue:{meetingId}:{sessionId}` — LRANGE+DEL is safe because only one consumer reads from its own list
- Publisher reads consumer set via SMEMBERS, pipelines RPUSH to all consumer lists

### Orphaned consumer cleanup
- **Both TTL + self-cleanup**: Consumer queue keys get 60s TTL (refreshed on each poll). Consumer set members get cleaned up by events.php on graceful exit (SREM). As a safety net, the consumer set key itself gets 120s TTL. Stale consumer IDs in the set are harmless — they just cause RPUSH to an expired key, which auto-creates a short-lived key that expires via TTL.

### File-based fallback
- **Redis-only multi-consumer** — the file fallback stays single-consumer (existing `dequeueSseFile` behavior). Multi-consumer only works with Redis. This is acceptable because Redis is expected in production, and the file fallback is for demo/Render deployments where concurrent SSE consumers are unlikely.

### Nginx SSE location block
- **Exempt from API rate limit** — SSE clients reconnect every 30s automatically (built into EventSource API). With `rate=10r/s burst=20`, reconnects are fine under normal load, but a network blip causing 30+ voters to reconnect simultaneously would trigger 503s. SSE connections are self-limiting (one per tab, 30s duration) so rate limiting adds risk without meaningful protection.
- Add dedicated `location = /api/v1/events.php` block with: `fastcgi_buffering off`, `fastcgi_read_timeout 35s`, no `limit_req`, security headers preserved

### PHP-FPM pool sizing
- **No nginx per-IP cap** — rely on PHP-FPM `max_children` as the natural concurrency limit. A per-IP cap (e.g. 5) would break NAT scenarios (many voters behind the same IP at an assembly venue). The `max_children=10` default is the true bottleneck and should be documented.
- Add inline comments to `php-fpm.conf` documenting the SSE sizing calculation: each SSE client holds 1 worker for 30s → `max_children` must accommodate SSE connections + regular API requests
- Recommend: production deployments with >5 concurrent voters should increase `max_children` to 20-30

### Claude's Discretion
- Exact Redis key naming format for consumer queues
- Whether to pipeline consumer registration + first poll in events.php
- Whether to add a max consumer count safety valve per meeting

</decisions>

<specifics>
## Specific Ideas

- The current LRANGE+DEL in events.php (lines 159-162) is the root cause of multi-consumer event loss — first consumer to poll gets all events, second gets nothing
- `EventBroadcaster::publishToSse()` (line 151) currently pushes to a single per-meeting list — change to fan-out to all registered consumers
- events.php already has `X-Accel-Buffering: no` header (line 68) as a workaround, but explicit `fastcgi_buffering off` in nginx is more reliable
- Consumer registration/deregistration happens inside events.php — no client-side changes needed (event-stream.js stays unchanged)

</specifics>

<code_context>
## Existing Code Insights

### Files to Modify
- `app/WebSocket/EventBroadcaster.php:151-175` — `publishToSse()` — change from single list to per-consumer fan-out
- `public/api/v1/events.php:150-186` — `pollEvents()` — consumer registration + personal queue dequeue
- `deploy/nginx.conf` — add dedicated SSE location block before generic `.php` handler
- `deploy/php-fpm.conf` — add SSE sizing documentation comments

### Established Patterns
- Redis pipeline for atomic operations (events.php:159-162)
- Per-meeting key namespacing: `sse:events:{meetingId}` (EventBroadcaster.php:160)
- File-based fallback with flock (EventBroadcaster.php:180-207)
- Dedicated nginx location blocks for special endpoints (health.php at line 82, auth_login.php at line 93)

### Integration Points
- Operator SSE: `operator-realtime.js:28-46` — connects on meeting change
- Voter SSE: `vote.js:357` — connects on meeting selection
- Projection SSE: `public.js:344` — connects on page load
- Event publish: `EventBroadcaster::toMeeting()` → `queue()` → `publishToSse()`

</code_context>

<deferred>
## Deferred Ideas

None — all decisions made during context gathering.

</deferred>

---

*Phase: 18-sse-infrastructure*
*Context gathered: 2026-03-16*
