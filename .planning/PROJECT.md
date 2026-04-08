# AgVote — Application de gestion de votes

## What This Is

Application web de gestion de votes pour associations et collectivites, construite en PHP 8.4 avec PostgreSQL, Redis, et HTMX. L'application gere des assemblees generales avec motions, scrutins, quorum, procurations, et resultats en temps reel.

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
- ✓ SSE Redis-only (fallback fichier supprime) — v1.0 Phase 1
- ✓ Rate-limiting Redis Lua atomique (flock supprime) — v1.0 Phase 1
- ✓ Detection serveur SSE via heartbeat Redis (PID-file supprime) — v1.0 Phase 1
- ✓ Health check Redis au boot avec erreur claire — v1.0 Phase 1
- ✓ PDO timeouts + statement_timeout PostgreSQL configurable — v1.0 Phase 2
- ✓ Stats aggregation single-query (getDashboardStats) — v1.0 Phase 2
- ✓ Streaming XLSX via OpenSpout (sub-3MB memoire) — v1.0 Phase 2
- ✓ Email batch processing (lots de 25, pagine) — v1.0 Phase 2
- ✓ ImportController extrait en ImportService (149 lignes) — v1.0 Phase 3
- ✓ Tests AuthMiddleware lifecycle session complet (28 tests) — v1.0 Phase 3
- ✓ Tests RgpdExportController (4 tests) — v1.0 Phase 3
- ✓ Tests SSE EventBroadcaster race conditions (14 tests) — v1.0 Phase 4
- ✓ Tests ImportService fuzzy matching aliases (54 tests) — v1.0 Phase 4
- ✓ Inventaire contrats ID JS + reparation selectors casses — v1.1 Phase 5
- ✓ Sidebar async timing hardening + sidebar:loaded event — v1.1 Phase 5
- ✓ waitForHtmxSettled() Playwright helper — v1.1 Phase 5
- ✓ Design tokens uniformes sur tous les CSS par-page — v1.1 Phase 6
- ✓ Login 2-panels 40/60 avec brand panel rich — v1.1 Phase 6
- ✓ Loading states CSS .htmx-request avec skeleton-row — v1.1 Phase 6
- ✓ Status badges semantiques coherents — v1.1 Phase 6
- ✓ Playwright baseline green (networkidle elimine) — v1.1 Phase 7
- ✓ Tests d'interaction par page (page-interactions.spec.js) — v1.1 Phase 7
- ✓ Playwright 1.59.1 + @axe-core/playwright — v1.1 Phase 7
- ✓ Workflow operateur E2E (operator-e2e.spec.js) — v1.1 Phase 7

### Active

(Fresh for next milestone — run /gsd:new-milestone)

### Out of Scope

- Regressions visuelles v4.2 — traite dans un milestone separe
- Interactions JS/HTMX cassees — traite dans un milestone separe
- Nouvelles fonctionnalites metier — stabiliser d'abord
- Migration vers un framework (Symfony, Laravel) — refactoring incremental uniquement
- PDFs de convocation/emargement — hors perimetre de l'app
- Raccourcis clavier — hors perimetre

## Current State

Shipped v1.1 — Coherence UI/UX et Wiring milestone complete. Application now coherent and reliable:
- JS/HTMX wiring repaired: 1,269 selectors audited, vote.js mismatch fixed, 5 dead-code blocks removed, sidebar timing hardened
- Design tokens applied uniformly across 5 per-page CSS files, 5 badge defects fixed
- Login redesigned as 40/60 2-panel layout with hero content, features, trust pills (no more empty space)
- HTMX skeleton-row loading wired into operator/members/meetings list containers
- Playwright upgraded to 1.59.1, @axe-core/playwright integrated with per-page audits
- New tests: page-interactions.spec.js (8 tests, 7 pages) + operator-e2e.spec.js (full workflow)
- 3 critical hotfixes delivered: RateLimiter::configure() boot regression (v1.0 leftover blocked all API requests), nginx clean URL routing (multi-week regression that served index.html instead of htmx.html), login 2-panel polish

v1.0 base still in place:
- Redis only broker (SSE, rate-limiting, heartbeat)
- Streaming XLSX exports, paginated email batches, single-query stats
- ImportController 921 → 149 lines

Tech debt carried to v1.2:
- Browser test execution requires libatk system libraries or Docker
- 11 human verification items deferred from Phases 5-7
- getDashboardStats() not wired into DashboardController (from v1.0)
- MeetingReportsController (727 lignes) et MotionsController (720 lignes) not split (from v1.0)
- 10/14 tests EventBroadcaster necessitent phpredis (Docker uniquement) (from v1.0)

## Next Milestone

(Not yet started — run `/gsd:new-milestone` to begin v1.2)

## Context

- Codebase brownfield PHP 8.4 avec architecture MVC custom
- Redis integre partout — aucun composant critique n'utilise de fichiers /tmp
- ImportController est un orchestrateur HTTP pur (149 lignes)
- ImportService contient toute la logique d'import avec DI nullable pour tests
- ExportService utilise OpenSpout pour streaming XLSX sub-3MB
- EmailQueueService traite par lots pagines de 25
- Tests couvrent auth lifecycle, RGPD, SSE delivery, et fuzzy matching import

## Constraints

- **Stack**: PHP 8.4, PostgreSQL, Redis (obligatoire)
- **Namespaces**: AgVote\Controller, AgVote\Service, AgVote\Repository
- **Architecture**: Controllers API etendent AbstractController, controllers HTML utilisent HtmlView::render()
- **DI**: Constructeur avec parametres optionnels nullable pour les tests
- **Langue**: Texte visible en francais, jamais "copropriete" ou "syndic"
- **Compatibilite**: Ne pas casser les APIs existantes

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Redis obligatoire (plus de fallback fichier) | Fallbacks fichiers causent perte d'evenements, race conditions, et locks en prod | ✓ Good — Phase 1 delivered cleanly |
| Extraire en services + splitter si necessaire | Controllers > 700 lignes melangent logique metier et HTTP | ✓ Good — ImportController 149 lines, splits deferred for MeetingReports/Motions |
| Fiabilite prod avant qualite de code | Un crash prod est pire qu'un controller trop long | ✓ Good — all file fallbacks eliminated, timeouts configured |
| DI nullable constructors for testability | Enables mock injection without framework overhead | ✓ Good — ImportService tested with mock RepositoryFactory |
| Controller splits deferred to v2 | Existing tests assert private method existence, making splits disruptive | ⚠️ Revisit — MeetingReportsController and MotionsController still >700 lines |

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
*Last updated: 2026-04-08 after v1.1 milestone completion*
