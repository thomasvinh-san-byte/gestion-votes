# Roadmap: AgVote — Reduction de la Dette Technique

## Overview

Ce milestone assainit le codebase AgVote en quatre phases ordonnees par severite de risque production. On elimine d'abord les infrastructures fichiers fragiles (SSE, rate-limiter, PID) et on rend Redis obligatoire. On traite ensuite les bugs memoire et les requetes N+1. On extrait enfin la logique metier des controllers surdimensionnes avec les tests de caracterisation en amont. Les tests des edge cases ferment le cycle. A chaque phase, le comportement externe reste identique — seule la robustesse interne change.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [ ] **Phase 1: Infrastructure Redis** - Eliminer les fallbacks fichiers /tmp, rendre Redis obligatoire avec health check au boot
- [ ] **Phase 2: Optimisations Memoire et Requetes** - Streaming XLSX, traitement email par lots, aggregation COUNT unique
- [ ] **Phase 3: Extraction Services et Refactoring** - Tests de caracterisation AuthMiddleware et RGPD, extraction ImportController
- [ ] **Phase 4: Tests et Decoupage Controllers** - Tests SSE race conditions et fuzzy matching import, splits conditionnels controllers

## Phase Details

### Phase 1: Infrastructure Redis
**Goal**: L'application ne depend plus d'aucun fichier /tmp en production — Redis est le seul broker pour SSE, rate-limiting, et detection serveur
**Depends on**: Nothing (first phase)
**Requirements**: REDIS-01, REDIS-02, REDIS-03, REDIS-04
**Success Criteria** (what must be TRUE):
  1. Le demarrage de l'application echoue avec un message clair si Redis est indisponible, sans tenter de fallback fichier
  2. Les evenements SSE sont emis et recus via Redis Pub/Sub — aucun fichier cree dans /tmp/sse*
  3. Deux requetes simultanees de vote se bloquent correctement via le script Lua Redis, jamais via flock()
  4. La detection d'un serveur SSE actif repose sur une cle Redis avec TTL, pas sur un PID-file
**Plans**: TBD

### Phase 2: Optimisations Memoire et Requetes
**Goal**: Aucun chemin de code ne charge un jeu de donnees complet en memoire — exports, emails, et stats d'assemblee sont tous traites de facon incrementale
**Depends on**: Phase 1
**Requirements**: PERF-01, PERF-02, PERF-03, PERF-04
**Success Criteria** (what must be TRUE):
  1. L'export XLSX d'une assemblee avec 5 000 participations consomme moins de 3 MB de memoire PHP
  2. EmailQueueService traite les emails par lots de 25 sans jamais charger la queue entiere en memoire
  3. Le tableau de bord d'assemblee n'emet qu'une seule requete SQL pour toutes les statistiques de quorum et votes
  4. Une requete PDO qui depasse le timeout configuré retourne une erreur geree, pas un worker bloque indefiniment
**Plans**: TBD

### Phase 3: Extraction Services et Refactoring
**Goal**: ImportController est un orchestrateur HTTP pur (sous 150 lignes), et AuthMiddleware est teste et documente avant tout refactoring de ses statics
**Depends on**: Phase 2
**Requirements**: REFAC-01, TEST-01, TEST-02
**Success Criteria** (what must be TRUE):
  1. Les tests AuthMiddleware couvrent le lifecycle session complet et toutes les transitions d'etat des variables statiques, et passent en isolation et en suite complete
  2. Les tests RgpdExportController couvrent la validation de scope, l'acces non autorise, et la conformite des donnees exportees
  3. ImportController fait moins de 150 lignes et ne contient aucune logique de validation, transformation, ou matching de colonnes
  4. Un import CSV complet (creation, mise a jour, matching) peut etre teste sans contexte HTTP
**Plans**: TBD

### Phase 4: Tests et Decoupage Controllers
**Goal**: Les gaps de tests sur les edge cases SSE et import sont fermes, et les controllers encore trop lourds apres extraction sont decoupes
**Depends on**: Phase 3
**Requirements**: TEST-03, TEST-04
**Success Criteria** (what must be TRUE):
  1. Les tests SSE couvrent la perte de connexion Redis, le reordering d'evenements, et la reconnexion du client
  2. Les tests ImportService couvrent le fuzzy matching avec variantes de casse, caracteres accentues, et headers multi-langue
  3. MeetingReportsController et MotionsController font chacun moins de 400 lignes, ou une justification documentee explique pourquoi le seuil n'est pas atteint
**Plans**: TBD

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3 → 4

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Infrastructure Redis | 0/TBD | Not started | - |
| 2. Optimisations Memoire et Requetes | 0/TBD | Not started | - |
| 3. Extraction Services et Refactoring | 0/TBD | Not started | - |
| 4. Tests et Decoupage Controllers | 0/TBD | Not started | - |
