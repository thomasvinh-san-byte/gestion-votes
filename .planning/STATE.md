---
gsd_state_version: 1.0
milestone: v8.0
milestone_name: Account & Hardening
status: defining
stopped_at: Defining requirements
last_updated: "2026-04-02T08:00:00.000Z"
last_activity: 2026-04-02 -- Milestone v8.0 started
progress:
  total_phases: 0
  completed_phases: 0
  total_plans: 0
  completed_plans: 0
  percent: 0
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-02)

**Core value:** Self-hosted voting platform with legal compliance for French general assemblies
**Current focus:** Defining v8.0 requirements

## Current Position

Phase: Not started (defining requirements)
Plan: —
Status: Defining requirements
Last activity: 2026-04-02 — Milestone v8.0 started

Progress: [░░░░░░░░░░] 0%

## Accumulated Context

### Decisions

- [v7.0]: 4 phases (67-70) shipped — PV PDF, email queue worker, setup page, password reset
- [v7.0 audit]: Nginx routing fix for /reset-password and /setup discovered and applied
- [v7.0]: HMAC-SHA256 token pattern established (VoteTokenService + PasswordResetService)
- [v7.0]: HTML controller pattern (no AbstractController, uses HtmlView::render())

### Existing Infrastructure

- PasswordResetService + PasswordResetController (Phase 70)
- SetupController with hasAnyAdmin() guard (Phase 69)
- Email queue worker via supervisord (Phase 68)
- MeetingReportService with Dompdf PV generation (Phase 67)
- AuthController + AuthMiddleware with bcrypt/argon2 hashing
- CSRF middleware (synchronizer token pattern)
- InputValidator with fluent API
- RateLimiter (file-based fallback when Redis unavailable)
- 50+ audit event types with SHA-256 chain verification

### Known Tech Debt Carried Forward

- Controller coverage at 64.6% (3 exit()-based controllers are structural ceiling)
- admin.js KPI load failure catch is silent (non-blocking, admin-only)
- CI e2e job runs chromium only; mobile-chrome/tablet are local-only
- E2E seed data (04_e2e.sql) not loaded in CI e2e job
- Migration idempotency check is local-only, not CI-gated

### Pending Todos

None.

### Blockers/Concerns

None.

## Session Continuity

Last session: 2026-04-02T08:00:00.000Z
Stopped at: Defining v8.0 requirements
Resume file: None
