---
phase: 17-loose-ends-phase-12
plan: 02
subsystem: postsession
tags: [bugfix, e2e, playwright, postsession, event-delegation, loose-end]
dependency_graph:
  requires: []
  provides: [postsession-natural-chip-clicks]
  affects: [public/assets/js/pages/postsession.js, tests/e2e/specs/critical-path-postsession.spec.js]
tech_stack:
  added: []
  patterns: [document-level-event-delegation, idempotent-bind-guard]
key_files:
  created: []
  modified:
    - public/assets/js/pages/postsession.js
    - tests/e2e/specs/critical-path-postsession.spec.js
decisions:
  - Move eIDAS chip click delegation from #eidasChips (bound inside bindNavigation while panel-3 hidden) to document-level so Playwright actionability checks succeed
  - Bind delegation before the meetingId early-return in init() so it is wired even when the picker is shown
  - Use module-scoped _eidasChipDelegated guard to prevent double-binding if init runs twice
metrics:
  duration: ~10m
  completed: 2026-04-09
  tasks_completed: 2
  files_created: 0
  files_modified: 2
  test_runs: 1
requirements: [LOOSE-02]
---

# Phase 17 Plan 02: Postsession eIDAS chip click delegation Summary

Replaced the fragile `page.evaluate()` chip-click workaround in the postsession critical-path spec by fixing the underlying delegation in `postsession.js` to be panel-visibility independent.

## Root Cause

In `public/assets/js/pages/postsession.js` the eIDAS chip click handler was bound on `#eidasChips` inside `bindNavigation()`, which only ran after `meetingId` was resolved AND while panel-3 was still hidden. Playwright's actionability-checked `.click()` then dispatched a click whose bubble path was technically valid, but the combination of "listener attached on a hidden ancestor" + "panel later programmatically shown via `hidden = false`" produced inconsistent dispatch behaviour. The Phase 12-15 spec papered over this with `page.evaluate(() => chip.click())`, which bypasses Playwright's interception entirely.

## Fix

### `public/assets/js/pages/postsession.js`

- Removed the `chipGroup.addEventListener('click', ...)` block from `bindNavigation()`.
- Added a new top-level helper `bindEidasChipDelegation()` that attaches a single `document.addEventListener('click', ...)` listener using `e.target.closest('#eidasChips .chip[data-eidas]')` for the selector match.
- Added a module-scoped `_eidasChipDelegated` boolean guard so the delegation can never be double-bound (idempotent).
- Called `bindEidasChipDelegation()` at the very top of `init()`, **before** the `meetingId` early-return, so the listener is wired even when the meeting picker is displayed.
- All edits annotated with `LOOSE-02 fix` comments.

### `tests/e2e/specs/critical-path-postsession.spec.js`

- Replaced the three `page.evaluate(() => chip.click())` blocks (qualified / manuscript / advanced) with natural `await page.locator('.chip[data-eidas="..."]').click()` calls.
- Other `page.evaluate()` usages in the file (sessionStorage setup, programmatic panel switches, scrollWidth measurement) are intentionally left untouched — they exist for unrelated reasons.
- Added a single `LOOSE-02:` comment documenting why natural clicks now work.

## Test Result

```
$ timeout 180 bin/test-e2e.sh specs/critical-path-postsession.spec.js
Running 1 test using 1 worker
  1 passed (5.0s)
```

Passed on first run (1/3 of the CLAUDE.md test budget used).

## Tasks Completed

| Task | Description | Commit | Status |
|------|-------------|--------|--------|
| 1 | Make eIDAS chip click delegation robust (document-level + idempotent) | d120ba2e | Done |
| 2 | Remove page.evaluate workaround from postsession spec | 36b6414c | Done |

## Acceptance Criteria

- [x] `grep -c "LOOSE-02" public/assets/js/pages/postsession.js` = 3 (≥1)
- [x] `grep -c "_eidasChipDelegated" public/assets/js/pages/postsession.js` = 3 (≥1)
- [x] `grep "document.addEventListener" public/assets/js/pages/postsession.js` shows new delegation
- [x] Old `chipGroup.addEventListener('click'` block removed
- [x] `grep -c "page.evaluate.*chip" tests/e2e/specs/critical-path-postsession.spec.js` = 0
- [x] `grep -c "LOOSE-02" tests/e2e/specs/critical-path-postsession.spec.js` = 1 (≥1)
- [x] 3 natural `page.locator('.chip[data-eidas...').click()` calls in spec
- [x] `bin/test-e2e.sh specs/critical-path-postsession.spec.js` exits 0
- [x] Other unrelated `page.evaluate` calls in the spec preserved

## Deviations from Plan

None — plan executed exactly as written. Test passed on the first run; no need for retries.

## Self-Check: PASSED

- [x] `public/assets/js/pages/postsession.js` modified — verified via git log
- [x] `tests/e2e/specs/critical-path-postsession.spec.js` modified — verified via git log
- [x] Commit d120ba2e exists in git log
- [x] Commit 36b6414c exists in git log
- [x] Spec exits 0 in Docker
