---
phase: 14-visual-polish
plan: "01"
subsystem: frontend-notifications
tags: [toast, ux, polish, members, e2e]
dependency_graph:
  requires: []
  provides: [POLISH-01]
  affects: [public/assets/js/pages/members.js, tests/e2e/specs/critical-path-members.spec.js]
tech_stack:
  added: []
  patterns: [AgToast.show() global API, Playwright locator on custom element]
key_files:
  created: []
  modified:
    - public/assets/js/pages/members.js
    - tests/e2e/specs/critical-path-members.spec.js
decisions:
  - Replace all 27 setNotif() call sites with AgToast.show() — same argument order (type, message), zero callers remain
  - E2E assertion uses empty-group-name client-side validation path (re-runnable, no DB write)
metrics:
  duration_minutes: 8
  completed_date: "2026-04-07"
  tasks_completed: 2
  files_modified: 2
requirements:
  - POLISH-01
---

# Phase 14 Plan 01: Toast Notification System Unification Summary

**One-liner:** members.js converted from legacy setNotif() banner pattern to AgToast.show() across 27 call sites, pushing POLISH-01 adoption to 9 pages; Playwright spec asserts real `<ag-toast>` DOM element appears after client-side validation error.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Convert members.js setNotif() to AgToast.show() | 74e99015 | public/assets/js/pages/members.js |
| 2 | Extend critical-path-members spec with toast assertion | be2236d2 | tests/e2e/specs/critical-path-members.spec.js |

## Key Changes

### Task 1: members.js conversion

- Replaced 27 `setNotif()` call sites with `AgToast.show()` — argument order identical so arguments passed unchanged
- Zero remaining `setNotif()` calls in members.js (function definition lives in utils.js, untouched)
- POLISH-01 threshold: 9 pages now use AgToast (settings.js, hub.js, vote.js, wizard.js, users.js + members.js + 3 others confirmed by grep)
- JS syntax verified: `node -c` passes

### Task 2: critical-path-members spec extension

- New test case: `'members: AgToast appears after validation error on empty group name @critical-path'`
- Trigger: click `#btnCreateGroup` with empty `#groupName` — fires `AgToast.show('error', ...)` client-side without API call
- Assertion: `page.locator('ag-toast')` visible within 3s, `type` attribute equals `'error'`
- Re-runnable: no DB mutation (validation fires before any `api()` call in members.js line 293)
- 3 `ag-toast` references in spec, syntax verified: `node -c` passes

## Verification Results

| Check | Expected | Actual | Status |
|-------|----------|--------|--------|
| `grep -c "setNotif(" members.js` | 0 | 0 | PASS |
| `grep -c "AgToast.show(" members.js` | ≥15 | 27 | PASS |
| Pages using AgToast | ≥6 | 9 | PASS |
| `grep -c "ag-toast" spec.js` | ≥1 | 3 | PASS |
| `node -c members.js` | OK | OK | PASS |
| `node -c spec.js` | OK | OK | PASS |

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

Files exist:
- /home/user/gestion_votes_php/public/assets/js/pages/members.js — FOUND
- /home/user/gestion_votes_php/tests/e2e/specs/critical-path-members.spec.js — FOUND
- /home/user/gestion_votes_php/.planning/phases/14-visual-polish/14-01-SUMMARY.md — FOUND

Commits exist:
- 74e99015 feat(14-01): convert members.js setNotif() calls to AgToast.show() — FOUND
- be2236d2 test(14-01): add ag-toast appearance assertion to critical-path-members spec — FOUND
