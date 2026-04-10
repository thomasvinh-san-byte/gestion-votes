# Phase 5: CSP Nonce Enforcement - Context

**Gathered:** 2026-04-10
**Status:** Ready for planning
**Mode:** Auto-generated (infrastructure phase — discuss skipped)

<domain>
## Phase Boundary

Les scripts inline theme init portent des nonces CSP ; `'unsafe-inline'` est retire de `script-src` apres une periode report-only. Implementer SecurityProvider::nonce(), injecter les nonces dans tous les inline scripts/styles des 22 .htmx.html, passer de report-only a enforcement, et valider via Playwright zero violation CSP.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion — pure infrastructure phase. Use ROADMAP phase goal, success criteria, and codebase conventions to guide decisions.

</decisions>

<code_context>
## Existing Code Insights

Codebase context will be gathered during plan-phase research.

</code_context>

<specifics>
## Specific Ideas

No specific requirements — infrastructure phase. Refer to ROADMAP phase description and success criteria.

</specifics>

<deferred>
## Deferred Ideas

None — discuss phase skipped.

</deferred>
