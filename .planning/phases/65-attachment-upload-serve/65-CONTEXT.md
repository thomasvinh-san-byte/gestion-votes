# Phase 65: Attachment Upload & Serve - Context

**Gathered:** 2026-04-01
**Status:** Ready for planning

<domain>
## Phase Boundary

Add meeting-level attachment upload in wizard step 1, management in operator console, and a dual-auth serve endpoint for voter access. MeetingAttachmentController already has upload/list/delete for operators — this phase adds serve() and wires the frontend.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion — infrastructure phase. Key constraints:
- `MeetingAttachmentController` already has upload/list/delete (operator-only routes)
- Mirror `ResolutionDocumentController::serve()` dual-auth pattern (session OR vote token) for the new serve endpoint
- Mirror wizard step 2 FilePond pattern (initResolutionPond) for meeting attachments in step 1
- Mirror operator console resolution doc upload pattern (addDocUploadToMotionCard) for meeting attachment management
- Use existing ag-pdf-viewer Web Component for preview
- PDF-only upload (10MB max, matching existing MeetingAttachmentController limits)
- Route: `meeting_attachment_serve` with role=public + rate_limit (matching resolution_document_serve pattern)

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `MeetingAttachmentController::upload/list/delete()` — already implemented
- `ResolutionDocumentController::serve()` lines 153-213 — dual auth pattern to copy
- `wizard.js:initResolutionPond()` lines 530-588 — FilePond pattern to mirror
- `operator-tabs.js:addDocUploadToMotionCard()` lines 3306-3376 — upload pattern to mirror
- `ag-pdf-viewer.js` — Web Component with inline/sheet/panel modes

### Integration Points
- `app/Controller/MeetingAttachmentController.php` — add serve() method
- `app/routes.php` — add meeting_attachment_serve route
- `public/assets/js/pages/wizard.js` — add FilePond in step 1
- `public/wizard.htmx.html` — add attachment upload section in step 1
- `public/assets/js/pages/operator-tabs.js` — add attachment management section
- `public/operator.htmx.html` or `public/partials/operator-live-tabs.html` — add attachment HTML

</code_context>

<specifics>
## Specific Ideas

No specific requirements — infrastructure phase.

</specifics>

<deferred>
## Deferred Ideas

None.

</deferred>
