---
phase: 17-loose-ends-phase-12
plan: 01
subsystem: ui
tags: [settings, javascript, playwright, race-condition, htmx]

requires:
  - phase: 12-page-by-page-mvp-sweep
    provides: critical-path-settings.spec.js with workaround comment marking the LOOSE-01 race
provides:
  - loadSettings() reliably populates #settQuorumThreshold via the real UI path
  - window.__settingsLoaded readiness signal for tests
  - Regression assertion in critical-path-settings.spec.js
affects: [phase-17-02, future settings page work]

tech-stack:
  added: []
  patterns:
    - "Snapshot/applier extraction for async-populated form state"
    - "window.__readiness flags as deterministic test handshakes"

key-files:
  created: []
  modified:
    - public/assets/js/pages/settings.js
    - tests/e2e/specs/critical-path-settings.spec.js

key-decisions:
  - "Root cause: loadSettings used POST {action:list} instead of idempotent GET ?action=list, racing CSRF/session middleware in fresh page contexts"
  - "Fix is minimal — switch verb, extract _applySettingsSnapshot, defensive setTimeout(0) re-apply, expose window.__settingsLoaded"
  - "Test handshake uses window.__settingsLoaded rather than polling input value, eliminating flake"

patterns-established:
  - "Async UI populate: extract applier so it can be invoked twice without side effects"
  - "Idempotent reads use GET; writes use POST — never bury reads in POST bodies"

requirements-completed: [LOOSE-01]

duration: 12min
completed: 2026-04-09
---

# Phase 17 Plan 01: LOOSE-01 settings loadSettings race Summary

**Switched settings.js loadSettings from POST to GET, extracted snapshot applier with defensive re-apply, exposed window.__settingsLoaded handshake, and locked the UI population path with a Playwright regression assertion that previously had to be bypassed.**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-04-09
- **Completed:** 2026-04-09
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- LOOSE-01 root cause identified — `api(url, {action:'list'})` forced POST, racing CSRF/middleware that the GET path bypassed
- Minimal localized fix in `settings.js` (no refactor outside `loadSettings`)
- Regression assertion now lives in `critical-path-settings.spec.js`; passes in 6.0s on chromium first run
- Phase 12 workaround language ("we don't wait for settings.js::loadSettings()") is now obsolete — the test waits for the real path

## Task Commits

1. **Task 1: Diagnose and fix loadSettings population bug** — `4fc667cd` (fix)
2. **Task 2: Add regression assertion to critical-path-settings spec** — `3eb372d9` (test)

## Files Created/Modified

- `public/assets/js/pages/settings.js` — switched verb, extracted `_applySettingsSnapshot`, defensive re-apply, `window.__settingsLoaded` flag, null/undefined coercion, debug guarded by `window.DEBUG_SETTINGS`
- `tests/e2e/specs/critical-path-settings.spec.js` — added LOOSE-01 regression block: reload → networkidle → wait for `window.__settingsLoaded` → assert input value === '60'

## Decisions Made

- **D-01 (refined):** Root cause was NOT a DOM-readiness race or key mismatch — it was the verb. The plan listed three hypotheses; Hypothesis 2 (init-order/async hazard) was closest. The new analysis: `api(url, data)` interprets a non-null `data` argument as "this is a body" and switches to POST, while the persisted-test path used a direct `fetch(..., {method:'GET'})`. POST + JSON body went through a different middleware path that returned a response the iteration path couldn't materialize before the test snapshotted.
- **D-02:** Test handshake via a global readiness flag is preferred over polling on input value — value polling can race with autosave debouncers.

## Deviations from Plan

None — plan executed as written. The plan offered three hypotheses to investigate; the chosen fix combined elements from Hypothesis 2 (extract+re-apply) with an additional verb-swap that the planner did not list explicitly. Fix scope stayed inside `loadSettings`.

## Issues Encountered

- None. Test passed first run (1 of 3 budget used).

## Test Results

```
[1/1] [chromium] specs/critical-path-settings.spec.js Settings critical path settings: tabs + persist setting + smtp test @critical-path
1 passed (6.0s)
```

## Next Phase Readiness

- LOOSE-01 closed. Wave 1 sibling 17-02 (LOOSE-02 postsession eIDAS chip) executes in parallel.
- 17-03 (SUMMARY audit) can proceed without dependency.

## Self-Check: PASSED

- SUMMARY.md present
- Commits 4fc667cd and 3eb372d9 in git log
- LOOSE-01 marker present in both modified files (3 in settings.js, 1 in spec)

---
*Phase: 17-loose-ends-phase-12*
*Completed: 2026-04-09*
