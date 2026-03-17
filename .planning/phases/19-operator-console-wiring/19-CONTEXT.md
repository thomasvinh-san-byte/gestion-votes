# Phase 19: Operator Console Wiring - Context

**Gathered:** 2026-03-16
**Status:** Ready for planning

<domain>
## Phase Boundary

Wire the operator console to load real session data from API endpoints using meeting_id propagated by MeetingContext. Attendance tab loads from `/api/v1/attendances.php`, motions tab loads from `/api/v1/motions_for_meeting.php`, and SSE connection triggers on `MeetingContext:change` event — not on page load. All demo/hardcoded data eliminated. This phase wires existing UI to existing APIs; no new UI components or API endpoints are created.

**Requirements:** OPR-01 (real session data via MeetingContext), OPR-02 (attendance from API), OPR-03 (motions from API), OPR-04 (SSE on MeetingContext:change)

</domain>

<decisions>
## Implementation Decisions

### SSE lifecycle on meeting change
- **Debounced reconnect (300ms)**: On meeting change, wait 300ms after last change before closing old SSE stream and connecting to new one. Prevents connection churn from rapid meeting switching.
- **Close SSE + stop polling when meeting cleared**: If meeting_id is cleared (no meeting selected), close SSE stream and stop all polling. No real-time updates until a new meeting is selected.
- **Initial connection via MeetingContext init event**: On page load with pre-existing meeting_id (from URL param or sessionStorage), MeetingContext fires its change event on init. The SSE listener picks it up naturally — no special init code in operator-realtime.js.
- **Remove immediate connectSSE() call**: Current line 215 of operator-realtime.js calls `connectSSE()` at module init. This must be removed. SSE connects only via the `meetingcontext:change` event listener.

### Loading & empty states per tab
- **Simple loading text/spinner**: Show "Chargement des participants..." / "Chargement des résolutions..." (French, tab-specific) in each tab content area while API data loads.
- **Centered empty message**: When zero items, show centered message: "Aucun participant enregistré" / "Aucune résolution" (per tab). Matches Phase 17 zero-demo-data pattern.
- **Error banner + retry in tab**: On API failure for a specific tab, show error banner with retry button inside the tab content area. Uses Phase 16 pattern (`.hub-error` class, retry button).

### Data clearing on meeting switch
- **Clear immediately + show loading**: On meeting change, immediately clear all caches (attendanceCache, motionsCache, proxiesCache, etc.) and show loading state per tab. Brief flash of empty is acceptable — clean break.
- **Reset KPI strip to placeholder**: KPI values reset to "—" immediately on meeting switch, then populate when new data loads. No stale numbers from previous meeting.
- **Stale response check**: Each `loadAllData()` call tags responses with the meeting_id. When response arrives, check if meeting_id still matches `OpS.currentMeetingId`. Discard stale responses silently.
- **Reset to setup mode**: When switching meetings, console resets to setup mode (viewSetup). User must explicitly enter exec mode for the new meeting.

### Quorum calculation wiring
- **Recalculate on initial load + every SSE event**: Quorum updates on `attendance.updated` and `quorum.updated` SSE events, not just initial load. Real-time quorum tracking.
- **Reset quorum warning per meeting**: `quorumWarningShown` flag resets on meeting switch. New meeting = new quorum check. If quorum not met, warning modal shows immediately on data load.
- **Backend-computed quorum**: Quorum status comes from the attendance API response (`summary.quorum_met`, `summary.present_count`, `summary.total_eligible`). Frontend displays — does not compute.
- **Show warning immediately on data load**: If quorum not met when attendance data arrives (initial load or SSE update), show quorum warning modal right away.

### Claude's Discretion
- Exact debounce implementation mechanism (setTimeout/clearTimeout vs library)
- CSS for loading/empty states (reuse existing patterns or new)
- Stale response check implementation (request counter vs meeting_id comparison)
- Polling interval adjustments (currently POLL_FAST=5000, POLL_SLOW=15000)

</decisions>

<specifics>
## Specific Ideas

- Loading messages must be in French and tab-specific (not generic "Chargement...")
- Error handling follows Phase 16 established pattern: 1 auto-retry after 2s, then error banner with retry button
- Phase 17 principle: zero demo data under any circumstance — real data, empty state, or error state
- Phase 18 SSE pattern: `EventStream.connect(meetingId, { onEvent, onConnect, onDisconnect })`
- OpS bridge is the cross-module communication pattern — all state shared via `window.OpS`

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `window.api(url, data)`: Canonical API call pattern (returns Promise with {body})
- `Shared.showToast(msg, type)`: Toast notification system
- `EventStream.connect(meetingId, opts)`: SSE connection manager from Phase 18
- `MeetingContext.onChange(callback)`: Change listener for meeting context
- `MeetingContext.get()` / `.set(id)` / `.isSet()` / `.forApi()`: Meeting state management
- `OpS` bridge: State proxy + function registry connecting all operator sub-modules
- `ag-confirm` component: 3-button quorum warning modal (already built in Phase 10.1)

### Established Patterns
- **IIFE + var + 'use strict'**: All operator-*.js modules use this pattern, shared via OpS bridge
- **Promise.allSettled for loadAllData()**: Already handles partial failures gracefully
- **Error pattern**: try/catch + `setNotif('error', msg)` per load function
- **OpS.fn registry**: Sub-modules register functions (e.g., `OpS.fn.loadAttendance`)
- **Script load order**: operator-tabs.js first (creates OpS), then sub-modules attach

### Integration Points
- `operator-realtime.js:215`: Remove immediate `connectSSE()` call — add MeetingContext listener instead
- `operator-tabs.js:loadMeetingContext()`: Add cache clearing + loading state before `loadAllData()`
- `operator-tabs.js:loadAllData()`: Add stale response check (compare meeting_id on response)
- `operator-tabs.js:OpS bridge (~line 3059)`: Reset `quorumWarningShown` on meeting change
- `operator-exec.js`: Reset to setup mode on meeting switch (set `OpS.currentMode = 'setup'`)
- KPI strip elements: `opKpiPresent`, `opKpiQuorum`, `opKpiVoted`, `opKpiResolution` — reset to "—"

### Key API Endpoints (already exist)
- `GET /api/v1/meetings_index.php?active_only=1` — dropdown population
- `GET /api/v1/meetings.php?id={uuid}` — meeting details + status
- `GET /api/v1/attendances.php?meeting_id={uuid}` — attendance + summary (includes quorum)
- `GET /api/v1/motions_for_meeting.php?meeting_id={uuid}` — resolutions + stats
- `GET /api/v1/events.php?meeting_id={uuid}` — SSE stream

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 19-operator-console-wiring*
*Context gathered: 2026-03-16*
