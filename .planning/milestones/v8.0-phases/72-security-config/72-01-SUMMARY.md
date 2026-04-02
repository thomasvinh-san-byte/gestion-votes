---
phase: 72-security-config
plan: 01
subsystem: admin-security
tags: [security, admin, password-confirmation, 2fa-lite]
dependency_graph:
  requires: []
  provides: [confirm_password verification on critical admin actions]
  affects: [AdminController, UserRepository, admin.js]
tech_stack:
  added: []
  patterns: [2-step confirmation via password_verify, requireConfirmation() helper method]
key_files:
  created: []
  modified:
    - app/Controller/AdminController.php
    - app/Repository/UserRepository.php
    - public/assets/js/pages/admin.js
    - tests/Unit/AdminControllerTest.php
decisions:
  - requireConfirmation() fires before other checks (self-delete, weak_password) — confirmation gate is the outermost guard on critical actions
  - findActiveById() updated to SELECT password_hash — required by confirmation check
  - 4 existing tests updated to include confirm_password (testUsersPostDeleteSelf, testUsersPostDeleteSuccess, testUsersPostSetPasswordSuccess, testUsersPostSetPasswordWeakPassword)
metrics:
  duration: ~15 minutes
  completed: 2026-04-02
  tasks_completed: 2
  files_changed: 4
---

# Phase 72 Plan 01: Security Config — 2-Step Confirmation Summary

**One-liner:** Admin delete and set_password actions now require password_verify() confirmation before executing, with inline modal field errors on failure.

## Tasks Completed

### Task 1: Backend — require confirm_password on delete and set_password (TDD)

**Commit:** c0cc7502

Added `requireConfirmation(array $in, string $tenantId): void` private helper to `AdminController`. The helper:
- Returns `400 confirmation_required` if `confirm_password` field is missing/empty
- Calls `UserRepository::findActiveById()` to get admin's own password hash
- Returns `400 confirmation_failed` + audit log `admin.confirm.failed` if `password_verify()` fails
- Called at the start of both `delete` and `set_password` action branches

Also updated `findActiveById()` in `UserRepository` to `SELECT id, name, password_hash` (was missing `password_hash`).

Tests (TDD):
- RED: 6 new tests added, 4 failing (confirmation_required/confirmation_failed not yet implemented)
- GREEN: All 51 AdminControllerTest tests pass after implementation
- 4 existing tests updated to include `confirm_password` since the gate fires before prior checks

### Task 2: Frontend — password confirmation modal for delete and set_password

**Commit:** 1a98658d

Modified `public/assets/js/pages/admin.js`:

**Delete user modal:** Added `<input type="password" id="confirmDeletePw">` with label "Votre mot de passe (confirmation)". `onConfirm` validates field is not empty, sends `confirm_password: pw` in API call. On `confirmation_failed` error from API, shows inline field error "Mot de passe incorrect" and returns false (keeps modal open).

**Set password modal:** Added third form-group `<input type="password" id="confirmAdminPw">` with label "Votre mot de passe (confirmation)" after the existing new/confirm password fields. `onConfirm` validates field, sends `confirm_password: adminPw` in API call. On `confirmation_failed`, shows inline field error on the confirm field.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Updated existing tests to pass confirm_password**
- **Found during:** Task 1 GREEN phase
- **Issue:** 4 existing tests (`testUsersPostDeleteSelf`, `testUsersPostDeleteSuccess`, `testUsersPostSetPasswordSuccess`, `testUsersPostSetPasswordWeakPassword`) broke because `requireConfirmation()` fires before their respective checks
- **Fix:** Added `confirm_password` + `findActiveById` mock to all 4 tests
- **Files modified:** `tests/Unit/AdminControllerTest.php`
- **Commit:** c0cc7502

## Self-Check

Checking created/modified files exist:
- app/Controller/AdminController.php — present
- app/Repository/UserRepository.php — present
- public/assets/js/pages/admin.js — present
- tests/Unit/AdminControllerTest.php — present

Checking commits exist:
- c0cc7502 — feat(72-01): require confirm_password on delete and set_password admin actions
- 1a98658d — feat(72-01): add password confirmation modal for delete and set_password in admin.js

## Self-Check: PASSED
