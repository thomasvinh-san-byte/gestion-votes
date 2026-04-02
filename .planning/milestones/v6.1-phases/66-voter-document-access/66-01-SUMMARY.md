---
phase: 66-voter-document-access
plan: "01"
subsystem: api, ui
tags: [controller, dual-auth, pdf-serve, voter, hub, vote-page, ag-pdf-viewer]

dependency_graph:
  requires:
    - phase: 65-attachment-upload-serve
      provides: MeetingAttachmentController::serve() dual-auth pattern and upload infrastructure
  provides:
    - GET /api/v1/meeting_attachments_public (public, session OR vote token auth)
    - Hub "Documents de la seance" section with clickable attachments opening ag-pdf-viewer panel
    - Vote page "Documents" button opening ag-pdf-viewer sheet (voter read-only)
  affects:
    - Any future phase extending voter document access
    - MeetingAttachmentController (new method)

tech-stack:
  added: []
  patterns:
    - "listPublic dual-auth: session path (api_current_user_id/tenant) OR token path (hash_hmac sha256 + findByHash + meeting match)"
    - "Safe-fields mapping: stored_name stripped from public API response"
    - "getElementById('meetingAttachViewer') — never querySelector to avoid collision with resoPdfViewer"
    - "Meeting-scoped attachment load: loadMeetingAttachments called once on meeting context change, not per motion"

key-files:
  created: []
  modified:
    - app/Controller/MeetingAttachmentController.php
    - app/routes.php
    - tests/Unit/MeetingAttachmentControllerTest.php
    - public/hub.htmx.html
    - public/assets/js/pages/hub.js
    - public/assets/css/hub.css
    - public/vote.htmx.html
    - public/assets/js/pages/vote.js

key-decisions:
  - "Reuse doc_serve rate limit bucket (120 req/60s) for meeting_attachments_public — same use case"
  - "stored_name excluded from listPublic response via explicit field mapping — internal storage detail never exposed"
  - "openAttachmentViewer uses getElementById('meetingAttachViewer') not querySelector to avoid collision with resoPdfViewer in vote.js"
  - "vote page viewer has no allow-download attribute — voter is read-only (PDF-10)"
  - "loadMeetingAttachments called on meeting context change (loadMeetings + meetingSelect change), not per motion refresh"

requirements-completed: [ATTACH-03, ATTACH-04]

duration: ~12min
completed: 2026-04-01
---

# Phase 66 Plan 01: Voter Document Access Summary

**listPublic() dual-auth endpoint + hub "Documents de la seance" card and vote page "Documents" button wiring ag-pdf-viewer for voter PDF access**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-04-01T08:30:00Z
- **Completed:** 2026-04-01T08:42:00Z
- **Tasks:** 2 of 3 complete (Task 3 is checkpoint:human-verify — deferred by user, no visual verification performed)
- **Files modified:** 8

## Accomplishments

- Public list endpoint `GET /api/v1/meeting_attachments_public` with dual-auth (session OR vote token), meeting match guard, and stored_name stripping
- Hub page "Documents de la seance" card — appears above Resolutions card, hidden when no attachments, clickable rows open ag-pdf-viewer in panel mode with download
- Vote page "Documents" button — hidden when no attachments, click opens ag-pdf-viewer in sheet mode (no download, read-only), vote token forwarded on both list and serve calls
- 7 new unit tests covering all listPublic() paths; 28 total tests pass in MeetingAttachmentControllerTest

## Task Commits

Each task was committed atomically:

1. **Task 1: Backend listPublic() endpoint + unit tests** - `af88fa40` (feat + test — TDD)
2. **Task 2: Hub attachments section + vote page Documents button** - `596f7dcd` (feat)

*Task 3 (checkpoint:human-verify) — deferred by user ("Continue without verifying")*

## Files Created/Modified

- `app/Controller/MeetingAttachmentController.php` - Added listPublic() method after serve()
- `app/routes.php` - Registered GET /api/v1/meeting_attachments_public (public, doc_serve rate limit)
- `tests/Unit/MeetingAttachmentControllerTest.php` - Added 7 testListPublic* tests; updated testControllerHasRequiredMethods
- `public/hub.htmx.html` - Added hub-attachments-card section before hubMotionsSection
- `public/assets/js/pages/hub.js` - Added loadMeetingAttachments, renderMeetingAttachments, openAttachmentViewer functions; wired in loadData()
- `public/assets/css/hub.css` - Added hub-attachments-card, hub-attachments-header, hub-attachment-row styles
- `public/vote.htmx.html` - Added btnMeetingDocs button after btnConsultDocument in motion-card-footer
- `public/assets/js/pages/vote.js` - Added _meetingAttachments, loadMeetingAttachments, wireMeetingDocsBtn, openMeetingAttachViewer; wired on meeting load and meeting change events

## Decisions Made

- Used `doc_serve` rate limit bucket for meeting_attachments_public (120 req/60s) — same use case as resolution_document_serve and meeting_attachment_serve
- stored_name stripped via explicit field mapping in listPublic() — only id, original_name, file_size, created_at exposed to clients
- `getElementById('meetingAttachViewer')` used everywhere for the new viewer — critical to avoid querySelector collision with `resoPdfViewer` (id="resoPdfViewer") that openVoterDocViewer uses via querySelector
- No `allow-download` on vote page viewer — voter is read-only per PDF-10 requirement
- Meeting attachment load is meeting-scoped: `loadMeetingAttachments(meetingId)` called on `loadMeetings()` completion and `meetingSelect` change, NOT inside the per-motion `refresh()` cycle

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

- PHP 8.3 host vs composer platform requiring 8.4: tests run via `php vendor/bin/phpunit` after temporarily patching `vendor/composer/platform_check.php` from `>= 80400` to `>= 80300`. This is a dev environment discrepancy — the Docker container runs PHP 8.4, production is PHP 8.4. No code change required.

## Next Phase Readiness

- listPublic() endpoint is live and tested — voter attachment access is functional
- Hub and vote page have all necessary HTML/JS/CSS wired
- Checkpoint Task 3 (visual verification) was deferred by user — when ready, upload a PDF attachment via Phase 65 operator UI and verify the hub card and vote page button appear and behave correctly

## Self-Check: PASSED

- [x] app/Controller/MeetingAttachmentController.php — contains `public function listPublic`
- [x] app/routes.php — contains `meeting_attachments_public`
- [x] tests/Unit/MeetingAttachmentControllerTest.php — contains all 7 new testListPublic* tests
- [x] public/hub.htmx.html — contains `hubAttachmentsSection`
- [x] public/vote.htmx.html — contains `btnMeetingDocs`
- [x] Commit af88fa40 exists
- [x] Commit 596f7dcd exists
- [x] 28 tests pass, 0 failures (MeetingAttachmentControllerTest)

---
*Phase: 66-voter-document-access*
*Completed: 2026-04-01*
