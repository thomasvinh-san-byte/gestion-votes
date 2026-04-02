---
gsd_state_version: 1.0
milestone: v9.0
milestone_name: Compliance & Robustness
status: defining
stopped_at: Defining requirements
last_updated: "2026-04-02T12:00:00.000Z"
last_activity: 2026-04-02 -- Milestone v9.0 started
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
**Current focus:** Defining v9.0 requirements

## Current Position

Phase: Not started (defining requirements)
Plan: —
Status: Defining requirements
Last activity: 2026-04-02 — Milestone v9.0 started

Progress: [░░░░░░░░░░] 0%

## Accumulated Context

### Decisions

- [v8.0]: Page Mon Compte, 2-step confirmation, configurable timeout, vote session resume, CI hardening, coverage >70%
- [v8.0]: confirm_password field for critical admin ops (password_verify before delete/set_password)
- [v8.0]: AuthMiddleware::getSessionTimeout() reads from tenant_settings, cached per-request
- [v7.0]: HMAC-SHA256 token pattern, HTML controller pattern, nginx routing for PHP controllers

### Existing Infrastructure

- AccountController + /account page (Phase 71)
- 2-step confirmation on AdminController (Phase 72)
- Configurable session timeout via tenant_settings (Phase 72)
- Vote session resume via return_to param (Phase 73)
- CI e2e seed data + migration idempotency gate (Phase 74)
- 3 exit()-based controllers refactored with PHPUNIT_RUNNING exceptions (Phase 75)
- Dompdf for PDF generation (MeetingReportService)
- ProxiesService with proxy chain validation (has TOCTOU issue)
- lockForUpdate() in MeetingRepository (5 locations, needs expansion)

### Known Tech Debt Carried Forward

- ProxiesService TOCTOU race condition in proxy chain validation
- SSE EventSource listeners not cleaned up on page unload
- No pagination on audit/meeting/member list endpoints
- PV can be re-exported after validation (no immutable snapshot)
- ARIA labels sporadic coverage

### Pending Todos

None.

### Blockers/Concerns

None.

## Session Continuity

Last session: 2026-04-02T12:00:00.000Z
Stopped at: Defining v9.0 requirements
Resume file: None
