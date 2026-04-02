# Phase 64: In-App Notifications - Context

**Gathered:** 2026-04-01
**Status:** Ready for planning

<domain>
## Phase Boundary

Wire the existing notification infrastructure (NotificationsController, shell.js bell, ag-toast, SSE EventBroadcaster) into a complete notification system. Bell badge shows unread count, panel lists recent notifications with read/unread state, SSE events trigger real-time toasts.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion — infrastructure wiring phase. Key constraints:
- `NotificationsController::list()` already exists with NOTIF_ACTIONS and read/unread state
- `shell.js` already has `createNotifBell()` with bell icon, badge, and panel scaffolding
- `Shared.showToast()` and `ag-toast` Web Component already exist
- `EventBroadcaster` and SSE infrastructure already exist (events.php, SseListener, Redis fan-out)
- `notification_reads` table already exists for read state tracking
- Bell polling at 60s intervals already wired in shell.js
- Mark-as-read endpoint needed (NotificationsController::markRead)
- SSE events to trigger toasts: motion_opened, quorum_met/quorum_updated, meeting_launched/meeting_closed
- Toast messages should be in French, matching the "officiel et confiance" tone

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `NotificationsController::list()` — returns audit events as notifications with read/unread state
- `shell.js:createNotifBell()` — creates bell icon + badge + panel in header
- `shell.js:loadNotifications()` — fetches and renders notifications (already implemented)
- `Shared.showToast(message, type)` — shows ag-toast notification
- `EventBroadcaster::toMeeting()` — broadcasts SSE events to meeting channel
- `event-stream.js` — SSE client with reconnection logic

### Integration Points
- `public/assets/js/core/shell.js` — bell + panel + polling
- `public/assets/js/core/event-stream.js` — SSE event handling
- `app/Controller/NotificationsController.php` — notification API
- `app/routes.php` — notifications route already defined
- `app/SSE/EventBroadcaster.php` — event broadcasting

</code_context>

<specifics>
## Specific Ideas

No specific requirements — infrastructure phase.

</specifics>

<deferred>
## Deferred Ideas

None.

</deferred>
