# AG-VOTE Roadmap — v1.1

## Milestone: Post-Audit Hardening

**Goal**: Harden the application after UX/UI audit fixes, establish automated testing, and address technical concerns identified in the codebase mapping.

---

### Phase 1 — E2E Test Suite [REQ-001]
**Status**: pending
**Goal**: Committed Playwright test suite covering all pages and critical flows.
- Set up Playwright as dev dependency
- Create test fixtures and helpers
- Write tests for all 17+ pages (load, screenshot, DOM assertions)
- Add operator session flow tests
- Add mobile viewport tests

### Phase 2 — CI Pipeline [REQ-002]
**Status**: pending
**Goal**: Automated checks on every push.
- GitHub Actions workflow for PHPUnit
- ESLint + PHPStan in CI
- Playwright tests in CI (headless)
- Dependency caching

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
