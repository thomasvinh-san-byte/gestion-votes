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
- 🚧 **v6.1 PDF & Preparation de Seance** - Phases 65-66 (in progress)

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

### v6.1 PDF & Preparation de Seance (In Progress)

**Milestone Goal:** Make meeting-level PDF attachments accessible to voters through the hub and vote page. Operators upload in the wizard and manage from the console. A dual-auth serve endpoint (session OR vote token) secures file access.

## Phases

- [ ] **Phase 65: Attachment Upload & Serve** - Wizard FilePond upload, operator console management panel, dual-auth serve endpoint
- [ ] **Phase 66: Voter Document Access** - Hub "Documents de la seance" section and vote page "Documents" button with ag-pdf-viewer

## Phase Details

### Phase 65: Attachment Upload & Serve
**Goal**: Operators can upload meeting attachments during session creation and manage them from the console, with a secure serve endpoint ready for voter access
**Depends on**: Nothing (first phase of v6.1)
**Requirements**: ATTACH-01, ATTACH-02, ATTACH-05
**Success Criteria** (what must be TRUE):
  1. Operator can upload one or more PDF attachments in wizard step 1 (session info) using FilePond, and they persist to the database and filesystem
  2. Operator can view the list of existing attachments, add new ones, and delete existing ones from the operator console
  3. The serve endpoint returns the PDF file with correct Content-Type when accessed with a valid session OR a valid vote token (dual auth mirroring ResolutionDocumentController::serve())
  4. Unauthenticated or unauthorized requests to the serve endpoint receive 401/403
**Plans**: 2 plans

Plans:
- [ ] 65-01-PLAN.md — Dual-auth serve() endpoint, route registration, and unit tests (ATTACH-05)
- [ ] 65-02-PLAN.md — Wizard post-creation FilePond upload and operator console attachment management (ATTACH-01, ATTACH-02)

### Phase 66: Voter Document Access
**Goal**: Voters can consult all meeting attachments from the hub and from the vote page using ag-pdf-viewer
**Depends on**: Phase 65
**Requirements**: ATTACH-03, ATTACH-04
**Success Criteria** (what must be TRUE):
  1. Hub displays a "Documents de la seance" section listing all meeting attachments, each clickable to open ag-pdf-viewer (inline or sheet mode)
  2. Vote page displays a "Documents" button that opens a panel or sheet listing meeting attachments viewable via ag-pdf-viewer
  3. PDF content loads successfully for voters authenticated by either session or vote token
**Plans**: TBD

Plans:
- [ ] 66-01: Hub document section and vote page document button with ag-pdf-viewer

## Progress

**Execution Order:**
Phases execute in numeric order: 65 → 66

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 65. Attachment Upload & Serve | 0/2 | Not started | - |
| 66. Voter Document Access | 0/1 | Not started | - |
