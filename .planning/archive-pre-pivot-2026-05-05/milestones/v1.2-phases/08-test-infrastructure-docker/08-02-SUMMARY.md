---
phase: 08-test-infrastructure-docker
plan: 02
subsystem: testing
tags: [playwright, docker, bash, e2e, chromium]

# Dependency graph
requires:
  - phase: 08-01
    provides: tests service in docker-compose.yml (profile: test)
provides:
  - bin/test-e2e.sh executable wrapper for containerized Playwright execution
  - One-liner CLI entry point for E2E tests (INFRA-02)
affects: [phase-09, ci]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "exec docker compose run --rm for clean exit code propagation"
    - "$* (not $@) inside bash -lc for argument forwarding to inner command"

key-files:
  created:
    - bin/test-e2e.sh
  modified: []

key-decisions:
  - "Use exec so playwright exit code propagates cleanly to caller — no wrapper exit code masking"
  - "$* (not $@) inside bash -lc — arguments joined as single string matching how the inner shell parses them"
  - "Enforce --project=chromium unconditionally — chromium-only scope per locked Phase 8 decision"
  - "Auto-start app/db/redis if not running — avoids cryptic errors when stack is down"

patterns-established:
  - "bin/test-e2e.sh: BASH_SOURCE[0] pattern for portable script-dir resolution"
  - "UID/GID export before docker compose run — container runs as host user to prevent root-owned files"

requirements-completed: [INFRA-02]

# Metrics
duration: 5min
completed: 2026-04-07
---

# Phase 8 Plan 02: Test E2E Wrapper Script Summary

**Executable bin/test-e2e.sh wrapping docker compose --profile test run --rm tests with chromium-enforced Playwright execution and clean exit code propagation via exec**

## Performance

- **Duration:** ~5 min
- **Started:** 2026-04-07T00:00:00Z
- **Completed:** 2026-04-07T00:05:00Z
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments

- Created `bin/test-e2e.sh` — CLI wrapper for containerized Playwright E2E tests
- Enforces chromium-only scope via `--project=chromium` (locked decision from Phase 8 context)
- Forwards arbitrary user arguments (`$*` inside bash -lc) for grep, spec file, --list, etc.
- Propagates playwright exit code cleanly via `exec docker compose ...`
- Auto-starts app/db/redis stack if the `app` service is not running

## Task Commits

Each task was committed atomically:

1. **Task 1: Create bin/test-e2e.sh wrapper script** - `07ab4c96` (feat)

**Plan metadata:** (docs commit to follow)

## Files Created/Modified

- `bin/test-e2e.sh` - Bash wrapper that invokes docker compose --profile test run --rm tests with chromium Playwright execution

## Decisions Made

- `exec` instead of a subprocess call so the script's exit code equals playwright's exit code exactly — no wrapping exit code masking
- `$*` (not `"$@"`) inside `bash -lc "..."` because the argument is a single string passed to a new bash shell; `$@` would produce separate argv tokens that don't compose correctly in that context
- `--project=chromium` hardcoded in the wrapper — multi-browser deferred to post-bouclage milestone per locked decisions
- Auto-start pattern checks `docker compose ps --filter status=running` before run — prevents "app not healthy" dependency failures for users who forgot `docker compose up`

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- INFRA-02 complete: `./bin/test-e2e.sh` is the entry point for all E2E test runs
- Phase 09 can now run the full Playwright suite via this wrapper
- `./bin/test-e2e.sh --list` available for smoke-testing the setup without executing tests

---
*Phase: 08-test-infrastructure-docker*
*Completed: 2026-04-07*
