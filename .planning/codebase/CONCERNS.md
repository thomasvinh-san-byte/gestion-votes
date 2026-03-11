# AG-VOTE Technical Concerns

## Security

- **CDN dependencies**: Chart.js and HTMX loaded from CDNs without SRI (Subresource Integrity) hashes
- **Google Fonts**: External CSS dependency blocks rendering if Google is slow/unavailable
- **Demo mode bypass**: `APP_ENV=demo` disables auth entirely — must ensure this never runs in production
- **CSRF protection**: Present but relies on middleware ordering
- **innerHTML usage**: ESLint custom rule `agvote/no-inner-html` mitigates XSS, but some older code may still use it

## Technical Debt

- **No JS bundler**: Each page loads many individual script files (10+ HTTP requests per page). No tree-shaking, no code splitting
- **`var` keyword**: Used throughout JS instead of `const`/`let`. Hoisting bugs possible
- **Global namespaces**: `Shared`, `Auth`, `Utils` pollute global scope. No module isolation at runtime
- **Inconsistent error handling**: Some fetch calls lack error handling. Some API failures silently swallowed
- **Mixed CDN + vendor**: Some libs vendored (marked.js), others on CDN (Chart.js, HTMX). No unified strategy
- **No TypeScript**: Large JS codebase (~30 page modules + 20 components) with no type safety
- **Inline HTML in pages**: Shared app shell (sidebar, header) is duplicated across all `.htmx.html` files

## Performance

- **No asset bundling**: Individual CSS and JS files loaded per page (no concatenation/bundling in dev)
- **Google Fonts blocking**: External stylesheet link blocks first paint
- **No lazy loading**: All page JS loaded eagerly
- **DomPDF memory**: PDF generation for large meeting reports can be memory-intensive

## Testing Gaps

- **No frontend tests**: Zero JS unit or integration tests
- **No E2E suite**: Playwright used ad-hoc but no committed test suite
- **No CI/CD pipeline**: Tests run manually via Makefile
- **No database integration tests**: All tests mock the data layer

## Accessibility

- **No ARIA audit**: Custom Web Components may lack proper ARIA attributes
- **Keyboard navigation**: Not systematically verified across pages
- **Color contrast**: Design system tokens not verified against WCAG AA
- **Screen reader support**: No testing documented

## Code Quality

- **Large controllers**: Some controllers (e.g., `OperatorController`) likely handle many actions
- **38 controllers**: High count suggests potential for consolidation
- **Template duplication**: Email and report templates may have shared patterns not extracted
- **French-only UI**: No i18n infrastructure for future multi-language support

## Infrastructure

- **Single container**: Nginx + PHP-FPM in one container (supervisord). Not ideal for horizontal scaling
- **Redis single instance**: No clustering or sentinel setup
- **No health check endpoint**: Would be useful for container orchestration
- **File-based SSE fallback**: Can cause issues with multiple PHP-FPM workers
