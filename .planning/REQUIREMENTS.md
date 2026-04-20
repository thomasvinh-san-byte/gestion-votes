# Requirements: AgVote v1.7

**Defined:** 2026-04-20
**Core Value:** L'application doit etre fiable en production -- aucun crash lie a des fallbacks fichiers, des fuites memoire, ou des timeouts silencieux.

## v1.7 Requirements

Requirements for this milestone. Each maps to roadmap phases.

### Audit

- [x] **IDEM-01**: Inventaire complet des routes POST/PATCH/DELETE avec leur niveau de protection actuel (IdempotencyGuard, UNIQUE constraint, CSRF seul)
- [x] **IDEM-02**: Chaque route classee par risque (critique/moyen/bas) selon l'impact d'un doublon

### Protection des routes critiques

- [ ] **IDEM-03**: Les routes de creation sans contrainte UNIQUE utilisent IdempotencyGuard (email_templates, export_templates, member_groups, reminders, attachments, resolution_documents)
- [ ] **IDEM-04**: Les routes d'import bulk (members_bulk) resistent au double-submit
- [ ] **IDEM-05**: Les transitions de workflow (demarrer/cloturer seance) sont idempotentes

### Frontend

- [ ] **IDEM-06**: Les boutons de soumission HTMX envoient un header X-Idempotency-Key unique par action

### Validation

- [ ] **IDEM-07**: Tests unitaires verifiant que IdempotencyGuard rejette les requetes dupliquees

## Future Requirements

Deferred to next milestone.

### CSP Hardening
- **CSP-FLIP**: CSP report-only flip vers enforcement
- **CSP-STYLE**: Migrer inline styles JS vers classes CSS

### JS Modularization
- **JSMOD-01**: operator-tabs.js (3534 LOC) decompose en modules ES6
- **JSMOD-02**: vote.js (1473 LOC) decompose en modules ES6

### Tests
- **TEST-TENANT**: E2E isolation multi-tenant

## Out of Scope

| Feature | Reason |
|---------|--------|
| Idempotence sur les routes GET/OPTIONS | Pas de mutation, pas de risque de doublon |
| Rate limiting par route | Deja en place via RateLimitGuard |
| Retry automatique cote client | Complexite frontend excessive pour ce milestone |
| Refactoring IdempotencyGuard (storage DB) | Redis suffit, fallback silencieux acceptable |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| IDEM-01 | Phase 1 | Complete |
| IDEM-02 | Phase 1 | Complete |
| IDEM-03 | Phase 2 | Pending |
| IDEM-04 | Phase 2 | Pending |
| IDEM-05 | Phase 2 | Pending |
| IDEM-06 | Phase 3 | Pending |
| IDEM-07 | Phase 3 | Pending |

**Coverage:**
- v1.7 requirements: 7 total
- Mapped to phases: 7/7
- Unmapped: 0

---
*Requirements defined: 2026-04-20*
*Traceability updated: 2026-04-20*
