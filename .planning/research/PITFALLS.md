# Pitfalls Research

**Domain:** Full-stack wiring — SSE real-time + API completion + demo data removal for PHP + vanilla JS voting platform
**Researched:** 2026-03-16
**Confidence:** HIGH (all findings derived from direct codebase inspection)

---

## Critical Pitfalls

### Pitfall 1: SSE Worker Exhaustion — nginx Has No Dedicated Location for events.php

**What goes wrong:**
`events.php` holds a PHP-FPM worker open for 30 seconds per client. With `pm.max_children = 10`, just 10 concurrent SSE connections saturate the entire worker pool. All other requests — vote submissions, attendance updates, the operator console — queue behind them. During a live meeting, the operator console (1 worker), room display (1 worker), and every voter view (1 worker each) would consume the entire pool in seconds.

**Why it happens:**
The nginx config has no dedicated location block for `events.php`. It falls into the generic `~ \.php$` location with `fastcgi_read_timeout 60s` and the `zone=api burst=20` rate limit. There is no `fastcgi_buffering off` directive or `proxy_buffering off` (matters when nginx sits in front). The PHP-FPM pool is sized for short-lived API requests, not long-polling workers.

**How to avoid:**
1. Add a dedicated nginx location for `/api/v1/events.php` with `fastcgi_read_timeout 35s` (matches `set_time_limit(35)` in the script), `fastcgi_buffering off`, and `add_header X-Accel-Buffering no` (the PHP script already sets this header, but the nginx location should reinforce it).
2. Add a separate PHP-FPM pool (`sse` pool) with its own `pm.max_children` limit dedicated to SSE connections, preventing SSE from starving the API pool.
3. Or cap `pm.max_children` at a value accounting for SSE connections: `(total_workers - max_concurrent_sse_clients)`.

**Warning signs:**
- Vote submission requests start returning 504 during live meetings
- `php-fpm` access log shows queue wait times climbing
- `events.php` shows in `ps aux` holding all worker slots
- Operator console poll fails silently (no error surface in UI)

**Phase to address:**
SSE infrastructure phase — before any real-time wiring reaches production.

---

### Pitfall 2: Demo Data Fallback Silently Masks Real Backend Failures

**What goes wrong:**
Several page scripts (`hub.js`, `dashboard.js`) catch API errors and fall back to hardcoded demo data with only a `console.warn`. When the backend has a real bug — a missing `wizard_status` endpoint field, a 500 from a broken migration, a permissions error — the page renders successfully with fake data. The developer sees a working UI and misses the backend error entirely.

In `hub.js` (line 414): `console.warn('Hub loadData: API call failed, falling back to demo data.')` — this is indistinguishable from "backend unavailable" vs "backend returned malformed response" vs "migration broke the column".

**Why it happens:**
Demo data was added during the v2.0 UI phase to let the UI be built independently of backend completeness. The pattern made sense then. Now that v3.0 is wiring the two together, these fallbacks invert into a debugging obstacle.

**How to avoid:**
Remove demo data fallbacks in a systematic pass before wiring, not after. The replacement for a demo fallback is an error state rendered in the UI (e.g., "Impossible de charger les données de la séance") plus a visible toast. Use the existing `ag-toast` component for this. Never swallow API errors silently.

Pattern to eliminate:
```javascript
} catch (e) {
    console.warn('...falling back to demo data.', e); // DELETE this branch
    renderWithDemoData();                              // DELETE this call
}
```

Pattern to replace with:
```javascript
} catch (e) {
    showErrorState('Erreur lors du chargement des données: ' + (e.message || 'Erreur réseau'));
    return;
}
```

**Warning signs:**
- Any `console.warn` containing "fallback" or "demo" in page JS files
- `DEMO_SESSION`, `DEMO_FILES`, `DEMO_*` constant declarations in page scripts
- `showFallback()` function definitions in page scripts
- A page renders content even when the API endpoint returns 500

**Phase to address:**
First wiring phase for each page — remove demo fallback before wiring the real endpoint, not as a cleanup step afterward.

---

### Pitfall 3: `vote_cast` SSE Event Not Broadcast on Actual Voter Submit Path

**What goes wrong:**
`BallotsService::castBallot()` correctly calls `EventBroadcaster::voteCast()` (line 207). However, `BallotsController::cast()` calls `(new BallotsService())->castBallot($data)` and then returns — it does NOT call any broadcast itself. This is correct by design. But if the `BallotsService` broadcast fails (Redis unavailable, exception), the `error_log()` at line 209 swallows it silently and the vote.js SSE event listener never fires.

The result: the voter's ballot is persisted, but the operator console does not see the real-time tally update. The operator must wait for the polling cycle (5s interval) to see the vote.

**Why it happens:**
Broadcast failures are treated as non-fatal (which is correct — the vote must be persisted), but there is no mechanism to notify the SSE client that broadcast failed and it should poll immediately. The polling interval of 5s with SSE active (`POLL_FAST * 3 = 15s` in operator-realtime.js when SSE is connected) means operators can be 15 seconds behind on tallies when Redis has transient issues.

**How to avoid:**
Keep the swallow-and-log pattern for broadcast failures (vote integrity matters more than real-time). But in `operator-realtime.js`, do not triple the polling interval when SSE is connected — keep the fallback poll at `POLL_FAST` (5s) regardless of SSE state. SSE should trigger *additional* refreshes, not suppress polling.

Also verify: the `vote.cast` event data structure emitted by `EventBroadcaster::voteCast()` is `{ motion_id, tally }`. The `operator-realtime.js` handler reads `data.motion_id || data.data.motion_id`. This handles the envelope wrapping. Confirm the `event-stream.js` `pollEvents()` function returns the event as `{ type: 'vote.cast', data: { motion_id, tally } }` not as `{ type, motion_id, tally }` directly — the nesting must be consistent.

**Warning signs:**
- Operator tally doesn't update for 10-15 seconds after a vote in testing
- Redis `KEYS sse:events:*` shows no entries during voting
- `error_log` shows `[WebSocket] Broadcast failed` messages

**Phase to address:**
SSE + vote flow integration phase.

---

### Pitfall 4: PHP-FPM `request_terminate_timeout = 60s` Will Kill Long SSE Connections Before nginx Timeout

**What goes wrong:**
`events.php` calls `set_time_limit(35)` and loops for 30 seconds. PHP-FPM's `request_terminate_timeout = 60s` should be fine. However, if the PHP execution time is not reset after a sleep cycle (PHP's `sleep()` counts toward `max_execution_time` only when not in safe mode — complex behavior), and if the system is under load causing slow iteration, the SSE connection can be killed mid-stream with no `reconnect` event sent, leaving the client in a broken state.

More critically: nginx's generic PHP location has `fastcgi_read_timeout 60s`. If nginx decides it has read enough of the response (keepalive comments count as partial data), the connection closes at 60s. The `events.php` sends a `reconnect` event at 30s. If nginx buffers the keepalive comments and only flushes when its buffer fills, the client receives nothing until nginx flushes — defeating the point of SSE.

**Why it happens:**
nginx buffering is not disabled for the generic PHP location. The `X-Accel-Buffering: no` header set in `events.php` disables nginx's proxy buffering for that response, but only if nginx is configured to honour it (FastCGI responses respect this header by default in nginx 1.7.3+, but it must be explicitly enabled with `fastcgi_ignore_headers X-Accel-Buffering;` NOT set — i.e., do not ignore it).

**How to avoid:**
Add a dedicated nginx location for `events.php`:
```nginx
location = /api/v1/events.php {
    fastcgi_pass 127.0.0.1:9000;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
    fastcgi_read_timeout 35s;
    fastcgi_buffering off;
}
```
This ensures buffering is definitely off and the timeout matches the PHP script duration.

**Warning signs:**
- SSE connections drop at exactly 60s in production but work locally
- Operator console shows "SSE disconnected" every 60s
- Browser DevTools shows SSE stream receives no data until a large burst

**Phase to address:**
SSE infrastructure phase — nginx configuration before SSE is used in production.

---

### Pitfall 5: The `window.OpS` Bridge Has No Guard — Sub-Module Load Order Is Silently Fragile

**What goes wrong:**
`operator-realtime.js` line 8 reads `var O = window.OpS;` and line 215 calls `connectSSE()` and `schedulePoll()` immediately on IIFE execution. If `operator-tabs.js` has not yet executed (e.g., if script order changes, or a developer adds the script without understanding the dependency), `O` is `undefined` and every call to `O.currentMeetingId`, `O.fn.loadResolutions()`, etc. throws a TypeError. There is no defensive guard.

When wiring new SSE events or adding new SSE-triggered data loaders in `operator-realtime.js`, it is easy to add `O.fn.someNewFunction()` without realising it must also be stubbed in `operator-tabs.js` (line 1989 "Delegating stubs") before the sub-module that implements it registers.

**Why it happens:**
The `window.OpS` bridge was designed for a specific load order that is enforced by HTML script tag sequence, not by any runtime check. Adding sub-modules during v3.0 wiring will feel natural but can silently break existing integrations if the stub pattern is not followed.

**How to avoid:**
Before adding any new `O.fn.X()` call in `operator-realtime.js` or any operator sub-module:
1. Add the stub in `operator-tabs.js` in the "Delegating stubs" section (line 1989).
2. Have the implementing sub-module overwrite it on `O.fn.X = function(...) {...}` in its module init.
3. Add a guard at the top of `operator-realtime.js`: `if (!window.OpS) { console.error('[operator-realtime] OpS not loaded'); return; }`.

**Warning signs:**
- TypeError in console: "Cannot read properties of undefined (reading 'currentMeetingId')"
- SSE connects but handleSSEEvent does nothing
- New function works in isolation but not when all scripts load together

**Phase to address:**
Every phase that modifies operator console scripts must follow this pattern.

---

### Pitfall 6: SSE Connection Per Page Means Room Display and Voter View Are Each Blind to Others' Events

**What goes wrong:**
`vote.js` (voter view) connects SSE with `EventStream.connect(selectedMeetingId, ...)`. Room display likely does the same. Each connection independently polls `sse:events:{meetingId}` in Redis via a LRANGE + DEL pipeline — the **del** is atomic. If two SSE clients for the same meeting poll simultaneously, the first one gets all events and deletes them; the second gets nothing.

This is a fundamental design flaw in the current SSE implementation. The Redis `LRANGE + DEL` pipeline in `events.php` lines 159-161 is destructive — it's a queue drain, not a pub/sub pattern. Multiple consumers for the same meeting destroy each other's event visibility.

**Why it happens:**
The implementation was designed assuming one SSE consumer per meeting (the operator). But v3.0 requires SSE for operator console, room display, AND voter view simultaneously — all three connecting to the same meeting SSE queue and competing to drain it.

**How to avoid:**
Replace the destructive LRANGE + DEL pattern with a per-consumer offset approach, or use Redis Pub/Sub properly. Options in order of complexity:

Option A (lowest change): Use Redis `SUBSCRIBE` on a per-meeting channel instead of polling a list. Each SSE client subscribes; the broadcaster publishes. No client destroys another's events. Requires PHP's `Redis::subscribe()` which blocks — works in SSE's long-poll model.

Option B (medium change): Use a per-consumer cursor. Store the last consumed index per SSE connection. Use `LRANGE key cursor -1` without deleting. Add a TTL-based eviction on the list. The broadcaster trims to last 100 events; consumers read from their offset.

Option C (pragmatic hack for v3.0): Separate SSE keys per consumer type: `sse:events:operator:{meetingId}`, `sse:events:room:{meetingId}`, `sse:events:voter:{meetingId}`. The broadcaster pushes to all three. Requires knowing the consumer type at connection time (pass `?role=operator` etc. to `events.php`).

**Warning signs:**
- Room display misses motion.opened events when tested with operator console open simultaneously
- vote.js SSE handler fires for some votes but not others during testing with multiple browser tabs
- Inconsistency in real-time behavior depending on how many clients are connected

**Phase to address:**
SSE architecture phase — must be resolved before wiring voter view and room display concurrently. This is the highest-priority SSE pitfall for v3.0.

---

### Pitfall 7: Demo Data Removal Without Error State UI Leaves Blank Screens

**What goes wrong:**
When demo data fallbacks are removed, any API call that fails or returns unexpected data leaves the UI blank — no error message, no loading indicator, just an empty container. This is worse UX than the demo data it replaced. It is also a testing blind spot: a blank screen in a test does not fail the E2E test unless the test explicitly asserts element content.

**Why it happens:**
The existing page scripts render into containers via `innerHTML`. When the fallback is removed, the "no data" case is not handled. E2E tests using `expect(element).toBeVisible()` pass even when the element is visible but empty.

**How to avoid:**
For every demo fallback removed, add an explicit error state:
1. A loading state (spinner/skeleton while API call is in flight)
2. An error state (toast + empty-state message if API fails)
3. An empty state (empty-state message if API succeeds but returns no data)

Use the existing `ag-toast` component for transient errors. Add `[data-loading]` attribute during fetch and remove it on completion. E2E tests should assert the actual content text, not just visibility.

**Warning signs:**
- Blank containers in the UI with no console errors (fallback removed but no error state added)
- E2E tests pass on pages that are actually showing empty containers
- Operator reports "the page is empty" during testing

**Phase to address:**
Every demo data removal phase — error state must be implemented in the same PR as the fallback removal.

---

### Pitfall 8: `api()` Returns `{ status: 0, body: {...} }` on Timeout/Network Error — Not All Call Sites Check `status`

**What goes wrong:**
The global `api()` function (utils.js line 661-668) returns `{ status: 0, body: { ok: false, error: 'timeout' } }` on abort/network error rather than throwing. Many call sites check only `res.body.ok` or `res.status === 200`. If a site checks `if (res && res.body && res.body.ok)` (as in hub.js line 402), a timeout returns `{ status: 0, body: { ok: false } }` which hits the fallback branch — but after removing the demo fallback, it hits the error state. This is the correct behaviour.

The trap is call sites that destructure directly: `const { data } = res.body` without checking `ok` first. A timeout returns `body: { ok: false, error: 'timeout', message: '...' }` which has no `data` field. `data` is `undefined` and downstream code crashes with "Cannot read properties of undefined".

**Why it happens:**
The `api()` function was designed to never throw (resilient by default). But this means callers must handle the "status 0" case everywhere. Not all call sites were written with this in mind, and adding new call sites during v3.0 wiring will repeat the mistake.

**How to avoid:**
Establish a standard call pattern for all v3.0 wiring work:
```javascript
const res = await api('/api/v1/some_endpoint.php?meeting_id=' + encodeURIComponent(id));
if (!res || !res.body || !res.body.ok) {
    showError(res?.body?.message || 'Erreur lors du chargement');
    return;
}
const data = res.body.data; // safe to access now
```

Never destructure `res.body` without checking `ok` first.

**Warning signs:**
- "Cannot read properties of undefined (reading 'someField')" TypeError in console after a slow network or timeout
- Page works normally but crashes intermittently under network throttling
- Tests pass on fast network, fail on CI with simulated latency

**Phase to address:**
All API wiring phases — enforce the pattern in code review.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Keep demo data fallbacks during partial wiring | UI renders during incremental backend work | Masks real errors, hides regressions | During v2.0 UI-only phases — not acceptable in v3.0 |
| `console.warn` instead of user-visible error state | Less UI work | Users see blank screens; errors invisible in production | Never in user-facing flows |
| `error_log()` in broadcast failure handlers | Simple | Unstructured, no context, missed in log aggregation | Replace with `Logger::warning()` in the same PR |
| Single PHP-FPM pool for both SSE and API requests | Simpler config | SSE starves API workers | Never in production with concurrent SSE clients |
| Inline `DEMO_*` constants left in production code | "Safe" fallback | Makes backend errors invisible; breaks audit correctness | Remove before go-live |
| Triple poll interval when SSE is connected | Reduces server load | Extends operator blindness when Redis has transient issue | Acceptable only if SSE reliability is verified |

---

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| SSE + nginx FastCGI | Using generic PHP location — nginx buffers SSE stream | Dedicated `events.php` location with `fastcgi_buffering off` |
| SSE + Redis multi-consumer | LRANGE+DEL drains queue, second consumer gets nothing | Use Redis Pub/Sub channels or per-consumer offset keys |
| EventBroadcaster + `publishToSse` | Forgetting it writes to `sse:events:{meetingId}` not the WS queue | `voteCast` writes to BOTH `ws:event_queue` AND `sse:events:{meetingId}` |
| `window.OpS` bridge | Adding `O.fn.newFunc()` call without adding a stub first | Always add stub in operator-tabs.js before sub-module registers implementation |
| `api()` global + error handling | Destructuring `res.body.data` without checking `ok` first | Always gate on `res?.body?.ok` before accessing `data` |
| Demo fallback removal | Removing fallback without adding error/loading/empty states | Three states required: loading, error, empty — in same commit |
| `wizard_status` endpoint data shape | hub.js has `mapApiDataToSession()` that normalises field names — adding new fields to the API response won't automatically appear in the hub KPIs | Add new fields to `mapApiDataToSession()` explicitly |
| PV generation | Triggering PV export before `validated_at` is set | The meeting must be in `ended` status AND `validated_at` must be set — verify both in frontend before enabling PV download |

---

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| SSE connections saturating PHP-FPM pool | All API calls hang during live meeting | Dedicated SSE FPM pool or separate worker count | At 6+ concurrent SSE clients with `pm.max_children = 10` |
| autoPoll running full loadResolutions + loadDashboard + loadDevices every 15s with SSE active | High DB query count; Redis 404 on every poll | Move to event-driven refresh; only poll when SSE is disconnected | At 5+ concurrent operators on same meeting |
| Redis LRANGE+DEL for multiple SSE consumers | Some consumers miss events intermittently | Per-consumer keys or Pub/Sub | As soon as 2+ SSE clients connect to same meeting |
| File-based SSE fallback under concurrent PHP-FPM workers | Events silently dropped on flock failure | Ensure Redis is available in all environments that run concurrent SSE | When Redis is down and >2 PHP-FPM workers handle SSE simultaneously |
| `pm.max_requests = 500` recycling workers mid-SSE | SSE connection dropped every ~500 requests (varies by load) | SSE connections should not share pool with recycled workers | When SSE pool is under continuous heavy load |

---

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| `events.php` only validates meeting_id UUID format, not tenant isolation | A voter from Tenant A could connect to Tenant B's SSE stream if they know the meeting UUID | After auth check, verify the meeting belongs to the authenticated user's tenant |
| Removing demo data but leaving `admin_reset_demo.php` endpoint accessible in staging | Staging data can be reset by any operator-level user | Ensure DevSeedController's env check also blocks `staging` and `demo` environments |
| SSE keepalive comments contain no data — but if a future change adds data to them, XSS via meeting title injection is possible | Low risk currently; higher risk if event data is ever injected into comments | Keep SSE comment lines as `: keepalive\n\n` with no dynamic content |
| `error_log()` calls in broadcast failure handlers include exception messages that may contain tenant or meeting data | Log injection, data leakage in unstructured logs | Replace all `error_log()` with `Logger::warning()` in broadcast failure handlers |

---

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Removing demo data without loading skeleton | Page feels broken during API fetch — blank flash before data appears | Add CSS skeleton screens or a loading spinner before the first API call resolves |
| SSE disconnect not surfaced in operator console | Operator doesn't know they've lost real-time; may miss critical vote events | Show a subtle "temps réel déconnecté — actualisation auto" banner when `sseConnected` goes false |
| Blank voter view if `current_motion.php` returns 404 (no open motion) | Voter thinks the app is broken | Render "Aucun vote en cours" empty state explicitly |
| Error toast disappears before operator reads it | Operator misses a failed action during a fast-paced meeting | Use persistent toasts for errors in operator console (require manual dismiss) |
| PV download button active before meeting is validated | Operator clicks, gets an error; confusing during stressful post-session | Disable PV download until `validated_at` is set; show tooltip "Validation requise" |

---

## "Looks Done But Isn't" Checklist

- [ ] **SSE real-time:** Operator console, room display, and voter view all connected simultaneously — verify events reach ALL consumers, not just the first one to poll.
- [ ] **Demo data removed:** Search for `DEMO_SESSION`, `DEMO_FILES`, `DEMO_`, `showFallback`, "falling back to demo" — zero occurrences required before go-live.
- [ ] **Error states:** Every API call site that previously fell back to demo data now renders a visible error message to the user on failure.
- [ ] **nginx SSE config:** `fastcgi_buffering off` is set for `events.php` — verify with `curl -N` that events stream in real-time and are not buffered.
- [ ] **PHP-FPM pool:** Confirm SSE connections don't exhaust the pool — run `ab -c 8 -n 8 /api/v1/events.php` (8 concurrent SSE connects) and verify normal API requests still respond.
- [ ] **EventBroadcaster call coverage:** Every state-changing controller action that should trigger SSE (`ballot.cast`, `motion.open/close`, `attendance.update`, `meeting.status_change`) calls the correct `EventBroadcaster` method.
- [ ] **Hub checklist reflects real state:** `wizard_status` endpoint returns all fields needed to compute each checklist step (members added, convocations sent, motions created) — not just the meeting title.
- [ ] **Proxy votes in tally:** When a voter casts via proxy, `EventBroadcaster::voteCast()` is called with the correct `is_proxy_vote` tally — operator console and room display show accurate voix count.
- [ ] **PV generation:** `export_pv_html.php` actually returns a valid HTML document for a completed meeting — not a 500 or an empty document.
- [ ] **Session survival:** All state survives a page reload — meeting_id from URL param, selected member, current motion state — nothing is stored only in JS memory.

---

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| SSE pool exhaustion in production | MEDIUM | Restart PHP-FPM to release blocked workers; add dedicated SSE pool as hotfix; reduce `pm.max_children` temporarily |
| Demo fallback masked a broken migration | HIGH | Run `database/migrations/` manually; check `applied_migrations` table; re-run failed migration; verify data integrity |
| Multiple SSE consumers destroying each other's events | HIGH | Disable SSE (`PUSH_ENABLED=false`) to fall back to polling; implement per-consumer keys; re-enable SSE |
| `api()` timeout on vote submission during live meeting | LOW | The vote is already persisted (timeout happens after DB write, during broadcast); operator uses manual vote log to verify; investigate Redis connectivity |
| `window.OpS` bridge crash from missing stub | LOW | Add stub to operator-tabs.js, deploy; no data loss; issue is JS TypeError only |

---

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| SSE worker exhaustion (PHP-FPM pool) | SSE infrastructure phase | Run 10 concurrent curl SSE connections; verify API still responds |
| SSE buffering (no nginx location for events.php) | SSE infrastructure phase | `curl -N` shows events streamed in real-time, not buffered |
| Multi-consumer SSE queue destruction | SSE architecture phase (before voter/room wiring) | Open operator + room display + voter view simultaneously; all receive motion.opened event |
| Demo data fallback masking errors | First wiring phase for each page | Search codebase for DEMO_ constants and showFallback — zero hits |
| Missing error states after demo removal | Same phase as demo removal | API endpoint returns 500; UI shows error message (not blank screen) |
| `api()` destructure without ok-check | All API wiring phases | Network throttle to 0; no TypeErrors in console |
| `window.OpS` stub missing | Every operator console wiring phase | Load operator page with all scripts; no TypeError in DevTools |
| `vote.cast` broadcast timing | Vote flow integration phase | Cast vote; operator tally updates within 2s (SSE) or 5s (poll) |
| PV available before validation | Post-session wiring phase | Meeting in `ended` without `validated_at`; PV button disabled |
| Proxy vote tally accuracy | Proxy delegation wiring phase | Cast proxy vote; operator sees voix count updated, not member count only |

---

## Sources

- Direct codebase inspection: `/home/user/gestion-votes/public/api/v1/events.php` — SSE implementation and Redis polling logic
- Direct codebase inspection: `/home/user/gestion-votes/app/WebSocket/EventBroadcaster.php` — queue architecture (destructive LRANGE+DEL per meeting)
- Direct codebase inspection: `/home/user/gestion-votes/public/assets/js/pages/operator-realtime.js` — SSE connection and polling intervals
- Direct codebase inspection: `/home/user/gestion-votes/public/assets/js/pages/vote.js` — voter SSE connection pattern
- Direct codebase inspection: `/home/user/gestion-votes/public/assets/js/pages/hub.js` (lines 301-430) — demo data fallback pattern
- Direct codebase inspection: `/home/user/gestion-votes/public/assets/js/pages/dashboard.js` — `showFallback()` pattern
- Direct codebase inspection: `/home/user/gestion-votes/deploy/nginx.conf` — no dedicated events.php location
- Direct codebase inspection: `/home/user/gestion-votes/deploy/php-fpm.conf` — `pm.max_children = 10`, `request_terminate_timeout = 60s`
- Direct codebase inspection: `/home/user/gestion-votes/app/Services/BallotsService.php` (line 207) — voteCast broadcast location
- Direct codebase inspection: `/home/user/gestion-votes/.planning/codebase/CONCERNS.md` — file-based fallback fragility, window.OpS bridge fragility
- Direct codebase inspection: `/home/user/gestion-votes/public/assets/js/core/utils.js` (lines 627-671) — api() error handling contract

---
*Pitfalls research for: AG-VOTE v3.0 — SSE + API wiring + demo data removal on PHP + vanilla JS*
*Researched: 2026-03-16*
