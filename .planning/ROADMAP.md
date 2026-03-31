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
- 🚧 **v5.1 Operational Hardening** - Phases 58-61 (in progress)

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

<details>
<summary>✅ v4.4 Complete Rebuild (Shipped: 2026-03-30) — 3 phases, 10 plans</summary>

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

### Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 52. Infrastructure Foundations | 2/2 | Complete | 2026-03-30 |
| 53. Service Unit Tests Batch 1 | 2/2 | Complete | 2026-03-30 |
| 54. Service Unit Tests Batch 2 | 2/2 | Complete | 2026-03-30 |
| 55. Coverage Target & Tooling | 9/9 | Complete | 2026-03-30 |
| 56. E2E Test Updates | 2/2 | Complete | 2026-03-30 |
| 57. CI/CD Pipeline | 1/1 | Complete | 2026-03-30 |

</details>

---

## 🚧 v5.1 Operational Hardening (In Progress)

**Milestone Goal:** Close all logical holes in the domain layer — rename WebSocket to SSE throughout the codebase, harden vote/quorum/session/import/auth edge cases with explicit error handling and audit logging, and eliminate dead code and vocabulary inconsistencies.

**Approach:** Rename first (clean foundation), then harden domain edge cases by category, then clean up dead code. Each phase delivers verifiable behavioral guarantees, not just code changes.

## Phases

- [x] **Phase 58: WebSocket to SSE Rename** - Rename the `AgVote\WebSocket` namespace and `WebSocketListener` class to `AgVote\SSE` and `SseListener`; eliminate all "WebSocket" terminology from PHP source (completed 2026-03-31)
- [x] **Phase 59: Vote and Quorum Edge Cases** - Enforce explicit error responses for expired tokens, double votes, closed-motion votes, zero-member quorum, and real-time quorum updates via SSE (completed 2026-03-31)
- [ ] **Phase 60: Session, Import, and Auth Edge Cases** - Enforce invalid state transitions and live-session deletion guard; handle non-UTF-8 CSV encodings and email duplicates; guarantee redirect-with-message on session expiry and brute-force blocking
- [ ] **Phase 61: Dead Code Cleanup** - Remove or implement controller stubs; purge copropriete/syndic vocabulary from demo seeds; audit and document or delete identified dead files

## Phase Details

### Phase 58: WebSocket to SSE Rename
**Goal**: The codebase accurately reflects its transport mechanism — SSE, not WebSockets — with zero terminology drift between namespace, class names, comments, and documentation
**Depends on**: Nothing (first phase in milestone)
**Requirements**: SSE-01, SSE-02, SSE-03
**Success Criteria** (what must be TRUE):
  1. `grep -r "AgVote\\WebSocket" src/` (or equivalent PHP source directories) returns zero results — the namespace does not exist anywhere outside vendor/
  2. The class formerly named `WebSocketListener` is now named `SseListener` and all references to it (instantiation, type hints, comments) use the new name
  3. Running `grep -ri "websocket" src/ app/` (excluding vendor/) returns zero results — no stale comments, docblocks, or identifiers remain
  4. The application boots and SSE connections function correctly after the rename — no autoload or routing breakage
**Plans**: 2 plans

Plans:
- [ ] 58-01-PLAN.md — Rename app/WebSocket/ to app/SSE/, WebSocketListener to SseListener, update bootstrap.php autoloader and Application.php wiring
- [ ] 58-02-PLAN.md — Update use statements and inline comments in 6 controllers, 2 services, 1 repository, 1 test file; verify PHPUnit passes

### Phase 59: Vote and Quorum Edge Cases
**Goal**: The voting and quorum subsystems handle all failure modes explicitly — no silent failures, no 500 errors, no division-by-zero panics, with anomalies logged to the audit trail
**Depends on**: Phase 58
**Requirements**: VOTE-01, VOTE-02, VOTE-03, QUOR-01, QUOR-02
**Success Criteria** (what must be TRUE):
  1. Submitting a vote with an expired or already-used token returns an HTTP 4xx response with a clear error message — the server does not return 500 and does not crash
  2. Submitting a second vote with the same token is rejected, and an audit trail entry is created recording the double-vote attempt (token, timestamp, IP)
  3. Submitting a vote on a motion whose status is "closed" returns an explicit error message stating the motion is no longer accepting votes — the vote is not recorded
  4. The quorum calculation function returns 0% (or equivalent zero-safe value) when zero members are present — no division-by-zero exception is raised
  5. When an operator adds or removes an attendee while a vote is open, the quorum percentage displayed in the operator console updates in real time via an SSE event without requiring a page reload
**Plans**: 2 plans

Plans:
- [ ] 59-01-PLAN.md — Add audit_log calls for token reuse/expiry and try/catch RuntimeException for closed-motion in BallotsController::cast(), plus unit tests for VOTE-01/02/03
- [ ] 59-02-PLAN.md — Verify QUOR-01 zero-member test lock exists, add QUOR-02 quorum broadcast tests to AttendancesControllerTest

### Phase 60: Session, Import, and Auth Edge Cases
**Goal**: Sessions enforce their state machine, CSV import handles encoding and duplicates gracefully, and authentication failures always produce informative user-facing responses
**Depends on**: Phase 58
**Requirements**: SESS-01, SESS-02, IMP-01, IMP-02, AUTH-01, AUTH-02
**Success Criteria** (what must be TRUE):
  1. Attempting an invalid session state transition (e.g., draft directly to validated) returns an explicit error message naming the disallowed transition — the session state is not changed
  2. Attempting to delete a session whose status is "live" is rejected with a clear message — the session is not deleted and the operator is informed
  3. Uploading a CSV file encoded in Windows-1252 or ISO-8859-1 produces a correctly imported member list — names with accented characters (e.g., "Dupre", "Muller") are stored correctly in UTF-8
  4. Uploading a CSV that contains two rows with the same email address produces a validation error listing the duplicate emails — no silent creation of duplicate member records occurs
  5. Accessing any authenticated page with an expired session redirects to /login with a visible message explaining the session has expired — no blank page or raw PHP error is shown
  6. After a configurable number of consecutive failed login attempts from the same IP, further attempts are blocked and an entry recording the IP and attempt count is written to the audit trail
**Plans**: 3 plans

Plans:
- [ ] 60-01-PLAN.md — Enrich invalid transition error message (SESS-01) and add live-session delete guard (SESS-02)
- [ ] 60-02-PLAN.md — Add encoding detection to ImportService::readCsvFile() (IMP-01) and duplicate email pre-scan to ImportController (IMP-02)
- [ ] 60-03-PLAN.md — Differentiate session expiry in AuthMiddleware (AUTH-01) and add configurable rate limiting with audit to AuthController (AUTH-02)

### Phase 61: Dead Code Cleanup
**Goal**: The codebase contains no controller stubs, no copropriete/syndic vocabulary in demo data, and no unaddressed dead files — every file either works or is documented as intentionally deferred
**Depends on**: Phase 59, Phase 60
**Requirements**: CLEAN-01, CLEAN-02, CLEAN-03
**Success Criteria** (what must be TRUE):
  1. Every controller method that previously returned a stub response (e.g., `["status" => "not implemented"]` or similar placeholder) either has a real implementation or has been removed — zero stubs remain in production controller code
  2. All demo seed files contain no references to "copropriete", "syndic", "lot", "copropriétaire", or equivalent vocabulary — seeds use AG/assembly terminology exclusively
  3. All files identified during the dead code audit are either deleted (with no test regressions) or have a code comment explaining why they are retained — no undocumented dead files remain
**Plans**: TBD

## Progress

**Execution Order:** 58 -> 59 (parallel with 60) -> 61

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 58. WebSocket to SSE Rename | 2/2 | Complete    | 2026-03-31 |
| 59. Vote and Quorum Edge Cases | 2/2 | Complete    | 2026-03-31 |
| 60. Session, Import, and Auth Edge Cases | 2/3 | In Progress|  |
| 61. Dead Code Cleanup | 0/TBD | Not started | - |
