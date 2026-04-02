---
gsd_state_version: 1.0
milestone: v8.0
milestone_name: Account & Hardening
status: executing
stopped_at: Phase 71 complete, Phase 72 next
last_updated: "2026-04-02T10:00:00.000Z"
last_activity: 2026-04-02 -- Phase 71 complete, continuing v8.0
progress:
  total_phases: 5
  completed_phases: 1
  total_plans: 1
  completed_plans: 1
  percent: 20
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-02)

**Core value:** Self-hosted voting platform with legal compliance for French general assemblies
**Current focus:** v8.0 Account & Hardening — Phase 72 next

## Current Position

Phase: 72 (Security Config) — Not started
Plan: —
Status: Phase 71 complete, proceeding to Phase 72
Last activity: 2026-04-02 — Phase 71 Mon Compte completed

Progress: [██░░░░░░░░] 20%

## Accumulated Context

### Decisions

- [v7.0]: 4 phases (67-70) shipped — PV PDF, email queue worker, setup page, password reset
- [v7.0 audit]: Nginx routing fix for /reset-password and /setup discovered and applied
- [v7.0]: HMAC-SHA256 token pattern established (VoteTokenService + PasswordResetService)
- [v7.0]: HTML controller pattern (no AbstractController, uses HtmlView::render())
- [v8.0]: Phase 71 Mon Compte — /account page with profile view + self-service password change
- [v8.0]: AccountController follows same HTML controller pattern (HtmlView::render, AccountRedirectException)
- [v8.0]: Phases 74-75 (tech debt) independent of 71-73

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

Last session: 2026-04-02T10:00:00.000Z
Stopped at: Phase 71 complete, Phase 72 next
Resume file: None
