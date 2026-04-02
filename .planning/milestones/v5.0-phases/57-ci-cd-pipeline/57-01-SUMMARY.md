---
phase: 57-ci-cd-pipeline
plan: 01
subsystem: infra
tags: [github-actions, playwright, phpunit, docker, ci-cd, coverage, postgres, redis]

# Dependency graph
requires:
  - phase: 55-coverage-target-tooling
    provides: coverage-check.sh with pcov, validate-migrations.sh with --syntax-only mode
  - phase: 56-e2e-test-updates
    provides: 177 passing Playwright E2E tests with rate-limit-safe auth setup
provides:
  - All 4 quality gates run automatically on every push to main and every PR
  - migrate-check job: SQLite pattern detection in CI (no PostgreSQL, ~5s)
  - coverage job: PHPUnit Unit suite with pcov, Services >= 90% and Controllers >= 60% gate
  - e2e job: Full Playwright chromium suite against Docker stack built from GHA cache
  - integration job: PHPUnit Integration suite against real Postgres + Redis via GHA services
affects: [future-phases, deployment]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "4-wave CI pipeline: parallel pre-build gates (wave 1) -> Docker build (wave 2) -> parallel post-build tests (wave 3)"
    - "GHA cache for Docker layers: build job writes cache-to=gha,mode=max; e2e job reads cache-from=gha to restore pre-built image"
    - "COMPOSE_PROJECT_NAME=agvote pattern: compose project name controls image tag lookup (agvote-app) and container names (agvote-redis for auth.setup.js)"

key-files:
  created: []
  modified:
    - .github/workflows/docker-build.yml

key-decisions:
  - "E2E job uses GHA cache (Buildx cache-from=type=gha) to restore pre-built image as agvote:ci, then tags it agvote-app for docker compose --no-build — avoids full Docker rebuild in post-build jobs"
  - "COMPOSE_PROJECT_NAME=agvote ensures container name agvote-redis matches auth.setup.js clearRateLimit() docker exec command"
  - "Integration job uses GHA services: (not docker compose) — cleaner lifecycle management, postgres+redis are automatically healthy before steps run"
  - "E2E runs chromium-only in CI (--project=chromium) — reduces runtime while covering core flows; full browser matrix is local-dev only"
  - "build.needs expanded to [validate, lint-js, migrate-check, coverage] — Docker image only built after all 4 quality gates pass"

patterns-established:
  - "Wave-based CI: fast checks run first, expensive Docker build only after all pre-checks pass"
  - "Artifact upload on failure: playwright-report uploaded for 7 days to enable debug of E2E failures"

requirements-completed: [CI-01, CI-02, CI-03, CI-04]

# Metrics
duration: 2min
completed: 2026-03-30
---

# Phase 57 Plan 01: CI/CD Pipeline Summary

**Four-wave GitHub Actions pipeline wiring all quality gates (migrate-check, coverage, Playwright E2E, PHPUnit Integration) to run automatically on every push/PR**

## Performance

- **Duration:** ~2 min
- **Started:** 2026-03-30T12:04:43Z
- **Completed:** 2026-03-30T12:06:35Z
- **Tasks:** 3
- **Files modified:** 1

## Accomplishments
- Added `migrate-check` job: pure grep for SQLite patterns in migration SQL files, no PostgreSQL needed, exits 3 on failure (CI-03)
- Added `coverage` job: installs pcov via shivammathur/setup-php, runs coverage-check.sh enforcing Services >= 90% and Controllers >= 60% (CI-02)
- Added `e2e` job: rebuilds image from GHA Buildx cache, starts full Docker stack via compose with COMPOSE_PROJECT_NAME=agvote, runs Playwright chromium suite, uploads playwright-report artifact on failure (CI-01)
- Added `integration` job: uses GHA services for Postgres 16.8 + Redis 7.4, loads schema-master.sql + all migrations, runs PHPUnit Integration suite (CI-04)
- Updated `build.needs` to block Docker build until all 4 pre-build gates pass

## Task Commits

Each task was committed atomically:

1. **Task 1: Add pre-build CI jobs (migrate-check and coverage)** - `7e8dc7f` (feat)
2. **Task 2: Add post-build CI jobs (e2e and integration)** - `904bfb3` (feat)
3. **Task 3: Validate complete workflow YAML** - no files changed (validation only)

**Plan metadata:** to be committed with this SUMMARY

## Files Created/Modified
- `.github/workflows/docker-build.yml` - Extended from 3 to 7 jobs: added migrate-check, coverage, e2e, integration; updated build.needs

## Decisions Made
- **E2E image reuse via GHA cache:** e2e job uses Buildx `cache-from: type=gha` to restore the image built by the `build` job, tags it `agvote-app`, then runs `docker compose --no-build`. This avoids a full Dockerfile rebuild in the post-build job.
- **COMPOSE_PROJECT_NAME=agvote:** Setting this ensures compose looks for image `agvote-app` and creates containers named `agvote-app`, `agvote-db`, `agvote-redis` — matching the hardcoded `agvote-redis` in `auth.setup.js::clearRateLimit()`.
- **GHA services for integration tests:** Using `services:` block rather than docker-compose for the integration job provides cleaner lifecycle management — PostgreSQL and Redis are guaranteed healthy before any step runs.
- **Chromium-only E2E in CI:** Running only `--project=chromium` keeps E2E runtime reasonable; full browser matrix (firefox, webkit, mobile-chrome, tablet) runs locally.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required. All jobs use secrets already available in GitHub Actions (`GITHUB_TOKEN` for registry push).

## Next Phase Readiness
- CI pipeline is complete. Every push to main now validates migrations, enforces coverage thresholds, runs Playwright E2E against the Docker stack, and runs Integration tests against real PostgreSQL + Redis.
- Phase 57 is complete — the v5.0 Quality & Production Readiness milestone is done.

---
*Phase: 57-ci-cd-pipeline*
*Completed: 2026-03-30*
