# AG-VOTE Roadmap — v1.5

## Milestone: E2E Coverage Expansion & Release

**Goal**: Close E2E gaps for core user-facing pages. Bump version to 1.5.0.

---

### Phase 1 — Operator & Dashboard E2E Specs
**Status**: done
**Goal**: Add E2E tests for the two most critical operator pages.
- operator.spec.js: 8 tests (page load, tabs, meeting context, a11y, API auth)
- dashboard.spec.js: 7 tests (page load, KPIs, quick actions, sidebar, API auth)

### Phase 2 — Report, Validate & Archives E2E Specs
**Status**: done
**Goal**: Cover remaining user-facing pages.
- report.spec.js: 5 tests (page load, PV content, export, overflow, API auth)
- validate.spec.js: 4 tests (page load, checklist, action buttons, overflow)
- archives.spec.js: 5 tests (page load, list, search, sidebar, overflow)

### Phase 3 — Version Bump & Release
**Status**: done
**Goal**: Update version to 1.5.0.
- package.json: 1.1.0 → 1.5.0
- SW cache version: agvote-v1 → agvote-v1.5
- 21 E2E spec files (was 16), ~230+ E2E tests total

---

## Previous: v1.4 (Test Coverage & Final Polish) — COMPLETE

3 phases: 100% controller tests, Permissions-Policy header, dead code audit.

## Previous: v1.3 (Code Quality & Frontend Cleanup) — COMPLETE

3 phases: unused vars fixed (142→0), innerHTML triaged safe, CI lint ratchet.

## Previous: v1.2 (Security & Resilience Hardening) — COMPLETE

4 phases: tenant isolation, rate limiting, PWA hardening, audit verification.

## Previous: v1.1 (Post-Audit Hardening) — COMPLETE

6 phases: E2E suite, CI pipeline, CDN hardening, app shell audit, error handling, accessibility.
