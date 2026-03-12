# AG-VOTE — Project State

## Current Position

- **Version**: 2.0.0 (target)
- **Milestone**: v2.0 (UI Redesign — Acte Officiel)
- **Phase**: Not started (defining requirements)
- **Last activity**: 2026-03-12 — Milestone v2.0 started

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-12)

**Core value:** Self-hosted voting platform with legal compliance for French general assemblies
**Current focus:** UI Redesign — align all pages with wireframe v3.19.2

## Milestone History

### v1.5 — E2E Coverage Expansion & Release (COMPLETE)

| Phase | Status | Summary |
|-------|--------|---------|
| 1. Operator & Dashboard E2E | done | 15 tests across 2 new specs |
| 2. Report, Validate & Archives E2E | done | 14 tests across 3 new specs |
| 3. Version Bump | done | 1.1.0 → 1.5.0, SW cache v1.5 |

### v1.4 — Test Coverage & Final Polish (COMPLETE)

| Phase | Status | Summary |
|-------|--------|---------|
| 1. NotificationsController Test | done | 38/38 controllers tested |
| 2. Permissions-Policy Header | done | Header + E2E security tests |
| 3. Dead Code & TODO Audit | done | No dead code found |

### v1.3 — Code Quality & Frontend Cleanup (COMPLETE)

| Phase | Status | Summary |
|-------|--------|---------|
| 1. Unused Variable Cleanup | done | 142 warnings → 0 |
| 2. innerHTML Security Triage | done | 310 usages audited safe |
| 3. CI Lint Gate | done | Ratchet pattern (max 310) |

### v1.2 — Security & Resilience Hardening (COMPLETE)

| Phase | Status | Summary |
|-------|--------|---------|
| 1. Multi-Tenant DB Isolation | done | Already implemented |
| 2. Rate Limiting Activation | done | Already implemented |
| 3. PWA & Service Worker Hardening | done | Precache + 5s timeout |
| 4. Audit Log Verification | done | audit_verify endpoint |

### v1.1 — Post-Audit Hardening (COMPLETE)

| Phase | Status | Summary |
|-------|--------|---------|
| 1. E2E Test Suite Hardening | done | 197 tests, shared helpers |
| 2. CI Pipeline Expansion | done | ESLint + PHPStan + Playwright |
| 3. CDN Hardening | done | Vendored HTMX + Chart.js |
| 4. App Shell Deduplication | done | Already optimal |
| 5. Frontend Error Handling | done | Already handled |
| 6. Accessibility & Performance | done | Focus trap, contrast, ARIA |

## Open Issues

- wizard.js TODO: meeting creation API not yet wired (intentional)
