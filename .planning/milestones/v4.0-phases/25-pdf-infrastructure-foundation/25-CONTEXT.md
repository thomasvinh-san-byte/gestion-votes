# Phase 25: PDF Infrastructure Foundation - Context

**Gathered:** 2026-03-18
**Status:** Ready for planning

<domain>
## Phase Boundary

Deliver the complete PDF pipeline for resolution documents: upload (FilePond), persistent storage (Docker volume), secure authenticated serving, and inline viewing (ag-pdf-viewer Web Component). Close two P0 security blockers (CVE-2024-4367, missing serve endpoint). Wire PDF upload into wizard step 3, hub document status, operator console motions panel, and voter view bottom sheet. Add SSE event for live document additions.

</domain>

<decisions>
## Implementation Decisions

### PDF Viewer Experience
- **Mobile (voter):** Bottom sheet — slide-up panel from bottom of screen. Voter stays in voting context. Draggable to full-screen.
- **Desktop (wizard, hub, operator):** Side panel (slide-over from right). Page context remains visible alongside the PDF.
- **Viewer tech:** Native browser viewer first (`<iframe>` or `<embed>`). Zero dependency. Defer PDF.js custom rendering to v5+ only if native proves insufficient.
- **Download policy:** Voter = read-only, no download. Operator/admin = can download.

### Upload Workflow
- **Multiple PDFs per resolution** — one resolution can have several documents (e.g., annexe A, annexe B)
- **Upload timing: maximum flexibility** — attach during wizard creation, from the hub before meeting, AND from the operator console during a live session. Never lock the user out of adding documents.
- **Upload UX:** FilePond with drag-and-drop zone + browse button. PDF-only, 10MB max per file.
- **Post-upload display:** Card per file — PDF icon + filename + file size + preview button. Delete with ag-confirm confirmation dialog (no accidental deletion).
- **Inline error messages:** FilePond shows validation errors inline before submission (wrong type, too large).

### Storage & Security
- **Storage location:** Persistent Docker volume at configurable path via `AGVOTE_UPLOAD_DIR` env var (default: `/var/agvote/uploads/`). Migrate from current hardcoded `/tmp/ag-vote/`.
- **Access control:** Only authenticated users who are members of the meeting (operator + voters of that specific session) can view PDFs. Serve endpoint validates auth + tenant + meeting membership.
- **Security headers on serve:** `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`, `Content-Disposition: inline`.
- **PDF.js version:** Pin >= 4.2.67 (CVE-2024-4367 closed). If using PDF.js prebuilt viewer, set `isEvalSupported: false`.

### Hub & Operator Integration
- **Hub display:** Badge on each resolution in the hub checklist — "📎 2 documents joints" or "Aucun document". Click opens the side panel viewer.
- **Operator console:** Can add/remove PDFs from the motions panel during live session. No lock after freeze.
- **SSE notification:** New `documentAdded` SSE event when operator adds a PDF during live session. Voter view shows/updates "Consulter le document" button in real-time.

### Claude's Discretion
- DB migration schema details (indexes, constraints)
- ag-pdf-viewer internal implementation (iframe vs embed tag)
- FilePond plugin configuration specifics
- Docker volume mount path in docker-compose.yml
- Error handling patterns for upload failures

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Existing upload infrastructure (exact template to follow)
- `app/Controller/MeetingAttachmentController.php` — Complete upload/list/delete pattern with finfo MIME validation, but NO serve endpoint and hardcoded `/tmp/ag-vote/` path
- `app/Repository/MeetingAttachmentRepository.php` — Repository pattern for attachments
- `database/migrations/20260219_meeting_attachments.sql` — Schema template for new resolution_documents table
- `public/api/v1/meeting_attachments.php` — API routing pattern (GET/POST/DELETE)

### Web Component patterns
- `public/assets/js/components/ag-modal.js` — Modal/overlay component pattern (reference for slide-over panel)
- `public/assets/js/components/ag-confirm.js` — Confirmation dialog pattern (for delete confirmation)
- `public/assets/js/components/ag-toast.js` — Notification pattern (for upload success/error feedback)

### SSE infrastructure
- `app/WebSocket/EventBroadcaster.php` — Add `documentAdded` event following existing event patterns
- `public/assets/js/core/event-stream.js` — SSE client for handling new document events

### Research
- `.planning/research/STACK.md` — FilePond v4.32.12 integration, PDF.js prebuilt viewer approach, security patterns
- `.planning/research/ARCHITECTURE.md` — resolution_documents schema design, serve endpoint architecture, storage migration strategy
- `.planning/research/PITFALLS.md` — CVE-2024-4367 details, /tmp storage risk, tenant isolation gap

### Configuration
- `deploy/php.ini` — upload_max_filesize=10M and post_max_size=12M already set
- `deploy/nginx.conf` — May need CSP update for `object-src 'self'` to allow embedded PDFs

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `MeetingAttachmentController` — Exact template for ResolutionDocumentController. Copy and extend with serve() method and motion_id support.
- `MeetingAttachmentRepository` — Repository pattern with generateUuid(), create(), findById(), listForMeeting(), delete().
- 20 Web Components following `AgXxx extends HTMLElement` pattern — ag-pdf-viewer follows this convention.
- `ag-confirm` component — Use for delete confirmation on uploaded documents.
- `ag-toast` component — Use for upload success/error feedback.
- `EventBroadcaster` — Add `documentAdded()` static method following existing event patterns.

### Established Patterns
- IIFE + var for page scripts — FilePond integration follows this (CDN script tag, `window.FilePond` global).
- One CSS per page — ag-pdf-viewer styles go in design-system.css (component CSS) not a page CSS file.
- `api_fail()` / `api_ok()` exception-based response — serve endpoint follows this pattern.
- `finfo` MIME type detection — already used in MeetingAttachmentController for PDF validation.

### Integration Points
- `wizard.js` step 3 (résolutions) — Add FilePond upload zone per resolution card
- `hub.js` checklist — Add document badge per resolution
- `operator-motions.js` — Add upload capability and document badges on motion cards
- `vote.js` / `vote.htmx.html` — Add "Consulter le document" button that opens ag-pdf-viewer bottom sheet
- `app/routes.php` — Add routes for resolution_document endpoints
- `docker-compose.yml` — Add named volume for uploads

</code_context>

<specifics>
## Specific Ideas

- "Il faut viser la flexibilité, se retrouver coincé en pleine séance, c'est NON!" — Documents must be attachable at any time, including during live sessions. Never lock the operator out.
- Delete confirmation is critical — "la suppression fait peur" — use ag-confirm dialog, never instant delete.
- Multiple PDFs per resolution (annexes) is required, not optional.
- SSE notification for live document additions ensures voters are aware of new documents in real-time.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 25-pdf-infrastructure-foundation*
*Context gathered: 2026-03-18*
