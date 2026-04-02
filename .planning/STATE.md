---
gsd_state_version: 1.0
milestone: v8.0
milestone_name: Account & Hardening
status: roadmapped
stopped_at: Roadmap created — ready to plan Phase 71
last_updated: "2026-04-02T08:00:00.000Z"
last_activity: 2026-04-02 -- v8.0 roadmap created (5 phases, 71-75)
progress:
  total_phases: 5
  completed_phases: 0
  total_plans: 0
  completed_plans: 0
  percent: 0
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-02)

**Core value:** Self-hosted voting platform with legal compliance for French general assemblies
**Current focus:** v8.0 Account & Hardening — Phase 71 next

## Current Position

Phase: Phase 71 — Mon Compte (not started)
Plan: —
Status: Roadmap defined, awaiting first plan
Last activity: 2026-04-02 — v8.0 roadmap created (5 phases: 71-75)

Progress: [░░░░░░░░░░] 0%

## Accumulated Context

### Decisions

- [v7.0]: 4 phases (67-70) shipped — PV PDF, email queue worker, setup page, password reset
- [v7.0 audit]: Nginx routing fix for /reset-password and /setup discovered and applied
- [v7.0]: HMAC-SHA256 token pattern established (VoteTokenService + PasswordResetService)
- [v7.0]: HTML controller pattern (no AbstractController, uses HtmlView::render())
- [v8.0 roadmap]: Phase 74 (CI Hardening) and Phase 75 (Coverage & Observability) have no dependency on earlier v8.0 phases — can be planned and executed in parallel if needed

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
- tenant_settings table for persisted config (used by SMTP, session timeout target)

### Known Tech Debt Carried Forward

- Controller coverage at 64.6% (exit()-based controllers are structural ceiling) — DEBT-01 targets this
- admin.js KPI load failure catch is silent (non-blocking, admin-only) — DEBT-02 targets this
- E2E seed data (04_e2e.sql) not loaded in CI e2e job — DEBT-03 targets this
- Migration idempotency check is local-only, not CI-gated — DEBT-04 targets this

### Pending Todos

None.

### Blockers/Concerns

None.

## Session Continuity

Last session: 2026-04-02T08:00:00.000Z
Stopped at: Roadmap created — next step is /gsd:plan-phase 71
Resume file: None
