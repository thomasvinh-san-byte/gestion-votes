---
phase: 74-ci-hardening
plan: 01
subsystem: infra
tags: [ci, github-actions, postgresql, playwright, e2e, migrations]

# Dependency graph
requires:
  - phase: 57-ci-cd-pipeline
    provides: CI pipeline with 7 jobs (validate, lint, migrate-check, coverage, build, e2e, integration)
provides:
  - CI e2e job loads 04_e2e.sql after app health check so E2E meeting UUID exists when specs run
  - migrate-check job runs full two-pass PostgreSQL idempotency validation instead of grep-only scan
  - Non-idempotent migrations (bare CREATE TABLE) fail CI with exit code 2
affects: [ci, migrations, e2e-tests]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Docker exec stdin pipe pattern for loading SQL files into containerized PostgreSQL"
    - "Dedicated CI migration DB (agvote_migration_ci) avoids credential collision with integration job"

key-files:
  created: []
  modified:
    - .github/workflows/docker-build.yml

key-decisions:
  - "E2E seed loaded via docker exec -i stdin pipe (not docker cp) — avoids temp file concerns"
  - "migrate-check uses dedicated agvote_migration_ci DB/user to avoid name collision with integration job"
  - "Full two-pass validation replaces --syntax-only — catches both SQLite patterns and idempotency bugs"

patterns-established:
  - "CI seed loading: pipe file via stdin into docker exec psql after app healthy, before test runner setup"

requirements-completed: [DEBT-03, DEBT-04]

# Metrics
duration: 10min
completed: 2026-04-01
---

# Phase 74 Plan 01: CI Hardening Summary

**CI pipeline upgraded with E2E seed loading and full PostgreSQL idempotency gate, closing two local-only drift risks**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-04-01T10:15:00Z
- **Completed:** 2026-04-01T10:25:00Z
- **Tasks:** 2
- **Files modified:** 1

## Accomplishments

- E2E job now loads 04_e2e.sql after app health check — E2E meeting UUID eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001 exists in database when Playwright specs run
- migrate-check job upgraded from grep-only syntax scan to full two-pass PostgreSQL validation with dedicated postgres service
- Non-idempotent migrations (bare CREATE TABLE without IF NOT EXISTS) now fail CI with exit code 2

## Task Commits

1. **Task 1: Load 04_e2e.sql in CI e2e job after app health check** - `8b8dd9b5` (feat)
2. **Task 2: Upgrade migrate-check job to full PostgreSQL idempotency validation** - `17bc9624` (feat)

## Files Created/Modified

- `.github/workflows/docker-build.yml` — Added "Load E2E seed data" step in e2e job; replaced migrate-check with postgres-backed two-pass validation

## Decisions Made

- Used `docker exec -i agvote-db psql ... < file` (stdin pipe) rather than `docker cp` to load the seed file — simpler, no temp files
- Dedicated credentials (agvote_migration_ci / agvote_ci) for migrate-check postgres service to avoid any collision with integration job's agvote_test DB
- `--syntax-only` flag removed entirely — full run catches all SQLite patterns on first pass AND idempotency bugs on second pass

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- CI pipeline now enforces both E2E data presence and migration idempotency on every push
- Both tech debt items DEBT-03 and DEBT-04 from STATE.md are resolved

---
*Phase: 74-ci-hardening*
*Completed: 2026-04-01*
