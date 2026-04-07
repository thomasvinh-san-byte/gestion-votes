# Roadmap: AgVote

## Milestones

- ✅ **v1.0 Dette Technique** - Phases 1-4 (shipped 2026-04-07)
- 🚧 **v1.1 Coherence UI/UX et Wiring** - Phases 5-7 (in progress)

## Phases

<details>
<summary>✅ v1.0 Dette Technique (Phases 1-4) - SHIPPED 2026-04-07</summary>

### Phase 1: Infrastructure Redis
**Goal**: Redis est le seul broker — aucun fallback fichier en production
**Plans**: 2 plans

Plans:
- [x] 01-01: SSE Redis-only, rate-limiting Lua atomique, detection heartbeat
- [x] 01-02: Health check Redis au boot

### Phase 2: Optimisations Memoire et Requetes
**Goal**: Aucune fuite memoire ni timeout silencieux sur les operations lourdes
**Plans**: 2 plans

Plans:
- [x] 02-01: PDO timeouts + getDashboardStats single-query + streaming XLSX
- [x] 02-02: Email batch processing pagine

### Phase 3: Refactoring Controllers et Tests Auth
**Goal**: ImportController orchestre uniquement — logique metier dans ImportService
**Plans**: 3 plans

Plans:
- [x] 03-01: Extraction ImportService
- [x] 03-02: Tests AuthMiddleware lifecycle
- [x] 03-03: Tests RgpdExportController

### Phase 4: Tests et Decoupage Controllers
**Goal**: Couverture tests SSE et import fuzzy matching
**Plans**: 3 plans

Plans:
- [x] 04-01: Tests SSE EventBroadcaster race conditions
- [x] 04-02: Tests ImportService fuzzy matching aliases
- [x] 04-03: Tests SSE connection loss + client reconnection

</details>

### 🚧 v1.1 Coherence UI/UX et Wiring (In Progress)

**Milestone Goal:** Rendre l'application professionnelle, moderne et coherente — design language uniforme, wiring JS reparé, zero bugs evidents via tests Playwright.

- [ ] **Phase 5: JS Audit et Wiring Repair** - Inventaire des contrats DOM + reparation de tous les handlers et du timing sidebar
- [ ] **Phase 6: Application Design Tokens** - Login 2-panels, design tokens uniformes, loading states, status badges
- [ ] **Phase 7: Playwright Coverage** - Baseline verte, tests d'interaction par page, upgrade 1.59.1, workflow operateur E2E

## Phase Details

### Phase 5: JS Audit et Wiring Repair
**Goal**: Chaque page charge sans erreur JS console et chaque bouton principal declenche l'action attendue
**Depends on**: Phase 4 (v1.0 complete)
**Requirements**: WIRE-01, WIRE-02, WIRE-03, WIRE-04
**Success Criteria** (what must be TRUE):
  1. Un inventaire des contrats ID (querySelector/getElementById) existe et est verifie contre le HTML actuel — aucun selector orphelin documente
  2. Toutes les pages chargent sans erreur JavaScript dans la console (fetch handlers, event listeners)
  3. Le timing de la sidebar async (shared.js, shell.js, auth-ui.js) fonctionne sans flash ni echec silencieux
  4. Le helper waitForHtmxSettled() est disponible dans les specs Playwright et elimine les race conditions HTMX
**Plans**: TBD

### Phase 6: Application Design Tokens
**Goal**: L'application a un design language uniforme et professionnel visible sur toutes les pages cles
**Depends on**: Phase 5
**Requirements**: DESIGN-01, DESIGN-02, DESIGN-03, DESIGN-04
**Success Criteria** (what must be TRUE):
  1. La page login s'affiche en 2 panels (branding gauche, formulaire droit) et bascule en colonne unique sous 768px
  2. Toutes les pages utilisent les design tokens de design-system.css — aucune valeur de couleur ou d'espacement codee en dur dans les CSS par-page
  3. Un indicateur de chargement CSS s'affiche sur .htmx-request pendant chaque appel reseau
  4. Les badges de statut (actif, ferme, archive, en cours) ont des couleurs semantiques coherentes sur toutes les pages
**Plans**: TBD

### Phase 7: Playwright Coverage
**Goal**: Toute regression visible dans un vrai navigateur est detectee par la suite Playwright
**Depends on**: Phase 6
**Requirements**: TEST-01, TEST-02, TEST-03, TEST-04
**Success Criteria** (what must be TRUE):
  1. Les 18 specs Playwright existants passent tous sans erreur (baseline verte, zero flaky)
  2. Chaque page cle a un test d'interaction : chargement + clic bouton principal + assertion changement DOM
  3. Playwright est mis a jour vers 1.59.1 et @axe-core/playwright est integre avec au moins un audit d'accessibilite par page
  4. Un test E2E couvre le workflow complet operateur : connexion → creation reunion → ajout membres → lancement vote → cloture
**Plans**: TBD

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Infrastructure Redis | v1.0 | 2/2 | Complete | 2026-04-07 |
| 2. Optimisations Memoire et Requetes | v1.0 | 2/2 | Complete | 2026-04-07 |
| 3. Refactoring Controllers et Tests Auth | v1.0 | 3/3 | Complete | 2026-04-07 |
| 4. Tests et Decoupage Controllers | v1.0 | 3/3 | Complete | 2026-04-07 |
| 5. JS Audit et Wiring Repair | v1.1 | 0/? | Not started | - |
| 6. Application Design Tokens | v1.1 | 0/? | Not started | - |
| 7. Playwright Coverage | v1.1 | 0/? | Not started | - |
