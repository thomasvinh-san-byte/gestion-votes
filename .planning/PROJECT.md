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

(Defined in REQUIREMENTS.md for v1.2)

### Out of Scope

- Regressions visuelles v4.2 — traite dans un milestone separe
- Interactions JS/HTMX cassees — traite dans un milestone separe
- Nouvelles fonctionnalites metier — stabiliser d'abord
- Migration vers un framework (Symfony, Laravel) — refactoring incremental uniquement
- PDFs de convocation/emargement — hors perimetre de l'app
- Raccourcis clavier — hors perimetre

## Current State

Shipped v1.2 — Bouclage et Validation Bout-en-Bout milestone complete (2026-04-09).

**The project is shippable.** Every page passes 3 gates: UI pleine largeur, design language partage, every element functionally proven by Playwright.

- 23 critical-path Playwright specs GREEN (3 runs consecutifs, zero flake, ~1.2m total)
- 21 pages applicatives auditees et reparees (Phase 12 sweep)
- 5 endpoints fantomes prouves (procuration_pdf, motions_override_decision, invitations_send_reminder, meeting_attachments_public, meeting_attachment_serve)
- 3 vote settings (settVoteMode, settQuorumThreshold, settMajority) wired dans VoteEngine + QuorumEngine
- Test infrastructure Docker reproductible (bin/test-e2e.sh + mcr.microsoft.com/playwright)
- Dette tech v1.0 fully closed (getDashboardStats wiring + 2 controller refactors)

Hotfixes critiques delivres en cours de v1.2 :
- RateLimiter::configure() boot regression (app crashait sur chaque API call)
- nginx clean URL routing (regression de plusieurs semaines)
- Cookie domain via Redis TCP direct (Phase 9 prerequis tests)
- Chrome HSTS preload .app collision (network alias agvote)
- Login 2-panel redesign polish

## Current Milestone: v1.3 Polish Post-MVP

**Goal:** Faire passer l'app de "fonctionnelle prouvee" (v1.2) a "delicieuse a utiliser et solide cross-browser". Polish visuel + robustesse tests.

**Target features:**
- Visual polish: toast notification system, dark mode parity audit, role-specific sidebar nav, micro-interactions
- Multi-browser tests: extend Phase 8 to firefox + webkit + mobile-chromium, run 23 critical-path specs cross-browser
- a11y deep audit: axe-core complet sur les 21 pages, fix critical + serious violations, WCAG 2.1 AA
- Loose ends Phase 12: settings loadSettings race, postsession eIDAS chip, minor known issues

## Tech Debt Carried to v1.3 (post-MVP)

- Multi-browser test matrix (firefox/webkit) — currently chromium-only via Phase 8 setup
- axe-core accessibility deep audit — baseline integrated in v1.1, deep audit deferred
- HTMX 2.0 upgrade — breaking changes (hx-on case sensitivity), separate milestone
- Visual regression testing (snapshot comparison) — separate milestone

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
*Last updated: 2026-04-08 after v1.2 milestone start*
