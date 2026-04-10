# Roadmap: AgVote

## Milestones

- ✅ **v1.0 Dette Technique** - Phases 1-4 (shipped 2026-04-07) — see `.planning/milestones/v1.0-ROADMAP.md`
- ✅ **v1.1 Coherence UI/UX et Wiring** - Phases 5-7 (shipped 2026-04-08) — see `.planning/milestones/v1.1-ROADMAP.md`
- ✅ **v1.2 Bouclage et Validation Bout-en-Bout** - Phases 8-13 (shipped 2026-04-09) — see `.planning/milestones/v1.2-ROADMAP.md`
- ✅ **v1.3 Polish Post-MVP** - Phases 14-17 (shipped 2026-04-09) — see `.planning/milestones/v1.3-ROADMAP.md`
- ✅ **v1.4 Regler Deferred et Dette Technique** - Phases 1-6 (shipped 2026-04-10) — see `.planning/milestones/v1.4-ROADMAP.md`
- 🚧 **v1.5 Nettoyage et Refactoring Services** - Phases 1-7 (in progress)

## Phases

<details>
<summary>✅ v1.0 Dette Technique (Phases 1-4) - SHIPPED 2026-04-07</summary>

See `.planning/milestones/v1.0-ROADMAP.md` for full details.

</details>

<details>
<summary>✅ v1.1 Coherence UI/UX et Wiring (Phases 5-7) - SHIPPED 2026-04-08</summary>

See `.planning/milestones/v1.1-ROADMAP.md` for full details.

**Phases:** 3 (5, 6, 7)
**Plans:** 11
**Hotfixes delivered:** 3 (RateLimiter boot, nginx routing, login redesign)

</details>

<details>
<summary>✅ v1.2 Bouclage et Validation Bout-en-Bout (Phases 8-13) - SHIPPED 2026-04-09</summary>

See `.planning/milestones/v1.2-ROADMAP.md` for full details.

**Phases:** 6 (8-13)
**Plans:** 36
**Critical-path Playwright specs:** 23 GREEN x 3 runs zero flake
**Hotfixes delivered:** 5 (RateLimiter boot, nginx routing, login polish, cookie domain, HSTS preload)

</details>

<details>
<summary>✅ v1.3 Polish Post-MVP (Phases 14-17) - SHIPPED 2026-04-09</summary>

See `.planning/milestones/v1.3-ROADMAP.md` for full details.

**Phases:** 4 (14, 15, 16, 17)
**Plans:** 12 (4 + 1 + 5 + 3) — phase 15 executed inline without PLAN files
**Shipped:**
- Visual polish: toast system unifie, dark mode parity, role-specific sidebar, micro-interactions
- Cross-browser: chromium + firefox + webkit + mobile-chrome matrix (25/25 chromium, 25/25 firefox, 23/25 webkit, 21/25 mobile-chrome)
- Accessibility deep audit: 47 structural violations fixed, keyboard-nav spec (6/6), contrast audit produced (316 nodes DEFERRED to token remediation), WCAG 2.1 AA partial conformance
- Loose ends: settings loadSettings race fixed, eIDAS chip delegation fixed, Phase 12 SUMMARY audit ledger (6 findings, 3 deferred to v2)

**Deferred to v2:**
- CONTRAST-REMEDIATION (316 color-contrast nodes, design-token work)
- V2-OVERLAY-HITTEST (systematic `[hidden]`+flex overlay sweep)
- V2-TRUST-DEPLOY (trust.htmx.html auditor/assessor fixtures)
- V2-CSP-INLINE-THEME (strict CSP compatibility)

</details>

<details>
<summary>✅ v1.4 Regler Deferred et Dette Technique (Phases 1-6) — SHIPPED 2026-04-10</summary>

See `.planning/milestones/v1.4-ROADMAP.md` for full details.

**Phases:** 6 (1-6)
**Plans:** 14
**Requirements:** 24/24 satisfied
**Shipped:**
- Contrast AA remediation: 316 violations -> 0, WCAG 2.1 AA CONFORME
- Global [hidden] rule + codebase audit
- Auditor/assessor Playwright fixtures with seed endpoint
- htmx 2.0.6 upgrade with zero regressions (4 browsers)
- CSP nonce enforcement in report-only mode
- 4 controllers refactored from >500 to <300 LOC

</details>

### 🚧 v1.5 Nettoyage et Refactoring Services (In Progress)

**Milestone Goal:** Nettoyer le codebase (console.log, code deprecie, superglobals, TODOs) et refactorer les 5 services >600 LOC en respectant le plafond 300 LOC.

- [x] **Phase 1: Nettoyage Codebase** - Supprimer console.log, code deprecie, TODOs, migrer superglobals, tester PageController (completed 2026-04-10)
- [ ] **Phase 2: Refactoring AuthMiddleware** - Extraire SessionManager et RbacEngine, ramener AuthMiddleware <300 LOC
- [ ] **Phase 3: Refactoring ImportService** - Extraire CsvImporter et XlsxImporter, ramener ImportService <300 LOC
- [ ] **Phase 4: Refactoring ExportService** - Extraire ValueTranslator, ramener ExportService <300 LOC
- [ ] **Phase 5: Refactoring MeetingReportsService** - Extraire ReportGenerator, ramener MeetingReportsService <300 LOC
- [ ] **Phase 6: Refactoring EmailQueueService** - Extraire RetryPolicy, ramener EmailQueueService <300 LOC
- [ ] **Phase 7: Validation Gate** - Confirmer zero regression routes, PHPUnit vert, Playwright vert

## Phase Details

### Phase 1: Nettoyage Codebase
**Goal**: Le codebase de production ne contient plus de bruit (console.log, code mort, superglobals directs, TODOs non resolus) et PageController a une couverture de test
**Depends on**: Nothing (first phase)
**Requirements**: CLEAN-01, CLEAN-02, CLEAN-03, CLEAN-04, CLEAN-05
**Success Criteria** (what must be TRUE):
  1. `grep -rn 'console\.\(log\|warn\|error\)' public/assets/js/` retourne zero resultats (hors error handlers critiques documentes)
  2. `grep -rn 'PermissionChecker' app/` retourne zero resultats et les methodes deprecated de VoteTokenService sont supprimees
  3. `grep -rn 'TODO\|FIXME' public/assets/js/ public/assets/css/` retourne zero resultats
  4. `grep -rn '\$_GET\|\$_POST\|\$_REQUEST' app/` retourne zero resultats (hors bootstrap/index.php)
  5. PHPUnit PageControllerTest passe au vert couvrant nonce injection et 404
**Plans**: 2 plans

Plans:
- [ ] 01-01-PLAN.md — JS/CSS cleanup (console.log, TODO) + dead code removal (PermissionChecker, deprecated methods)
- [ ] 01-02-PLAN.md — Superglobal migration (6 controllers) + PageController unit test

### Phase 2: Refactoring AuthMiddleware
**Goal**: AuthMiddleware est un orchestrateur leger (<300 LOC) qui delegue la gestion de session a SessionManager et l'evaluation RBAC a RbacEngine
**Depends on**: Phase 1
**Requirements**: REFAC-01, REFAC-02
**Success Criteria** (what must be TRUE):
  1. `wc -l app/Core/Middleware/AuthMiddleware.php` affiche <300 lignes
  2. `wc -l app/Services/SessionManager.php` et `wc -l app/Services/RbacEngine.php` affichent chacun <300 lignes
  3. SessionManager et RbacEngine sont des `final class` avec constructeur DI nullable (grep confirme)
  4. Les tests AuthMiddleware existants passent au vert sans modification
**Plans**: 2 plans

Plans:
- [ ] 02-01-PLAN.md — Extract SessionManager + RbacEngine, refactor AuthMiddleware to thin orchestrator
- [ ] 02-02-PLAN.md — Unit tests for SessionManager and RbacEngine (isolation proof)

### Phase 3: Refactoring ImportService
**Goal**: ImportService est un orchestrateur leger (<300 LOC) qui delegue le parsing CSV a CsvImporter et XLSX a XlsxImporter
**Depends on**: Phase 1
**Requirements**: REFAC-03, REFAC-04
**Success Criteria** (what must be TRUE):
  1. `wc -l app/Services/ImportService.php` affiche <300 lignes
  2. `wc -l app/Services/CsvImporter.php` et `wc -l app/Services/XlsxImporter.php` affichent chacun <300 lignes
  3. CsvImporter et XlsxImporter sont des `final class` avec constructeur DI nullable (grep confirme)
  4. Les 49+ tests ImportServiceTest existants passent au vert sans modification
**Plans**: 1 plan

Plans:
- [ ] 03-01-PLAN.md — Extract CsvImporter + XlsxImporter, refactor ImportService to thin facade with delegation stubs

### Phase 4: Refactoring ExportService
**Goal**: ExportService est un orchestrateur leger (<300 LOC) qui delegue la traduction de valeurs a ValueTranslator
**Depends on**: Phase 1
**Requirements**: REFAC-05, REFAC-06
**Success Criteria** (what must be TRUE):
  1. `wc -l app/Services/ExportService.php` affiche <300 lignes
  2. `wc -l app/Services/ValueTranslator.php` affiche <300 lignes
  3. ValueTranslator est une `final class` avec <300 LOC (grep confirme)
**Plans**: 1 plan

Plans:
- [ ] 04-01-PLAN.md — Extract ValueTranslator + refactor ExportService to thin I/O facade

### Phase 5: Refactoring MeetingReportsService
**Goal**: MeetingReportsService est un orchestrateur leger (<300 LOC) qui delegue la generation de rapports a ReportGenerator
**Depends on**: Phase 1
**Requirements**: REFAC-07, REFAC-08
**Success Criteria** (what must be TRUE):
  1. `wc -l app/Services/MeetingReportsService.php` affiche <300 lignes
  2. `wc -l app/Services/ReportGenerator.php` affiche <300 lignes
  3. ReportGenerator est une `final class` avec constructeur DI nullable (grep confirme)
**Plans**: 2 plans

Plans:
- [ ] 05-01: TBD

### Phase 6: Refactoring EmailQueueService
**Goal**: EmailQueueService est un orchestrateur leger (<300 LOC) qui delegue la politique de retry a RetryPolicy
**Depends on**: Phase 1
**Requirements**: REFAC-09, REFAC-10
**Success Criteria** (what must be TRUE):
  1. `wc -l app/Services/EmailQueueService.php` affiche <300 lignes
  2. `wc -l app/Services/RetryPolicy.php` affiche <300 lignes
  3. RetryPolicy est une `final class` avec <300 LOC (grep confirme)
**Plans**: 2 plans

Plans:
- [ ] 06-01: TBD

### Phase 7: Validation Gate
**Goal**: Le milestone est valide -- zero regression sur les routes, les tests unitaires, et les tests E2E
**Depends on**: Phase 2, Phase 3, Phase 4, Phase 5, Phase 6
**Requirements**: GUARD-01, GUARD-02, GUARD-03
**Success Criteria** (what must be TRUE):
  1. `diff` de routes.php entre HEAD et le commit pre-v1.5 montre zero changement (aucune URL publique modifiee)
  2. `php vendor/bin/phpunit --no-coverage` passe au vert (zero failures, zero errors)
  3. `npx playwright test --project=chromium` passe au vert (zero regression)
**Plans**: 2 plans

Plans:
- [ ] 07-01: TBD

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Infrastructure Redis | 2/2 | Complete   | 2026-04-10 | 2026-04-07 |
| 2. Optimisations Memoire et Requetes | v1.0 | 2/2 | Complete | 2026-04-07 |
| 3. Refactoring Controllers et Tests Auth | v1.0 | 3/3 | Complete | 2026-04-07 |
| 4. Tests et Decoupage Controllers | v1.0 | 3/3 | Complete | 2026-04-07 |
| 5. JS Audit et Wiring Repair | v1.1 | 3/3 | Complete | 2026-04-08 |
| 6. Application Design Tokens | v1.1 | 4/4 | Complete | 2026-04-08 |
| 7. Playwright Coverage | v1.1 | 4/4 | Complete | 2026-04-08 |
| 8. Test Infrastructure Docker | v1.2 | 3/3 | Complete | 2026-04-08 |
| 9. Tests E2E par Role | v1.2 | 5/5 | Complete | 2026-04-08 |
| 10. Validation Manuelle Bout-en-Bout | v1.2 | — | Complete | 2026-04-08 |
| 11. Backend Wiring Fixes | v1.2 | 7/7 | Complete | 2026-04-08 |
| 12. Page-by-Page MVP Sweep | v1.2 | 20/21 | Complete | 2026-04-09 |
| 13. MVP Validation Finale | v1.2 | — | Complete | 2026-04-09 |
| 14. Visual Polish | v1.3 | 4/4 | Complete | 2026-04-09 |
| 15. Multi-Browser Tests | v1.3 | — | Complete | 2026-04-09 |
| 16. Accessibility Deep Audit | v1.3 | 5/5 | Complete | 2026-04-09 |
| 17. Loose Ends Phase 12 | v1.3 | 3/3 | Complete | 2026-04-09 |
| 1. Contrast AA Remediation | v1.4 | 3/3 | Complete | 2026-04-10 |
| 2. Overlay Hittest Sweep | v1.4 | 2/2 | Complete | 2026-04-10 |
| 3. Trust Fixtures Deploy | v1.4 | 2/2 | Complete | 2026-04-10 |
| 4. HTMX 2.0 Upgrade | v1.4 | 2/2 | Complete | 2026-04-10 |
| 5. CSP Nonce Enforcement | v1.4 | 2/2 | Complete | 2026-04-10 |
| 6. Controller Refactoring | v1.4 | 3/3 | Complete | 2026-04-10 |
| 1. Nettoyage Codebase | v1.5 | 0/2 | Not started | - |
| 2. Refactoring AuthMiddleware | v1.5 | 0/2 | Not started | - |
| 3. Refactoring ImportService | v1.5 | 0/1 | Not started | - |
| 4. Refactoring ExportService | v1.5 | 0/1 | Not started | - |
| 5. Refactoring MeetingReportsService | v1.5 | 0/1 | Not started | - |
| 6. Refactoring EmailQueueService | v1.5 | 0/1 | Not started | - |
| 7. Validation Gate | v1.5 | 0/1 | Not started | - |
