# Phase 18: SSE Infrastructure - Research

**Researched:** 2026-03-16
**Domain:** Redis fan-out, nginx SSE configuration, PHP-FPM long-lived connections
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

#### Multi-consumer strategy
- Fan-out to per-consumer Redis lists at publish time
- Consumer ID = PHP session ID (session_id())
- Consumer registers in Redis SET: `sse:consumers:{meetingId}`
- Consumer dequeues from personal queue: `sse:queue:{meetingId}:{sessionId}`
- Publisher reads consumer set via SMEMBERS, pipelines RPUSH to all

#### Orphaned cleanup
- Both TTL (60s on queue keys, 120s on consumer sets) + self-cleanup (SREM on exit)

#### File fallback
- Redis-only multi-consumer; file fallback stays single-consumer

#### Nginx
- Exempt SSE from API rate limit
- Dedicated location block with fastcgi_buffering off

#### PHP-FPM
- No nginx per-IP cap; document max_children SSE implications

### Claude's Discretion
- Redis key naming format for consumer queues
- Pipeline consumer registration + first poll
- Max consumer count safety valve per meeting

### Deferred Ideas (OUT OF SCOPE)
None

</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| SSE-01 | events.php supports multiple concurrent consumers without event loss | Fan-out pattern eliminates destructive LRANGE+DEL race condition |
| SSE-02 | nginx has dedicated location block for events.php with fastcgi_buffering off | New location block before generic .php handler |
| SSE-03 | PHP-FPM config documents sizing for long-lived SSE connections | Inline comments in php-fpm.conf with calculation |
| SSE-04 | Operator vote count updates in real-time via SSE after each ballot | Already wired (EventBroadcaster::voteCast → operator-realtime.js); multi-consumer fix ensures delivery |

</phase_requirements>

---

## Summary

Phase 18 fixes a critical concurrency bug in the SSE pipeline and adds proper server configuration. The root problem is in `events.php:159-162` where `pollEvents()` atomically reads ALL events from a per-meeting Redis list and deletes it — the first consumer to poll gets everything, subsequent consumers get nothing. This is a textbook single-consumer anti-pattern applied to a multi-consumer scenario (operator + voters + projection screen all connect to the same meeting).

The fix replaces the single shared list with per-consumer lists. `EventBroadcaster::publishToSse()` is modified to look up all registered consumers for a meeting (via a Redis SET) and fan out the event to each consumer's personal queue. `events.php` registers itself on connect and dequeues from its own personal queue — since only one process reads from each queue, the destructive LRANGE+DEL pattern becomes safe.

The nginx change adds a `location = /api/v1/events.php` block placed before the generic `.php` handler (nginx processes exact-match locations before regex). This block disables buffering explicitly, sets a 35s read timeout (matching the 30s PHP loop + 5s margin), and exempts SSE from the API rate limiter.

**Primary recommendation:** Single plan with 4 tasks: (1) modify EventBroadcaster for fan-out publish, (2) modify events.php for consumer registration + personal queue, (3) add nginx SSE location block, (4) add PHP-FPM documentation comments.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| phpredis | existing | Redis SET/LIST operations for fan-out | Already in use; no new extension needed |
| nginx | existing | Dedicated SSE location block | Standard pattern, already used for health.php and auth_login.php |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| EventBroadcaster.php | existing | Event publish with fan-out | Modified to iterate consumer sets |
| events.php | existing | SSE consumer endpoint | Modified for registration + personal queue |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Per-consumer lists (fan-out) | Redis Streams (XADD/XREAD) | Streams are purpose-built for multi-consumer, but add complexity; phpredis stream support varies; fan-out is simpler for <50 consumers |
| Per-consumer lists (fan-out) | Redis Pub/Sub (SUBSCRIBE) | PHP-FPM can't do true async subscribe; would need Swoole or ReactPHP; too large a change |
| Per-consumer lists (fan-out) | Shared list + per-consumer cursor | LTRIM invalidates cursor positions; complex to keep cursors in sync with trimmed list |
| Session ID as consumer ID | UUID per connection | Would require client-side changes (query param); session ID is already available after auth |

**Installation:** No new dependencies. All required Redis commands (SADD, SMEMBERS, SREM, RPUSH, LRANGE, DEL, EXPIRE) are available in phpredis.

---

## Architecture Patterns

### Recommended Project Structure
No new files needed. All changes are to existing files:
```
app/WebSocket/EventBroadcaster.php  — publishToSse() fan-out logic
public/api/v1/events.php           — consumer registration + personal queue polling
deploy/nginx.conf                   — SSE location block
deploy/php-fpm.conf                 — SSE sizing documentation
```

### Pattern 1: Consumer Registration (events.php)
**What:** On SSE connect, register the consumer in a per-meeting Redis SET and poll from a personal queue.
**When to use:** Every SSE connection lifetime.
**Example:**
```php
// Register consumer
$consumerId = session_id();
$consumerSetKey = "sse:consumers:{$meetingId}";
$queueKey = "sse:queue:{$meetingId}:{$consumerId}";
$redis->sAdd($consumerSetKey, $consumerId);
$redis->expire($consumerSetKey, 120);

// Poll personal queue (inside event loop)
function pollEvents(string $meetingId, string $consumerId): array {
    $redis = RedisProvider::connection();
    $queueKey = "sse:queue:{$meetingId}:{$consumerId}";

    // Refresh TTLs
    $redis->expire($queueKey, 60);
    $redis->expire("sse:consumers:{$meetingId}", 120);

    // Atomic dequeue from personal queue (safe — single consumer)
    $pipe = $redis->multi(Redis::PIPELINE);
    $pipe->lRange($queueKey, 0, -1);
    $pipe->del($queueKey);
    $results = $pipe->exec();

    $raw = $results[0] ?? [];
    // ... parse events
}

// On exit: unregister
$redis->sRem($consumerSetKey, $consumerId);
```

### Pattern 2: Fan-out Publish (EventBroadcaster)
**What:** Publish to all registered consumers for a meeting via pipelined RPUSH.
**When to use:** Every event broadcast to a meeting.
**Example:**
```php
private static function publishToSse(array $event): void {
    $meetingId = $event['meeting_id'] ?? null;
    if ($meetingId === null) return;

    if (!self::useRedis()) {
        self::publishToSseFile($meetingId, $event);
        return;
    }

    $redis = RedisProvider::connection();
    $consumerSetKey = "sse:consumers:{$meetingId}";
    $consumers = $redis->sMembers($consumerSetKey);

    if (empty($consumers)) {
        return; // No active consumers for this meeting
    }

    $encoded = json_encode($event);
    $pipe = $redis->multi(Redis::PIPELINE);
    foreach ($consumers as $consumerId) {
        $queueKey = "sse:queue:{$meetingId}:{$consumerId}";
        $pipe->rPush($queueKey, $encoded);
        $pipe->expire($queueKey, 60);
        $pipe->lTrim($queueKey, -100, -1);
    }
    $pipe->exec();
}
```

### Pattern 3: Nginx SSE Location Block
**What:** Exact-match location for events.php before the generic `.php` regex handler.
**When to use:** Every SSE connection request.
**Example:**
```nginx
# ── SSE endpoint — long-lived, no rate limiting, no buffering ──────────
location = /api/v1/events.php {
    try_files $uri =404;
    fastcgi_pass 127.0.0.1:9000;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;

    # SSE requires unbuffered streaming
    fastcgi_buffering off;
    fastcgi_read_timeout 35s;
    fastcgi_send_timeout 35s;

    # No rate limiting — SSE auto-reconnects every 30s; rate limiting would
    # cause 503 during reconnection storms after network blips
}
```

### Anti-Patterns to Avoid
- **Single shared list with destructive dequeue:** The current LRANGE+DEL on a shared list loses events for concurrent consumers. This is the bug being fixed.
- **Redis KEYS command:** Do not use `$redis->keys("sse:queue:*")` for consumer discovery — KEYS is O(n) and blocks Redis. Use SMEMBERS on a SET instead.
- **Blocking Redis SUBSCRIBE in PHP-FPM:** PHP-FPM workers can't do async subscribe. The polling approach (sleep 1s) is the pragmatic solution for PHP.
- **Rate limiting SSE endpoint:** SSE connections are self-limiting (one per tab, 30s duration). Rate limiting causes spurious 503s during reconnect storms.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Consumer tracking | Custom DB table | Redis SET with TTL | In-memory, fast, auto-expires |
| Fan-out publish | Custom queue dispatcher | Redis PIPELINE with RPUSH loop | Atomic, <1ms for 20 consumers |
| Event ordering | Sequence numbers | Redis LIST (RPUSH appends in order) | FIFO guaranteed by Redis list semantics |
| Orphan cleanup | Cron job | TTL on Redis keys | Self-cleaning, zero maintenance |

---

## Common Pitfalls

### Pitfall 1: Session not started in events.php
**What goes wrong:** `session_id()` returns empty string because `session_start()` was not called.
**Why it happens:** events.php calls `SessionHelper::start()` only when auth is enabled. If auth is disabled (dev mode), session may not be started.
**How to avoid:** Call `session_id()` after the auth block. When auth is disabled, generate a fallback consumer ID from `$_SERVER['REMOTE_ADDR'] . ':' . getmypid()`.
**Warning signs:** All consumers share the same empty consumer ID → back to single-consumer problem.

### Pitfall 2: SMEMBERS returns stale consumer IDs
**What goes wrong:** A consumer disconnected without cleanup (killed process, network drop). Its session ID remains in the SET. Publisher fans out to a dead consumer's queue, wasting memory.
**Why it happens:** The SET TTL (120s) is refreshed by any active consumer polling, not per-member. A stale member can persist as long as any consumer for that meeting is active.
**How to avoid:** The per-queue TTL (60s) ensures stale queues auto-expire. The RPUSH to a non-existent key auto-creates it with default TTL (none), but we set EXPIRE 60s in the pipeline. Net effect: dead consumer queues auto-delete after 60s. The stale SET member causes harmless RPUSHes to a short-lived key.
**Warning signs:** Redis memory slowly growing — monitor with `redis-cli info memory`.

### Pitfall 3: nginx location ordering
**What goes wrong:** The generic `location ~ \.php$` regex handler catches events.php before the exact-match `location = /api/v1/events.php` block.
**Why it happens:** Wrong placement in nginx.conf OR misunderstanding of nginx location priority.
**How to avoid:** nginx always prefers exact-match (`=`) over regex (`~`), regardless of order in the config. But for clarity, place the SSE block near the other exact-match blocks (health.php, auth_login.php). Verify with `curl -I /api/v1/events.php` — should NOT return `429 Too Many Requests` under load.
**Warning signs:** SSE requests getting rate-limited despite dedicated block.

### Pitfall 4: Redis serializer conflict
**What goes wrong:** `SMEMBERS` returns JSON-encoded strings instead of plain session IDs, or `RPUSH` double-encodes the event payload.
**Why it happens:** RedisProvider sets `OPT_SERIALIZER` to `SERIALIZER_JSON` globally. The existing SSE code already toggles to `SERIALIZER_NONE` before raw operations (events.php:157, EventBroadcaster.php:161).
**How to avoid:** Wrap all new Redis operations in the same `setOption(SERIALIZER_NONE)` / `setOption(SERIALIZER_JSON)` sandwich pattern. Use `$redis->setOption()` before SADD/SMEMBERS/SREM too.
**Warning signs:** Consumer IDs in SET look like `"\"abc123\""` (double-quoted) instead of `abc123`.

### Pitfall 5: fastcgi_buffering off not enough
**What goes wrong:** Events still arrive in batches despite `fastcgi_buffering off`.
**Why it happens:** Other nginx buffering layers (proxy_buffering, gzip) can still buffer responses.
**How to avoid:** The `X-Accel-Buffering: no` header (already sent by events.php:68) disables proxy buffering. Gzip is not applied to `text/event-stream` content type (not in our `gzip_types` list). The combination of `fastcgi_buffering off` + `X-Accel-Buffering: no` is sufficient.
**Warning signs:** Events arrive in 8KB chunks instead of one at a time.

---

## Code Examples

### Consumer registration + deregistration lifecycle
```php
// events.php — after auth, before main loop
$consumerId = session_id();
if ($consumerId === '') {
    // Auth disabled fallback
    $consumerId = md5($_SERVER['REMOTE_ADDR'] . ':' . getmypid());
}
$consumerSetKey = "sse:consumers:{$meetingId}";

if (RedisProvider::isAvailable()) {
    try {
        $redis = RedisProvider::connection();
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
        $redis->sAdd($consumerSetKey, $consumerId);
        $redis->expire($consumerSetKey, 120);
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
    } catch (Throwable $e) {
        // Fall through — file fallback will be used
    }
}

// ... main event loop with pollEvents($meetingId, $consumerId) ...

// After loop exits (timeout or connection_aborted)
if (RedisProvider::isAvailable()) {
    try {
        $redis = RedisProvider::connection();
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
        $redis->sRem($consumerSetKey, $consumerId);
        // Also clean up personal queue
        $redis->del("sse:queue:{$meetingId}:{$consumerId}");
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
    } catch (Throwable $e) {
        // Ignore — TTL will clean up
    }
}
```

### Fan-out publish with pipeline
```php
// EventBroadcaster::publishToSse() — replacement
private static function publishToSse(array $event): void {
    $meetingId = $event['meeting_id'] ?? null;
    if ($meetingId === null) {
        return;
    }

    if (!self::useRedis()) {
        self::publishToSseFile($meetingId, $event);
        return;
    }

    try {
        $redis = RedisProvider::connection();
        $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);

        $consumerSetKey = 'sse:consumers:' . $meetingId;
        $consumers = $redis->sMembers($consumerSetKey);

        if (!empty($consumers)) {
            $encoded = json_encode($event);
            $pipe = $redis->multi(\Redis::PIPELINE);
            foreach ($consumers as $cid) {
                $queueKey = 'sse:queue:' . $meetingId . ':' . $cid;
                $pipe->rPush($queueKey, $encoded);
                $pipe->expire($queueKey, 60);
                $pipe->lTrim($queueKey, -100, -1);
            }
            $pipe->exec();
        }

        $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_JSON);
    } catch (Throwable $e) {
        // Fall through to file-based SSE queue
        self::publishToSseFile($meetingId, $event);
    }
}
```

### Nginx SSE location block
```nginx
# ── SSE endpoint — long-lived, no rate limiting, no buffering ──────────
# Must appear before generic \.php$ handler. Exact-match (=) takes
# priority over regex (~) regardless of order, but placed here for clarity.
location = /api/v1/events.php {
    try_files $uri =404;
    fastcgi_pass 127.0.0.1:9000;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;

    # SSE requires unbuffered streaming to push events immediately
    fastcgi_buffering off;

    # Match PHP loop duration (30s) + margin for connection setup
    fastcgi_read_timeout 35s;
    fastcgi_send_timeout 35s;

    # No rate limiting — SSE auto-reconnects every 30s via EventSource API;
    # rate limiting causes 503 during simultaneous reconnections after blips
}
```

---

## Validation Architecture

> nyquist_validation key absent from config.json — treated as enabled.

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit (backend), grep verification (infrastructure config) |
| Config file | `phpunit.xml` |
| Quick run command | `vendor/bin/phpunit --testsuite unit` |
| Full suite command | `vendor/bin/phpunit` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| SSE-01 | Fan-out to per-consumer Redis lists | grep | `grep -n "sMembers\|sse:consumers\|sse:queue" app/WebSocket/EventBroadcaster.php` | Yes |
| SSE-01 | Consumer registration in events.php | grep | `grep -n "sAdd\|sse:consumers" public/api/v1/events.php` | Yes |
| SSE-02 | Dedicated nginx location for events.php | grep | `grep -n "events.php\|fastcgi_buffering" deploy/nginx.conf` | Yes |
| SSE-03 | PHP-FPM SSE sizing documentation | grep | `grep -n "SSE\|sse\|long-lived" deploy/php-fpm.conf` | Yes |
| SSE-04 | Vote event → operator console flow | grep | `grep -n "vote.cast\|voteCast\|loadBallots" public/assets/js/pages/operator-realtime.js` | Yes (already wired) |

### Sampling Rate
- **Per task commit:** Verify changed files with grep for key patterns
- **Per wave merge:** Full grep verification of all SSE-01 through SSE-04 criteria
- **Phase gate:** `vendor/bin/phpunit --testsuite unit` must pass

### Wave 0 Gaps
None — existing test infrastructure covers all phase requirements. The PHPUnit suite validates backend code. Infrastructure config changes (nginx, PHP-FPM) are verified by grep. SSE-04 is already wired from prior phases; multi-consumer fix (SSE-01) ensures delivery.

---

## Sources

### Primary (HIGH confidence)
- `/home/user/gestion-votes/public/api/v1/events.php` — full 187 lines read
- `/home/user/gestion-votes/app/WebSocket/EventBroadcaster.php` — full 409 lines read
- `/home/user/gestion-votes/deploy/nginx.conf` — full 143 lines read
- `/home/user/gestion-votes/deploy/php-fpm.conf` — full 30 lines read
- `/home/user/gestion-votes/public/assets/js/core/event-stream.js` — full 154 lines read
- `/home/user/gestion-votes/.planning/REQUIREMENTS.md` — SSE-01 through SSE-04 text

### Secondary (MEDIUM confidence)
- `/home/user/gestion-votes/public/assets/js/pages/operator-realtime.js` — SSE consumer setup
- `/home/user/gestion-votes/public/assets/js/pages/vote.js` — voter SSE setup
- `/home/user/gestion-votes/public/assets/js/pages/public.js` — projection SSE setup

### Tertiary (LOW confidence)
- None

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all Redis commands verified available in phpredis
- Architecture: HIGH — fan-out pattern derived from reading existing code; no speculation
- Pitfalls: HIGH — serializer toggle pattern already used in codebase; nginx location priority is well-documented

**Research date:** 2026-03-16
**Valid until:** 2026-04-16 (stable codebase, no external dependencies)
