# Stack Research

**Domain:** PHP brownfield technical debt reduction — voting/association management app
**Researched:** 2026-04-07
**Confidence:** MEDIUM-HIGH (verified via official docs and WebSearch cross-referencing; training data only used as initial hypothesis)

---

## Context: What Already Exists

AgVote runs PHP 8.4-fpm + PostgreSQL + Redis (phpredis extension). The stack is NOT being replaced — this milestone eliminates specific fragile subsystems and replaces them with reliable alternatives within the same runtime. No framework migration. No new languages.

---

## Recommended Stack

### Core Technologies (existing — keep)

| Technology | Version | Purpose | Why Keep |
|------------|---------|---------|----------|
| PHP | 8.4 | Application runtime | Already on latest stable. Property hooks, asymmetric visibility, HTML5 parser built-in. No upgrade needed. |
| PostgreSQL | 12+ (target 16) | Primary datastore | Existing schema. FILTER clause for aggregations available since PG9.4, materialized views available since PG9.3. |
| phpredis | ^6.0 (extension) | Redis client (cache, queues, SSE, rate limiting) | C extension, lower overhead than Predis. Already installed. Supports XADD/XREAD/XREADGROUP (Streams), atomic Lua eval, pub/sub. |
| Symfony Mailer | ^8.0 | SMTP delivery | Already integrated. Keep — well-maintained, handles STARTTLS/TLS. |
| PHPUnit | ^10.5 | Test harness | Already integrated. Target tests individually per CLAUDE.md rules. |

### Libraries to Add

| Library | Version | Purpose | Why This One |
|---------|---------|---------|--------------|
| openspout/openspout | ^5.6 | Streaming XLSX/ODS/CSV writer (replaces in-memory PhpSpreadsheet for large exports) | True streaming: writes row-by-row, keeps memory below 3MB regardless of row count. PHP 8.4 native (`~8.4.0 \|\| ~8.5.0`). Actively maintained (v5.6.0 released March 2026). |

### Libraries to Retire (after replacement)

| Library | Current Use | Problem | Replacement |
|---------|-------------|---------|-------------|
| PhpSpreadsheet ^1.29 | ExportService full-sheet generation | Loads entire dataset in memory before writing. 1.6KB per cell on 64-bit PHP. Crashes on large meetings. | openspout/openspout for streaming export. Keep PhpSpreadsheet ONLY for complex cell formatting requirements (formula evaluation, charts) — assess per-sheet need. |

### Development Tools (existing — keep)

| Tool | Purpose | Notes |
|------|---------|-------|
| PHP-CS-Fixer ^3.0 | Code style | Already configured. Run before each commit. |
| PHPStan ^2.1 | Static analysis | Already integrated. Should be run at level 6+ for refactored services. |

---

## Tooling by Problem Area

### 1. Redis: Mandatory Infrastructure (SSE, Rate Limiting, PID Detection)

**Pattern: Redis Pub/Sub for SSE broadcast (replace file queue)**

The current file-based fallback in `EventBroadcaster.php` (lines 206-273) uses flock-based JSON files in `/tmp`. Replace entirely with Redis Pub/Sub:

- Publisher: `$redis->publish('agvote:sse:meeting:{id}', json_encode($event))`
- SSE worker: `$redis->subscribe(['agvote:sse:meeting:{id}'], $callback)` — blocking, dedicated process
- No fallback. If Redis is unavailable, SSE endpoint returns 503.

**Pattern: Redis Streams (XADD/XREAD) for durable SSE history**

If event replay (reconnecting clients catching up) is needed:
- Use `xAdd('agvote:stream:meeting:{id}', '*', $fields)` to append
- Use `xRead(['agvote:stream:meeting:{id}' => $lastId])` for catch-up
- Use `EXPIRE` to cap stream lifetime per meeting

**Pattern: Redis key with TTL for SSE server liveness (replace PID file)**

Replace `/tmp/agvote-sse.pid` with a Redis heartbeat key:
- SSE worker writes `SET agvote:sse:alive 1 EX 10` every 5 seconds
- Health check reads this key — absence after TTL means worker is down
- No stale PID files possible.

**Pattern: Redis atomic INCR + Lua for rate limiting (replace file locks)**

Replace `RateLimiter.php` flock approach with:
```php
// Lua script — atomic: no race between INCR and EXPIRE
$script = "
  local current = redis.call('INCR', KEYS[1])
  if current == 1 then redis.call('EXPIRE', KEYS[1], ARGV[1]) end
  return current
";
$count = $redis->eval($script, ["ratelimit:{$context}:{$window}"], 1, $windowSeconds);
```
- Key per context + time window (fixed window counter)
- For stricter limits: sliding window via sorted sets (ZADD timestamp, ZREMRANGEBYSCORE, ZCARD)
- phpredis `eval()` supports Lua atomicity — no separate MULTI/EXEC needed

**Confidence:** HIGH — Redis.io official docs, phpredis extension API, Crunchy Data blog (all verified)

---

### 2. Streaming XLSX Export (replace in-memory PhpSpreadsheet)

**Library: openspout/openspout ^5.6**

Install:
```bash
composer require openspout/openspout
```

**Pattern: Row-by-row streaming to browser**

```php
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Common\Entity\Row;

$writer = new Writer();
$writer->openToBrowser('export.xlsx');

// Stream header row
$writer->addRow(Row::fromValues(['Nom', 'Prénom', 'Vote', ...]));

// Cursor-based query iteration — never load full result set
$stmt = $pdo->prepare('SELECT ... FROM votes WHERE meeting_id = ? ORDER BY id');
$stmt->execute([$meetingId]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $writer->addRow(Row::fromValues(array_values($row)));
}

$writer->close();
```

Key properties:
- Memory stays below 3MB for any row count (verified in library docs)
- `openToBrowser()` streams directly — no temp file needed
- Requires PHP extensions: DOM, Filter, LibXML, XMLReader, Zip (all available in current Dockerfile)
- Does NOT support formulas or charts — pure data export only

**What PhpSpreadsheet keeps:** Complex formatted exports (if any) with formulas. Evaluate per-sheet. If pure data, migrate to OpenSpout. PhpSpreadsheet can remain as a dev dependency for sheets that genuinely need formatting features — do not rip it out globally until ExportService is audited sheet by sheet.

**Confidence:** HIGH — Packagist confirms v5.6.0, official docs confirm streaming API and memory profile

---

### 3. PostgreSQL Query Aggregation (replace 10+ COUNT queries)

**Pattern: Single aggregation with FILTER clause (PostgreSQL-native)**

Replace `MeetingStatsRepository`'s 10+ separate `COUNT(*)` queries with one:

```sql
SELECT
  COUNT(*) FILTER (WHERE attendance_mode = 'present')         AS present_count,
  COUNT(*) FILTER (WHERE attendance_mode = 'proxy')           AS proxy_count,
  COUNT(*) FILTER (WHERE attendance_mode = 'represented')     AS represented_count,
  COUNT(*) FILTER (WHERE m.status = 'approved')               AS approved_motions,
  COUNT(*) FILTER (WHERE m.status = 'rejected')               AS rejected_motions,
  COUNT(DISTINCT v.member_id) FILTER (WHERE v.choice = 'yes') AS yes_votes
FROM attendances a
LEFT JOIN motions m ON m.meeting_id = a.meeting_id
LEFT JOIN votes v ON v.motion_id = m.id
WHERE a.meeting_id = :meeting_id
```

**FILTER vs CASE WHEN:**
- Both compile to equivalent plans on PostgreSQL 9.4+
- `FILTER (WHERE ...)` is preferred: cleaner syntax, applies condition before aggregation (skips expression evaluation entirely for non-matching rows)
- `COUNT(CASE WHEN ... THEN 1 END)` is acceptable fallback if mixed with non-PostgreSQL targets — not relevant here

**Caching strategy:** Cache the result in Redis with TTL matching meeting update frequency:
```php
$cacheKey = "meeting:{$id}:stats";
$cached = $redis->get($cacheKey);
if ($cached) return json_decode($cached, true);
$stats = $this->runAggregation($id);
$redis->setex($cacheKey, 60, json_encode($stats)); // 60s TTL
```

Invalidate on any vote/motion state change event.

**Confidence:** HIGH — PostgreSQL official docs confirm FILTER clause syntax, GeeksforGeeks and Crunchy Data tutorials cross-confirmed

---

### 4. PDO Timeout Configuration

Two distinct timeouts are needed; they are NOT the same setting:

**Connection timeout (TCP handshake):**
```php
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION, // Required for ATTR_TIMEOUT to work
    PDO::ATTR_TIMEOUT    => 5,  // Seconds for TCP connect only; minimum is 2 (libpq enforces)
]);
```

**Query timeout (statement execution):**

`PDO::ATTR_TIMEOUT` does NOT limit query execution time — it only covers connection establishment. For query timeouts, use PostgreSQL's `statement_timeout`:

```php
// Set at session level immediately after connect — applies to all queries on this connection
$pdo->exec("SET statement_timeout = '30s'");

// Or set per critical query block (SET LOCAL — reverts after transaction ends)
$pdo->beginTransaction();
$pdo->exec("SET LOCAL statement_timeout = '10s'");
$pdo->prepare($heavyQuery)->execute($params);
$pdo->commit();
```

Recommended values for AgVote:
- Default session: `30s` — covers normal CRUD
- Export queries: `120s` — streaming queries can be long
- Stats aggregation: `15s` — should be fast; fail loudly if not

**Do NOT set `statement_timeout` in `postgresql.conf` globally** — this affects all sessions including migrations and admin tasks. Set it at application connection level only.

**Confidence:** HIGH — pracucci.com technical article, PostgreSQL official docs (v18 runtime config), Crunchy Data blog all confirm this distinction

---

### 5. Controller Refactoring: Extract Service Layer

**Pattern: Single Responsibility Services with constructor DI**

No new library needed — this is a PHP architecture pattern. The existing constraint (`DI par constructeur avec parametres optionnels nullable pour les tests`) already defines the pattern:

```php
// Before: ImportController 921 lines with business logic
class ImportController extends AbstractController {
    public function handle(): void {
        // CSV parsing, fuzzy matching, member creation, error aggregation — all here
    }
}

// After: Controller becomes orchestrator only
class ImportController extends AbstractController {
    public function __construct(
        private readonly ImportService    $importService,    // business logic
        private readonly MemberRepository $memberRepository, // data access
        ?ImportService $testImport = null  // test injection point
    ) {
        $this->importService = $testImport ?? $importService;
    }

    public function handle(): void {
        $result = $this->importService->processUpload($this->getFile(), $this->getTenantId());
        $this->respondJson($result);
    }
}
```

Extraction order (highest ROI first):
1. `ImportController` (921 lines) → `ImportService` (CSV parsing, fuzzy matching, member upsert)
2. `AuthMiddleware` (871 lines, 10+ statics) → `SessionService` (timeout logic, revalidation), `AuthorizationService` (RBAC checks)
3. `MeetingReportsController` (727 lines) → `MeetingReportService`
4. `MotionsController` (720 lines) → assess after extraction; split only if > 700 lines remains

**Static state elimination in AuthMiddleware:**

Replace 10+ static properties with a request-scoped value object passed via DI:
```php
class AuthContext {
    public function __construct(
        public readonly ?User   $user = null,
        public readonly ?int    $meetingId = null,
        public readonly array   $meetingRoles = [],
        public readonly bool    $sessionExpired = false,
    ) {}
}
```
No static state = no cross-request pollution in CLI/FPM workers.

**Confidence:** HIGH — Service Layer pattern is well-documented and directly matches existing namespace/DI constraints in CLAUDE.md

---

### 6. Email Queue Backpressure

**Pattern: Redis LPOP with count for batch consumption**

Replace `EmailQueueService::processQueue()` full-memory load with batched LPOP from Redis list:

```php
// Process in batches of 25 — never more than batch_size in memory
$batchSize = 25;
while (true) {
    $batch = $redis->lPop('agvote:email:queue', $batchSize);
    if (empty($batch)) break;

    foreach ($batch as $payload) {
        $email = json_decode($payload, true);
        $this->mailerService->send($email);
    }
    // Natural backpressure: if SMTP is slow, loop slows, queue depth grows, monitoring alerts
}
```

`LPOP key count` is available in Redis 6.2+ (confirmed in Redis official docs). phpredis `lPop($key, $count)` exposes this.

**Confidence:** MEDIUM — Redis list docs confirm LPOP count param; phpredis API verified via GitHub; batch size of 25 is convention, not a standard

---

## Installation

```bash
# Add streaming XLSX writer
composer require openspout/openspout

# Verify PHP extension prerequisites are present (should already be in Dockerfile)
php -m | grep -E 'dom|zip|xmlreader|libxml'
```

No other new dependencies needed. All other patterns use phpredis (already installed) and PDO (already configured).

---

## Alternatives Considered

| Recommended | Alternative | Why Not |
|-------------|-------------|---------|
| openspout/openspout | PhpSpreadsheet streaming (cell caching) | PhpSpreadsheet's cell caching offloads to APCu/disk but still buffers sheet structure. Not true streaming. API complexity higher for simple tabular exports. |
| openspout/openspout | CSV-only export | CSV cannot handle multi-sheet results (attendance + votes + motions in one file). Users explicitly need XLSX. |
| Redis Pub/Sub (SSE) | Mercure hub | Mercure is the right long-term answer for high-concurrency SSE (handles thousands of connections). Overkill for this milestone — adds infrastructure dependency. Revisit if concurrent meeting count exceeds 50. |
| Redis Pub/Sub (SSE) | RabbitMQ | Correct for persistent durable messaging. Over-engineered for this use case. Redis already in stack — don't add a second message broker. |
| Redis Streams (XADD) | Redis Pub/Sub only | Pub/Sub loses messages if SSE client is not connected. Streams provide replay for reconnects. Choose Streams if event history matters (votes, quorum changes). Choose Pub/Sub if fire-and-forget is acceptable (live cursor updates). |
| Lua eval (rate limiter) | MULTI/EXEC pipeline | MULTI/EXEC is not truly atomic for read-then-write (another client can interleave between commands in a MULTI block). Lua eval is atomic by design. |
| PDO statement_timeout via exec() | postgresql.conf global timeout | Global timeout breaks migrations and long-running admin operations. Per-session is the correct scope. |
| AuthContext value object | Request container / DI framework | No framework in scope. A plain PHP value object passed via constructor achieves the same result without new dependencies. |

---

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| File-based SSE fallback (`/tmp/agvote-sse-*.json`) | Race conditions, event loss on /tmp clear, no replay, flock contention under load | Redis Pub/Sub (fire-and-forget) or Redis Streams (durable) |
| `flock()` for rate limiting | Serializes concurrent requests at filesystem level; bottleneck on hot endpoints like vote.cast | Redis atomic Lua eval with INCR + EXPIRE |
| PID file for process detection (`/tmp/agvote-sse.pid`) | Stale PIDs after crash; no liveness — only existence check | Redis key with TTL, refreshed by worker heartbeat |
| PhpSpreadsheet for large dataset exports | 1.6KB/cell in memory; meeting with 10k attendances × 5 columns = 80MB before writing | openspout/openspout row-by-row streaming |
| Static properties in AuthMiddleware | Cross-request state pollution in FPM workers; test isolation requires explicit reset(); concurrency bugs in CLI | Request-scoped `AuthContext` value object injected via constructor |
| `PDO::ATTR_TIMEOUT` for query timeout | Only controls TCP connection establishment, not query execution time — a 3s ATTR_TIMEOUT does not prevent a 5-minute runaway query | `$pdo->exec("SET statement_timeout = '30s'")` after connect |
| Loading full email queue into memory | Queue backlog exhausts memory; slow SMTP cascades into OOM | Redis LPOP with count, process in batches of 25 |
| Multiple `COUNT(*)` queries in stats | N queries for N statistics; linear with statistic count | Single PostgreSQL aggregation with `COUNT(*) FILTER (WHERE ...)` |

---

## Version Compatibility

| Package | PHP Constraint | Redis Constraint | Notes |
|---------|---------------|-----------------|-------|
| openspout/openspout ^5.6 | `~8.4.0 \|\| ~8.5.0` | N/A | Requires DOM, Filter, LibXML, XMLReader, Zip extensions — all present in current Dockerfile |
| phpredis (extension) | PHP 7.4–8.5 | Redis 3.0+ | LPOP count param requires Redis 6.2+; XADD/XREAD requires Redis 5.0+ |
| PostgreSQL FILTER clause | N/A | N/A | Available since PostgreSQL 9.4; well within our 12+ requirement |
| Redis LPOP count | N/A | Redis 6.2+ | Verify Redis version in docker-compose before implementing |

---

## Sources

- [openspout/openspout on Packagist](https://packagist.org/packages/openspout/openspout) — v5.6.0, PHP 8.4 constraint (HIGH confidence)
- [OpenSpout docs (4.x branch)](https://github.com/openspout/openspout/blob/4.x/docs/index.md) — streaming API, memory profile (HIGH confidence)
- [Redis Rate Limiting tutorial](https://redis.io/tutorials/howtos/ratelimiting/) — sliding window, fixed window, Lua atomicity (HIGH confidence)
- [Redis Lists documentation](https://redis.io/docs/latest/develop/data-types/lists/) — LPOP count, LRANGE, LTRIM patterns (HIGH confidence)
- [PDO pgsql connection timeout — pracucci.com](https://pracucci.com/php-pdo-pgsql-connection-timeout.html) — ATTR_TIMEOUT TCP-only scope (HIGH confidence)
- [Control Runaway Queries — Crunchy Data](https://www.crunchydata.com/blog/control-runaway-postgres-queries-with-statement-timeout) — statement_timeout per session (HIGH confidence)
- [PostgreSQL runtime config v18 — official docs](https://www.postgresql.org/docs/current/runtime-config-client.html) — statement_timeout (HIGH confidence)
- [PostgreSQL multiple counts — GeeksforGeeks](https://www.geeksforgeeks.org/how-to-get-multiple-counts-with-single-query-in-postgresql/) — FILTER vs CASE WHEN (MEDIUM confidence; cross-verified with official docs)
- [phpredis GitHub — Redis Streams API](https://github.com/phpredis/phpredis) — XADD, XREAD, XREADGROUP, lPop count (HIGH confidence)
- [Messaging with PHP and Redis Streams — patriqueouimet.ca](https://patriqueouimet.ca/post/messaging-php-and-redis-streams) — practical Streams pattern in PHP (MEDIUM confidence)

---

*Stack research for: AgVote — PHP 8.4 technical debt reduction*
*Researched: 2026-04-07*
