---
gsd_state_version: 1.0
milestone: v5.1
milestone_name: Operational Hardening
status: executing
stopped_at: Completed 61-01-PLAN.md
last_updated: "2026-03-31T10:37:05.399Z"
last_activity: "2026-03-31 — Phase 60 Plan 01 complete: Invalid transition returns 422 with structured detail; live-session delete returns 409 with close-first hint"
progress:
  total_phases: 4
  completed_phases: 4
  total_plans: 8
  completed_plans: 8
  percent: 100
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-31)

**Core value:** Self-hosted voting platform with legal compliance for French general assemblies
**Current focus:** Phase 58 complete — Phase 59/60 next (parallel: vote/quorum + session/import/auth)

## Current Position

Phase: 60 of 61 (Session Import and Auth Edge Cases) — IN PROGRESS
Plan: 01 complete — 60-02 and beyond continuing
Status: In progress
Last activity: 2026-03-31 — Fixed admin login: removed duplicate rate limit on auth_login route (Phase 60 regression)

Progress: [██████████] 100%

## Accumulated Context

### Decisions

- [v5.1 roadmap]: Phase 59 and 60 can run in parallel after 58 — they target independent subsystems (vote/quorum vs. session/import/auth)
- [v5.1 roadmap]: Phase 61 depends on both 59 and 60 to be complete before cleanup
- [Phase 58-websocket-to-sse-rename]: Renamed Redis queue keys from ws:event_queue to sse:event_queue for consistent terminology
- [Phase 58-websocket-to-sse-rename]: bootstrap.php WEBSOCKET AUTH TOKEN section header renamed to SSE AUTH TOKEN (residual auto-fixed during final grep verification)
- [Phase 59-vote-and-quorum-edge-cases]: QUOR-01 already test-locked pre-execution — no changes to QuorumEngineTest.php required
- [Phase 59-vote-and-quorum-edge-cases]: QUOR-02: SSE broadcast verified via 200 response — broadcast silently fails in test env, 200 proves path ran without blocking HTTP response
- [Phase 59-01]: audit_log('vote_token_reuse') fires for both token_expired and token_already_used — covers all suspicious reuse patterns
- [Phase 59-01]: BallotsService constructor dependencies (AttendancesService, ProxiesService) require their repos injected in unit tests to avoid null-PDO RuntimeException
- [Phase 60-02]: Empty emails skipped in duplicate detection — blank field is not an address, avoids false-positive 422
- [Phase 60-02]: mb_detect_encoding strict mode with ['UTF-8','Windows-1252','ISO-8859-1'] order ensures UTF-8 preferred; temp file (csv_enc_ prefix) preserves fgetcsv API
- [Phase 60-02]: checkDuplicateEmails extracted to private static helper shared by membersCsv and membersXlsx
- [Phase 60-01]: api_fail() preferred over self::deny() in requireTransition() — deny() hides extras behind debug flag, api_fail() always includes structured fields
- [Phase 60-01]: Invalid transitions are not audit-logged (normal user input errors, not security events)
- [Phase 60-01]: live-specific meeting_live_cannot_delete guard precedes generic meeting_not_draft check in deleteMeeting()
- [Phase 60-03]: session_expired uses static flag in AuthMiddleware consumed in deny() — avoids parameter chain changes
- [Phase 60-03]: ApiResponseException used directly in login() for 429 — api_fail() cannot set custom Retry-After header
- [Phase 60-03]: ControllerTestCase::setUp() now resets APP_AUTH_ENABLED=0 to prevent env var leakage from AuthMiddlewareTest
- [Phase 61-dead-code-cleanup]: app/Command CLI tools retained without unit tests — documented via inline comments
- [Phase 61-dead-code-cleanup]: phpunit.xml app/WebSocket corrected to app/SSE (Phase 58 rename artifact)
- [Phase 61-dead-code-cleanup]: CLEAN-01 was pre-satisfied: zero stubs in 41 controller files, no changes required

### Known Tech Debt Carried Forward
- Controller coverage at 64.6% (3 exit()-based controllers are structural ceiling)
- CI e2e job runs chromium only; mobile-chrome/tablet are local-only
- Migration idempotency check is local-only, not CI-gated
- 04_e2e.sql seed data not loaded in CI e2e job

### Pending Todos

None.

### Blockers/Concerns

None.

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 260331-7s9 | Remove voting weight/ponderation from UI and sample CSV | 2026-03-31 | 7cb5378 | [260331-7s9-remove-voting-weight-ponderation-from-ui](./quick/260331-7s9-remove-voting-weight-ponderation-from-ui/) |
| 260331-854 | Wizard field layout and time input modernization | 2026-03-31 | e655a46 | [260331-854-wizard-field-layout-and-time-input-moder](./quick/260331-854-wizard-field-layout-and-time-input-moder/) |
| 260331-8wf | Modernize project README.md | 2026-03-31 | 868c43a | [260331-8wf-modernize-project-readme-md](./quick/260331-8wf-modernize-project-readme-md/) |
| 260331-901 | Modernize all documentation files | 2026-03-31 | c4e68b1 | [260331-901-modernize-all-docs-rich-french-no-em-das](./quick/260331-901-modernize-all-docs-rich-french-no-em-das/) |
| 260331-ez9 | Fix admin login — double rate limit on auth_login | 2026-03-31 | c3b1add2 | [260331-ez9-fix-admin-login-failure](./quick/260331-ez9-fix-admin-login-failure/) |
| 260331-ffw | Full project audit — gitignore, env, CSS tokens, git hygiene | 2026-03-31 | 4625f6ca | [260331-ffw-full-project-audit-bugs-cleanup-config-i](./quick/260331-ffw-full-project-audit-bugs-cleanup-config-i/) |
| 260331-fya | Second pass audit — remaining CSS tokens, route cleanup | 2026-03-31 | 00fe92f5 | [260331-fya-second-pass-audit-remaining-issues](./quick/260331-fya-second-pass-audit-remaining-issues/) |
| 260331-g8a | Critical path audit — API functional, operator null guards | 2026-03-31 | 3d504fe2 | [260331-g8a-critical-path-audit-functional-visual-on](./quick/260331-g8a-critical-path-audit-functional-visual-on/) |

## Session Continuity

Last session: 2026-03-31T10:27:53.314Z
Stopped at: Completed 61-01-PLAN.md
Resume file: None
