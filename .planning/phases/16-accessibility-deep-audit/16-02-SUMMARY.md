---
phase: 16-accessibility-deep-audit
plan: 02
subsystem: testing
tags: [accessibility, axe-core, playwright, a11y, wcag, blocker]

# Dependency graph
requires:
  - phase: 16-01
    provides: parametrized accessibility.spec.js with 22-page PAGES matrix and extraDisabledRules plumbing
provides:
  - 16-02-BASELINE.md documenting environmental blocker with full unblock path
  - Unblock instructions for the test host (libatk1.0-0 + companion libs)
affects: [16-03-keyboard-nav, 16-04-accessibility-report]

# Tech tracking
tech-stack:
  added: []
  patterns: []

key-files:
  created:
    - .planning/phases/16-accessibility-deep-audit/16-02-BASELINE.md
  modified: []

key-decisions:
  - "Task 2 (batch-fix) NOT executed: per plan, baseline data is mandatory before fixes — no training-data priors allowed"
  - "Environmental blocker logged in STATE.md for next session to resolve (requires sudo)"
  - "Plan partially complete: A11Y-01 instrumentation from 16-01 stands, A11Y-02 still OPEN"

patterns-established: []

requirements-completed: []
requirements-blocked: [A11Y-02]

# Metrics
duration: 5min
completed: 2026-04-09
status: blocked
---

# Phase 16 Plan 02: Axe Baseline Run — BLOCKED

**Plan execution halted at Task 1: Playwright chromium cannot launch because the host is missing `libatk-1.0.so.0` (and ~10 companion runtime libraries). All 26 test invocations fail identically at browser launch, with zero axe assertions reached. Task 2 (batch-fix) cannot proceed because the plan explicitly forbids fixing assumed violations — fix order must come from empirical baseline data.**

## Performance

- **Duration:** ~5 min (diagnostic + documentation)
- **Started:** 2026-04-09T09:22:00Z (approx)
- **Completed:** 2026-04-09T09:27:00Z (blocker returned)
- **Tasks:** 1/2 (Task 1 partial — document produced, violation inventory impossible)
- **Files created:** 1 (16-02-BASELINE.md)
- **Files modified:** 0
- **Test runs used:** 1/3 (one full run, which failed in environment, not in assertions)

## Accomplishments

- Ran the parametrized axe suite once: `cd tests/e2e && timeout 300 npx playwright test specs/accessibility.spec.js --project=chromium --reporter=line`.
- Captured full output to `/tmp/16-02-axe-baseline.txt` (~38KB, 52 `libatk` error occurrences).
- Diagnosed root cause: `ldconfig -p | grep libatk` empty, `find / -name "libatk-1.0*"` empty, `dpkg -l libatk1.0-0` → not installed. Both chromium binaries (`chrome` and `chrome-headless-shell`) exit 127 on launch.
- Attempted auto-unblock: `sudo -n apt-get install …` → password required; `npx playwright install-deps chromium` → also requires root.
- Wrote `16-02-BASELINE.md` honestly reflecting the blocker, including per-page failure table, full unblock command set, and the proposed replay workflow once the environment is fixed.
- Committed: `f191a933` — `docs(16-02): axe baseline — blocked by missing libatk-1.0.so.0 system library`.

## Task Commits

1. **Task 1: Baseline axe run + violation inventory** — `f191a933` (docs) — honestly documents the environmental failure; violation inventory empty because zero axe code ran.
2. **Task 2: Batch-fix critical/serious violations** — NOT EXECUTED (blocked by missing baseline data — plan rule D-07 forbids assumed priors).

## Blocker Details

### What blocks execution

- **Host library missing:** `libatk-1.0.so.0` (and companions: `libatk-bridge-2.0.so.0`, `libcups.so.2`, `libxkbcommon.so.0`, `libXcomposite.so.1`, `libXdamage.so.1`, `libXfixes.so.3`, `libXrandr.so.2`, `libgbm.so.1`, `libasound.so.2`, `libnss3.so`).
- **Effect:** Playwright chromium (both full and headless-shell variants) exits 127 immediately on launch. No test assertion runs, no axe invocation, no HTTP to the dev server. The dev server itself is unaffected.
- **Scope:** Any Playwright test against chromium project is blocked. Firefox/webkit may or may not be affected (not tested — phase 15 report last verified them 3 days ago; the regression source is unknown).

### What is required to unblock

Run **as root on the test host**:

```bash
cd tests/e2e
npx playwright install-deps chromium
# OR manually:
sudo apt-get update
sudo apt-get install -y \
  libatk1.0-0 libatk-bridge2.0-0 libcups2 libxkbcommon0 \
  libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 \
  libasound2t64 libnss3
```

### Replay workflow after unblock

1. Confirm browser launches: `npx playwright test specs/accessibility.spec.js --project=chromium --list` (should succeed without errors).
2. Re-run the baseline capture command from `16-02-BASELINE.md`, overwriting `/tmp/16-02-axe-baseline.txt`.
3. Rewrite the "Violations grouped by rule-id" and "Proposed batch-fix order" sections of `16-02-BASELINE.md` with real data.
4. Amend commit `f191a933` (or add a follow-up) with the populated baseline.
5. Resume plan 16-02 Task 2 with the real rule-id frequency list.

### Why Task 2 was not attempted

The 16-02 plan's `<interfaces>` block lists *likely* rules (button-name, label, dialog-name, etc.) as "a PRIOR, not truth". RESEARCH.md and D-07 require the fix order to be *derived from the baseline data*, not invented. Plan 16-01's seed commits already pre-fixed the highest-probability issues (settings icon buttons, operator live regions). Guessing further fixes would (a) risk adding French aria-labels to elements that don't actually violate anything, (b) produce commits that cannot be validated, (c) violate CLAUDE.md's "max 3 test runs per task" since every guess would need its own verification loop. The disciplined action is to stop and document.

## Deviations from Plan

**Rule 4 escalation — environmental block.**

- **Trigger:** Chromium binary cannot launch on the host due to a missing system library. Not a code defect, not something a user-space patch can fix.
- **Action:** STOP, document, do not guess fixes.
- **Justification:** No authority to `sudo`, and the plan explicitly forbids assumed violations (would require regression testing that also cannot run).

## Issues Encountered

1. **libatk-1.0.so.0 missing** — blocks all Playwright chromium runs. Documented above.
2. **Regression from phase 15** — phase 15 (2026-04-06) successfully ran cross-browser matrix including chromium; something removed the GTK/ATK runtime between then and now. Root cause unknown, out of scope for this plan.

## Known Open Questions (carried into next session)

- Did firefox/webkit also lose their system deps? Not tested to preserve the test-run budget. The first post-unblock run should spot-check at least chromium.
- Are any of the assumed "likely" violations from RESEARCH lines 307-327 still present after the 16-01 seed fixes? Empirically unknown.
- Will `trust.htmx.html` (Pitfall 5) pass with the admin fallback? Still untested.

## User Setup Required

**YES — root access needed.** See "Blocker Details / What is required to unblock" above. Once the libraries are installed the plan can resume automatically with a single test run to produce the real baseline.

## Next Phase Readiness

- **16-03 (keyboard-nav) and 16-04 (report)** are wave-2 siblings — they may or may not be affected by the same libatk blocker depending on whether they run Playwright. If they do, they will hit the same wall. Their summaries should be checked for the same symptom.
- **A11Y-01** stands from 16-01 (infrastructure ready).
- **A11Y-02** remains OPEN — cannot be marked complete until the environment is repaired and the violation inventory is produced.

## Self-Check: PASSED (scope-limited)

- FOUND: .planning/phases/16-accessibility-deep-audit/16-02-BASELINE.md
- FOUND: commit f191a933 (docs(16-02): axe baseline — blocked by missing libatk-1.0.so.0 system library)
- VERIFIED: /tmp/16-02-axe-baseline.txt exists and contains 52 `libatk-1.0.so.0` error occurrences across 26 test cases (no axe assertions)
- VERIFIED: no source files modified (no guessed fixes committed)
- NOTE: Task 2 intentionally not executed — not a self-check failure, a disciplined halt per plan rules

---
*Phase: 16-accessibility-deep-audit*
*Status: BLOCKED on environmental infrastructure*
*Completed: 2026-04-09*
