# Phase 66: Voter Document Access - Context

**Gathered:** 2026-04-01
**Status:** Ready for planning

<domain>
## Phase Boundary

Add meeting attachment visibility for voters on the hub page (documents section) and vote page (documents button). Phase 65 delivered the serve endpoint — this phase wires the frontend. Resolution documents already work on both pages — mirror those patterns for meeting attachments.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion — frontend wiring phase. Key constraints:
- Hub already has resolution doc badges (hub.js:loadDocBadges) — add a "Documents de la séance" section above motions
- Vote page already has "Consulter le document" button (vote.js:loadMotionDocs) — add a "Documents" button for meeting attachments
- Use ag-pdf-viewer in panel mode (hub) and sheet mode (vote page, mobile-friendly)
- Serve endpoint: `/api/v1/meeting_attachment_serve?id={id}` (Phase 65, role=public with rate limit)
- Vote token must be appended for unauthenticated voters: `&token={token}` (same pattern as resolution docs)
- Meeting attachments list endpoint: `/api/v1/meeting_attachments?meeting_id={id}` (operator role — may need a public route or use the serve endpoint listing)

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `hub.js:loadDocBadges()` lines 253-280 — resolution doc badge pattern
- `hub.js:openDocViewer()` lines 282-304 — ag-pdf-viewer panel mode
- `vote.js:loadMotionDocs()` lines 840-859 — document loading pattern
- `vote.js:openVoterDocViewer()` lines 862-883 — ag-pdf-viewer sheet mode
- `ag-pdf-viewer.js` — inline/sheet/panel modes with allow-download attribute

### Integration Points
- `public/assets/js/pages/hub.js` — add meeting attachments section
- `public/hub.htmx.html` — add documents section HTML
- `public/assets/js/pages/vote.js` — add meeting attachments button
- `public/vote.htmx.html` — add documents button/section HTML
- May need a public list endpoint for meeting attachments (current list is operator-only)

</code_context>

<specifics>
## Specific Ideas

No specific requirements — frontend wiring phase.

</specifics>

<deferred>
## Deferred Ideas

None.

</deferred>
