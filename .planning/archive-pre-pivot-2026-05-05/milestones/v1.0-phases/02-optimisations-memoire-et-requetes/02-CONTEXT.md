# Phase 2: Optimisations Memoire et Requetes - Context

**Gathered:** 2026-04-07
**Status:** Ready for planning
**Mode:** Auto-generated (infrastructure phase — discuss skipped)

<domain>
## Phase Boundary

Aucun chemin de code ne charge un jeu de donnees complet en memoire — exports, emails, et stats d'assemblee sont tous traites de facon incrementale. PDO timeout configure pour eviter les workers bloques.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion — pure infrastructure phase. Use ROADMAP phase goal, success criteria, and codebase conventions to guide decisions.

Key constraints from research:
- openspout/openspout ^5.6 pour streaming XLSX (sub-3MB memoire)
- PhpSpreadsheet reste si formules/charts necessaires — auditer feuille par feuille
- PostgreSQL COUNT(*) FILTER (WHERE ...) pour aggregation unique
- PDO::ATTR_TIMEOUT pour connection timeout, SET statement_timeout pour query timeout
- statement_timeout configurable par env (0 en CI/test)
- EmailQueueService batch de 25 avec LIMIT, pas de chargement complet

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Services/ExportService.php` — Export XLSX actuel avec PhpSpreadsheet (674 lignes, chargement memoire)
- `app/Repository/MeetingStatsRepository.php` — 10+ COUNT(*) queries separees (lignes 40-135)
- `app/Core/Providers/DatabaseProvider.php` — Connexion PDO singleton (pas de timeout)
- `app/Services/EmailQueueService.php` — Traitement queue emails sans pagination

### Established Patterns
- Singleton DatabaseProvider pour connexion PDO
- RepositoryFactory pour DI
- composer require pour nouvelles dependances

### Integration Points
- ExportService utilise par ExportController
- MeetingStatsRepository utilise par dashboard controllers
- DatabaseProvider::$pdo utilise par tous les repositories
- EmailQueueService utilise par cron workers

</code_context>

<specifics>
## Specific Ideas

No specific requirements — infrastructure phase. Refer to ROADMAP phase description and success criteria.

</specifics>

<deferred>
## Deferred Ideas

None — infrastructure phase.

</deferred>
