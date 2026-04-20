# Phase 1: JS Interaction Audit & Repair - Context

**Gathered:** 2026-04-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Audit all 21 HTMX pages for broken JS interactions (event handlers, DOM selectors, HTMX targets, form submissions, SSE wiring) and fix everything found. The goal is functional correctness — every button responds, every form submits via HTMX, every SSE listener connects.

</domain>

<decisions>
## Implementation Decisions

### Audit Methodology
- **D-01:** Code-first static analysis — grep/read JS+HTML for broken selectors, missing targets, event handler mismatches. No running server needed.
- **D-02:** All 21 pages audited equally — no prioritization. Every page gets the same depth of analysis.

### Fix Scope
- **D-03:** Claude decides per case — minimal fix for simple issues, root cause fix for recurring patterns. If HTML structure from v4.2 is the root cause of a recurring problem, fix the HTML.

### Verification
- **D-04:** Code verification only — verify fixes via static analysis (selectors match DOM elements, event handlers target correct elements, HTMX attributes valid). No browser runtime needed.
- **D-05:** SSE verification via code review only — verify EventSource URLs, event listeners, DOM update handlers are correctly wired. No runtime test.

### Claude's Discretion
- Fix depth per issue (minimal vs root cause) based on judgment
- How to group pages into plans (by functional area, by complexity, or alphabetically)
- Whether to batch small fixes or create separate commits per page

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requirements
- `.planning/REQUIREMENTS.md` — JSFIX-01 through JSFIX-04

### Prior Art
- `.planning/milestones/v1.1-phases/` — v1.1 Phase 5 did a JS audit and wiring repair previously

### Key Files
- `public/assets/js/` — 57 JS files, 28,819 LOC total
- `public/*.htmx.html` — 21 HTMX page templates
- `public/assets/css/design-system.css` — design token definitions and form grid classes

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `core/shell.js` (913 LOC) — page initialization, HTMX event handling, sidebar management
- `core/utils.js` (779 LOC) — shared utilities (API calls, DOM helpers, formatting)
- `core/shared.js` (563 LOC) — shared state and cross-page utilities
- `components/` — 16 web components (ag-modal, ag-toast, ag-searchable-select, etc.)

### Established Patterns
- HTMX-driven: forms submit via `hx-post`/`hx-patch`/`hx-delete`, responses swap DOM
- JS files are per-page (`pages/*.js`) with shared core (`core/*.js`) and components (`components/*.js`)
- Event delegation via `document.addEventListener` and HTMX event hooks (`htmx:afterSwap`, `htmx:beforeRequest`)
- SSE via `EventSource` in `core/event-stream.js` (181 LOC)

### Integration Points
- Each HTMX page loads a corresponding `pages/*.js` file
- `shell.js` initializes on every page (sidebar, theme, HTMX global handlers)
- `design-system.css` provides `form-grid-2` class used by some pages but potentially conflicting with page-specific CSS

### Pages to Audit (21 total)
admin, analytics, archives, audit, dashboard, docs, email-templates, help, hub, meetings, members, operator, postsession, public, report, settings, trust, users, validate, vote, wizard

</code_context>

<specifics>
## Specific Ideas

- The v4.2 visual redesign moved HTML elements, renamed classes, changed DOM structure — JS selectors may reference elements that no longer exist or have different class names
- `form-grid-2` class is used in wizard.htmx.html but defined in design-system.css, not wizard.css — potential load order conflict
- wizard.js (1172 LOC) and operator-tabs.js (3534 LOC) are the largest page-specific JS files — most likely to have issues

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 01-js-interaction-audit-repair*
*Context gathered: 2026-04-20*
