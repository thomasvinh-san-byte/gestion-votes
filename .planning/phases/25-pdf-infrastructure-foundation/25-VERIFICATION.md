---
phase: 25-pdf-infrastructure-foundation
verified: 2026-03-18T14:30:00Z
status: passed
score: 11/11 truths verified
re_verification:
  previous_status: gaps_found
  previous_score: 8/11
  gaps_closed:
    - "Serve endpoint now returns 401 for unauthenticated requests (no ?token= param)"
    - "Voter token holders can access serve endpoint via ?token= query param with VoteTokenRepository::findByHash()"
    - "PDF-09 REQUIREMENTS.md updated to reflect native iframe decision; requirements ambiguity resolved"
  gaps_remaining: []
  regressions: []
---

# Phase 25: PDF Infrastructure Foundation — Verification Report

**Phase Goal:** PDF documents can be securely uploaded, stored, served, and previewed — all two P0 security blockers resolved before any viewer UI is built
**Verified:** 2026-03-18T14:30:00Z
**Status:** passed
**Re-verification:** Yes — after gap closure

---

## Gap Closure Verification

### Gap 1 — Unauthenticated request now returns 401 (CLOSED)

Previous failure: `'role' => 'public'` on the route meant no auth was enforced at middleware level; `api_current_tenant_id()` fell back to the default UUID, and the document lookup returned 404, not 401.

Fix confirmed in `ResolutionDocumentController::serve()` lines 160-182:

- `api_current_user_id()` is called first; for unauthenticated requests with `'role' => 'public'`, `authenticate()` is called via `getCurrentUser()` — it finds no valid session and returns `null`, so `$userId === null`.
- The code then reads `api_query('token')` (`$_GET['token']`). If the query param is absent or empty, `api_fail('authentication_required', 401)` fires immediately before any tenant or document lookup.
- Result: a bare unauthenticated request (no `?token=` param) returns **401**, not 404.

### Gap 2 — Voter token path now functional (CLOSED)

Previous failure: `$_SESSION['meeting_id']` was checked, but no voter auth flow writes that key; all voter requests returned 403.

Fix confirmed in `ResolutionDocumentController::serve()` lines 168-182:

- `$rawToken = api_query('token')` reads the raw token from `?token=` query param.
- `hash_hmac('sha256', $rawToken, APP_SECRET)` produces the stored hash (consistent with how tokens are issued — `APP_SECRET` is defined in `Application::loadConfig()` from `getenv('APP_SECRET')`).
- `$this->repo()->voteToken()->findByHash($tokenHash)` — `VoteTokenRepository::findByHash()` exists at line 39, queries `vote_tokens WHERE token_hash = :hash`, returns `tenant_id` and `meeting_id`.
- `$tenantId` and `$tokenMeetingId` are populated from the token row; the subsequent `findById($id, $tenantId)` and meeting cross-check at line 190 follow correctly.
- Result: a voter presenting a valid token via `?token=<raw>` gets the PDF; invalid tokens get 401; cross-meeting access gets 403.

### Gap 3 — PDF-09 requirements ambiguity resolved (CLOSED)

`.planning/REQUIREMENTS.md` line 40 now reads:

> `[x] PDF-09: PDF viewer uses native browser iframe (CVE-2024-4367 does not apply); PDF.js deferred to v5+ if programmatic API needed`

The requirement text no longer says "self-hosted PDF.js v5.5.207+". It accurately reflects the locked decision. The `[x]` mark is now factually correct.

---

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | resolution_documents table exists with correct columns and indexes | VERIFIED | `database/migrations/20260318_resolution_documents.sql` — correct DDL with tenant_id, meeting_id, motion_id, display_order, and 3 indexes |
| 2 | Upload endpoint accepts PDF, validates MIME/size, stores to AGVOTE_UPLOAD_DIR | VERIFIED | `ResolutionDocumentController::upload()` — finfo MIME check, 10MB limit, `AG_UPLOAD_DIR . '/resolutions/' . $motionId` |
| 3 | Serve endpoint returns PDF with all 5 security headers for authenticated users | VERIFIED | Lines 203-208: Content-Type, Content-Disposition, X-Content-Type-Options: nosniff, Cache-Control: private no-store, X-Frame-Options: SAMEORIGIN; readfile() + exit |
| 4 | Serve endpoint returns 401 for unauthenticated requests | VERIFIED | Lines 168-171: `api_query('token')` empty → `api_fail('authentication_required', 401)` before any tenant/doc lookup; invalid token hash → 401 |
| 5 | A voter with valid vote token can view a PDF | VERIFIED | Lines 174-182: `hash_hmac('sha256', $rawToken, APP_SECRET)` → `voteToken()->findByHash()` → `tenant_id` + `meeting_id` from token row; meeting cross-check at line 190 |
| 6 | AGVOTE_UPLOAD_DIR env var used everywhere instead of hardcoded /tmp | VERIFIED | `app/bootstrap.php` lines 51-52: `define('AG_UPLOAD_DIR', ...getenv('AGVOTE_UPLOAD_DIR')...)`; MeetingAttachmentController uses AG_UPLOAD_DIR; no hardcoded /tmp/ag-vote/uploads remain |
| 7 | Docker volume 'uploads' defined and mounted at /var/agvote/uploads | VERIFIED | docker-compose.yml: `uploads:/var/agvote/uploads`; bottom `volumes: uploads: driver: local`; Dockerfile creates and chowns `/var/agvote/uploads` |
| 8 | ag-pdf-viewer Web Component renders in inline, sheet, and panel modes | VERIFIED | ag-pdf-viewer.js: CSS `:host([mode="sheet"])`, `:host([mode="panel"])`, `:host([mode="inline"])` selectors; open/close via attribute; iframe with sandbox attribute |
| 9 | FilePond in wizard step 3 accepts only PDF/10MB with inline errors in French | VERIFIED | wizard.js: `acceptedFileTypes: ['application/pdf']`, `maxFileSize: '10MB'`, French error messages; CDN plugins load before core |
| 10 | Hub badges show doc count; voter view shows Consulter button; SSE updates voter in real-time | VERIFIED | hub.js: `loadDocBadges`, `renderDocBadge`, "Aucun document"/"documents joints"; vote.js: `_currentMotionDocs` state, SSE `document.added`/`document.removed` handlers, no `allow-download` attribute |
| 11 | PDF-09: Native iframe decision locked in requirements (CVE-2024-4367 does not apply) | VERIFIED | REQUIREMENTS.md line 40: `[x] PDF-09: PDF viewer uses native browser iframe (CVE-2024-4367 does not apply); PDF.js deferred to v5+` |

**Score:** 11/11 truths verified

---

## Required Artifacts

### Plan 01 Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `database/migrations/20260318_resolution_documents.sql` | DDL with tenant_id, motion_id, display_order, 3 indexes | VERIFIED | All columns present; CREATE TABLE IF NOT EXISTS; idx_resolution_docs_motion/meeting/tenant |
| `app/Repository/ResolutionDocumentRepository.php` | 6 methods: listForMotion, create, findById, delete, countForMotion, listForMeeting | VERIFIED | All 6 methods; php -l passes |
| `app/Controller/ResolutionDocumentController.php` | listForMotion, upload, delete, serve with dual auth | VERIFIED | 213 lines; all 4 methods; php -l passes; EventBroadcaster imported and used; dual auth (session + token) implemented |
| `public/api/v1/resolution_documents.php` | Route dispatch file | VERIFIED | Full dispatch file with match(api_method()); not a stub |
| `public/api/v1/resolution_document_serve.php` | Route dispatch file | VERIFIED | Full dispatch file calling $c->handle('serve') |
| `app/Core/Providers/RepositoryFactory.php` | resolutionDocument() accessor | VERIFIED | `public function resolutionDocument(): ResolutionDocumentRepository` |
| `app/bootstrap.php` | AG_UPLOAD_DIR constant | VERIFIED | `define('AG_UPLOAD_DIR', ...getenv('AGVOTE_UPLOAD_DIR')...)` |

### Plan 02 Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `public/assets/js/components/ag-pdf-viewer.js` | Web Component with 3 modes | VERIFIED | 338 lines; class AgPdfViewer extends HTMLElement; customElements.define; all 3 modes in CSS; escapeHtml; Escape key; allow-download; custom events |
| `public/assets/css/design-system.css` | ag-pdf-viewer host-level CSS | VERIFIED | `ag-pdf-viewer[mode="sheet"]`, `ag-pdf-viewer[mode="panel"]` display: block |
| `public/wizard.htmx.html` | FilePond CDN script/link tags | VERIFIED | filepond@4.32.12 CSS in head; plugins before filepond.min.js in body |

### Plan 03 Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/WebSocket/EventBroadcaster.php` | documentAdded/documentRemoved methods | VERIFIED | Both static methods broadcasting 'document.added' and 'document.removed' |
| `public/assets/js/core/event-stream.js` | document.added/removed in eventTypes | VERIFIED | 'document.added', 'document.removed' present |
| `public/assets/js/pages/hub.js` | Doc badges with loadDocBadges/renderDocBadge | VERIFIED | All 3 functions; "Aucun document"; "documents joints"; ag-pdf-viewer panel mode |
| `public/assets/js/pages/operator-tabs.js` | Upload button per motion card | VERIFIED | addDocUploadToMotionCard; 10 Mo validation; 'filepond' field name in FormData |
| `public/assets/js/pages/vote.js` | Voter consultation + SSE handlers | VERIFIED | _currentMotionDocs; loadMotionDocs; mode="sheet"; NO allow-download; SSE document.added/removed |
| `public/vote.htmx.html` | btnConsultDocument button (hidden) | VERIFIED | id="btnConsultDocument" hidden type="button" |

---

## Key Link Verification

### Plan 01 Key Links

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `ResolutionDocumentController.php` | `ResolutionDocumentRepository` | `$this->repo()->resolutionDocument()` | WIRED | Lines 24, 76, 129, 165, 184 all call repo()->resolutionDocument() |
| `ResolutionDocumentController.php` | `VoteTokenRepository` | `$this->repo()->voteToken()->findByHash()` | WIRED | Line 175: `$this->repo()->voteToken()->findByHash($tokenHash)` — accessor exists in RepositoryFactory line 105 |
| `app/routes.php` | `ResolutionDocumentController` | route registration | WIRED | Lines 232-241: mapMulti + map('GET', serve, 'public') |
| `ResolutionDocumentController.php` | `AG_UPLOAD_DIR` constant | storage path | WIRED | Lines 71, 135, 194 use `AG_UPLOAD_DIR . '/resolutions/...'` |

### Plan 02 Key Links

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `ag-pdf-viewer.js` | `/api/v1/resolution_document_serve` | iframe src attribute | WIRED | src attribute change updates iframe.src directly |
| `wizard.js` | `/api/v1/resolution_documents` | FilePond server config | WIRED | `url: '/api/v1/resolution_documents'` in FilePond process config |

### Plan 03 Key Links

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `vote.js` | `event-stream.js` | SSE event listener for document.added | WIRED | vote.js: if (type === 'document.added'), if (type === 'document.removed') |
| `ResolutionDocumentController.php` | `EventBroadcaster.php` | EventBroadcaster::documentAdded() in upload() | WIRED | Lines 105, 148: calls to documentAdded and documentRemoved |

---

## Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|------------|-------------|--------|----------|
| PDF-01 | 25-01 | resolution_documents DB table and migration | SATISFIED | Migration SQL with correct schema, FKs, indexes |
| PDF-02 | 25-01 | ResolutionDocumentController upload, list, delete, serve | SATISFIED | 4 methods in controller; all present; PHP lint passes |
| PDF-03 | 25-01 | Secure serve endpoint — auth + headers | SATISFIED | Unauthenticated returns 401; voter token path via findByHash(); security headers present; meeting cross-check for voter cross-access |
| PDF-04 | 25-01 | AGVOTE_UPLOAD_DIR env var replacing /tmp paths | SATISFIED | AG_UPLOAD_DIR in bootstrap; no hardcoded /tmp/ag-vote/uploads in controllers |
| PDF-05 | 25-01 | Docker volume for persistent PDF storage | SATISFIED | Named volume 'uploads' at /var/agvote/uploads in docker-compose.yml + Dockerfile |
| PDF-06 | 25-02 | FilePond upload in wizard step 3 (PDF only, 10MB max) | SATISFIED | FilePond CDN tags; acceptedFileTypes; maxFileSize; French error messages |
| PDF-07 | 25-02 | ag-pdf-viewer Web Component inline + sheet modes | SATISFIED | ag-pdf-viewer.js with inline/sheet/panel CSS modes; Shadow DOM; registered globally |
| PDF-08 | 25-03 | PDF viewer wired to wizard, hub, voter view | SATISFIED | Hub badges; operator upload; voter consultation button; SSE events |
| PDF-09 | 25-02 | Native browser iframe; PDF.js deferred; CVE-2024-4367 N/A | SATISFIED | REQUIREMENTS.md updated; native iframe confirmed; no PDF.js dependency needed |
| PDF-10 | 25-03 | Voter consultation read-only, no download | SATISFIED | vote.js has no allow-download attribute; mode="sheet" confirmed |

### Orphaned Requirements

None — all PDF-01 through PDF-10 are claimed by exactly one plan each and all satisfied.

---

## Anti-Patterns Found

None remaining. Both P0 blockers from the initial verification are resolved:

- The `serve()` endpoint now performs its own auth check before any tenant/document lookup.
- The voter token path uses `VoteTokenRepository::findByHash()` — the established pattern in this codebase — not a session key that was never written.

No stubs, no hardcoded /tmp paths, no allow-download in vote.js.

---

## Human Verification Required

### 1. PDF serves correctly in browser iframe

**Test:** Log in as an operator, upload a PDF in wizard step 3, navigate to the hub, click the document badge, verify the PDF renders inside ag-pdf-viewer panel mode.
**Expected:** PDF appears inline without a download prompt; viewer header shows filename; Escape key closes the panel.
**Why human:** Native browser PDF rendering inside Shadow DOM iframe cannot be verified by grep.

### 2. Voter token PDF access end-to-end

**Test:** Using a voter link (URL with `?token=<raw_token>`), open a motion with an attached PDF and tap "Consulter le document". The viewer should render the PDF via `?token=` forwarded in the iframe src.
**Expected:** PDF renders in bottom-sheet; no 401/403 error; no download button visible.
**Why human:** Token hash computation and iframe src construction with ?token= forwarding requires real request flow.

### 3. FilePond drag-and-drop in wizard step 3

**Test:** Open wizard step 3 with an existing meeting, drag a non-PDF file onto the FilePond zone, observe error; then drag a valid PDF under 10MB and observe success card.
**Expected:** Wrong type shows "Seuls les fichiers PDF sont acceptes" inline before upload; large file shows "Le fichier depasse 10 Mo" inline before upload; valid PDF shows a doc card with filename, size, preview, and delete buttons.
**Why human:** FilePond validation UX and card rendering require browser interaction.

### 4. Bottom sheet transition on mobile

**Test:** Open vote.htmx.html on a mobile device (or DevTools mobile viewport) while a motion with an attached PDF is open; tap "Consulter le document".
**Expected:** ag-pdf-viewer slides up from the bottom with CSS transition; PDF renders; tapping X or backdrop closes it without navigating away from the ballot.
**Why human:** CSS transition behavior and touch interaction require manual testing.

---

## Summary

All three gaps from the initial verification are closed. The phase goal is achieved.

**Gap 1 (401 for unauthenticated):** `serve()` now calls `api_current_user_id()` first (which internally calls `authenticate()` — safely returning null when no session exists), then explicitly checks for `?token=` and returns 401 if absent. The `'role' => 'public'` on the route is intentional and correct — the controller owns its auth logic for this dual-auth endpoint.

**Gap 2 (voter token path):** Replaced the broken `$_SESSION['meeting_id']` check with `VoteTokenRepository::findByHash()`, which is the actual pattern used by the vote token system. The raw token from `?token=` is hashed with `hash_hmac('sha256', $rawToken, APP_SECRET)` (matching the token issuance pattern) before lookup. The `tenant_id` and `meeting_id` come directly from the token row, which is correct and secure.

**Gap 3 (PDF-09 requirements):** REQUIREMENTS.md updated at line 40 to accurately describe the native iframe decision with an explicit note that CVE-2024-4367 does not apply. The `[x]` mark is now factually correct.

---

*Verified: 2026-03-18T14:30:00Z*
*Verifier: Claude (gsd-verifier)*
*Re-verification after gap closure*
