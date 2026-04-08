# Milestones

## v1.1 Coherence UI/UX et Wiring (Shipped: 2026-04-08)

**Phases completed:** 3 phases, 11 plans

**Key accomplishments:**

- JS audit + wiring repair: 1,269 querySelector calls audited, 1 confirmed mismatch fixed (vote.js voteButtons), 5 dead-code blocks removed for v4.2 orphan selectors, sidebar async timing hardened with sidebar:loaded event, waitForHtmxSettled() Playwright helper created
- Login redesigned 2x: first 50/50 then 40/60 brand panel with hero title, 3 feature highlights, trust pills, gradient + animated glow, all coherent with project tokens
- Design tokens uniformly applied: 7 raw color literals replaced across operator/settings/report/vote/audit.css, @layer pages cascade declared, 5 badge defects fixed (hub BEM, QuorumController PHP)
- HTMX skeleton-row loading wired into operator/members/meetings list containers, pv_sent badge entry added
- Playwright upgraded to 1.59.1 with @axe-core/playwright + per-page accessibility audits (7 pages)
- 22 networkidle calls removed from 6 spec files; new page-interactions.spec.js (8 tests, 7 pages) and operator-e2e.spec.js (full workflow with hybrid API+UI strategy)
- 3 critical hotfixes delivered: RateLimiter::configure() boot regression (v1.0 cleanup leftover blocked all API requests), nginx clean URL routing (multi-week regression that served index.html instead of htmx.html for /dashboard, /meetings, /hub), login redesign polish

**Tech debt carried to v1.2:** Browser test execution requires installing libatk system libraries or running in Docker. 11 human verification items deferred. Phase 5 + Phase 6 nyquist_compliant flag never flipped. Backend tech debt from v1.0 still pending (getDashboardStats wiring, MeetingReports/Motions controller split).

---

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
