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

### Active

(Defined in REQUIREMENTS.md for v1.4)

### Out of Scope

- Regressions visuelles v4.2 — traite dans un milestone separe
- Interactions JS/HTMX cassees — traite dans un milestone separe
- Nouvelles fonctionnalites metier — stabiliser d'abord
- Migration vers un framework (Symfony, Laravel) — refactoring incremental uniquement
- PDFs de convocation/emargement — hors perimetre de l'app
- Raccourcis clavier — hors perimetre

## Current State

v1.4 Phase 5 complete (2026-04-10) — CSP nonce enforcement in report-only.

**The project is shippable AND polished.** Every page passes 3 gates from v1.2 plus 2 new ones: visual polish coherence, and axe-core structural a11y conformance. Contrast remediation now complete: 316 violations → 0, CONFORME declared.

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

## Current Milestone: v1.4 Régler Deferred et Dette Technique

**Goal:** Éliminer la dette technique reportée de v1.0-v1.3 : design-system contrast, patterns CSS fragiles, debt test/CSP, upgrade HTMX 2.0, et refactoring des controllers volumineux restants.

**Target features:**
- Contrast remediation au niveau des design tokens (316 nœuds, 42 paires, WCAG AA 4.5:1)
- V2-OVERLAY-HITTEST — audit codebase-wide du pattern `[hidden]` + `display:flex`
- V2-TRUST-DEPLOY — fixtures auditor/assessor pour trust.htmx.html
- V2-CSP-INLINE-THEME — externaliser ou nonce-ifier les scripts inline theme init
- HTMX 2.0 upgrade — migration breaking changes (hx-on case sensitivity)
- Refactoring des controllers >500 lignes (Meetings 687, MeetingWorkflow 559, Operator 516, Admin 510)

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
*Last updated: 2026-04-10 — v1.4 Phase 5 complete (CSP Nonce Enforcement)*
