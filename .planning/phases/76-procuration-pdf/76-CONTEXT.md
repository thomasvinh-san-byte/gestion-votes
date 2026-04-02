# Phase 76: Procuration PDF - Context

**Gathered:** 2026-04-02
**Status:** Ready for planning

<domain>
## Phase Boundary

Operators can download a legally valid pouvoir (procuration) PDF for any recorded delegation. The PDF contains mandant name, mandataire name, session details, and a legal mention. Builds on existing Dompdf infrastructure from MeetingReportService.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion. Use existing Dompdf patterns from MeetingReportService, ProxiesService for delegation data, and the established PDF endpoint pattern (Content-Disposition attachment).

Key references:
- MeetingReportService::generatePdf() for Dompdf usage
- ProxiesService / ProxiesController for delegation data access
- app/Templates/ for HTML template patterns

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
