# Requirements: AgVote v1.2 — Bouclage et Validation Bout-en-Bout

**Defined:** 2026-04-08
**Core Value:** L'application doit etre fiable en production — aucun crash lie a des fallbacks fichiers, des fuites memoire, ou des timeouts silencieux.

## v1.2 Requirements

Requirements pour le milestone Bouclage. Stop aux ajouts, focus sur la validation que TOUT fonctionne ensemble pour TOUS les roles.

### Infrastructure de Test

- [x] **INFRA-01**: Container Docker avec libatk + browsers Playwright preinstalles, capable de lancer la suite E2E complete
- [x] **INFRA-02**: Script bin/test-e2e.sh qui lance Playwright dans le container et retourne le rapport
- [x] **INFRA-03**: Baseline verte sur tous les specs existants + page-interactions + operator-e2e (de v1.1)

### Tests E2E par Role

- [x] **E2E-01**: Test critical path admin: login - settings - users management - audit - logout
- [x] **E2E-02**: Test critical path operator: login - creer assemblee - ajouter membres - lancer vote - cloturer - rapport
- [x] **E2E-03**: Test critical path president: login - assemblee active - ouvrir vote - modifier quorum - cloturer
- [x] **E2E-04**: Test critical path votant: vote token - page de vote - soumettre - confirmation

### Validation Manuelle (UAT)

- [ ] **UAT-01**: Checklist de parcours manuel par role (4 checklists), executee dans un vrai browser
- [ ] **UAT-02**: Checklist par page-cle (dashboard, hub, meetings, members, operator, vote, settings, admin) - chaque interaction principale verifiee
- [ ] **UAT-03**: Rapport de validation finale documentant ce qui marche et ce qui ne marche pas, par role et par page

### Reparation des Regressions

- [x] **FIX-01**: Reparer toutes les regressions decouvertes pendant E2E ou UAT (scope determine pendant l'execution)
- [ ] **FIX-02**: Resolution des 11 items 'human verification deferred' de v1.1 (3 phase 5, 4 phase 6, 4 phase 7)

### Dette Technique v1.0

- [ ] **DEBT-01**: getDashboardStats() branche dans DashboardController (perf gain v1.0 unrealized)
- [ ] **DEBT-02**: MeetingReportsController (727 lignes) decoupe en service + controller mince
- [ ] **DEBT-03**: MotionsController (720 lignes) decoupe en service + controller mince

### MVP — 3 criteres stricts par page (escalade scope)

- [ ] **MVP-01** (Width): Chaque page applicative utilise la largeur totale de l'ecran (pas de max-width artificielle). Pages de contenu (docs, help) restent contraintes a ~80ch pour la lisibilite. Verifie par grep CSS sur les 21 pages.
- [ ] **MVP-02** (Design Language): Aucun hex/oklch literal dans le CSS d'aucune page applicative. Tout en `var(--*)` du design-system.css. Verifie par `! grep -nE 'oklch\(|#[0-9a-f]{6}' public/assets/css/*.css` sur les 21 fichiers CSS par-page.
- [ ] **MVP-03** (Function): Chaque page-cle a un test Playwright `critical-path-{page}.spec.js` qui pour CHAQUE bouton/input/lien principal declenche l'interaction et assert un changement d'etat reel (DOM update OU API 2xx OU DB persisted state OU comportement metier verifie). Plus jamais "shows cards = done".

### Validation Finale MVP

- [ ] **VAL-01**: `bin/test-e2e.sh` (full suite) retourne exit 0 sans flake (3 runs consecutifs identiques)
- [ ] **VAL-02**: UAT manuel par les 4 roles PASS, zero blocker, zero gap critique. Rapport `.planning/v1.2-MVP-VALIDATION.md` produit avec verdict ship.

## v2 Requirements

Reporte au prochain milestone (post-bouclage):

### Polish UI/UX

- **POLISH-01**: Systeme de notifications toast pour feedback utilisateur
- **POLISH-02**: Audit et correction du mode sombre sur toutes les pages
- **POLISH-03**: Navigation sidebar adaptee par role

### Test Infrastructure

- **TEST-FUTURE-01**: Tests d'accessibilite axe-core etendus (a11y baseline complete)
- **TEST-FUTURE-02**: Tests de performance (lighthouse, web vitals)

## Out of Scope

| Feature | Reason |
|---------|--------|
| Nouvelles fonctionnalites metier | Bouclage = stop aux ajouts, on valide ce qui existe |
| Refonte majeure (framework, architecture) | Hors scope strict, focus stabilisation |
| Migration SPA / Tailwind / autres tooling | Stack actuelle est le bon choix |
| HTMX 2.0 upgrade | Hors scope, breaking changes a evaluer separement |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| INFRA-01 | Phase 8 | Complete |
| INFRA-02 | Phase 8 | Complete |
| INFRA-03 | Phase 8 | Complete |
| E2E-01 | Phase 9 | Complete |
| E2E-02 | Phase 9 | Complete |
| E2E-03 | Phase 9 | Complete |
| E2E-04 | Phase 9 | Complete |
| UAT-01 | Phase 10 | Pending |
| UAT-02 | Phase 10 | Pending |
| UAT-03 | Phase 10 | Pending |
| FIX-01 | Phase 11 | Complete |
| FIX-02 | Phase 10 | Pending |
| DEBT-01 | Phase 11 | Pending |
| DEBT-02 | Phase 11 | Pending |
| DEBT-03 | Phase 11 | Pending |
| MVP-01 | Phase 12 | Pending |
| MVP-02 | Phase 12 | Pending |
| MVP-03 | Phase 12 | Pending |
| VAL-01 | Phase 13 | Pending |
| VAL-02 | Phase 13 | Pending |

**Coverage:**
- v1.2 requirements: 20 total (15 original + 5 escalated MVP/VAL)
- Mapped to phases: 20
- Unmapped: 0

---
*Requirements defined: 2026-04-08*
*Last updated: 2026-04-08 after initial definition*
