# Requirements: AG-VOTE

**Defined:** 2026-04-02
**Core Value:** Self-hosted voting platform with legal compliance for French general assemblies

## v8.0 Requirements

Requirements for v8.0 Account & Hardening milestone.

### Account Management

- [ ] **ACCT-01**: L'utilisateur connecte peut voir son profil (nom, email, role) sur une page Mon Compte
- [ ] **ACCT-02**: L'utilisateur connecte peut changer son mot de passe depuis la page Mon Compte (ancien + nouveau + confirmation)

### Security Hardening

- [ ] **SEC-01**: Les operations critiques (suppression utilisateur, reset mot de passe admin) demandent une confirmation 2 etapes
- [ ] **SEC-02**: Le timeout de session est configurable depuis les parametres admin (au lieu de 30 min hardcode)
- [ ] **SEC-03**: Un utilisateur dont la session expire pendant un vote peut reprendre son vote apres re-authentification

### Tech Debt

- [ ] **DEBT-01**: Augmenter la couverture controller au-dela de 64.6% en refactorant les controllers avec exit()
- [ ] **DEBT-02**: admin.js KPI load failure doit afficher un message d'erreur au lieu d'echouer silencieusement
- [ ] **DEBT-03**: Le seed data E2E (04_e2e.sql) est charge dans le job CI e2e
- [ ] **DEBT-04**: Le check d'idempotency des migrations est execute en CI (pas seulement en local)

## Future Requirements

Deferred to later milestones.

- **i18n-01**: Support multi-langue (francais + anglais minimum)
- **SIG-01**: Upload et validation de signature electronique

## Out of Scope

| Feature | Reason |
|---------|--------|
| Multi-langue (i18n) | App mono-langue francais — suffisant pour associations loi 1901 |
| Framework migration | Vanilla PHP + JS est l'identite du projet |
| Mobile native | PWA approach maintenue |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| ACCT-01 | TBD | Pending |
| ACCT-02 | TBD | Pending |
| SEC-01 | TBD | Pending |
| SEC-02 | TBD | Pending |
| SEC-03 | TBD | Pending |
| DEBT-01 | TBD | Pending |
| DEBT-02 | TBD | Pending |
| DEBT-03 | TBD | Pending |
| DEBT-04 | TBD | Pending |
