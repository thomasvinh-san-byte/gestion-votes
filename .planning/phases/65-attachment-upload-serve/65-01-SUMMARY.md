---
phase: 65-attachment-upload-serve
plan: "01"
subsystem: backend
tags: [controller, auth, pdf-serve, unit-tests]
dependency_graph:
  requires:
    - MeetingAttachmentController (existing upload/list/delete)
    - ResolutionDocumentController::serve() (pattern reused)
    - VoteTokenRepository::findByHash (dual-auth)
  provides:
    - GET /api/v1/meeting_attachment_serve (public, dual-auth)
  affects:
    - app/routes.php (new route entry)
    - app/Controller/MeetingAttachmentController.php (new method)
    - tests/Unit/MeetingAttachmentControllerTest.php (7 new tests)
tech_stack:
  added: []
  patterns:
    - Dual-auth: session OR vote token (hash_hmac sha256 + findByHash)
    - Tenant-scoped findById
    - PDF serve with security headers (X-Content-Type-Options, Cache-Control, X-Frame-Options)
    - doc_serve rate limit bucket shared with resolution_document_serve
key_files:
  created: []
  modified:
    - app/Controller/MeetingAttachmentController.php
    - app/routes.php
    - tests/Unit/MeetingAttachmentControllerTest.php
decisions:
  - "Reuse doc_serve rate limit bucket (120 req/60s) — same use case as resolution_document_serve"
  - "Use $att variable name (not $doc) for clarity in MeetingAttachmentController::serve()"
  - "No SSE broadcast in serve() — meeting attachments are pre-session documents"
metrics:
  duration: "~4 minutes"
  tasks_completed: 2
  files_changed: 3
  completed_date: "2026-04-01"
---

# Phase 65 Plan 01: Attachment Serve Endpoint Summary

**One-liner:** Dual-auth serve() endpoint for meeting PDF attachments — session user OR vote token holder, with meeting-scoped 403 guard and shared rate limit bucket.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Add serve() method and route | 51fb498e | app/Controller/MeetingAttachmentController.php, app/routes.php |
| 2 | Add serve() unit tests | c1e28a0f | tests/Unit/MeetingAttachmentControllerTest.php |

## What Was Built

### serve() Method (MeetingAttachmentController)

Added `serve()` method after `delete()` implementing:
- **Input validation:** UUID check on `?id=` param -> 400 missing_id
- **Session auth path:** `api_current_user_id()` not null -> use `api_current_tenant_id()`
- **Token auth path:** hash_hmac sha256 -> `voteToken()->findByHash()` -> extract tenant + meeting
- **Authentication gate:** no session + no token -> 401 authentication_required
- **Invalid token:** findByHash returns null -> 401 invalid_token
- **Attachment lookup:** tenant-scoped `meetingAttachment()->findById()` -> 404 not_found
- **Cross-meeting guard:** token meeting_id !== attachment meeting_id -> 403 access_denied
- **File serve:** AG_UPLOAD_DIR/meetings/{meeting_id}/{stored_name} with PDF headers

### Route Registration (routes.php)

```php
$router->map('GET', "{$prefix}/meeting_attachment_serve",
    MeetingAttachmentController::class, 'serve',
    ['role' => 'public', 'rate_limit' => ['doc_serve', 120, 60]]
);
```
Inserted after the meeting_attachments mapMulti block. Shares `doc_serve` bucket with resolution_document_serve.

### Unit Tests (MeetingAttachmentControllerTest)

7 new tests covering all serve() validation paths:
1. `testServeMissingId` — 400 missing_id
2. `testServeInvalidId` — 400 missing_id (bad UUID)
3. `testServeWithSessionUserDocNotFound` — 404 not_found
4. `testServeWithNoAuthRequiresToken` — 401 authentication_required
5. `testServeWithInvalidToken` — 401 invalid_token
6. `testServeWithValidTokenButAttachmentNotFound` — 404 not_found
7. `testServeWithTokenForWrongMeeting` — 403 access_denied

Updated `testControllerHasRequiredMethods` to include `'serve'`. Added `VoteTokenRepository` use statement. Auth-enabled tests use try/finally to restore `APP_AUTH_ENABLED=0` and `AuthMiddleware::reset()`.

**Result:** 21 tests, 43 assertions, 0 failures, 0 errors.

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

- [x] app/Controller/MeetingAttachmentController.php — contains `public function serve`
- [x] app/routes.php — contains `meeting_attachment_serve`
- [x] tests/Unit/MeetingAttachmentControllerTest.php — contains all 7 new test methods
- [x] Commit 51fb498e exists
- [x] Commit c1e28a0f exists
- [x] 21 tests pass, 0 failures
