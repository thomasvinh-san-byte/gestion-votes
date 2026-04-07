# Requirements: AgVote v1.1

**Defined:** 2026-04-07
**Core Value:** L'application doit etre fiable en production — aucun crash lie a des fallbacks fichiers, des fuites memoire, ou des timeouts silencieux.

## v1.1 Requirements

Requirements pour le milestone Coherence UI/UX et Wiring. Chaque requirement mappe a une phase du roadmap.

### Wiring

- [x] **WIRE-01**: Inventaire de tous les contrats ID (querySelector/getElementById) et verification vs HTML actuel
- [x] **WIRE-02**: Reparation de tous les fetch handlers et event handlers casses sur toutes les pages
- [ ] **WIRE-03**: Reparation du timing sidebar async (shared.js, shell.js, auth-ui.js)
- [ ] **WIRE-04**: Helper Playwright waitForHtmxSettled() pour eviter les race conditions HTMX

### Design

- [ ] **DESIGN-01**: Application uniforme des design tokens de design-system.css sur tous les fichiers CSS par page
- [ ] **DESIGN-02**: Login 2-panels — branding a gauche, formulaire a droite, responsive <768px
- [ ] **DESIGN-03**: Loading states CSS pour .htmx-request — feedback visuel pendant les chargements
- [ ] **DESIGN-04**: Status badges avec couleurs semantiques (actif, ferme, archive, en cours, etc.)

### Tests

- [ ] **TEST-01**: Tous les specs Playwright existants passent sans erreur (baseline green)
- [ ] **TEST-02**: Tests d interaction par page — load + click bouton principal + assertion DOM
- [ ] **TEST-03**: Upgrade Playwright 1.58 vers 1.59.1 + ajout @axe-core/playwright
- [ ] **TEST-04**: Workflow complet operateur en test end-to-end Playwright

## v2 Requirements

Reporte au prochain milestone.

### Polish

- **POLISH-01**: Systeme de notifications toast pour feedback utilisateur
- **POLISH-02**: Audit et correction du mode sombre sur toutes les pages
- **POLISH-03**: Navigation sidebar adaptee par role

### Refactoring Backend

- **REFAC-01**: Brancher getDashboardStats() dans DashboardController
- **REFAC-02**: Decouper MeetingReportsController (727 lignes)
- **REFAC-03**: Decouper MotionsController (720 lignes)

## Out of Scope

| Feature | Reason |
|---------|--------|
| HTMX 2.0 upgrade | Breaking changes (hx-on case sensitivity) necessitent audit complet — v1.2+ |
| Horizontal-first layout refactor | Refonte majeure, reporter apres stabilisation |
| Nouveau framework CSS (Tailwind, Bootstrap) | Conflit avec design-system.css OKLCH existant |
| Migration SPA (React, Vue) | Architecture HTMX server-driven est le bon choix |
| PDFs de convocation/emargement | Hors perimetre de l app |
| Raccourcis clavier | Hors perimetre |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| WIRE-01 | Phase 5 | Complete |
| WIRE-02 | Phase 5 | Complete |
| WIRE-03 | Phase 5 | Pending |
| WIRE-04 | Phase 5 | Pending |
| DESIGN-01 | Phase 6 | Pending |
| DESIGN-02 | Phase 6 | Pending |
| DESIGN-03 | Phase 6 | Pending |
| DESIGN-04 | Phase 6 | Pending |
| TEST-01 | Phase 7 | Pending |
| TEST-02 | Phase 7 | Pending |
| TEST-03 | Phase 7 | Pending |
| TEST-04 | Phase 7 | Pending |

**Coverage:**
- v1.1 requirements: 12 total
- Mapped to phases: 12
- Unmapped: 0

---
*Requirements defined: 2026-04-07*
*Last updated: 2026-04-07 after roadmap v1.1 creation*
