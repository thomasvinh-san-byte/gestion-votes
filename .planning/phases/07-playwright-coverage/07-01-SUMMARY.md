---
phase: 07-playwright-coverage
plan: "01"
subsystem: e2e-tests
tags: [playwright, networkidle, htmx, sse, e2e]
dependency_graph:
  requires: []
  provides: [zero-networkidle-specs, domcontentloaded-baseline]
  affects: [07-02, 07-03, 07-04]
tech_stack:
  added: []
  patterns: [domcontentloaded-wait, element-based-wait, sse-strategy-c, waitForHtmxSettled]
key_files:
  created: []
  modified:
    - tests/e2e/specs/navigation.spec.js
    - tests/e2e/specs/ux-interactions.spec.js
    - tests/e2e/specs/workflow-meeting.spec.js
    - tests/e2e/specs/audit-regression.spec.js
    - tests/e2e/specs/public-display.spec.js
    - tests/e2e/specs/vote.spec.js
key_decisions:
  - "Strategy C mandatory for public.htmx.html: SSE keeps network busy indefinitely, domcontentloaded + waitForSelector is the only viable approach"
  - "libatk-1.0.so.0 missing on host — chromium browser tests fail at OS level, not code level; this is a pre-existing env blocker unrelated to networkidle changes"
  - "73 API/request-fixture tests pass; browser-based tests require host libatk-1.0 install"
metrics:
  duration_seconds: 318
  completed_date: "2026-04-08"
  tasks_completed: 2
  tasks_total: 2
  files_modified: 6
  commits: 2
requirements:
  - TEST-01
---

# Phase 07 Plan 01: Networkidle Removal — Baseline Spec Cleanup Summary

**One-liner:** Replaced all 22 `waitForLoadState('networkidle')` calls across 6 Playwright spec files with domcontentloaded + element-based waits, eliminating the root cause of SSE page hangs.

## What Was Done

### Task 1 — Replace networkidle in 5 non-HTMX specs (commit b92ff696)

**navigation.spec.js** (3 occurrences): Replaced with `waitForLoadState('domcontentloaded')` + `expect(.app-shell, main, body).toBeVisible()`. Timing tests remain valid since domcontentloaded fires before SSE connections open.

**ux-interactions.spec.js** (9 occurrences): Mixed strategies applied:
- `beforeEach` blocks: `domcontentloaded` + `expect(#meetingSelect).toBeVisible({ timeout: 10000 })` ensures page is interactive before tests run
- Sidebar navigation test: `domcontentloaded` replaces networkidle (element assertions already present)
- Performance loop: `domcontentloaded` + element visibility check
- Error handling tests: `domcontentloaded` + element visibility on operator page

**workflow-meeting.spec.js** (7 occurrences): Strategy B throughout — dropped networkidle, relied on existing `expect(#meetingSelect).toBeVisible({ timeout: 10000 })` assertions which are already the definitive "page ready" signal for the operator console.

**audit-regression.spec.js** (1 occurrence): `domcontentloaded` + `expect(.app-shell, main, body).toBeVisible({ timeout: 10000 })` for docs.htmx.html with async JS boot.

**public-display.spec.js** (2 occurrences): Strategy C applied (mandatory): `page.goto(URL, { waitUntil: 'domcontentloaded' })` + `page.waitForSelector('main, #content, body', { timeout: 10000 })`. The public display page subscribes to SSE on load — networkidle can never resolve here.

### Task 2 — Fix vote.spec.js (commit 4994358e)

Removed `await page.waitForLoadState('networkidle')` from vote.htmx.html load. The `waitForHtmxSettled` import and call were already present (added in Phase 05). Changed goto to use `{ waitUntil: 'domcontentloaded' }`. vote.spec.js now uses the canonical HTMX wait pattern.

## Verification

```
grep -rn "await.*waitForLoadState('networkidle')" tests/e2e/specs/
# Exit code 1 — zero matches
```

All 22 networkidle calls removed. Zero remain in the 6 flagged files.

## Deviations from Plan

### Environment Blocker — libatk-1.0.so.0 Missing

**Found during:** Task 2 (Playwright suite run)
**Issue:** Chromium headless shell fails to launch on the host with `libatk-1.0.so.0: cannot open shared object file: No such file or directory`. This is a missing system library (GNOME accessibility toolkit), not a test code issue.
**Impact:** All browser-based tests fail at the OS level. The 73 tests using the `request` API fixture (no browser launch) pass successfully.
**Confirmed pre-existing:** Verified via git stash — same error occurs on the original code before any of our changes.
**Fix:** `sudo apt-get install -y libatk1.0-0 libatk-bridge2.0-0` would resolve this. Out of scope for this plan — documented for the environment team.
**Rule applied:** Not Rule 1/2/3 (not caused by our changes). Documented as environment blocker.

### Correction Applied — vote.spec.js stash revert

During the pre-existing environment check, `git stash pop` failed due to merge conflict on `.auth/*.json` files. vote.spec.js was reverted to its original state. The fix was re-applied manually and re-committed. No functional impact.

## Auth Gates

None encountered.

## Self-Check

- [x] navigation.spec.js modified: EXISTS
- [x] ux-interactions.spec.js modified: EXISTS
- [x] workflow-meeting.spec.js modified: EXISTS
- [x] audit-regression.spec.js modified: EXISTS
- [x] public-display.spec.js modified: EXISTS
- [x] vote.spec.js modified: EXISTS
- [x] Commit b92ff696: EXISTS
- [x] Commit 4994358e: EXISTS
- [x] Zero networkidle await calls in specs/: VERIFIED (grep exit code 1)

## Self-Check: PASSED
