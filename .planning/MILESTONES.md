# Milestones

## v1.0 Dette Technique (Shipped: 2026-04-07)

**Phases completed:** 4 phases, 11 plans, 18 tasks

**Key accomplishments:**

- Application now fails fast with French error when Redis is unreachable; RateLimiter uses atomic Lua EVAL instead of PIPELINE, with all file-based fallback code deleted
- EventBroadcaster stripped of all file-based fallback code; SSE server detection replaced with Redis TTL heartbeat (sse:server:active); events.php writes heartbeat each loop iteration
- PDO::ATTR_TIMEOUT=10 and configurable statement_timeout added to all DB connections; 12-metric getDashboardStats() consolidates 11+ COUNT queries into a single SQL round-trip via scalar subqueries
- AbstractRepository.selectGenerator()
- Replace unbounded member table loads in EmailQueueService with LIMIT/OFFSET paginated batches of 25, preventing OOM for large associations (500+ members)
- 1. [Constraint - Test Infrastructure] api_require_role() stubbed as no-op in bootstrap
- ImportController reduced 67% (921 to 303 lines) by extracting all business logic into ImportService with nullable DI constructor and 4 typed process methods
- ImportController reduced to 149 lines with zero delegation wrappers; ImportService processMemberImport verified with 5 mock-RepositoryFactory integration tests; TEST-01 and TEST-02 marked complete
- EventBroadcasterTest extended with 6 Redis-integration tests covering event ordering, atomic dequeue, consumer fan-out, tenant event isolation, heartbeat TTL expiry, and queue trim limit — closing TEST-03.
- 49-test ImportServiceTest covering all accented aliases (ponderation/pondération, prenom/prénom, tantiemes/tantièmes, etc.) across 4 column maps plus readCsvFile header normalization edge cases
- SC1 gap closed: EventBroadcasterTest extended with structural Redis connection loss proof + client reconnect buffer/drain/re-buffer cycle, TEST-04 documentation lag fixed

---
