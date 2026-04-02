---
gsd_state_version: 1.0
milestone: v9.0
milestone_name: Compliance & Robustness
status: roadmapped
stopped_at: Roadmap created — ready to plan Phase 76
last_updated: "2026-04-02T12:00:00.000Z"
last_activity: 2026-04-02 -- v9.0 roadmap created (5 phases, 76-80)
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
**Current focus:** v9.0 Compliance & Robustness — Phase 76 next

## Current Position

Phase: 76 (not started)
Plan: —
Status: Roadmap defined, ready to plan
Last activity: 2026-04-02 — v9.0 roadmap created

Progress: [░░░░░░░░░░] 0% (0/5 phases)

## Phase Map

| Phase | Name | Requirements | Status |
|-------|------|--------------|--------|
| 76 | Procuration PDF | LEGAL-01 | Not started |
| 77 | RGPD Compliance | LEGAL-02, LEGAL-03, LEGAL-04 | Not started |
| 78 | Data Integrity Locks | DATA-01, DATA-02 | Not started |
| 79 | SSE & Async Robustness | FE-01, FE-03, FE-04 | Not started |
| 80 | Pagination & Quality | FE-02, QUAL-01, QUAL-02 | Not started |

## Accumulated Context

### Decisions

- [v9.0]: 5 phases derived from 12 requirements — LEGAL split into PDF (76) and RGPD (77), DATA together (78), FE split into SSE/async (79) and pagination (80 combined with QUAL)
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
- Dompdf for PDF generation (MeetingReportService) — reuse for procuration PDF
- ProxiesService with proxy chain validation (has TOCTOU issue — Phase 78 target)
- lockForUpdate() in MeetingRepository (5 locations — expand in Phase 78)
- SSE EventSource in operator-realtime.js, hub.js, vote.js (cleanup target Phase 79)
- audit.js, meetings.js, members.js — list endpoints without pagination (Phase 80 target)

### Known Tech Debt Addressed in v9.0

- ProxiesService TOCTOU race condition in proxy chain validation (DATA-02, Phase 78)
- SSE EventSource listeners not cleaned up on page unload (FE-01, Phase 79)
- No pagination on audit/meeting/member list endpoints (FE-02, Phase 80)
- PV can be re-exported after validation — no immutable snapshot (QUAL-01, Phase 80)
- ARIA labels sporadic coverage (QUAL-02, Phase 80)

### Pending Todos

None.

### Blockers/Concerns

None.

## Session Continuity

Last session: 2026-04-02T12:00:00.000Z
Stopped at: Roadmap created for v9.0 — run /gsd:plan-phase 76 to start
Resume file: None
