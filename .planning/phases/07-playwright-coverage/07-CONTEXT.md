# Phase 7: Playwright Coverage - Context

**Gathered:** 2026-04-08
**Status:** Ready for planning
**Mode:** Auto-generated (infrastructure phase — discuss skipped)

<domain>
## Phase Boundary

Toute regression visible dans un vrai navigateur est detectee par la suite Playwright. Baseline verte, tests d'interaction par page, upgrade Playwright 1.59.1 + axe-core, workflow operateur E2E complet.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion — pure test infrastructure phase.

Key research findings from Phase 6 RESEARCH.md to incorporate:
- Existing Playwright suite has 18 spec files in tests/e2e/specs/
- Multi-browser config: chromium/firefox/webkit/mobile/tablet
- Global auth setup via API (avoids login rate limit)
- Session cookie injection pattern
- waitForHtmxSettled() helper now exists in tests/e2e/helpers/ (created Phase 5)
- Some specs use waitForLoadState('networkidle') which hangs on SSE pages — must be replaced with domcontentloaded + element-based assertions
- vote.spec.js already imports waitForHtmxSettled (Phase 5 gap closure)

### Implementation Approach
- TEST-01: Run full Playwright suite, fix any failing specs
- TEST-02: Add interaction tests for each key page (load + click main button + assert DOM change)
- TEST-03: Upgrade @playwright/test 1.58.2 → 1.59.1, install @axe-core/playwright
- TEST-04: Operator workflow E2E — login → create meeting → add members → start vote → close

</decisions>

<code_context>
## Existing Code Insights

### Key Files
- tests/e2e/specs/ — 18 existing spec files
- tests/e2e/helpers/ — auth helpers, waitForHtmxSettled.js
- playwright.config.js — multi-browser config
- package.json — Playwright dependency

### Established Patterns
- Auth via cookie injection (not form submit) to avoid rate limit
- Each spec is a single .spec.js file
- Selectors prefer data-testid then ID then class

### Integration Points
- New tests go in tests/e2e/specs/
- New helpers go in tests/e2e/helpers/
- Config changes in playwright.config.js
- @axe-core/playwright as devDependency in package.json

</code_context>

<specifics>
## Specific Ideas

No specific requirements — infrastructure phase. Focus on making the test suite robust and catching real-world regressions.

</specifics>

<deferred>
## Deferred Ideas

None — infrastructure phase.

</deferred>
