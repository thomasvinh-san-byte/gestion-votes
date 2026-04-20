# Requirements: AgVote v1.8

**Defined:** 2026-04-20
**Core Value:** L'application doit etre fiable en production — aucun crash lie a des fallbacks fichiers, des fuites memoire, ou des timeouts silencieux.

## v1.8 Requirements

Requirements for this milestone. Each maps to roadmap phases.

### Palette et Tokens

- [x] **UI-01**: Palette de fond passe de beige/parchment (#EDECE6) a gris neutre (#f8f9fa ou similaire)
- [x] **UI-02**: Tokens couleur text/border migrent de stone/warm a slate/cool
- [x] **UI-03**: Persona colors migrent de hex brut Tailwind vers le systeme oklch

### Classes CSS

- [ ] **UI-04**: Wizard migre de field-input vers form-input/form-select sur tous les champs
- [ ] **UI-05**: Inline styles elimines — 42 occurrences remplacees par des classes CSS
- [ ] **UI-06**: Shell.js drawer inline styles remplaces par des classes design-system

### Coherence Cross-Pages

- [ ] **UI-07**: Version unique sur toutes les pages (supprimer v3.19/v4.3/v4.4/v5.0, source unique)
- [ ] **UI-08**: Footer "Accessibilite" corrige en "Accessibilité" sur 13 pages
- [ ] **UI-09**: Modales consolidees en un seul pattern (modal-backdrop + modal role=dialog)

### Layout

- [ ] **UI-10**: Landing page hero compact — contenu visible sans scroll sur 1080p
- [ ] **UI-11**: Radio buttons type de seance remplaces par select (operator + meetings + wizard)
- [ ] **UI-12**: KPI dead code supprime (definition design-system.css non fonctionnelle)

### Validation

- [ ] **UI-13**: Zero inline style residuel, zero classe non-standard (field-input), version unique confirmee

## Future Requirements

Deferred to next milestone.

### CSP Hardening
- **CSP-FLIP**: CSP report-only flip vers enforcement
- **CSP-STYLE**: Migrer inline styles JS vers classes CSS

### JS Modularization
- **JSMOD-01**: operator-tabs.js (3534 LOC) decompose en modules ES6
- **JSMOD-02**: vote.js (1473 LOC) decompose en modules ES6

### Accessibilite Avancee
- **A11Y-ARIA**: aria-labelledby vides sur settings tabpanels
- **A11Y-MODAL**: Unifier role=dialog sur toutes les modales
- **A11Y-TABS**: role=tab/aria-selected sur analytics et help

## Out of Scope

| Feature | Reason |
|---------|--------|
| Dark mode refinements | Le dark mode existe, les tokens se transposeront automatiquement |
| Refonte complete landing page | Hero compact suffit, pas de redesign complet |
| Accessibilite ARIA avancee | Defere a v1.9 — fonctionnel d'abord |
| Animation/transitions CSS | Polish visuel, pas fonctionnel |
| Responsive mobile < 768px | Les breakpoints existants suffisent |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| UI-01 | Phase 1 | Complete |
| UI-02 | Phase 1 | Complete |
| UI-03 | Phase 1 | Complete |
| UI-04 | Phase 2 | Pending |
| UI-05 | Phase 2 | Pending |
| UI-06 | Phase 2 | Pending |
| UI-07 | Phase 3 | Pending |
| UI-08 | Phase 3 | Pending |
| UI-09 | Phase 3 | Pending |
| UI-10 | Phase 4 | Pending |
| UI-11 | Phase 4 | Pending |
| UI-12 | Phase 4 | Pending |
| UI-13 | Phase 5 | Pending |

**Coverage:**
- v1.8 requirements: 13 total
- Mapped to phases: 13/13
- Unmapped: 0

---
*Requirements defined: 2026-04-20*
*Traceability updated: 2026-04-20*
