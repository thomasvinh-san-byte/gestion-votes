---
quick_id: 260402-eh3
title: Fix 11 Critical Path Findings
completed: "2026-04-02"
duration: ~40min
tasks_completed: 3
files_changed: 16
commits:
  - d5e1d421
  - df57647a
tags: [security, ux, backend, frontend, sse, password, rgpd, vote-engine]
---

# Quick Task 260402-eh3: Fix 11 Critical Path Findings

**One-liner:** Closed 11 security, UX, and data-integrity gaps — expired token page, RGPD audit anonymize, tie detection, shared password helper, meeting delete guards, president auto-assign, past vote badge, quorum toast, participation counter, multi-operator SSE badge.

## Tasks Completed

| Task | Description | Commit |
|------|-------------|--------|
| Task 1 | Backend fixes (findings 1-6, 9) | d5e1d421 |
| Task 2 | Frontend UX (findings 8, 10, 11) | df57647a |
| Task 3 | Multi-operator SSE presence badge (finding 7) | df57647a |

## Findings Resolved

### Finding 1 — Expired vote token page
- **Before:** `HtmlView::text('Token invalide ou expiré', 403)` — plain text 403
- **After:** `HtmlView::render('vote_token_expired', [], 403)` — branded French page with login.css
- **Files:** `app/Templates/vote_token_expired.php` (new), `app/Controller/VotePublicController.php`

### Finding 2 — Audit anonymize on RGPD hard-delete
- **Added:** `AuditEventRepository::anonymizeForResource()` — updates `resource_id = 'ERASED'` for member/invitation rows
- **Called in:** `AdminController::erase_member` and `DataRetentionCommand::execute()`
- **Files:** `app/Repository/AuditEventRepository.php`, `app/Controller/AdminController.php`, `app/Command/DataRetentionCommand.php`

### Finding 3 — Tie vote detection
- **Added:** `$tie` computation in `VoteEngine::computeDecision()` — true when ratio == threshold within 0.0001 epsilon and forWeight > 0
- **Added:** `MeetingReportService::majorityLine()` appends ` · Egalité des voix` when `$maj['tie']` is true
- **Files:** `app/Services/VoteEngine.php`, `app/Services/MeetingReportService.php`

### Finding 4 — Shared password strength validator
- **Created:** `app/Helper/PasswordValidator.php` — `PasswordValidator::validate()` enforces 8+ chars, 1 uppercase, 1 digit
- **Applied in:** `AccountController`, `PasswordResetController`, `SetupController` — replaced inline `strlen < 8` checks
- **Files:** `app/Helper/PasswordValidator.php` (new), 3 controllers

### Finding 5 — Meeting delete confirmation
- **Added:** `requireConfirmation()` moved from private `AdminController` to protected `AbstractController`
- **Applied:** `MeetingsController::deleteMeeting()` now calls `requireConfirmation()` first
- **Files:** `app/Controller/AbstractController.php`, `app/Controller/AdminController.php`, `app/Controller/MeetingsController.php`

### Finding 6 — Meeting delete warning counts
- **Added:** `delete_warning` response field in `deleteMeeting()` with motions, ballots, attendances counts
- **Uses:** `MeetingStatsRepository::countMotions()`, `countBallots()`, `WizardRepository::countAttendances()`
- **Files:** `app/Controller/MeetingsController.php`

### Finding 7 — Multi-operator SSE presence badge
- **Backend:** `events.php` tracks operator connections in Redis SET `sse:operators:{meetingId}` with 90s TTL
- **Backend:** Emits `operator.presence` event with count on SSE connect
- **Backend:** `register_shutdown_function` cleans up presence on ungraceful exit; graceful exit also cleans up
- **Backend:** `?heartbeat=1` param cheaply renews presence TTL without SSE loop
- **Frontend:** `updateOperatorPresenceBadge()` shows badge next to SSE indicator when count > 1
- **Frontend:** 60s heartbeat interval in `connectSSE()` renews presence TTL
- **Files:** `public/api/v1/events.php`, `public/assets/js/pages/operator-realtime.js`

### Finding 8 — Voter past vote badge
- **Added:** `showPastVoteBadge()` function in vote.js — shows "Vous avez voté : X" badge for non-secret motions
- **Called:** In `refresh()` after `updateMotionCard(m)` using `d?.my_vote ?? d?.member_ballot`
- **Files:** `public/assets/js/pages/vote.js`

### Finding 9 — President auto-assign on createMeeting
- **Added:** After `IdempotencyGuard::store()`, if `api_current_role() === 'president'`, auto-assigns president meeting role
- **Files:** `app/Controller/MeetingsController.php`

### Finding 10 — Quorum-met transition toast
- **Added:** `_prevQuorumMet` module-level var in operator-realtime.js
- **Updated:** `quorum.updated` SSE case — separated from `attendance.updated`, fires `setNotif('success', 'Quorum atteint !')` on false→true transition
- **Files:** `public/assets/js/pages/operator-realtime.js`

### Finding 11 — Participation counter X/Y ont vote
- **Updated:** `updateVoteParticipation()` — shows `"X/Y ont voté"` when both cast and eligible available, falls back to `"pct% ont voté"`
- **Files:** `public/assets/js/pages/vote.js`

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] requireConfirmation() was private in AdminController, not available to MeetingsController**
- **Found during:** Task 1 (Finding 5)
- **Issue:** The plan said to call `requireConfirmation()` in `MeetingsController::deleteMeeting()` but the method was private in `AdminController`
- **Fix:** Moved `requireConfirmation()` from `private` in `AdminController` to `protected` in `AbstractController` (both inherit it)
- **Files modified:** `app/Controller/AbstractController.php`, `app/Controller/AdminController.php`
- **Commit:** d5e1d421

**2. [Rule 1 - Bug] Plan used findActiveById() for role check but that query excludes the role column**
- **Found during:** Task 1 (Finding 9)
- **Issue:** `UserRepository::findActiveById()` only selects `id, name, password_hash` — not `role`
- **Fix:** Used `api_current_role()` (session-based) instead, which is the correct approach for checking the current user's system role
- **Files modified:** `app/Controller/MeetingsController.php`
- **Commit:** d5e1d421

**3. [Rule 1 - Bug] Heartbeat block placed before meetingId/consumerId were defined**
- **Found during:** Task 3
- **Issue:** Draft placement of `?heartbeat=1` handler referenced `$meetingId` and `$consumerId` before they were assigned
- **Fix:** Moved heartbeat block to after both variables are defined and validated
- **Files modified:** `public/api/v1/events.php`
- **Commit:** df57647a

## Verification

All 13 PHP files pass `php -l` (no syntax errors).
Unit tests: 12/12 pass (PasswordResetControllerTest + PasswordResetServiceTest).

## Self-Check: PASSED

- app/Helper/PasswordValidator.php — FOUND
- app/Templates/vote_token_expired.php — FOUND
- Commit d5e1d421 — FOUND
- Commit df57647a — FOUND
