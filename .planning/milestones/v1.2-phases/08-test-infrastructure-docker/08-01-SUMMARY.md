---
phase: 08-test-infrastructure-docker
plan: 01
subsystem: test-infrastructure
tags: [playwright, docker, e2e, infrastructure]
dependency_graph:
  requires: []
  provides: [INFRA-01, playwright-docker-service, conditional-baseurl]
  affects: [tests/e2e/playwright.config.js, docker-compose.yml, .gitignore]
tech_stack:
  added: [mcr.microsoft.com/playwright:v1.59.1-jammy]
  patterns: [docker-compose-profiles, conditional-env-config, named-volume-cache]
key_files:
  created: []
  modified:
    - docker-compose.yml
    - tests/e2e/playwright.config.js
    - .gitignore
decisions:
  - "Microsoft Playwright jammy image pinned to v1.59.1 to match @playwright/test 1.59.1 in package.json (locked)"
  - "profiles: [test] gates the service — it does not start on plain docker compose up (locked)"
  - "IN_DOCKER env var as conditional flag for baseURL and webServer.url (locked)"
  - "Dual reporter: line (terminal) + html (playwright-report/) — no auto-open (locked)"
  - "Named volume tests-node-modules for npm install cache (discretion)"
  - "Non-root user via UID/GID host vars to avoid root-owned files on bind mount (discretion)"
metrics:
  duration: ~10 minutes
  completed: 2026-04-08T07:54:54Z
  tasks_completed: 3
  tasks_total: 3
  files_modified: 3
---

# Phase 8 Plan 01: Playwright Docker Service Setup Summary

**One-liner:** Playwright containerized test service using mcr.microsoft.com/playwright:v1.59.1-jammy with profile gating, backend network wiring, and conditional baseURL via IN_DOCKER env var.

## What Was Built

Added a reproducible containerized Playwright test runner environment (INFRA-01). The `tests` service in docker-compose.yml uses Microsoft's official Playwright jammy image (which ships all browser system libraries preinstalled), gated behind the `test` profile so it never starts on plain `docker compose up`. The service waits for `app` to be healthy before running, shares the `backend` network for service name resolution (`app:8080`), and uses a named volume to cache `node_modules` between runs. playwright.config.js now resolves the correct host depending on execution context.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Add `tests` service to docker-compose.yml | 59c1788e | docker-compose.yml |
| 2 | Conditional baseURL + dual reporter in playwright.config.js | 9caeffd1 | tests/e2e/playwright.config.js |
| 3 | .gitignore for playwright-report and test artifacts | a865da9a | .gitignore |

## Verification Results

- `docker compose --profile test config` exits 0 (YAML valid)
- `docker compose config` (default) does not include `tests` service
- `node -e "require('./tests/e2e/playwright.config.js')"` exits 0 (config parses)
- `grep -c "IN_DOCKER" tests/e2e/playwright.config.js` returns 2 (baseURL + webServer.url)
- `grep "mcr.microsoft.com/playwright:v1.59.1-jammy" docker-compose.yml` matches
- `grep "tests/e2e/playwright-report" .gitignore` matches

## Deviations from Plan

None - plan executed exactly as written. All locked decisions honored.

## Self-Check: PASSED

- [x] docker-compose.yml contains `mcr.microsoft.com/playwright:v1.59.1-jammy`
- [x] docker-compose.yml `profiles: [test]` present
- [x] docker-compose.yml `tests-node-modules` volume in both service and top-level volumes
- [x] tests/e2e/playwright.config.js contains `IN_DOCKER` (2 occurrences)
- [x] tests/e2e/playwright.config.js reporter is array with `line` and `html`
- [x] .gitignore contains `tests/e2e/playwright-report/`, `tests/e2e/test-results/`, `tests/e2e/node_modules/`
- [x] Commits 59c1788e, 9caeffd1, a865da9a exist in git log
