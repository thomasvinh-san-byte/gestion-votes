# Requirements: AG-VOTE v5.0

**Defined:** 2026-03-30
**Core Value:** Self-hosted voting platform with legal compliance — production-ready with 90%+ test coverage

## v5.0 Requirements

### Unit Tests — Services (TEST)

- [x] **TEST-01**: QuorumEngine has unit tests covering quorum calculation, threshold logic, and edge cases
- [x] **TEST-02**: VoteEngine has unit tests covering vote tallying, majority rules, and weighted votes
- [x] **TEST-03**: ImportService has unit tests covering CSV parsing, validation, and error handling
- [ ] **TEST-04**: MeetingValidator has unit tests covering all meeting state transition rules
- [ ] **TEST-05**: NotificationsService has unit tests covering notification creation and delivery logic
- [ ] **TEST-06**: EmailTemplateService has unit tests covering template rendering and variable substitution
- [ ] **TEST-07**: SpeechService has unit tests covering speech queue management and ordering
- [ ] **TEST-08**: MonitoringService has unit tests covering health checks and metric collection
- [ ] **TEST-09**: ErrorDictionary has unit tests covering error code lookup and message formatting
- [ ] **TEST-10**: ResolutionDocumentController has unit tests covering upload, serve, and delete endpoints

### Coverage Target (COV)

- [ ] **COV-01**: PHPUnit code coverage reaches 90%+ on app/Services/ directory
- [ ] **COV-02**: PHPUnit code coverage reaches 90%+ on app/Controller/ directory
- [ ] **COV-03**: Code coverage report generates in CI and fails build below 90% threshold

### E2E Tests (E2E)

- [ ] **E2E-01**: All 18 Playwright specs updated with correct selectors matching v4.3/v4.4 rebuilt pages
- [ ] **E2E-02**: auth.spec.js uses v4.3 login page selectors (#email, #password, #submitBtn, .field-eye)
- [ ] **E2E-03**: audit-regression.spec.js updated for v4.4 audit page structure (filter tabs, timeline/table views)
- [ ] **E2E-04**: vote.spec.js updated for French data-choice attributes and v4.4 ballot layout
- [ ] **E2E-05**: All E2E specs pass against running Docker stack on chromium
- [ ] **E2E-06**: Mobile viewport specs pass for vote/ballot page on tablet and mobile-chrome projects

### CI/CD Pipeline (CI)

- [ ] **CI-01**: Playwright E2E tests run in GitHub Actions workflow after Docker build
- [ ] **CI-02**: Code coverage gate enforced in CI — build fails below 90% threshold
- [ ] **CI-03**: Migration syntax validation step in CI — all .sql files checked for PostgreSQL compatibility
- [ ] **CI-04**: Integration tests run post-Docker-build against containerized PostgreSQL + Redis

### Infrastructure — Migrations (MIG)

- [x] **MIG-01**: All migration files audited — zero SQLite syntax (AUTOINCREMENT, datetime('now'), etc.)
- [x] **MIG-02**: Migration dry-run validation script exists and runs all migrations against clean PostgreSQL
- [x] **MIG-03**: Migration idempotency verified — running all migrations twice produces no errors

### Infrastructure — Docker (DOC)

- [x] **DOC-01**: Docker healthcheck uses runtime PORT variable correctly (not build-time evaluation)
- [x] **DOC-02**: Entrypoint handles custom PORT with read-only filesystem gracefully (nginx config template or fallback)
- [x] **DOC-03**: Health endpoint returns structured JSON with database, redis, and filesystem check results

## v6.0 Requirements

### Performance & Monitoring

- **PERF-01**: Load testing suite for SSE connections (100+ concurrent voters)
- **PERF-02**: Database query performance benchmarks for large tenants (1000+ members)
- **MON-01**: Prometheus metrics endpoint for production monitoring

### Security Hardening

- **SEC-01**: ClamAV virus scanning for uploaded PDFs
- **SEC-02**: Rate limiting integration tests under concurrent load

## Out of Scope

| Feature | Reason |
|---------|--------|
| New voting modes | Quality milestone — no new features |
| UI changes | v4.4 rebuilt all pages — no visual work needed |
| Mobile native app | PWA approach maintained |
| AI-assisted PV generation | Deferred to feature milestone |
| Electronic signatures | Deferred to feature milestone |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| TEST-01 | Phase 53 | Complete |
| TEST-02 | Phase 53 | Complete |
| TEST-03 | Phase 53 | Complete |
| TEST-04 | Phase 53 | Pending |
| TEST-05 | Phase 53 | Pending |
| TEST-06 | Phase 54 | Pending |
| TEST-07 | Phase 54 | Pending |
| TEST-08 | Phase 54 | Pending |
| TEST-09 | Phase 54 | Pending |
| TEST-10 | Phase 54 | Pending |
| COV-01 | Phase 55 | Pending |
| COV-02 | Phase 55 | Pending |
| COV-03 | Phase 55 | Pending |
| E2E-01 | Phase 56 | Pending |
| E2E-02 | Phase 56 | Pending |
| E2E-03 | Phase 56 | Pending |
| E2E-04 | Phase 56 | Pending |
| E2E-05 | Phase 56 | Pending |
| E2E-06 | Phase 56 | Pending |
| CI-01 | Phase 57 | Pending |
| CI-02 | Phase 57 | Pending |
| CI-03 | Phase 57 | Pending |
| CI-04 | Phase 57 | Pending |
| MIG-01 | Phase 52 | Complete |
| MIG-02 | Phase 52 | Complete |
| MIG-03 | Phase 52 | Complete |
| DOC-01 | Phase 52 | Complete |
| DOC-02 | Phase 52 | Complete |
| DOC-03 | Phase 52 | Complete |

**Coverage:**
- v5.0 requirements: 29 total
- Mapped to phases: 29
- Unmapped: 0

---
*Requirements defined: 2026-03-30*
*Last updated: 2026-03-30 — all 29 requirements mapped to phases 52-57*
