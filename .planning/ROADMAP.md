# Roadmap: AG-VOTE

## Milestones

- ✅ **v1.x Foundations** - Phases 1-3 (shipped)
- ✅ **v2.0 UI Redesign** - Phases 4-15 (shipped 2026-03-16)
- ✅ **v3.0 Session Lifecycle** - Phases 16-24 (shipped 2026-03-18)
- ✅ **v4.0 Clarity & Flow** - Phases 25-29 (shipped 2026-03-18)
- ✅ **v4.1 Design Excellence** - Phases 30-34 (shipped 2026-03-19)
- ✅ **v4.2 Visual Redesign** - Phases 35-41.5 (shipped 2026-03-20)
- ✅ **v4.3 Ground-Up Rebuild** - Phases 42-48 (shipped 2026-03-22)
- ✅ **v4.4 Complete Rebuild** - Phases 49-51 (shipped 2026-03-30)
- 🚧 **v5.0 Quality & Production Readiness** - Phases 52-57 (in progress)

---

## ✅ v4.3 Ground-Up Rebuild (Shipped: 2026-03-22)

<details>
<summary>7 phases, 14 plans — dashboard, login, wizard, operator, hub, settings/admin rebuilt from scratch</summary>

**Milestone Goal:** Rebuild every critical page from scratch — HTML+CSS+JS together in one commit, fix all v4.2 regressions, wire backend properly, achieve genuine top 1% design quality.

**Approach:** Read existing JS before touching HTML. Rewrite HTML+CSS. Update JS if DOM changes. Verify backend connections. Test in browser before marking done. No broken intermediate states.

### Phases

- [x] **Phase 42: Stabilization** - Fix all v4.2 regressions before any rebuild work begins
- [x] **Phase 43: Dashboard Rebuild** - Complete HTML+CSS+JS rewrite, KPIs and session list wired to backend (completed 2026-03-20)
- [x] **Phase 44: Login Rebuild** - Complete HTML+CSS rewrite, auth flow wired, top 1% entry point (completed 2026-03-20)
- [x] **Phase 45: Wizard Rebuild** - Complete HTML+CSS+JS rewrite, all 4 steps wired, form submissions verified (completed 2026-03-22)
- [x] **Phase 46: Operator Console Rebuild** - Complete HTML+CSS+JS rewrite, SSE wired, live vote panel functional (completed 2026-03-22)
- [x] **Phase 47: Hub Rebuild** - Complete HTML+CSS+JS rewrite, session lifecycle wired, quorum bar functional (completed 2026-03-22)
- [x] **Phase 48: Settings/Admin Rebuild** - Complete HTML+CSS+JS rewrite, all settings save, admin KPIs wired (completed 2026-03-22)

### Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 42. Stabilization | 1/1 | Complete | 2026-03-20 |
| 43. Dashboard Rebuild | 2/2 | Complete | 2026-03-20 |
| 44. Login Rebuild | 2/2 | Complete | 2026-03-20 |
| 45. Wizard Rebuild | 2/2 | Complete | 2026-03-22 |
| 46. Operator Console Rebuild | 2/2 | Complete | 2026-03-22 |
| 47. Hub Rebuild | 3/3 | Complete | 2026-03-22 |
| 48. Settings/Admin Rebuild | 2/2 | Complete | 2026-03-22 |

</details>

---

## ✅ v4.4 Complete Rebuild (Shipped: 2026-03-30)

<details>
<summary>3 phases, 10 plans — postsession, analytics, meetings, archives, audit, members, users, vote, help, email-templates, public, report, trust/validate/docs rebuilt</summary>

**Milestone Goal:** Ground-up rebuild of all remaining 13 pages to v4.3 quality standard — HTML+CSS+JS from scratch, backend wiring verified, browser tested.

### Phases

- [x] **Phase 49: Secondary Pages Part 1** - Ground-up rebuild of postsession, analytics, meetings, archives (completed 2026-03-30)
- [x] **Phase 50: Secondary Pages Part 2** - Ground-up rebuild of audit, members, users, vote/ballot (completed 2026-03-30)
- [x] **Phase 51: Utility Pages** - Ground-up rebuild of help, email-templates, public, report/PV, trust/validate/docs (completed 2026-03-30)

### Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 49. Secondary Pages Part 1 | 3/3 | Complete | 2026-03-30 |
| 50. Secondary Pages Part 2 | 4/4 | Complete | 2026-03-30 |
| 51. Utility Pages | 3/3 | Complete | 2026-03-30 |

</details>

---

## 🚧 v5.0 Quality & Production Readiness (In Progress)

**Milestone Goal:** Achieve 90%+ test coverage across all layers, fix infrastructure bugs, harden Docker/CI pipeline, and make AG-VOTE production-ready.

**Approach:** Fix foundations first (migrations, Docker), then build test coverage bottom-up (unit tests -> coverage tooling -> E2E updates -> CI wiring). Each phase delivers a verifiable quality gate.

## Phases

- [x] **Phase 52: Infrastructure Foundations** - Fix Docker healthcheck, entrypoint PORT handling, health endpoint JSON response, and all migration SQLite-isms (completed 2026-03-30)
- [x] **Phase 53: Service Unit Tests Batch 1** - Write unit tests for QuorumEngine, VoteEngine, ImportService, MeetingValidator, NotificationsService (5 business-critical services) (completed 2026-03-30)
- [x] **Phase 54: Service Unit Tests Batch 2** - Write unit tests for EmailTemplateService, SpeechService, MonitoringService, ErrorDictionary, and ResolutionDocumentController (completed 2026-03-30)
- [x] **Phase 55: Coverage Target & Tooling** - Install pcov/xdebug coverage driver, measure baseline, fill gaps to reach 90%+ on Services and Controllers
- [x] **Phase 56: E2E Test Updates** - Update all 18 stale Playwright specs with selectors matching v4.3/v4.4 rebuilt pages; all specs pass on Chromium (completed 2026-03-30)
- [ ] **Phase 57: CI/CD Pipeline** - Wire PHPUnit coverage gate, E2E suite, migration validation, and integration tests into GitHub Actions workflow

## Phase Details

### Phase 52: Infrastructure Foundations
**Goal**: Docker runs correctly in all deployment scenarios and every migration file is clean PostgreSQL — no SQLite syntax, no runtime evaluation bugs
**Depends on**: Nothing (first phase in milestone)
**Requirements**: MIG-01, MIG-02, MIG-03, DOC-01, DOC-02, DOC-03
**Success Criteria** (what must be TRUE):
  1. Running all migrations twice against a clean PostgreSQL instance produces zero errors — no duplicate table or column creation failures
  2. The Docker healthcheck correctly reads PORT at container runtime, not at image build time — changing PORT via environment variable takes effect without rebuilding the image
  3. The health endpoint at `/health` returns a JSON object containing `database`, `redis`, and `filesystem` status fields with valid values
  4. A migration dry-run script exists that can be invoked to validate all `.sql` files against a fresh PostgreSQL database, reporting any incompatible syntax
  5. Zero occurrences of SQLite-specific syntax (`AUTOINCREMENT`, `datetime('now')`, `INTEGER PRIMARY KEY`) remain in any migration file
**Plans:** 2/2 plans complete
Plans:
- [ ] 52-01-PLAN.md — Migration audit, dry-run script, idempotency verification
- [ ] 52-02-PLAN.md — Docker healthcheck fix, nginx template, health endpoint enhancement

### Phase 53: Service Unit Tests Batch 1
**Goal**: The five most business-critical services — QuorumEngine, VoteEngine, ImportService, MeetingValidator, NotificationsService — have comprehensive unit tests covering happy paths, edge cases, and error conditions
**Depends on**: Phase 52
**Requirements**: TEST-01, TEST-02, TEST-03, TEST-04, TEST-05
**Success Criteria** (what must be TRUE):
  1. `vendor/bin/phpunit --filter QuorumEngineTest` passes with tests covering quorum calculation, threshold boundary conditions, and missing-attendance edge cases
  2. `vendor/bin/phpunit --filter VoteEngineTest` passes with tests covering simple majority, absolute majority, weighted votes, and tie-breaking logic
  3. `vendor/bin/phpunit --filter ImportServiceTest` passes with tests covering valid CSV import, malformed rows, duplicate detection, and encoding edge cases
  4. `vendor/bin/phpunit --filter MeetingValidatorTest` passes with tests covering all valid and invalid meeting state transitions
  5. `vendor/bin/phpunit --filter NotificationsServiceTest` passes with tests covering notification creation, delivery dispatch, and failure handling
**Plans:** 2/2 plans complete
Plans:
- [ ] 53-01-PLAN.md — VoteEngine computeMotionResult tests + ImportServiceTest + verify QuorumEngineTest
- [ ] 53-02-PLAN.md — MeetingValidatorTest + NotificationsServiceTest

### Phase 54: Service Unit Tests Batch 2
**Goal**: The remaining five services and the ResolutionDocumentController have unit tests — completing full service-layer coverage
**Depends on**: Phase 53
**Requirements**: TEST-06, TEST-07, TEST-08, TEST-09, TEST-10
**Success Criteria** (what must be TRUE):
  1. `vendor/bin/phpunit --filter EmailTemplateServiceTest` passes with tests covering template rendering, variable substitution, and missing-variable fallback behavior
  2. `vendor/bin/phpunit --filter SpeechServiceTest` passes with tests covering speech queue ordering, insertion, removal, and empty-queue edge cases
  3. `vendor/bin/phpunit --filter MonitoringServiceTest` passes with tests covering health check responses for each subsystem (database, redis, filesystem)
  4. `vendor/bin/phpunit --filter ErrorDictionaryTest` passes with tests covering known error code lookup, unknown code fallback, and message formatting with parameters
  5. `vendor/bin/phpunit --filter ResolutionDocumentControllerTest` passes with tests covering upload validation, file serving with auth check, and delete authorization
**Plans:** 2/2 plans complete
Plans:
- [x] 54-01-PLAN.md — ErrorDictionary + EmailTemplateService + SpeechService unit tests
- [ ] 54-02-PLAN.md — MonitoringService + ResolutionDocumentController unit tests

### Phase 55: Coverage Target & Tooling
**Goal**: Code coverage measurement is operational and the codebase meets 90%+ coverage on Services and Controllers — the gap between current state and target is closed
**Depends on**: Phase 54
**Requirements**: COV-01, COV-02, COV-03
**Success Criteria** (what must be TRUE):
  1. Running `vendor/bin/phpunit --coverage-html coverage/` generates an HTML report without errors — the report is readable in a browser
  2. The coverage report shows 90%+ line coverage for the `app/Services/` directory
  3. The coverage report shows 90%+ line coverage for the `app/Controller/` directory
  4. A PHPUnit configuration (phpunit.xml) enforces the 90% threshold — running the suite below threshold exits with a non-zero code
**Plans:** 9/9 plans complete
Plans:
- [x] 55-01-PLAN.md — Install pcov, fix phpunit.xml source includes, measure baseline coverage
- [x] 55-02-PLAN.md — Fill coverage gaps, add 90% threshold enforcement script
- [ ] 55-03-PLAN.md — ControllerTestCase base class + service gap-filling to 90%
- [ ] 55-04-PLAN.md — Controller tests: Import, Motions, MeetingReports, Meetings, MeetingWorkflow
- [ ] 55-05-PLAN.md — Controller tests: Operator, Admin, Analytics, Ballots, Audit
- [ ] 55-06-PLAN.md — Controller tests: Trust, MemberGroups, Auth, Export, ResolutionDocument, Email, Doc, Devices
- [ ] 55-07-PLAN.md — Controller tests: Dashboard, EmailTemplates, Quorum, Speech, Attendances, Invitations, ExportTemplates, Policies, VotePublic, MeetingAttachment, Members, Agenda
- [ ] 55-08-PLAN.md — Controller tests: Proxies, EmailTracking, Reminder, DevSeed, Projector, VoteToken, Settings, Emergency, DocContent, Notifications
- [ ] 55-09-PLAN.md — Update coverage-check.sh thresholds to 90/90 and final validation

### Phase 56: E2E Test Updates
**Goal**: All Playwright specs reflect the v4.3/v4.4 page rebuilds — every spec uses current selectors and passes on Chromium against the running Docker stack
**Depends on**: Phase 52
**Requirements**: E2E-01, E2E-02, E2E-03, E2E-04, E2E-05, E2E-06
**Success Criteria** (what must be TRUE):
  1. `npx playwright test --project=chromium` runs all 18 specs to completion with zero failures — no stale selector errors, no timeout failures from missing elements
  2. The auth spec successfully logs in using `#email`, `#password`, `#submitBtn` and toggles password visibility via `.field-eye` — matching the v4.3 login page DOM
  3. The audit-regression spec correctly targets the v4.4 audit page structure — filter tabs, timeline view, and table view are all exercised without selector errors
  4. The vote spec selects choices using French `data-choice` attribute values and interacts with the v4.4 ballot layout — the vote is submitted and confirmed without errors
  5. Mobile viewport specs for the vote/ballot page pass on both `tablet` and `mobile-chrome` Playwright projects — no layout overflow or touch target failures
**Plans:** 2/2 plans complete
Plans:
- [ ] 56-01-PLAN.md — Install Playwright, audit and fix all 18 specs for stale selectors
- [ ] 56-02-PLAN.md — Run full suite on Chromium, fix failures iteratively, verify mobile viewports

### Phase 57: CI/CD Pipeline
**Goal**: Every quality gate runs automatically on every push — coverage, E2E, migration validation, and integration tests are all enforced in GitHub Actions
**Depends on**: Phase 55, Phase 56
**Requirements**: CI-01, CI-02, CI-03, CI-04
**Success Criteria** (what must be TRUE):
  1. A GitHub Actions workflow runs Playwright E2E tests after a successful Docker build — the workflow fails and reports spec-level errors if any E2E test fails
  2. The CI workflow runs PHPUnit with coverage and fails the build if coverage drops below 90% on Services or Controllers — the failure message names the threshold breached
  3. The CI workflow runs a migration validation step that checks all `.sql` files for PostgreSQL compatibility — a file containing SQLite syntax causes the step to fail with the offending filename
  4. Integration tests run against a containerized PostgreSQL + Redis stack in CI — tests exercise real database and cache connections, not mocks
**Plans:** 1 plan
Plans:
- [ ] 57-01-PLAN.md — Add migrate-check, coverage, e2e, and integration jobs to GitHub Actions workflow

## Progress

**Execution Order:** 52 -> 53 -> 54 -> 55 -> 56 -> 57 (56 can run in parallel with 53-55 after 52)

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 52. Infrastructure Foundations | 2/2 | Complete    | 2026-03-30 | - |
| 53. Service Unit Tests Batch 1 | 2/2 | Complete    | 2026-03-30 | - |
| 54. Service Unit Tests Batch 2 | 2/2 | Complete    | 2026-03-30 | - |
| 55. Coverage Target & Tooling | 9/9 | Complete    | 2026-03-30 | 2026-03-30 |
| 56. E2E Test Updates | 2/2 | Complete    | 2026-03-30 | - |
| 57. CI/CD Pipeline | v5.0 | 0/1 | Not started | - |
