# Phase 78: Data Integrity Locks - Context

**Gathered:** 2026-04-02
**Status:** Ready for planning
**Mode:** Auto-generated (infrastructure phase)

<domain>
## Phase Boundary

Concurrent ballot submissions and proxy validations cannot corrupt state. All ballot mutations and motion status changes use FOR UPDATE locks within transactions. Proxy chain validation is moved inside the transaction to fix TOCTOU.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion — pure backend integrity phase. Key areas:
- lockForUpdate() already exists in MeetingRepository (5 locations) — expand to cover all ballot/motion mutations
- ProxiesService has TOCTOU in proxy chain validation — move validation inside transaction scope
- BallotsService, MeetingWorkflowController, OperatorController are primary targets

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
