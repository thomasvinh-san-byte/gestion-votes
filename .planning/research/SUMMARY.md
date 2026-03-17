# Project Research Summary

**Project:** AG-VOTE v3.0 — Session Lifecycle Wiring
**Domain:** General Assembly voting platform — full-stack integration (PHP + vanilla JS)
**Researched:** 2026-03-16
**Confidence:** HIGH

## Executive Summary

AG-VOTE v3.0 is not a greenfield build. It is a wiring milestone: the v2.0 UI is fully constructed, ~150 backend API endpoints exist, SSE real-time infrastructure is built end-to-end, and all major services (VoteEngine, MeetingWorkflowService, MeetingReportService, OfficialResultsService) are complete. What remains is a focused set of integration gaps — field name mismatches between the wizard and backend, demo data fallbacks that mask real errors, one missing endpoint alias, an nginx configuration gap, and a critical architectural flaw in how multiple SSE consumers share the same Redis event queue. None of these gaps require new libraries, framework changes, or new architecture. Every fix is a targeted modification to existing files.

The recommended approach is to work through the session lifecycle in dependency order: fix the data creation layer first (wizard payload → backend persistence), then eliminate demo fallbacks page by page replacing them with proper error states, then verify the SSE real-time pipeline end-to-end, then close out the post-session PV workflow. This ordering ensures each phase's output is verifiable before the next phase builds on top of it. The single most consequential architectural decision in this milestone is how to handle multiple concurrent SSE consumers (operator + room display + voter view) against the same meeting's Redis event queue — the current LRANGE+DEL pattern is destructive and will cause event loss under concurrent clients.

The primary risks are: (1) the multi-consumer SSE queue destruction pitfall, which must be solved before voter view and room display are wired concurrently with the operator console; (2) demo data fallbacks that remain in place after wiring, silently hiding integration failures; (3) PHP-FPM worker exhaustion if the SSE pool is not separated from the API request pool. All three risks are architectural/configuration issues with known solutions documented in the research. No risk requires external dependencies or fundamental design changes.

---

## Key Findings

### Recommended Stack

The existing stack is fully sufficient — no new packages, libraries, or infrastructure components are needed. The implementation uses PHP 8.4-FPM with a synchronous SSE polling loop (`set_time_limit(35)` + `flush()` iteration), Redis 7.4 for the per-meeting event queue, PostgreSQL 16 for persistence, and vanilla JS with `EventSource` for the browser SSE client. This is the established pattern for PHP-FPM SSE and it is already correctly implemented. Two infrastructure configuration changes are required but neither adds new dependencies: a dedicated nginx `location` block for `events.php` with `fastcgi_buffering off`, and documented `pm.max_children` guidance for the PHP-FPM pool to account for long-lived SSE workers.

**Core technologies:**
- PHP 8.4-FPM: SSE endpoint + all API handlers — synchronous polling loop is correct for this scale
- Redis 7.4: Per-meeting SSE event queue (`sse:events:{meeting_id}`) — with file fallback for dev/no-Redis environments
- PostgreSQL 16: All session state, ballot records, tally — fully wired via 27 repositories
- EventBroadcaster (custom): PHP-side SSE dispatch — covers all 9 event types, Redis + file fallback
- EventStream (custom JS): Browser SSE client — auto-reconnect, typed event listeners, polling fallback
- Dompdf v3.1.4: PV PDF generation — already in MeetingReportsController
- PHPUnit v10 + Playwright v1.50: Test coverage — SSE integration testable via existing tooling

### Expected Features

This milestone's MVP definition is zero demo fallbacks and a working end-to-end session lifecycle. Features are already designed and UI-complete; what changes is data source (real API vs. hardcoded demo objects).

**Must have (table stakes — v3.0 core):**
- Wizard creates real session in DB with members and resolutions persisted atomically
- Hub checklist reflects actual backend state — demo fallback removed, error state on failure
- Dashboard shows real session counts — demo fallback removed
- Operator console loads real meeting data via URL-propagated meeting_id
- Meeting state machine: all 6 transitions (draft → scheduled → frozen → live → closed → validated) work reliably
- SSE vote tally reaches operator console in real-time after ballots are cast
- Motion open/close cycle works: operator opens, voters see motion, operator closes
- Vote casting by voter: ballot cast, confirmation returned, SSE event fires
- Post-session stepper: verify results → validate → generate PV PDF → send/archive
- PV PDF generation via Dompdf is functional
- Audit demo fallback removed (audit.js DEMO_EVENTS)

**Should have (differentiators already built, need verification):**
- SSE with Redis + file fallback — real-time without WebSocket infrastructure
- Consolidated official results: `OfficialResultsService::consolidateMeeting()` called before validation
- Draft PV watermark: active when meeting not yet validated
- Weighted voting power in tally display
- Paper ballot / manual vote redemption by operator
- Device trust system (block/allow voter devices)

**Defer to v3.x:**
- invitations_stats.php completeness and placeholder removal in operator-tabs.js
- Explicit consolidate trigger in post-session flow UI
- eIDAS signatory storage in DB (UI exists, backend storage not implemented)
- SSE file fallback load tested with Redis disabled

**Defer to v4+:**
- Electronic signature upload/validation
- export_correspondance endpoint (referenced in postsession.js but does not exist — link must be removed in v3.0)

### Architecture Approach

The architecture is a conventional MVC PHP backend (Controller → Service → Repository → PostgreSQL) combined with vanilla JS page modules using the IIFE+var pattern for page scripts and the `window.OpS` shared object for operator console cross-module communication. The SSE pipeline runs: EventBroadcaster (PHP) → Redis list `sse:events:{meeting_id}` → events.php (polls Redis) → EventSource (browser) → operator-realtime.js / vote.js event handlers. The entire session lifecycle flows in one direction: wizard creates data → hub reads it → operator works with it → voter interacts → post-session reads results. The `MeetingContext` service propagates `meeting_id` via URL params and sessionStorage to ensure all pages and sub-modules reference the same meeting.

**Major components:**
1. `wizard.js` + `MeetingsController::createMeeting()` — entry point; must be extended to persist members[] and resolutions[] atomically
2. `hub.js` + `DashboardController::wizardStatus()` — session checklist; demo fallback must be replaced with error state; field name contract must be stabilised
3. `operator-tabs.js` + OpS bridge — tab container and cross-module state; meeting_id must propagate via MeetingContext
4. `operator-realtime.js` + `events.php` + EventBroadcaster — SSE pipeline; connectSSE() must fire on MeetingContext:change, not on page load
5. `postsession.js` + MeetingWorkflowController — PV workflow; single one-line bug (wrong endpoint name) blocks step 1
6. EventBroadcaster + Redis queue — must be refactored to support multiple concurrent consumers before voter view and room display are wired alongside the operator console

**Key patterns to follow:**
- Exception-based response flow: `api_ok()` / `api_fail()` both throw `ApiResponseException` — never `return` from controllers
- IIFE+var for page scripts, ES module classes for Web Components — never mix patterns
- OpS bridge stub pattern: add stub in operator-tabs.js before sub-module registers implementation
- `api()` call pattern: always gate on `res?.body?.ok` before accessing `res.body.data`
- MeetingContext for meeting_id propagation: never store meeting_id only in a local JS variable

### Critical Pitfalls

1. **Multi-consumer SSE queue destruction** — The Redis LRANGE+DEL pattern in events.php is destructive. When operator console, room display, and voter view all connect to `sse:events:{meetingId}` simultaneously, the first consumer drains the queue and the others receive nothing. Resolution: implement per-consumer keys (`sse:events:operator:{id}`, `sse:events:voter:{id}`) with EventBroadcaster pushing to all, OR switch to Redis Pub/Sub subscribe pattern in events.php. Must be fixed before concurrent consumer wiring.

2. **PHP-FPM worker exhaustion under SSE load** — Each SSE client holds one PHP-FPM worker for 30 seconds. Default `pm.max_children = 10` is exhausted by 10 concurrent SSE clients, blocking all API requests. Resolution: add a dedicated SSE FPM pool OR document that `pm.max_children` must be set to at least (max_voters + 10 headroom). Add a dedicated nginx location for events.php with `fastcgi_buffering off` and `fastcgi_read_timeout 35s`.

3. **Demo data fallbacks masking integration failures** — hub.js, dashboard.js, and audit.js silently fall back to hardcoded demo data on any API error. During v3.0 wiring this hides real bugs. Resolution: remove every demo fallback in the same commit that wires the real endpoint; replace with an explicit error state using the existing `ag-toast` component. Never remove a fallback without adding loading, error, and empty states.

4. **Wizard payload field name mismatch** — wizard.js sends `type`, `place`, `date`+`time` separately; backend expects `meeting_type`, `location`, `scheduled_at`. The backend silently discards the mismatched fields including the entire `members[]` and `resolutions[]` arrays — only the meeting title is persisted. Resolution: fix `buildPayload()` field mapping AND extend `createMeeting()` to handle members[] and resolutions[] in a single transaction.

5. **`window.OpS` bridge load-order fragility** — operator-realtime.js reads `window.OpS` on IIFE execution with no guard. Any new `O.fn.X()` call added during wiring will throw TypeError if the stub is not pre-registered in operator-tabs.js. Resolution: add guard at top of operator-realtime.js; always add stub in operator-tabs.js before the sub-module that implements it.

---

## Implications for Roadmap

Based on research, the lifecycle dependency chain is clear: data must be created before it can be read; the operator console depends on members/motions existing; SSE must work correctly before vote flows are tested; post-session depends on votes having been cast. This dictates a strictly ordered phase structure.

### Phase 1: Data Foundation — Wizard + Backend Persistence
**Rationale:** Everything downstream (hub, operator, votes, post-session) reads data that the wizard creates. The wizard currently only persists the meeting title. Members and resolutions are silently discarded. This must be fixed first or no subsequent phase can produce real data to verify.
**Delivers:** A wizard that creates a complete meeting record (meeting + members + motions) in a single atomic transaction; hub that shows real session state with no demo data
**Addresses:** Wizard creates real DB session, hub loads real session state, members and motions exist for operator
**Avoids:** Multi-round-trip wizard creation anti-pattern; silent field-name discard; demo data masking the wizard bug
**No research-phase needed** — patterns are well-documented; fix is targeted to buildPayload() + createMeeting()

### Phase 2: Demo Data Removal — Hub, Dashboard, Audit
**Rationale:** Demo fallbacks in hub.js, dashboard.js, and audit.js will mask integration errors in all subsequent phases. Removing them systematically before wiring downstream features ensures errors surface as visible error states, not as silently correct-looking fake data.
**Delivers:** Hub checklist driven entirely by wizard_status API; dashboard KPIs from real DB counts; audit trail showing real events. Every page shows an error state (not demo data) when the backend is unavailable.
**Addresses:** Dashboard shows real counts; audit demo fallback removed; convocation_status field added to wizard_status response
**Avoids:** Demo fallback masking pitfall; blank-screen-on-removal pitfall (loading/error/empty states required in same commit)
**No research-phase needed** — standard pattern: replace catch+fallback with catch+showError

### Phase 3: SSE Infrastructure Hardening
**Rationale:** The SSE pipeline is built but has two infrastructure gaps that will cause production failures: (1) no dedicated nginx location for events.php means buffering and rate-limit interference; (2) the LRANGE+DEL multi-consumer destruction problem blocks concurrent wiring of operator + voter view. Both must be resolved before any SSE-dependent features are validated.
**Delivers:** nginx events.php location with fastcgi_buffering off; PHP-FPM pool guidance documented; multi-consumer SSE strategy chosen and implemented (per-role keys or Redis Pub/Sub)
**Addresses:** SSE real-time infrastructure; nginx buffering; PHP-FPM pool exhaustion
**Avoids:** SSE worker exhaustion pitfall; nginx buffering pitfall; multi-consumer queue destruction pitfall
**Research flag:** The multi-consumer strategy choice (per-role keys vs Redis Pub/Sub) may benefit from a quick research-phase spike to confirm feasibility with phpredis blocking subscribe in the PHP-FPM lifecycle.

### Phase 4: Operator Console — Setup Mode + MeetingContext Wiring
**Rationale:** The operator console sub-modules (attendance, motions, exec) depend on meeting_id being correctly propagated from the hub URL param through MeetingContext to all sub-modules. This wiring must work before live vote testing, otherwise SSE connects to no meeting and all operator data loads fail.
**Delivers:** Meeting selector propagates meeting_id via MeetingContext.set(); attendance and motions tabs load real API data; SSE connectSSE() triggers on MeetingContext:change (not page load)
**Addresses:** Operator console loads real meeting; attendance registration; proxy management
**Avoids:** OpS bridge stub pattern must be followed for any new O.fn.X() calls; SSE connect before meeting context pitfall
**No research-phase needed** — patterns are defined in codebase; fix is targeted MeetingContext wiring

### Phase 5: Live Vote Flow — SSE Real-Time Tally
**Rationale:** The core product value. With SSE infrastructure hardened (Phase 3) and operator context wired (Phase 4), the vote cycle can be end-to-end tested: operator opens motion → tokens generated → voter casts ballot → SSE vote.cast event → operator tally updates in real-time.
**Delivers:** Full vote cycle working end-to-end; operator tally updates within 2s (SSE) or 5s (polling fallback); motion.opened/closed events switch operator view mode; meetingStatusChanged SSE call site added to MeetingWorkflowController
**Addresses:** Live vote tally via SSE; motions open/close; vote casting by voter; meeting state transitions
**Avoids:** vote.cast broadcast timing pitfall; operator poll interval not tripled when SSE connected; proxy vote tally accuracy
**No research-phase needed** — all components exist; this is integration verification

### Phase 6: Post-Session PV Workflow
**Rationale:** Post-session depends on votes having been cast (Phase 5) and results consolidated. One endpoint name bug (meeting_motions.php → motions_for_meeting.php) blocks step 1. The consolidate-before-validate requirement must be explicitly triggered. The export_correspondance link must be removed.
**Delivers:** Post-session stepper completes all 4 steps: verification (results table) → validation (closed→validated transition) → PV PDF generation (Dompdf) → send/archive. Zero broken links.
**Addresses:** Post-session verification shows results; validation state transition; PV generation; archive meeting; remove export_correspondance link
**Avoids:** PV available before validation pitfall; meeting_motions.php 404; export_correspondance 404; consolidate not called before validation
**No research-phase needed** — all endpoints exist; fixes are targeted one-liners plus one explicit consolidate call

### Phase 7: Final Verification + "Looks Done But Isn't" Audit
**Rationale:** A final sweep to confirm no demo data remains, all error states work, all SSE call sites fire correctly, and the full lifecycle can be completed end-to-end in an integration test.
**Delivers:** Zero DEMO_ constants in codebase; every API call site has loading/error/empty states; EventBroadcaster call coverage audit (all 9 event types have verified call sites); Playwright E2E test covering the full session lifecycle from wizard to archive
**Addresses:** All remaining P1/P2 items from feature prioritisation matrix
**Avoids:** All "looks done but isn't" checklist items from PITFALLS.md
**No research-phase needed** — this is verification and cleanup, not new implementation

### Phase Ordering Rationale

- Phase 1 must precede all others because no subsequent phase can verify real data flows if the wizard only persists a meeting title.
- Phase 2 (demo removal) must precede Phase 4-6 wiring work so that wiring bugs surface as visible errors, not as silent demo-data render.
- Phase 3 (SSE infrastructure) must precede Phase 5 (live vote SSE) because the multi-consumer destruction problem will cause intermittent test failures if multiple browser contexts are used during testing.
- Phases 4, 5, 6 follow the natural session lifecycle order: setup → live vote → post-session.
- Phase 7 is a gate-check that can run after all prior phases are complete.

### Research Flags

Phases likely needing deeper research during planning:
- **Phase 3 (SSE multi-consumer strategy):** The choice between per-role Redis keys vs. Redis Pub/Sub blocking subscribe in PHP-FPM deserves a focused spike. The phpredis `subscribe()` call blocks — this works inside the SSE loop but requires testing to confirm it does not conflict with the existing `LPUSH` broadcast pattern.

Phases with standard patterns (skip research-phase):
- **Phase 1:** Extending createMeeting() to handle optional arrays in a transaction is standard service-layer PHP. Pattern matches existing IdempotencyGuard usage.
- **Phase 2:** Replacing catch+fallback with catch+showError is a mechanical pass with no novel patterns.
- **Phase 4:** MeetingContext wiring follows the documented pattern in ARCHITECTURE.md.
- **Phase 5:** All SSE components are built. Integration verification only.
- **Phase 6:** All endpoints exist except the endpoint name fix. One-line change + consolidate call.
- **Phase 7:** Audit and E2E test coverage. No new patterns required.

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All findings verified against actual codebase files. No new packages required. Configuration gaps are confirmed against deploy/nginx.conf and deploy/php-fpm.conf. |
| Features | HIGH | Direct codebase inspection of all ~150 endpoints, all page JS modules, and all service/controller files. Critical gaps (meeting_motions.php, export_correspondance.php, demo fallbacks) verified by grep. |
| Architecture | HIGH | Component responsibilities, data flows, and integration points verified against routes.php, ValidationSchemas.php, and direct file reads. Field name mismatches confirmed against actual code. |
| Pitfalls | HIGH | All pitfalls derived from direct codebase inspection. SSE multi-consumer issue confirmed by reading the LRANGE+DEL pipeline in events.php. PHP-FPM pool size confirmed from php-fpm.conf. |

**Overall confidence:** HIGH

### Gaps to Address

- **SSE multi-consumer architecture choice:** The per-role key approach (Option C) is the lowest-risk change but requires EventBroadcaster to push to 3 keys per event. The Redis Pub/Sub approach (Option A) is architecturally cleaner but requires phpredis `subscribe()` inside the SSE loop — needs a spike to confirm this works correctly under PHP-FPM request lifecycle constraints. Recommend resolving in Phase 3 planning.

- **invitations_stats.php completeness:** operator-tabs.js line 2799 has a comment noting the endpoint may not return complete data. The file exists but its response contract is unverified. This should be checked during Phase 4 operator console wiring.

- **eIDAS signatory storage:** postsession.js step 3 shows a signataire/observations form but no backend storage is implemented. Deferred to v3.x but should be documented as a known gap so the UI is not promoted as functional.

- **Consolidate trigger placement:** Whether consolidate should be called automatically at motion close (operator exec), explicitly in post-session step 2, or both. Currently not triggered anywhere in the post-session flow. Phase 6 must decide the UX for this.

---

## Sources

### Primary (HIGH confidence — direct codebase inspection)
- `app/WebSocket/EventBroadcaster.php` — SSE event dispatch architecture and Redis queue pattern
- `public/api/v1/events.php` — SSE endpoint implementation, LRANGE+DEL pattern, Redis polling loop
- `public/assets/js/core/event-stream.js` — browser SSE client, reconnect logic
- `public/assets/js/pages/operator-realtime.js` — SSE + polling integration, OpS bridge usage
- `public/assets/js/pages/hub.js` — demo data fallback pattern (DEMO_SESSION, DEMO_FILES)
- `public/assets/js/pages/wizard.js` — payload field names, post-creation redirect
- `public/assets/js/pages/postsession.js` — wrong endpoint call (meeting_motions.php)
- `app/Controller/MeetingsController.php` + `ValidationSchemas.php` — wizard payload mismatch
- `app/Controller/DashboardController.php` — wizardStatus() response shape
- `deploy/nginx.conf` — no dedicated events.php location block
- `deploy/php-fpm.conf` — pm.max_children = 10, request_terminate_timeout = 60s
- `app/routes.php` — complete endpoint inventory
- `.planning/codebase/ARCHITECTURE.md` — system architecture documentation
- `.planning/milestones/v2.0-phases/14-wizard-hub-dashboard-api/14-VERIFICATION.md` — Phase 14 state

### Secondary (MEDIUM confidence — established patterns)
- PHP-FPM SSE polling loop pattern (set_time_limit + flush()) — consistent with PHP documentation
- nginx X-Accel-Buffering header behaviour — standard nginx FastCGI documented behaviour
- Redis LPUSH/LRANGE/DEL as single-consumer queue pattern — established PHP SSE approach

### External (industry context)
- French regulatory: [PV AG Copropriété — Service Public](https://www.service-public.fr/particuliers/vosdroits/F2636)
- [GetQuorum Features](https://www.getquorum.com/features/) — competitor feature landscape
- [SSE vs WebSockets vs Long Polling 2025 — DEV Community](https://dev.to/haraf/server-sent-events-sse-vs-websockets-vs-long-polling-whats-best-in-2025-5ep8)

---
*Research completed: 2026-03-16*
*Ready for roadmap: yes*
