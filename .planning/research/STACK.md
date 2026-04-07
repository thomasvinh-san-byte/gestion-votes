# Stack Research

**Domain:** Admin panel UI/UX coherence + E2E testing for PHP 8.4 + HTMX application
**Researched:** 2026-04-07
**Confidence:** HIGH (existing setup verified from codebase; library versions verified from npm)

---

## Context: What Already Exists

The project already has a substantial design and testing foundation. This file documents only **new capabilities** needed for v1.1.

**Already in place (do not re-add):**
- `design-system.css` — custom CSS design tokens (OKLCH palette, Bricolage Grotesque / Fraunces / JetBrains Mono fonts, spacing scale, component classes)
- `app.css` — single entrypoint importing design system + page-specific overrides
- `@playwright/test` ^1.50.0 declared in root `package.json` (1.58.2 installed in `tests/e2e/`)
- Full Playwright suite: 18 spec files, multi-browser (Chromium/Firefox/WebKit/mobile), global auth setup, helpers with cookie injection
- HTMX 1.9.12 vendored in `public/assets/vendor/htmx.min.js`
- Chart.js 4.4.1 for analytics

---

## Recommended Stack Additions

### Core Technologies

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| `@playwright/test` | ^1.59.1 | E2E browser automation | Already in use; upgrade from 1.50.0/1.58.2 to get bug fixes and new locator APIs. Latest as of April 2026. No breaking changes within 1.x. |
| Custom CSS design system | existing (v2.0) | Design language | The project already has a complete, well-structured design system with OKLCH tokens. The problem is **broken wiring and inconsistent application**, not missing infrastructure. DO NOT add Tailwind, Bootstrap, or daisyUI — they conflict with the custom token architecture already established. |
| HTMX | 2.0.8 (optional, defer) | Server-driven UI | HTMX 2.0 has been stable since June 2024. The project currently uses 1.9.12. Upgrading provides cleaner event syntax but requires auditing `hx-on` attributes (case-sensitivity changed), DELETE request semantics, and cross-domain config. **Defer unless 1.9 bugs are root cause of broken wiring.** |

### Supporting Libraries

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `@axe-core/playwright` | ^4.10.x | Automated WCAG accessibility checks embedded in Playwright tests | Add to `tests/e2e/` alongside existing `accessibility.spec.js`. Detects contrast failures, missing labels, duplicate IDs without separate tooling. |
| Google Fonts (already linked) | n/a | Bricolage Grotesque, Fraunces, JetBrains Mono | Already loaded via `<link rel="preconnect">` in all HTML heads. No change needed. |

### Development Tools

| Tool | Purpose | Notes |
|------|---------|-------|
| Playwright HTML reporter | Visual test result inspection with screenshots on failure | Already configured (`reporter: 'html'` in `playwright.config.js`). No change needed. |
| Playwright trace viewer | Debug flaky tests with full action timeline | Already configured (`trace: 'on-first-retry'`). Run `npx playwright show-trace trace.zip` after a failure. |
| PHP-CS-Fixer | Code style | Already configured, no change. |

---

## Installation

```bash
# In tests/e2e/ — upgrade Playwright and add axe accessibility checker
cd tests/e2e
npm install --save-dev @playwright/test@^1.59.1 @axe-core/playwright@^4.10.0

# Update browser binaries after version bump
npx playwright install

# In root — sync version declaration
# Edit package.json: "@playwright/test": "^1.59.1"
npm install
```

No CSS framework installation needed — the design system is handcrafted in `public/assets/css/design-system.css`.

---

## Alternatives Considered

| Recommended | Alternative | Why Not |
|-------------|-------------|---------|
| Custom CSS design tokens (existing) | Tailwind CSS | Tailwind's utility classes fight the existing token-based component architecture. Mixing both produces specificity wars. The design system is already built; the problem is incorrect application, not missing framework. |
| Custom CSS design tokens (existing) | daisyUI | daisyUI is Tailwind-dependent. Same conflict as Tailwind. The project's hand-rolled component classes (`.btn`, `.card`, `.field-group`) would need complete replacement. |
| Custom CSS design tokens (existing) | Bootstrap 5 | Bootstrap's opinionated grid and component classes conflict with the existing OKLCH color system and custom spacing scale. Migration cost exceeds benefit. |
| HTMX 1.9 (keep current) | HTMX 2.0 upgrade now | Upgrading 1.9 → 2.0 requires auditing `hx-on` attributes (case-sensitivity changed), DELETE request query-vs-body semantics, and cross-domain config. The broken wiring is likely DOM selector / event handler bugs, not HTMX version bugs. Defer upgrade to v1.2. |
| `@playwright/test` (JS/Node) | `playwright-php/playwright` | The project already has a mature 18-spec JS Playwright suite with global auth setup, helpers, and cookie injection. Rebuilding in PHP would be wasted effort. |
| Playwright (existing) | Cypress | Playwright already installed, configured, and used. Cypress would be a full replacement with no net benefit. |

---

## What NOT to Add

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| React / Vue / Alpine.js | Any component framework competes with HTMX's server-driven model. Alpine.js is tempting but adds a second declarative system alongside HTMX — two reactive systems on the same DOM cause hard-to-debug conflicts. | HTMX attributes + vanilla JS in `public/assets/js/` |
| Sass/SCSS preprocessor | The design system already uses CSS custom properties and `@layer` — native CSS features that make Sass unnecessary. Adding Sass would require a build step while providing no new capability. | Native CSS custom properties (already in use) |
| CSS-in-JS / Emotion / styled-components | PHP server renders HTML; CSS-in-JS has no role here. | Static CSS files |
| Storybook | Overhead of maintaining a component catalog is not justified for an app this size. Component states are tested through Playwright page tests. | Playwright visual tests |
| Webpack / Vite | The project uses a simple `cp` script to vendor HTMX and Chart.js. A bundler would add significant complexity for a project that doesn't bundle modules. | `npm run vendor:copy` (existing) |
| `playwright-axe` (old package) | Deprecated in favour of `@axe-core/playwright`. | `@axe-core/playwright` |

---

## Stack Patterns by Variant

**For the login 2-panel redesign:**
- Pure CSS grid: `display: grid; grid-template-columns: 1fr 1fr` on `.login-page`, left panel for branding, right panel for form
- No JS needed; existing `login.css` handles the implementation
- On mobile (`max-width: 768px`): `grid-template-columns: 1fr` with left panel hidden or collapsed above form

**For HTMX wiring fixes:**
- Use `htmx:afterRequest`, `htmx:responseError`, `htmx:sendError` event listeners in vanilla JS to debug broken targets
- Use Playwright's `page.waitForResponse('/api/v1/*')` pattern for asserting HTMX requests complete before DOM assertions
- Pattern: `await page.waitForResponse(resp => resp.url().includes('/api/v1/') && resp.status() === 200)`

**For SSE testing with Playwright:**
- SSE streams cannot be fully intercepted by Playwright's `waitForResponse` (stream never closes)
- Pattern: test SSE effect on DOM rather than the stream itself — `await page.waitForSelector('[data-live-updated]')` after triggering the event source
- Avoid testing the SSE endpoint directly in E2E; that belongs in PHPUnit integration tests

**For accessibility testing:**
```javascript
// In any spec that renders a full page:
const { checkA11y, injectAxe } = require('@axe-core/playwright');
await injectAxe(page);
await checkA11y(page, null, { runOnly: ['wcag2a', 'wcag2aa'] });
```

---

## Version Compatibility

| Package | Compatible With | Notes |
|---------|-----------------|-------|
| `@playwright/test@1.59.1` | Node 18+ | Project already requires Node >=18 (declared in `tests/e2e/package.json`). |
| `@axe-core/playwright@4.10.x` | `@playwright/test@1.50+` | Peer dep satisfied by 1.59.1. |
| HTMX 1.9.12 | Chart.js 4.4.1 | No conflicts — different concerns. |
| HTMX 2.0.8 (if upgraded) | Existing `hx-on` attributes | Breaking: `hx-on` attribute case-sensitivity changed; DELETE request body becomes query params; cross-domain requests disabled by default. Full audit required before upgrade. |

---

## Key Insight: The Problem Is Not the Stack

The v1.1 milestone's core problems (broken wiring, design incoherence) are **application-layer bugs**, not missing tools:

1. **Broken JS/HTMX wiring** — DOM selectors or event handler registrations broken after v4.2 CSS refactor. Fix by auditing `public/assets/js/` files against current DOM structure. Playwright tests catch regressions.

2. **Design incoherence** — `design-system.css` defines the tokens; individual page CSS files (`meetings.css`, `admin.css`, etc.) do not consistently reference those tokens. Fix by auditing each page CSS against the design system variables.

3. **Login 2-panel layout** — Implement in `login.css` using CSS grid. No new dependencies.

The only genuine stack addition for v1.1 is **upgrading Playwright to 1.59.1** and optionally **adding `@axe-core/playwright`** for automated accessibility regression detection.

---

## Sources

- [npm @playwright/test](https://www.npmjs.com/package/@playwright/test) — version 1.59.1 confirmed as latest (April 2026)
- [npm htmx.org](https://www.npmjs.com/package/htmx.org) — version 2.0.8 confirmed as latest stable
- [htmx migration guide 1.x to 2.x](https://htmx.org/migration-guide-htmx-1/) — breaking changes verified, HIGH confidence
- [Playwright accessibility testing docs](https://playwright.dev/docs/accessibility-testing) — `@axe-core/playwright` integration pattern
- [Playwright release notes](https://playwright.dev/docs/release-notes) — 1.59.x changelog
- `tests/e2e/package.json` in codebase — 1.58.2 currently installed (verified via node)
- `public/assets/css/design-system.css` in codebase — custom design token system verified as complete (OKLCH palette, type scale, spacing, components)

---

*Stack research for: AgVote v1.1 UI/UX coherence + Playwright E2E*
*Researched: 2026-04-07*
