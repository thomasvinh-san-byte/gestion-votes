# Codebase Concerns

**Analysis Date:** 2026-03-16

---

## Tech Debt

**operator-tabs.js God File:**
- Issue: Single 3,165-line IIFE containing 82+ functions covering settings, dashboard, live meeting state, device management, agenda, quorum display, and manual vote. Comment in the file itself acknowledges a P3 split: `NICE-TO-HAVE: split P3 (settings + dashboard modules)`.
- Files: `public/assets/js/pages/operator-tabs.js`
- Impact: High cognitive load, difficult to test, merge conflicts during feature work, slow review cycles.
- Fix approach: Extract settings module and dashboard module into separate `operator-settings.js` and `operator-dashboard.js` files communicating via the existing `window.OpS` bridge.

**`var` Keyword Throughout operator-tabs.js:**
- Issue: 118 `var` declarations inside the IIFE. All other modern page files use `const`/`let`. The legacy `var`s create function-scoped variables with hoisting that differ from `const`/`let` semantics.
- Files: `public/assets/js/pages/operator-tabs.js`
- Impact: Potential scoping bugs when refactoring; inconsistent with the rest of the codebase.
- Fix approach: Migrate to `const`/`let` when performing the file split (P3).

**No JS Module System:**
- Issue: All JavaScript runs as plain `<script>` tags with global-scope IIFEs. Inter-module communication uses `window.OpS` (operator), `window.Utils`, `window.Shared`, `window.Shell`. No ES modules, no bundler.
- Files: All files under `public/assets/js/`
- Impact: Load-order dependency brittle; no tree-shaking; all ~12 scripts on the operator page load serially; potential global namespace collisions.
- Fix approach: Migrate to ES modules (`type="module"`); this would also eliminate the `window.*` bridge pattern.

**No Asset Bundling or Minification:**
- Issue: Operator page loads 12 separate `<script>` tags (~7,500 LOC). Analytics page loads Chart.js vendor plus 5 scripts. No concatenation, no production minification (only a `bin/minify.sh` helper for manual use), no cache-busting hashes on filenames.
- Files: `public/operator.htmx.html`, `public/analytics.htmx.html`, `bin/minify.sh`
- Impact: 12 sequential HTTP requests per page load; cache invalidation requires manual filename bumps.
- Fix approach: Add a build step (esbuild or Vite) that bundles per-page entry points with content-hash filenames.

**PolicyRepository Optional Tenant Guard:**
- Issue: `findQuorumPolicy()` and `findVotePolicy()` accept `tenantId = ''` (optional), meaning callers can accidentally query across tenants if they forget to pass it. A dedicated `findQuorumPolicyForTenant()` method with required tenant exists but is not the only path.
- Files: `app/Repository/PolicyRepository.php` (lines 15, 121)
- Impact: Low risk in practice because all current call sites pass a tenant ID, but the signature is a trap for future contributors.
- Fix approach: Make `tenantId` required; remove the optional overload; update all call sites.

**`SELECT *` in Several Repositories:**
- Issue: Several repository methods use `SELECT *` rather than naming columns explicitly.
- Files: `app/Repository/MeetingRepository.php`, `app/Repository/MemberRepository.php`, `app/Repository/PolicyRepository.php`, `app/Repository/SpeechRepository.php`, `app/Repository/MeetingAttachmentRepository.php`, `app/Repository/Traits/MotionFinderTrait.php`
- Impact: Schema changes silently add columns to API responses; over-fetches data; breaks if a column is renamed.
- Fix approach: Replace with explicit column lists as part of routine repository touch-ups.

**No Database Migration Runner CLI:**
- Issue: Migrations are plain SQL files applied by `deploy/entrypoint.sh` at container start via a bash loop. There is no standalone migration CLI (`bin/migrate` or similar). The `applied_migrations` table tracks state but is only populated by Docker entrypoint.
- Files: `deploy/entrypoint.sh`, `database/migrations/`
- Impact: Running migrations outside Docker (e.g., staging DB, local `psql`) requires manually running each SQL file and inserting tracking rows.
- Fix approach: Extract migration logic into `bin/migrate.php` that reads `applied_migrations` and can be called independently.

**Inline Style Strings in JS Forcing `style-src unsafe-inline` CSP:**
- Issue: ~215 occurrences of `style="..."` inside JS template literals across `public/assets/js/pages/` and `public/assets/js/core/`. The CSP comment in `SecurityProvider.php` acknowledges this: `"style-src keeps unsafe-inline: 50+ dynamic inline styles in JS innerHTML"`.
- Files: `app/Core/Providers/SecurityProvider.php` (line 33–36), all page JS files
- Impact: `unsafe-inline` in `style-src` is a meaningful CSP weakening. Cannot enforce strict CSP.
- Fix approach: Replace inline style strings with CSS classes via `classList.add()`; reduce inline styles to zero; then drop `unsafe-inline` from `style-src`.

**French-Only UI with No i18n Infrastructure:**
- Issue: All UI strings are hardcoded French in HTML templates and JS files. No translation key system, no `gettext`, no `i18n` library.
- Files: All `.htmx.html` files, all `public/assets/js/pages/` files
- Impact: Adding a second language would require touching every file. Not a current requirement but worth noting if the product targets multi-lingual orgs.
- Fix approach: Not urgent; track as a future architectural decision if internationalisation is ever required.

---

## Security Considerations

**CSP Discrepancy Between Nginx and PHP SecurityProvider:**
- Risk: `deploy/nginx.conf` includes `https://cdn.jsdelivr.net` and `https://unpkg.com` in `script-src`, but these CDNs are not referenced by any HTML file in the repo. PHP `SecurityProvider::headers()` omits them. This creates a confusing dual-source of truth; if someone adds a CDN script reference in an HTML file, the PHP CSP (sent for PHP-served responses) will block it while nginx won't.
- Files: `deploy/nginx.conf` (line with CSP `add_header`), `app/Core/Providers/SecurityProvider.php` (line 34–38)
- Current mitigation: PHP CSP is stricter (no cdn.jsdelivr.net). Neither CDN is actually used in HTML.
- Recommendations: Remove the CDN allowances from the nginx CSP to match PHP; or establish a single place that owns the CSP value.

**Dev Seed Endpoint Protected Only by `APP_ENV` Check:**
- Risk: `dev_seed_members` and `dev_seed_attendances` endpoints in `DevSeedController` check `config('env')` against `['production', 'prod']` and block if matched. If `APP_ENV` is set to anything else (e.g., `staging`, `preprod`, `demo`) the endpoint is accessible to any authenticated operator.
- Files: `app/Controller/DevSeedController.php` (lines 14–21), `app/routes.php` (lines 148–149)
- Current mitigation: Requires operator-level authentication. Production blocks correctly.
- Recommendations: Rename check to also block `staging` and `demo`; or remove the endpoints from production builds entirely via deployment config.

**Uploaded Attachments Stored in `/tmp/ag-vote/`:**
- Risk: Meeting attachments (PDF files) are written to `/tmp/ag-vote/uploads/meetings/{meetingId}/` — a location inside `/tmp` that is world-readable by default on Linux unless permissions are enforced. The dir is created with `0o750`, which is correct, but `/tmp` itself is often cleaned by the OS or shared across containers.
- Files: `app/Controller/MeetingAttachmentController.php` (lines 63–65, 118)
- Current mitigation: Files are UUIDs renamed without original filenames; MIME type verified as PDF only; directory permissions set to 750.
- Recommendations: Move uploads to a persistent volume outside `/tmp`; serve downloads through a PHP controller that enforces tenant + auth checks (no direct nginx path to attachment files currently exists, which is good).

**Session Save Path in `/tmp`:**
- Risk: PHP sessions stored in `/tmp` (configured in `deploy/php.ini` line 22). With PHP-FPM and multiple workers, file sessions in `/tmp` can be slow under load. In containerised environments `/tmp` is ephemeral; sessions are lost on container restart.
- Files: `deploy/php.ini` (line 22)
- Current mitigation: Redis is used for WebSocket events and rate limiting; sessions are on disk only.
- Recommendations: Configure `session.save_handler = redis` and `session.save_path` to Redis when Redis is available, with file fallback.

**Audit Log Search Performs Full JSONB Cast:**
- Risk: Searching audit events by free text casts `payload` to text (`payload::text ILIKE :q3`), bypassing any index on the `payload` column and causing sequential scans on `audit_events` as the table grows.
- Files: `app/Repository/AuditEventRepository.php` (line 189)
- Current mitigation: A covering index `idx_audit_tenant_action_time` was added (migration `20260311_audit_covering_index.sql`) but it covers `(tenant_id, action, created_at)`, not `payload` text search.
- Recommendations: Use a GIN index on `payload` as `jsonb`; or move to PostgreSQL full-text search with `to_tsvector`; or restrict free-text search to the `action` field only.

**`error_log()` Mixed With Structured Logger:**
- Risk: ~20 `error_log()` calls are scattered directly in controllers and services alongside the `AgVote\Core\Logger` structured logger. `error_log()` output is unstructured plain text with no tenant/user context.
- Files: `app/Controller/AbstractController.php` (lines 47, 52, 68), `app/Controller/MeetingWorkflowController.php` (lines 180, 292), `app/Controller/VotePublicController.php` (lines 39, 42), `app/WebSocket/EventBroadcaster.php`, others
- Current mitigation: Logger is used for warning-level events. Critical errors still reach `error_log`.
- Recommendations: Replace all `error_log()` calls with `Logger::error()` / `Logger::warning()` for consistent JSON-structured output.

---

## Performance Bottlenecks

**No DB Connection Pooling:**
- Problem: Each PHP-FPM worker opens its own PDO connection per request. No `ATTR_PERSISTENT`, no PgBouncer in the deployment config.
- Files: `app/Core/Providers/DatabaseProvider.php` (lines 35–40), `deploy/supervisord.conf`
- Cause: PHP-FPM workers are stateless; connection overhead is paid per request at high concurrency.
- Improvement path: Add PgBouncer in transaction-pooling mode in front of PostgreSQL; or enable `ATTR_PERSISTENT => true` in PDO options (suitable for PHP-FPM with stable worker count).

**Google Fonts Blocking Render:**
- Problem: All 21 `.htmx.html` pages include synchronous `<link rel="stylesheet" href="https://fonts.googleapis.com/css2?...">` which blocks first contentful paint if the Google CDN is slow or unreachable.
- Files: All `.htmx.html` files (e.g., `public/operator.htmx.html` line 17, `public/analytics.htmx.html` line 14)
- Cause: External stylesheet loaded synchronously in `<head>`.
- Improvement path: Add `media="print" onload="this.media='all'"` pattern for async loading; or self-host the font files; or use `font-display: swap` CSS alongside async loading.

**Full-Text Payload Search on Large Audit Table:**
- Problem: Free-text search in audit log pages casts `payload::text ILIKE '%...%'` causing full sequential scans on `audit_events`.
- Files: `app/Repository/AuditEventRepository.php` (lines 67, 105, 189)
- Cause: No GIN/GiST index on the `payload` JSONB column for text search.
- Improvement path: See security section; add `CREATE INDEX idx_audit_payload_fts ON audit_events USING GIN (to_tsvector('french', payload::text))`.

**PhpSpreadsheet XLSX Export Memory:**
- Problem: XLSX export for large meetings instantiates `PhpOffice\PhpSpreadsheet` which loads the entire dataset into memory to build the spreadsheet object.
- Files: `app/Services/ExportService.php` (line 497), `app/Controller/AnalyticsController.php` (line 135)
- Cause: PhpSpreadsheet is an in-memory workbook builder.
- Improvement path: For large exports, stream CSV instead of XLSX, or use a streaming XLSX writer; add memory limit guard before export.

---

## Fragile Areas

**File-Based SSE/WebSocket Queue Fallback:**
- Files: `app/WebSocket/EventBroadcaster.php` (lines 149–246, `QUEUE_FILE = '/tmp/agvote-ws-queue.json'`)
- Why fragile: When Redis is unavailable the broadcaster falls back to a single JSON file at `/tmp/agvote-ws-queue.json` with `flock` for concurrency. Under high event throughput with multiple PHP-FPM workers this becomes a write bottleneck and events can be lost if the lock cannot be acquired.
- Safe modification: Always ensure Redis is available before disabling it; do not increase PHP-FPM worker count past ~4 if Redis is unavailable.
- Test coverage: Unit tests mock Redis; file-fallback path has no dedicated load test.

**File-Based Rate Limiter Fallback:**
- Files: `app/Core/Security/RateLimiter.php` (lines 109–119, `$storageDir = '/tmp/ag-vote-ratelimit'`)
- Why fragile: Same pattern as the WebSocket queue — flock-based file writes per request. Under load with Redis down, the rate limiter falls back to per-IP files in `/tmp`. Concurrent PHP-FPM workers contend on the same lock file per IP.
- Safe modification: Ensure Redis is healthy in production before scaling workers.
- Test coverage: `tests/Unit/RateLimiterTest.php` covers the happy path but not file-contention scenarios.

**`window.OpS` Inter-Module Bridge:**
- Files: `public/assets/js/pages/operator-tabs.js` (line 11: `window.OpS = { fn: {} }`), `public/assets/js/pages/operator-attendance.js` (line 9), `public/assets/js/pages/operator-motions.js` (line 10), `public/assets/js/pages/operator-speech.js` (line 9), `public/assets/js/pages/operator-exec.js` (line 19)
- Why fragile: Sub-modules reference `window.OpS` which must be set by `operator-tabs.js` before any sub-module executes. Script load order in `operator.htmx.html` enforces this, but there is no runtime guard if a sub-module is loaded standalone.
- Safe modification: Always keep `operator-tabs.js` as the first script in `operator.htmx.html`; do not load sub-modules in isolation.
- Test coverage: No JS unit tests for the `window.OpS` bridge.

---

## Accessibility Gaps

**Chart.js Charts Without Accessible Text Alternatives:**
- What's not tested: 8+ Chart.js instances on `analytics.htmx.html` render visual data with no `<table>` fallback, no `aria-describedby`, no visible data labels for screen reader users. The pages themselves acknowledge this: `"Graphiques Chart.js : alternatives textuelles manquantes"`.
- Files: `public/assets/js/pages/analytics-dashboard.js` (lines 209, 246, 366, 390, 408, 452, 477, 507), `public/analytics.htmx.html`, `public/admin.htmx.html` (line 1070), `public/settings.htmx.html` (line 656)
- Risk: WCAG 2.1 AA requires non-text content to have text alternatives (criterion 1.1.1). Analytics data is inaccessible to screen reader users.
- Priority: High — affects a core feature for any auditor or admin who uses assistive tech.

**Focus Trap Only Implemented in `ag-modal` Web Component:**
- What's not tested: `ag-modal.js` implements `_trapFocus()`. However, inline modals constructed via `modal.innerHTML = ...` in `operator-attendance.js` (lines 155, 390, 530), `operator-tabs.js` (lines 181, 1428), and `shared.js` do not use the `ag-modal` component and have no focus trap — keyboard users can Tab out of these modals into the background.
- Files: `public/assets/js/pages/operator-attendance.js`, `public/assets/js/pages/operator-tabs.js`, `public/assets/js/core/shared.js`
- Risk: WCAG 2.1 criterion 2.1.2 (No Keyboard Trap is the inverse; here the risk is the opposite — modal doesn't trap focus, making background content reachable while modal is open).
- Priority: Medium — affects keyboard-only users during core operator workflows.

**Accessibility E2E Tests are Shallow:**
- What's not tested: `tests/e2e/specs/accessibility.spec.js` contains 4 tests checking only that headings and buttons are visible. No contrast ratio checks, no screen-reader simulation, no axe-core/pa11y integration.
- Files: `tests/e2e/specs/accessibility.spec.js`
- Risk: Regressions in ARIA structure, colour contrast, or keyboard navigation go undetected.
- Priority: Medium — add `@axe-core/playwright` to the E2E suite for automated WCAG scanning.

**`aria-live` Regions Missing for Dynamic Vote Results:**
- What's not tested: Live vote tallies, quorum status updates, and motion state changes are injected via `innerHTML` into containers that have no `aria-live` attribute. Screen reader users won't hear updates during a live vote.
- Files: `public/assets/js/pages/operator-motions.js` (tally display containers), `public/assets/js/pages/vote.js` (vote status area), `public/assets/js/core/shell.js` (dashboard drawer)
- Risk: Real-time state changes invisible to screen reader users during the most time-critical part of the application.
- Priority: High for the vote page (`vote.htmx.html`); medium for operator screens.

---

## Test Coverage Gaps

**Zero JavaScript Unit Tests:**
- What's not tested: All business logic in `public/assets/js/` — vote submission, quorum display, attendance rendering, modal state machines, SSE reconnection — has no unit tests.
- Files: All of `public/assets/js/pages/` and `public/assets/js/components/`
- Risk: JS regressions only caught by E2E tests (slow) or manual QA; `operator-tabs.js` in particular is high complexity with no test coverage.
- Priority: High — start with `utils.js` and `shared.js` as they are pure utility functions.

**E2E Tests Not Run in CI:**
- What's not tested: `tests/e2e/` Playwright suite exists and covers major flows (auth, voting, operator, accessibility, mobile viewport) but there is no CI configuration (no `.github/workflows/` file triggers `playwright test`).
- Files: `tests/e2e/`, `.github/workflows/` (no e2e workflow present)
- Risk: E2E tests only run manually; regressions reach main undetected.
- Priority: High — add a GitHub Actions workflow that runs `playwright test` on PR.

**No Tests for File-Fallback Paths:**
- What's not tested: The Redis-unavailable fallback paths in `EventBroadcaster` and `RateLimiter` that write to `/tmp` files.
- Files: `app/WebSocket/EventBroadcaster.php` (file backend methods), `app/Core/Security/RateLimiter.php` (file backend)
- Risk: The fallback could silently break (file lock failure, permission error) in production and events/rate-limit state would be lost.
- Priority: Medium.

---

## Scaling Limits

**Single PHP-FPM + Nginx Container:**
- Current capacity: Single container with supervisord managing Nginx + PHP-FPM. Redis is a separate service.
- Limit: Horizontal scaling requires sticky sessions (PHP file sessions in `/tmp` are not shared across instances) or migrating sessions to Redis.
- Scaling path: (1) Move sessions to Redis; (2) run as separate Nginx and PHP-FPM containers; (3) add a load balancer.

**File-Based Rate Limiter Does Not Scale Horizontally:**
- Current capacity: Works correctly on a single PHP-FPM instance.
- Limit: On multiple instances, each has its own `/tmp/ag-vote-ratelimit/` directory. An attacker can make 10 requests per instance and stay under the per-instance limit.
- Scaling path: Enforce Redis-only rate limiting when running multi-instance; remove the file fallback as a valid production path.

---

## Dependencies at Risk

**`marked.min.js` Vendored Without Version Tracking:**
- Risk: `public/assets/js/vendor/marked.min.js` is vendored without a lockfile entry, package.json reference, or version comment. Security vulnerabilities in marked.js (XSS via malformed markdown) cannot be tracked or auto-detected.
- Impact: Doc viewer page (`doc.php`, `docs-viewer.js`) renders Markdown from documentation files; XSS via malicious `.md` content if marked.js has an unpatched vulnerability.
- Migration plan: Add marked as a `devDependency` in `package.json`; update via `npm`; use the version from `node_modules` via build step.

**Google Fonts External Dependency:**
- Risk: Typography depends on Google Fonts CDN. Three typefaces (Bricolage Grotesque, Fraunces, JetBrains Mono) loaded from `fonts.googleapis.com` / `fonts.gstatic.com`. Outage or policy change degrades UI legibility.
- Impact: Layout shift and fallback font rendering on all 21 pages.
- Migration plan: Self-host font files in `public/assets/fonts/`; remove external CSP font-src allowances.

---

*Concerns audit: 2026-03-16*
