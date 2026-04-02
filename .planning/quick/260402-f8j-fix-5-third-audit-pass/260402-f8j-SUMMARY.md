---
phase: quick
plan: 260402-f8j
subsystem: config, upload, email
tags: [env, security, disk-guard, email-rendering]
depends_on: []
provides:
  - Complete .env.example with all documented env vars
  - Disk-space guard in MeetingAttachmentController::upload()
  - renderHtml() used consistently throughout EmailQueueService
tech_stack:
  added: []
  patterns:
    - disk_free_space() guard before file storage
    - renderHtml() for all body template rendering in email queue
key_files:
  created: []
  modified:
    - .env.example
    - app/Controller/MeetingAttachmentController.php
    - app/Services/EmailQueueService.php
decisions:
  - Application.php APP_SECRET empty guard already correct — no code change needed ('' already in $insecureSecrets)
metrics:
  duration: ~8 minutes
  completed: "2026-04-02T11:02:53Z"
  tasks_completed: 2
  tasks_total: 2
  files_modified: 3
---

# Quick Task 260402-f8j: Third Audit Pass — Config surface, disk guard, renderHtml

**One-liner:** Add 11 missing env vars to .env.example, guard uploads against low disk space (507), and replace 14 render() calls with renderHtml() in EmailQueueService.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | .env.example missing vars + disk-space check | 26b751ce | .env.example, MeetingAttachmentController.php |
| 2 | EmailQueueService — replace render() with renderHtml() | e97f1834 | EmailQueueService.php |

## What Was Done

### Task 1: .env.example + Disk Guard

**A. .env.example** — Added 11 new environment variables in documented sections:
- `APP_URL` — application public URL
- `API_KEY_OPERATOR`, `API_KEY_TRUST`, `API_KEY_ADMIN` — programmatic API access keys
- `MAIL_TLS`, `MAIL_TIMEOUT` — SMTP transport options
- `APP_LOGIN_MAX_ATTEMPTS`, `APP_LOGIN_WINDOW` — auth rate limiting
- `EMAIL_TRACKING_ENABLED` — email tracking pixel toggle
- `PROXY_MAX_PER_RECEIVER` — proxy vote limit per voter
- `AGVOTE_UPLOAD_DIR` — persistent upload volume path (uncommented with production note)

Existing monitoring vars (`MONITOR_AUTH_FAILURES_THRESHOLD`, `MONITOR_DB_LATENCY_MS`, `MONITOR_DISK_FREE_PCT`, `MONITOR_EMAIL_BACKLOG`, `MONITOR_ALERT_EMAILS`, `MONITOR_WEBHOOK_URL`) were already present as commented examples — confirmed, no change needed.

**B. Application.php** — Audit finding confirmed resolved: `''` (empty string) is already in `$insecureSecrets` array at line 153. No code change required.

**C. MeetingAttachmentController::upload()** — Added `disk_free_space()` check after `mkdir()`, before `move_uploaded_file()`. Returns HTTP 507 with French error message when free space < 50 MB.

### Task 2: EmailQueueService renderHtml() fix

Replaced all 14 occurrences of `$this->templateService->render(` with `$this->templateService->renderHtml(` across the four methods that render email bodies: `scheduleInvitations()`, `scheduleReminders()`, `scheduleResults()`, `sendInvitationsNow()`. The 4 `renderTemplate()` calls were left unchanged. All 32 unit tests pass (1 skipped, expected).

## Verification

```
php -l app/Services/EmailQueueService.php          -> No syntax errors
php -l app/Controller/MeetingAttachmentController.php -> No syntax errors
php -l app/Core/Application.php                    -> No syntax errors
grep render( EmailQueueService.php | grep -v renderHtml|renderTemplate -> 0 results
grep disk_free_space MeetingAttachmentController.php -> 1 match
phpunit EmailQueueServiceTest.php                  -> 32/32 OK (1 skipped)
```

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check

- [x] `26b751ce` — feat(260402-f8j-01): add missing env vars, disk-space guard before upload
- [x] `e97f1834` — fix(260402-f8j-02): replace render() with renderHtml() in EmailQueueService (14 sites)
- [x] `.env.example` contains all 11 new vars
- [x] `disk_free_space()` present in MeetingAttachmentController.php
- [x] 0 plain `->render(` calls remain in EmailQueueService.php

## Self-Check: PASSED
