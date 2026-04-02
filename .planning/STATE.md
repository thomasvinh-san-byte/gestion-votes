---
gsd_state_version: 1.0
milestone: v9.0
milestone_name: Compliance & Robustness
status: executing
stopped_at: Completed 78-data-integrity-locks/78-01-PLAN.md
last_updated: "2026-04-02T08:06:00.000Z"
last_activity: 2026-04-02 -- Phase 78 Plan 01 complete: TOCTOU races closed via FOR UPDATE proxy and motion row locks
progress:
  total_phases: 5
  completed_phases: 3
  total_plans: 4
  completed_plans: 4
  percent: 100
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-01)

**Core value:** Self-hosted voting platform with legal compliance for French general assemblies
**Current focus:** Phase 78 — data-integrity-locks

## Current Position

Phase: 78 (data-integrity-locks) — COMPLETE
Plan: 1 of 1
Status: Phase 78 complete
Last activity: 2026-04-02 -- Phase 78 Plan 01 complete: TOCTOU races closed via FOR UPDATE proxy and motion row locks

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
- [Phase 77-rgpd-compliance]: Direct PDO injection in RgpdExportService — export spans multiple tables with JOINs not covered by existing single-table repos
- [Phase 77-rgpd-compliance]: All authenticated roles allowed for RGPD export — any logged-in user may export their own data (Article 20 portability)
- [Phase 78-data-integrity-locks]: hasActiveProxyForUpdate uses selectAll+FOR UPDATE (not scalar count) — consistent with countActiveAsGiverForUpdate pattern
- [Phase 78-data-integrity-locks]: VotePublicController motion lock placed before consumeIfValid — ensures lock acquired before irreversible token consumption
- [Phase 78-data-integrity-locks]: Source-level assertion for DATA-02 in DataIntegrityLocksTest — controller uses exit() so cannot be fully executed in unit tests

### Existing Infrastructure

- MeetingReportService exists with Dompdf for PV generation (partially implemented)
- EmailQueueService::processQueue() exists — needs cron wrapper
- Symfony Mailer installed and configured (Phase 62)
- AuthController + AuthMiddleware complete with bcrypt/argon2 password hashing
- Login page already built (Phase 44 rebuild)

### Known Tech Debt Carried Forward

- Controller coverage at 64.6% (3 exit()-based controllers are structural ceiling)
- CI e2e job runs chromium only; mobile-chrome/tablet are local-only
- Migration idempotency check is local-only, not CI-gated

### Pending Todos

None.

### Blockers/Concerns

None.

## Session Continuity

Last session: 2026-04-02T08:06:00Z
Stopped at: Completed 78-data-integrity-locks/78-01-PLAN.md
Resume file: None
