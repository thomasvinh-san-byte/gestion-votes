# Feature Research

**Domain:** PHP technical debt reduction — brownfield MVC voting app (AgVote)
**Researched:** 2026-04-07
**Confidence:** HIGH (findings grounded in CONCERNS.md audit + verified against PHP ecosystem sources)

## Feature Landscape

### Table Stakes (Must Have or Codebase Degrades)

These are the minimum deliverables. Skipping any of them leaves production risk unchanged or actively worsens maintainability.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Redis-only SSE queue (remove file fallback) | File-based `/tmp` queue loses events on restart, hits race conditions under load, and caps at 10 concurrent meetings. Production stability is the milestone's core value. | MEDIUM | EventBroadcaster.php lines 206-273. Drop the flock path entirely — don't keep it as a fallback. |
| Redis-only rate limiting (remove file locks) | flock() on `/tmp` files serializes requests at exactly the high-frequency endpoints (vote.cast) where serialization is most harmful. | LOW | RateLimiter.php lines 198-234. Redis INCR + EXPIRE is the standard atomic pattern. |
| Redis heartbeat for SSE server detection (remove PID file) | Stale PID file causes false "server running" status; crashes go undetected. | LOW | Replace `/tmp/agvote-sse.pid` with a Redis key with TTL. Health check endpoint is bonus but not required for the table-stakes fix. |
| PDO connection timeout + PostgreSQL statement_timeout | No timeout means a slow query blocks the worker indefinitely. Under load this cascades into request queue saturation. | LOW | Two distinct concerns: PDO::ATTR_TIMEOUT for TCP connect (requires ERRMODE_EXCEPTION), SET statement_timeout per session for query-level cap. Both required. |
| Streaming XLSX export (fix memory exhaustion) | ExportService loads entire dataset before writing — crashes on meetings with thousands of attendances. This is an existing bug, not a future scaling concern. | HIGH | PhpSpreadsheet has a cell-caching mode but it is slow. Preferred path: adopt openspout/openspout (successor to box/spout) for true row-streaming. Falls back to chunked PhpSpreadsheet if dependency change is blocked. |
| EmailQueueService batch processing (fix unbounded memory load) | Loading entire queue into memory before SMTP calls causes OOM if queue backs up during a slow SMTP session. | MEDIUM | Process in configurable batches (default 25). Pagination via LIMIT/OFFSET on email_queue. Do not implement priority queuing in this milestone. |
| Aggregate MeetingStatsRepository COUNT queries | 10+ separate COUNT(*) per dashboard load is measurable latency, O(N) with statistic count. | MEDIUM | Single SQL query with CASE-WHEN aggregation. Cache result in Redis with short TTL (30–60s) if dashboard is polled frequently. |
| Extract business logic from ImportController | 921-line controller mixes HTTP orchestration, CSV column mapping, fuzzy matching, temp file management, and member upsert. Untestable as a unit. | HIGH | Extract to ImportService (fuzzy matching) + MemberUpsertService (DB writes). Controller becomes thin coordinator. Cleanup guarantee for temp files via register_shutdown_function(). |
| Extract business logic from AuthMiddleware | 10+ interdependent static variables make test isolation impossible and create tenant cross-contamination risk in CLI context. | HIGH | Convert static state to constructor-injected, request-scoped value object (AuthContext). Remove $testSessionTimeout injection pattern — tests use DI directly. |
| Tests for RgpdExportController | RGPD data export has zero test coverage despite being GDPR Article 20 critical path. Data scoping regression = data leak. | LOW | Test export scope (only requester's own data), unauthorized rejection, and response shape. ~3-5 test cases. |
| Tests for AuthMiddleware state transitions | SESSION_REVALIDATE_INTERVAL logic and cached timeout invalidation are untested. Any refactor of static state is blind without these. | MEDIUM | Must cover: initial cache miss, cache hit within TTL, cache invalidation on tenant switch, expired session path. Required before the static→DI refactor. |

### Differentiators (Competitive Advantage in Maintainability)

These go beyond preventing fires. They raise the floor for future development velocity.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Split MeetingReportsController after extraction | After business logic is in services, if controller remains >400 lines it should be split by report type (attendance vs. financial vs. motion summary). Reduces merge conflicts on the most-edited controller. | MEDIUM | Only worth doing after extraction is complete. Pre-extraction splitting just moves the mess. |
| Split MotionsController after extraction | Same rationale as MeetingReportsController. MotionsController mixes ballot state machine, motion CRUD, and vote orchestration. | MEDIUM | Depends on BallotsService extraction being stable first. |
| Tests for SSE race conditions (post-Redis) | After file fallback is removed, the remaining race is Redis consumer group behavior under concurrent SSE connections. Tests here prevent regression if EventBroadcaster is extended. | MEDIUM | Can only be written after Redis migration is complete. Test: event ordering, queue overflow behavior, broker reconnection. |
| Tests for ImportController fuzzy column matching | Silent data loss on non-standard CSV headers is a real user-facing bug. Fuzzy matching edge cases (case variants, accented characters, partial matches) are untested. | MEDIUM | Depends on ImportService extraction being done first — tests should target the service, not the controller. |
| ProxyRepository transaction assertion | FOR UPDATE locking silently does nothing when called outside a transaction. An assertion guard (throw if no active transaction) makes misuse visible immediately rather than as a race condition in production. | LOW | Small change, high signal. Document the transaction scope requirement in a docblock. |
| MeetingRepository deadlock prevention (lock ordering) | Concurrent quorum checks on multiple meetings can deadlock if lock acquisition order differs. Enforcing meeting_id ASC lock order is a one-line convention, but it needs a comment and ideally a test. | LOW | No dedicated deadlock test exists today. Add one to document the invariant. |
| Audit trail for session lifecycle | Session creation and expiry are not logged. This is a compliance gap (security audits cannot trace session lifecycle). | MEDIUM | Call audit_log() in SessionHelper::destroy(); log session timeout events with user_id and tenant_id. |

### Anti-Features (Explicitly Out of Scope for Debt Reduction)

These will be requested or tempting. Doing them during a debt reduction milestone is a trap.

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Framework migration (Symfony/Laravel) | Debt reduction suggests "use a real framework." | Rewrite risk is enormous. AgVote has custom RBAC, multi-tenant session logic, and SSE that do not map cleanly to framework conventions. A migration would create new debt faster than it removes old debt. | Incremental extraction to services + DI is the correct path. Re-evaluate framework adoption after the codebase has clear service boundaries. |
| Redis sessions (replace PHP $_SESSION) | Logical extension of "move everything to Redis." | PHP session files are not the production pain point identified in CONCERNS.md. The file-based SSE queue and rate limiter are. Adding Redis session migration expands scope without addressing the documented risks. | Keep PHP sessions as-is. Revisit if multi-node deployment becomes a requirement. |
| New business features (new vote types, new report formats) | Stakeholders see the milestone as "active development." | Adding features to code that is being structurally refactored causes merge conflicts, slows extraction, and defeats the stabilization goal. | Capture feature requests in a separate backlog. Block them from this milestone at planning. |
| Visual/UX regression fixes (v4.2 breakage) | Same codebase, same milestone window. | Already assigned to a separate milestone. Mixing frontend regressions with backend refactoring makes it impossible to bisect failures. | Parallel milestone or immediate successor. |
| PgBouncer / connection pooling | "We're fixing DB resilience, might as well pool." | Connection pooling is an infrastructure change that belongs in a DevOps/deployment milestone. It does not reduce PHP code debt. | Add to scaling backlog. Document the single-PDO-connection limit in CONCERNS.md as a known scaling boundary. |
| Email delivery confirmation / bounce handling | Reasonable feature, but not a debt item. | EmailQueueService marking emails "sent" without SMTP confirmation is a missing feature, not technical debt. It requires schema changes (delivery_status enum) and external webhook infrastructure. | Schedule as a standalone feature milestone after batch processing is stable. |
| 100% test coverage target | Sounds rigorous. | Chasing 100% coverage on a brownfield codebase creates pressure to write trivial tests for trivial code, burning time that should go to high-risk gaps (RGPD, AuthMiddleware). Industry consensus: 70-80% overall with 95%+ on critical paths. | Prioritize coverage on RGPD, AuthMiddleware, SSE, and import fuzzy matching. Leave low-risk getters/setters uncovered. |
| Encryption-at-rest for $_SESSION | Security consideration from CONCERNS.md. | Encrypting session data requires a key management strategy, migration of existing sessions, and careful handling of deserialization. This is a security hardening milestone item, not a debt item. | Log as a security backlog item. Verify session.gc_maxlifetime matches DEFAULT_SESSION_TIMEOUT (1800s) as the low-effort mitigation. |

## Feature Dependencies

```
Redis-only rate limiting
    └──enables──> Redis-only SSE queue (both require Redis to be mandatory, not optional)
                      └──enables──> SSE race condition tests (tests need stable Redis backend first)

AuthMiddleware tests
    └──must precede──> AuthMiddleware static→DI refactor
                           └──enables──> MeetingReportsController split (split only after logic is extracted)
                           └──enables──> MotionsController split

ImportController extraction
    └──must precede──> ImportController fuzzy matching tests (tests target the service, not controller)
    └──must precede──> MeetingReportsController split (extraction reveals true controller size)

PDO timeout
    └──independent of all other features (low-risk isolated change, do first)

RGPD tests
    └──independent (no code changes required, only test additions)

Aggregate COUNT queries
    └──independent (isolated repository change)

Streaming XLSX export
    └──independent (isolated service change, but HIGH complexity — sequence after quick wins)

EmailQueueService batching
    └──independent (isolated service change)
```

### Dependency Notes

- **Redis migration features are grouped:** SSE queue, rate limiter, and PID file all require Redis to become mandatory. They should be delivered as a single atomic phase so the "no fallback" invariant is established once.
- **AuthMiddleware tests must precede refactor:** The static state is the thing being changed. Tests written against current behavior define the contract that the refactored DI version must satisfy.
- **Import extraction must precede import tests:** Tests written against a 921-line controller test the wrong boundary. The service is the unit of behavior.
- **MeetingReportsController and MotionsController splits are post-extraction:** Splitting before extraction relocates complexity without reducing it. These are the last steps, not the first.

## MVP Definition

For this milestone, "MVP" means: production reliability is restored and the codebase is structurally ready for feature development.

### Launch With (Milestone Complete)

- [ ] Redis-only SSE queue — eliminates the highest-probability production crash
- [ ] Redis-only rate limiting — eliminates file lock serialization under load
- [ ] Redis heartbeat for SSE server detection — eliminates stale PID false positives
- [ ] PDO connection timeout + statement_timeout — eliminates silent hang risk
- [ ] EmailQueueService batch processing — eliminates OOM on queue backlog
- [ ] Streaming XLSX export — eliminates export crashes on large meetings
- [ ] Aggregate MeetingStatsRepository queries — eliminates O(N) dashboard queries
- [ ] Tests for RgpdExportController — closes the highest-risk compliance gap
- [ ] Tests for AuthMiddleware state transitions — required before static state refactor
- [ ] Extract ImportController business logic — makes import testable and maintainable
- [ ] Extract AuthMiddleware business logic (static→DI) — eliminates tenant cross-contamination risk

### Add After Core Extraction (Phase 2 of Milestone)

- [ ] Split MeetingReportsController — only meaningful after extraction reveals true size
- [ ] Split MotionsController — only meaningful after BallotsService is stable
- [ ] Tests for SSE race conditions — only valid after Redis migration is complete
- [ ] Tests for ImportController fuzzy matching — only valid after ImportService extraction

### Future Consideration (Next Milestone)

- [ ] Audit trail for session lifecycle — compliance improvement, not production stability
- [ ] ProxyRepository transaction assertion — guard rail, not a crash risk today
- [ ] MeetingRepository deadlock prevention — theoretical, no reported incidents
- [ ] Session timeout per-tenant configuration — scaling feature, current hardcoded value works

## Feature Prioritization Matrix

| Feature | Production Risk Reduction | Implementation Cost | Priority |
|---------|--------------------------|---------------------|----------|
| Redis-only SSE queue | HIGH | MEDIUM | P1 |
| Redis-only rate limiting | HIGH | LOW | P1 |
| Redis PID heartbeat | MEDIUM | LOW | P1 |
| PDO + statement_timeout | HIGH | LOW | P1 |
| EmailQueueService batching | HIGH | MEDIUM | P1 |
| RGPD tests | HIGH | LOW | P1 |
| AuthMiddleware tests | HIGH | MEDIUM | P1 |
| Streaming XLSX export | HIGH | HIGH | P1 |
| Aggregate COUNT queries | MEDIUM | MEDIUM | P1 |
| Extract ImportController | MEDIUM | HIGH | P1 |
| Extract AuthMiddleware (static→DI) | MEDIUM | HIGH | P1 |
| Split MeetingReportsController | LOW | MEDIUM | P2 |
| Split MotionsController | LOW | MEDIUM | P2 |
| SSE race condition tests | LOW | MEDIUM | P2 |
| Import fuzzy matching tests | MEDIUM | MEDIUM | P2 |
| Audit trail session lifecycle | LOW | MEDIUM | P3 |
| ProxyRepository transaction assertion | LOW | LOW | P3 |
| MeetingRepository deadlock prevention | LOW | LOW | P3 |

**Priority key:**
- P1: Required for milestone completion — production stability goal
- P2: Add within this milestone if P1 work completes ahead of schedule
- P3: Defer to next milestone — low production risk today

## Sources

- Project CONCERNS.md audit (2026-04-07) — primary source for issue identification
- [PhpSpreadsheet memory saving docs](https://phpspreadsheet.readthedocs.io/en/latest/topics/memory_saving/) — cell caching approach
- [Build 5 Rate Limiters with Redis](https://redis.io/tutorials/howtos/ratelimiting/) — INCR+EXPIRE atomic pattern, TOCTOU risk with non-atomic approaches
- [PHP PDO pgsql connection timeout](https://pracucci.com/php-pdo-pgsql-connection-timeout.html) — ATTR_TIMEOUT applies to TCP connect only; statement_timeout is separate
- [PHP-DI best practices](https://php-di.org/doc/best-practices.html) — request-scoped vs singleton service lifetimes
- [Why Static Methods in PHP Are Dangerous](https://www.maxpronko.com/php-static-methods-vs-dependency-injection/) — static state vs DI tradeoffs
- [PHPUnit code coverage guidance](https://docs.phpunit.de/en/10.5/code-coverage.html) — 70-80% overall, 95%+ on critical paths is industry norm
- [Streaming XLSX via openspout](https://medium.com/@turgutahmet/how-we-built-a-php-package-that-streams-4-5-2d09214d9439) — row-streaming alternative to PhpSpreadsheet

---
*Feature research for: PHP technical debt reduction — AgVote*
*Researched: 2026-04-07*
