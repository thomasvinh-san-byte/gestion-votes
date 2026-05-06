# Phase 1: Infrastructure Redis - Research

**Researched:** 2026-04-07
**Domain:** PHP Redis (phpredis), SSE, rate-limiting, health-check bootstrap
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
All implementation choices are at Claude's discretion — pure infrastructure phase. Use ROADMAP phase goal,
success criteria, and codebase conventions to guide decisions.

Key constraints from pre-research:
- Redis Pub/Sub pour SSE (XADD/XREAD si replay necessaire)
- Lua eval() pour rate-limiting atomique (pas MULTI/EXEC)
- Heartbeat Redis avec TTL pour remplacer PID-file
- Health check dans Application::boot()
- Verifier Redis 6.2+ dans docker-compose (requis pour LPOP count)

### Claude's Discretion
All implementation choices.

### Deferred Ideas (OUT OF SCOPE)
None — infrastructure phase.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| REDIS-01 | SSE EventBroadcaster utilise Redis exclusivement, fallback fichier supprime | See "REDIS-01" section below — fan-out pattern already exists, remove file code paths |
| REDIS-02 | Rate-limiting utilise Redis avec script Lua atomique (INCR+EXPIRE), flock supprime | See "REDIS-02" section — current PIPELINE is not atomic; Lua script fixes the race |
| REDIS-03 | Detection serveur SSE via heartbeat Redis avec TTL, PID-file supprime | See "REDIS-03" section — `isServerRunning()` reads /tmp/agvote-sse.pid, replace with Redis SET+EXPIRE |
| REDIS-04 | Health check Redis au boot de Application, erreur claire si Redis indisponible | See "REDIS-04" section — Application::boot() step 10 is silent; add hard fail |
</phase_requirements>

---

## Summary

The codebase already uses phpredis (extension compiled into the Docker image, version confirmed in Dockerfile).
Redis 7.4 is declared in docker-compose.yml — well above the 6.2 threshold flagged as a concern. The
phpredis singleton lives in `RedisProvider` and is already wired into Application::boot(). Every component
that needs to change (EventBroadcaster, RateLimiter, Application) is already structured for Redis — the
file fallback branches simply need to be deleted and the health check needs to become mandatory.

The four changes are surgical and independent. No new library or infrastructure is required. The
`EventBroadcaster` fan-out (RPUSH per consumer queue) is already the Redis-only design; only the file-based
branches need removal. The `RateLimiter` Redis branch uses a PIPELINE (not truly atomic) — replacing it with
a Lua `EVAL` fixes the race condition required by REDIS-02. `isServerRunning()` reads a PID file; it should
instead check a Redis key with TTL written by the SSE endpoint on connection. `Application::boot()` calls
`RedisProvider::configure()` silently — it must throw a `RuntimeException` with a clear French message if
Redis is unreachable.

**Primary recommendation:** Delete file-fallback code paths, replace the PIPELINE rate-limit with a Lua
script, add a Redis TTL heartbeat in events.php, and add a mandatory Redis health check in Application::boot().

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| phpredis (ext-redis) | compiled in Dockerfile (pecl latest) | Redis client for PHP | Already installed, used by RedisProvider |
| Redis server | 7.4-alpine3.21 (docker-compose.yml) | Broker for SSE, rate-limit, heartbeat | Already deployed; 7.4 >> 6.2 requirement |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| PHPUnit ^10.5 | existing composer.lock | Unit tests for new Redis-only paths | All test tasks |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| phpredis EVAL (Lua) | Predis library | Predis is pure-PHP and slower; phpredis already installed |
| Per-consumer RPUSH fan-out | Redis Pub/Sub SUBSCRIBE | SUBSCRIBE requires a dedicated blocking connection per client — impossible in PHP-FPM poll loop |
| Redis SET+EXPIRE heartbeat | Redis Streams (XADD) | TTL key is simpler for presence detection; streams add unnecessary complexity |

**Installation:** No new packages required. Everything already in composer.json and Dockerfile.

---

## Architecture Patterns

### Recommended Project Structure

No new directories needed. Changes are within existing files:

```
app/
├── Core/
│   ├── Application.php          # Add mandatory Redis health check at boot step 10
│   ├── Providers/
│   │   └── RedisProvider.php    # No change needed — already throws on failure
│   └── Security/
│       └── RateLimiter.php      # Replace PIPELINE branch with Lua EVAL; delete file fallback
└── SSE/
    └── EventBroadcaster.php     # Delete all file-backend methods; hard-require Redis
public/
└── api/v1/
    └── events.php               # Add heartbeat TTL key write; remove file fallback in pollEvents()
tests/Unit/
    └── RateLimiterTest.php      # Existing tests cover file path — add Redis Lua tests with mock
    └── EventBroadcasterTest.php # NEW — covers Redis-only paths and absence of /tmp writes
    └── ApplicationBootTest.php  # NEW — covers boot failure when Redis unavailable
```

### Pattern 1: Mandatory Redis Health Check at Boot

**What:** Call `RedisProvider::connection()` in `Application::boot()` and throw `RuntimeException` with a
clear French message on failure. No fallback, no silent skip.

**When to use:** Step 10 of `Application::boot()` and `Application::bootCli()`. Both must check.

**Example:**
```php
// In Application::boot() and bootCli(), replace:
RedisProvider::configure(self::$config['redis'] ?? []);

// With:
RedisProvider::configure(self::$config['redis'] ?? []);
try {
    RedisProvider::connection(); // throws RuntimeException on failure
} catch (\Throwable $e) {
    throw new \RuntimeException(
        'Redis est indisponible — l\'application ne peut pas demarrer. '
        . 'Verifiez que le service Redis est lance et accessible. '
        . 'Detail : ' . $e->getMessage(),
    );
}
```

**Why this pattern:** `RedisProvider::connection()` already throws `RuntimeException` on failure —
the only thing missing is calling it eagerly at boot instead of lazily per-request.

### Pattern 2: Atomic Lua Rate-Limit Script

**What:** Replace the PIPELINE INCR+TTL pattern in `RateLimiter::checkRedis()` with a single
`$redis->eval($script, [$key, $windowSeconds, $maxAttempts], 1)` call.

**When to use:** Any counter that must be incremented and compared atomically.

**Example:**
```php
// Source: Redis documentation on EVAL atomicity
private static function checkRedis(string $key, int $maxAttempts, int $windowSeconds): array {
    $lua = <<<'LUA'
        local current = redis.call('INCR', KEYS[1])
        if current == 1 then
            redis.call('EXPIRE', KEYS[1], ARGV[1])
        end
        local ttl = redis.call('TTL', KEYS[1])
        return {current, ttl}
    LUA;

    $redis = RedisProvider::connection();
    $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);
    /** @var array{0: int, 1: int} $result */
    $result = $redis->eval($lua, [$key, (string) $windowSeconds], 1);
    $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_JSON);

    $count = (int) $result[0];
    $ttl = (int) $result[1];

    if ($count > $maxAttempts) {
        return ['allowed' => false, 'remaining' => 0, 'retry_after' => max(1, $ttl)];
    }
    return ['allowed' => true, 'remaining' => $maxAttempts - $count];
}
```

**Why Lua instead of PIPELINE:** A PIPELINE sends INCR and TTL as two separate commands without atomicity
guarantees between them. Under concurrent load, two requests can both see count=1 and neither sets EXPIRE,
leaving the key without TTL (immortal counter). Lua `EVAL` executes atomically in a single Redis command slot.

**phpredis `eval()` signature:** `$redis->eval(string $script, array $args, int $numKeys): mixed`
- `$args[0..numKeys-1]` become KEYS[1..N] inside the script
- `$args[numKeys..]` become ARGV[1..M]
- The third parameter is the count of KEYS entries

### Pattern 3: SSE Server Presence via Redis TTL Key

**What:** Replace `isServerRunning()` (reads `/tmp/agvote-sse.pid`) with a check of a Redis key
`sse:server:active` that the SSE endpoint writes with `SET ... EX 90` on each poll iteration.

**When to use:** Anywhere that currently calls `EventBroadcaster::isServerRunning()`.

**Example:**
```php
// In events.php — write heartbeat each loop iteration:
$redis->set('sse:server:active', '1', ['EX' => 90]);

// In EventBroadcaster::isServerRunning() — replace PID check:
public static function isServerRunning(): bool {
    try {
        $redis = RedisProvider::connection();
        return (bool) $redis->exists('sse:server:active');
    } catch (\Throwable) {
        return false;
    }
}
```

**Why TTL key:** The TTL guarantees automatic expiry if the SSE process dies without cleanup. A PID file
persists forever, causing false positives.

### Pattern 4: EventBroadcaster — Redis-Only Fan-Out

**What:** Delete all `queueFile()`, `dequeueFile()`, `publishToSseFile()`, `dequeueSseFile()`,
`sseFilePath()` methods and the `useRedis()` conditional. All methods call Redis directly and throw on
failure.

**When to use:** This is the target state — no conditional branching on Redis availability.

**Key keys used (already in production):**
```
sse:consumers:{meetingId}     — SET of active consumer IDs
sse:queue:{meetingId}:{consumerId} — RPUSH per-consumer queue (TTL 60s)
sse:operators:{meetingId}     — SET for operator presence badge (TTL 90s)
sse:event_queue               — General event queue (legacy, can be kept for CLI dequeue)
```

**What `publishToSse()` becomes after removing fallback:**
```php
private static function publishToSse(array $event): void {
    $meetingId = $event['meeting_id'] ?? null;
    if ($meetingId === null) {
        return;
    }
    $redis = RedisProvider::connection(); // throws if Redis down — intentional
    $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);
    $consumers = $redis->sMembers("sse:consumers:{$meetingId}");
    if (!empty($consumers)) {
        $encoded = json_encode($event);
        $pipe = $redis->multi(\Redis::PIPELINE);
        foreach ($consumers as $consumerId) {
            $queueKey = "sse:queue:{$meetingId}:{$consumerId}";
            $pipe->rPush($queueKey, $encoded);
            $pipe->expire($queueKey, 60);
            $pipe->lTrim($queueKey, -100, -1);
        }
        $pipe->exec();
    }
    $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_JSON);
}
```

### Anti-Patterns to Avoid

- **Silent Redis fallback:** The entire point of this phase is removing silent fallbacks. Any
  `catch (Throwable) { self::queueFile(...) }` is an anti-pattern post-migration.
- **PIPELINE for atomic increment:** INCR then EXPIRE in a pipeline is not atomic. Use Lua `EVAL`.
- **PID-file for process detection:** `/tmp/*.pid` files survive process death. Use Redis TTL key.
- **Static file constants left in class:** After removing file backend, delete `QUEUE_FILE`,
  `LOCK_FILE` constants and `storageDir` static from `RateLimiter` to avoid dead code.
- **Checking `RedisProvider::isAvailable()` before every call:** Once Redis is mandatory, the
  `isAvailable()` guard becomes dead code in callers — remove all conditional `if (self::useRedis())`.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Atomic counter with TTL | Custom MULTI/EXEC counter | Redis Lua EVAL (INCR + EXPIRE in one script) | MULTI/EXEC is not truly atomic when key expires mid-transaction |
| Connection retry on boot | Custom retry loop | Throw immediately with clear message | Retry loops mask infra problems; fail-fast is the requirement |
| Fan-out to multiple SSE consumers | Custom pub/sub mechanism | Existing RPUSH per-consumer pattern (already in EventBroadcaster) | Pattern is already implemented and tested in production |

**Key insight:** The hard part (Redis fan-out, consumer registration, pipeline flush) is already written.
This phase deletes fallback code, not adds new logic.

---

## Common Pitfalls

### Pitfall 1: OPT_SERIALIZER state left inconsistent
**What goes wrong:** EventBroadcaster temporarily switches `OPT_SERIALIZER` to `SERIALIZER_NONE` to push
raw JSON strings, then restores it. If an exception occurs mid-pipeline, the serializer stays in NONE mode
and subsequent calls that expect JSON deserialization break silently.

**Why it happens:** phpredis serializer is a global option on the connection instance, shared across all
callers.

**How to avoid:** Wrap serializer toggle in try/finally:
```php
$redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);
try {
    // raw string operations
} finally {
    $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_JSON);
}
```

**Warning signs:** `json_decode()` receiving already-decoded PHP arrays instead of strings.

### Pitfall 2: RedisProvider prefix breaks key lookups
**What goes wrong:** `RedisProvider` sets `OPT_PREFIX = 'agvote:'`. This means all keys get prefixed
automatically — `sse:server:active` becomes `agvote:sse:server:active` in Redis. Code that constructs
key strings for display/debugging may show the un-prefixed form.

**Why it happens:** The prefix is transparent to phpredis calls but invisible in key names passed to logs
or `KEYS` patterns.

**How to avoid:** Always use the phpredis client methods (not raw `redis-cli` strings) to check keys during
development. Aware that `$redis->exists('sse:server:active')` will check `agvote:sse:server:active`.

### Pitfall 3: Lua script KEYS vs ARGV confusion
**What goes wrong:** Passing the wrong count as third argument to `eval()` causes KEYS[1] to be `null`
inside the script and the script silently returns wrong results.

**Why it happens:** `$redis->eval($script, $args, $numKeys)` — the third param is numKeys, not total args.

**How to avoid:** For the rate-limit script with key=1 and window+max as args:
```php
$redis->eval($lua, [$key, (string) $windowSeconds, (string) $maxAttempts], 1);
// KEYS[1] = $key, ARGV[1] = windowSeconds, ARGV[2] = maxAttempts
```

### Pitfall 4: Application::boot() called in tests without Redis
**What goes wrong:** After adding the mandatory Redis check, any test that calls `Application::boot()`
(directly or via bootstrap) will fail if the test environment has no Redis.

**Why it happens:** Tests often run with no Redis available.

**How to avoid:** Tests should not call `Application::boot()`. The existing pattern (inject mocks via
nullable constructor params, call classes directly) avoids bootstrap. For the new
`ApplicationBootTest.php`, mock `RedisProvider::connection()` or use a test double. RateLimiter and
EventBroadcaster tests already avoid bootstrap by calling classes directly — keep that pattern.

### Pitfall 5: `RateLimiter::cleanup()` still touches /tmp after migration
**What goes wrong:** `cleanup()` scans `self::$storageDir` which defaults to `/tmp/ag-vote-ratelimit`.
After removing file backend, this method becomes dead code but still references /tmp.

**Why it happens:** The method will not be called in practice (Redis-only), but it pollutes the class.

**How to avoid:** Delete `cleanup()` entirely or replace with a no-op comment that cleanup is handled
by Redis TTL.

---

## Code Examples

### Lua Rate-Limit Script — Verified Pattern
```php
// Standard Redis pattern: atomic increment with sliding TTL
// KEYS[1] = rate limit key, ARGV[1] = window seconds, ARGV[2] = max attempts
private const RATE_LIMIT_LUA = <<<'LUA'
    local current = redis.call('INCR', KEYS[1])
    if current == 1 then
        redis.call('EXPIRE', KEYS[1], tonumber(ARGV[1]))
    end
    local ttl = redis.call('TTL', KEYS[1])
    return {current, ttl}
LUA;
```

### Redis SET with EX (TTL presence key)
```php
// phpredis SET with options array — atomically sets key and expiry
$redis->set('sse:server:active', '1', ['EX' => 90]);
// OR the older form, both work in phpredis 5+:
$redis->setEx('sse:server:active', 90, '1');
```

### Hard-fail Redis boot check pattern
```php
// Placed immediately after RedisProvider::configure() in Application::boot()
try {
    RedisProvider::connection();
} catch (\RuntimeException $e) {
    throw new \RuntimeException(
        'Redis est indisponible — impossible de demarrer l\'application. '
        . 'Assurez-vous que le service Redis est accessible sur '
        . (getenv('REDIS_HOST') ?: '127.0.0.1') . ':' . (getenv('REDIS_PORT') ?: '6379') . '. '
        . 'Erreur : ' . $e->getMessage(),
    );
}
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| File locks with flock() for rate-limit | Redis PIPELINE INCR+EXPIRE | Existing codebase | Pipeline is still not atomic — Lua needed |
| PID file /tmp/agvote-sse.pid for presence | Redis TTL key | This phase | Automatic expiry on process death |
| File queue /tmp/agvote-sse-*.json | Redis RPUSH per-consumer | Already implemented in EventBroadcaster | Remove fallback paths |
| Optional Redis (graceful degradation) | Mandatory Redis (hard fail) | This phase | Eliminates silent /tmp fallback in prod |

**Deprecated/outdated after this phase:**
- `QUEUE_FILE`, `LOCK_FILE` constants in EventBroadcaster
- `storageDir` static and `cleanup()` in RateLimiter
- `isServerRunning()` PID-file implementation
- All `useRedis()` conditional checks in both classes

---

## Open Questions

1. **`dequeueSseFile()` public method — callers outside EventBroadcaster?**
   - What we know: The method is `public` and called from `events.php` as file fallback in `pollEvents()`
   - What's unclear: Are there other callers not yet found?
   - Recommendation: Grep for `dequeueSseFile` before deleting; one call found in events.php (also being migrated)

2. **`RateLimiter::cleanup()` callers**
   - What we know: The method is public and may be called from a maintenance command
   - What's unclear: Is it called from `bin/console` or any scheduled task?
   - Recommendation: Grep for `cleanup` in bin/ and app/Command/ before deleting

3. **SSE `events.php` — file fallback in `pollEvents()` function**
   - What we know: The function falls back to `EventBroadcaster::dequeueSseFile()` when Redis unavailable
   - What's unclear: After removing the fallback from EventBroadcaster, this call will fail at compile-time if the method is removed
   - Recommendation: Migrate `pollEvents()` in the same task as EventBroadcaster refactor

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit ^10.5 |
| Config file | `phpunit.xml` or `phpunit.xml.dist` (check root) |
| Quick run command | `timeout 60 php vendor/bin/phpunit tests/Unit/RateLimiterTest.php --no-coverage` |
| Full suite command | `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` |

### Phase Requirements to Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| REDIS-01 | EventBroadcaster pushes to Redis queue, no /tmp file created | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/EventBroadcasterTest.php --no-coverage` | Wave 0 |
| REDIS-02 | RateLimiter Lua script increments atomically, no flock called | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/RateLimiterTest.php --no-coverage` | exists (file-only tests need Redis variant) |
| REDIS-03 | isServerRunning() checks Redis key, not /tmp/*.pid | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/EventBroadcasterTest.php --no-coverage` | Wave 0 |
| REDIS-04 | Application::boot() throws RuntimeException when Redis down | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/ApplicationBootTest.php --no-coverage` | Wave 0 |

### Sampling Rate
- **Per task commit:** Run the specific test file for that task
- **Per wave merge:** `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Unit/EventBroadcasterTest.php` — covers REDIS-01 and REDIS-03 (Redis-only paths, no /tmp writes)
- [ ] `tests/Unit/ApplicationBootTest.php` — covers REDIS-04 (boot failure message)
- [ ] `tests/Unit/RateLimiterTest.php` — existing file covers file-backend; add Lua Redis test methods

---

## Sources

### Primary (HIGH confidence)
- Direct codebase inspection — `app/SSE/EventBroadcaster.php`, `app/Core/Security/RateLimiter.php`,
  `app/Core/Application.php`, `app/Core/Providers/RedisProvider.php`, `public/api/v1/events.php`
- `docker-compose.yml` (worktree copy) — Redis 7.4-alpine3.21 confirmed
- `Dockerfile` (worktree copy) — phpredis compiled via pecl, extension enabled

### Secondary (MEDIUM confidence)
- Redis documentation pattern for Lua EVAL atomicity (INCR+EXPIRE race condition is a well-known Redis FAQ topic)
- phpredis `eval()` signature: `eval(string $script, array $args, int $numKeys)` — documented in phpredis README

### Tertiary (LOW confidence)
- None

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all confirmed by Dockerfile and docker-compose.yml in repo
- Architecture: HIGH — all target files read and understood; changes are deletions + minor rewrites
- Pitfalls: HIGH — OPT_SERIALIZER issue is visible in the existing code (serializer toggle without try/finally in some branches); prefix issue is confirmed by RedisProvider::configure()

**Research date:** 2026-04-07
**Valid until:** 2026-07-07 (stable stack, Redis 7.x API stable)
