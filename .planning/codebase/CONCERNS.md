# Codebase Concerns

**Analysis Date:** 2026-04-07

## Tech Debt

**Large Controllers & Services:**
- Issue: Multiple controllers exceed 700+ lines (ImportController: 921, AuthMiddleware: 871, MeetingReportsController: 727, MotionsController: 720, MeetingsController: 687, ExportService: 674)
- Files: `app/Controller/ImportController.php`, `app/Core/Security/AuthMiddleware.php`, `app/Controller/MeetingReportsController.php`, `app/Services/ExportService.py`
- Impact: Reduced testability, increased cyclomatic complexity, harder to maintain and debug
- Fix approach: Extract business logic into smaller, focused services; use composition over inheritance; consider CQRS pattern for complex domains

**File-based SSE Queue and Lock System:**
- Issue: Event broadcasting falls back to file-based queue in `/tmp/agvote-sse-*.json` with flock-based synchronization instead of reliable Redis
- Files: `app/SSE/EventBroadcaster.php` (lines 206-273)
- Impact: Unreliable event delivery, potential data loss, race conditions if /tmp is cleared, lock file contention on high-traffic systems, events not persisted across restarts
- Fix approach: Mandate Redis for production deployments; remove file-based fallback or rebuild with persistent queue (RabbitMQ, SQS); implement queue depth monitoring

**PID-file based SSE Server Health Check:**
- Issue: SSE server running detection relies on `/tmp/agvote-sse.pid` file existence and PID validity check
- Files: `app/SSE/EventBroadcaster.php` (line 289-298)
- Impact: Stale PID files cause false "server running" status; no heartbeat/liveness check; server crash goes undetected
- Fix approach: Replace with Redis key with TTL, implement health check endpoint, add supervisor monitoring (systemd/Docker)

**Temporary Files in /tmp for CSV/XLSX Uploads:**
- Issue: ImportController creates temp files via `tempnam(sys_get_temp_dir(), 'csv_')` without guaranteed cleanup
- Files: `app/Controller/ImportController.php` (lines 47-54)
- Impact: Disk space exhaustion if cleanup fails; temporary exposure of uploaded content
- Fix approach: Use `register_shutdown_function()` for cleanup guarantee; migrate to streaming parser for large files

**Rate Limiting with File Locks:**
- Issue: RateLimiter uses file-based locking on `/tmp/agvote-ratelimit-*.lock` 
- Files: `app/Core/Security/RateLimiter.php` (line 198-234)
- Impact: Performance degradation under high request volume; potential lock contention; filesystem bottleneck
- Fix approach: Move rate limiting to Redis with atomic increments and TTL expiry

**Static Session Timeout State:**
- Issue: AuthMiddleware caches session timeout in static variables without tenant isolation initially, then adds tenant checking
- Files: `app/Core/Security/AuthMiddleware.php` (lines 67-81)
- Impact: Potential race conditions in multi-tenant scenarios; test-only injected timeout logic adds complexity
- Fix approach: Use request-scoped configuration object; remove static state test injection

## Known Bugs

**HTML Controllers Bypass Validation Framework:**
- Bug: Login, Setup, Reset controllers extend AbstractController but don't call `api_require_role()` like API controllers
- Files: `app/Controller/AuthController.php`, `app/Controller/SetupController.php`
- Trigger: Login page HTML rendering path bypasses standard auth middleware checks
- Impact: Potential auth bypass if HTML controller logic path is misused
- Workaround: Controllers manually validate in handle methods
- Fix approach: Create separate `HtmlController` base class with explicit auth checks; enforce at Router level

**RgpdExportController Missing Test Coverage:**
- Bug: RGPD data export endpoint has no unit tests despite critical privacy functionality
- Files: `app/Controller/RgpdExportController.php` (45 lines, untested)
- Trigger: Any authenticated user calls GET /api/v1/rgpd_export
- Impact: No regression detection for RGPD Article 20 compliance; data leakage possible if scoping breaks
- Workaround: Manual GDPR audit logs track exports
- Fix approach: Add RgpdExportControllerTest with export scope validation; verify only user's own data included

**Session Timeout Cache Invalidation:**
- Bug: `$cachedSessionTimeout` cached per-request but logic expects tenant isolation via `$cachedTimeoutTenantId`
- Files: `app/Core/Security/AuthMiddleware.php` (lines 67-68, 145-147)
- Trigger: Multiple API requests from different tenants in same CLI process (non-web context)
- Impact: User A's timeout might be used for User B if both are in same tenant but cache from different calls; test isolation issues with static state
- Workaround: Tests inject timeout via `$testSessionTimeout`
- Fix approach: Use request-scoped container instead of static state; remove test injection pattern

**Design Regressions from v4.2 Milestone:**
- Bug: v4.2 visual redesign broke JS interactions (event handlers, DOM selectors, HTMX targets) due to HTML restructuring
- Files: Frontend files (HTML/HTMX/JS not examined in detail)
- Impact: Broken form submissions, unresponsive UI elements, missing interactions
- Workaround: Fall back to page refresh or server-side navigation
- Fix approach: Re-examine each page's HTML structure against JS; verify HTMX targets match DOM ids; test in browser before committing

## Security Considerations

**Password Storage in Form Data:**
- Risk: SetupController and AccountController access raw password via `$_POST['admin_password']` and `$_POST['current_password']`
- Files: `app/Controller/SetupController.php` (line 65), `app/Controller/AccountController.php` (lines 63-65)
- Current mitigation: Passwords hashed immediately with password_hash(); no logging of raw values observed
- Recommendations: Use constant-time comparison for password verification; avoid password variables in error messages; implement key rotation for password_hash algorithm

**Session Storage in $_SESSION Array:**
- Risk: CSRF token and auth user data stored in superglobal $_SESSION without encryption-at-rest
- Files: `app/Core/Security/CsrfMiddleware.php`, `app/Core/Security/AuthMiddleware.php`
- Current mitigation: PHP's session.serialize_handler and default directory-based storage; httpOnly cookie flag set
- Recommendations: Encrypt sensitive session data (CSRF tokens, user IDs); migrate to Redis sessions in production with TLS; verify session.gc_maxlifetime matches DEFAULT_SESSION_TIMEOUT (1800s)

**CORS Configuration in Code:**
- Risk: CORS_ALLOWED_ORIGINS loaded from environment, must list exact domains
- Files: `app/Core/Providers/SecurityProvider.php` (reads from config)
- Current mitigation: Localhost-only in .env.example; production requires explicit config
- Recommendations: Validate CORS origins against whitelist; log rejected origins; consider preflight caching

**Database Connection without Timeout:**
- Risk: PDO connection has no connection timeout or query timeout configured
- Files: `app/Core/Providers/DatabaseProvider.php` (line 35)
- Current mitigation: None observed
- Recommendations: Set PDO timeout attributes (PDO::ATTR_TIMEOUT); add query-level timeout via PostgreSQL statement_timeout

**Email Tracking Pixel Privacy:**
- Risk: EMAIL_TRACKING_ENABLED allows email open tracking via pixel; potentially conflicts with GDPR strict interpretation
- Files: `app/Controller/EmailTrackingController.php`, configuration in .env
- Current mitigation: Can be disabled via config; audit logs pixel access
- Recommendations: Require explicit opt-in per email; anonymize tracking IP; implement data retention policy; document tracking in privacy policy

## Performance Bottlenecks

**Large File Exports to Memory:**
- Problem: ExportService::createFullExportSpreadsheet loads entire attendance, motion, and vote data into PhpSpreadsheet in memory before output
- Files: `app/Services/ExportService.php` (lines 622-673)
- Cause: Full array_map() transformations before sheet creation; no streaming
- Impact: Memory exhaustion on large meetings (10k+ attendances × multiple sheets); export timeout
- Improvement path: Implement streaming XLSX writer; chunk data into pages; add progress callback; consider CSV-only for large exports

**Multiple COUNT(*) Queries in Dashboard/Stats:**
- Problem: MeetingStatsRepository issues 10+ separate COUNT(*) queries instead of single aggregation query
- Files: `app/Repository/MeetingStatsRepository.php` (lines 40-135)
- Cause: Each statistic (present count, proxy count, motion count, etc.) is separate query
- Impact: O(N) queries for M statistics; dashboard load time linear with statistic count
- Improvement path: Create single aggregation query with GROUP BY and CASE statements; cache results; implement materialized view

**Session Revalidation DB Query Per Request:**
- Problem: AuthMiddleware re-checks user is_active and role from DB every 60 seconds (SESSION_REVALIDATE_INTERVAL)
- Files: `app/Core/Security/AuthMiddleware.php` (line 395)
- Impact: Unnecessary DB hits even for stateless requests; adds latency; can create connection pool pressure
- Improvement path: Implement fast-path for single-tenant; cache in Redis with TTL < 60s; batch revalidation in background worker

**Email Queue Processing Without Backpressure:**
- Problem: EmailQueueService::processQueue() loads all queued emails into memory before processing
- Files: `app/Services/EmailQueueService.php`
- Cause: No pagination or streaming of queue
- Impact: Memory exhaustion if queue backs up; slow delivery if SMTP is slow
- Improvement path: Process in batches of 10-50; implement queue priority (urgent vs. batch); add send rate limiting

**File Upload Temporary Path Race Condition:**
- Problem: ImportController creates temp file, processes it, then unlinks it — but multiple concurrent uploads could conflict
- Files: `app/Controller/ImportController.php` (lines 47-54)
- Cause: tempnam() is not atomic with processing
- Impact: Potential data corruption if two uploads write to same temp file; unlink() may delete wrong file
- Improvement path: Use unique session-scoped directory; implement file locking; prefer streaming parser

## Fragile Areas

**AuthMiddleware Static State Management:**
- Files: `app/Core/Security/AuthMiddleware.php` (lines 29-82)
- Why fragile: 10+ static variables (currentUser, currentMeetingId, currentMeetingRoles, debug, accessLog, sessionExpired, cachedSessionTimeout, cachedTimeoutTenantId, testSessionTimeout, testTimeoutTenantId) with interdependent state
- Modification risks: Adding new static property requires understanding thread-safety (in CLI) and request isolation (in web); test setup requires calling multiple init/reset methods; state pollution between tests if not reset
- Safe modification: Use dependency injection instead of statics; wrap in request-scoped service; add explicit reset() method called in test setUp()
- Test coverage: AuthMiddlewareTest exists but doesn't cover all static state transitions; SESSION_REVALIDATE_INTERVAL logic lacks explicit test

**ExportService Value Translation Tables:**
- Files: `app/Services/ExportService.php` (lines 25-74)
- Why fragile: Hardcoded French translation tables (ATTENDANCE_MODES, DECISIONS, VOTE_CHOICES, MEETING_STATUSES); duplicated in database schema as ENUM constraints
- Modification risks: Changes to status/mode enums must sync 3+ places (migration, constant, test fixtures); missing value causes blank output instead of error
- Safe modification: Centralize translations in translation service; load from database; validate against schema constraints
- Test coverage: ExportServiceTest uses fixtures; doesn't verify all enum values mapped

**ImportService CSV Column Mapping:**
- Files: `app/Services/ImportService.php`, `app/Controller/ImportController.php` (lines 64-70)
- Why fragile: Column mapping is flexible (getMembersColumnMap() returns fuzzy header matching) — CSV with different column order or missing columns fails silently with partial data
- Modification risks: User uploads CSV with "Nom" instead of "name"; system skips that row with no error message; data loss looks like import succeeded
- Safe modification: Validate all required columns present before processing; show validation report to user; require explicit column mapping UI
- Test coverage: ImportControllerTest has cases for missing columns; missing case for partial-match column names

**ProxyRepository and ForUpdate Locking:**
- Files: `app/Repository/ProxyRepository.php` (lines 96-105), `app/Services/BallotsService.php` (line 171)
- Why fragile: FOR UPDATE locking relies on transactions; if transaction scope is wrong, lock isn't acquired
- Modification risks: Calling hasActiveProxyForVote() outside transaction causes race condition (proxy deleted between check and vote); calling from non-transaction context silently ignores lock
- Safe modification: Add assertion that transaction is active; return lock status to caller; document transaction scope requirement
- Test coverage: BallotsServiceTest has concurrency scenarios; missing test for non-transactional call to locked method

**MeetingRepository Quorum Calculation:**
- Files: `app/Repository/MeetingRepository.php` (lines 499-504)
- Why fragile: Meeting row locked with FOR UPDATE for quorum checks; lock can deadlock if multiple meetings locked in different orders
- Modification risks: Concurrent quorum checks on two meetings can deadlock if operations lock in opposite order
- Safe modification: Always lock by meeting_id ASC; use timeout on lock acquisition; document lock ordering in code
- Test coverage: No explicit deadlock test

## Scaling Limits

**File-based SSE Queue Max 1000 Events:**
- Current capacity: SSE event queue limited to 1000 events per broker (line 21 of EventBroadcaster)
- Limit: At 100 events per meeting, supports only 10 concurrent active meetings
- Scaling path: Move to Redis with list; configure max queue depth per meeting; implement event batching; add consumer group for multiple SSE servers

**PDO Single Connection:**
- Current capacity: Single PDO connection per PHP process (DatabaseProvider::$pdo is static singleton)
- Limit: Apache/FPM with 10 workers = 10 connections; under load, slow queries block requests
- Scaling path: Implement connection pool via PgBouncer; use read replicas; add query caching layer

**CSV Import File Size Limit 5MB:**
- Current capacity: ImportService::MAX_FILE_SIZE = 5 * 1024 * 1024 (line 22)
- Limit: ~50k rows (assuming 100 bytes/row); larger files fail upload
- Scaling path: Implement chunked upload; add streaming CSV parser; background processing with job queue

**Session Timeout Fallback 30 Minutes:**
- Current capacity: DEFAULT_SESSION_TIMEOUT = 1800 seconds hardcoded (line 41 of AuthMiddleware)
- Limit: No way to configure per-tenant session duration without code change
- Scaling path: Move timeout to database settings table; cache in Redis with TTL; allow per-role override

**Rate Limiter File-based Bucket:**
- Current capacity: Single file lock per rate limit context; flock() serializes requests
- Limit: High-frequency endpoints (vote.cast, motion updates) serialize under rate limiter lock
- Scaling path: Implement Redis-based sliding window counter; use atomic INCR + EXPIRE

## Dependencies at Risk

**PhpSpreadsheet for XLSX Export:**
- Risk: Large in-memory data structures; potential memory exhaustion on large exports; no built-in streaming mode
- Impact: Exports >10MB timeout or crash; users cannot export large meetings
- Migration plan: Evaluate PhpSpreadsheet Pro or switch to simpler CSV/JSON; implement server-side Excel generation with Apache POI (PHP-Java bridge) or Node.js worker

**Symfony EventDispatcher:**
- Risk: Synchronous event dispatching; long-running listeners block request
- Impact: Email sending during request (if EmailService listens to events) delays API response
- Migration plan: Extract event listeners to background job queue (RabbitMQ, Redis); use pub/sub for async processing

**PostgreSQL Version Lock (implicit):**
- Risk: No explicit version constraint in code; features used may require 12+
- Impact: Deployment fails silently on PostgreSQL 9.6; RETURNING clause may not work
- Migration plan: Add explicit pg_version check in Application::boot(); document minimum version in deploy docs

## Missing Critical Features

**Feature Gap: Transaction-level Error Recovery:**
- Problem: If api_transaction fails, no automatic retry or rollback confirmation; user sees generic "internal_error"
- Blocks: Multi-step operations like bulk imports cannot be retried safely
- Recommended approach: Implement retry-with-exponential-backoff in api_transaction(); make transaction idempotent via unique constraint on operation id

**Feature Gap: Audit Trail for Session Operations:**
- Problem: Session creation/expiry not logged; SessionHelper::destroy() doesn't audit_log
- Blocks: Security compliance audits; impossible to trace session lifecycle
- Recommended approach: Call audit_log() in SessionHelper::destroy(); log session timeout events

**Feature Gap: Email Queue Delivery Confirmation:**
- Problem: EmailQueueService marks emails as "sent" without SMTP delivery confirmation
- Blocks: Cannot identify failed deliveries; bounces not tracked
- Recommended approach: Implement bounce handling (SNS webhook for AWS SES); add delivery_status enum to email_queue table; retry logic for transient failures

## Test Coverage Gaps

**Untested Components:**
- RgpdExportController: No test file exists
  - What's not tested: Data export scope validation, GDPR compliance, unauthorized access rejection
  - Files: `app/Controller/RgpdExportController.php`
  - Risk: Export could include other users' data if scoping logic breaks
  - Priority: High

**AuthMiddleware Static State Transitions:**
- What's not tested: Full lifecycle of session timeout cache invalidation across multiple requests
- Files: `app/Core/Security/AuthMiddleware.php` (SESSION_REVALIDATE_INTERVAL logic)
- Risk: Cache invalidation bugs go undetected; user session doesn't refresh correctly
- Priority: High

**EventBroadcaster File-based Fallback:**
- What's not tested: File locking contention, queue overflow behavior, stale lock cleanup
- Files: `app/SSE/EventBroadcaster.php` (lines 206-265)
- Risk: Events lost under high load; server hangs if lock cannot be acquired
- Priority: Medium

**ImportController CSV Column Fuzzy Matching:**
- What's not tested: Edge cases in column mapping (partial matches, case sensitivity, multi-language headers)
- Files: `app/Controller/ImportController.php` (line 64), `app/Services/ImportService.php`
- Risk: Silent data loss when user uploads CSV with non-standard headers
- Priority: Medium

**MotionsController and BallotsController Complex Vote Logic:**
- What's not tested: Race conditions between vote casting and ballot state changes
- Files: `app/Controller/MotionsController.php`, `app/Controller/BallotsController.php`, `app/Services/BallotsService.php`
- Coverage: ~60% estimated; edge cases like concurrent ballot transitions not covered
- Priority: Medium

---

*Concerns audit: 2026-04-07*
