# Phase 56: E2E Test Updates - Context

**Gathered:** 2026-03-30
**Status:** Ready for planning

<domain>
## Phase Boundary

Update all 18 Playwright E2E specs to use correct selectors matching v4.3/v4.4 rebuilt pages. All specs must pass on Chromium against the running Docker stack. Mobile viewport specs must pass for vote/ballot page.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion — pure infrastructure phase. Follow existing Playwright patterns in tests/e2e/specs/.

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- tests/e2e/specs/ — 18 existing spec files
- tests/e2e/helpers.js — shared test helpers
- tests/e2e/playwright.config.js — 5 browser projects (chromium, firefox, webkit, mobile-chrome, tablet)
- Docker stack running at http://localhost:8080

### Established Patterns
- Playwright test framework with page object pattern
- baseURL configurable via env
- webServer config starts PHP built-in server for CI

### Integration Points
- All pages rebuilt in v4.3/v4.4 with new DOM IDs and selectors
- Login page uses #email, #password, #submitBtn
- Vote page uses French data-choice attributes (pour/contre/abstention)
- Audit page has new filter tabs, timeline/table views

</code_context>

<specifics>
## Specific Ideas

No specific requirements — infrastructure phase.

</specifics>

<deferred>
## Deferred Ideas

None.

</deferred>
