---
phase: 66-voter-document-access
verified: 2026-04-01T09:00:00Z
status: human_needed
score: 7/7 must-haves verified
re_verification: false
human_verification:
  - test: "Hub 'Documents de la seance' card visible with attachments"
    expected: "Card appears above Resolutions card, shows count badge and filenames; clicking an attachment opens ag-pdf-viewer in panel mode with PDF content and download button"
    why_human: "Visual appearance, panel open animation, and actual PDF rendering require a browser with a live meeting that has at least one PDF attachment"
  - test: "Vote page 'Documents' button visible and opens viewer"
    expected: "Button appears in motion-card-footer; clicking it opens ag-pdf-viewer in sheet mode (bottom sheet); no download button visible for voter read-only mode"
    why_human: "Sheet open animation, PDF rendering, and absence of download button require a browser at a token-authenticated vote URL"
  - test: "Both sections hidden when meeting has zero attachments"
    expected: "Hub card is not visible; vote page Documents button is not visible"
    why_human: "DOM hidden attribute behavior requires a browser against a meeting with no attachments"
---

# Phase 66: Voter Document Access — Verification Report

**Phase Goal:** Voters can consult all meeting attachments from the hub and from the vote page using ag-pdf-viewer
**Verified:** 2026-04-01T09:00:00Z
**Status:** human_needed — all automated checks pass; 3 visual/runtime items require human confirmation
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|---------|
| 1 | Hub displays a "Documents de la seance" section listing all meeting attachments | VERIFIED | `hub.htmx.html:201` — `<div class="hub-attachments-card" id="hubAttachmentsSection" hidden>`; `hub.js:318` — `renderMeetingAttachments` populates list and removes `hidden` when attachments present |
| 2 | Clicking an attachment in the hub opens ag-pdf-viewer in panel mode showing the PDF | VERIFIED | `hub.js:352-370` — `openAttachmentViewer` uses `getElementById('meetingAttachViewer')`, sets `mode='panel'` and `allow-download`, sets `src` to `/api/v1/meeting_attachment_serve?id=…`, calls `.open()` |
| 3 | Vote page displays a "Documents" button when meeting has attachments | VERIFIED | `vote.htmx.html:197` — `<button id="btnMeetingDocs" … hidden>`; `vote.js:921-923` — `docsBtn.hidden = false` when `resp.attachments.length > 0` |
| 4 | Clicking the Documents button on the vote page opens ag-pdf-viewer in sheet mode | VERIFIED | `vote.js:946-963` — `openMeetingAttachViewer` uses `getElementById('meetingAttachViewer')`, sets `mode='sheet'` (no `allow-download`), calls `.open()` |
| 5 | PDF loads for voters authenticated by session OR vote token | VERIFIED | `MeetingAttachmentController.php:133-181` — `listPublic()` has full dual-auth block; `vote.js:913-915` — token appended to list URL; `vote.js:956-958` — token appended to serve URL |
| 6 | Hub section is hidden when meeting has zero attachments | VERIFIED | `hub.js:324-326` — `if (!attachments \|\| attachments.length === 0) { section.hidden = true; return; }` |
| 7 | Vote page button is hidden when meeting has zero attachments | VERIFIED | `vote.js:907-908` — `loadMeetingAttachments` resets `btn.hidden = true` on entry; button is only un-hidden in the success handler when `resp.attachments.length > 0` |

**Score: 7/7 truths verified**

---

## Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/Controller/MeetingAttachmentController.php` | `listPublic()` method with dual-auth | VERIFIED | Line 133 — full method with session path + token path + meeting-match guard + stored_name stripping |
| `app/routes.php` | Public list route `meeting_attachments_public` | VERIFIED | Line 237 — `GET {prefix}/meeting_attachments_public`, role `public`, `doc_serve` rate limit |
| `tests/Unit/MeetingAttachmentControllerTest.php` | 7 `testListPublic*` methods | VERIFIED | Lines 427–614 — all 7 methods present; `testControllerHasRequiredMethods` updated to include `'listPublic'` |
| `public/hub.htmx.html` | Hub attachments section `hubAttachmentsSection` | VERIFIED | Line 201 — section with header, count badge, and list container |
| `public/assets/js/pages/hub.js` | `loadMeetingAttachments`, `openAttachmentViewer` | VERIFIED | Lines 308–370 — 3 functions present; wired in `loadData()` at line 606 |
| `public/assets/css/hub.css` | Hub attachments card styles | VERIFIED | Lines 404–448 — `.hub-attachments-card`, `.hub-attachments-header`, `.hub-attachment-row` (+ hover + last-child) |
| `public/vote.htmx.html` | `btnMeetingDocs` button | VERIFIED | Line 197 — button present after `btnConsultDocument`, starts `hidden` |
| `public/assets/js/pages/vote.js` | `wireMeetingDocsBtn`, `loadMeetingAttachments`, `openMeetingAttachViewer` | VERIFIED | Lines 905–963 and 934–944 and 946–963; wired at lines 1235 and 1250 |

---

## Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `public/assets/js/pages/hub.js` | `/api/v1/meeting_attachments_public` | `window.api()` in `loadMeetingAttachments` | WIRED | `hub.js:309` — exact URL with `meeting_id` param |
| `public/assets/js/pages/vote.js` | `/api/v1/meeting_attachments_public` | `window.api()` with token forwarding | WIRED | `vote.js:912-915` — URL built with `meeting_id`; token appended when present |
| `public/assets/js/pages/hub.js` | `ag-pdf-viewer#meetingAttachViewer` | `getElementById` in `openAttachmentViewer` | WIRED | `hub.js:353` — `getElementById('meetingAttachViewer')`, never querySelector |
| `public/assets/js/pages/vote.js` | `ag-pdf-viewer#meetingAttachViewer` | `getElementById` in `openMeetingAttachViewer` | WIRED | `vote.js:947` — `getElementById('meetingAttachViewer')`, never querySelector; no collision with `resoPdfViewer` |

---

## Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|-------------|-------------|--------|----------|
| ATTACH-03 | 66-01-PLAN.md | Voters can consult seance attachments from the hub ("Documents de la seance" section with ag-pdf-viewer) | SATISFIED | `hubAttachmentsSection` HTML + `loadMeetingAttachments`/`renderMeetingAttachments`/`openAttachmentViewer` in hub.js fully implement this |
| ATTACH-04 | 66-01-PLAN.md | Voters can consult seance attachments from the vote page ("Documents" button with ag-pdf-viewer) | SATISFIED | `btnMeetingDocs` HTML + `loadMeetingAttachments`/`wireMeetingDocsBtn`/`openMeetingAttachViewer` in vote.js fully implement this |

No orphaned requirements — both IDs declared in plan frontmatter are present in REQUIREMENTS.md with status Complete.

---

## Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `public/assets/js/pages/hub.js` | 309 | No token forwarding on hub list call | Info | Hub is operator-session-only context; token forwarding is not required there. Session auth handles hub access correctly. Not a bug. |

No blockers. No stubs. No placeholder returns. No TODO/FIXME comments in modified files.

---

## Human Verification Required

### 1. Hub attachment card renders and opens PDF

**Test:** Upload at least one PDF attachment to a meeting via the Phase 65 operator UI. Open the hub page for that meeting as an authenticated operator.
**Expected:** The "Documents de la seance" card appears above the Resolutions card. The count badge shows the correct number. Attachment filenames are clickable. Clicking one opens ag-pdf-viewer in panel mode (slides in from right) and displays the PDF. A download button is visible.
**Why human:** Visual appearance, panel animation, and actual PDF rendering require a browser session.

### 2. Vote page Documents button renders and opens PDF in sheet mode

**Test:** Open the vote page via a token URL (`/vote?token=xxx`) for a meeting that has at least one attachment.
**Expected:** A "Documents" button appears in the motion card footer alongside "Consulter le document". Clicking it opens ag-pdf-viewer in sheet mode (slides up from bottom) and displays the PDF. No download button is visible (voter read-only).
**Why human:** Sheet animation, PDF display, and absence of the download control require a browser at a live token URL.

### 3. Both sections hidden with zero attachments

**Test:** Open the hub page and vote page for a meeting that has NO attachments.
**Expected:** The "Documents de la seance" card is completely absent from the hub. The "Documents" button is not visible on the vote page.
**Why human:** DOM hidden state requires a browser against a meeting with no attachments.

---

## Commits Verified

| Hash | Description |
|------|-------------|
| `af88fa40` | feat(66-01): add listPublic() endpoint for voter meeting attachment access |
| `596f7dcd` | feat(66-01): hub attachments section and vote page documents button |

Both commits confirmed present in git log.

---

## Test Results

```
MeetingAttachmentControllerTest: 28 tests, 65 assertions — OK
  - 7 new testListPublic* tests all pass
  - All 21 prior tests unbroken
  - Only warning: no code coverage driver (dev environment — not a failure)
```

---

_Verified: 2026-04-01T09:00:00Z_
_Verifier: Claude (gsd-verifier)_
