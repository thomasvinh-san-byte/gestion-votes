# AG-VOTE Roadmap — v1.2

## Milestone: Security & Resilience Hardening

**Goal**: Close security gaps identified in the codebase audit — multi-tenant isolation, input validation, rate limiting, offline resilience, and audit integrity.

---

### Phase 1 — Multi-Tenant DB Isolation
**Status**: done (already implemented)
**Goal**: Ensure all repository queries enforce tenant_id scope.
- All repository queries already include `tenant_id = :tid` (58+ refs in Motion traits alone)
- BallotRepository verified: all 20+ methods scope by tenant_id

### Phase 2 — Rate Limiting Activation
**Status**: done (already implemented)
**Goal**: Enable application-level rate limiting on sensitive endpoints.
- Rate limiting already configured in routes.php: auth_login (10/5min), ballot_cast (60/min), admin_ops (30/min), csv_import (10/hr)
- api_rate_limit() already wired in api.php dispatch

### Phase 3 — PWA & Service Worker Hardening
**Status**: done
**Goal**: Ensure offline functionality works with vendored assets.
- Added htmx.min.js, chart.umd.js, marked.min.js to SW precache list
- Added 5s AbortController timeout to networkFirst() and networkFirstWithCache()

### Phase 4 — Audit Log Verification
**Status**: done
**Goal**: Verifiable audit trail with integrity checks.
- Created `GET /api/v1/audit_verify` lightweight chain integrity endpoint
- Returns chain_valid, error_count, total_events without full data export
- Added E2E tests for audit_verify auth requirement
- Existing: SHA-256 hash chain via PostgreSQL trigger, DELETE protection trigger

---

## Previous: v1.1 (Post-Audit Hardening) — COMPLETE

All 6 phases done: E2E suite, CI pipeline, CDN hardening, app shell audit, error handling audit, accessibility fixes.
