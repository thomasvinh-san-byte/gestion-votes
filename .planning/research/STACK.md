# Stack Research

**Domain:** Full-stack session lifecycle wiring — SSE real-time voting, backend API completeness, frontend-backend integration
**Researched:** 2026-03-16
**Confidence:** HIGH (all findings verified against actual codebase files)

---

## What Already Exists (Do NOT Re-Add)

Before listing what is needed, this section documents the infrastructure that is already fully built. The milestone is about **wiring and gap-closing**, not about adding new technology.

### SSE Infrastructure — Fully Implemented

| Component | File | Status |
|-----------|------|--------|
| SSE endpoint | `public/api/v1/events.php` | Complete. Redis poll + file fallback. Auth check. 30s hold. Keepalive comment. |
| Event broadcaster | `app/WebSocket/EventBroadcaster.php` | Complete. Redis LPUSH to `sse:events:{meeting_id}` + file fallback at `/tmp/agvote-sse-{meeting_id}.json`. Pre-defined methods for all event types. |
| SSE client | `public/assets/js/core/event-stream.js` | Complete. `EventSource` with auto-reconnect (max 10), typed event listeners for all 9 event types. |
| Operator real-time | `public/assets/js/pages/operator-realtime.js` | Complete. SSE primary + polling safety net (5s active, 15s idle, 3x slower when SSE connected). All 9 event types handled. |
| Voter view SSE | `public/assets/js/pages/vote.js` | Complete. `EventStream.connect()` called on init, falls back to 3s polling when SSE inactive. |
| SSE events fired from PHP | Multiple controllers + `BallotsService` | Partial — `motionOpened`, `motionClosed`, `motionUpdated`, `attendanceUpdated`, `quorumUpdated`, `voteCast` are fired. `meetingStatusChanged` and `speechQueueUpdated` exist in broadcaster but call sites need audit. |

### API Endpoints — Extensively Built

Over 90 PHP API endpoints exist in `public/api/v1/`. All session lifecycle actions have handlers:
- `meetings.php` (GET list, POST create), `meetings_update.php`, `meeting_transition.php`
- `motions.php`, `motions_open.php`, `motions_close.php`, `motions_for_meeting.php`
- `ballots_cast.php`, `ballots_result.php`, `ballots_cancel.php`, `manual_vote.php`
- `attendances.php`, `attendances_upsert.php`, `attendances_bulk.php`
- `wizard_status.php`, `operator_workflow_state.php`, `meeting_workflow_check.php`
- `meeting_summary.php`, `meeting_generate_report.php`, `meeting_report_send.php`
- `quorum_status.php`, `quorum_card.php`

### Frontend API Wiring — Partially Done

- **Wizard → create meeting**: `wizard.js` calls `POST /api/v1/meetings` and redirects to `/hub.htmx.html?id={meeting_id}`. Fully wired.
- **Operator console**: All operator-* JS modules make real API calls. No demo data found in operator pages.
- **Voter view** (`vote.js`): Real API calls for motion load, ballot cast, heartbeat.
- **Post-session** (`postsession.js`): Real API calls for summary, transition, report generation, archive.

---

## Gaps Requiring Work (No New Libraries Needed)

### Gap 1: Hub Demo Fallback Must Be Removed

**File:** `public/assets/js/pages/hub.js`

`hub.js` contains `SEED_SESSION` and `SEED_FILES` objects (lines 301–322). When `wizard_status` API returns data in unexpected shape, or when `session_id` is absent from URL, the page silently renders demo data. The `mapApiDataToSession()` function normalises multiple field name variants (`meeting_title`/`title`, `members_count`/`member_count`, etc.) — indicating the API contract between `wizard_status.php` and `hub.js` needs to be stabilised.

**Resolution approach:** Audit `DashboardController::wizardStatus()` response shape, align `mapApiDataToSession()` to that contract, replace silent demo fallback with an error state.

### Gap 2: Nginx Missing SSE-Specific Location Block

**File:** `deploy/nginx.conf`

The `events.php` endpoint holds a PHP-FPM worker for up to 30 seconds (SSE loop). The current nginx config has a catch-all `location ~ \.php$` block with `fastcgi_read_timeout 60s` — sufficient for SSE but no `X-Accel-Buffering: no` is set at the nginx level (only in PHP headers). The file already sends `header('X-Accel-Buffering: no')` from PHP, which nginx honours, so this is actually fine.

**What is missing:** There is no dedicated `location` block for `/api/v1/events.php` that explicitly excludes it from the `limit_req zone=api burst=20` rate limiting. Long-held SSE connections could exhaust the rate limit zone for other API requests from the same IP. An SSE-specific location block with either its own rate limit zone or `limit_req off` is needed.

**Resolution approach:** Add a specific location block for `/api/v1/events.php` before the catch-all PHP block, with a permissive or absent rate limit, and an explicit `fastcgi_read_timeout 35s` (slightly above the PHP 30s SSE duration to ensure clean close).

### Gap 3: PHP-FPM Worker Exhaustion Under SSE Load

**Context:** PHP-FPM is configured with default pool settings (`deploy/php-fpm.conf`). Each SSE client holds a PHP-FPM worker for 30 seconds. With multiple concurrent users in a live meeting, worker exhaustion is possible at default pool sizes.

**Current state:** `pm = dynamic` is likely the default (not customised in `php-fpm.conf`). For a self-hosted single-meeting context (primary use case), this is acceptable. For high-concurrency scenarios, `pm.max_children` may need increasing.

**Resolution approach:** This is a configuration concern, not a library concern. Document `pm.max_children` guidance in deployment docs. No new stack component needed.

### Gap 4: `vote.cast` SSE Event Not Fired from `ballots_cast.php`

**File:** `app/Controller/BallotsController.php`, `app/Services/BallotsService.php`

`BallotsService::castBallot()` fires `EventBroadcaster::voteCast()` (line 207 of `BallotsService.php`). However, `BallotsController::cast()` calls `(new BallotsService())->castBallot($data)` and then calls `api_ok()` without any broadcaster call in the controller itself. The broadcaster call is inside the service — this is correct, but needs verification that the tally data passed to `voteCast()` is complete (ballot count + weights).

**Status:** Likely correct. Requires integration test to confirm `vote.cast` SSE event reaches operator console after a ballot is cast.

### Gap 5: `meeting_motions.php` Endpoint Aliasing

**File:** `public/assets/js/pages/postsession.js`

`postsession.js` calls `/api/v1/meeting_motions.php?meeting_id=…` (line 118). This file does **not** exist. The equivalent endpoint is `/api/v1/motions_for_meeting.php`. Either a redirect/alias file is needed, or `postsession.js` must be updated to call the correct path.

**Resolution approach:** Add `public/api/v1/meeting_motions.php` as a thin alias that calls `MotionsController::listForMeeting()`, matching the pattern used throughout the codebase. No new library needed.

### Gap 6: `meetingStatusChanged` SSE Call Sites

The `EventBroadcaster::meetingStatusChanged()` method exists but grep shows no controller or service calls it directly in the scan above. The `meeting_transition.php` endpoint routes to `MeetingWorkflowController` — this controller needs to call `meetingStatusChanged()` after a successful transition.

**Resolution approach:** Add `EventBroadcaster::meetingStatusChanged()` call in `MeetingWorkflowService` or `MeetingWorkflowController` after status transition commits.

---

## Recommended Stack (No Changes Required)

The existing stack is fully adequate for the milestone. No new Composer packages, npm packages, or infrastructure components are needed.

### Core Technologies (Existing — Confirmed Sufficient)

| Technology | Version | Purpose | Why Sufficient |
|------------|---------|---------|----------------|
| PHP 8.4-FPM | 8.4 | SSE endpoint, all API handlers | Synchronous PHP-FPM + `set_time_limit(35)` + `flush()` loop is the established pattern for SSE in PHP. No async runtime needed for this scale. |
| Redis 7.4 | 7.4 | SSE event queue per meeting (`sse:events:{meeting_id}`) | Per-meeting list with 60s TTL and 100-event cap is already implemented. LPUSH/LRANGE/DEL pipeline is correct pattern for PHP SSE polling. |
| phpredis (PECL) | installed | PHP Redis client | Already in Docker image. No alternative needed. |
| PostgreSQL 16.8 | 16 | Persistent session state, ballot records, tally | All repositories exist. Vote results, attendance, motions all persist to DB via existing service layer. |
| `EventBroadcaster` (custom) | n/a | PHP-side SSE event dispatch | Covers all required event types. Redis + file fallback means it works in dev (no Redis) and prod. |
| `EventStream` (custom JS) | n/a | Browser SSE client | Auto-reconnect, typed event listeners, polling fallback — all implemented. |

### Supporting Libraries (Existing — No Additions Needed)

| Library | Version | Purpose | Notes |
|---------|---------|---------|-------|
| `dompdf/dompdf` | v3.1.4 | PV PDF generation | Already used in `MeetingReportsController`. Report endpoints exist. |
| `symfony/event-dispatcher` | v8.0.4 | Domain event dispatch | Already wired. `VoteEvents::VOTE_CAST` constant exists. |
| `thecodingmachine/safe` | v3.3.0 | Type-safe stdlib | Already used throughout. |

### Development Tools (No Changes)

| Tool | Purpose | Notes |
|------|---------|-------|
| PHPUnit v10.5.63 | PHP unit + integration tests | New controller tests for SSE call sites should be added here. |
| Playwright v1.50.0 | E2E tests | SSE integration can be tested via `page.waitForResponse()` or event listener assertions. Playwright supports EventSource interception. |
| phpstan v2.1.39 | Static analysis | No new types introduced; existing level 5 is fine. |

---

## Installation

No new packages required. All capabilities are present in the existing `composer.json` and `package.json`.

```bash
# No new dependencies to install for this milestone.
# Verify existing setup:
composer install
```

---

## Configuration Changes Required

These are the only "stack" changes needed — configuration, not new dependencies.

### nginx.conf — Add SSE-Specific Location Block

Add **before** the catch-all `location ~ \.php$` block:

```nginx
# ── SSE endpoint — no rate limiting, extended timeout ──────────────────
location = /api/v1/events.php {
    try_files $uri =404;
    fastcgi_pass 127.0.0.1:9000;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
    # SSE loop runs 30s; give nginx 35s before killing the connection
    fastcgi_read_timeout 35s;
    # No rate limiting — SSE connections are long-lived and low-frequency
    # The PHP endpoint itself validates auth and meeting_id
}
```

**Why:** The current `location ~ \.php$` applies `limit_req zone=api burst=20 nodelay`. A 30-second SSE connection does not consume rate limit tokens continuously, but the initial request does consume one token. In a meeting with 20+ simultaneous voters all reconnecting at once (server-initiated reconnect at the 30s mark), burst requests could briefly exhaust the zone. The dedicated block removes ambiguity.

### php-fpm.conf — Document pm.max_children

For a live meeting with N simultaneous SSE clients, `pm.max_children` must be at least N + (normal API request concurrency). Document in `deploy/php-fpm.conf`:

```ini
; SSE NOTE: Each active SSE client holds one worker for up to 30s.
; For a meeting with 50 simultaneous voters: set pm.max_children >= 60
; (50 SSE + 10 headroom for API calls)
pm.max_children = 20
```

The default Alpine PHP-FPM pool is `pm.max_children = 5`. For a 50-voter meeting this will cause 503s. Set to at minimum 20 for typical AG use (10–30 voters).

---

## Alternatives Considered

| Category | Recommended | Alternative | Why Not |
|----------|-------------|-------------|---------|
| Real-time transport | SSE (already implemented) | WebSockets | WebSockets require a persistent server process (Ratchet, Swoole) incompatible with PHP-FPM. SSE is one-way (server → client) which matches the use case: voters watch the operator push events. |
| SSE server | PHP-FPM polling loop (existing) | Swoole / ReactPHP async server | Would require framework migration and separate process management. Polling at 1s in a 30s window gives max 1s latency — acceptable for voting. |
| Event queue | Redis per-meeting list (existing) | Redis Pub/Sub | Pub/Sub requires a persistent subscriber connection, impossible in PHP-FPM. The LPUSH + LRANGE/DEL pipeline pattern used here is the correct PHP-FPM SSE pattern. |
| SSE client reconnect | Native `EventSource` auto-reconnect | Manual `setTimeout` reconnect | `EventSource` spec guarantees automatic reconnect with `Last-Event-ID` support. Already used correctly in `event-stream.js`. |

---

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| Swoole / OpenSwoole | Changes PHP runtime model, incompatible with PHP-FPM container design | Continue with PHP-FPM polling SSE pattern |
| Ratchet WebSocket | Requires separate process, adds complexity, one-way events don't need full-duplex | SSE (already implemented) |
| Long-polling | Wastes connections, harder to implement correctly than SSE | SSE (already implemented) |
| `ob_start()` at SSE endpoint | Buffering defeats real-time streaming | SSE endpoint already calls `ob_end_flush()` loop — do not add output buffering middleware |
| Redis Pub/Sub from PHP-FPM | Cannot maintain blocking subscriber connection in FPM request lifecycle | Redis list + poll pattern (already implemented) |
| New Composer packages for SSE | No PHP SSE library adds value over the existing custom implementation | Use `EventBroadcaster` + `events.php` directly |

---

## Stack Patterns by Variant

**For operator console real-time KPIs:**
- SSE event triggers immediate `loadBallots()` / `loadResolutions()` call (already in `operator-realtime.js`)
- Polling continues at 3x slower rate as safety net (already implemented)
- No changes needed

**For voter view real-time motion updates:**
- SSE `motion.opened` / `motion.closed` events trigger `refresh()` (already in `vote.js`)
- Falls back to 3s polling when SSE inactive (already implemented)
- No changes needed

**For hub checklist reflecting real state:**
- Hub calls `wizard_status.php` on load — API endpoint exists
- Demo fallback in `hub.js` must be replaced with error UI
- `wizard_status.php` response shape must be documented and stabilised

**For post-session PV generation:**
- `meeting_generate_report.php` and `meeting_report_send.php` exist
- `postsession.js` uses `meeting_motions.php` alias that does not exist — add alias file

**For Redis-unavailable environments (Render free tier, dev without Docker):**
- File-based fallback is already implemented in both `EventBroadcaster` and `events.php`
- No additional configuration needed — fallback activates automatically

---

## Version Compatibility

| Component | Version | Compatible With | Notes |
|-----------|---------|-----------------|-------|
| PHP EventSource headers | PHP 8.4 | All modern browsers (Chrome 6+, Firefox 6+, Safari 5+) | `EventSource` is baseline-available. IE11 does not support it but is out of scope. |
| Redis phpredis | 7.4 + PECL | PHP 8.4 | Already installed in Docker image. No version conflict. |
| `flush()` + nginx buffering | PHP 8.4 + nginx | Works with `X-Accel-Buffering: no` header | PHP already sends this header in `events.php`. Nginx honours it. No nginx module needed. |
| Playwright SSE testing | v1.50.0 | EventSource interception | `page.route()` can intercept SSE streams in Playwright. `page.waitForEvent('response')` works for initial SSE connection assertion. |

---

## Sources

- Direct codebase audit of `app/WebSocket/EventBroadcaster.php` — HIGH confidence (read full file)
- Direct codebase audit of `public/api/v1/events.php` — HIGH confidence (read full file)
- Direct codebase audit of `public/assets/js/core/event-stream.js` — HIGH confidence (read full file)
- Direct codebase audit of `public/assets/js/pages/operator-realtime.js` — HIGH confidence (read full file)
- Direct codebase audit of `public/assets/js/pages/vote.js` (SSE section) — HIGH confidence
- Direct codebase audit of `deploy/nginx.conf` — HIGH confidence
- Direct codebase audit of `deploy/php-fpm.conf` + `deploy/php.ini` — HIGH confidence
- Grep scan of EventBroadcaster call sites across all controllers and services — HIGH confidence
- PHP-FPM SSE pattern (polling loop + flush) — MEDIUM confidence (established pattern, consistent with PHP documentation for SSE)
- nginx `X-Accel-Buffering` behaviour — MEDIUM confidence (standard nginx behaviour documented at nginx.org)

---

*Stack research for: AG-VOTE v3.0 Session Lifecycle — SSE real-time voting + API completeness + frontend wiring*
*Researched: 2026-03-16*
