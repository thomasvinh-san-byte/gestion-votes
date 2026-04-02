---
phase: 65-attachment-upload-serve
plan: 02
subsystem: ui
tags: [filepond, file-upload, attachments, wizard, operator-console]

# Dependency graph
requires:
  - phase: 65-01
    provides: MeetingAttachmentController with upload/list/delete API endpoints at /api/v1/meeting_attachments
provides:
  - Wizard step 3 post-creation attachment upload section (FilePond, name='file', POSTs to meeting_attachments)
  - Operator console tab-seance attachment management (list, add via native file input, delete)
  - Hub accordion Documents section wired to show attachment count
affects:
  - 66-voter-attachment-access (voter-facing view of attachments uploaded here)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "FilePond attachment pond: name='file' to match MeetingAttachmentController::api_file('file')"
    - "Post-creation flow: wizard shows optional upload section after meeting creation, redirects via btnGoToHub"
    - "Operator attachment management: native file input + fetch POST, no FilePond dependency in operator console"
    - "loadMeetingAttachments called from loadAllData on every meeting load"

key-files:
  created: []
  modified:
    - public/wizard.htmx.html
    - public/assets/js/pages/wizard.js
    - public/operator.htmx.html
    - public/assets/js/pages/operator-tabs.js

key-decisions:
  - "Wizard uses FilePond (mirrors step 2 resolution pond pattern) with name='file' for meeting attachments"
  - "After meeting creation, wizard shows attachment section instead of immediate redirect — user clicks btnGoToHub when done"
  - "Operator console uses native file input (no FilePond) mirroring addDocUploadToMotionCard pattern"
  - "loadMeetingAttachments wired into loadAllData so attachments load on every meeting context switch"
  - "No SSE/EventBroadcaster for attachment changes — pre-session documents do not need real-time broadcast"

patterns-established:
  - "createdMeetingId stored in outer IIFE scope, accessible to both btnCreate and btnGoToHub handlers"
  - "renderMeetingAttachments/updateHubDocuments: two-target refresh — tab-seance list + hub accordion"

requirements-completed: [ATTACH-01, ATTACH-02]

# Metrics
duration: 15min
completed: 2026-04-01
---

# Phase 65 Plan 02: Attachment Upload & Serve UI Summary

**FilePond attachment upload in wizard post-creation flow and native file-input attachment management in operator console tab-seance, both targeting /api/v1/meeting_attachments with field name 'file'**

## Performance

- **Duration:** 15 min
- **Started:** 2026-04-01T08:08:00Z
- **Completed:** 2026-04-01T08:23:10Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments
- Wizard step 3 shows wizAttachmentSection after meeting creation, with FilePond configured for PDF upload to /api/v1/meeting_attachments and a "Continuer vers la console" button
- Operator console tab-seance has meetingAttachmentSection with attachment list, native file input for adding PDFs, and per-item delete buttons with confirmation
- Hub accordion "Documents" section now updates with attachment count whenever meeting context loads
- loadMeetingAttachments is called from loadAllData ensuring attachments refresh on every meeting switch

## Task Commits

Each task was committed atomically:

1. **Task 1: Wizard post-creation attachment upload** - `a1350367` (feat)
2. **Task 2: Operator console attachment management** - `a1ab7dfd` (feat)

**Plan metadata:** (docs commit below)

## Files Created/Modified
- `public/wizard.htmx.html` - Added wizAttachmentSection with FilePond input and btnGoToHub
- `public/assets/js/pages/wizard.js` - Added initAttachmentPond, renderAttachmentCard, modified btnCreate handler
- `public/operator.htmx.html` - Added meetingAttachmentSection in tab-seance
- `public/assets/js/pages/operator-tabs.js` - Added loadMeetingAttachments, renderMeetingAttachments, updateHubDocuments, deleteMeetingAttachment, file input event handler

## Decisions Made
- Wizard uses FilePond (matching existing step 2 resolution pond pattern) with `name: 'file'` critical to match MeetingAttachmentController
- Operator console uses native file input (not FilePond) — mirrors existing addDocUploadToMotionCard pattern, avoids FilePond dependency in operator JS
- No SSE/EventBroadcaster for attachment changes — meeting attachments are pre-session documents, no EventBroadcaster method exists for them

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Attachment upload and management UI complete for operators
- Phase 66 (voter attachment access) can now build on top: serve endpoint exists (65-01) and attachments are populated via this UI (65-02)
- Hub accordion Documents section ready to be enriched in 66 with actual links/viewer

## Self-Check: PASSED

- SUMMARY.md: FOUND
- wizard.htmx.html: FOUND (wizAttachmentSection, wizAttachmentPondInput, btnGoToHub)
- wizard.js: FOUND (initAttachmentPond, renderAttachmentCard, name='file')
- operator.htmx.html: FOUND (meetingAttachmentSection, meetingAttachmentList, meetingAttachmentFileInput)
- operator-tabs.js: FOUND (loadMeetingAttachments, renderMeetingAttachments, updateHubDocuments, deleteMeetingAttachment)
- Commit a1350367: FOUND
- Commit a1ab7dfd: FOUND

---
*Phase: 65-attachment-upload-serve*
*Completed: 2026-04-01*
