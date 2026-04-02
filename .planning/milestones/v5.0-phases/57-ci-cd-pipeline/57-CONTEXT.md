# Phase 57: CI/CD Pipeline - Context

**Gathered:** 2026-03-30
**Status:** Ready for planning

<domain>
## Phase Boundary

Wire Playwright E2E tests, code coverage gates, migration validation, and integration tests into the GitHub Actions workflow. Every quality gate runs automatically on push/PR.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion — pure infrastructure phase. Build on the existing .github/workflows/docker-build.yml.

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- .github/workflows/docker-build.yml — existing CI with validate, lint-js, build+push stages
- tests/e2e/playwright.config.js — configured for chromium, baseURL via env
- scripts/coverage-check.sh — enforces 90% Services, 60% Controllers
- scripts/validate-migrations.sh — checks SQLite syntax + optional full migration run
- phpunit.xml — clover output configured

### Established Patterns
- GitHub Actions with matrix jobs
- Docker build uses multi-stage (assets + runtime)
- Smoke test after build (PHP extensions + autoload)
- pcov installed locally, needs apt in CI

### Integration Points
- Playwright needs Chromium browser in CI runner
- E2E needs running Docker stack (app + postgres + redis)
- Coverage needs pcov extension
- Migration validation needs PostgreSQL for full mode

</code_context>

<specifics>
## Specific Ideas

No specific requirements — infrastructure phase.

</specifics>

<deferred>
## Deferred Ideas

None.

</deferred>
