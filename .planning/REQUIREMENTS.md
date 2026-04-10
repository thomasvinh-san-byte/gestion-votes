# Requirements: AgVote v1.5

**Defined:** 2026-04-10
**Core Value:** L'application doit etre fiable en production — aucun crash lie a des fallbacks fichiers, des fuites memoire, ou des timeouts silencieux.

## v1.5 Requirements

Requirements for this milestone. Each maps to roadmap phases.

### Nettoyage JS

- [ ] **CLEAN-01**: Zero console.log/warn/error dans le JS de production (hors error handlers critiques)
- [ ] **CLEAN-02**: Zero code deprecie dans le codebase (PermissionChecker supprime, VoteTokenService deprecated methods supprimes)
- [ ] **CLEAN-03**: Zero TODO/FIXME dans les fichiers CSS et JS de production
- [ ] **CLEAN-04**: Test unitaire PageController couvrant nonce injection et 404

### Nettoyage PHP

- [ ] **CLEAN-05**: Zero $_GET/$_POST/$_REQUEST direct — tous migres vers Request::query()/Request::body() ou api_query()/api_request()

### Refactoring AuthMiddleware

- [ ] **REFAC-01**: AuthMiddleware <300 LOC apres extraction de SessionManager et RbacEngine
- [ ] **REFAC-02**: SessionManager et RbacEngine sont des final class avec DI nullable, chacun <300 LOC

### Refactoring ImportService

- [ ] **REFAC-03**: ImportService <300 LOC apres extraction des importers CSV/XLSX
- [ ] **REFAC-04**: CsvImporter et XlsxImporter sont des final class avec DI nullable, chacun <300 LOC

### Refactoring ExportService

- [ ] **REFAC-05**: ExportService <300 LOC apres extraction de ValueTranslator
- [ ] **REFAC-06**: ValueTranslator est une final class <300 LOC

### Refactoring MeetingReportsService

- [ ] **REFAC-07**: MeetingReportsService <300 LOC apres extraction de ReportGenerator
- [ ] **REFAC-08**: ReportGenerator est une final class avec DI nullable <300 LOC

### Refactoring EmailQueueService

- [ ] **REFAC-09**: EmailQueueService <300 LOC apres extraction de RetryPolicy
- [ ] **REFAC-10**: RetryPolicy est une final class <300 LOC

### Garde-fous

- [ ] **GUARD-01**: Aucune URL publique ne change (routes.php inchange)
- [ ] **GUARD-02**: Suite PHPUnit complete passe au vert
- [ ] **GUARD-03**: Suite Playwright chromium passe au vert (zero regression)

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
| Migration vers framework (Symfony/Laravel) | Refactoring incremental uniquement |
| JS modularization complete | Trop volumineux pour ce milestone — v1.6 |
| CSP enforcement flip | Necessite validation production d'abord |
| Visual regression testing | Outil a choisir — milestone separe |
| Nouvelles fonctionnalites metier | Stabiliser d'abord |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| CLEAN-01 | TBD | Pending |
| CLEAN-02 | TBD | Pending |
| CLEAN-03 | TBD | Pending |
| CLEAN-04 | TBD | Pending |
| CLEAN-05 | TBD | Pending |
| REFAC-01 | TBD | Pending |
| REFAC-02 | TBD | Pending |
| REFAC-03 | TBD | Pending |
| REFAC-04 | TBD | Pending |
| REFAC-05 | TBD | Pending |
| REFAC-06 | TBD | Pending |
| REFAC-07 | TBD | Pending |
| REFAC-08 | TBD | Pending |
| REFAC-09 | TBD | Pending |
| REFAC-10 | TBD | Pending |
| GUARD-01 | TBD | Pending |
| GUARD-02 | TBD | Pending |
| GUARD-03 | TBD | Pending |

**Coverage:**
- v1.5 requirements: 18 total
- Mapped to phases: 0 (pending roadmap creation)
- Unmapped: 18

---
*Requirements defined: 2026-04-10*
*Last updated: 2026-04-10 after initial definition*
