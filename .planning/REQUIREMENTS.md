# AG-VOTE Requirements — v1.1 (Post-Audit Hardening)

## Milestone Context

Following the UX/UI audit (P1/P2/P3 fixes applied), this milestone focuses on **hardening, testing, and quality improvements** identified during the audit and codebase mapping.

---

## v1 — Must Have

### REQ-001: Automated E2E Test Suite
Establish a committed Playwright test suite covering all pages and critical user flows.
- Login flow, page navigation, meeting CRUD
- Operator live session (votes, attendance, quorum)
- Mobile viewport regressions
- Replaces ad-hoc `/tmp` test scripts

### REQ-002: CI Pipeline
Set up GitHub Actions (or equivalent) to run PHPUnit + ESLint + PHPStan on every push.
- Fail on test failures, lint errors, static analysis issues
- Cache Composer and npm dependencies

### REQ-003: CDN Fallback / SRI Hashes
Add Subresource Integrity (SRI) hashes for CDN-loaded Chart.js and HTMX.
- Consider vendoring Chart.js and HTMX like marked.js

### REQ-004: Google Fonts Self-Hosting
Eliminate render-blocking Google Fonts CDN dependency.
- Download and self-host font files
- Use `font-display: swap` for better loading

### REQ-005: App Shell Deduplication
Extract shared sidebar/header HTML into a reusable template or include mechanism.
- Reduce duplication across 17+ `.htmx.html` pages
- Consider PHP-rendered shell or JS-injected template

### REQ-006: Frontend Error Handling Audit
Systematically review all `fetch()` calls for proper error handling.
- Ensure toast notifications on failures
- Prevent silent error swallowing

---

## v2 — Should Have

### REQ-007: Accessibility Audit (WCAG AA)
Verify color contrast, ARIA attributes, keyboard navigation across all components.

### REQ-008: Performance Optimization
- Asset bundling/concatenation for production
- Lazy loading for non-critical page JS
- Image optimization

### REQ-009: i18n Infrastructure
Prepare for multi-language support (currently French-only).

### REQ-010: Database Integration Tests
Add tests that run against a real PostgreSQL instance.

---

## Out of Scope

- Major feature additions (new voting modes, new report types)
- Framework migration (React, Vue, Laravel, Symfony)
- Multi-database support (MySQL, SQLite)
- Mobile native app
