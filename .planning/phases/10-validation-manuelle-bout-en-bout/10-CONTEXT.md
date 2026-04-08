# Phase 10: Validation Manuelle Bout-en-Bout - Context

**Gathered:** 2026-04-08
**Status:** Awaiting human walkthrough

<domain>
## Phase Boundary

Toi (utilisateur) parcours l'application en vrai dans un browser, role par role et page par page, en remplissant les checklists generees par cette phase. Le rapport UAT-REPORT.md documente exactement ce qui marche et ce qui casse. Phase 11 utilisera ce rapport pour scoper FIX-01.

</domain>

<decisions>
## Implementation Decisions

### Workflow
1. Cette phase produit 4 checklists role + 8 checklists page + 1 template UAT-REPORT.md
2. L'humain les remplit dans son browser (manuel, pas Playwright)
3. Phase 11 lit UAT-REPORT.md pour scoper les fixes

### Roles a tester
- Admin (admin@ag-vote.local / Admin2026!)
- Operator (operator@ag-vote.local / Operator2026!)
- President (president@ag-vote.local / President2026!)
- Votant (votant@ag-vote.local / Votant2026!)

### Pages a couvrir
1. /dashboard (admin/operator)
2. /hub (operator/president)
3. /meetings (operator)
4. /members (operator)
5. /operator (operator/president)
6. /vote (votant)
7. /settings (admin)
8. /admin (admin)

</decisions>

<code_context>
## Existing Code Insights

App is running at http://192.168.122.135:8080 (or http://localhost:8080 from same machine).
Cookie domain bug fixed in Phase 9.
nginx routing fixed earlier (/dashboard, /meetings, etc serve correct htmx.html).
RateLimiter::configure() boot regression fixed earlier.
4 critical-path Playwright tests now green (Phase 9).

</code_context>

<specifics>
## Specific Ideas

- L'utilisateur a explicitement dit: "rien ne remplace un humain qui clique"
- Les 11 items "human verification deferred" de v1.1 doivent etre confirmes ici
- Si quelque chose casse, NOTER mais ne pas fixer ici (Phase 11 gere les fixes)

</specifics>

<deferred>
## Deferred Ideas

None — manual walkthrough phase, no scope creep.

</deferred>
