# Phase 23: Integration Wiring Fixes - Research

**Researched:** 2026-03-18
**Domain:** Cross-phase JavaScript/PHP wiring — hub navigation handoff + frozen-to-live meeting state machine
**Confidence:** HIGH

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| HUB-01 | Le hub charge l'état réel de la session via l'API wizard_status (zéro donnée démo) — gap: meeting_id not forwarded to action button destinations | Fix: append `?meeting_id=` to `dest` URLs in `HUB_STEPS`; operator page's `MeetingContext.init()` reads URL param and auto-selects the meeting |
| VOT-01 | L'opérateur peut ouvrir une motion et les votants voient la motion active — gap: frozen meeting skips meetingStatusChanged SSE | Fix: call `operator_open_vote.php` (OperatorController::openVote) instead of `motions_open.php` when `isFrozen`; the PHP controller already handles the frozen→live transition and SSE broadcast |
| VOT-04 | Les transitions d'état machine (frozen→live) fonctionnent de bout en bout — same root cause as VOT-01 | Fix is identical to VOT-01: route through `operator_open_vote.php` which writes `status = 'live'` inside a transaction then fires `EventBroadcaster::meetingStatusChanged` after commit |
</phase_requirements>

---

## Summary

Phase 23 fixes two cross-phase integration breaks discovered by the v3.0 milestone audit. Both gaps are wiring bugs — the backend PHP logic already exists and works; the frontend JavaScript is calling the wrong endpoint or omitting a URL parameter.

**Gap 1 — HUB-01:** `hub.js` correctly loads session state from `wizard_status` API. When the user clicks an action button (steps 3 "Pointage" or 4 "Voter"), the destination URL is hardcoded as `/operator.htmx.html` with no `meeting_id` parameter. The operator page's `MeetingContext.init()` reads `?meeting_id=` from the URL or from `sessionStorage`. If neither is present, the dropdown shows empty and the user must manually select a meeting. The fix is to append `?meeting_id={sessionId}` to the `dest` field for the two operator-bound steps in `HUB_STEPS`, using the `sessionId` already retrieved by `loadData()`.

**Gap 2 — VOT-01 / VOT-04:** `operator-motions.js::openVote()` always calls `motions_open.php` (MotionsController::open). That endpoint opens the motion and fires `motionOpened` SSE — but it never touches meeting status. When the meeting is `frozen`, it should instead call `operator_open_vote.php` (OperatorController::openVote), which atomically transitions the meeting to `live` AND opens the motion AND broadcasts `meetingStatusChanged` via SSE. The `isFrozen` boolean is already computed on line 745 — it just needs to gate which endpoint is called.

**Gap 3 (audit note) — shim file:** The audit flagged that `public/api/v1/operator_open_vote.php` might be missing. It exists and already contains the correct one-liner shim routing to `OperatorController::openVote`. No action needed.

**Primary recommendation:** Two targeted JS changes — one in `hub.js` (append meeting_id to action button destinations), one in `operator-motions.js` (branch on `isFrozen` to choose the correct endpoint).

---

## Standard Stack

### Core (no new dependencies)

| Component | File | Purpose |
|-----------|------|---------|
| Hub page JS | `public/assets/js/pages/hub.js` | Renders HUB_STEPS, action buttons, loads session via wizard_status |
| MeetingContext service | `public/assets/js/services/meeting-context.js` | Singleton: URL param → sessionStorage → onChange → loadMeetingContext |
| Operator tabs JS | `public/assets/js/pages/operator-tabs.js` | Consumes MeetingContext; auto-loads meeting when `?meeting_id=` is in URL |
| Operator motions JS | `public/assets/js/pages/operator-motions.js` | `openVote()` function — calls motions_open.php today |
| MotionsController::open | `app/Controller/MotionsController.php` | Opens motion only; no meeting status change |
| OperatorController::openVote | `app/Controller/OperatorController.php` | Atomically opens motion + transitions meeting to `live` + SSE broadcast |
| PHP shim | `public/api/v1/operator_open_vote.php` | Exists; routes to OperatorController::openVote |
| routes.php | `app/routes.php` | `operator_open_vote` registered at line 285 with `['role' => 'operator']` |

**Installation:** No new packages required.

---

## Architecture Patterns

### Recommended Project Structure

No new files required. All changes are in existing files:

```
public/assets/js/pages/
├── hub.js                  # Gap 1 fix: append meeting_id to dest URLs
└── operator-motions.js     # Gap 2 fix: branch on isFrozen to call operator_open_vote.php

public/api/v1/
└── operator_open_vote.php  # Already exists — NO CHANGE needed
```

### Pattern 1: Hub meeting_id propagation

**What:** After `loadData()` resolves with a valid `sessionId`, the `HUB_STEPS` entries that navigate to `/operator.htmx.html` must include `?meeting_id={sessionId}` in `dest`.

**When to use:** Steps with `dest: '/operator.htmx.html'` (presences step #3, vote step #4).

**How MeetingContext consumes it (HIGH confidence — read from source):**

```javascript
// meeting-context.js lines 33-43
// Priority: URL param > sessionStorage > hidden input
const urlParams = new URLSearchParams(window.location.search);
const urlId = urlParams.get('meeting_id');
const storedId = sessionStorage.getItem(STORAGE_KEY);
_meetingId = urlId || storedId || inputId || null;
if (_meetingId) { sessionStorage.setItem(STORAGE_KEY, _meetingId); }
```

The operator page fires `MeetingContext.init()` on DOMContentLoaded. If `?meeting_id=` is in the URL, it is picked up and `loadMeetingContext()` is called automatically (operator-tabs.js lines 3222-3228).

**Implementation approach for hub.js:**

Option A — patch `dest` in `HUB_STEPS` after `sessionId` is known:

```javascript
// Inside loadData(), after sessionId is confirmed valid:
HUB_STEPS.forEach(function(s) {
  if (s.dest === '/operator.htmx.html') {
    s.dest = '/operator.htmx.html?meeting_id=' + encodeURIComponent(sessionId);
  }
});
render(); // re-render action card with updated dest
```

Option B — set `MeetingContext.set(sessionId)` from hub.js (no sessionStorage key conflict since STORAGE_KEY = 'meeting_id' is shared across pages).

**Recommended: Option A.** Simpler, explicit, zero coupling to MeetingContext internals. The `renderAction()` function reads `step.dest` each time, so re-rendering picks up the updated URL.

**Caution:** `HUB_STEPS` is a module-level var. Mutating `dest` is safe as long as `loadData()` is only called once, or is idempotent (the URL append is stable for a given session). The retry/error path calls `loadData()` again — but `sessionId` will be the same, so double-appending is not a risk if you check before appending.

### Pattern 2: openVote endpoint branching

**What:** `operator-motions.js::openVote()` already computes `isFrozen` (line 745). Branch on it to call `operator_open_vote.php` when frozen.

**When to use:** Any time the operator opens the first vote on a frozen meeting.

**Current code (lines 781-782):**

```javascript
const isFrozen = O.currentMeetingStatus !== 'live';
// ... confirmation modal ...
const openResult = await api('/api/v1/motions_open.php', {
  meeting_id: O.currentMeetingId,
  motion_id: motionId
});
```

**Fixed code pattern:**

```javascript
const isFrozen = O.currentMeetingStatus !== 'live';
// ... confirmation modal (unchanged) ...

const endpoint = isFrozen
  ? '/api/v1/operator_open_vote.php'
  : '/api/v1/motions_open.php';

const openResult = await api(endpoint, {
  meeting_id: O.currentMeetingId,
  motion_id: motionId
});
```

**Response shape difference:** Both endpoints return `{ ok: true, ... }`. The `operator_open_vote.php` response includes `{ meeting_id, motion_id, generated }`. The existing error-check `openResult.body?.ok` works for both. The post-open UI flow (`loadResolutions`, `loadBallots`, mode switch) is status-agnostic and does not need changing.

**SSE events fired by `operator_open_vote.php` (HIGH confidence — read from OperatorController.php lines 324-326):**

```php
// OUTSIDE the transaction — after commit — to avoid broadcasting rolled-back state
if ($previousStatus !== 'live') {
    EventBroadcaster::meetingStatusChanged($meetingId, $tenantId, 'live', $previousStatus);
}
// motionOpened is NOT fired by OperatorController::openVote — only meetingStatusChanged
```

**Important:** `OperatorController::openVote` does NOT fire `motionOpened`. It fires `meetingStatusChanged` only. This means after switching the endpoint, the operator page will receive `meetingStatusChanged` SSE (updating status badge etc.) but will NOT receive `motionOpened` SSE automatically from the same call. `motionOpened` is fired by `MotionsController::open`.

This is a critical design point. Two options to handle it:

- **Option A (dual-call):** When frozen, call `operator_open_vote.php` first (gets meeting live + opens motion + generates tokens), then optionally also call `motions_open.php` for the `motionOpened` SSE — but this risks double-opening. NOT recommended.

- **Option B (single call, rely on SSE chain):** After calling `operator_open_vote.php`, call `loadResolutions()` (already in the success path). The operator page will pick up the newly opened motion from the DB state. The `meetingStatusChanged` SSE will update status. The public vote page already polls/SSEs via `current_motion` endpoint which reads DB state. This is the correct approach — the `motionOpened` SSE is a convenience optimization, not a gate.

- **Option C (extend OperatorController::openVote to also fire motionOpened):** Would require PHP change. Cleanest for SSE completeness but larger scope.

**Recommendation: Option B for minimum scope.** The UI reload path (`loadResolutions`, `refreshExecView`) already handles the case. `motionOpened` SSE is fired for voters on the public page; we should verify if voters need it or if they're covered by polling. Given the audit only flags the `meetingStatusChanged` gap, Option B satisfies VOT-01 and VOT-04.

### Anti-Patterns to Avoid

- **Calling `MeetingContext.set()` from hub.js:** Hub page does not use the operator page's MeetingContext — it has its own IIFE scope. While `sessionStorage` is shared (same key `meeting_id`), this approach creates hidden coupling. Use explicit URL parameter instead.
- **Hardcoding the step indices:** Steps 2 and 3 (0-indexed) both have `dest: '/operator.htmx.html'`. Filter by `dest` value, not array index, in case `HUB_STEPS` order changes.
- **Mutating `HUB_STEPS` before `sessionId` is known:** Guard the mutation inside the `res.body.ok` branch where `sessionId` is confirmed.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead |
|---------|-------------|-------------|
| Meeting ID URL propagation | Custom link rewriter | MeetingContext already does `_propagateToLinks()` for `<a>` tags — we just need the correct initial URL |
| Frozen→live state machine | Custom transition logic | `OperatorController::openVote` handles this atomically in PHP |
| SSE broadcast after transition | Custom SSE publish | `EventBroadcaster::meetingStatusChanged` already wired in OperatorController |

---

## Common Pitfalls

### Pitfall 1: operator_open_vote.php does not fire motionOpened SSE
**What goes wrong:** After switching to `operator_open_vote.php`, `motionOpened` SSE is never broadcast. Voters on the public page may not see the motion appear unless they poll.
**Why it happens:** OperatorController::openVote only broadcasts `meetingStatusChanged`, not `motionOpened`.
**How to avoid:** Verify if the public vote page relies on `motionOpened` SSE or polls `current_motion` API. If it polls, no problem. If it relies on SSE, consider adding `EventBroadcaster::motionOpened(...)` call in OperatorController::openVote after the transaction (similar to MotionsController::open lines 483-488).
**Warning signs:** Voters don't see the active vote appear after operator opens first motion on a frozen meeting.

### Pitfall 2: Double mutation of HUB_STEPS dest on retry
**What goes wrong:** `loadData()` is called again on retry (button click). If `HUB_STEPS[n].dest` already has `?meeting_id=`, appending again creates `?meeting_id=X?meeting_id=X`.
**Why it happens:** The retry handler calls `loadData()` without resetting `HUB_STEPS`.
**How to avoid:** Check if `dest` already contains `meeting_id` before appending. Or reset `dest` from a `ORIGINAL_HUB_STEPS` constant, or use `new URL()` to set the param:
```javascript
var u = new URL(s.dest, window.location.origin);
u.searchParams.set('meeting_id', sessionId);
s.dest = u.pathname + u.search;
```
**Warning signs:** Operator page URL looks like `/operator.htmx.html?meeting_id=X&meeting_id=X`.

### Pitfall 3: operator_open_vote.php returns different field names
**What goes wrong:** Error handling in `openVote()` checks `openResult.body?.ok`. The `operator_open_vote.php` response shape differs from `motions_open.php` — specifically it includes `generated` count but does NOT include `motion_id` the same way.
**Why it happens:** The two controllers have different response contracts.
**How to avoid:** After the endpoint call, the code already calls `loadResolutions()` which re-fetches state from the API — no reliance on the response body for UI state. The only check needed is `openResult.body?.ok`.

### Pitfall 4: sessionId vs meeting_id terminology in hub.js
**What goes wrong:** In hub.js, the URL param is `?id=` (the session/meeting ID from the hub page URL), but the operator page expects `?meeting_id=`. Using the wrong param name means MeetingContext won't pick it up.
**Why it happens:** hub.js reads `params.get('id')` to get sessionId. The operator page reads `params.get('meeting_id')`. These are the same UUID but different param names.
**How to avoid:** When building the operator destination URL, always use `meeting_id` as the query parameter name.

---

## Code Examples

Verified patterns from source code:

### hub.js: loadData() sessionId extraction (existing, confirmed)

```javascript
// hub.js lines 394-404 — sessionId is the meeting UUID from ?id=
var params = new URLSearchParams(window.location.search);
var sessionId = params.get('id');
if (!sessionId) {
  sessionStorage.setItem('ag-vote-toast', JSON.stringify({ msg: '...', type: 'error' }));
  window.location.href = '/dashboard.htmx.html';
  return;
}
```

### hub.js: HUB_STEPS operator destinations (existing, to be patched)

```javascript
// Steps 3 and 4 both use dest: '/operator.htmx.html'
{ id: 'presences', dest: '/operator.htmx.html', ... }
{ id: 'vote',     dest: '/operator.htmx.html', ... }
```

### hub.js: dest URL construction pattern (to add)

```javascript
// After API load confirms sessionData, patch dest for operator steps:
HUB_STEPS.forEach(function(s) {
  if (s.dest && s.dest.indexOf('/operator.htmx.html') === 0) {
    var u = new URL(s.dest, window.location.origin);
    u.searchParams.set('meeting_id', sessionId);
    s.dest = u.pathname + u.search;
  }
});
render();
```

### operator-motions.js: openVote endpoint branch (to add)

```javascript
// operator-motions.js ~line 781 — replace single api() call:
var endpoint = isFrozen
  ? '/api/v1/operator_open_vote.php'
  : '/api/v1/motions_open.php';

const openResult = await api(endpoint, {
  meeting_id: O.currentMeetingId,
  motion_id: motionId
});
```

### OperatorController::openVote — SSE broadcast (existing, confirmed)

```php
// app/Controller/OperatorController.php lines 323-326
// Runs OUTSIDE transaction — only fires if meeting was not already live
$previousStatus = (string) ($txResult['previousStatus'] ?? 'live');
if ($previousStatus !== 'live') {
    EventBroadcaster::meetingStatusChanged($meetingId, api_current_tenant_id(), 'live', $previousStatus);
}
```

### MeetingContext.init() — URL param priority (existing, confirmed)

```javascript
// meeting-context.js lines 33-43
const urlParams = new URLSearchParams(window.location.search);
const urlId = urlParams.get('meeting_id');       // <-- reads ?meeting_id=
const storedId = sessionStorage.getItem(STORAGE_KEY);
_meetingId = urlId || storedId || inputId || null;
if (_meetingId) { sessionStorage.setItem(STORAGE_KEY, _meetingId); }
```

---

## State of the Art

| Old Approach | Current Approach | Impact |
|--------------|------------------|--------|
| Hub action buttons navigate to /operator.htmx.html (bare) | Must append ?meeting_id= to pre-select meeting | User forced to re-select meeting from dropdown |
| openVote() always calls motions_open.php | When frozen, must call operator_open_vote.php | Meeting stays frozen in DB; meetingStatusChanged SSE never fires |

---

## Open Questions

1. **Does OperatorController::openVote need to also fire motionOpened SSE?**
   - What we know: `motions_open.php` fires `motionOpened` SSE; `operator_open_vote.php` does not.
   - What's unclear: Whether the public vote page (`vote.htmx.html`) relies on `motionOpened` SSE to display the active motion, or whether it polls `current_motion` API on its own timer.
   - Recommendation: Check `public/assets/js/pages/vote.js` or `public.js` for SSE event listener on `motionOpened`. If voters poll `current_motion`, Option B (no PHP change) is safe. If they wait for SSE, add `EventBroadcaster::motionOpened()` call to `OperatorController::openVote` after the transaction.

2. **Should hub.js also call MeetingContext.set() to pre-fill sessionStorage before navigation?**
   - What we know: `MeetingContext.init()` reads URL param first, then sessionStorage.
   - What's unclear: Whether a timing gap between navigation and `init()` could cause `meeting_id` to be missed.
   - Recommendation: URL param approach is race-safe (param is in the URL before any JS runs). No need to also set sessionStorage.

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | None detected (no jest.config, no vitest.config, no pytest.ini, no phpunit.xml) |
| Config file | none — no test infrastructure found |
| Quick run command | Manual browser verification |
| Full suite command | Manual browser verification |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| HUB-01 | Clicking operator action button on hub navigates to `/operator.htmx.html?meeting_id=<uuid>` | manual | N/A — browser navigation test | ❌ Wave 0 |
| VOT-01 | Opening first vote on frozen meeting transitions meeting to live and broadcasts meetingStatusChanged SSE | manual | N/A — SSE + DB state check | ❌ Wave 0 |
| VOT-04 | Meeting DB status becomes `live` after operator opens first vote via operator_open_vote.php | manual | N/A — DB query verification | ❌ Wave 0 |

### Sampling Rate

- **Per task commit:** Manual spot-check of affected code path
- **Per wave merge:** Full E2E flow verification (hub → operator handoff, frozen → live vote)
- **Phase gate:** Both flows pass manually before `/gsd:verify-work`

### Wave 0 Gaps

- No automated test infrastructure exists for this project
- Manual verification steps must be documented in VERIFICATION.md
- Suggested verification script: SQL query `SELECT status FROM meetings WHERE id = '<uuid>'` before and after opening first vote

*(No test files needed — this is a pure wiring fix with no testable business logic layer)*

---

## Sources

### Primary (HIGH confidence)

- `/home/user/gestion_votes_php/public/assets/js/pages/hub.js` — Full file read; confirmed sessionId retrieval pattern and HUB_STEPS structure
- `/home/user/gestion_votes_php/public/assets/js/pages/operator-motions.js` — Lines 739-818 read; confirmed `isFrozen` computation and `api('/api/v1/motions_open.php', ...)` call at line 782
- `/home/user/gestion_votes_php/app/Controller/OperatorController.php` — Full file read; confirmed `openVote()` does: transaction → status→live → motionOpened → tokens → meetingStatusChanged SSE after commit
- `/home/user/gestion_votes_php/app/Controller/MotionsController.php` — Lines 409-488 read; confirmed `open()` does NOT touch meeting status, only fires `motionOpened` SSE
- `/home/user/gestion_votes_php/public/api/v1/operator_open_vote.php` — File exists, one-liner shim routing to OperatorController::openVote
- `/home/user/gestion_votes_php/public/assets/js/services/meeting-context.js` — Full file read; confirmed URL param priority in `init()`
- `/home/user/gestion_votes_php/public/assets/js/pages/operator-tabs.js` — Lines 3213-3228 read; confirmed `MeetingContext.onChange` + race-condition fallback that calls `loadMeetingContext(_initMeetingId)`
- `/home/user/gestion_votes_php/app/routes.php` — Line 285 read; `operator_open_vote` registered with `['role' => 'operator']`
- `.planning/v3.0-MILESTONE-AUDIT.md` — Audit report defining both integration gaps with file locations and line numbers

### Secondary (MEDIUM confidence)

- `.planning/STATE.md` — Decision log confirming Phase 19 MeetingContext patterns and Phase 20 SSE broadcast placement
- `.planning/REQUIREMENTS.md` — Requirement definitions for HUB-01, VOT-01, VOT-04

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all relevant files read directly from source
- Architecture: HIGH — fix approach derived from reading actual code, not inference
- Pitfalls: HIGH — pitfall 1 (motionOpened SSE) verified by reading both controllers; pitfall 4 (param name mismatch) verified by reading hub.js line 395 and meeting-context.js line 34

**Research date:** 2026-03-18
**Valid until:** 2026-04-18 (stable codebase, no fast-moving dependencies)
