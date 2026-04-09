# Requirements — Milestone v1.4 : Régler Deferred et Dette Technique

**Created:** 2026-04-09
**Goal:** Éliminer la dette technique reportée de v1.0-v1.3 : design-system contrast, patterns CSS fragiles, debt test/CSP, upgrade HTMX 2.0, et refactoring des controllers volumineux restants.

## v1.4 Requirements

### Contrast AA Remediation (CONTRAST)

- [ ] **CONTRAST-01** : L'application atteint WCAG 2.1 AA contrast 4.5:1 sur toutes les paires fg/bg identifiées dans v1.3-CONTRAST-AUDIT.json (316 nœuds → 0 violation)
- [ ] **CONTRAST-02** : Les tokens modifiés sont propagés dans les critical-tokens inline blocks des 22 .htmx.html dans le même commit que `:root` / `[data-theme="dark"]`
- [ ] **CONTRAST-03** : Les fallbacks hex des Shadow DOM (`var(--token, #hex)`) sont retirés pour les 23 Web Components
- [ ] **CONTRAST-04** : v1.3-A11Y-REPORT.md est mis à jour — conformance WCAG 2.1 AA déclarée (plus "partial")

### Overlay Hittest (OVERLAY)

- [ ] **OVERLAY-01** : Une règle CSS globale `:where([hidden]) { display: none !important }` bloque le conflit `[hidden]` + `display: flex`
- [ ] **OVERLAY-02** : Un audit codebase-wide recense tous les sites `display: flex` sur éléments pouvant recevoir `[hidden]` et documente leur statut
- [ ] **OVERLAY-03** : Un test Playwright smoke vérifie que `[hidden]` → computed `display: none` sur ≥3 pages représentatives

### Trust Fixtures (TRUST)

- [ ] **TRUST-01** : Les helpers Playwright `loginAsAuditor` et `loginAsAssessor` existent et sont fonctionnels dans `tests/e2e/helpers/`
- [ ] **TRUST-02** : Un endpoint de seed test-gated `POST /api/v1/test/seed-user` existe, retourne 404 en production (gate appliqué à route-registration)
- [ ] **TRUST-03** : trust.htmx.html est testé avec fixtures auditor + assessor (plus de fallback `loginAsAdmin`)

### HTMX 2.0 Upgrade (HTMX)

- [ ] **HTMX-01** : htmx.org est mis à jour de 1.x vers 2.0.6 ; `htmx-1-compat` est chargé comme safety net pendant la migration
- [ ] **HTMX-02** : Tous les attributs `hx-on="event: ..."` sont réécrits en `hx-on:event-name="..."` (kebab-case) — grep retourne zéro occurrence de l'ancienne syntaxe
- [ ] **HTMX-03** : Tous les handlers `hx-delete` lisent les paramètres depuis query params (pas `$_POST` / `php://input`)
- [ ] **HTMX-04** : Les extensions HTMX (SSE, preload, ...) sont chargées individuellement (non bundled)
- [ ] **HTMX-05** : La suite Playwright complète passe chromium + firefox + webkit + mobile-chrome contre la baseline v1.3

### CSP Nonce (CSP)

- [ ] **CSP-01** : `SecurityProvider::nonce()` génère un nonce par requête via `random_bytes(16)` et l'injecte dans l'header CSP
- [ ] **CSP-02** : `HtmlView::render()` expose `$cspNonce` au template ; tous les inline theme init scripts portent `nonce="..."`
- [ ] **CSP-03** : La directive CSP `script-src` utilise `'nonce-{NONCE}' 'strict-dynamic'` ; `'unsafe-inline'` est retiré de script-src
- [ ] **CSP-04** : La CSP tourne en report-only pendant ≥1 phase avant enforcement ; un test Playwright vérifie zéro violation console

### Controller Refactoring (CTRL)

- [ ] **CTRL-01** : MeetingsController.php est réduit à <300 lignes (depuis 687) via extraction vers `MeetingLifecycleService` avec DI nullable
- [ ] **CTRL-02** : MeetingWorkflowController.php est réduit à <300 lignes (depuis 559) via extraction vers `MeetingWorkflowService`
- [ ] **CTRL-03** : OperatorController.php est réduit à <300 lignes (depuis 516) via extraction vers `OperatorWorkflowService`
- [ ] **CTRL-04** : AdminController.php est réduit à <300 lignes (depuis 510) via extraction vers `AdminService`
- [ ] **CTRL-05** : Un audit pré-split des tests pour usage `ReflectionClass` / `hasMethod` précède chaque split ; les tests sont réécrits vers les services publics avant le split

## Future Requirements (Deferred)

- MeetingReports et Motions splits au-delà de <300 lignes (déjà < 400 après v1.2)
- HTMX 4.x migration (pré-release en v1.4)
- Role × route coverage matrix complète (auditor/assessor seulement)
- `<dialog>` native migration (hors périmètre)
- CSP `report-uri` avec backend collector
- Visual regression testing (milestone séparé, post-v1.4 pour rebaseline)

## Out of Scope

- **Nouvelles fonctionnalités métier** — milestone 100% dette technique
- **Migration framework (Symfony, Laravel)** — refactoring incrémental uniquement (Constraint PROJECT.md)
- **PDFs de convocation/emargement** — hors périmètre app (feedback user)
- **Raccourcis clavier** — hors périmètre (feedback user)
- **Visual regression snapshots** — milestone séparé après stabilisation des tokens
- **HTMX 4.x** — pre-release, double migration pain
- **Playwright >1.59.1** — invalidate v1.3 cross-browser baseline

## Traceability

(Filled by roadmapper — maps each REQ-ID to its phase)
