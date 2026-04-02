---
gsd_state_version: 1.0
milestone: v8.0
milestone_name: Account & Hardening
status: executing
stopped_at: Completed 75-coverage-observability/75-02-PLAN.md
last_updated: "2026-04-02T06:28:22.965Z"
last_activity: 2026-04-02
progress:
  total_phases: 9
  completed_phases: 9
  total_plans: 13
  completed_plans: 13
  percent: 99
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-01)

**Core value:** Self-hosted voting platform with legal compliance for French general assemblies
**Current focus:** Phase 75 — coverage-observability

## Current Position

Phase: 75
Plan: Not started
Status: Executing Phase 75
Last activity: 2026-04-02

Progress: [██████████] 99%

## Accumulated Context

### Decisions

- [v7.0 roadmap]: 4 phases derived from 11 requirements — each feature is an independent vertical slice
- [v7.0 roadmap]: Phase 70 (Reset Password) depends on Phase 68 (Email Queue) for reliable email delivery
- [v7.0 roadmap]: PV generation builds on existing MeetingReportService + Dompdf (already installed)
- [v7.0 roadmap]: Email queue builds on existing EmailQueueService::processQueue() — just needs cron worker
- [v7.0 roadmap]: Setup page guard checks admin user count — no config file needed
- [Phase 67-pv-officiel-pdf]: Secretary signature blank line only — no secretary_name column; loi 1901 handwritten practice
- [Phase 67-pv-officiel-pdf]: Inline mode uses separate ?inline=1 flag (not ?preview=1) — preview adds watermark, inline final PV must not
- [Phase 67-pv-officiel-pdf]: Task 2 (visual verification) deferred — user chose Continue without verifying
- [Phase 68-email-queue-worker]: Command tests validate configuration only (no execute() call) — execute() needs live DB via Application::config()
- [Phase 68-email-queue-worker]: Repository retry tests use file_get_contents() pattern to assert SQL patterns without a database connection
- [Phase 68-email-queue-worker]: Added --reminders to supervisord.conf so processReminders() runs every cycle alongside processQueue()
- [Phase 69-initial-setup]: SetupRedirectException pattern: redirect throws exception in PHPUNIT_RUNNING for testable redirects without process exit
- [Phase 69-initial-setup]: No CSRF on /setup: pre-auth first-run page, hasAnyAdmin() guard is sufficient idempotency protection
- [Phase 74]: E2E seed loaded via docker exec -i stdin pipe into agvote-db psql after app healthy — no temp files needed
- [Phase 74]: migrate-check uses dedicated agvote_migration_ci credentials, full two-pass validation replaces --syntax-only grep scan
- [Phase 75-coverage-observability]: Only overwrite KPI cards still showing '-' placeholder — cards that loaded keep their values (partial failure case)
- [Phase 75-01]: FileServedOkException and EmailPixelSentException follow AccountRedirectException pattern — tests call serve() directly (not via handle()) to bypass AbstractController RuntimeException catch
- [Phase 75-01]: coverage-check.sh CTRL_THRESHOLD raised from 60 to 70 after exit() refactoring removes structural ceiling

### Existing Infrastructure

- MeetingReportService exists with Dompdf for PV generation (partially implemented)
- EmailQueueService::processQueue() exists — needs cron wrapper
- Symfony Mailer installed and configured (Phase 62)
- AuthController + AuthMiddleware complete with bcrypt/argon2 password hashing
- Login page already built (Phase 44 rebuild)

### Known Tech Debt Carried Forward

- Controller coverage ceiling removed — exit()-based serve() and outputPixel() now testable via PHPUNIT_RUNNING exception pattern
- CI e2e job runs chromium only; mobile-chrome/tablet are local-only
- Migration idempotency check is local-only, not CI-gated

### Pending Todos

None.

### Blockers/Concerns

None.

## Session Continuity

Last session: 2026-04-02T06:22:48.985Z
Stopped at: Completed 75-coverage-observability/75-02-PLAN.md
Resume file: None
