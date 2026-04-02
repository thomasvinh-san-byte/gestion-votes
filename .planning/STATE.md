---
gsd_state_version: 1.0
milestone: v8.0
milestone_name: Account & Hardening
status: executing
stopped_at: Completed 72-security-config/72-01-PLAN.md
last_updated: "2026-04-02T05:38:20Z"
last_activity: 2026-04-02 -- Phase 72 Plan 01 complete — admin delete/set_password require confirm_password
progress:
  total_phases: 9
  completed_phases: 5
  total_plans: 9
  completed_plans: 8
  percent: 25
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-02)

**Core value:** Self-hosted voting platform with legal compliance for French general assemblies
**Current focus:** Phase 72 — security-config

## Current Position

Phase: 72 (security-config) — EXECUTING
Plan: 2 of 2
Status: Executing Phase 72
Last activity: 2026-04-02 -- Phase 72 Plan 01 complete

Progress: [███░░░░░░░] 25%

## Accumulated Context

### Decisions

- [v7.0]: 4 phases (67-70) shipped — PV PDF, email queue worker, setup page, password reset
- [v7.0 audit]: Nginx routing fix for /reset-password and /setup discovered and applied
- [v7.0]: HMAC-SHA256 token pattern established (VoteTokenService + PasswordResetService)
- [v7.0]: HTML controller pattern (no AbstractController, uses HtmlView::render())
- [v8.0]: Phase 71 Mon Compte — /account page with profile view + self-service password change
- [v8.0]: AccountController follows same HTML controller pattern (HtmlView::render, AccountRedirectException)
- [v8.0]: Phases 74-75 (tech debt) independent of 71-73
- [Phase 72-01]: requireConfirmation() fires before other checks — confirmation gate is outermost guard on critical admin actions
- [Phase 72-01]: findActiveById() updated to SELECT password_hash — required by confirmation check

### Existing Infrastructure

- AccountController + account_form.php (Phase 71)
- PasswordResetService + PasswordResetController (Phase 70)
- SetupController with hasAnyAdmin() guard (Phase 69)
- Email queue worker via supervisord (Phase 68)
- AuthController + AuthMiddleware with bcrypt/argon2 hashing
- CSRF middleware (synchronizer token pattern)
- InputValidator with fluent API
- RateLimiter (file-based fallback when Redis unavailable)
- 50+ audit event types with SHA-256 chain verification
- tenant_settings table for persisted config

### Known Tech Debt Carried Forward

- Controller coverage at 64.6% (exit()-based controllers are structural ceiling) — DEBT-01
- admin.js KPI load failure catch is silent — DEBT-02
- E2E seed data (04_e2e.sql) not loaded in CI e2e job — DEBT-03
- Migration idempotency check is local-only, not CI-gated — DEBT-04

### Pending Todos

None.

### Blockers/Concerns

None.

## Session Continuity

Last session: 2026-04-02T05:38:20Z
Stopped at: Completed 72-security-config/72-01-PLAN.md
Resume file: None
