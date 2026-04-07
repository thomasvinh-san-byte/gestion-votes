# Project Research Summary

**Project:** AgVote — PHP technical debt reduction
**Domain:** Brownfield MVC voting application (associations and collectivites)
**Researched:** 2026-04-07
**Confidence:** HIGH

## Executive Summary

AgVote is a PHP 8.4 / PostgreSQL / Redis voting management application for associations and collectivites. This milestone is not a rewrite — it is a targeted stabilization effort that eliminates specific fragile subsystems within the existing runtime. The core production risk is threefold: file-based infrastructure in `/tmp` (SSE queue, rate limiter, PID detection) that fails under concurrent load; memory-exhaustion bugs in ExportService and EmailQueueService that crash on realistic meeting sizes; and oversized controllers (ImportController at 921 lines, AuthMiddleware at 871 lines) that make the codebase untestable and tenant isolation unreliable.

The recommended approach is strictly incremental: harden infrastructure first (Redis-only, PDO timeouts), optimize data access second (streaming export, aggregated stats, batch email), then extract business logic into testable services. All three phases maintain identical external API surfaces. The fourth phase fills the test coverage gaps revealed by prior phases. This ordering is driven by production risk reduction priority: a file-lock race condition under load is worse than a fat controller; a memory crash during an export is worse than a missing stat aggregation. Nothing in the architecture changes fundamentally — the existing Controller/Service/Repository layering is sound and should be preserved.

The top risks in executing this milestone are: (1) silently changing behavior when extracting from large controllers — must be guarded by characterization tests written before any extraction; (2) AuthMiddleware static state polluting the test suite between runs — must be addressed before refactoring; (3) making Redis mandatory without updating dev/CI environments — must include a boot-time health check and Docker Compose update in the same commit. These three pitfalls account for the majority of potential regressions.

---

## Key Findings

### Recommended Stack

The stack is not changing. PHP 8.4, PostgreSQL 12+, and phpredis (already installed as a C extension) are the complete runtime. The only new dependency is `openspout/openspout ^5.6` (released March 2026, PHP 8.4-compatible) which provides true row-by-row XLSX streaming for under 3MB memory regardless of dataset size. PhpSpreadsheet is retained only for any sheet that genuinely requires formula evaluation or charting — all pure data exports migrate to OpenSpout.

All other changes use infrastructure already present: phpredis Pub/Sub and Lua eval replace file locks; PostgreSQL `FILTER` clauses replace multiple `COUNT(*)` round trips; session-level `statement_timeout` replaces the current absence of any query cap. No new message broker, no framework, no ORM.

**Core technologies:**
- PHP 8.4: Application runtime — already on latest stable, no upgrade needed
- PostgreSQL 12+ (target 16): Primary datastore — FILTER aggregation available since 9.4
- phpredis ^6.0: Redis client — C extension, XADD/XREAD/Pub/Sub/Lua eval, already installed
- Symfony Mailer ^8.0: SMTP delivery — already integrated, keep as-is
- openspout/openspout ^5.6: Streaming XLSX writer — only new dependency; replaces PhpSpreadsheet for tabular data exports

### Expected Features

Research confirmed the full feature scope from the CONCERNS.md audit. Every item in the table-stakes list represents an existing production bug or production risk — none are speculative improvements.

**Must have (table stakes — milestone incomplete without these):**
- Redis-only SSE queue (remove `/tmp` file fallback) — highest-probability production crash vector
- Redis-only rate limiting (remove `flock()` in RateLimiter) — serializes concurrent vote.cast requests
- Redis heartbeat for SSE server detection (remove PID file) — stale PIDs cause undetected SSE outages
- PDO connection timeout + PostgreSQL `statement_timeout` — no timeout means a runaway query blocks a worker indefinitely
- Streaming XLSX export via OpenSpout — existing OOM bug on meetings with thousands of attendances
- EmailQueueService batch processing — existing OOM risk when SMTP is slow and queue backs up
- Aggregate MeetingStatsRepository queries — O(N) COUNT queries per dashboard load, measurable latency
- Tests for RgpdExportController — zero coverage on GDPR Article 20 critical path, highest compliance risk
- Tests for AuthMiddleware state transitions — required before any static-state refactoring begins
- Extract ImportController business logic (921 lines) — makes import testable, eliminates HTTP context leaking into data logic
- Extract AuthMiddleware business logic (static → constructor DI) — eliminates tenant cross-contamination risk in CLI/FPM workers

**Should have (P2 — add within this milestone if P1 completes ahead of schedule):**
- Split MeetingReportsController after extraction — only meaningful once service extraction reveals true controller size
- Split MotionsController after extraction — depends on BallotsService being stable
- Tests for SSE race conditions — only valid after Redis migration is complete
- Tests for ImportController fuzzy column matching — only valid after ImportService extraction

**Defer (P3 — next milestone):**
- Audit trail for session lifecycle — compliance improvement, not production stability
- ProxyRepository transaction assertion guard — guard rail, no reported incidents yet
- MeetingRepository deadlock prevention tests — theoretical, no deadlock reports
- Session timeout per-tenant configuration — current hardcoded 1800s value works

**Explicit anti-features (must not enter this milestone):**
- Framework migration (Symfony/Laravel) — rewrite risk vastly exceeds debt removal benefit
- Redis sessions replacing `$_SESSION` — not identified as a pain point in CONCERNS.md
- New business features (new vote types, new report formats) — mixing features with structural refactoring defeats stabilization goal
- Frontend/UX regression fixes from v4.2 — belongs in separate parallel milestone
- PgBouncer / connection pooling — DevOps/infrastructure milestone, not PHP debt

### Architecture Approach

The existing layered architecture (Router → Middleware → Controller → Service → Repository → PDO) is correct and must not change. Refactoring work is internal to each layer — controllers become thin HTTP adapters, services hold all business logic, repositories hold all SQL. The inversion rules are absolute: repositories never call services, services never call controllers, controllers never call other controllers. The `Strangler Fig` pattern governs all extractions: move code block-by-block with tests before and after each move, never rewrite.

**Major components and their debt status:**
1. `AuthMiddleware` (871 lines, 10+ static vars) — critical debt; convert statics to `SessionContext` value object
2. `RateLimitGuard` (file locks in `/tmp`) — critical debt; replace with Redis atomic `INCR + EXPIRE` via Lua eval
3. `ImportController` (921 lines, inline business logic) — critical debt; extract to `ImportService`, `MemberUpsertService`
4. `EventBroadcaster` (file queue + PID file) — critical debt; replace with Redis List (RPUSH/BLPOP) + TTL heartbeat key
5. `ExportService` (full in-memory load) — critical debt; replace with OpenSpout row-by-row streaming
6. `DatabaseProvider` (no timeouts) — critical debt; add `ATTR_TIMEOUT` + `SET statement_timeout` at connect
7. `MeetingStatsRepository` (10+ COUNT queries) — moderate debt; single `COUNT(*) FILTER (WHERE ...)` aggregation
8. `EmailQueueService` (full queue in memory) — moderate debt; Redis `LPOP key count` batch processing
9. `MeetingReportsController` (727 lines), `MotionsController` (720 lines) — moderate debt; split after extraction only
10. All other repositories, BallotsService, QuorumEngine — clean, no changes needed

### Critical Pitfalls

1. **Silently breaking behavior during controller extraction** — Write characterization tests against the controller before any extraction. Move ALL side effects (audit logging, session flash) into the service — never split them across controller + service. Warning sign: service constructor takes more than 4 dependencies.

2. **AuthMiddleware static state surviving between tests** — Add `AuthMiddleware::resetForTest()` method that resets ALL statics. Run full test suite after every AuthMiddleware change, not just the isolated test file — order-dependent failures only appear in suite runs.

3. **Redis fallback removal crashing dev/CI environments** — Add a Redis health check in `Application::boot()` that throws a descriptive `RuntimeException` if Redis is unavailable. Remove fallbacks in a single atomic commit that includes the health check, Docker Compose update, and CI service dependency update.

4. **Export chunking introducing performance regression** — Use keyset pagination (`WHERE id > $lastId ORDER BY id LIMIT 500`), never `LIMIT/OFFSET` — `OFFSET` performance degrades exponentially with page number. Test with a 5,000-member fixture measuring both memory AND wall time.

5. **PDO timeout values causing false CI failures** — Read timeout from environment variable (`QUERY_TIMEOUT_MS`), never hardcode. Set CI to override with a permissive value (0 or 30s). Timeout value in `.env.example` is required documentation.

6. **EmailQueueService silent queue backup** — Use `SELECT ... FOR UPDATE SKIP LOCKED` when claiming emails. Log queue depth after each batch. Define cron interval before choosing batch size — verify the throughput math (batch size ÷ interval = emails/minute).

7. **ImportController temp file leaks on OOM kill** — `register_shutdown_function()` does not fire on SIGKILL or OOM container restart. Use session-scoped temp directories and add a maintenance cron that deletes directories older than 1 hour as a backstop.

8. **Query aggregation creating lock contention** — Keep the aggregation query read-only and avoid touching row sets that `ProxyRepository` and `MeetingRepository` lock with `FOR UPDATE`. Cache result in Redis with 30-60s TTL to reduce contention frequency.

---

## Implications for Roadmap

Based on combined research, four phases with a clear dependency ordering.

### Phase 1: Infrastructure Hardening

**Rationale:** Production crashes are worse than code structure problems. File-based infrastructure is the highest-probability failure vector. Making Redis mandatory first establishes the foundation that phases 2 and 3 depend on. These changes have no API surface impact — each can be merged independently. Lowest-risk phase despite addressing highest-severity issues.

**Delivers:** Redis-only rate limiting, Redis SSE queue, Redis PID heartbeat, PDO connection and statement timeouts. All `/tmp`-based fragility eliminated. Redis becomes a declared hard dependency.

**Addresses (from FEATURES.md):** Redis-only SSE queue (P1), Redis-only rate limiting (P1), Redis heartbeat (P1), PDO + statement_timeout (P1)

**Pitfalls to avoid:** Redis fallback removal without boot check (Pitfall 3), PDO timeout values causing CI failures (Pitfall 5)

**Research flag:** Standard patterns — Redis INCR+Lua eval, Redis List BLPOP, `SET statement_timeout` at session level are all well-documented. No additional research needed.

---

### Phase 2: Memory and Query Optimizations

**Rationale:** After infrastructure is stable, eliminate the OOM crash vectors. These changes are internal to services and repositories — controller signatures unchanged, no extraction complexity. OpenSpout streaming and email batching are isolated service changes. Stats aggregation is an isolated repository change. Sequence after Phase 1 so the team is not simultaneously managing Redis migration and memory fixes.

**Delivers:** Streaming XLSX export via OpenSpout (memory under 3MB regardless of dataset size), EmailQueueService batch processing (LPOP with count, FOR UPDATE SKIP LOCKED), MeetingStatsRepository single aggregation query with Redis cache.

**Addresses (from FEATURES.md):** Streaming XLSX export (P1), EmailQueueService batching (P1), Aggregate COUNT queries (P1)

**Stack additions:** `composer require openspout/openspout` — only new dependency in the entire milestone.

**Pitfalls to avoid:** Export chunking performance regression (Pitfall 6 — use keyset pagination, test with 5,000-row fixture), EmailQueueService silent backup (Pitfall 7 — log depth, document cron interval), Query aggregation lock contention (Pitfall 8 — cache result, avoid FOR UPDATE overlap)

**Research flag:** Standard patterns for all three items. OpenSpout streaming API verified against official docs. No additional research needed.

---

### Phase 3: Service Extraction and Static State Elimination

**Rationale:** Only after infrastructure is stable and memory bugs are fixed does structural refactoring proceed. This ordering ensures that regressions during extraction are bisectable — a new bug discovered post-phase-3 cannot be confused with a Redis or memory issue. AuthMiddleware tests must be written before any refactoring begins in this phase.

**Delivers:** Tests for AuthMiddleware state transitions (characterization tests that define the contract), Extract AuthMiddleware statics → `SessionContext` value object, Extract ImportController → `ImportService` + `MemberUpsertService` + `ImportValidationService` (target: controller under 150 lines), Tests for RgpdExportController (GDPR compliance gap closed).

**Addresses (from FEATURES.md):** Tests for AuthMiddleware (P1), Extract ImportController (P1), Extract AuthMiddleware static→DI (P1), Tests for RgpdExportController (P1)

**Architecture patterns:** Strangler Fig (extract method-by-method with characterization tests), Value Object for request context (SessionContext replaces 10+ static vars), constructor DI with nullable parameters (matches existing CLAUDE.md constraint)

**Pitfalls to avoid:** Silently breaking behavior during extraction (Pitfall 1 — characterization tests before extraction), AuthMiddleware static state test pollution (Pitfall 2 — resetForTest() method, full suite run), ImportController temp file leaks on OOM (Pitfall 8 — session-scoped temp dirs + maintenance cron)

**Research flag:** Needs attention during planning. The AuthMiddleware static-to-DI refactor is the highest-risk change in the entire milestone. The extraction sequence matters: tests first, then introduce SessionContext, then migrate static getters one by one, then verify full suite. The roadmapper should allocate more tasks to this phase and sequence them conservatively.

---

### Phase 4: Test Coverage and Controller Splits

**Rationale:** P2 features from FEATURES.md that depend on prior phases being complete. SSE race condition tests are only valid after Redis migration (Phase 1). Import fuzzy matching tests are only valid after ImportService extraction (Phase 3). Controller splits are only meaningful after extraction reveals true controller size.

**Delivers:** Tests for SSE race conditions (event ordering, queue overflow, broker reconnection), Tests for ImportController fuzzy column matching edge cases (case variants, accented chars, partial matches), Split MeetingReportsController if still over 400 lines after extraction, Split MotionsController if BallotsService extraction is stable.

**Addresses (from FEATURES.md):** SSE race condition tests (P2), Import fuzzy matching tests (P2), MeetingReportsController split (P2), MotionsController split (P2)

**Research flag:** Standard patterns. No additional research needed for test writing or controller splits.

---

### Phase Ordering Rationale

- Infrastructure before code structure: a crash in production is irreversible in a way that a fat controller is not. Redis migration unblocks all file-lock problems in one phase.
- Memory bugs before extraction: OOM crashes are reproducible and verifiable. Extracting from a service that still crashes on large inputs produces misleading test results.
- Characterization tests before extraction: this is not optional. PITFALLS.md documents multiple ways extraction fails silently without pre-extraction tests. AuthMiddleware is explicitly identified as the highest-risk extraction.
- Splits only after extraction: MeetingReportsController and MotionsController splits done pre-extraction just redistribute complexity. ARCHITECTURE.md and FEATURES.md both independently flag this.
- Redis features are atomic: SSE queue, rate limiter, and PID heartbeat must be delivered together so the "Redis is mandatory" invariant is established once, not incrementally.

### Research Flags

Phases needing deeper research during planning:
- **Phase 3 (Service Extraction):** AuthMiddleware has 10+ interdependent statics, 871 lines, and complex tenant/session caching behavior. The roadmapper should plan individual tasks at fine granularity (one static at a time, not "refactor AuthMiddleware"). Consider whether `api_current_user_id()` global functions need to stay for backward compatibility — this was not fully resolved in research.

Phases with standard patterns (skip research-phase):
- **Phase 1 (Infrastructure):** Redis INCR+Lua, Redis List, `statement_timeout` — all verified against official docs with HIGH confidence.
- **Phase 2 (Memory/Query):** OpenSpout streaming API, keyset pagination, `FOR UPDATE SKIP LOCKED` — all verified against official docs or package documentation.
- **Phase 4 (Tests/Splits):** Standard PHPUnit patterns; controller splits are mechanical once extraction is complete.

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All technologies verified against official docs and Packagist. openspout ^5.6 confirmed PHP 8.4 compatible, v5.6.0 released March 2026. phpredis LPOP count param requires Redis 6.2+ — verify Docker Compose Redis version before implementing. |
| Features | HIGH | Grounded in CONCERNS.md first-party codebase audit. Feature scope is concrete and bounded. Anti-features list is explicit and protects milestone focus. |
| Architecture | HIGH | Based on direct codebase analysis. Refactoring patterns (Strangler Fig, Value Object for context) are well-established PHP patterns. Inversion rules are already present in the codebase's existing structure. |
| Pitfalls | HIGH | Eight pitfalls all grounded in specific files and line numbers from the existing codebase. Recovery strategies are specific, not generic. The "looks done but isn't" checklist is directly actionable. |

**Overall confidence:** HIGH

### Gaps to Address

- **Redis version in docker-compose:** `LPOP key count` requires Redis 6.2+. Verify before Phase 1 implementation. If Redis is older, the email batching pattern needs adjustment (LRANGE + LTRIM instead of LPOP count).
- **Global function compatibility for `api_current_user_id()`:** ARCHITECTURE.md notes these global functions read from static state. After AuthMiddleware refactor, they need to read from a request-scoped SessionContext. The mechanism for passing SessionContext to global function scope was not fully specified in research — this is the main open design question for Phase 3.
- **ExportService PhpSpreadsheet audit:** STACK.md recommends keeping PhpSpreadsheet for sheets with formula/chart requirements, but does not enumerate which existing export sheets (if any) actually use these features. ExportService should be audited sheet-by-sheet before deciding what to migrate vs. keep. This audit belongs at the start of Phase 2.
- **openspout DOM extension dependency:** Requires DOM, Filter, LibXML, XMLReader, Zip extensions. Research confirms these are present in the current Dockerfile — verify before merging the OpenSpout install to avoid CI breakage.

---

## Sources

### Primary (HIGH confidence)
- Project CONCERNS.md (2026-04-07) — primary source for feature scope and issue identification
- Project TESTING.md (2026-04-07) — AuthMiddleware test isolation constraints
- [Redis official docs — rate limiting tutorial](https://redis.io/tutorials/howtos/ratelimiting/) — Lua eval atomicity, INCR+EXPIRE patterns
- [Redis official docs — Lists](https://redis.io/docs/latest/develop/data-types/lists/) — LPOP count param, Redis 6.2+ requirement
- [PostgreSQL official docs v18 — statement_timeout](https://www.postgresql.org/docs/current/runtime-config-client.html) — per-session timeout configuration
- [openspout/openspout on Packagist](https://packagist.org/packages/openspout/openspout) — v5.6.0 release, PHP 8.4 constraint confirmed
- [Crunchy Data — control runaway queries](https://www.crunchydata.com/blog/control-runaway-postgres-queries-with-statement-timeout) — SET LOCAL pattern
- phpredis GitHub — XADD, XREAD, lPop count API surface

### Secondary (MEDIUM confidence)
- [PDO pgsql connection timeout — pracucci.com](https://pracucci.com/php-pdo-pgsql-connection-timeout.html) — ATTR_TIMEOUT TCP-only scope (verified against official docs)
- [PostgreSQL multiple COUNTs — GeeksforGeeks](https://www.geeksforgeeks.org/how-to-get-multiple-counts-with-single-query-in-postgresql/) — FILTER vs CASE WHEN (cross-verified with PostgreSQL docs)
- [OpenSpout streaming pattern — Medium](https://medium.com/@turgutahmet/how-we-built-a-php-package-that-streams-4-5-2d09214d9439) — row-streaming API usage
- [SSE with Redis — zerone-consulting.com](https://www.zerone-consulting.com/resources/blog/Real-Time-Magic-Harnessing-Server-Sent-Events-(SSE)-with-Redis-for-Instant-Updates/) — SSE + Redis List pattern
- [PHP static methods vs DI — maxpronko.com](https://www.maxpronko.com/php-static-methods-vs-dependency-injection/) — static state tradeoffs
- [Strangler Fig for PHP — devm.io](https://devm.io/php/php-legacy-apps-modernize) — Strangler Fig extraction pattern

### Tertiary (LOW confidence — no blocking gaps)
- [Redis Streams in PHP — patriqueouimet.ca](https://patriqueouimet.ca/post/messaging-php-and-redis-streams) — XADD/XREAD practical PHP example; use only if SSE event replay is required

---

*Research completed: 2026-04-07*
*Ready for roadmap: yes*
