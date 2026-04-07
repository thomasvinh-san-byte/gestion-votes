# Phase 4: Tests et Decoupage Controllers - Context

**Gathered:** 2026-04-07
**Status:** Ready for planning
**Mode:** Auto-generated (infrastructure phase — discuss skipped)

<domain>
## Phase Boundary

Les gaps de tests sur les edge cases SSE et import sont fermes, et les controllers encore trop lourds apres extraction sont decoupes.

Success criteria:
1. Les tests SSE couvrent la perte de connexion Redis, le reordering d'evenements, et la reconnexion du client
2. Les tests ImportService couvrent le fuzzy matching avec variantes de casse, caracteres accentues, et headers multi-langue
3. MeetingReportsController et MotionsController font chacun moins de 400 lignes, ou une justification documentee explique pourquoi le seuil n'est pas atteint

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion — pure infrastructure/testing phase. Use ROADMAP phase goal, success criteria, and codebase conventions to guide decisions.

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

None — infrastructure phase.

</deferred>
