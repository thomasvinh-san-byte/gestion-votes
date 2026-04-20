# Requirements: AgVote v1.6

**Defined:** 2026-04-20
**Core Value:** L'application doit etre fiable en production — aucun crash lie a des fallbacks fichiers, des fuites memoire, ou des timeouts silencieux.

## v1.6 Requirements

Requirements for this milestone. Each maps to roadmap phases.

### JS Interaction Audit

- [ ] **JSFIX-01**: Audit systematique des 21 pages HTMX — chaque page doit charger sans erreur console JS
- [ ] **JSFIX-02**: Tous les boutons d'action (CRUD, navigation, modales) repondent au clic
- [ ] **JSFIX-03**: Tous les formulaires soumettent correctement via HTMX (pas de rechargement page)
- [ ] **JSFIX-04**: Les evenements SSE (temps reel) fonctionnent sur les pages operator/vote

### Form Modernization

- [ ] **FORM-01**: Formulaires utilisent des layouts multi-colonnes (2-3 colonnes sur ecrans >1024px)
- [ ] **FORM-02**: Champs de formulaire compacts et modernes (inputs, selects, textareas uniformises)
- [ ] **FORM-03**: Aucun formulaire ne depasse 60% de la largeur disponible en layout mono-colonne

### Wizard

- [ ] **WIZ-01**: L'assistant de creation de seance tient sur un seul viewport sans scroll vertical

### Validation

- [ ] **VALID-01**: Chaque page HTMX verifiee bout-en-bout dans un navigateur (pas de regression visuelle ni fonctionnelle)

## Future Requirements

Deferred to next milestone.

### CSP Hardening
- **CSP-FLIP**: CSP report-only flip vers enforcement (retirer unsafe-inline de script-src et style-src)
- **CSP-STYLE**: Migrer inline styles JS vers classes CSS pour eliminer style-src unsafe-inline

### JS Modularization
- **JSMOD-01**: operator-tabs.js (3534 LOC) decompose en modules ES6
- **JSMOD-02**: vote.js (1473 LOC) decompose en modules ES6

### Tests
- **TEST-TENANT**: E2E isolation multi-tenant
- **TEST-IDEMPOTENCY**: Audit idempotency sur les routes POST/PATCH

## Out of Scope

| Feature | Reason |
|---------|--------|
| Pixel-perfect design polish | Fonctionnel d'abord, joli apres |
| JS modularization (ES6 modules) | Trop volumineux — v1.7 |
| CSP enforcement flip | Necessite validation production d'abord |
| Nouvelles fonctionnalites metier | Stabiliser d'abord |
| Dark mode refinements | Polish visuel, pas fonctionnel |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| JSFIX-01 | Phase 1 | Pending |
| JSFIX-02 | Phase 1 | Pending |
| JSFIX-03 | Phase 1 | Pending |
| JSFIX-04 | Phase 1 | Pending |
| FORM-01 | Phase 2 | Pending |
| FORM-02 | Phase 2 | Pending |
| FORM-03 | Phase 2 | Pending |
| WIZ-01 | Phase 3 | Pending |
| VALID-01 | Phase 4 | Pending |

**Coverage:**
- v1.6 requirements: 9 total
- Mapped to phases: 9/9
- Unmapped: 0

---
*Requirements defined: 2026-04-20*
