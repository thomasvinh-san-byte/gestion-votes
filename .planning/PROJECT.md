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
- ✓ Checklist operateur live (quorum/votes/SSE/connected) avec alertes visuelles — v2.0 Phase 1
- ✓ Mode Focus 5-zones avec toggle + persistance sessionStorage — v2.0 Phase 2
- ✓ Animations vote (RAF + bar transitions) avec respect prefers-reduced-motion — v2.0 Phase 3
- ✓ Validation gate cross-phase v2.0 — audit PASS, zero regression — v2.0 Phase 4
- ✓ Hotfix securite F1: /setup 404 opaque + CSRF strict + 4 tests de regression — v2.0 (PR #247)
- ✓ CI repair: lint-js + migrate-check verts, validate ameliore (83→21 errors), coverage redis-aware — v2.0 (PR #248)
- ✓ v2.1 Hardening Sécurité : 21 contremesures F02-F22 shipped — defense en profondeur sur auth, vote, isolation tenant, perimetre, uploads, headers, monitoring (voir milestones/v2.1-REQUIREMENTS.md)
- ✓ v2.2 Design Tokens & Components (PR #256 mergé)
- ✓ v2.3 Layout Refonte & UX Polish (PR #259 ouvert, 35/35 reqs PASSED, gates manuelles A1+B1.1+D1 pending dev machine) — voir `.planning/milestones/v2.3-REQUIREMENTS.md`
- ✓ v2.4 Polish & Robustness (PR #260 mergé 2026-05-04, 12/12 reqs `tech_debt` verdict, 12 items deferred to v2.5) — voir entrée v2.4 dans `.planning/MILESTONES.md`
- ✓ v2.5 Real-time Live Cockpit + Logger Migration (PR #261/#262 mergé 2026-05-04, 8/12 done + 2 SEC bonus, 4 deferred v2.6+) — voir `.planning/milestones/v2.5-REQUIREMENTS.md`
  - `meeting.heartbeat` 10s SSE tick avec snapshot quorum + status + presence
  - 47 sites `error_log()` migrés vers `Logger::*` avec sévérité différenciée
  - `error_events` table + `api_fail()` capture + `/admin/error-stats` recâblée
  - `next_step_clicks` tracking endpoint + dashboard CTR colonne
  - SEC-V2-01 closeout étendu : 27 method signatures hardened across 11 repos

### Active

## Current Milestone: v2.6 Clôture dette technique

**Goal :** Liquider la dette explicite accumulée v2.3/v2.5 et atteindre un état "tout shipped, zéro carry-forward". Pas d'ajout opportuniste — chaque item est tracé en amont. Ambition : clôture stricte, ~1 semaine, 5 phases courtes.

**Target features (5 buckets) :**
- **Bucket 1 — Tests heartbeat** : HEARTBEAT-V25-03 (PHPUnit payload `meeting.heartbeat`) + HEARTBEAT-V25-04 (Playwright `sse-heartbeat.spec.js` 12s tick) — lève le stop-tests directive v2.5
- **Bucket 2 — Codes erreur ciblés** : `business_error` générique → 3 codes ciblés (différés Phase 4 v2.3 follow-up 04.6-FOLLOWUP-2) + race conditions empty-state idempotency (04.6-FOLLOWUP-3)
- **Bucket 3 — TOKENS 7.2-7.4** : width/soft/none cleanup + emphasis flatten + ring variants — cible <30 tokens 1-site (suite du 4-phase remediation plan dans `.planning/v2.5-TOKENS-AUDIT.md`)
- **Bucket 4 — Test infra + GSD ergo** : seed-meeting helper `@integration` + Playwright dual-install fix + test infra README + Explore patterns doc + code-reviewer budget timeout / scope splits
- **Bucket 5 — Print/PDF polish** : dompdf header répétabilité + em-dash UTF-8 fallback + pagination robuste sur PVs ≥10 pages

**Hors scope explicite :**
- 3 OPS résiduelles (§3 Playwright runs, §4 visual inspection, §5 cron schedule) — restent dev-machine, pas de code à écrire
- Nouvelles capacités métier — pas en milestone de clôture
- Refactos opportunistes — clôture stricte, pas d'ajout en cours de route

**Status (2026-05-05):** v2.5 archivée + 6 commits ad-hoc post-archive shippés sur main. v2.6 en planning. Phases v2.3/v2.4 archivées dans `milestones/v2.5-phases/`.

**Recently shipped (v2.6 in progress + post-v2.5 close, on main, no PR yet):**

- ✓ **PDF-V26-01/02/03 (v2.6 Phase 5)** : `tests/Unit/MeetingReportsLongPdfTest.php` (4 tests / 46 assertions GREEN) parsing PDF binaire via `smalot/pdfparser` ^2 (nouveau dev dep) — header répété chaque page sur PV ≥10 pages, em-dash UTF-8 + accents français rendus correct, footer `Page X sur Y` correct. Production code intact (verification only, @page rules de v2.4 P4 conservées). *Commits `552ee6b` / `b95b8bb` / `ec42030`.*
- ✓ **INFRA-V26-01..05 (v2.6 Phase 4)** : 3 plans archival/verification — seedMeeting helper audit static PASS (helper + spec + route TestSeedController présents), Playwright dual-install audit (`bin/check-deps.sh` guard intact), README e2e 5 sections, `EXPLORE-PATTERNS.md` déplacé `.planning/codebase/` → `.planning/intel/` (canonical), gsd-code-reviewer flags vérifiés. Runtime gates dev-machine déferrés (3 runs verts Playwright F-4, fresh-clone ≤30 min walkthrough, real gsd-code-review v2.6). *Commits `c98f80d` / `346f108` / `acb900d` / `f115c3f` / `2e77d9b`.*
- ✓ **TOKENS-V26-01/02/03/04 (v2.6 Phase 3)** : Phases 7.2/7.3/7.4 du `v2.5-TOKENS-AUDIT.md` exécutées — 10 tokens 1-site retirés (-2 width via consolidation `--border-thick`, -4 emphasis flatten, -4 ring variants unifiés vers 4 canoniques `--shadow-ring-2px-*`). 9 fichiers callers migrés (login/postsession/analytics/vote/pages/validate/wizard/meetings/ag-health-bar). Audit final `v2.6-TOKENS-AUDIT-FINAL.md` : 31 tokens 1-site (delta +1 vs cible <30 documenté drift v2.5→v2.6 hors-scope), ratios borders 97.7% + shadows 100% (cible 95% dépassée). 6 tokens orphelins détectés bonus pour v2.7. Régression visuelle déférée dev-machine (10 pages checklist embedded). *Commits `0fc1d09` / `8846e0e` / `93567c1` / `5b35178`.*
- ✓ **ERR-V26-01/02/03 (v2.6 Phase 2)** : `AbstractController::extractBusinessErrorCode()` détecte snake_case dans `RuntimeException::getMessage()` → ~80 services existants bénéficient automatiquement (`meeting_not_found`, `motion_not_found`, etc. surfacent au lieu de `business_error`). 3 service throws français normalisés (MeetingTransitionService:56/251, MeetingLifecycleService:44 → `archived_meeting_locked` / `validated_meeting_locked`). 2 nouvelles entrées `ErrorDictionary` avec next-step Norman v2.3 ERR-02. `ErrorEventsRepository::capture()` guard d'idempotence intra-request (md5(rid|code|route)). 20 PHPUnit tests GREEN (10+3+7). *Commits `0efe010` / `b3e46ff` / `92a1fe2` / `66417fb` / `013035b` / `d24f3af`.*
- ✓ **HEARTBEAT-V25-03/04 (v2.6 Phase 1)** : `tests/Unit/Sse/HeartbeatPayloadTest.php` (8 tests / 29 assertions, byte-identical payload vs v2.5 free function via `AgVote\SSE\HeartbeatPayloadBuilder` extraction) + `tests/e2e/specs/sse-heartbeat.spec.js` (Playwright EventSource 13s wait, ≥1 `meeting.heartbeat` capture). Stop-tests v2.5 directive levée. *Commits `403478c` / `0673e78` / `9dbb4e2`.*
- ✓ **SEC-V2-02** : `CsrfMiddleware::field()` migré vers `fieldFor('POST', '/setup')` action-scopé HMAC sur le seul caller production (`setup_form.php`). `field()` marqué `@deprecated`. *Commit `ade443f`.*
- ✓ **SEC-V2-03** : `InvitationRepository` hashage migré de `hash('sha256')` vers `hash_hmac('sha256', $token, APP_SECRET)` (4 sites centralisés via `hashToken()` helper). Migration SQL `20260504_invitation_revoke_pre_hmac.sql` révoque les invitations pré-HMAC avec audit log. *Commit `ade443f`.*
- ✓ **COCKPIT-V25-01** : sub-tab Avancé wrapping 4 actions secondaires (Unanimité / Passerelle / Procuration / Suspendre) dans `<details>` disclosure `#opAvanceActions`. Net delta -3 cliquables visibles. Test spec étendu (1 nouveau cas Playwright). *Commit `eeb9aa4`.*
- ✓ **TOKENS-V25-01** : audit `.planning/v2.5-TOKENS-AUDIT.md` (43 tokens classés 22 keep / 21 consolidate, 4-phase remediation plan). Phase 7.1 (alpha unification) exécutée : `--border-primary-alpha-22` + `--border-success-alpha-20` retirés (visual delta 3-5% imperceptible). *Commit `d7bf36e`.*

**Tech Debt carried (remaining v2.6 buckets):**

- **TOKENS-V25-01 Phases 7.2-7.4** (v2.6 Phase 3) : width/soft/none cleanup + emphasis flatten + ring variants unification — 4-phase remediation plan ready for pickup, target <30 tokens 1-site
- **Gates dev-machine v2.4 inheritance** : Playwright runs (cockpit-button-count, sse-burst-idempotency, cockpit-keyboard-shortcuts, critical-path-operator, cockpit-health-bar) + screenshots Phase 1 + 3 PVs longs PDF visuels + CSS smoke regression 17 fichiers migrés tokens
- **3 DB migrations à appliquer côté ops** avant prod : `20260504_error_events.sql` + `20260504_next_step_clicks.sql` + `20260504_invitation_revoke_pre_hmac.sql` (toutes idempotentes)

---

## Previously: v2.4 Polish & Robustness (shipped PR #260)

**Goal:** Consolider la fiabilité production post-v2.3 — éliminer les frictions toolchain (test infra, code-review scope), refactorer les codes d'erreur génériques en codes ciblés observables, et finir le polish cockpit pour atteindre une charge cognitive opérateur maîtrisée (≤25 boutons visibles, palette danger confinée).

**Strategy:** 4 phases sequentielles. P1+P2 parallélisables (zones disjointes : cockpit JS/CSS vs services PHP). P3 prérequis avant v2.5 sécurité (pentest sans friction Playwright). P4 opportuniste, peut chevaucher.

**Target phases:**
- Phase 1 — Cockpit Polish & Hygiène : declutter ≤25 boutons + persona color confinement (Schoger S-1/S-6)
- Phase 2 — Error Observability : business_error → codes ciblés + race conditions empty-state idempotency + Logger context enrichment
- Phase 3 — Test Infrastructure : seed-meeting helper + code-reviewer scope splits + Playwright dual-install fix + README + Explore patterns doc
- Phase 4 — Print + Tech Debt residuel : dompdf header impression + ~140 borders + ~45 shadows residuels (tokens ≥95 %)

Voir `.planning/REQUIREMENTS.md` pour les 12 requirements détaillés (COCKPIT-V24-01/02, ERR-V24-01..03, TEST-V24-01..05, TECH-V24-01/02).
Voir `.planning/v2.4-BACKLOG-PLAN.md` pour le découpage thématique source.

**Pré-requis** : v2.3 PR #259 mergée avant Phase 1 v2.4.

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

## Completed Milestone: v2.1 Hardening Sécurité

**Shipped:** 2026-04-29 — 6 phases, 22 plans, 21 requirements (F02-F22), all satisfied.

PRs delivered: #247 (F1 setup), #249/#250/#254 (consolidées), #255.

Tech debt carried to v2.2 :
- 8 méthodes MotionRepository à `tenantId = ''` optionnel
- Migration progressive `field()` → `fieldFor(method, path)` pour F10
- Hash invitation token SHA-256 → HMAC-SHA256 (forcer re-issue)

See `.planning/milestones/v2.1-REQUIREMENTS.md` for full archive.

## Completed Milestone: v2.0 Operateur Live UX

**Shipped:** 2026-04-29 — 4 phases, 6 plans, 11 requirements, all satisfied.

Audit cross-phase: PASS (`.planning/milestones/v2.0-MILESTONE-AUDIT.md`).
Hotfix sécurité F1 (PR #247) + CI repair (PR #248) inclus.

See `.planning/milestones/v2.0-ROADMAP.md` for full archive.

## Completed Milestone: v1.9 UX Standards & Retention

**Shipped:** 2026-04-21 — 5 phases, 9 plans, 16 requirements, all satisfied.

See `.planning/milestones/v1.9-ROADMAP.md` for full archive.

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
*Last updated: 2026-05-05 — v2.6 milestone bootstrapped (Clôture dette technique, 5 buckets, ~1 sem). v2.5 archived; phase dirs v2.3/v2.4 leftover déplacés vers `milestones/v2.5-phases/`.*
