# AG-VOTE Roadmap — v1.1

## Milestone: Post-Audit Hardening

**Goal**: Harden the application after UX/UI audit fixes, establish automated testing, and address technical concerns identified in the codebase mapping.

---

### Phase 1 — E2E Test Suite Hardening [REQ-001]
**Status**: done
**Goal**: Harden existing Playwright suite (14 specs) and add audit regression coverage.
- [x] Extracted shared helpers (loginAsOperator/Admin/Voter, constants) to `tests/e2e/helpers.js`
- [x] Updated all 14 original specs to use shared helpers (DRY)
- [x] Added audit regression spec (`audit-regression.spec.js`) — 13 tests covering P1/P2/P3 fixes
- [x] Added mobile viewport regression spec (`mobile-viewport.spec.js`) — 13 tests (375x812 + 768x1024)
- [x] Added `package.json` with Playwright dependency and test scripts
- [x] All 197 tests across 16 files parse correctly in headless mode

### Phase 2 — CI Pipeline Expansion [REQ-002]
**Status**: pending
**Goal**: Expand existing CI workflow with linting and E2E.
- Add ESLint + PHPStan jobs to `docker-build.yml`
- Add Playwright E2E execution in CI (headless)
- Cache Composer and npm dependencies
- Ensure PR checks include all quality gates

### Phase 3 — CDN Hardening [REQ-003, REQ-004]
**Status**: pending
**Goal**: Eliminate CDN single points of failure.
- Vendor Chart.js and HTMX locally
- Add SRI hashes if keeping CDN
- Self-host Google Fonts
- Verify offline PWA still works

### Phase 4 — App Shell Deduplication [REQ-005]
**Status**: pending
**Goal**: Single source of truth for shared layout.
- Analyze sidebar/header duplication across pages
- Design template mechanism (PHP include vs JS injection)
- Implement and migrate all pages
- Verify no visual regressions

### Phase 5 — Frontend Error Handling [REQ-006]
**Status**: pending
**Goal**: No silent fetch failures anywhere.
- Audit all fetch() calls across 29 page modules
- Add consistent error handling pattern
- Ensure toast notifications on failures
- Add network error recovery where appropriate

### Phase 6 — Accessibility & Performance [REQ-007, REQ-008]
**Status**: pending
**Goal**: WCAG AA compliance and improved load times.
- ARIA audit on Web Components
- Color contrast verification
- Keyboard navigation testing
- Asset concatenation for production
- Lazy loading non-critical JS
