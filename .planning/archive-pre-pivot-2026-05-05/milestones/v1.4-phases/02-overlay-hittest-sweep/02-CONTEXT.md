# Phase 2: Overlay Hittest Sweep - Context

**Gathered:** 2026-04-10
**Status:** Ready for planning
**Mode:** Auto-generated (infrastructure phase — discuss skipped)

<domain>
## Phase Boundary

Le pattern `[hidden]` + `display:flex` est neutralise globalement et audite a l'echelle du codebase. Ajouter une regle CSS `:where([hidden]) { display: none !important }` dans la couche base du design-system, auditer tous les selecteurs `display: flex|grid|block` sur elements susceptibles de recevoir `[hidden]`, et verifier via Playwright que le pattern est neutralise.

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
