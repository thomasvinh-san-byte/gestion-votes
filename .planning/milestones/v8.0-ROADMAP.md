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
- ✅ **v5.0 Quality & Production Readiness** - Phases 52-57 (shipped 2026-03-30)
- ✅ **v5.1 Operational Hardening** - Phases 58-61 (shipped 2026-03-31)
- ✅ **v6.0 Production & Email** - Phases 62-64 (shipped 2026-04-01)
- ✅ **v6.1 PDF & Preparation de Seance** - Phases 65-66 (shipped 2026-04-01)
- ✅ **v7.0 Production Essentials** - Phases 67-70 (shipped 2026-04-01)
- 🚧 **v8.0 Account & Hardening** - Phases 71-75 (in progress)

---

<details>
<summary>✅ v4.3 Ground-Up Rebuild (Shipped: 2026-03-22) — 7 phases, 14 plans</summary>

**Milestone Goal:** Rebuild every critical page from scratch — HTML+CSS+JS together in one commit, fix all v4.2 regressions, wire backend properly, achieve genuine top 1% design quality.

### Phases

- [x] **Phase 42: Stabilization** - Fix all v4.2 regressions before any rebuild work begins
- [x] **Phase 43: Dashboard Rebuild** - Complete HTML+CSS+JS rewrite, KPIs and session list wired to backend (completed 2026-03-20)
- [x] **Phase 44: Login Rebuild** - Complete HTML+CSS rewrite, auth flow wired, top 1% entry point (completed 2026-03-20)
- [x] **Phase 45: Wizard Rebuild** - Complete HTML+CSS+JS rewrite, all 4 steps wired, form submissions verified (completed 2026-03-22)
- [x] **Phase 46: Operator Console Rebuild** - Complete HTML+CSS+JS rewrite, SSE wired, live vote panel functional (completed 2026-03-22)
- [x] **Phase 47: Hub Rebuild** - Complete HTML+CSS+JS rewrite, session lifecycle wired, quorum bar functional (completed 2026-03-22)
- [x] **Phase 48: Settings/Admin Rebuild** - Complete HTML+CSS+JS rewrite, all settings save, admin KPIs wired (completed 2026-03-22)

</details>

---

<details>
<summary>✅ v4.4 Complete Rebuild (Shipped: 2026-03-30) — 3 phases, 10 plans</summary>

**Milestone Goal:** Ground-up rebuild of all remaining 13 pages to v4.3 quality standard — HTML+CSS+JS from scratch, backend wiring verified, browser tested.

### Phases

- [x] **Phase 49: Secondary Pages Part 1** - Ground-up rebuild of postsession, analytics, meetings, archives (completed 2026-03-30)
- [x] **Phase 50: Secondary Pages Part 2** - Ground-up rebuild of audit, members, users, vote/ballot (completed 2026-03-30)
- [x] **Phase 51: Utility Pages** - Ground-up rebuild of help, email-templates, public, report/PV, trust/validate/docs (completed 2026-03-30)

</details>

---

<details>
<summary>✅ v5.0 Quality & Production Readiness (Shipped: 2026-03-30) — 6 phases, 18 plans</summary>

**Milestone Goal:** Achieve 90%+ test coverage across all layers, fix infrastructure bugs, harden Docker/CI pipeline, and make AG-VOTE production-ready.

### Phases

- [x] **Phase 52: Infrastructure Foundations** - Fix Docker healthcheck, entrypoint PORT handling, health endpoint JSON response, and all migration SQLite-isms (completed 2026-03-30)
- [x] **Phase 53: Service Unit Tests Batch 1** - Write unit tests for QuorumEngine, VoteEngine, ImportService, MeetingValidator, NotificationsService (completed 2026-03-30)
- [x] **Phase 54: Service Unit Tests Batch 2** - Write unit tests for EmailTemplateService, SpeechService, MonitoringService, ErrorDictionary, ResolutionDocumentController (completed 2026-03-30)
- [x] **Phase 55: Coverage Target & Tooling** - Install pcov, measure baseline, fill gaps to 90%+ on Services and Controllers (completed 2026-03-30)
- [x] **Phase 56: E2E Test Updates** - Update all 18 stale Playwright specs for v4.3/v4.4 rebuilds; all specs pass on Chromium (completed 2026-03-30)
- [x] **Phase 57: CI/CD Pipeline** - Wire PHPUnit coverage gate, E2E suite, migration validation, and integration tests into GitHub Actions (completed 2026-03-30)

</details>

---

<details>
<summary>✅ v5.1 Operational Hardening (Shipped: 2026-03-31) — 4 phases, 8 plans</summary>

**Milestone Goal:** Close all logical holes in the domain layer — rename WebSocket to SSE throughout the codebase, harden vote/quorum/session/import/auth edge cases with explicit error handling and audit logging, and eliminate dead code and vocabulary inconsistencies.

### Phases

- [x] **Phase 58: WebSocket to SSE Rename** - Rename AgVote\WebSocket namespace to AgVote\SSE, WebSocketListener to SseListener (completed 2026-03-31)
- [x] **Phase 59: Vote and Quorum Edge Cases** - Token reuse audit trail, closed-motion 409, zero-member quorum safety, SSE broadcast tests (completed 2026-03-31)
- [x] **Phase 60: Session, Import, and Auth Edge Cases** - Invalid transition messages, CSV encoding detection, duplicate email pre-scan, session expiry redirect, rate limiting with audit (completed 2026-03-31)
- [x] **Phase 61: Dead Code Cleanup** - Vocabulary purge, phpunit.xml path fix, Command file retention documentation (completed 2026-03-31)

### Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 58. WebSocket to SSE Rename | 2/2 | Complete | 2026-03-31 |
| 59. Vote and Quorum Edge Cases | 2/2 | Complete | 2026-03-31 |
| 60. Session, Import, and Auth Edge Cases | 3/3 | Complete | 2026-03-31 |
| 61. Dead Code Cleanup | 1/1 | Complete | 2026-03-31 |

</details>

---

<details>
<summary>✅ v6.0 Production & Email (Shipped: 2026-04-01) — 3 phases, 6 plans</summary>

**Milestone Goal:** Deliver functional email communication (invitations, reminders, results) via SMTP with customizable templates, plus real-time in-app notifications via bell icon and SSE toasts.

### Phases

- [x] **Phase 62: SMTP & Template Engine** - Wire Symfony Mailer SMTP config and make email templates editable from admin UI (completed 2026-04-01)
- [x] **Phase 63: Email Sending Workflows** - Operator sends invitations/reminders, system sends results after session close (completed 2026-04-01)
- [x] **Phase 64: In-App Notifications** - Bell badge with notification list and real-time SSE toasts (completed 2026-04-01)

### Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 62. SMTP & Template Engine | 2/2 | Complete | 2026-04-01 |
| 63. Email Sending Workflows | 2/2 | Complete | 2026-04-01 |
| 64. In-App Notifications | 2/2 | Complete | 2026-04-01 |

</details>

---

<details>
<summary>✅ v6.1 PDF & Preparation de Seance (Shipped: 2026-04-01) — 2 phases, 3 plans</summary>

**Milestone Goal:** Make meeting-level PDF attachments accessible to voters through the hub and vote page.

### Phases

- [x] **Phase 65: Attachment Upload & Serve** — Wizard FilePond upload, operator console management, dual-auth serve endpoint (completed 2026-04-01)
- [x] **Phase 66: Voter Document Access** — Hub "Documents de la seance" section, vote page "Documents" button with ag-pdf-viewer (completed 2026-04-01)

### Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 65. Attachment Upload & Serve | 2/2 | Complete | 2026-04-01 |
| 66. Voter Document Access | 1/1 | Complete | 2026-04-01 |

</details>

---

### v7.0 Production Essentials (In Progress)

**Milestone Goal:** Deliver four production-essential features: official PV PDF generation for legal compliance, cron-based email queue worker for reliable delivery, browser-based initial setup for new deployments, and secure password reset via email.

## Phases

- [x] **Phase 67: PV Officiel PDF** - Generate legally compliant proces-verbal PDF with asso loi 1901 template (completed 2026-04-01)
- [x] **Phase 68: Email Queue Worker** - Cron-based queue processor in Docker with retry and failure handling (completed 2026-04-01)
- [x] **Phase 69: Initial Setup** - First-run /setup page to create tenant and admin account (completed 2026-04-01)
- [ ] **Phase 70: Reset Password** - Secure token-based password reset flow via email

## Phase Details

### Phase 67: PV Officiel PDF
**Goal**: Operators can generate a legally compliant official PV after validating a session
**Depends on**: Nothing (builds on existing MeetingReportService + Dompdf)
**Requirements**: PV-01, PV-02, PV-03
**Success Criteria** (what must be TRUE):
  1. After validating a session, the operator clicks "Generer PV" and receives a PDF containing: organization header (name, date, location), attendance list (present and represented members), quorum confirmation, each resolution with detailed vote counts (pour/contre/abstention), and signature blocks for president and secretary
  2. The generated PDF follows the standard asso loi 1901 proces-verbal template layout
  3. The PV PDF is viewable inline and downloadable from the post-session page
**Plans**: 2 plans

Plans:
- [ ] 67-01-PLAN.md — Upgrade generatePdf() to loi 1901 template with org header, quorum block, dual signatures, inline mode
- [ ] 67-02-PLAN.md — Wire post-session Step 3 inline PDF preview and download UI

### Phase 68: Email Queue Worker
**Goal**: Queued emails are processed automatically without manual intervention
**Depends on**: Nothing (builds on existing EmailQueueService::processQueue())
**Requirements**: QUEUE-01, QUEUE-02
**Success Criteria** (what must be TRUE):
  1. A cron job inside the Docker container calls processQueue() every minute without operator action
  2. Emails that fail to send are retried with exponential backoff (not immediately re-sent in a tight loop)
  3. After max retries, permanently failed emails are marked as "failed" in the queue table and stop being retried
**Plans**: 1 plan

Plans:
- [ ] 68-01-PLAN.md — Verify and test existing queue worker, add --reminders to supervisord, add retry logic tests

### Phase 69: Initial Setup
**Goal**: A new deployment can be bootstrapped through a browser-based setup page
**Depends on**: Nothing (independent feature)
**Requirements**: SETUP-01, SETUP-02, SETUP-03
**Success Criteria** (what must be TRUE):
  1. Navigating to /setup when no admin user exists shows a setup form with organization name, admin email, and admin password fields
  2. Submitting the form creates the first tenant and first admin user in the database
  3. After setup completes, the browser redirects to /login and /setup returns a redirect (or 404) for all future requests
**Plans**: 1 plan

Plans:
- [ ] 69-01-PLAN.md — SetupController + SetupRepository + setup form template + route + unit tests

### Phase 70: Reset Password
**Goal**: Users who forget their password can securely reset it via email
**Depends on**: Phase 68 (email queue ensures reset emails are delivered reliably)
**Requirements**: RESET-01, RESET-02, RESET-03
**Success Criteria** (what must be TRUE):
  1. The login page displays a "Mot de passe oublie" link that opens a form where the user enters their email address
  2. Submitting the form sends an email containing a secure token link that expires after 1 hour
  3. Clicking the link opens a new-password form; submitting it updates the password hash in the database and the user can immediately log in with the new password
  4. Expired or already-used tokens are rejected with a clear French error message
**Plans**: 2 plans

Plans:
- [ ] 70-01-PLAN.md — Backend: migration, repository, service, controller, routes, templates, unit tests
- [ ] 70-02-PLAN.md — Wire login page forgot link to /reset-password + human verification

## Progress

**Execution Order:**
Phases execute in numeric order: 67 → 68 → 69 → 70

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 67. PV Officiel PDF | 2/2 | Complete    | 2026-04-01 |
| 68. Email Queue Worker | 1/1 | Complete    | 2026-04-01 |
| 69. Initial Setup | 1/1 | Complete    | 2026-04-01 |
| 70. Reset Password | 0/2 | Not started | - |

</details>

---

### v8.0 Account & Hardening (In Progress)

**Milestone Goal:** Give every logged-in user a self-service Mon Compte page, harden critical operations with 2-step confirmation, make session timeout configurable, add vote session resume after re-auth, and retire four known tech debt items (controller coverage, silent error, CI seed data, CI migration check).

## Phases

- [x] **Phase 71: Mon Compte** - Profile view and self-service password change page for all connected users (completed 2026-04-02)
- [x] **Phase 72: Security Config** - 2-step confirmation for critical operations and configurable session timeout from admin settings (completed 2026-04-02)
- [x] **Phase 73: Vote Session Resume** - Re-authentication flow that restores voter context after session timeout during a live vote (completed 2026-04-02)
- [x] **Phase 74: CI Hardening** - Load E2E seed data in CI job and gate migration idempotency check in CI pipeline (completed 2026-04-02)
- [x] **Phase 75: Coverage & Observability** - Refactor exit()-based controllers to raise coverage ceiling and surface admin.js KPI errors visibly (completed 2026-04-02)

## Phase Details

### Phase 71: Mon Compte
**Goal**: Any connected user can view their profile and change their own password without admin intervention
**Depends on**: Nothing (builds on existing AuthController + bcrypt infrastructure)
**Requirements**: ACCT-01, ACCT-02
**Plans**: 1 plan (complete)

### Phase 72: Security Config
**Goal**: Administrators can require 2-step confirmation before irreversible operations execute, and can set the session idle timeout from the admin UI instead of relying on a hardcoded 30-minute value
**Depends on**: Phase 71
**Requirements**: SEC-01, SEC-02
**Success Criteria** (what must be TRUE):
  1. Deleting a user or triggering an admin password reset shows a confirmation dialog requiring a second explicit action before proceeding
  2. An admin changes the session timeout value in settings and subsequent sessions expire after the new configured duration
  3. The timeout setting persists across server restarts (stored in tenant_settings, not only in memory)
  4. Attempting the critical operation via direct POST without completing the 2-step flow is rejected
**Plans**: 2 plans

Plans:
- [x] 72-01-PLAN.md — 2-step confirmation for critical admin operations (delete, set_password)
- [ ] 72-02-PLAN.md — Configurable session timeout from admin settings UI

### Phase 73: Vote Session Resume
**Goal**: A voter whose session expires mid-vote can re-authenticate and return to the exact ballot they were on, without losing their voting context
**Depends on**: Phase 72
**Requirements**: SEC-03
**Success Criteria** (what must be TRUE):
  1. When a voter's session expires during an active vote, they are redirected to a re-authentication page rather than the generic login page
  2. After successful re-authentication, the voter is returned directly to the ballot for the meeting they were voting in
  3. Any vote the voter had already cast before timeout is preserved and visible on return
  4. If the vote session has closed while the voter was timed out, they see a clear message explaining the vote has ended
**Plans**: 1 plan

Plans:
- [ ] 73-01-PLAN.md — Modify auth-ui.js session expiry redirect + login.js return_to routing for vote page resume

### Phase 74: CI Hardening
**Goal**: The CI pipeline loads E2E seed data automatically and validates migration idempotency on every run, so local-only checks cannot silently drift from CI
**Depends on**: Nothing (pure CI/infrastructure work)
**Requirements**: DEBT-03, DEBT-04
**Success Criteria** (what must be TRUE):
  1. The CI e2e job loads 04_e2e.sql before running Playwright tests, and tests that depend on seed data pass in CI without manual intervention
  2. The CI migrate-check job runs the idempotency validation script and fails the build if any migration is not idempotent
  3. A purposely non-idempotent migration (test fixture) causes the CI job to fail with a clear error message
**Plans**: 1 plan

Plans:
- [ ] 74-01-PLAN.md — Load 04_e2e.sql in e2e job and upgrade migrate-check to full PostgreSQL idempotency validation

### Phase 75: Coverage & Observability
**Goal**: Controller test coverage rises above the current 64.6% structural ceiling and the admin dashboard no longer silently swallows KPI load failures
**Depends on**: Nothing (independent quality work)
**Requirements**: DEBT-01, DEBT-02
**Success Criteria** (what must be TRUE):
  1. The exit()-based controllers are refactored so PHPUnit can exercise them; coverage report shows controller coverage above 70%
  2. When the admin KPI endpoint fails, the admin page displays a visible error message or fallback state instead of leaving the KPI cards blank with no indication of failure
  3. The coverage-check.sh threshold is updated to enforce the new higher floor, and CI fails if coverage drops below it
**Plans**: 2 plans

Plans:
- [ ] 75-01-PLAN.md — Refactor exit()-based serve() and outputPixel() methods to use PHPUNIT_RUNNING exceptions; add happy-path tests; raise coverage threshold to 70
- [ ] 75-02-PLAN.md — Replace silent KPI catch block with visible error fallback in admin.js

## Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 71. Mon Compte | 1/1 | Complete | 2026-04-02 |
| 72. Security Config | 1/2 | Complete    | 2026-04-02 |
| 73. Vote Session Resume | 0/1 | Complete    | 2026-04-02 |
| 74. CI Hardening | 0/1 | Complete    | 2026-04-02 |
| 75. Coverage & Observability | 1/2 | Complete    | 2026-04-02 |
