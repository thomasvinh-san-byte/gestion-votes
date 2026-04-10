# Roadmap: AgVote

## Milestones

- ✅ **v1.0 Dette Technique** - Phases 1-4 (shipped 2026-04-07) — see `.planning/milestones/v1.0-ROADMAP.md`
- ✅ **v1.1 Coherence UI/UX et Wiring** - Phases 5-7 (shipped 2026-04-08) — see `.planning/milestones/v1.1-ROADMAP.md`
- ✅ **v1.2 Bouclage et Validation Bout-en-Bout** - Phases 8-13 (shipped 2026-04-09) — see `.planning/milestones/v1.2-ROADMAP.md`
- ✅ **v1.3 Polish Post-MVP** - Phases 14-17 (shipped 2026-04-09) — see `.planning/milestones/v1.3-ROADMAP.md`
- 🚧 **v1.4 Régler Deferred et Dette Technique** - Phases 1-6 (started 2026-04-09, reset-phase-numbers active)

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
- Visual polish: toast system unifié, dark mode parity, role-specific sidebar, micro-interactions
- Cross-browser: chromium + firefox + webkit + mobile-chrome matrix (25/25 chromium, 25/25 firefox, 23/25 webkit, 21/25 mobile-chrome)
- Accessibility deep audit: 47 structural violations fixed, keyboard-nav spec (6/6), contrast audit produced (316 nodes DEFERRED to token remediation), WCAG 2.1 AA partial conformance
- Loose ends: settings loadSettings race fixed, eIDAS chip delegation fixed, Phase 12 SUMMARY audit ledger (6 findings, 3 deferred to v2)

**Deferred to v2:**
- CONTRAST-REMEDIATION (316 color-contrast nodes, design-token work)
- V2-OVERLAY-HITTEST (systematic `[hidden]`+flex overlay sweep)
- V2-TRUST-DEPLOY (trust.htmx.html auditor/assessor fixtures)
- V2-CSP-INLINE-THEME (strict CSP compatibility)

</details>

### 🚧 v1.4 Régler Deferred et Dette Technique (Phases 1-6)

**Goal:** Éliminer la dette technique reportée de v1.0-v1.3 : design-system contrast, patterns CSS fragiles, debt test/CSP, upgrade HTMX 2.0, et refactoring des controllers volumineux restants.

**Granularity:** standard | **Requirements:** 24/24 mapped | **Phase numbering:** reset to 1 (v1.3 phases archived to `.planning/milestones/v1.3-phases/`)

- [x] **Phase 1: Contrast AA Remediation** — WCAG 2.1 AA conforme sur 316 nœuds via 4 shifts de tokens oklch dual-theme (completed 2026-04-10)
- [x] **Phase 2: Overlay Hittest Sweep** — Règle base `:where([hidden])!important` + audit codebase-wide du pattern `[hidden]`+`display:flex` (completed 2026-04-10)
- [x] **Phase 3: Trust Fixtures Deploy** — `loginAsAuditor`/`loginAsAssessor` + endpoint seed test-gated pour trust.htmx.html (completed 2026-04-10)
- [ ] **Phase 4: HTMX 2.0 Upgrade** — Migration 1.x→2.0.6, kebab-case `hx-on:*`, extensions unbundled, `htmx-1-compat` safety net
- [ ] **Phase 5: CSP Nonce Enforcement** — `SecurityProvider::nonce()` + `strict-dynamic`, report-only puis enforcement
- [ ] **Phase 6: Controller Refactoring** — 4 controllers >500 LOC splittés via ImportService pattern (<300 LOC chacun)

## Phase Details

### Phase 1: Contrast AA Remediation
**Goal**: L'application atteint WCAG 2.1 AA contrast 4.5:1 sur toutes les paires fg/bg identifiées, déclarée conforme (plus "partial")
**Depends on**: Nothing (first phase of v1.4)
**Requirements**: CONTRAST-01, CONTRAST-02, CONTRAST-03, CONTRAST-04
**Success Criteria** (what must be TRUE):
  1. `tests/e2e/specs/contrast-audit.spec.js` retourne 0 violations sur 22 pages (light + dark mode) contre baseline `v1.3-CONTRAST-AUDIT.json` (316 → 0)
  2. Grep `grep -rE 'var\(--color-[^,)]*,\s*#' public/assets/js/components/` retourne 0 occurrences (fallbacks hex Shadow DOM retirés pour les 23 Web Components)
  3. Un même commit contient la modification de `:root` / `[data-theme="dark"]` dans `design-system.css` ET les 22 blocs `<style id="critical-tokens">` dans `public/*.htmx.html` (enforcement par pre-commit verification)
  4. `v1.3-A11Y-REPORT.md` déclare "WCAG 2.1 AA CONFORME" (plus "partial"), timestamp mis à jour
**Plans**: 3 plans
  - [ ] 01-01-PLAN.md — Baseline audit + 4 token oklch shift (design-system.css + 22 critical-tokens inline blocks, single atomic commit)
  - [ ] 01-02-PLAN.md — Strip hex fallbacks from 23 Web Components Shadow DOM (var(--color-*, #hex) → var(--color-*))
  - [ ] 01-03-PLAN.md — Contrast audit re-run, residual cleanup, A11Y-REPORT → CONFORME

### Phase 2: Overlay Hittest Sweep
**Goal**: Le pattern `[hidden]` + `display:flex` est neutralisé globalement et audité à l'échelle du codebase
**Depends on**: Nothing (parallelizable with Phase 1 — disjoint CSS files)
**Requirements**: OVERLAY-01, OVERLAY-02, OVERLAY-03
**Success Criteria** (what must be TRUE):
  1. Une règle `:where([hidden]) { display: none !important }` existe dans la couche base du design-system et une spec Playwright vérifie `getComputedStyle(el).display === 'none'` sur ≥3 pages représentatives quand `[hidden]` est appliqué
  2. Un document d'audit (`docs/audits/v1.4-overlay-hittest.md` ou équivalent) liste tous les sélecteurs `display: flex|grid|block` sur éléments susceptibles de recevoir `[hidden]` avec leur statut (OK/fixé/n/a)
  3. Aucune régression sur specs Playwright existantes (`keyboard-nav.spec.js`, `page-interactions.spec.js`) — baseline chromium maintenue
**Plans**: 2 plans
Plans:
- [ ] 02-01-PLAN.md — Global :where([hidden]) rule + remove 16 redundant overrides + audit document
- [ ] 02-02-PLAN.md — Playwright hidden-attr smoke spec + regression verification

### Phase 3: Trust Fixtures Deploy
**Goal**: Les rôles auditor et assessor sont testables de bout en bout avec fixtures Playwright réelles, plus de fallback `loginAsAdmin`
**Depends on**: Nothing (additive, parallelizable with Phases 1-2)
**Requirements**: TRUST-01, TRUST-02, TRUST-03
**Success Criteria** (what must be TRUE):
  1. `tests/e2e/helpers/auditor.js` et `tests/e2e/helpers/assessor.js` exportent `loginAsAuditor()` / `loginAsAssessor()` et sont utilisés par au moins un spec qui passe
  2. `POST /api/v1/test/seed-user` retourne 200 en développement/test et 404 en production (gate vérifiable : route conditionnellement enregistrée sur `APP_ENV !== 'production'` dans `app/routes.php`) — un test assert le 404 en mode production
  3. `trust.htmx.html` specs utilisent les nouvelles fixtures : grep `loginAsAdmin` dans `tests/e2e/specs/trust*.spec.js` retourne 0 occurrences
  4. La fixture construit le graphe complet (user → tenant → meeting → meeting-role) — assertée par un smoke test qui navigue et vérifie au moins un élément role-gated visible
**Plans**: 2 plans
Plans:
- [ ] 03-01-PLAN.md — Seed-user endpoint + route-level production gate + auditor/assessor auth fixtures
- [ ] 03-02-PLAN.md — Migrate trust specs from loginAsAdmin/loginAsOperator to loginAsAuditor


### Phase 4: HTMX 2.0 Upgrade
**Goal**: htmx.org migre de 1.x vers 2.0.6 sans régression sur la suite Playwright cross-browser
**Depends on**: Phase 1 (contrast ships before HTMX touches same `.htmx.html` files to minimize merge conflicts)
**Requirements**: HTMX-01, HTMX-02, HTMX-03, HTMX-04, HTMX-05
**Success Criteria** (what must be TRUE):
  1. `public/assets/vendor/htmx.min.js` est la version 2.0.6 et `htmx-1-compat` est chargé explicitement dans le shell HTML comme safety net pendant la migration
  2. Grep `grep -rE 'hx-on="[^:]' public/*.html public/*.htmx.html` retourne 0 occurrences (tous les `hx-on` sont en kebab-case `hx-on:event-name=`)
  3. Tous les handlers DELETE lisent leurs paramètres depuis query string : audit documenté sur l'ensemble des endpoints `hx-delete`, aucun n'utilise `$_POST` / `php://input`
  4. Les extensions HTMX (SSE, preload) sont chargées comme scripts individuels dans le shell HTML — plus de bundle monolithique
  5. Playwright full suite passe chromium + firefox + webkit + mobile-chrome contre baseline v1.3 (ou explicit rationale documenté pour toute régression pré-existante)
Plans:
- [ ] 04-01-PLAN.md — htmx 2.0.6 vendor swap + compat safety net + DELETE param migration (JS+PHP)
- [ ] 04-02-PLAN.md — Cross-browser Playwright regression validation (chromium+firefox+webkit+mobile-chrome)


### Phase 5: CSP Nonce Enforcement
**Goal**: Les scripts inline theme init portent des nonces CSP ; `'unsafe-inline'` est retiré de `script-src` après une période report-only
**Depends on**: Phase 4 (HTMX `hx-on:*` must be migrated before strict CSP can enforce without silently breaking inline event handlers)
**Requirements**: CSP-01, CSP-02, CSP-03, CSP-04
**Success Criteria** (what must be TRUE):
  1. `SecurityProvider::nonce()` existe comme accesseur statique request-scoped, génère via `random_bytes(16)`, et est injecté dans l'header `Content-Security-Policy` par `SecurityProvider::headers()` avant dispatch router
  2. `HtmlView::render()` expose `$cspNonce` aux templates ; tous les `<script>` et `<style>` inline des 22 `.htmx.html` portent `nonce="<?= $cspNonce ?>"` (grep : 0 inline sans nonce)
  3. La directive CSP `script-src` contient `'nonce-{NONCE}' 'strict-dynamic'` et ne contient plus `'unsafe-inline'` (header assertion dans un spec)
  4. La CSP a tourné en `Content-Security-Policy-Report-Only` pendant ≥1 phase complète avant le flip en enforcement ; un spec Playwright écoute `page.on('pageerror')` + `page.on('console')` et assert zéro violation CSP sur les 22 pages
**Plans**: 2 plans
Plans:
- [ ] 02-01-PLAN.md — Global :where([hidden]) rule + remove 16 redundant overrides + audit document
- [ ] 02-02-PLAN.md — Playwright hidden-attr smoke spec + regression verification

### Phase 6: Controller Refactoring
**Goal**: Les 4 controllers >500 LOC sont réduits à <300 LOC via extraction vers des services finaux avec DI nullable, sans casser les URLs publiques ni les tests existants
**Depends on**: Nothing structural (parallelizable Plane B). Pre-split reflection audit is mandatory entry gate.
**Requirements**: CTRL-01, CTRL-02, CTRL-03, CTRL-04, CTRL-05
**Success Criteria** (what must be TRUE):
  1. `wc -l` sur `app/Controller/MeetingsController.php`, `MeetingWorkflowController.php`, `OperatorController.php`, `AdminController.php` retourne chacun <300 lignes
  2. Quatre nouveaux services existent : `MeetingLifecycleService`, `MeetingWorkflowService`, `OperatorWorkflowService`, `AdminService` — chacun `final class`, constructeur avec paramètres `?Type = null` resolved via `RepositoryFactory::getInstance()`, chacun ≤300 LOC (no god-service)
  3. Un audit pré-split documenté existe pour chaque controller cible : grep `ReflectionClass|hasMethod|getMethod` dans les tests associés, résultats listés, tests réécrits vers l'API publique des services AVANT le split (vérifiable dans git log — commits de tests précèdent les commits de split)
  4. Aucune URL publique ne change : `app/routes.php` ne modifie que la classe handler ; suite PHPUnit ciblée sur les tests des 4 controllers passe au vert ; spec Playwright critical-path reste green
  5. Chaque service a au moins un test unitaire avec mock `RepositoryFactory` démontrant le pattern DI nullable (minimum 1 test par service)
**Plans**: 2 plans
Plans:
- [ ] 02-01-PLAN.md — Global :where([hidden]) rule + remove 16 redundant overrides + audit document
- [ ] 02-02-PLAN.md — Playwright hidden-attr smoke spec + regression verification

## Build Order Rationale (v1.4)

Reconciled from `research/SUMMARY.md` and `research/ARCHITECTURE.md`:

1. **Phase 1 first** — Contrast touches only `<style>` blocks in 22 `.htmx.html` files; HTMX 2.0 (Phase 4) touches attributes on elements. Disjoint regions → zero merge conflict if contrast ships first. 4 token value edits fix ~71% of 316 nodes; dual-theme (`:root` + `[data-theme="dark"]`) must ship in same commit.
2. **Phases 2 & 3 parallelizable** — Both independent (CSS-only and additive-only respectively). Can land any time after or alongside Phase 1.
3. **Phase 4 before Phase 5** — HTMX 2.0's `hx-on:*` syntax is inline event handler script. CSP `strict-dynamic` enforcement would silently break un-migrated `hx-on` handlers. HTMX migration must complete first. Full `hx-on` grep inventory required before rewrite.
4. **Phase 5 report-only gate** — CSP runs in `Content-Security-Policy-Report-Only` for at least one phase before enforcement flip to catch missed inline scripts empirically. Use `strict-dynamic` + `unsafe-hashes` strategy.
5. **Phase 6 Plane B** — Pure PHP, zero file overlap with Planes A/C. Can parallelize with any earlier phase. Pre-split reflection audit on tests is hard entry gate (pitfall #6). 300 LOC ceiling per extracted service.

## Coverage Validation (v1.4)

| REQ-ID | Phase | Category |
|--------|-------|----------|
| CONTRAST-01 | 1 | 3/3 | Complete   | 2026-04-10 | 1 | Contrast |
| CONTRAST-03 | 1 | Contrast |
| CONTRAST-04 | 1 | Contrast |
| OVERLAY-01 | 2 | 2/2 | Complete   | 2026-04-10 | 2 | Overlay |
| OVERLAY-03 | 2 | Overlay |
| TRUST-01 | 3 | 2/2 | Complete   | 2026-04-10 | 3 | Trust |
| TRUST-03 | 3 | Trust |
| HTMX-01 | 4 | HTMX |
| HTMX-02 | 4 | HTMX |
| HTMX-03 | 4 | HTMX |
| HTMX-04 | 4 | HTMX |
| HTMX-05 | 4 | HTMX |
| CSP-01 | 5 | CSP |
| CSP-02 | 5 | CSP |
| CSP-03 | 5 | CSP |
| CSP-04 | 5 | CSP |
| CTRL-01 | 6 | Controller |
| CTRL-02 | 6 | Controller |
| CTRL-03 | 6 | Controller |
| CTRL-04 | 6 | Controller |
| CTRL-05 | 6 | Controller |

**Coverage:** 24/24 ✓ — No orphaned requirements, no duplicates.

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Infrastructure Redis | v1.0 | 2/2 | Complete | 2026-04-07 |
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
| 1. Contrast AA Remediation | v1.4 | 0/3 | Planned | - |
| 2. Overlay Hittest Sweep | v1.4 | 0/0 | Not started | - |
| 3. Trust Fixtures Deploy | v1.4 | 0/2 | Planned | - |
| 4. HTMX 2.0 Upgrade | v1.4 | 0/2 | Planned | - |
| 5. CSP Nonce Enforcement | v1.4 | 0/0 | Not started | - |
| 6. Controller Refactoring | v1.4 | 0/0 | Not started | - |
