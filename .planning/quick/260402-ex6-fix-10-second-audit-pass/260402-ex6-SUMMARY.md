---
phase: 260402-ex6
plan: 01
subsystem: security, import, export, voter-ux
tags: [security, csv-injection, xss, rate-limit, voter-ux, import, export]
dependency_graph:
  requires: []
  provides: [csv-injection-protection, email-html-escaping, ratelimit-reset-command, voter-html-pages, import-size-validation, in-batch-dedup, export-ob-flush]
  affects: [ExportService, EmailTemplateService, VotePublicController, ImportController, ExportController]
tech_stack:
  added: []
  patterns: [sanitizeCsvCell, renderHtml, ob_end_clean before CSV streaming]
key_files:
  created:
    - app/Command/RateLimitResetCommand.php
    - app/Templates/vote_motion_closed.php
    - app/Templates/vote_already_cast.php
    - app/Templates/vote_meeting_validated.php
    - app/Templates/vote_token_expired.php
  modified:
    - app/Services/ExportService.php
    - app/Services/EmailTemplateService.php
    - app/Controller/VotePublicController.php
    - app/Controller/ImportController.php
    - app/Controller/ExportController.php
    - bin/console
decisions:
  - "vote_token_expired.php created as generic invalid/error page and reused for weight validation errors (no separate template needed)"
  - "motion_closed_concurrent case absent from VotePublicController — plan referenced it but it was not in the existing code"
  - "In-batch dedup in processMemberRows uses $seenEmails map — complements the pre-scan checkDuplicateEmails() which aborts early"
metrics:
  duration: ~15 minutes
  completed: 2026-04-02
  tasks_completed: 3
  files_created: 5
  files_modified: 6
---

# Quick Task 260402-ex6: Fix 10 Second-Pass Audit Findings Summary

**One-liner:** 10 security/UX/robustness findings fixed — CSV formula injection prevention, HTML email XSS escaping, lockout reset CLI command, styled voter outcome pages, import size limits, in-batch dedup, CSV export buffer flush, and descriptive draft-export error.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Security fixes — CSV injection, email XSS, lockout recovery | 25f20426 | ExportService.php, EmailTemplateService.php, RateLimitResetCommand.php, bin/console |
| 2 | Voter UX — proper HTML pages for vote outcomes | 2c9e3e62 | VotePublicController.php, vote_motion_closed.php, vote_already_cast.php, vote_meeting_validated.php, vote_token_expired.php |
| 3 | Import/Export robustness — file size, in-batch dedup, streaming, draft export guard | f56a3865 | ImportController.php, ExportController.php |

## What Was Built

### Task 1: Security Fixes

**Finding 1 — CSV injection (ExportService):**
Added `sanitizeCsvCell()` private method that prefixes cells starting with `=`, `+`, `-`, or `@` with a tab character. `writeCsvRow()` now maps all values through this sanitizer before calling `fputcsv()`.

**Finding 2 — XSS in HTML emails (EmailTemplateService):**
Added `renderHtml()` method that HTML-escapes all variable values using `htmlspecialchars(ENT_QUOTES|ENT_SUBSTITUTE, UTF-8)` before substitution. `renderTemplate()` now uses `renderHtml()` for `body_html` while keeping `render()` for subjects and plain-text bodies (where escaping would corrupt content).

**Finding 3 — No lockout recovery (RateLimitResetCommand):**
Created `app/Command/RateLimitResetCommand.php` with `ratelimit:reset` command. Requires `--context` and `--identifier` options. Calls `RateLimiter::reset()` directly. Registered in `bin/console` with usage documentation.

### Task 2: Voter UX Pages

Created four HTML templates with login-card layout (matching `vote_confirm.php`):
- `vote_motion_closed.php` — "Ce vote est maintenant clos" (motion_not_open, 409)
- `vote_already_cast.php` — "Votre vote a déjà été enregistré" (token_already_used, 409)
- `vote_meeting_validated.php` — "La séance est clôturée" (meeting_validated_at, 409)
- `vote_token_expired.php` — "Ce lien de vote est invalide ou expiré" (invalid token, weight validation, generic 400)

All `HtmlView::text()` calls for terminal error states in `VotePublicController::doVote()` replaced with `HtmlView::render()`.

### Task 3: Import/Export Robustness

**Finding 7 — File upload size (ImportController):**
- `readImportFile()` now checks file size before processing (10 MB limit via `api_fail('file_too_large', 400)`)
- `membersCsv()` file-upload path also validates size before `validateUploadedFile()`

**Finding 8 — In-batch dedup (ImportController):**
`processMemberRows()` now tracks a `$seenEmails` map. When a duplicate email is found within the batch, it adds an error entry `"Email en double dans le fichier: {email} (déjà à la ligne {N})"` and skips the row. This complements the existing `checkDuplicateEmails()` pre-scan.

**Finding 9 — CSV export buffering (ExportController):**
Each of the 5 CSV export methods (`attendanceCsv`, `votesCsv`, `membersCsv`, `motionResultsCsv`, `ballotsAuditCsv`) now calls `if (ob_get_level() > 0) { ob_end_clean(); }` before initializing CSV output headers.

**Finding 10 — Draft export error message (ExportController):**
`requireValidatedMeeting()` now returns `api_fail('meeting_not_validated', 409, ['detail' => "Les exports ne sont disponibles qu'après la validation de la séance."])`.

## Deviations from Plan

**1. [Rule 1 - Bug] motion_closed_concurrent case not found in VotePublicController**
- **Found during:** Task 2
- **Issue:** Plan specified replacing `HtmlView::text('Ce vote est clos', 409)` for `motion_closed_concurrent` but that catch branch was not present in the existing controller
- **Fix:** No change needed — the code path simply did not exist
- **Impact:** None — the existing `token_already_used` catch handler covers that case or the exception propagates

**2. [Rule 2 - Extension] Created vote_token_expired.php (not originally planned as a template)**
- **Found during:** Task 2
- **Issue:** Plan said to reuse `vote_token_expired.php` for weight validation errors, but the template did not exist
- **Fix:** Created the template using the same login-card layout as the other voter outcome pages
- **Files created:** `app/Templates/vote_token_expired.php`

## Self-Check

### Files exist

- [x] app/Services/ExportService.php — modified
- [x] app/Services/EmailTemplateService.php — modified
- [x] app/Command/RateLimitResetCommand.php — created
- [x] app/Templates/vote_motion_closed.php — created
- [x] app/Templates/vote_already_cast.php — created
- [x] app/Templates/vote_meeting_validated.php — created
- [x] app/Templates/vote_token_expired.php — created
- [x] app/Controller/VotePublicController.php — modified
- [x] app/Controller/ImportController.php — modified
- [x] app/Controller/ExportController.php — modified
- [x] bin/console — modified

### Commits exist

- 25f20426 — Task 1: security fixes
- 2c9e3e62 — Task 2: voter UX pages
- f56a3865 — Task 3: import/export robustness

## Self-Check: PASSED
