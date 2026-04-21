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
- ✓ Contrast WCAG 2.1 AA 4.5:1 sur toutes les paires fg/bg — v1.4 Phase 1
- ✓ Shadow DOM hex fallback strip (0 var(--color-*, #hex)) — v1.4 Phase 1
- ✓ v1.3-A11Y-REPORT.md declare CONFORME (plus partial) — v1.4 Phase 1
- ✓ Global :where([hidden]) rule neutralise pattern [hidden]+display:flex — v1.4 Phase 2
- ✓ Fixtures Playwright auditor/assessor avec seed endpoint — v1.4 Phase 3
- ✓ htmx 2.0.6 upgrade + DELETE param migration + cross-browser validation — v1.4 Phase 4
- ✓ CSP nonce enforcement report-only + PageController routing — v1.4 Phase 5
- ✓ 4 controllers >500 LOC reduits a <300 LOC via extraction services — v1.4 Phase 6
- ✓ Codebase cleanup: console.log, dead code, superglobals, TODOs — v1.5 Phase 1
- ✓ AuthMiddleware 277 LOC + SessionManager 227 LOC + RbacEngine 259 LOC — v1.5 Phase 2
- ✓ ImportService 250 LOC + CsvImporter 292 LOC + XlsxImporter 300 LOC — v1.5 Phase 3
- ✓ ExportService 290 LOC + ValueTranslator 282 LOC — v1.5 Phase 4
- ✓ MeetingReportsService 293 LOC + ReportGenerator 296 LOC — v1.5 Phase 5
- ✓ EmailQueueService 212 LOC + RetryPolicy 259 LOC — v1.5 Phase 6
- ✓ Validation gate: zero route changes, unit tests green, E2E specs intact — v1.5 Phase 7
- ✓ JS interaction audit: 8 broken selectors fixed across 21 HTMX pages — v1.6 Phase 1
- ✓ Form layout modernization: multi-column grids on 16 pages, field classes normalized — v1.6 Phase 2
- ✓ Wizard CSS compacted for 1080p viewport fit — v1.6 Phase 3
- ✓ Validation gate: zero regressions after UI fixes — v1.6 Phase 4
- ✓ 73 routes mutantes auditees et classees par risque — v1.7 Phase 1
- ✓ 13 routes critiques protegees par IdempotencyGuard — v1.7 Phase 2
- ✓ Transitions workflow idempotentes (launch/close) — v1.7 Phase 2
- ✓ HTMX X-Idempotency-Key sur tous les POST/PATCH + 6 tests unitaires — v1.7 Phase 3

### Active

## Current Milestone: v2.0 Operateur Live UX

**Goal:** Ameliorer l'experience operateur en mode seance live — checklist de controle, interface epuree, et feedback visuel en temps reel sur les votes.

**Target features:**
- Checklist operateur en mode live (quorum OK, reseau OK, votes recus)
- Reduction des zones d'info de 9 a 4-5 en mode execution
- Animation sur les compteurs de vote en temps reel

### Out of Scope

- ~~Regressions visuelles v4.2~~ — resolved in v1.6
- ~~Interactions JS/HTMX cassees~~ — resolved in v1.6
- Nouvelles fonctionnalites metier — stabiliser d'abord
- Migration vers un framework (Symfony, Laravel) — refactoring incremental uniquement
- PDFs de convocation/emargement — hors perimetre de l'app
- Raccourcis clavier — hors perimetre

## Current State

Shipped v1.8 (2026-04-20) — UI refonte complete. Palette moderne slate, classes CSS normalisees, version unifiee v2.0, 67 problemes UI corriges.

**The project is production-hardened.** WCAG AA contrast fully conformant, CSP nonces in report-only mode, htmx 2.0.6 with zero regressions, controller architecture cleaned up (<300 LOC each), all test fixtures real (no admin fallbacks).

- **Visual polish shipped:** toast notification system unifié, dark mode parity audit (21 pages), role-specific sidebar nav filter, micro-interactions (focus rings, hover states, loading transitions)
- **Multi-browser matrix:** chromium 25/25, firefox 25/25, webkit 23/25, mobile-chrome 21/25 (deferred webkit/mobile critical-path gaps documented in 15-CROSS-BROWSER-REPORT.md)
- **Accessibility:**
  - 22 pages parametrized axe audit (from 7 hand-written tests)
  - 47 critical/serious structural violations fixed across 5 rule-id batches (zero waivers)
  - keyboard-nav.spec.js created — 6/6 tests green (skip-link, Tab order, ag-modal shadow DOM focus trap)
  - contrast-audit.spec.js + v1.3-CONTRAST-AUDIT.json produced (22/22 pages)
  - v1.3-A11Y-REPORT.md — partial WCAG 2.1 AA conformance declared
  - 4 real a11y regressions discovered by runtime validation and fixed: login autofocus, auth-banner ordering, operator overlay [hidden] CSS x2, ag-modal `_trapFocus` shadow DOM traversal
- **Loose ends Phase 12 closed:** settings loadSettings race (POST→GET + defensive re-apply + __settingsLoaded handshake), postsession eIDAS chip delegation (document-level with idempotency guard), Phase 12 SUMMARY audit ledger (6 findings: 2 resolved, 3 v2 deferred, 0 fix-now)
- **Dev infra:** dev-only docker-compose.override.yml bind-mounts public/ for live JS iteration without rebuilds

## Tech Debt Carried to v1.4

- ~~**Contrast remediation**~~ — ✓ Resolved in v1.4 Phase 1 (316 → 0 violations, CONFORME declared)
- ~~**V2-OVERLAY-HITTEST**~~ — ✓ Resolved in v1.4 Phase 2 (global `:where([hidden])` rule, 16 overrides removed, audit doc produced)
- ~~**V2-TRUST-DEPLOY**~~ — ✓ Resolved in v1.4 Phase 3 (loginAsAuditor/loginAsAssessor fixtures, seedUser endpoint, zero loginAsAdmin in trust specs)
- ~~**HTMX 2.0 upgrade**~~ — ✓ Resolved in v1.4 Phase 4 (2.0.6 + compat, DELETE param migration, zero regressions)
- ~~**V2-CSP-INLINE-THEME**~~ — ✓ Resolved in v1.4 Phase 5 (nonce + strict-dynamic, report-only mode, zero violations)
- **Phase 15 multi-browser deferrals** — webkit 2/25 + mobile-chrome 4/25 critical-path specs flaky/viewport-dependent
- **Visual regression testing** — snapshot comparison, separate milestone

## Completed Milestone: v1.8 Refonte UI et Coherence Visuelle

**Shipped:** 2026-04-20 — 5 phases, 9 plans, 13 requirements, all satisfied.

See `.planning/milestones/v1.8-ROADMAP.md` for full archive.

## Completed Milestone: v1.7 Audit Idempotence

**Shipped:** 2026-04-20 — 3 phases, 4 plans, 7 requirements, all satisfied.

See `.planning/milestones/v1.7-ROADMAP.md` for full archive.

## Completed Milestone: v1.6 Reparation UI et Polish Fonctionnel

**Shipped:** 2026-04-20 — 4 phases, 8 plans, 9 requirements, all satisfied.

See `.planning/milestones/v1.6-ROADMAP.md` for full archive.

## Completed Milestone: v1.5 Nettoyage et Refactoring Services

**Shipped:** 2026-04-20 — 7 phases, 9 plans, 18 requirements, all satisfied.

See `.planning/milestones/v1.5-ROADMAP.md` for full archive.

## Completed Milestone: v1.4 Régler Deferred et Dette Technique

**Shipped:** 2026-04-10 — 6 phases, 14 plans, 24 requirements, all satisfied.

See `.planning/milestones/v1.4-ROADMAP.md` for full archive.

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
| axeAudit.js disables color-contrast in structural runner | Contrast depends on light/dark theme and design tokens; avoid blocking CI on false positives tuned elsewhere | ✓ Good — D-04 methodology split, contrast audited separately via v1.3-CONTRAST-AUDIT.json, 316 nodes DEFERRED to token phase |
| ag-modal focus trap must traverse shadowRoot.activeElement chain | `document.activeElement` stops at shadow boundary and returns the host, not the focused button inside shadow root — Shift+Tab wrap was silently broken | ✓ Good — fixed in phase 16 gap closure (commit ef0fc529), documented as reusable pattern in RESEARCH.md |
| Dev-only docker-compose.override.yml bind-mounts public/ | Production Dockerfile COPYs public/ at build time; live JS iteration required a rebuild cycle, blocking fast a11y test loops | ✓ Good — unblocked phase 16-02 baseline capture, kept out of production image |

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
*Last updated: 2026-04-10 — v1.5 milestone started*
