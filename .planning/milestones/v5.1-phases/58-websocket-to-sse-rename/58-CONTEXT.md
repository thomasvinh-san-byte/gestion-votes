# Phase 58: WebSocket to SSE Rename - Context

**Gathered:** 2026-03-31
**Status:** Ready for planning

<domain>
## Phase Boundary

Rename the `AgVote\WebSocket` namespace to `AgVote\SSE` and the `WebSocketListener` class to `SseListener` across the entire PHP codebase. Eliminate all "WebSocket" terminology from PHP source files (excluding vendor/). The transport mechanism is SSE (Server-Sent Events) with Redis fan-out, not WebSockets.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion

All implementation choices are at Claude's discretion. This is a pure infrastructure rename phase with no user-facing behavior changes. Key decisions:

- Directory rename: `app/WebSocket/` to `app/SSE/`
- Class rename: `EventBroadcaster` stays (it's accurate), `WebSocketListener` becomes `SseListener`
- Namespace: `AgVote\WebSocket` becomes `AgVote\SSE`
- Update all `use` statements, autoloader registration, comments, and docblocks
- Update `Application.php` where `WebSocketListener::subscribe()` is called
- Verify autoloading works after rename
- Run full PHPUnit suite to confirm no breakage

</decisions>

<canonical_refs>
## Canonical References

No external specs. Requirements fully captured in decisions above. The rename is mechanical and guided by grep results.

</canonical_refs>

<code_context>
## Existing Code Insights

### Files to Rename
- `app/WebSocket/EventBroadcaster.php` (435 lines) : publisher SSE, stays as EventBroadcaster but moves to `AgVote\SSE` namespace
- `app/Event/Listener/WebSocketListener.php` : becomes `SseListener.php`

### References to Update (9 files)
- `app/Controller/AttendancesController.php` : `use AgVote\WebSocket\EventBroadcaster`
- `app/Controller/BallotsController.php` : same
- `app/Controller/MeetingWorkflowController.php` : same
- `app/Controller/ResolutionDocumentController.php` : same
- `app/Controller/OperatorController.php` : same
- `app/Controller/MotionsController.php` : same
- `app/Services/AttendancesService.php` : same
- `app/Services/BallotsService.php` : same
- `app/Core/Application.php` : `use AgVote\Event\Listener\WebSocketListener`

### Integration Points
- `public/api/v1/events.php` : SSE endpoint (references EventBroadcaster in comments only)
- `deploy/supervisord.conf` : no WebSocket references (already clean)
- Tests: `tests/Unit/` may reference the old namespace

</code_context>

<specifics>
## Specific Ideas

No specific requirements. Standard mechanical rename.

</specifics>

<deferred>
## Deferred Ideas

None. Discussion stayed within phase scope.

</deferred>

---

*Phase: 58-websocket-to-sse-rename*
*Context gathered: 2026-03-31*
