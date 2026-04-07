# AgVote — Reduction de la dette technique

## What This Is

Application web de gestion de votes pour associations et collectivites, construite en PHP 8.4 avec PostgreSQL, Redis, et HTMX. Ce milestone vise a assainir le codebase existant : eliminer les risques de fiabilite en production, refactorer les composants surdimensionnes, et combler les lacunes de tests.

## Core Value

L'application doit etre fiable en production — aucun crash lie a des fallbacks fichiers, des fuites memoire, ou des timeouts silencieux.

## Requirements

### Validated

- ✓ Gestion d'assemblees (creation, parametrage, workflow) — existing
- ✓ Motions et scrutins (creation, vote, resultats) — existing
- ✓ Quorum et procurations — existing
- ✓ Authentification multi-tenant avec RBAC — existing
- ✓ Import CSV/XLSX de membres — existing
- ✓ Export Excel/PDF des resultats — existing
- ✓ Notifications email avec file d'attente — existing
- ✓ SSE temps reel pour mises a jour live — existing
- ✓ Protection CSRF, rate-limiting, middleware de securite — existing

### Active

- [ ] Supprimer les fallbacks fichiers SSE — Redis obligatoire
- [ ] Supprimer le rate-limiting fichiers — Redis obligatoire
- [ ] Supprimer le PID-file SSE — detection serveur via Redis
- [ ] Corriger les exports memoire (streaming ou pagination)
- [ ] Corriger les COUNT(*) multiples dans MeetingStatsRepository
- [ ] Ajouter timeout PDO et query timeout PostgreSQL
- [ ] Ajouter backpressure a EmailQueueService (traitement par lots)
- [ ] Extraire la logique metier de ImportController en services
- [ ] Extraire la logique metier de AuthMiddleware en services
- [ ] Splitter MeetingReportsController si necessaire apres extraction
- [ ] Splitter MotionsController si necessaire apres extraction
- [ ] Ajouter tests RgpdExportController
- [ ] Ajouter tests transitions d'etat AuthMiddleware
- [ ] Ajouter tests race conditions SSE (sera Redis apres migration)
- [ ] Ajouter tests fuzzy matching ImportController

### Out of Scope

- Regressions visuelles v4.2 — traite dans un milestone separe
- Interactions JS/HTMX cassees — traite dans un milestone separe
- Nouvelles fonctionnalites metier — stabiliser d'abord
- Migration vers un framework (Symfony, Laravel) — refactoring incremental uniquement
- PDFs de convocation/emargement — hors perimetre de l'app
- Raccourcis clavier — hors perimetre

## Context

- Codebase brownfield PHP 8.4 avec architecture MVC custom
- Redis deja integre (cache, queues) mais des composants critiques utilisent encore des fichiers /tmp comme fallback
- 6 controllers depassent 680 lignes — logique metier melee a l'orchestration HTTP
- AuthMiddleware utilise 10+ variables statiques avec etat interdependant
- ExportService charge tout en memoire avant generation XLSX
- MeetingStatsRepository fait 10+ requetes COUNT(*) separees au lieu d'une aggregation
- EmailQueueService charge toute la queue en memoire sans pagination
- Tests existants couvrent ~60% des cas critiques, lacunes sur RGPD et edge cases

## Constraints

- **Stack**: PHP 8.4, PostgreSQL, Redis (obligatoire apres ce milestone)
- **Namespaces**: AgVote\Controller, AgVote\Service, AgVote\Repository
- **Architecture**: Controllers API etendent AbstractController, controllers HTML utilisent HtmlView::render()
- **DI**: Constructeur avec parametres optionnels nullable pour les tests
- **Langue**: Texte visible en francais, jamais "copropriete" ou "syndic"
- **Compatibilite**: Ne pas casser les APIs existantes

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Redis obligatoire (plus de fallback fichier) | Fallbacks fichiers causent perte d'evenements, race conditions, et locks en prod | — Pending |
| Extraire en services + splitter si necessaire | Controllers > 700 lignes melangent logique metier et HTTP — maintenance difficile | — Pending |
| Fiabilite prod avant qualite de code | Un crash prod est pire qu'un controller trop long | — Pending |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd:transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd:complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-04-07 after initialization*
