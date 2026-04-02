# Phase 59: Vote and Quorum Edge Cases - Context

**Gathered:** 2026-03-31
**Status:** Ready for planning

<domain>
## Phase Boundary

This phase hardens the voting and quorum subsystems to handle all failure modes explicitly. It covers: vote token validation errors (expired, reused, mismatched), closed-motion vote rejection, quorum division-by-zero safety, audit trail for vote anomalies, and real-time quorum SSE broadcasts on all attendee-change paths.

</domain>

<decisions>
## Implementation Decisions

### Error Response Strategy
- Catch RuntimeException in BallotsController and map to specific 4xx codes (409 for closed motion, 422 for invalid data) — consistent with existing api_fail pattern
- Error messages in French, matching existing codebase pattern (`'Token de vote invalide ou expiré.'`)
- Expired-token and already-used-token both return 401 with distinct `reason` field (`token_expired` vs `token_already_used`) — already implemented this way
- Closed-motion vote attempts include `motion_status: "closed"` in error payload so voter UI can show appropriate state

### Audit Trail for Vote Anomalies
- Double-vote attempts logged as `audit_log('vote_token_reuse', 'motion', $motionId, {token_hash, timestamp, ip, member_id, reason})` — full forensic trail
- Closed-motion vote attempts also audit-logged as `audit_log('vote_rejected', 'motion', $motionId, {reason: 'motion_closed', member_id})` — important for detecting automation/abuse
- Audit calls live in BallotsController (before `api_fail` return) — consistent with existing `audit_log('ballot.cast')` pattern
- IP address captured from `$_SERVER['REMOTE_ADDR']` — already used in existing audit infrastructure

### Quorum Real-Time Updates
- All 3 attendee-change paths in AttendancesController (add, remove, bulk) broadcast `quorum.updated` SSE event
- SSE event includes full quorum result from `QuorumEngine::computeForMeeting()` — ratio, threshold, met status, member counts
- Operator console JS handles `quorum.updated` SSE event to update display without page reload
- QuorumEngine zero-member behavior: return `ratio: 0.0, met: false` (current behavior) — add unit test to lock it

### Claude's Discretion
- Internal error handling patterns and code structure within the decided approach
- Test organization and naming

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `VoteTokenService::validateAndConsume()` — atomic validate+consume, returns `{valid, reason, token_hash, motion_id, member_id}`
- `QuorumEngine::computeForMeeting()` / `computeForMotion()` — full quorum calculation with policy support
- `EventBroadcaster::quorumUpdated()` — SSE broadcast helper already exists
- `BallotsService::castBallot()` — checks motion status, throws RuntimeException on closed/invalid
- `audit_log()` global helper — used throughout controllers for forensic logging
- `api_fail()` global helper — standardized JSON error response with HTTP code

### Established Patterns
- Controllers use `api_fail()` for error responses (not exceptions)
- Services throw `RuntimeException`/`InvalidArgumentException` — controllers catch and map
- Audit entries follow `audit_log($event, $entity_type, $entity_id, $data, $meeting_id)` signature
- SSE events broadcast via `EventBroadcaster::toMeeting()` static method
- All PHP files use `declare(strict_types=1)` and PSR-12

### Integration Points
- `BallotsController::cast()` (app/Controller/BallotsController.php:39) — main vote entry point
- `AttendancesController` (app/Controller/AttendancesController.php) — attendee add/remove/bulk with quorum broadcast
- `QuorumEngine::ratioBlock()` (app/Services/QuorumEngine.php:239) — division-by-zero guard at line 248
- Operator console JS — needs `quorum.updated` SSE event handler
- `public/partials/operator-live-tabs.html` — operator console template

</code_context>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches within the decided strategy.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>
