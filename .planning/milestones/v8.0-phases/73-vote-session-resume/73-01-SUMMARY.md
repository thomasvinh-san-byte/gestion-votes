---
phase: 73-vote-session-resume
plan: "01"
subsystem: frontend-auth
tags: [session-resume, voter-ux, auth-redirect, security]
dependency_graph:
  requires: []
  provides: [vote-session-resume-redirect]
  affects: [auth-ui.js, login.js]
tech_stack:
  added: []
  patterns: [return_to param convention, same-origin redirect validation]
key_files:
  modified:
    - public/assets/js/pages/auth-ui.js
    - public/assets/js/pages/login.js
  created: []
decisions:
  - "return_to param used for vote page expiry (vs generic redirect=) to allow role-based logic to still apply for other pages"
  - "return_to checked before redirect in redirectByRole() — expiry flow takes priority over legacy redirect"
  - "_isVote gate scopes return_to behavior to /vote and /vote.htmx.html only"
metrics:
  duration_seconds: 29
  completed_date: "2026-04-01"
  tasks_completed: 2
  tasks_total: 2
  files_modified: 2
  files_created: 0
---

# Phase 73 Plan 01: Vote Session Resume Summary

**One-liner:** Vote-page session expiry now redirects to /login.html?return_to=%2Fvote, and login.js sends voters back to /vote after re-auth via same-origin-validated return_to param.

## Tasks Completed

| # | Name | Commit | Files |
|---|------|--------|-------|
| 1 | auth-ui.js — emit return_to=/vote on session expiry for vote page | ba324399 | public/assets/js/pages/auth-ui.js |
| 2 | login.js — honor return_to param in redirectByRole() after successful login | 30856c3a | public/assets/js/pages/login.js |

## What Was Built

### Task 1 — auth-ui.js vote-page-aware session expiry redirect

In the `boot()` function's `session_expired` handler, replaced the generic `redirect=pathname` redirect with a conditional check:

- When the current pathname is `/vote` or `/vote.htmx.html`: emits `/login.html?expired=1&return_to=%2Fvote`
- All other pages: continue using `/login.html?expired=1&redirect=...` (backward compatible)

The `_isVote` boolean gates this behavior so it only activates on the vote page.

### Task 2 — login.js return_to param support in redirectByRole()

Added `return_to` as the highest-priority redirect param in `redirectByRole()`:

1. Read `return_to` param → validate with `isSafeRedirect()` → redirect if valid
2. Fall through to existing `redirect` param (unchanged, backward compat)
3. Fall through to existing role-based routing (unchanged)

`isSafeRedirect()` already enforces same-origin validation (must start with `/`, not `//`, no javascript:/data: URIs) — no changes needed to that function.

## End-to-End Flow After This Plan

1. Voter on /vote → session expires → whoami returns `session_expired`
2. auth-ui.js detects `/vote` → navigates to `/login.html?expired=1&return_to=%2Fvote`
3. login.js shows "Votre session a expirée" (existing expired=1 handler)
4. Voter re-authenticates → redirectByRole() reads `return_to=/vote` → isSafeRedirect passes → navigates to `/vote`
5. vote.js re-initializes with sessionStorage context intact (meeting_id, member_id preserved across same-tab navigation)
6. If vote closed during timeout: vote.js existing "vote ended" UI renders normally

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check

- [x] public/assets/js/pages/auth-ui.js modified — contains `_isVote` and `return_to=` logic
- [x] public/assets/js/pages/login.js modified — contains `return_to` param check before `redirect`
- [x] Commit ba324399 exists (Task 1)
- [x] Commit 30856c3a exists (Task 2)
- [x] Both JS files pass `node --check` syntax validation
- [x] Backward compatibility preserved: `redirect=` param still present in auth-ui.js for non-vote pages

## Self-Check: PASSED
