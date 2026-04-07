# Pitfalls Research

**Domain:** PHP 8.4 brownfield technical debt reduction — voting application (AgVote)
**Researched:** 2026-04-07
**Confidence:** HIGH (project-specific; grounded in CONCERNS.md + TESTING.md + verified community patterns)

---

## Critical Pitfalls

### Pitfall 1: Silently Breaking Behavior During Controller Extraction

**What goes wrong:**
When extracting business logic from a large controller (ImportController at 921 lines, AuthMiddleware at 871 lines) into a new service class, the extracted code appears to work under normal inputs but silently changes edge-case behavior. HTTP context leaks into the service (direct `$_POST`, `$_GET`, `$_FILES` access), error handling paths differ (exceptions vs. null returns), or side effects (audit logging, session state updates) are left behind in the controller instead of moving with the logic.

**Why it happens:**
Controllers accumulate behavior over time without clean boundaries. When you extract "the CSV parsing logic," you discover it also touches `$_FILES`, calls `audit_log()`, sets `$_SESSION` flash messages, and references `$this->someControllerProp`. These implicit dependencies are invisible until extraction breaks them.

**How to avoid:**
1. Write characterization tests against the current controller before touching it — capture what it actually does (status codes, response bodies, side effects) not what you think it does.
2. Extract into service with a thin adapter method on the controller that delegates completely — do not leave partial logic in both places.
3. Move ALL side effects (audit logging, session flash) into the service or handle them via return values, never split them across controller + service.
4. Follow the existing DI pattern: constructor injection with nullable parameters — `public function __construct(?ImportService $svc = null)` — so tests can inject mocks without touching globals.

**Warning signs:**
- The service constructor takes more than 4 dependencies (hidden orchestration still in the service)
- Service methods access `$_SERVER`, `$_SESSION`, `$_POST` directly
- Tests pass but the production endpoint returns different errors than before
- "It works in isolation" but fails when called from the controller

**Phase to address:**
Controller extraction phase — must precede any controller split decisions.

---

### Pitfall 2: AuthMiddleware Static State Surviving Between Tests

**What goes wrong:**
`AuthMiddleware` holds 10+ static properties (`$currentUser`, `$cachedSessionTimeout`, `$cachedTimeoutTenantId`, `$testSessionTimeout`, etc.). If any test calls `setAuth()` but teardown does not fully reset all statics, subsequent tests run with leaked auth state. The failure is non-deterministic: tests pass in isolation, fail in suite order, pass again after shuffling. The existing `ControllerTestCase::tearDown()` must reset every static — adding a new static to `AuthMiddleware` without updating teardown silently corrupts downstream tests.

**Why it happens:**
PHP process-level statics persist for the lifetime of the PHP-CLI process running PHPUnit. Each test shares the same memory space. `ControllerTestCase` explicitly resets known statics in teardown, but the list must be kept in sync manually — a brittle contract.

**How to avoid:**
1. When adding any new static to `AuthMiddleware`, immediately add its reset to `ControllerTestCase::tearDown()` in the same commit.
2. Create an explicit `AuthMiddleware::resetForTest()` method that resets ALL statics — single place to maintain instead of spread across test infrastructure.
3. Long term: the service extraction of AuthMiddleware (replacing statics with a request-scoped object) eliminates this entire class of problem.
4. Run the full test suite (`php vendor/bin/phpunit`) at the end of any AuthMiddleware change, even if individual file tests pass — order-dependent failures only appear in suite runs.

**Warning signs:**
- A test passes alone but fails when run after another specific test
- `setUp()` calls `AuthMiddleware::reset()` but the method does not exist yet
- A new `AuthMiddlewareTest` test causes unrelated `BallotsControllerTest` failures
- `$cachedSessionTimeout` is non-null at the start of a test that never set it

**Phase to address:**
AuthMiddleware refactoring phase; also enforce during any new test additions.

---

### Pitfall 3: Redis Removal of Fallbacks Crashing Dev Environments

**What goes wrong:**
Removing the file-based fallbacks from `EventBroadcaster` and `RateLimiter` makes Redis mandatory. Developers whose local setup does not have Redis running get immediate fatal errors or silent failures on startup — not a graceful "Redis is required" message. CI environments, Docker Compose setups, or staging deployments that rely on the file fallback break without warning.

**Why it happens:**
The fallback exists because Redis was not always guaranteed. Removing it without enforcing Redis presence at boot means the first code path that hits Redis will throw a `RedisException` or return `false` — and if that path has no Redis-specific error handling, the error surfaces as a generic 500 or is silently swallowed.

**How to avoid:**
1. Add a Redis health check in `Application::boot()` that fails fast with a clear error message if Redis is unavailable: `throw new \RuntimeException("Redis is required. Configure REDIS_URL or start Redis.")`.
2. Update `.env.example` and dev setup documentation before removing the fallbacks — document Redis as required, not optional.
3. Update Docker Compose and CI config to include Redis as a dependency before merging the fallback removal.
4. Remove fallbacks in a single atomic commit that includes the health check — never remove the fallback before the health check exists.

**Warning signs:**
- `REDIS_URL` is not set in `.env.example`
- `docker-compose.yml` has no Redis service
- `EventBroadcaster` or `RateLimiter` has a `try/catch` around Redis calls that silently falls back to files even after "removal"
- CI passes because tests mock Redis, but staging fails because Redis is not provisioned

**Phase to address:**
Redis mandatory migration phase — must be the first production-reliability task, before any other refactoring.

---

### Pitfall 4: Query Aggregation Replacement Introducing Lock Contention

**What goes wrong:**
Replacing 10 separate `COUNT(*)` queries in `MeetingStatsRepository` with a single aggregation query is correct in theory. In practice, the aggregation query may lock more rows for longer than 10 fast point queries, especially if the aggregation touches tables that are also written to during active voting. A dashboard stats query running a `GROUP BY` across `ballots`, `attendances`, and `motions` simultaneously can deadlock with a concurrent `FOR UPDATE` lock in `ProxyRepository` or `MeetingRepository`.

**Why it happens:**
The optimization is evaluated in isolation (query time improves) without considering concurrent write patterns. PostgreSQL's MVCC means read queries do not block writes in general — but certain aggregations combined with `FOR UPDATE` usage elsewhere create surprising lock ordering issues, especially if the aggregation also touches meeting-level rows.

**How to avoid:**
1. Test the aggregation query with `EXPLAIN ANALYZE` under realistic concurrent load — not just against an empty test database.
2. Keep the aggregation read-only and avoid touching the same row sets that `ProxyRepository::hasActiveProxyForVote()` and `MeetingRepository` quorum checks lock with `FOR UPDATE`.
3. If dashboard stats are acceptable with slight staleness, cache the aggregation result in Redis with a 30-60 second TTL rather than running it on every dashboard load.
4. Add a deadlock test: two concurrent transactions locking `meetings` and `ballots` in opposite order — verify no deadlock occurs after the aggregation is in place.

**Warning signs:**
- The aggregation query touches `meetings`, `ballots`, AND `attendances` in a single query without explicit ordering
- `EXPLAIN ANALYZE` shows `SeqScan` on large tables that previously used index-only scans
- Dashboard load time improves in dev but degrades under concurrent voting in staging
- PostgreSQL logs show `ERROR: deadlock detected` during vote-casting tests

**Phase to address:**
Query optimization phase — must include a concurrency review, not just a performance review.

---

### Pitfall 5: PDO Timeout Values That Cause False Test Failures

**What goes wrong:**
Adding `PDO::ATTR_TIMEOUT` and PostgreSQL `statement_timeout` is necessary for production reliability. If the timeout values are too low (e.g., 2 seconds for `statement_timeout`), integration tests that run against a real database on a slow CI machine hit the timeout during legitimate operations — the import test processing a 500-row CSV, or the export test generating a spreadsheet. The timeout fires, the test fails, and developers conclude the code is broken when the timeout is simply misconfigured.

**Why it happens:**
Timeout values are chosen for production (fast hardware, warmed caches, indexed data) and applied uniformly to test environments (slow CI, cold PostgreSQL, no indexes on test fixtures). The test failure looks identical to a real query hang, so developers waste time debugging the query instead of the timeout setting.

**How to avoid:**
1. Set `statement_timeout` at the session level, not as a permanent database-level setting — this allows tests to override via `SET LOCAL statement_timeout = 0` for known-slow operations.
2. Use environment-specific timeout values: production gets 5-10 seconds, CI/test gets 0 (disabled) or a generous 30 seconds.
3. Read the timeout from environment config (`QUERY_TIMEOUT_MS`), defaulting to a permissive value. Document that production must set this explicitly.
4. Never set `PDO::ATTR_TIMEOUT` below 10 seconds for unit/integration tests — this attribute controls TCP connection timeout, not query timeout, and a slow CI DNS resolution can exceed 2 seconds.

**Warning signs:**
- A test that passed for months starts failing intermittently on CI after the timeout PR merges
- The error is `SQLSTATE[HY000]: General error: 7 ERROR: canceling statement due to statement timeout`
- The same test passes locally (faster hardware) but fails on CI consistently
- Timeout value is hardcoded as a constant rather than read from environment

**Phase to address:**
Infrastructure reliability phase — timeout configuration must be tested on CI before merging, not just locally.

---

### Pitfall 6: ExportService Memory Fix Creating Worse Performance Regression

**What goes wrong:**
Fixing `ExportService`'s memory exhaustion by switching to chunked processing reduces peak memory but can dramatically increase total execution time if not implemented correctly. Common mistake: chunking by loading 100 rows at a time in a loop with a new PDO query per chunk — this replaces one memory problem with an N+1 query problem. If each chunk query is not using `LIMIT/OFFSET` with a covering index, query time grows linearly with meeting size and the export now times out at the HTTP layer instead of the memory layer.

**Why it happens:**
Memory pressure is immediately visible (fatal error). Query performance regression is gradual and only obvious with large datasets. Developers test the chunked version with 50-row fixtures, confirm memory is fixed, and ship it — the regression surfaces in production with a 5,000-row meeting export.

**How to avoid:**
1. Use PostgreSQL cursor-based streaming or keyset pagination (`WHERE id > $lastId ORDER BY id LIMIT 500`) rather than `LIMIT/OFFSET` — `OFFSET` performance degrades with page number.
2. Test with realistic data volumes in dev: generate a 5,000-member meeting fixture and verify both memory AND time are acceptable.
3. Set an HTTP-level timeout budget: if the export takes more than 30 seconds even after chunking, add a background job queue approach rather than synchronous HTTP response.
4. Profile `ExportService` before and after: measure peak memory AND total wall time for the same large fixture.

**Warning signs:**
- The chunking loop uses `OFFSET` pagination on a table without an index on the sort column
- Tests only use fixtures with fewer than 200 rows
- Export time is not measured, only memory usage
- The chunk size is hardcoded at a small value (10-20 rows) without justification

**Phase to address:**
Export service fix phase — include a performance benchmark as part of the acceptance criteria, not just memory usage.

---

### Pitfall 7: EmailQueueService Backpressure Creating Silent Email Loss

**What goes wrong:**
Adding backpressure to `EmailQueueService` by batching `processQueue()` means emails beyond the batch limit are left in the queue for the next run. If the scheduler is not configured correctly (cron interval too long, job fails silently, process kills after one batch), queued emails pile up and are never delivered. The system appears to work — no errors — but notification emails for vote results are hours late or never sent.

**Why it happens:**
Batching is implemented correctly at the code level but the operational requirement (how often the job runs, what happens on failure, how queue depth is monitored) is not addressed. The fix is incomplete without the operational context.

**How to avoid:**
1. Add queue depth logging: after each batch, log `email_queue depth: N remaining`. Alert if depth exceeds threshold.
2. Define the cron interval before implementing the batch size — if cron runs every 5 minutes and you process 50 emails per batch, the max throughput is 10 emails/minute. Verify this is sufficient.
3. Implement idempotency: if `processQueue()` is called concurrently (two cron jobs overlapping), emails must not be sent twice. Use a DB-level lock or `SELECT ... FOR UPDATE SKIP LOCKED`.
4. Test the failure case: if SMTP is unavailable, emails must stay in queue as `pending`, not be marked `sent` or `failed` permanently without retry logic.

**Warning signs:**
- `processQueue()` returns void with no indication of how many emails were processed
- No metric or log line shows queue depth after processing
- The batch size constant was chosen arbitrarily without calculating throughput requirements
- `FOR UPDATE SKIP LOCKED` is not used when claiming emails for processing

**Phase to address:**
Email queue backpressure phase — include operational documentation (cron setup, monitoring) in the same milestone, not as a followup.

---

### Pitfall 8: ImportController Temp File Cleanup "Fix" That Leaks on Fatal Errors

**What goes wrong:**
Adding `register_shutdown_function()` for temp file cleanup is correct for normal execution paths. However, if `ImportController` throws a fatal error, `E_ERROR`, or if the PHP process is killed by OOM, `register_shutdown_function()` may not fire. Additionally, if multiple concurrent imports each create temp files in `sys_get_temp_dir()`, a race condition in the current `tempnam()` + process approach can cause one upload's temp file to be deleted by another upload's shutdown handler if file naming is not sufficiently unique.

**Why it happens:**
`register_shutdown_function()` is documented as running on normal shutdown, including fatal errors in PHP 7+. Developers assume this is complete coverage — it is not complete for SIGKILL, OOM kills, or infrastructure-level container restarts. In production under load, container orchestrators kill PHP-FPM workers that exceed memory limits without going through PHP's shutdown sequence.

**How to avoid:**
1. Use a session-scoped temp directory (`sys_get_temp_dir() . '/agvote-import-' . session_id()`) rather than a flat file — this makes cleanup atomic (rmdir the whole directory) and avoids race conditions between concurrent uploads.
2. Add a maintenance cron that deletes temp directories older than 1 hour — handles the OOM kill scenario where shutdown function never fires.
3. Name temp files with both session ID and microsecond timestamp to guarantee uniqueness across concurrent requests.
4. Keep `register_shutdown_function()` as the primary cleanup, with the cron as the backstop — do not rely on only one mechanism.

**Warning signs:**
- Temp directory name only uses `tempnam()` output without embedding session ID
- No cron or cleanup job exists for stale temp files
- `/tmp` grows after a load test (cleanup is not running)
- Two concurrent import tests interfere with each other's temp files

**Phase to address:**
ImportController service extraction phase — fix temp file handling as part of the extraction, not separately.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Leave static state in AuthMiddleware, only add test reset | Faster to implement | Every new static property requires synchronized update in 3 places; test failures are order-dependent and confusing | Never — remove statics, use request-scoped object |
| Mock Redis in tests but keep file fallback in code | Tests pass without Redis | File fallback masks Redis connectivity problems in CI; production bug only visible in prod | Never — remove the fallback entirely once Redis is required |
| Chunk exports with OFFSET pagination | Simple to implement | `OFFSET N` scans all previous rows on each chunk; degrades exponentially on large tables | Never for large datasets — use keyset pagination |
| Set `statement_timeout` globally at DB level | One config change | Breaks long-running exports and imports that are legitimate; cannot be overridden per operation | Never — set at session level per operation |
| Extract service but keep test injection via protected property | Avoids changing controller signature | Creates invisible coupling between test infrastructure and production code shape | Only during migration window; remove after extraction complete |
| Skip characterization tests before refactoring | Faster to start refactoring | Cannot verify refactored behavior matches original; regressions go undetected | Never for controllers with complex branching (ImportController, AuthMiddleware) |

---

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| Redis (EventBroadcaster) | Remove fallback without adding Redis boot check | Add `Application::boot()` health check that throws descriptive exception before first Redis call |
| Redis (RateLimiter) | Use `INCR` + `EXPIRE` as two separate commands | Use `SET key 1 EX ttl NX` or Lua script — two commands are not atomic and race condition allows burst through rate limit |
| PostgreSQL `statement_timeout` | Set once as connection-level attribute, never reset | Use `SET LOCAL` inside transactions so timeout applies only to the specific operation |
| PDO connection (DatabaseProvider singleton) | Assume singleton PDO reconnects on dropped connection | Add ping/reconnect logic; singleton PDO does not auto-reconnect after idle timeout in PostgreSQL |
| EmailQueueService + SMTP | Mark email `sent` before SMTP `sendMail()` returns success | Only mark `sent` after confirmed SMTP success; on exception, mark `failed` with retry count |
| PhpSpreadsheet (ExportService) | Call `$spreadsheet->disconnectWorksheets()` after output | Not calling disconnect leaks circular references; PHP GC does not collect them promptly, causing memory to not return after export |

---

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| `MeetingStatsRepository` 10+ COUNT queries | Dashboard slow proportional to statistic count | Single aggregation query with `CASE` expressions; cache in Redis 30s TTL | 10+ concurrent dashboard loads (already at limit with current architecture) |
| ExportService full in-memory array before sheet creation | PHP OOM fatal on exports >5MB | Stream rows directly into PhpSpreadsheet writer; chunk at 500 rows | Meetings with 2,000+ attendances (likely with active associations) |
| `AuthMiddleware` DB revalidation every 60s per request | DB connection pool pressure under concurrent load | Cache revalidation result in Redis with 55s TTL; fast-path skip for same-tenant repeat | >20 concurrent authenticated users |
| EmailQueueService full queue load into memory | Memory exhaustion if queue backed up | `SELECT ... FOR UPDATE SKIP LOCKED LIMIT 50` cursor pattern | Queue depth >500 emails (realistic during meeting notification blast) |
| File-based rate limiter `flock()` serialization | Vote endpoints serialize under load; queue effect | Redis `SET key 1 EX ttl NX` atomic counter | >5 concurrent requests to same rate-limited endpoint |
| `tempnam()` flat-file temp files | Disk fills after OOM kills | Session-scoped temp directories + cron cleanup | Sustained load with imports or container restarts |

---

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| RGPD export with no test coverage | Data from other tenants included if scoping logic regresses; no regression detection | Add `RgpdExportControllerTest` with explicit tenant isolation assertions before ANY changes to export logic |
| File-based session storage in production | Sessions readable if `/tmp` is world-readable on shared host; session files not expired if PHP GC misconfigured | Migrate to Redis sessions with `session.save_handler = redis`; enforce TLS on Redis connection |
| Static `$currentUser` in AuthMiddleware under CLI | In CLI scripts running multiple "requests" (batch jobs), user from request N bleeds into request N+1 | Call `AuthMiddleware::reset()` at the start of every CLI iteration; better: remove static state entirely |
| `FOR UPDATE` lock without transaction assertion | Proxy race condition: lock not actually acquired if called outside transaction scope; concurrent vote can use same proxy twice | Add `assert($pdo->inTransaction())` at entry of any method that depends on `FOR UPDATE` |
| Hardcoded French enum translations in ExportService | If DB enum values change (migration), export silently outputs blank for unknown values — data integrity issue | Validate translation table completeness against DB enum definition at boot or in CI |

---

## "Looks Done But Isn't" Checklist

- [ ] **Redis fallback removal:** Verify `EventBroadcaster` has zero references to `/tmp/agvote-sse-*.json` AND that `Application::boot()` throws on missing Redis, AND that `docker-compose.yml` includes Redis, AND that CI has Redis in its service dependencies.
- [ ] **Controller extraction:** Verify the extracted service has no `$_POST`, `$_GET`, `$_FILES`, `$_SESSION`, or `$_SERVER` access. Verify the controller delegates entirely (no partial logic left). Verify characterization tests existed before extraction and still pass after.
- [ ] **Query aggregation:** Verify `EXPLAIN ANALYZE` shows index usage. Verify the aggregation query runs correctly under concurrent ballot inserts (use a PostgreSQL advisory lock test or pgbench). Verify result is cached.
- [ ] **PDO/statement timeout:** Verify timeout is read from environment, not hardcoded. Verify CI overrides to a permissive value. Verify exports and imports SET LOCAL to bypass or raise the timeout for their operation.
- [ ] **Email backpressure:** Verify queue depth is logged after each batch. Verify `FOR UPDATE SKIP LOCKED` is used. Verify emails are not marked `sent` before SMTP confirmation. Verify cron interval is documented.
- [ ] **AuthMiddleware refactor:** Verify `AuthMiddleware::resetForTest()` exists and resets ALL statics. Verify full test suite passes (not just AuthMiddlewareTest in isolation). Verify no new static properties were added without resetting them.
- [ ] **RGPD tests:** Verify `RgpdExportControllerTest` exists. Verify a test uses tenant B's auth to attempt accessing tenant A's export and asserts 403 or empty result.

---

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| Controller extraction broke behavior in production | HIGH | Revert extraction commit; redeploy previous version; add characterization tests against reverted code; re-extract with tests guiding each step |
| AuthMiddleware static leak causes auth bypass in production | CRITICAL | Redeploy with `AuthMiddleware::reset()` called at request start; add static reset to `Application::bootstrap()`; treat as security incident |
| Redis removal crashes production (Redis unreachable) | HIGH | Revert to last version with file fallback while Redis is provisioned; add monitoring before re-deploying Redis-mandatory version |
| Aggressive timeout kills legitimate exports in production | MEDIUM | `SET statement_timeout = 0` at session level for export path immediately; deploy environment-variable-driven timeout; set sane production value |
| Chunked export timeout loop creates duplicate emails on retry | HIGH | Add `operation_id` idempotency key to email queue inserts; on retry, skip already-queued emails for same operation |
| Test pollution causes CI to fail on valid code | LOW | Add `AuthMiddleware::reset()` to global bootstrap; run `--order random` to detect order-dependent failures; fix teardown |

---

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Silently breaking behavior during controller extraction | Controller extraction phase (after Redis migration) | Characterization tests must exist before extraction PR is opened |
| AuthMiddleware static state test pollution | AuthMiddleware refactoring phase | Full suite `php vendor/bin/phpunit` passes after every commit, not just isolated test file |
| Redis fallback removal crashing dev/CI environments | Redis mandatory migration phase (first phase) | CI pipeline includes Redis service; `docker-compose up` includes Redis; boot check logs clearly on missing Redis |
| Query aggregation creating lock contention | Query optimization phase | `pgbench` or concurrent transaction test runs without deadlock |
| PDO timeout causing false test failures | Infrastructure reliability phase (same phase as timeout addition) | CI environment sets `QUERY_TIMEOUT_MS=0`; timeout value documented in `.env.example` |
| Export chunking introducing performance regression | Export service fix phase | Acceptance test with 5,000-row fixture measures both memory AND wall time |
| EmailQueueService silent queue backup | Email backpressure phase | Queue depth logged; load test shows emails delivered within acceptable window |
| ImportController temp file leaks | ImportController extraction phase | Load test shows `/tmp` does not grow; OOM simulation confirms cron cleanup runs |

---

## Sources

- Project codebase analysis: `.planning/codebase/CONCERNS.md` (2026-04-07) — HIGH confidence, first-party
- Project codebase analysis: `.planning/codebase/TESTING.md` (2026-04-07) — HIGH confidence, first-party
- [How to Perform Extract Service Refactoring When You Don't Have Tests — Qafoo GmbH](https://qafoo.com/blog/099_extract_service_class.html) — MEDIUM confidence
- [PHPUnit worst practices — Victor Bolshov, Medium](https://medium.com/@crocodile2u/phpunit-worst-practices-4e3fe3b66fd7) — MEDIUM confidence
- [Process Isolation in PHPUnit — Matthew Turland](https://matthewturland.com/2010/08/19/process-isolation-in-phpunit/) — MEDIUM confidence
- [PostgreSQL statement_timeout documentation — postgresqlco.nf](https://postgresqlco.nf/doc/en/param/statement_timeout/) — HIGH confidence, official
- [12 Common Mistakes in SQL — Haki Benita](https://hakibenita.com/sql-dos-and-donts) — MEDIUM confidence
- [Slow Counting — PostgreSQL wiki](https://wiki.postgresql.org/wiki/Slow_Counting) — HIGH confidence, official
- [PHP PDO pgsql connection timeout — pracucci.com](https://pracucci.com/php-pdo-pgsql-connection-timeout.html) — MEDIUM confidence
- [Decoupling a monolithic PHP application — Lokalise Blog](https://lokalise.com/blog/decoupling-a-monolithic-php-application-a-practical-example/) — MEDIUM confidence

---

*Pitfalls research for: PHP 8.4 brownfield technical debt reduction — AgVote*
*Researched: 2026-04-07*
