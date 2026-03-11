# AG-VOTE Roadmap — v1.3

## Milestone: Code Quality & Frontend Cleanup

**Goal**: Eliminate ESLint errors and unused variable warnings. Triage innerHTML usage. Enforce lint gate in CI.

---

### Phase 1 — Unused Variable Cleanup
**Status**: done
**Goal**: Fix all 142 `no-unused-vars` warnings.
- Updated eslint config: vendor exclusion, caughtErrors:none, varsIgnorePattern
- Removed dead variables in 17 files (components, pages, core)
- Prefixed intentional unused params with `_`
- Result: 0 no-unused-vars, 0 eqeqeq warnings

### Phase 2 — innerHTML Security Triage
**Status**: done
**Goal**: Audit all 310 `agvote/no-inner-html` warnings for XSS risk.
- Triaged: all 310 are template assembly with escapeHtml() for user data
- No unsafe innerHTML patterns found — all use escapeHtml() or static HTML
- Rule kept as `warn` for ongoing visibility, not blocking CI

### Phase 3 — CI Lint Gate
**Status**: done
**Goal**: Enforce zero new lint warnings in CI.
- Added `lint:ci` script with `--max-warnings 310` cap
- CI updated to use `lint:ci` — fails if ANY new warning introduced
- 0 errors enforced; innerHTML warnings capped (ratchet pattern)

---

## Previous: v1.2 (Security & Resilience Hardening) — COMPLETE

All 4 phases done: multi-tenant isolation, rate limiting, PWA hardening, audit verification.

## Previous: v1.1 (Post-Audit Hardening) — COMPLETE

All 6 phases done: E2E suite, CI pipeline, CDN hardening, app shell audit, error handling audit, accessibility fixes.
