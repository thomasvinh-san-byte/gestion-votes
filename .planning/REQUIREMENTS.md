# AG-VOTE Requirements — v1.1 (Post-Audit Hardening)

## Milestone Context

Following the UX/UI audit (P1/P2/P3 fixes applied), this milestone focuses on **hardening, testing, and quality improvements** identified during the audit and codebase mapping.

---

## v1 — Must Have

### REQ-001: E2E Test Suite Hardening
Existing Playwright suite (14 specs in `tests/e2e/`) needs hardening:
- Add mobile viewport regression tests
- Add UX audit assertion coverage (P1/P2/P3 fixes)
- Ensure all specs pass reliably in headless mode
- Add operator live session flow tests if missing

### REQ-002: CI Pipeline Expansion
Existing CI (`.github/workflows/docker-build.yml`) runs PHPUnit + syntax check + Docker build. Expand:
- Add ESLint + PHPStan to CI pipeline
- Add Playwright E2E tests to CI (headless)
- Cache Composer and npm dependencies for speed

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

### REQ-010: Database Integration Test Expansion
Expand existing integration tests (3 files in `tests/Integration/`) with more coverage.

---

## Out of Scope

- Major feature additions (new voting modes, new report types)
- Framework migration (React, Vue, Laravel, Symfony)
- Multi-database support (MySQL, SQLite)
- Mobile native app
