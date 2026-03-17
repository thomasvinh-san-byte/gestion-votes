# Phase 19: Operator Console Wiring - Research

**Researched:** 2026-03-16
**Domain:** Vanilla JS module wiring — MeetingContext, EventStream SSE, OpS bridge, operator sub-modules
**Confidence:** HIGH (all findings from direct source inspection)

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**SSE lifecycle on meeting change**
- Debounced reconnect (300ms): On meeting change, wait 300ms after last change before closing old SSE stream and connecting to new one. Prevents connection churn from rapid meeting switching.
- Close SSE + stop polling when meeting cleared: If meeting_id is cleared (no meeting selected), close SSE stream and stop all polling. No real-time updates until a new meeting is selected.
- Initial connection via MeetingContext init event: On page load with pre-existing meeting_id (from URL param or sessionStorage), MeetingContext fires its change event on init. The SSE listener picks it up naturally — no special init code in operator-realtime.js.
- Remove immediate connectSSE() call: Current line 215 of operator-realtime.js calls `connectSSE()` at module init. This must be removed. SSE connects only via the `meetingcontext:change` event listener.

**Loading & empty states per tab**
- Simple loading text/spinner: Show "Chargement des participants..." / "Chargement des résolutions..." (French, tab-specific) in each tab content area while API data loads.
- Centered empty message: When zero items, show centered message: "Aucun participant enregistré" / "Aucune résolution" (per tab). Matches Phase 17 zero-demo-data pattern.
- Error banner + retry in tab: On API failure for a specific tab, show error banner with retry button inside the tab content area. Uses Phase 16 pattern (`.hub-error` class, retry button).

**Data clearing on meeting switch**
- Clear immediately + show loading: On meeting change, immediately clear all caches (attendanceCache, motionsCache, proxiesCache, etc.) and show loading state per tab. Brief flash of empty is acceptable — clean break.
- Reset KPI strip to placeholder: KPI values reset to "—" immediately on meeting switch, then populate when new data loads. No stale numbers from previous meeting.
- Stale response check: Each `loadAllData()` call tags responses with the meeting_id. When response arrives, check if meeting_id still matches `OpS.currentMeetingId`. Discard stale responses silently.
- Reset to setup mode: When switching meetings, console resets to setup mode (viewSetup). User must explicitly enter exec mode for the new meeting.

**Quorum calculation wiring**
- Recalculate on initial load + every SSE event: Quorum updates on `attendance.updated` and `quorum.updated` SSE events, not just initial load. Real-time quorum tracking.
- Reset quorum warning per meeting: `quorumWarningShown` flag resets on meeting switch. New meeting = new quorum check. If quorum not met, warning modal shows immediately on data load.
- Backend-computed quorum: Quorum status comes from the attendance API response (`summary.quorum_met`, `summary.present_count`, `summary.total_eligible`). Frontend displays — does not compute.
- Show warning immediately on data load: If quorum not met when attendance data arrives (initial load or SSE update), show quorum warning modal right away.

### Claude's Discretion
- Exact debounce implementation mechanism (setTimeout/clearTimeout vs library)
- CSS for loading/empty states (reuse existing patterns or new)
- Stale response check implementation (request counter vs meeting_id comparison)
- Polling interval adjustments (currently POLL_FAST=5000, POLL_SLOW=15000)

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| OPR-01 | La console opérateur charge les données réelles de la session via meeting_id propagé par MeetingContext | Requires adding meeting-context.js to operator.htmx.html + MeetingContext.onChange wiring in operator-tabs.js/operator-realtime.js |
| OPR-02 | L'onglet présence charge les données d'inscription depuis l'API | loadAttendance() exists; needs empty/loading/error states; attendance API summary does NOT include quorum_met |
| OPR-03 | L'onglet motions charge les résolutions depuis l'API | loadResolutions() exists; needs empty/loading/error states |
| OPR-04 | La connexion SSE se déclenche sur MeetingContext:change (pas au chargement de page) | Line 215 in operator-realtime.js must be removed; MeetingContext.onChange listener must be added |
</phase_requirements>

---

## Summary

Phase 19 wires the operator console to real meeting data using MeetingContext as the single source of truth. The work is entirely frontend JS — no new API endpoints, no new UI components. Three files drive 95% of the work: `operator-tabs.js` (loadMeetingContext + loadAllData + cache-clearing), `operator-realtime.js` (SSE lifecycle), and `operator.htmx.html` (script loading).

The most critical discovery from code inspection: **`meeting-context.js` is NOT currently loaded in `operator.htmx.html`**. Every other page in the project loads it; the operator console does not. This is the root cause of OPR-01 and OPR-04 being unmet. Without MeetingContext loaded, there is no `meetingcontext:change` event to listen to.

The second critical discovery: **MeetingContext's `init()` does NOT fire `_notifyListeners`**. The CONTEXT.md decision states "MeetingContext fires its change event on init" — this behavior must be ADDED to meeting-context.js. Currently, `init()` sets `_meetingId` silently. The operator-realtime.js SSE listener will never trigger on page load with a pre-existing meeting_id unless init fires the event.

The third critical discovery: **The attendance API summary does NOT include `quorum_met` or `total_eligible`**. The CONTEXT.md references these fields as coming from the attendance API response, but the actual `AttendanceRepository.summaryForMeeting()` only returns `{present_count, present_weight}`. Quorum status comes from the separate `quorum_status.php` endpoint, which is already called by `loadQuorumStatus()` in operator-tabs.js. The planner should use the existing `loadQuorumStatus()` pathway rather than parsing attendance summary for quorum fields.

**Primary recommendation:** Load meeting-context.js in operator.htmx.html, patch MeetingContext.init() to fire onChange, wire MeetingContext.onChange in operator-tabs.js to call loadMeetingContext(), add MeetingContext.onChange in operator-realtime.js to trigger SSE, add debounced connectSSE on meeting change, remove line 215, add loading/empty/error states to attendance and motions tabs.

---

## Standard Stack

### Core (confirmed by direct source inspection)

| Library/API | Location | Purpose | Notes |
|-------------|----------|---------|-------|
| `MeetingContext` | `/assets/js/services/meeting-context.js` | Single source of truth for meeting_id | IIFE, returns singleton; NOT loaded in operator page yet |
| `EventStream` | `/assets/js/core/event-stream.js` | SSE connection manager | `connect(meetingId, {onEvent, onConnect, onDisconnect})` returns `{close, isConnected}` |
| `window.OpS` | `operator-tabs.js` line 11 | Cross-module state + function bridge | State via `Object.defineProperty` proxies; functions via `_ops.fn.xxx` |
| `window.api(url, data)` | `utils.js` / `core` | Canonical HTTP call | Returns `Promise<{body}>` |
| `Shared.showToast(msg, type)` | `shared.js` | Toast notifications | Available as `setNotif(type, msg)` inside operator modules |
| `Promise.allSettled` | operator-tabs.js:508 | Parallel data loading with partial failure | Already used in loadAllData() |

### Script Load Order (operator.htmx.html, lines 1020-1031)

```
components/index.js  (module)
core/utils.js
core/shared.js
core/shell.js
core/event-stream.js       ← EventStream available here
core/page-components.js
pages/operator-tabs.js     ← OpS bridge created, loadMeetings() called
pages/operator-speech.js
pages/operator-attendance.js
pages/operator-motions.js
pages/operator-exec.js
pages/operator-realtime.js ← connectSSE() called at line 215 (to be removed)
```

`meeting-context.js` must be added. Best position: after `event-stream.js` and before `operator-tabs.js`, so MeetingContext is available when operator-tabs.js runs.

---

## Architecture Patterns

### Recommended Script Order Addition

```html
<script src="/assets/js/core/event-stream.js"></script>
<script src="/assets/js/services/meeting-context.js"></script>  <!-- ADD HERE -->
<script src="/assets/js/core/page-components.js"></script>
<script src="/assets/js/pages/operator-tabs.js"></script>
```

### Pattern 1: MeetingContext.onChange Listener in operator-realtime.js

**What:** Replace the immediate `connectSSE()` call at module init with a MeetingContext change listener. Use debounced setTimeout/clearTimeout (300ms) to prevent connection churn.

**When to use:** This is the mandatory pattern for OPR-04.

```javascript
// Source: direct code inspection of operator-realtime.js + meeting-context.js

// REMOVE this line (currently line 215):
// connectSSE();

// ADD this instead, after the module's function definitions:
var _sseDebounceTimer = null;

window.addEventListener(MeetingContext.EVENT_NAME, function(e) {
  var newId = e.detail.newId;
  if (_sseDebounceTimer) clearTimeout(_sseDebounceTimer);

  if (!newId) {
    // Meeting cleared — disconnect SSE and stop polling
    if (sseStream) { sseStream.close(); sseStream = null; }
    sseConnected = false;
    if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
    return;
  }

  _sseDebounceTimer = setTimeout(function() {
    connectSSE();
  }, 300);
});

// Initial polling still starts (SSE may not connect if no meeting_id yet)
schedulePoll(POLL_SLOW);
```

### Pattern 2: MeetingContext.onChange in operator-tabs.js for Data Loading

**What:** Wire MeetingContext.onChange to call loadMeetingContext() so any external meeting_id change (URL navigation, cross-tab sessionStorage) triggers a full data reload.

```javascript
// Source: direct code inspection of operator-tabs.js loadMeetings() and meeting-context.js

// In operator-tabs.js, after loadMeetings() is called:
MeetingContext.onChange(function(oldId, newId) {
  // Sync the dropdown if it doesn't already reflect newId
  if (meetingSelect && meetingSelect.value !== (newId || '')) {
    meetingSelect.value = newId || '';
  }
  loadMeetingContext(newId);
});
```

### Pattern 3: MeetingContext.init() Must Fire onChange

**What:** MeetingContext's `init()` currently sets `_meetingId` silently. To support the "initial connection via MeetingContext init event" behavior, `init()` must call `_notifyListeners` when it finds a pre-existing meeting_id.

**Change in meeting-context.js:**

```javascript
// Source: direct inspection of meeting-context.js init() function

function init() {
  if (_initialized) return _meetingId;

  // ... existing resolution logic ...
  _meetingId = urlId || storedId || inputId || null;
  // ... persist, sync URL, propagate links ...

  _initialized = true;

  // ADD: Fire change event if a meeting_id was found on init
  if (_meetingId) {
    _notifyListeners(null, _meetingId);  // oldId = null (no previous state)
  }

  return _meetingId;
}
```

**Risk:** This change fires `meetingcontext:change` for ALL pages that load meeting-context.js. Confirm no other page has side effects from receiving a change event on init. Based on inspection, other pages (hub, dashboard, analytics) use MeetingContext.get() directly, not onChange listeners — so the impact is low.

### Pattern 4: Cache Clearing on Meeting Switch

**What:** In `loadMeetingContext()` in operator-tabs.js, before calling `loadAllData()`, clear all caches and show loading states in tab content areas.

```javascript
// Source: direct inspection of operator-tabs.js loadMeetingContext() at line 382

async function loadMeetingContext(meetingId) {
  if (!meetingId) { showNoMeeting(); return; }

  currentMeetingId = meetingId;
  _hasAutoNavigated = false;
  OpS.quorumWarningShown = false;
  updateURLParam('meeting_id', meetingId);

  // ADD: Clear all caches immediately
  attendanceCache = [];
  motionsCache = [];
  proxiesCache = [];
  currentOpenMotion = null;
  currentMode = 'setup';  // Reset to setup mode per locked decision

  // ADD: Show loading state in tabs
  showTabLoading('participants', 'Chargement des participants...');
  showTabLoading('ordre-du-jour', 'Chargement des résolutions...');

  // ADD: Reset KPI strip to placeholders
  resetKpiStrip();

  // ... existing: fetch meeting details, showMeetingContent, updateHeader, loadAllData ...
}
```

### Pattern 5: Loading / Empty / Error States per Tab

**What:** Each tab's data-loading function shows loading text before the API call, empty state on zero results, error banner + retry on failure.

```javascript
// Pattern used across operator-attendance.js and operator-motions.js

async function loadAttendance() {
  var container = document.getElementById('attendanceGrid');
  if (container) {
    container.innerHTML = '<div class="text-center p-4 text-muted">Chargement des participants...</div>';
  }
  try {
    const { body } = await api(`/api/v1/attendances.php?meeting_id=...`);
    O.attendanceCache = body?.data?.items || [];
    if (O.attendanceCache.length === 0) {
      container.innerHTML = '<div class="text-center p-4 text-muted">Aucun participant enregistré</div>';
      return;
    }
    renderAttendance();
  } catch (err) {
    if (container) {
      container.innerHTML = '<div class="hub-error">Erreur chargement participants. <button class="btn btn-sm btn-outline" onclick="OpS.fn.loadAttendance()">Réessayer</button></div>';
    }
    setNotif('error', 'Erreur chargement présences');
  }
}
```

### Pattern 6: Stale Response Check

**What:** Tag each loadAllData() invocation with a request epoch (meeting_id snapshot). Discard responses that arrive for a stale meeting_id.

```javascript
// Recommendation: meeting_id comparison (simpler than request counter)

async function loadAttendance() {
  var snapshotMeetingId = O.currentMeetingId;  // capture at call time
  // ... API call ...
  if (O.currentMeetingId !== snapshotMeetingId) return;  // stale — discard
  O.attendanceCache = body?.data?.items || [];
  renderAttendance();
}
```

### Anti-Patterns to Avoid

- **Calling MeetingContext.set() inside operator-tabs.js:** operator-tabs.js already manages meeting_id via URL params and its own `currentMeetingId`. MeetingContext should be READ ONLY from operator-tabs.js — do not call `.set()` which would cause infinite loop via the onChange listener.
- **Registering MeetingContext.onChange before MeetingContext is loaded:** operator-tabs.js runs after meeting-context.js in script order; this is safe only if meeting-context.js is added to the HTML before operator-tabs.js.
- **Removing the meetingSelect.addEventListener('change', ...):** The dropdown change handler (line 2579 in operator-tabs.js) must also call `MeetingContext.set(newId)` so MeetingContext stays in sync when user picks from the dropdown.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Debounce for SSE reconnect | Custom debounce utility | `setTimeout`/`clearTimeout` pattern | Already used for `newVoteDebounceTimer` in same file; consistent with codebase style |
| SSE connection management | Custom EventSource wrapper | `EventStream.connect()` from Phase 18 | Handles reconnect, event parsing, max reconnect attempts |
| Meeting state persistence | Custom sessionStorage wrapper | `MeetingContext.get()` / `.set()` | Already handles URL sync, sessionStorage, cross-tab |
| Error HTML | Custom error component | `.hub-error` CSS class + retry button | Phase 16/17 established pattern; CSS already exists |
| Loading spinner | Custom loader | Inline text: "Chargement des X..." | Phase 17 pattern; consistent with zero-demo-data approach |

**Key insight:** Every infrastructure piece needed already exists. This phase is purely wiring — connecting existing APIs, existing SSE, existing MeetingContext, existing error patterns.

---

## Common Pitfalls

### Pitfall 1: MeetingContext.init() fires on DOMContentLoaded, before sub-module scripts run

**What goes wrong:** meeting-context.js auto-inits on DOMContentLoaded. If operator-tabs.js registers `MeetingContext.onChange(...)` in its IIFE (which runs synchronously on script load, after DOM is ready), the init event has already fired before the listener is registered.

**Why it happens:** DOMContentLoaded fires once. Auto-init in meeting-context.js line 202-207 fires it immediately. Sub-module IIFEs register listeners on script load, which is AFTER DOMContentLoaded.

**How to avoid:** Two options:
1. MeetingContext.init() fires `_notifyListeners` and the operator-tabs.js listener registers via `MeetingContext.onChange()` — but only works if init has NOT yet fired (race condition).
2. **Preferred:** In operator-tabs.js IIFE initialization code (after loadMeetings() call), explicitly check `MeetingContext.isSet()` and call `loadMeetingContext(MeetingContext.get())` directly if a meeting_id is already known. The onChange listener handles subsequent changes.

**Warning signs:** SSE never connects on page load with `?meeting_id=UUID` in URL.

### Pitfall 2: meetingSelect dropdown and MeetingContext falling out of sync

**What goes wrong:** User selects a meeting from the dropdown → `loadMeetingContext()` fires → MeetingContext is not updated → URL gets one meeting_id, MeetingContext has null → future SSE listeners or other pages get wrong meeting_id.

**Why it happens:** The dropdown's `change` listener (operator-tabs.js line 2579) calls `loadMeetingContext()` directly without calling `MeetingContext.set()`. MeetingContext is never informed of the new selection.

**How to avoid:** In the dropdown change handler, call `MeetingContext.set(newMeetingId, { silent: true })` first (silent = don't re-trigger onChange loop), then the MeetingContext.onChange listener will NOT fire (silent), and operator-tabs.js can proceed with the existing loadMeetingContext() call.

**Alternative:** Call `MeetingContext.set(newMeetingId)` (not silent) and let the onChange listener call `loadMeetingContext()` — but remove the direct `loadMeetingContext()` call from the dropdown handler to avoid double-load.

### Pitfall 3: Stale response rendering after rapid meeting switch

**What goes wrong:** User switches from meeting A → B rapidly. Meeting A's API responses arrive AFTER meeting B's are already displayed. Meeting A's attendance data overwrites meeting B's grid.

**Why it happens:** `Promise.allSettled` inside `loadAllData()` launches many concurrent API calls. If a new `loadMeetingContext()` fires while the old `Promise.allSettled` is still in flight, both sets of promises resolve independently.

**How to avoid:** Capture `O.currentMeetingId` at the START of each load function. Before writing to the DOM, compare against `O.currentMeetingId`. Discard if they differ. (See Pattern 6 above.)

**Warning signs:** Attendance tab shows names from a previously selected meeting.

### Pitfall 4: MeetingContext.onChange fires twice on dropdown selection

**What goes wrong:** Dropdown change event calls `MeetingContext.set()` (fires onChange) AND operator-tabs.js `loadMeetingContext()` directly. The onChange listener also calls `loadMeetingContext()`. Data loads twice.

**Why it happens:** Not coordinating between the dropdown change handler and the MeetingContext.onChange listener.

**How to avoid:** Choose ONE trigger path. Recommended: `MeetingContext.set()` is the canonical setter; the onChange listener is the canonical data loader. Remove the direct `loadMeetingContext()` call from the dropdown handler.

### Pitfall 5: SSE debounce timer not cleared on page unload

**What goes wrong:** User navigates away during the 300ms debounce window. The debounce timer fires after navigation, attempting to connect SSE on a destroyed page.

**Why it happens:** The `beforeunload` handler in operator-realtime.js clears `pollTimer` and `newVoteDebounceTimer` but not the new `_sseDebounceTimer`.

**How to avoid:** Add `if (_sseDebounceTimer) clearTimeout(_sseDebounceTimer);` to the existing `beforeunload` listener in operator-realtime.js.

### Pitfall 6: Quorum warning `quorumWarningShown` flag — it's on OpS, not a local variable

**What goes wrong:** Code that resets quorum warning uses `OpS.quorumWarningShown = false` — this is correct. But operator-exec.js checks `O.quorumWarningShown` via the OpS bridge. Confirm there is no shadowing local variable in operator-exec.js.

**Why it happens:** `quorumWarningShown` is NOT in the `_defState` proxy list in operator-tabs.js (line 3069-3088). It is set directly on `OpS` object, not proxied. This means it's a plain property on `window.OpS`.

**How to avoid:** Use `OpS.quorumWarningShown` (not `O.quorumWarningShown`) consistently. In operator-exec.js, `O` is `window.OpS` so `O.quorumWarningShown` works. Reset in loadMeetingContext() uses `OpS.quorumWarningShown = false` (same object). This is correct as-is.

---

## Code Examples

### MeetingContext.init() with onChange fire (meeting-context.js change)

```javascript
// Source: direct inspection of /public/assets/js/services/meeting-context.js

function init() {
  if (_initialized) return _meetingId;

  const urlParams = new URLSearchParams(window.location.search);
  const urlId = urlParams.get('meeting_id');
  const storedId = sessionStorage.getItem(STORAGE_KEY);
  const inputEl = document.querySelector('input[name="meeting_id"]');
  const inputId = inputEl?.value || null;

  _meetingId = urlId || storedId || inputId || null;

  if (_meetingId) sessionStorage.setItem(STORAGE_KEY, _meetingId);
  if (_meetingId && !urlId) _syncToUrl(_meetingId);
  _propagateToLinks(_meetingId);

  _initialized = true;

  // NEW: Fire change so SSE listeners and data loaders can react to initial meeting_id
  if (_meetingId) {
    _notifyListeners(null, _meetingId);
  }

  return _meetingId;
}
```

### Adding meeting-context.js to operator.htmx.html

```html
<!-- Source: /public/operator.htmx.html lines 1020-1031 -->
<script src="/assets/js/core/event-stream.js"></script>
<script src="/assets/js/services/meeting-context.js"></script>   <!-- NEW LINE -->
<script src="/assets/js/core/page-components.js"></script>
<script src="/assets/js/pages/operator-tabs.js"></script>
```

### SSE lifecycle wiring in operator-realtime.js

```javascript
// Source: direct inspection of /public/assets/js/pages/operator-realtime.js

// REMOVE line 215: connectSSE();
// KEEP line 216:   schedulePoll(POLL_SLOW);

// ADD before "// Register on OpS" section:
var _sseDebounceTimer = null;

window.addEventListener(MeetingContext.EVENT_NAME, function(e) {
  var newId = e.detail ? e.detail.newId : null;
  if (_sseDebounceTimer) clearTimeout(_sseDebounceTimer);

  if (!newId) {
    if (sseStream) { sseStream.close(); sseStream = null; sseConnected = false; }
    if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
    return;
  }

  _sseDebounceTimer = setTimeout(function() {
    _sseDebounceTimer = null;
    connectSSE();
  }, 300);
});

// Also update beforeunload cleanup:
window.addEventListener('beforeunload', function() {
  if (pollTimer) clearTimeout(pollTimer);
  if (newVoteDebounceTimer) clearTimeout(newVoteDebounceTimer);
  if (_sseDebounceTimer) clearTimeout(_sseDebounceTimer);  // NEW
  if (sseStream) sseStream.close();
});
```

### MeetingContext.onChange wiring in operator-tabs.js

```javascript
// Source: direct inspection of /public/assets/js/pages/operator-tabs.js

// After initTabs(); startClock(); loadMeetings(); at bottom of IIFE:

// Wire MeetingContext changes to data loading
// Note: MeetingContext is already initialized (loaded before this script)
MeetingContext.onChange(function(_oldId, newId) {
  // Sync dropdown
  if (meetingSelect && meetingSelect.value !== (newId || '')) {
    meetingSelect.value = newId || '';
  }
  loadMeetingContext(newId);
});

// If MeetingContext already has a meeting_id on page load (init fired before onChange registered),
// load it now. This handles the race condition.
var _initMeetingId = MeetingContext.get();
if (_initMeetingId && !currentMeetingId) {
  if (meetingSelect) meetingSelect.value = _initMeetingId;
  loadMeetingContext(_initMeetingId);
}
```

### KPI strip reset helper

```javascript
// Source: OpS bridge inspection — KPI element IDs from operator-tabs.js comments

function resetKpiStrip() {
  var ids = ['opKpiPresent', 'opKpiQuorum', 'opKpiVoted', 'opKpiResolution'];
  ids.forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.textContent = '—';
  });
}
```

---

## State of the Art

| Old State | New State (Phase 19) | Impact |
|-----------|---------------------|--------|
| operator.htmx.html does NOT load meeting-context.js | operator.htmx.html loads meeting-context.js | MeetingContext.onChange becomes available to operator modules |
| SSE connects immediately on page load (line 215) | SSE connects only on MeetingContext:change event | OPR-04 met; no premature connections |
| Attendance/motions load with no loading states | Loading text shown before API calls | Better UX, no jarring empty flash |
| No stale response protection | Per-call meeting_id snapshot comparison | Rapid meeting switching safe |
| No cache clearing on meeting switch | Caches cleared immediately on switch | No stale data from previous meeting shown |
| `quorumWarningShown` resets only on dropdown change | Resets on any meeting context change | Quorum modal fires for every new meeting |

**Deprecated patterns this phase removes:**
- Immediate `connectSSE()` at module init (operator-realtime.js line 215)
- URL param reading for meeting_id inside loadMeetings() (superseded by MeetingContext)

---

## Open Questions

1. **Race condition: init() fires before onChange listener registered**
   - What we know: MeetingContext auto-inits on DOMContentLoaded. operator-tabs.js registers onChange inside its IIFE which runs synchronously when the script is parsed (after DOMContentLoaded). If DOM is already ready when scripts load (e.g., deferred scripts), init() may have already fired.
   - What's unclear: Whether the `_notifyListeners` call added to init() will race against onChange registration.
   - Recommendation: Add the "check MeetingContext.get() after registration" fallback (Pattern 5 above) as belt-and-suspenders. This handles the case where init fires before the listener is registered.

2. **Quorum: CONTEXT.md says summary.quorum_met from attendance API — this field does not exist**
   - What we know: AttendanceRepository.summaryForMeeting() returns only `{present_count, present_weight}`. No `quorum_met` or `total_eligible` field.
   - What's unclear: Whether CONTEXT.md describes desired backend changes or mistakenly references the quorum_status.php response shape.
   - Recommendation: Use existing `loadQuorumStatus()` pathway (calls `/api/v1/quorum_status.php`) to display quorum status. Do NOT attempt to parse `summary.quorum_met` from the attendance API — it doesn't exist. The quorum warning modal already calls `loadQuorumStatus()` in operator-exec.js (line 432 area).

3. **Whether loadMeetings() in operator-tabs.js should call MeetingContext.set() when pre-selecting**
   - What we know: loadMeetings() reads URL param and calls `loadMeetingContext(urlMeetingId)` directly (line 374). It does not call `MeetingContext.set()`.
   - What's unclear: If MeetingContext.init() fires via DOMContentLoaded and THEN the onChange listener is registered, MeetingContext already has the right meeting_id. The loadMeetings() pre-selection is redundant.
   - Recommendation: After adding MeetingContext.onChange wiring, simplify loadMeetings() to remove its own URL param pre-selection. The MeetingContext.onChange + fallback check handles this cleanly.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.5 |
| Config file | `phpunit.xml` |
| Quick run command | `vendor/bin/phpunit --testsuite Unit --stop-on-failure` |
| Full suite command | `vendor/bin/phpunit` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| OPR-01 | Operator console loads real meeting data via MeetingContext | manual | Browser: navigate to `/operator.htmx.html?meeting_id=UUID`, verify title shows, no hardcoded values | N/A — frontend-only |
| OPR-02 | Attendance tab loads registered participants from API | unit (PHP) + manual | `vendor/bin/phpunit tests/Unit/AttendancesControllerTest.php` | ✅ exists (covers listForMeeting validation) |
| OPR-03 | Motions tab loads resolutions from API | unit (PHP) + manual | `vendor/bin/phpunit tests/Unit/MotionsControllerTest.php` | ✅ exists (covers listForMeeting validation) |
| OPR-04 | SSE connects only on MeetingContext:change, not on page load | manual | Browser devtools: open operator page without meeting_id, confirm no SSE request in Network tab | N/A — frontend-only |

**Note:** OPR-01 and OPR-04 are frontend JavaScript behavior changes. PHPUnit does not cover browser JS. Manual verification via browser devtools is the validation path for these requirements. OPR-02 and OPR-03 have existing unit tests covering the PHP API endpoints; the JS wiring side requires manual verification.

### Sampling Rate
- **Per task commit:** `vendor/bin/phpunit tests/Unit/AttendancesControllerTest.php tests/Unit/MotionsControllerTest.php`
- **Per wave merge:** `vendor/bin/phpunit --testsuite Unit`
- **Phase gate:** Full suite green + manual browser verification of all 4 OPR requirements before `/gsd:verify-work`

### Wave 0 Gaps
None — existing test infrastructure covers the PHP API layer. No new PHP files are introduced in this phase. Frontend JS changes require manual verification only.

---

## Sources

### Primary (HIGH confidence — direct source inspection)

- `/home/user/gestion-votes/public/assets/js/services/meeting-context.js` — Full file read; init(), onChange(), _notifyListeners(), EVENT_NAME
- `/home/user/gestion-votes/public/assets/js/core/event-stream.js` — Full file read; connect() API signature, MAX_RECONNECT_ATTEMPTS, close()
- `/home/user/gestion-votes/public/assets/js/pages/operator-realtime.js` — Full file read; line 215 (immediate connectSSE), debounce patterns, sseStream, pollTimer, beforeunload
- `/home/user/gestion-votes/public/assets/js/pages/operator-tabs.js` — Read key sections: loadMeetingContext() line 382, loadAllData() line 506, OpS bridge line 3059, loadMeetings() line 358, meetingSelect onChange line 2579
- `/home/user/gestion-votes/public/assets/js/pages/operator-attendance.js` — Full file read; loadAttendance() structure, renderAttendance() patterns
- `/home/user/gestion-votes/public/assets/js/pages/operator-motions.js` — Read; loadResolutions() structure
- `/home/user/gestion-votes/public/assets/js/pages/operator-exec.js` — Read quorumWarningShown usage lines 432-433, showQuorumWarning() function
- `/home/user/gestion-votes/public/operator.htmx.html` — Script loading section lines 1020-1031 (meeting-context.js confirmed missing)
- `/home/user/gestion-votes/app/Repository/AttendanceRepository.php` — summaryForMeeting() returns {present_count, present_weight} only — NO quorum_met field
- `/home/user/gestion-votes/tests/Unit/AttendancesControllerTest.php` — listForMeeting coverage confirmed
- `/home/user/gestion-votes/tests/Unit/MotionsControllerTest.php` — listForMeeting coverage confirmed
- `/home/user/gestion-votes/phpunit.xml` — Framework configuration confirmed

### Secondary (MEDIUM confidence)
- None required — all information derived from direct source inspection

---

## Metadata

**Confidence breakdown:**
- Integration points (what to change, where): HIGH — confirmed by reading actual source files
- Quorum API shape: HIGH — confirmed by reading AttendanceRepository.summaryForMeeting() return annotation
- Missing meeting-context.js: HIGH — confirmed by grep across all HTML files and reading operator.htmx.html
- Init() does not fire onChange: HIGH — confirmed by reading full meeting-context.js init() implementation
- Race condition risk: MEDIUM — JavaScript execution order is well-understood but depends on browser script loading timing

**Research date:** 2026-03-16
**Valid until:** 2026-04-16 (stable vanilla JS codebase; no external library updates affect this)
