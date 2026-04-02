---
phase: 71-mon-compte
plan: 01
subsystem: auth
tags: [account, password, self-service, html-controller]
dependency_graph:
  requires: [UserRepository, SessionHelper, HtmlView, RepositoryFactory]
  provides: [/account route, AccountController, account_form template]
  affects: [auth-ui.js banner, nginx routing, voter confinement]
tech_stack:
  added: []
  patterns: [HTML controller without AbstractController, redirect exception pattern, TDD red-green]
key_files:
  created:
    - app/Controller/AccountController.php
    - app/Controller/AccountRedirectException.php
    - app/Templates/account_form.php
    - tests/Unit/AccountControllerTest.php
  modified:
    - app/routes.php
    - deploy/nginx.conf
    - deploy/nginx.conf.template
    - public/assets/js/pages/auth-ui.js
    - tests/bootstrap.php
decisions:
  - "AccountController directly checks $_SESSION['auth_user'] (no AuthMiddleware) — same pattern as PasswordResetController and SetupController"
  - "Template reuses login-card / login-orb layout from reset_request_form.php — no new CSS file needed"
  - "Voter confinement allowlist includes /account so voter-role users can access their profile"
  - "Worktree bootstrap.php updated to load vendor from parent project (3 levels up) using require (not require_once) to get ClassLoader back"
metrics:
  duration: "9 minutes"
  completed: "2026-04-02"
  tasks: 2
  files: 9
---

# Phase 71 Plan 01: Mon Compte — AccountController Summary

**One-liner:** Self-service /account page for profile view and password change using login-card layout with redirect-exception pattern for testability.

## What Was Built

A fully functional `/account` page allowing any authenticated user to view their profile (name, email, role in French) and change their password via a 3-field form. Uses the same HTML controller pattern as `/reset-password` and `/setup`.

### Files Created

- `app/Controller/AccountController.php` — GET profile display + POST password change with validation, password_verify, setPasswordHash, audit_log
- `app/Controller/AccountRedirectException.php` — testable redirect exception (PHPUNIT_RUNNING pattern)
- `app/Templates/account_form.php` — login-card layout, read-only profile section, password change form with French labels, error/success display
- `tests/Unit/AccountControllerTest.php` — 6 unit tests covering all 6 specified behaviors (18 assertions)

### Files Modified

- `app/routes.php` — added `mapAny('/account', AccountController::class, 'account')` after /setup
- `deploy/nginx.conf` — added `location = /account` block in PHP HTML controllers section (also added /reset-password and /setup which were missing)
- `deploy/nginx.conf.template` — same as nginx.conf
- `public/assets/js/pages/auth-ui.js` — Mon Compte link in banner, setStatus() toggle, /account in voter allowedExact array
- `tests/bootstrap.php` — worktree vendor fallback: `require` (not `require_once`) + `addPsr4` override to load worktree app/ classes

## Commits

| Task | Commit | Description |
|------|--------|-------------|
| RED  | 753904fb | test(71-01): add failing tests for AccountController |
| GREEN | 02ecd034 | feat(71-01): AccountController, template, route, nginx |
| Task 2 | 71778c06 | feat(71-01): add Mon Compte link to auth banner and voter confinement |

## Verification Results

- `php -l app/Controller/AccountController.php` — No syntax errors
- PHPUnit: 6/6 tests pass, 18 assertions
- Route registered in routes.php
- Nginx location = /account present in both conf files
- Mon Compte link present in auth-ui.js

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Nginx missing /reset-password and /setup location blocks**
- **Found during:** Task 1 (nginx.conf review)
- **Issue:** The plan only specified adding `/account` but `/reset-password` and `/setup` were also missing explicit location blocks. Added all three together for consistency.
- **Fix:** Added all three PHP HTML controller location blocks in both nginx.conf and nginx.conf.template
- **Commit:** 02ecd034

**2. [Rule 3 - Blocking] Worktree bootstrap.php couldn't find vendor directory**
- **Found during:** Task 1 (test execution)
- **Issue:** Git worktrees don't have their own vendor/ directory. The bootstrap.php used `require_once` which returns `true` (not ClassLoader) when vendor was already loaded by phpunit, so `addPsr4` override couldn't be applied.
- **Fix:** Changed to `require` (not `require_once`) + path fallback to parent project vendor (3 levels up), with `addPsr4('AgVote\\', [PROJECT_ROOT . '/app/'], true)` to prepend worktree paths.
- **Files modified:** tests/bootstrap.php
- **Commit:** 02ecd034

## Self-Check: PASSED

All created files exist. All commits present.

| Item | Status |
|------|--------|
| app/Controller/AccountController.php | FOUND |
| app/Controller/AccountRedirectException.php | FOUND |
| app/Templates/account_form.php | FOUND |
| tests/Unit/AccountControllerTest.php | FOUND |
| commit 753904fb | FOUND |
| commit 02ecd034 | FOUND |
| commit 71778c06 | FOUND |
