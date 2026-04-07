# Architecture Research

**Domain:** PHP technical debt reduction — brownfield MVC voting application
**Researched:** 2026-04-07
**Confidence:** HIGH (existing codebase analyzed directly; refactoring patterns verified with multiple sources)

## Standard Architecture

### System Overview

Current state — components and their debt locations:

```
┌─────────────────────────────────────────────────────────────────────┐
│                        HTTP Entry Point                              │
│  public/index.php  →  Router  →  Middleware Chain                   │
├─────────────────────────────────────────────────────────────────────┤
│                     Middleware & Security                            │
│  ┌──────────────────────┐  ┌──────────────┐  ┌──────────────────┐  │
│  │  AuthMiddleware       │  │ RateLimitGuard│  │  CsrfMiddleware  │  │
│  │  [DEBT: 10+ statics] │  │ [DEBT: files]│  │  [OK]            │  │
│  └──────────────────────┘  └──────────────┘  └──────────────────┘  │
├─────────────────────────────────────────────────────────────────────┤
│                          Controllers                                  │
│  ┌────────────────┐  ┌────────────────┐  ┌──────────────────────┐  │
│  │ ImportController│  │MeetingReports  │  │  MotionsController   │  │
│  │ [DEBT: 921 LOC]│  │[DEBT: 727 LOC] │  │  [DEBT: 720 LOC]     │  │
│  └────────┬───────┘  └───────┬────────┘  └──────────┬───────────┘  │
│           │                  │                       │              │
├───────────┴──────────────────┴───────────────────────┴──────────────┤
│                       Service Layer                                   │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────────┐  │
│  │BallotsService│  │ExportService │  │  EmailQueueService        │  │
│  │  [OK]        │  │[DEBT: memory]│  │  [DEBT: full queue load]  │  │
│  └──────────────┘  └──────────────┘  └──────────────────────────┘  │
├─────────────────────────────────────────────────────────────────────┤
│                      Repository Layer                                 │
│  ┌───────────────────┐  ┌────────────────────────────────────────┐  │
│  │MeetingStatsRepo   │  │  Other Repositories (OK)               │  │
│  │[DEBT: 10+ COUNTs] │  │  MeetingRepository, BallotRepository   │  │
│  └───────────────────┘  └────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────────────────────┤
│                      Infrastructure                                   │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────────┐  │
│  │  PostgreSQL  │  │    Redis     │  │  SSE/EventBroadcaster     │  │
│  │  [no timeout]│  │  [already    │  │  [DEBT: file fallback,    │  │
│  │              │  │   present]   │  │   PID-file health check]  │  │
│  └──────────────┘  └──────────────┘  └──────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| Component | Responsibility | Debt Status |
|-----------|----------------|-------------|
| `Router` | Route table dispatch, middleware wiring | Clean |
| `AuthMiddleware` | Session validation, RBAC, tenant isolation | Critical debt: 10+ static vars, test injection |
| `RateLimitGuard` | Per-context sliding window enforcement | Critical debt: file locks in /tmp |
| `ImportController` | CSV/XLSX member import orchestration | Critical debt: 921 LOC, business logic inline |
| `MeetingReportsController` | Report generation orchestration | Moderate debt: 727 LOC |
| `MotionsController` | Motion/vote lifecycle orchestration | Moderate debt: 720 LOC |
| `ExportService` | XLSX/PDF generation | Critical debt: full in-memory load |
| `EmailQueueService` | Async email processing | Moderate debt: full queue in memory |
| `MeetingStatsRepository` | Meeting dashboard statistics | Moderate debt: 10+ individual COUNT queries |
| `EventBroadcaster` (SSE) | Real-time event push to clients | Critical debt: /tmp file queue, PID-file health |
| `DatabaseProvider` | PDO singleton | Critical debt: no timeouts configured |
| All Repositories | Typed SQL access via PDO | Clean pattern, no changes needed |
| `BallotsService` | Vote casting with concurrency guards | Clean |
| `QuorumEngine` | Quorum calculation | Clean |

## Refactoring Build Order

The ordering principle: **remove production risks before improving code structure**. A crash in prod is worse than a fat controller.

### Phase 1: Infrastructure Hardening (no API surface changes)

These changes are self-contained. Each can be merged independently. No controller changes, no API breakage.

**1a. PDO Timeouts**

Add to `DatabaseProvider` DSN or `setAttribute()` calls:
- `PDO::ATTR_TIMEOUT` for connection timeout (TCP connect only)
- Execute `SET statement_timeout = '30s'` immediately after connect for query-level protection
- Requires `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` to take effect

Safe because: additive change, no existing callers change. Catch `PDOException` already in `AbstractController::handle()`.

**1b. Redis Rate Limiting**

Replace `RateLimiter` file-based locking with Redis atomic `INCR` + `EXPIRE`:
- Redis is already configured via `RedisProvider` — no new dependency
- Sliding window: store counter in key `ratelimit:{context}:{client}`, set TTL on first increment
- Atomic via `MULTI`/`EXEC` or Lua script to prevent TOCTOU
- Drop all `/tmp/agvote-ratelimit-*.lock` files

Safe because: `RateLimitGuard` is called from Router middleware, interface stays identical.

**1c. Redis SSE Queue + PID Removal**

Replace `EventBroadcaster` file queue with Redis List:
- `RPUSH agvote:sse:{meeting_id}` to publish, `BLPOP` with timeout to consume
- TTL on the list key (e.g., 24h) prevents unbounded growth
- Replace `/tmp/agvote-sse.pid` health check with Redis key `agvote:sse:heartbeat` with 10s TTL, refreshed by SSE server loop
- Remove `MAX_FILE_SIZE` 1000-event queue cap — Redis list has no hard cap by default

Safe because: `EventBroadcaster` interface stays identical from caller's perspective. Internal transport swapped.

### Phase 2: Query & Memory Optimizations (no API surface changes)

These require changing SQL or data processing but not controller signatures.

**2a. MeetingStatsRepository Aggregation**

Replace 10+ individual `COUNT(*)` queries with one query using PostgreSQL `FILTER` clause:

```sql
SELECT
  COUNT(*) FILTER (WHERE attendance_mode = 'present') AS present_count,
  COUNT(*) FILTER (WHERE attendance_mode = 'proxy')   AS proxy_count,
  COUNT(*) FILTER (WHERE voted = true)                 AS voted_count,
  -- ... remaining stats
FROM attendances
WHERE meeting_id = :meeting_id
```

Safe because: the repository method signatures remain the same. Callers receive the same data structure.

**2b. ExportService Streaming**

Replace `PhpSpreadsheet` full-load with streaming approach:
- Option A: Use `openspout/openspout` (successor to `box/spout`) — streams row-by-row, low memory footprint (MEDIUM confidence — verify current package name)
- Option B: Chunk `array_map()` transformations, flush via `ob_flush()` between sheets
- For very large meetings (10k+ rows): offer CSV download path as fallback (no library required)

The key constraint: `ExportController` must stream directly to `php://output` with correct `Content-Disposition` headers rather than building in memory first.

**2c. EmailQueueService Pagination**

Replace full queue load with batch cursor:
- `LIMIT 50 OFFSET 0` processing loop
- After batch completes, query next batch; stop when query returns empty
- Add `max_attempts` column check to skip permanently failed messages

### Phase 3: Service Extraction (changes controller internal structure only)

The Strangler Fig approach: extract business logic into services, keep controllers as thin HTTP adapters. API signatures must not change.

**Principle:** Extract, do not rewrite. Move code block by block with tests before and after each move.

**3a. ImportController (921 LOC → target: <150 LOC)**

Extract in this order:
1. `ImportValidationService` — file type checks, size validation, column mapping validation
2. `CsvParserService` (or reuse `ImportService` if it already exists) — streaming row parsing, fuzzy header matching
3. `MemberImportService` — business rules: deduplication, upsert logic, error reporting
4. Leave in `ImportController`: read file handle from request, call services in sequence, return JSON

Tests to write before extraction: fuzzy column matching edge cases (partial match, case sensitivity).

**3b. AuthMiddleware Static State (871 LOC, 10+ statics)**

This is the highest-risk extraction. Order matters:

1. First: add a comprehensive `AuthMiddlewareTest` covering all static state transitions (SESSION_REVALIDATE_INTERVAL, multi-tenant timeout cache). Do not extract yet.
2. Then: introduce a `SessionContext` value object holding `currentUserId`, `currentTenantId`, `currentMeetingId`, `currentMeetingRoles`. Pass as constructor argument.
3. Move static getters (`api_current_user_id()` etc.) to read from a request-scoped `SessionContext` stored in a well-known location (e.g., request attribute or thread-local equivalent).
4. Replace `$testSessionTimeout` / `$testTimeoutTenantId` injection with proper constructor injection of a `SessionTimeoutResolver` interface — implementable with a test double.

**3c. MeetingReportsController and MotionsController**

Only after extracting their business logic into services:
- `MeetingReportService` likely already exists — verify what remains inline in the controller
- `MotionsController`: extract vote state transition logic into `VoteLifecycleService`
- Split the controller file only if distinct concerns remain (e.g., one class for reads, one for writes)

### Phase 4: Test Coverage Gaps

Fill test gaps introduced by Phase 3 extractions and previously uncovered areas:

| Target | What to Test | Priority |
|--------|-------------|----------|
| `RgpdExportController` | Data scope validation (own-data only), unauthorized rejection | High |
| `AuthMiddleware` static transitions | SESSION_REVALIDATE_INTERVAL cache invalidation lifecycle | High |
| `EventBroadcaster` (post-Redis) | Queue overflow, consumer reconnect, heartbeat expiry | Medium |
| `ImportController` fuzzy matching | Partial column name match, case sensitivity, missing columns | Medium |
| `BallotsService` race conditions | Concurrent ballot transitions | Medium |

## Component Boundaries

### What Talks to What (dependency direction)

```
HTTP Request
    ↓
Router → [AuthMiddleware → RateLimitGuard → CsrfMiddleware]
    ↓
Controller
    ↓  calls
Services (BallotsService, ExportService, ImportService, EmailQueueService)
    ↓  calls
Repositories (MeetingRepository, BallotRepository, MeetingStatsRepository)
    ↓  calls
PDO → PostgreSQL

Controllers also call:
    EventBroadcaster → Redis (after migration)
    RepositoryFactory (singleton) → Repositories

AuthMiddleware calls:
    Redis (session) → falls back to DB session
    UserRepository (60s revalidation)
```

**Inversion rules that must not be violated:**
- Repositories never call Services
- Services never call Controllers
- Controllers never call other Controllers
- Infrastructure (Redis, PDO) never knows about domain objects

### Shared Infrastructure (cross-cutting, not a layer)

| Concern | Current Implementation | After Milestone |
|---------|----------------------|-----------------|
| Rate limiting | File locks `/tmp/` | Redis INCR+EXPIRE |
| SSE queue | File queue `/tmp/` + PID file | Redis List + TTL heartbeat key |
| Session storage | Redis (already) or DB fallback | Redis only |
| PDO connection | No timeouts | Connection timeout + statement_timeout |
| Audit logging | `audit_log()` global function | Unchanged |

## Data Flow

### Request Flow (post-refactoring, unchanged externally)

```
HTTP Request
    ↓
public/index.php (front controller)
    ↓
Router::dispatch()
    ↓
AuthMiddleware (reads session from Redis → validates → sets SessionContext)
    ↓
RateLimitGuard (Redis INCR — replaces file locks)
    ↓
Controller::handle() [<150 LOC after extraction]
    ↓
Service calls (extracted business logic)
    ↓
Repository calls (PDO with statement_timeout)
    ↓
PostgreSQL
    ↓
api_ok() / api_fail() → JSON response
```

### SSE Event Flow (after Redis migration)

```
Domain event (e.g., ballot cast)
    ↓
EventDispatcher::dispatch()
    ↓
SseListener::onEvent()
    ↓
Redis RPUSH agvote:sse:{meeting_id}   ← replaces file write + flock
    ↓
SSE server process (long-poll loop)
    BLPOP agvote:sse:{meeting_id} timeout=5
    ↓
text/event-stream response to browser
    ↓
Redis SETEX agvote:sse:heartbeat 10   ← replaces /tmp PID file
```

### Export Flow (after streaming fix)

```
ExportController receives request
    ↓
Set header: Content-Disposition: attachment; filename=export.xlsx
Set header: Content-Type: application/vnd.openxmlformats...
    ↓
Open php://output as write stream
    ↓
ExportService::streamFullExport(meetingId, outputStream)
    ↓
For each data chunk (50 rows):
    Repository query (paginated)
    ↓
    Write rows to stream writer (openspout or chunked PhpSpreadsheet)
    ↓
    ob_flush() — release memory for next chunk
    ↓
Close stream → response complete
```

### Email Queue Flow (after pagination fix)

```
Cron: bin/console email:process
    ↓
EmailQueueService::processQueue()
    ↓
Loop:
    SELECT * FROM email_queue WHERE status='pending' LIMIT 50
    ↓
    For each email: send via MailerService → mark sent
    ↓
    Repeat until query returns 0 rows
    ↓
    Sleep until next cron interval
```

## Architectural Patterns

### Pattern 1: Strangler Fig for Controller Extraction

**What:** Extract business logic method-by-method into service classes while keeping the controller signature unchanged. Old code and new service coexist briefly.

**When to use:** Any controller > 300 LOC with inline business logic.

**Trade-offs:** Requires writing tests before moving code. Slower than a rewrite. Much safer.

**Steps:**
1. Write characterization tests for the method being extracted (tests that assert current behavior, not ideal behavior)
2. Create new service class with the extracted logic
3. Replace controller method body with service call
4. Run tests — they must pass unchanged
5. Delete dead code (old inline logic)

### Pattern 2: Value Object for Request Context

**What:** Replace `AuthMiddleware` static variables with a `SessionContext` value object created per-request and injected where needed.

**When to use:** Any static state that should be request-scoped.

**Trade-offs:** Requires changing function signatures (e.g., `api_current_user_id()` global function becomes `$ctx->getUserId()`). Thread-safe. Testable without static reset.

**Example migration path:**
```php
// Before (static):
AuthMiddleware::$currentUserId = $user['id'];
// ... later:
$id = api_current_user_id(); // reads static

// After (injected):
$ctx = new SessionContext(userId: $user['id'], tenantId: ...);
// passed via constructor or request attribute
$id = $ctx->getUserId();
```

### Pattern 3: Repository Aggregation Query

**What:** Replace N sequential `COUNT(*)` queries with one query using `COUNT(*) FILTER (WHERE condition)`.

**When to use:** Any repository method that makes multiple queries to the same table to compute related statistics.

**Trade-offs:** More complex SQL, but eliminates N-1 round trips. Easier to cache (one cache key, not N).

### Pattern 4: Redis Key TTL as Health Signal

**What:** SSE server process writes `SETEX agvote:sse:heartbeat 10` every 5 seconds. Consumers check key existence instead of reading PID files.

**When to use:** Any liveness signal that crosses process boundaries.

**Trade-offs:** Requires SSE process to have Redis access (already true). TTL must be 2x the write interval. Stale key auto-expires — no manual cleanup needed.

## Anti-Patterns

### Anti-Pattern 1: Extracting Without Tests First

**What people do:** Move code to a new service class, then write tests.

**Why it's wrong:** Silent behavioral changes during extraction. Tests written after the fact test the new behavior, not whether it matches the old behavior. Bugs introduced during extraction go undetected.

**Do this instead:** Write characterization tests against the controller method before touching it. Move code. Run same tests.

### Anti-Pattern 2: Splitting Before Extracting

**What people do:** See `MeetingReportsController` at 727 LOC and immediately split into two controller files.

**Why it's wrong:** Splitting distributes debt across more files without reducing it. You now have two 360-LOC controllers with the same inline business logic problem.

**Do this instead:** Extract business logic into services first. Only split if the controller is still too large after extraction and serves genuinely distinct concerns.

### Anti-Pattern 3: Removing File Fallback Before Redis is Validated

**What people do:** Delete the file-based SSE queue in the same commit that adds the Redis queue.

**Why it's wrong:** If Redis is misconfigured in production, the entire SSE system fails with no fallback. Debugging a production outage with no fallback is painful.

**Do this instead:** Shadow-write to Redis alongside files for one release cycle. Validate Redis events are received correctly. Then remove file path.

### Anti-Pattern 4: Global statement_timeout Without Per-Route Awareness

**What people do:** Set `statement_timeout = 5s` globally in postgresql.conf.

**Why it's wrong:** Export queries and batch operations legitimately take longer. A global 5s timeout breaks valid long-running operations.

**Do this instead:** Set a permissive global default (e.g., `30s`), then override per-operation: `SET LOCAL statement_timeout = '300s'` inside long-running transactions.

## Integration Points

### External Services

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| PostgreSQL | PDO singleton per process | Add `ATTR_TIMEOUT` + `SET statement_timeout` at connect time |
| Redis | `RedisProvider` singleton | Already used for sessions; extend for rate limiting and SSE |
| Symfony Mailer | `MailerService` wrapper | Synchronous in-process; EmailQueueService batches calls |
| PhpSpreadsheet | `ExportService` | Replace full-load with streaming writer (openspout) |

### Internal Boundaries

| Boundary | Communication | Constraint |
|----------|---------------|------------|
| Controller → Service | Direct method call | Controller must not contain business logic |
| Service → Repository | Direct method call via `RepositoryFactory` | Services must not write raw SQL |
| Middleware → Controller | Router dispatch; middleware sets request context | AuthMiddleware sets `SessionContext`, not global statics |
| EventBroadcaster → SSE clients | Redis List (after migration) | One Redis key per meeting_id, TTL-bounded |
| Controller → EventBroadcaster | `EventDispatcher::dispatch()` | Synchronous in current architecture; keep synchronous |

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| Current (single server) | All changes in this milestone are valid and sufficient |
| 2-5 servers | Redis already handles rate limiting and SSE; PDO timeouts prevent cascading; no additional changes needed |
| 5+ servers + high concurrency | PgBouncer for connection pooling; read replicas for stats queries; separate SSE server process |

### Scaling Priorities

1. **First bottleneck (current):** File locks under concurrent requests — fixed by Redis migration (Phase 1).
2. **Second bottleneck:** Memory exhaustion during large exports — fixed by streaming (Phase 2).
3. **Third bottleneck:** PDO single connection under load — not addressed in this milestone; document for next milestone (PgBouncer).

## Sources

- PHP refactoring patterns: [Design Patterns in PHP 8.4: Refactoring Architecture Without Breaking Everything](https://medium.com/@mathewsfrj/design-patterns-in-php-8-4-part-7-refactoring-architecture-without-breaking-everything-03aeadc691f8) — MEDIUM confidence (Medium blog)
- Strangler Fig for PHP: [Modernizing PHP Legacy Apps: Strangler Fig, ACL & Parallel Models](https://devm.io/php/php-legacy-apps-modernize) — MEDIUM confidence
- Redis rate limiting: [Build 5 Rate Limiters with Redis: Algorithm Comparison Guide](https://redis.io/tutorials/howtos/ratelimiting/) — HIGH confidence (official Redis docs)
- PDO timeout configuration: [How to set connection timeout on PostgreSQL connection via PHP PDO pgsql driver](https://pracucci.com/php-pdo-pgsql-connection-timeout.html) — HIGH confidence (well-documented behavior)
- PostgreSQL statement_timeout: [PostgreSQL Documentation: statement_timeout](https://postgresqlco.nf/doc/en/param/statement_timeout/) — HIGH confidence (official docs)
- PostgreSQL multiple COUNTs: [How to Get Multiple Counts With Single Query in PostgreSQL](https://www.geeksforgeeks.org/postgresql/how-to-get-multiple-counts-with-single-query-in-postgresql/) — HIGH confidence (standard SQL)
- XLSX streaming: [openspout/openspout on GitHub](https://github.com/openspout/openspout) — MEDIUM confidence (verify current package status)
- SSE with Redis Pub/Sub: [Real Time Magic: SSE with Redis for Instant Updates](https://www.zerone-consulting.com/resources/blog/Real-Time-Magic-Harnessing-Server-Sent-Events-(SSE)-with-Redis-for-Instant-Updates/) — MEDIUM confidence
- Codebase analysis: `.planning/codebase/ARCHITECTURE.md`, `.planning/codebase/CONCERNS.md` — HIGH confidence (direct code audit)

---
*Architecture research for: AgVote PHP technical debt reduction*
*Researched: 2026-04-07*
