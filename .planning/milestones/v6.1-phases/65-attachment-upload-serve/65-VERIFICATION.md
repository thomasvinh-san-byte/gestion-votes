---
phase: 65-attachment-upload-serve
verified: 2026-04-01T00:00:00Z
status: passed
score: 9/9 must-haves verified
re_verification: false
---

# Phase 65: Attachment Upload & Serve Verification Report

**Phase Goal:** Operators can upload meeting attachments during session creation and manage them from the console, with a secure serve endpoint ready for voter access
**Verified:** 2026-04-01
**Status:** PASSED
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | GET /api/v1/meeting_attachment_serve?id=UUID returns PDF binary with correct Content-Type for session-authenticated users | VERIFIED | `serve()` in MeetingAttachmentController.php L133–192: session path sets tenantId via `api_current_tenant_id()`, sends `Content-Type: application/pdf` header, calls `readfile($path)` |
| 2 | GET /api/v1/meeting_attachment_serve?id=UUID&token=VALID returns PDF binary for vote token holders whose token belongs to the same meeting | VERIFIED | Token path: hash_hmac sha256 -> `voteToken()->findByHash()` -> cross-meeting guard at L170; 403 returned if mismatch, file served otherwise |
| 3 | GET /api/v1/meeting_attachment_serve without auth returns 401 authentication_required | VERIFIED | L148–151: `$rawToken === ''` -> `api_fail('authentication_required', 401)`; `testServeWithNoAuthRequiresToken` test confirmed |
| 4 | GET /api/v1/meeting_attachment_serve with token for wrong meeting returns 403 access_denied | VERIFIED | L170–172: `$tokenMeetingId !== $att['meeting_id']` -> `api_fail('access_denied', 403)`; `testServeWithTokenForWrongMeeting` test confirmed |
| 5 | GET /api/v1/meeting_attachment_serve with invalid UUID returns 400 missing_id | VERIFIED | L136–138: `!api_is_uuid($id)` -> `api_fail('missing_id', 400)`; `testServeMissingId` + `testServeInvalidId` tests confirmed |
| 6 | Operator can upload PDF attachments during session creation in the wizard | VERIFIED | `wizAttachmentSection` in wizard.htmx.html L456; `initAttachmentPond(meetingId)` in wizard.js L590 POSTs to `/api/v1/meeting_attachments` with `name: 'file'`; shown after meeting creation succeeds |
| 7 | Operator can view the list of existing attachments in the operator console Seance tab | VERIFIED | `meetingAttachmentSection` + `meetingAttachmentList` in operator.htmx.html L517–522; `renderMeetingAttachments()` in operator-tabs.js L3421 renders cards; `loadMeetingAttachments` called from `loadAllData` at L494 |
| 8 | Operator can add new attachments from the operator console Seance tab | VERIFIED | `meetingAttachmentFileInput` in operator.htmx.html L530; `fetch('/api/v1/meeting_attachments', { method: 'POST', ... })` in operator-tabs.js L3501 with `formData.append('file', file)` |
| 9 | Operator can delete existing attachments from the operator console Seance tab | VERIFIED | `deleteMeetingAttachment(id, name)` in operator-tabs.js L3464; calls `window.api('/api/v1/meeting_attachments', { id: id }, 'DELETE')`; delete button wired per-card at L3447 |

**Score:** 9/9 truths verified

---

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/Controller/MeetingAttachmentController.php` | serve() method with dual-auth (session OR vote token) | VERIFIED | L133–192: full dual-auth implementation, `meetingAttachment()->findById()`, `meetings/{meeting_id}/` path, all security headers, no resolutionDocument refs, no EventBroadcaster |
| `app/routes.php` | meeting_attachment_serve route with role=public and rate_limit | VERIFIED | L233–235: `$router->map('GET', .../meeting_attachment_serve, MeetingAttachmentController::class, 'serve', ['role' => 'public', 'rate_limit' => ['doc_serve', 120, 60]])` |
| `tests/Unit/MeetingAttachmentControllerTest.php` | Unit tests for serve() validation paths | VERIFIED | All 7 serve() tests present; `testControllerHasRequiredMethods` updated to include 'serve'; `VoteTokenRepository` imported |
| `public/wizard.htmx.html` | Attachment upload section in wizard step 3 review | VERIFIED | L456: `wizAttachmentSection` hidden div, `wizAttachmentPondInput`, `btnGoToHub` all present |
| `public/assets/js/pages/wizard.js` | initAttachmentPond function and post-creation attachment flow | VERIFIED | L590: `initAttachmentPond(meetingId)` with `name: 'file'`, POSTs to `/api/v1/meeting_attachments`; `renderAttachmentCard` at L651; `createdMeetingId` scoped correctly; `btnCreate` handler calls `initAttachmentPond` at L1064 |
| `public/operator.htmx.html` | Attachment management section in tab-seance | VERIFIED | L517–530: `meetingAttachmentSection`, `meetingAttachmentList`, `meetingAttachmentFileInput` all present |
| `public/assets/js/pages/operator-tabs.js` | loadMeetingAttachments, uploadMeetingAttachment, deleteMeetingAttachment functions | VERIFIED | L3410: `loadMeetingAttachments`; L3421: `renderMeetingAttachments`; L3453: `updateHubDocuments`; L3464: `deleteMeetingAttachment`; file input change handler at L3496 |

---

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `app/routes.php` | `app/Controller/MeetingAttachmentController.php` | route mapping meeting_attachment_serve -> serve() | WIRED | L233–234: `$router->map('GET', .../meeting_attachment_serve, MeetingAttachmentController::class, 'serve', ...)` |
| `app/Controller/MeetingAttachmentController.php` | `app/Repository/MeetingAttachmentRepository.php` | findById for tenant-scoped lookup | WIRED | L164: `$this->repo()->meetingAttachment()->findById($id, $tenantId)` |
| `public/assets/js/pages/wizard.js` | `/api/v1/meeting_attachments` | FilePond server.process.url | WIRED | L612: `url: '/api/v1/meeting_attachments'` in FilePond config; `name: 'file'` matches controller |
| `public/assets/js/pages/operator-tabs.js` | `/api/v1/meeting_attachments` | fetch POST and DELETE calls | WIRED | L3501: `fetch('/api/v1/meeting_attachments', { method: 'POST', ... })`; L3466: `window.api('/api/v1/meeting_attachments', { id: id }, 'DELETE')` |
| `public/assets/js/pages/operator-tabs.js` | `public/operator.htmx.html` | DOM manipulation of meetingAttachmentList element | WIRED | L3421: `document.getElementById('meetingAttachmentList')` — renders cards into element defined in operator.htmx.html; `loadMeetingAttachments` called at L494 inside `loadAllData` |

---

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|-------------|-------------|--------|----------|
| ATTACH-01 | 65-02-PLAN.md | Operator peut uploader des pieces jointes PDF depuis le wizard de creation | SATISFIED | `wizAttachmentSection` shown post-creation; `initAttachmentPond` POSTs to meeting_attachments with `name: 'file'`; `btnGoToHub` redirects after upload |
| ATTACH-02 | 65-02-PLAN.md | Operator peut gerer (voir, ajouter, supprimer) les pieces jointes depuis la console operateur | SATISFIED | `meetingAttachmentSection` with list/add/delete; `loadMeetingAttachments` called from `loadAllData`; delete has confirmation dialog and toast feedback |
| ATTACH-05 | 65-01-PLAN.md | Endpoint serve securise pour votants (session OU token de vote) | SATISFIED | `serve()` with full dual-auth; 401/403/404 guards all implemented; `meeting_attachment_serve` route registered as public with rate limiting |

All 3 requirement IDs declared across plans are accounted for. No orphaned requirements found in REQUIREMENTS.md for Phase 65.

---

### Anti-Patterns Found

No blockers found. Notable items:

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `public/assets/js/pages/operator-tabs.js` | 3472 | `window._currentMeetingId` used in delete handler — plan comment, but actual code uses `currentMeetingId` closure variable correctly | Info | No impact — `currentMeetingId` is the closure-scoped variable, plan snippet showed `window._currentMeetingId` as illustration only; actual code at L3472 uses the correct local reference |

---

### Human Verification Required

#### 1. Wizard attachment upload post-creation flow

**Test:** Create a new session via the wizard through step 3. After clicking "Creer la seance", verify that the page shows the attachment upload section (FilePond pond) instead of immediately redirecting. Upload a PDF, verify a card appears. Then click "Continuer vers la console" and confirm redirect to the hub.
**Expected:** FilePond renders, PDF uploads successfully, card shows with filename and size, redirect works.
**Why human:** Post-creation DOM manipulation, FilePond initialization timing, and redirect behavior cannot be verified statically.

#### 2. Operator console attachment management

**Test:** Open the operator console for a meeting, go to tab-seance. Verify the "Pieces jointes de la seance" section is visible with an "Ajouter un PDF" button. Upload a PDF and confirm the card appears with a delete button. Click delete and confirm the card is removed.
**Expected:** Section visible, upload succeeds with toast "Piece jointe ajoutee", delete shows confirmation then removes card with toast "Piece jointe supprimee".
**Why human:** Browser upload interaction, toast display, and DOM update after delete require live browser testing.

#### 3. Serve endpoint with vote token authentication

**Test:** Using a valid voter token for a meeting, attempt to GET /api/v1/meeting_attachment_serve?id=UUID&token=TOKEN for an attachment belonging to that meeting. Also test with a token from a different meeting.
**Expected:** Correct meeting: PDF served inline. Wrong meeting: 403 access_denied JSON response.
**Why human:** End-to-end test requires a real vote token, real attachment record, and real file on disk.

---

### Gaps Summary

No gaps found. All 9 observable truths verified against the actual codebase:

- `serve()` is a complete, substantive implementation (not a stub) with dual-auth, tenant scoping, cross-meeting guard, all security headers, and `readfile()`/`exit`.
- The route is registered as public with the shared `doc_serve` rate-limit bucket.
- All 7 unit tests exist and cover every validation path; `testControllerHasRequiredMethods` includes `'serve'`.
- The wizard attachment section is present in HTML and the JS `initAttachmentPond` function is properly wired into the `btnCreate` success handler.
- The operator console attachment section is present in HTML with all three elements (list, file input, label button), and `loadMeetingAttachments` is called from `loadAllData` ensuring it runs on every meeting context switch.
- No forbidden patterns: no `resolutionDocument` refs in `MeetingAttachmentController`, no `EventBroadcaster` calls, no `name: 'filepond'` in attachment FilePond config, `formData.append('file', ...)` used correctly throughout.
- Both PHP files pass syntax check. All 4 phase commits verified in git log.

---

_Verified: 2026-04-01_
_Verifier: Claude (gsd-verifier)_
