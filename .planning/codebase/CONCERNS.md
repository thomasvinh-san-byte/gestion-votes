# Codebase Concerns

**Analysis Date:** 2026-04-07

## Tech Debt

**Oversized controllers — HTML report generation in MeetingReportsController:**
- Issue: `MeetingReportsController` (727 lines) contains 5 public methods that each build raw HTML using heredoc strings and inline concatenation, then call `header('Content-Type: text/html')` directly. The `report()` method alone spans ~310 lines. Only `exportPvHtml()` delegates to `MeetingReportService`; the other 4 methods (`report()`, `generatePdf()`, `generateReport()`, `sendReport()`) duplicate template logic and bypass the `HtmlView` abstraction.
- Files: `app/Controller/MeetingReportsController.php`
- Impact: Rendering bugs must be fixed in multiple places. Adding a new section (e.g., digital signature block) requires changes in 3 methods.
- Fix approach: Extract report-building logic into `MeetingReportService` (already started — `renderHtml()` exists at line 38). Route all 4 HTML methods through it. Move inline CSS/HTML to `app/Templates/`.

**Oversized controller — MotionsController:**
- Issue: `MotionsController` (720 lines) handles 10 endpoint methods including the full vote lifecycle (`open()`, `close()`, `tally()`, `degradedTally()`, `overrideDecision()`). The `open()` method spans ~70 lines with a transaction, SSE broadcast, and policy validation inline.
- Files: `app/Controller/MotionsController.php`
- Impact: Single-responsibility violation — testing individual voting operations requires exercising the entire controller.
- Fix approach: Extract vote lifecycle (open/close/degraded tally/override) into a `MotionWorkflowService` following the pattern used by `MeetingWorkflowService`.

**Oversized controllers — MeetingsController, MeetingWorkflowController, OperatorController:**
- Issue: `MeetingsController` (687 lines) mixes CRUD, stats aggregation, validation, and report snapshot persistence. `MeetingWorkflowController` (559 lines) handles transitions, launch, consolidation, and demo reset in one class. `OperatorController` (516 lines) handles the full operator dashboard state query plus token generation.
- Files: `app/Controller/MeetingsController.php`, `app/Controller/MeetingWorkflowController.php`, `app/Controller/OperatorController.php`
- Impact: Increases cognitive load when tracing bugs; stat methods call 5–8 repo methods directly.
- Fix approach: Extract stats queries to `MeetingStatsService`; extract consolidation to `MeetingConsolidationService`.

**getDashboardStats() unused in production:**
- Issue: `MeetingStatsRepository::getDashboardStats()` (lines 154–196) is a single-query aggregate that fetches 12 stats in one SQL call. `DashboardController` never calls it — it calls 8 separate repository methods (`countNotDeleted`, `sumNotDeletedVoteWeight`, `dashboardSummary`, `countActive`, `countForMotion`, etc.). `getDashboardStats()` is only referenced in `MeetingStatsRepositoryTest`.
- Files: `app/Repository/MeetingStatsRepository.php` (line 154), `app/Controller/DashboardController.php`
- Impact: Each dashboard load issues 5–8 separate queries instead of 1. Under concurrent operator dashboards this multiplies read load on PostgreSQL.
- Fix approach: Wire `DashboardController::index()` to call `getDashboardStats()` and remove the individual per-stat calls. The method is already tested.

**N+1 queries in report generation — per-motion policy + service instantiation in loops:**
- Issue: In `MeetingReportsController::report()` (lines 73–128) and `MeetingReportService::renderHtml()` (lines 140–197), a `foreach` over `$motions` calls `policyRepo->findVotePolicy()` and `policyRepo->findQuorumPolicy()` once per motion. For a 20-motion meeting this is 40 individual queries. Additionally, `new OfficialResultsService()` and `new VoteEngine()` are instantiated inside the loop per motion.
- Files: `app/Controller/MeetingReportsController.php` (lines 73–128), `app/Services/MeetingReportService.php` (lines 140–197)
- Impact: A 30-motion meeting generates 60+ policy queries on report load. Service instantiation inside loops wastes constructor overhead.
- Fix approach: Collect all unique `vote_policy_id` and `quorum_policy_id` from motions before the loop, load them in one batch, then look up from a local array. Instantiate `OfficialResultsService` and `VoteEngine` once before the loop.

**N+1 in DashboardController ready_to_sign check:**
- Issue: `DashboardController::index()` (lines 109–122) calls `ballotRepo->countForMotion()` once per closed motion in a `foreach` loop. For a 15-motion meeting this is 15 `SELECT COUNT(*)` queries in sequence.
- Files: `app/Controller/DashboardController.php` (lines 109–122)
- Impact: Dashboard load latency scales linearly with closed motion count.
- Fix approach: Add `MeetingStatsRepository::countBallotsByMeeting()` returning a map of `motion_id → ballot_count` in one query; replace the loop.

**N+1 in OperatorController::openVote() token generation:**
- Issue: `OperatorController::openVote()` (lines 281–317) calls `tokenRepo->findActiveForMember()` inside a `foreach` over `$eligible` members. For a 200-member meeting this is 200 individual SELECT queries before inserting tokens — all inside a transaction.
- Files: `app/Controller/OperatorController.php` (lines 281–317)
- Impact: Token generation for 200 members issues 200+ queries while holding a transaction lock.
- Fix approach: Preload existing active tokens for the meeting+motion via a batch query `listActiveMemberIds(tenantId, meetingId, motionId)` returning a set, then skip members found in the set.

**Hardcoded session timeout in events.php:**
- Issue: `public/api/v1/events.php` (line 40) hardcodes the session timeout as `1800` seconds (`(time() - $lastActivity) > 1800`), duplicating `AuthMiddleware::DEFAULT_SESSION_TIMEOUT`. If a tenant configures a custom timeout, the SSE endpoint ignores it.
- Files: `public/api/v1/events.php` (line 40)
- Impact: SSE connections from tenants with non-default timeouts expire at the wrong time (too early or too late).
- Fix approach: Replace the literal `1800` with `AuthMiddleware::getSessionTimeout()`.

**Heartbeat Redis key string literal in events.php vs private const in EventBroadcaster:**
- Issue: `public/api/v1/events.php` (line 169) writes `$redis->set('sse:server:active', '1', ['EX' => 90])` as a hardcoded string. `EventBroadcaster::HEARTBEAT_KEY` (line 19) holds the same value as a `private` constant — inaccessible from `events.php`.
- Files: `public/api/v1/events.php` (line 169), `app/SSE/EventBroadcaster.php` (line 19)
- Impact: Changing the Redis key requires updating both files independently; any mismatch silently breaks `EventBroadcaster::isServerRunning()`.
- Fix approach: Change `HEARTBEAT_KEY` visibility to `public` and reference it as `EventBroadcaster::HEARTBEAT_KEY` in `events.php`.

**Service instantiation via `new` inside controllers (bypasses DI):**
- Issue: 17 `new ServiceClass()` calls appear inside controller methods. `new OfficialResultsService()`, `new VoteEngine()`, `new MailerService(...)`, `new MeetingReportService()`, `new Dompdf()` are scattered across multiple controllers. Most are inside conditional branches or loops.
- Files: `app/Controller/MeetingReportsController.php` (lines 92, 120, 507, 632), `app/Controller/MotionsController.php` (line 525), `app/Controller/BallotsController.php` (line 230), `app/Controller/MeetingWorkflowController.php` (line 501), `app/Controller/EmailController.php` (lines 129, 246), `app/Controller/AdminController.php` (line 386)
- Impact: Unit testing requires hitting real dependencies; heavy services like DOMPDF instantiated per-method call.
- Fix approach: Inject services via constructor with nullable params (`?OfficialResultsService $service = null`) following the established DI pattern in `ImportController`.

**Inline HTML rendering in API controller base class:**
- Issue: `MeetingReportsController` renders full HTML pages (complete `<!DOCTYPE html>` documents with `<style>` blocks) from inside methods that extend `AbstractController` — the API controller base class. Per architecture rules, HTML controllers should use `HtmlView::render()` and not extend `AbstractController`.
- Files: `app/Controller/MeetingReportsController.php` (lines 239–329, 360–530), `app/Controller/QuorumController.php` (line 29)
- Impact: Circumvents the security headers and error-handling pipeline of `AbstractController` for non-JSON responses.
- Fix approach: Extract HTML templates to `app/Templates/`, route HTML endpoints through `HtmlView::render()`.

**36 raw error_log() calls bypassing Logger:**
- Issue: 36 `error_log()` calls are scattered across controllers and services instead of using `Logger::error()` / `Logger::warning()`. This means these log entries lack request ID correlation, log level filtering, and structured context.
- Files: `app/Controller/AbstractController.php` (lines 47, 52, 85), `app/Services/BallotsService.php` (line 209), `app/Controller/MeetingWorkflowController.php` (lines 181, 193, 308), and 30 other locations
- Impact: Errors logged via `error_log()` cannot be filtered by severity or correlated to a request in log aggregators.
- Fix approach: Replace all `error_log()` calls with appropriate `Logger::*()` calls, passing context arrays.

## Known Bugs

**Design + JS interactions broken since v4.2 redesign:**
- Symptoms: Visual layout regressions in multiple pages; JS event handlers, DOM selectors, and HTMX targets broken after HTML restructuring during the v4.2 redesign milestone (table→flex migration in admin, wizard restructuring, etc.).
- Files: `public/assets/js/pages/` (1,461 `querySelector`/`getElementById` calls across 20 JS page files), `public/assets/css/` (25 CSS files)
- Trigger: Pages opened in browser; interactions (form submissions, dynamic updates, HTMX targets) fail silently or throw JS errors.
- Workaround: None currently. This requires a dedicated frontend audit milestone.

## Security Considerations

**api_require_role() is a no-op in test bootstrap:**
- Risk: `tests/bootstrap.php` (lines 215–218) defines `api_require_role()` as an empty function. Test cases for controllers that use `api_require_role()` never test the authorization path. A regression in the production implementation would not be caught by unit tests.
- Files: `tests/bootstrap.php` (line 216), `app/Controller/RgpdExportController.php` (primary documented example)
- Current mitigation: `RgpdExportControllerTest` (line 63) documents this limitation and tests `AuthMiddleware::requireRole()` directly as a workaround. `AuthMiddlewareTest` covers the production path independently.
- Recommendations: Add integration test variants with `APP_AUTH_ENABLED=1` asserting that unauthorized callers are rejected for security-critical endpoints (RGPD export, admin operations).

**Session timeout inconsistency between AuthMiddleware and SSE endpoint:**
- Risk: `AuthMiddleware::getSessionTimeout()` reads per-tenant timeout from the database. `events.php` hardcodes `1800`. If a tenant configures a shorter timeout (e.g., 300s), SSE connections remain valid for 30 minutes after other API calls have been rejected.
- Files: `public/api/v1/events.php` (line 40), `app/Core/Security/AuthMiddleware.php` (line 114)
- Current mitigation: Default timeout is 1800s; custom timeouts are not yet widely deployed.
- Recommendations: Replace hardcoded `1800` in `events.php` with `AuthMiddleware::getSessionTimeout()`.

## Performance Bottlenecks

**Report generation issues multiple queries per motion:**
- Problem: `MeetingReportsController::report()` and `MeetingReportService::renderHtml()` issue 4–5 DB queries per motion (2 policy lookups, 1 official tally computation, 1 quorum calculation, 1 majority calculation). A 30-motion meeting generates 120–150 queries on a single report page load.
- Files: `app/Controller/MeetingReportsController.php` (lines 73–128), `app/Services/MeetingReportService.php` (lines 140–197)
- Cause: Policies fetched individually per motion; `OfficialResultsService`, `VoteEngine`, `QuorumEngine` each call DB inside the per-motion loop.
- Improvement path: Batch-load all policies before loop. Leverage `motions.official_*` columns (already populated after consolidation) rather than recomputing.

**PDF generation has no memory or timeout guard:**
- Problem: `MeetingReportsController::generatePdf()` uses DOMPDF on the full meeting report HTML with no `set_time_limit()` or `ini_set('memory_limit', ...)`. A 100-motion meeting with 500 attendance rows will likely exceed default PHP memory limits.
- Files: `app/Controller/MeetingReportsController.php` (lines 332–534)
- Cause: DOMPDF stores the complete rendered DOM in memory before generating output.
- Improvement path: Set `set_time_limit(120)` and `ini_set('memory_limit', '512M')` at the start of `generatePdf()`. Consider precomputing PDF at consolidation time rather than on-demand.

## Fragile Areas

**AuthMiddleware static state:**
- Files: `app/Core/Security/AuthMiddleware.php` (lines 58–81)
- Why fragile: 9 static properties hold per-request state (`$currentUser`, `$currentMeetingId`, `$currentMeetingRoles`, `$cachedSessionTimeout`, `$sessionExpired`, etc.). In tests, state leaks between test cases unless `AuthMiddleware::reset()` is explicitly called in `tearDown`. In production with PHP-FPM (new process per request) this is safe; any migration to a persistent PHP runtime (ReactPHP, Swoole, FrankenPHP) would break all auth logic silently.
- Safe modification: Always call `AuthMiddleware::reset()` in `setUp`/`tearDown` in any test touching auth. Never introduce a persistent process runner without auditing all static state first.
- Test coverage: `AuthMiddlewareTest`, `AuthMiddlewareTimeoutTest`, `RelaxRoleTransitionsTest` all call `reset()` correctly.

**MeetingReportsController triplicated report logic:**
- Files: `app/Controller/MeetingReportsController.php`
- Why fragile: `report()` (lines 21–330), `generateReport()` (lines 538–606), and `MeetingReportService::renderHtml()` (lines 140–197) contain three parallel implementations of the motion-rendering loop. A fix to tally display logic must be applied in 3 places.
- Safe modification: Write characterization tests for all three entry points before any change. Route all through `MeetingReportService` as a prerequisite.
- Test coverage: `MeetingReportsControllerTest` exercises dispatch only; `MeetingReportServiceTest` covers the service path.

**OperatorController::openVote() transaction holds lock during N+1 token queries:**
- Files: `app/Controller/OperatorController.php` (lines 234–321)
- Why fragile: Token generation runs inside `api_transaction()` and issues 1 SELECT + 1 INSERT per eligible member. For meetings with 200+ members, the transaction may exceed PostgreSQL's default `lock_timeout` and be aborted mid-way, leaving the meeting in an inconsistent state (some tokens created, meeting already transitioned to `live`).
- Safe modification: Preload existing active token member IDs before the transaction; keep only INSERTs inside it.
- Test coverage: `OperatorControllerTest` uses a single-member stub; the N+1 risk is not exercised.

## Scaling Limits

**SSE poll-based architecture (1 PHP-FPM worker per connected client):**
- Current capacity: Each SSE connection holds a PHP-FPM worker for 30 seconds. A server with 20 FPM workers supports 20 concurrent SSE connections before new HTTP requests queue.
- Limit: Concurrent operator + voter SSE connections during an active vote consume all available FPM slots.
- Scaling path: Dedicate a separate FPM pool with higher worker count for SSE connections, or migrate to a dedicated event-push process (ReactPHP, Swoole, nginx SSE module).

**Import memory for large membership CSV files:**
- Current capacity: `ImportService` (791 lines) loads CSV into memory via `fgetcsv` before processing. Default PHP memory limit (128MB) handles ~5,000 members.
- Limit: Imports of 10,000+ member CSV files may exhaust memory on constrained environments.
- Scaling path: Stream CSV parsing row-by-row using `SplFileObject` with `READ_CSV` flag; `ExportService` already uses OpenSpout streaming as reference.

## Dependencies at Risk

**phpoffice/phpspreadsheet potentially unused after OpenSpout migration:**
- Risk: `ExportService` was migrated to OpenSpout but `phpoffice/phpspreadsheet` remains in `composer.json`. If it is no longer used elsewhere it adds install weight and potential version conflicts.
- Impact: Increased install size; possible dependency range conflicts.
- Migration plan: Audit `composer.json` `require` section; if phpspreadsheet has no remaining callers, remove it and run `composer update`.

**dompdf/dompdf memory limitations for complex reports:**
- Risk: DOMPDF has limited CSS support and significant memory usage for complex HTML. Large meeting reports (100+ motions, 500+ attendance rows) may exceed memory limits or render incorrectly.
- Impact: `generatePdf()` may crash or produce malformed output for large meetings.
- Migration plan: Add memory/timeout guards immediately (short-term). Evaluate headless Chromium via `wkhtmltopdf` for production PDF fidelity (long-term).

## Test Coverage Gaps

**api_require_role() authorization never tested end-to-end:**
- What's not tested: The production `api_require_role()` implementation in `app/api.php` is stubbed as a no-op in `tests/bootstrap.php`. Controllers relying solely on `api_require_role()` for authorization have no test asserting unauthorized access is rejected.
- Files: `tests/bootstrap.php` (line 216), all controllers calling `api_require_role()` without a secondary `AuthMiddleware::requireRole()` call
- Risk: A refactor of `api_require_role()` could silently remove authorization enforcement without failing any tests.
- Priority: High

**SSE race conditions and consumer group behavior untested:**
- What's not tested: `EventBroadcaster` fan-out to per-consumer Redis queues under concurrent connections. Event ordering, queue overflow at `MAX_QUEUE_SIZE = 1000`, and consumer cleanup on ungraceful disconnect are not verified.
- Files: `app/SSE/EventBroadcaster.php`, `public/api/v1/events.php`
- Risk: Redis queue overflow silently drops events; ungraceful disconnects leave stale consumer sets until TTL expires (120s).
- Priority: Medium

**Report generation query count not tested for growth:**
- What's not tested: No test asserts that `MeetingReportService::renderHtml()` issues a bounded number of queries regardless of motion count. Test suite uses single-motion fixtures, making the N+1 pattern invisible.
- Files: `tests/Unit/MeetingReportServiceTest.php`, `tests/Unit/MeetingReportsControllerTest.php`
- Risk: Query count grows linearly with motions; no regression guard.
- Priority: Medium

**Frontend JS interactions have zero automated tests:**
- What's not tested: All JS in `public/assets/js/pages/` (20 files, 1,461 DOM selector calls) has no automated test coverage. The v4.2 DOM restructuring broke event handlers without any test catching it.
- Files: `public/assets/js/pages/` (operator-realtime.js, dashboard.js, operator-motions.js, operator-attendance.js, etc.)
- Risk: Any HTML change can silently break user-facing interactions with no detection until manual browser testing. Known regressions are already present.
- Priority: High

---

*Concerns audit: 2026-04-07*
