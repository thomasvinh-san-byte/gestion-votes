# Phase 07: Validation Gate - Context

**Gathered:** 2026-04-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Confirm that the v1.5 milestone introduced zero regressions: no changed public URLs, all unit tests green, all E2E tests green. This is a verification-only phase — no code changes unless a regression is found and must be fixed.

</domain>

<decisions>
## Implementation Decisions

### Validation Strategy
- **D-01:** Route stability check via diff of routes.php against pre-v1.5 commit — must show zero changes to URL patterns
- **D-02:** PHPUnit full suite run with --no-coverage — zero failures, zero errors required
- **D-03:** Playwright chromium project run — zero failures required
- **D-04:** If any regression is found, fix it in this phase before declaring milestone complete

### Claude's Discretion
- Order of validation steps (routes → PHPUnit → Playwright is the natural progression)
- Whether to run additional browser targets beyond chromium (not required by GUARD-03)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requirements
- `.planning/REQUIREMENTS.md` — GUARD-01, GUARD-02, GUARD-03 define the three validation gates

### Route Reference
- `app/routes.php` — The route table that must remain unchanged

### Test Configuration
- `phpunit.xml` — PHPUnit configuration
- `playwright.config.ts` — Playwright configuration and project definitions

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- PHPUnit test suite: existing tests in `tests/Unit/` cover all refactored services
- Playwright E2E suite: existing specs in `tests/e2e/` cover critical user paths

### Established Patterns
- Prior phases used `timeout 60 php vendor/bin/phpunit ...` for bounded test runs
- Playwright runs target `--project=chromium` per GUARD-03

### Integration Points
- `app/routes.php` is the single source of truth for public URL patterns
- Pre-v1.5 baseline commit needed for routes diff (first commit before Phase 01 changes)

</code_context>

<specifics>
## Specific Ideas

No specific requirements — standard validation gate with clear pass/fail criteria.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 07-validation-gate*
*Context gathered: 2026-04-20*
