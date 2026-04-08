# Phase 5: JS Audit et Wiring Repair - Context

**Gathered:** 2026-04-07
**Status:** Ready for planning
**Mode:** Auto-generated (infrastructure phase — discuss skipped)

<domain>
## Phase Boundary

Chaque page charge sans erreur JS console et chaque bouton principal declenche l action attendue. Inventaire des contrats DOM, reparation de tous les handlers casses, fix du timing sidebar async, et creation du helper waitForHtmxSettled() pour Playwright.

</domain>

<decisions>
## Implementation Decisions

### Claude s Discretion
All implementation choices are at Claude s discretion — pure infrastructure phase. Use ROADMAP phase goal, success criteria, and codebase conventions to guide decisions.

Key research findings to incorporate:
- 1,269 querySelector/getElementById calls across page JS files — audit all against current HTML
- Only 2 pages use real HTMX (postsession, vote) — rest is vanilla JS + fetch()
- Sidebar loads async via fetch(/partials/sidebar.html) — shared.js, shell.js, auth-ui.js all depend on it
- vote.js:852 has known mismatch: getElementById(voteButtons) but HTML has class=vote-buttons

</decisions>

<code_context>
## Existing Code Insights

### Key Files
- public/assets/js/pages/*.js — per-page JS modules with fetch handlers
- public/assets/js/core/shared.js — sidebar async loading
- public/assets/js/core/shell.js — sidebar pin/scroll behavior
- public/assets/js/core/auth-ui.js — role-based sidebar filtering
- public/partials/sidebar.html — sidebar partial loaded via fetch
- tests/e2e/ — Playwright test infrastructure (18 specs)

### Established Patterns
- Vanilla JS + fetch() for API calls (not HTMX despite .htmx.html naming)
- DOM manipulation via querySelector/getElementById
- Sidebar loaded async then JS attaches event handlers

### Integration Points
- waitForHtmxSettled() helper goes in tests/e2e/helpers/
- ID contract inventory is a documentation artifact, not code

</code_context>

<specifics>
## Specific Ideas

No specific requirements — infrastructure phase. Refer to ROADMAP phase description and success criteria.

</specifics>

<deferred>
## Deferred Ideas

None — infrastructure phase.

</deferred>
