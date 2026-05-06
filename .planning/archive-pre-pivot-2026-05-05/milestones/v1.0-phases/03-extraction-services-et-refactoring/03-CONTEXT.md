# Phase 3: Extraction Services et Refactoring - Context

**Gathered:** 2026-04-07
**Status:** Ready for planning
**Mode:** Auto-generated (infrastructure phase — discuss skipped)

<domain>
## Phase Boundary

ImportController est un orchestrateur HTTP pur (sous 150 lignes), et AuthMiddleware est teste et documente avant tout refactoring de ses statics. Tests de caracterisation ecrits AVANT extraction.

Requirements: REFAC-01 (ImportController extraction), TEST-01 (RgpdExportController tests), TEST-02 (AuthMiddleware tests)

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion — pure infrastructure phase. Use ROADMAP phase goal, success criteria, and codebase conventions to guide decisions.

Key constraints from research:
- Tests de caracterisation obligatoires AVANT toute extraction
- ImportController (921 lignes) → logique metier dans services dedies
- Controller = orchestration HTTP uniquement (< 150 lignes)
- DI par constructeur avec parametres optionnels nullable pour les tests
- Namespaces: AgVote\Service pour nouveaux services
- RgpdExportController: tester scope validation, acces non autorise, compliance donnees
- AuthMiddleware: tester lifecycle session complet, transitions d'etat 10+ statics

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Controller/ImportController.php` — 921 lignes, logique CSV/XLSX melee a HTTP
- `app/Core/Security/AuthMiddleware.php` — 871 lignes, 10+ variables statiques
- `app/Controller/RgpdExportController.php` — 45 lignes, pas de tests
- `app/Services/ImportService.php` — service existant pour logique import
- `tests/Unit/ImportControllerTest.php` — tests existants
- `tests/Unit/AuthMiddlewareTest.php` — tests existants (partiels)

### Established Patterns
- Services injectes via constructeur avec nullable params
- RepositoryFactory pour DI
- AbstractController pour controllers API

### Integration Points
- ImportController utilise par routes import CSV/XLSX
- AuthMiddleware utilise par Router pour chaque requete
- RgpdExportController expose endpoint GET /api/v1/rgpd_export

</code_context>

<specifics>
## Specific Ideas

No specific requirements — infrastructure phase. Refer to ROADMAP phase description and success criteria.

</specifics>

<deferred>
## Deferred Ideas

- AuthMiddleware static → SessionContext DI (v2 — REFAC-02)
- Split MeetingReportsController/MotionsController (Phase 4)

</deferred>
