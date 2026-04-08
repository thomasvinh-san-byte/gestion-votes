---
phase: 11-backend-wiring-fixes
plan: "03"
subsystem: tests
tags: [tests, dual-auth, security, attachments]
dependency_graph:
  requires: []
  provides: [MeetingAttachmentControllerPublicTest]
  affects: [MeetingAttachmentController]
tech_stack:
  added: []
  patterns: [FileServedOkException test pattern, dual-auth toggle via putenv+AuthMiddleware::reset]
key_files:
  created:
    - tests/Unit/MeetingAttachmentControllerPublicTest.php
  modified: []
decisions:
  - "Separate test class (PublicTest) rather than appending to existing test file — clearer ownership of dual-auth coverage"
  - "FileServedOkException caught directly (bypassing callController) for serve happy path, consistent with existing testServeSuccessWithSessionUser pattern"
  - "HMAC token hash computed in-test using APP_SECRET constant — deterministic, no hard-coded hash strings"
  - "tempFilePath/tempDir tracked as instance properties, cleaned up in tearDown — safe for parallel test runs"
metrics:
  duration: "8 minutes"
  completed: "2026-04-07"
  tasks_completed: 2
  files_created: 1
  files_modified: 0
---

# Phase 11 Plan 03: Meeting Attachment Dual-Auth Integration Tests Summary

One new test class delivering execution-level proof that `listPublic` and `serve` both handle session users and vote token holders correctly — with the stored_name security invariant explicitly asserted.

## What Was Built

**File:** `tests/Unit/MeetingAttachmentControllerPublicTest.php`

6 new test scenarios covering the dual-auth contract:

| # | Test name | Auth path | Assertion |
|---|-----------|-----------|-----------|
| 1 | `testListPublicSessionUserReturnsAttachmentList` | Session operator | 2 attachments returned; `stored_name` absent from payload |
| 2 | `testListPublicTokenUserReturnsAttachmentList` | Vote token | HMAC-verified token; 1 attachment returned; `stored_name` absent |
| 3 | `testListPublicTokenWrongMeetingReturns403` | Vote token (wrong meeting) | 403 `access_denied` |
| 4 | `testListPublicNoAuthReturns401` | No session, no token | 401 `authentication_required` |
| 5 | `testServeSessionUserThrowsFileServedOk` | Session operator | `FileServedOkException` with `filename=reglement.pdf`, `size=9999`, `mime=application/pdf` |
| 6 | `testServeFileMissingReturns404` | Session operator | 404 `file_not_found` when physical file absent |

## Security Invariant Verified

`stored_name` is asserted absent from `listPublic` responses in two independent tests (session path + token path):

```php
$this->assertArrayNotHasKey(
    'stored_name',
    $attachments[0],
    'stored_name must NEVER leak to clients (filesystem path leak)',
);
```

## Dual-Auth Coverage

Both auth branches are exercised at the execution level (not just reflection):
- **Session path:** `setAuth()` → `api_current_user_id()` returns non-null → tenant from session
- **Token path:** `putenv('APP_AUTH_ENABLED=1')` + `AuthMiddleware::reset()` → `api_current_user_id()` returns null → token lookup via HMAC hash

## Regression Check

`MeetingAttachmentControllerTest.php` (29 existing tests) continues to pass. Combined run: **35 tests, 87 assertions, 0 failures**.

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check

- [x] `tests/Unit/MeetingAttachmentControllerPublicTest.php` exists
- [x] Commit `9a8b8b6a` present in git log
- [x] 6 tests pass
- [x] No regression in existing test file

## Self-Check: PASSED
