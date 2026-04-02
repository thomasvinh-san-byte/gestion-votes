# Phase 79: SSE & Async Robustness - Context

**Gathered:** 2026-04-02
**Status:** Ready for planning
**Mode:** Auto-generated (frontend infrastructure)

<domain>
## Phase Boundary

SSE connections do not leak on navigation; frontend async errors are visible to the user. Three JS fixes: (1) EventSource cleanup on page unload, (2) async error handling in operator-realtime.js, (3) SSE fallback polling shows notification.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion — frontend JS fixes. Key references:
- public/assets/js/core/event-stream.js for EventSource management
- public/assets/js/pages/operator-realtime.js for async error handling
- ag-toast Web Component for notifications

</decisions>

<code_context>
## Existing Code Insights

Codebase context will be gathered during planning.

</code_context>

<specifics>
## Specific Ideas

No specific requirements beyond ROADMAP success criteria.

</specifics>

<deferred>
## Deferred Ideas

None.

</deferred>
