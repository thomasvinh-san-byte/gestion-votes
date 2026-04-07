# Architecture Research

**Domain:** UI/UX coherence + Playwright E2E tests for existing PHP 8.4 + HTMX app
**Researched:** 2026-04-07
**Confidence:** HIGH — based on direct codebase inspection

## Standard Architecture

### System Overview

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         BROWSER (Client)                                 │
│  ┌──────────────┐  ┌────────────────┐  ┌─────────────────────────────┐  │
│  │ *.htmx.html  │  │  *.html        │  │  Custom Elements (ag-*)     │  │
│  │  (app pages) │  │ (login/public) │  │  Web Components             │  │
│  └──────┬───────┘  └───────┬────────┘  └─────────────┬───────────────┘  │
│         │                  │                          │                  │
│  ┌──────▼──────────────────▼──────────────────────────▼───────────────┐  │
│  │              JS Layer (IIFE modules, no bundler)                    │  │
│  │  core/: shell.js, shared.js, page-components.js, utils.js          │  │
│  │  pages/: dashboard.js, operator-*.js, meetings.js, ...             │  │
│  │  components/: ag-modal.js, ag-toast.js, ag-badge.js, ...           │  │
│  │  services/: meeting-context.js                                      │  │
│  └──────────────────────────────┬──────────────────────────────────────┘  │
│                                 │ fetch() / SSE                          │
└─────────────────────────────────┼──────────────────────────────────────┘
                                  │ HTTP
┌─────────────────────────────────▼──────────────────────────────────────┐
│                         PHP 8.4 Server                                   │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │  Router (app/Core/Router.php) + SecurityProvider                 │   │
│  ├──────────────────────────────────────────────────────────────────┤   │
│  │  Controllers:  API Controllers  │  HTML Controllers              │   │
│  │  (extend AbstractController)    │  (use HtmlView::render())      │   │
│  ├──────────────────────────────────────────────────────────────────┤   │
│  │  Services: VoteEngine, BallotsService, MeetingWorkflowService    │   │
│  ├──────────────────────────────────────────────────────────────────┤   │
│  │  Repositories: MeetingRepository, BallotRepository, ...          │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│  ┌──────────────┐  ┌──────────────────┐                                  │
│  │  PostgreSQL  │  │   Redis          │  (sessions, SSE events,          │
│  └──────────────┘  └──────────────────┘   rate-limit counters)          │
└────────────────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────────────┐
│                     Playwright E2E Tests                                 │
│  tests/e2e/playwright.config.js                                          │
│  tests/e2e/specs/*.spec.js    (15+ spec files, per page/feature)        │
│  tests/e2e/setup/auth.setup.js (global: 4 role logins via API)          │
│  tests/e2e/helpers.js          (auth injection, shared constants)        │
│  tests/e2e/.auth/{role}.json   (cached PHPSESSID per role)              │
│                                                                          │
│  Requires: Docker stack running at localhost:8080                        │
│  Seed data: database/seeds/04_e2e.sql (fixed UUIDs for determinism)     │
└────────────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| Component | Responsibility | Location |
|-----------|---------------|---------|
| `design-system.css` | All CSS custom properties (tokens), base reset, component styles | `public/assets/css/design-system.css` (5278 lines) |
| `app.css` | Single entry point — imports design-system.css + pages.css, adds app-level overrides | `public/assets/css/app.css` (809 lines) |
| `pages.css` | Page-specific styles extracted from inline HTML `<style>` blocks | `public/assets/css/pages.css` (1433 lines) |
| Per-page CSS | One CSS file per page for login/report/wizard/etc. (not included in app.css) | `public/assets/css/{page}.css` |
| Custom Elements | 20+ `ag-*` web components (modal, toast, badge, spinner, etc.) | `public/assets/js/components/ag-*.js` |
| `core/` JS | Shell layout (sidebar, drawer, theme), shared utilities, HTMX page-component patterns | `public/assets/js/core/` |
| `pages/` JS | Per-page logic — event handlers, API calls, DOM wiring | `public/assets/js/pages/{page}.js` |
| `.htmx.html` pages | App shell + content area HTML. Sidebar loaded via `fetch('/partials/sidebar.html')` | `public/*.htmx.html` |
| `partials/sidebar.html` | Shared navigation sidebar, loaded asynchronously by `shared.js` | `public/partials/sidebar.html` |
| Playwright config | Multi-browser config (chromium, firefox, webkit, mobile, tablet) | `tests/e2e/playwright.config.js` |
| Playwright specs | One spec file per page or feature domain | `tests/e2e/specs/*.spec.js` |
| Auth setup | Global setup: 4 API logins → `.auth/{role}.json` (run once before all tests) | `tests/e2e/setup/auth.setup.js` |
| E2E seed data | PostgreSQL seed with fixed UUIDs for deterministic test state | `database/seeds/04_e2e.sql` |

---

## Recommended Project Structure

The structure already exists. New files must fit within it. Do not introduce new directories.

```
public/
├── assets/
│   ├── css/
│   │   ├── design-system.css   ← ONLY place for CSS tokens + base components
│   │   ├── app.css             ← Entry point (imports design-system + pages)
│   │   ├── pages.css           ← Page-generic styles extracted from HTML
│   │   ├── login.css           ← Per-page: login only (loaded via <link>)
│   │   ├── wizard.css          ← Per-page: wizard only
│   │   └── {page}.css          ← Add new per-page files here only if needed
│   ├── js/
│   │   ├── components/
│   │   │   └── ag-{name}.js    ← Web components (self-contained)
│   │   ├── core/
│   │   │   ├── shell.js        ← Sidebar/drawer/theme — loaded on all pages
│   │   │   ├── shared.js       ← Sidebar loader, label maps, modal helpers
│   │   │   ├── page-components.js ← TabManager, FilterManager, etc.
│   │   │   └── utils.js        ← Escape helpers, formatters
│   │   ├── pages/
│   │   │   └── {page}.js       ← One file per page for page-specific logic
│   │   └── services/
│   │       └── meeting-context.js ← Meeting ID state across pages
│   └── vendor/
│       ├── htmx.min.js         ← HTMX (copied from node_modules by npm script)
│       └── chart.umd.js        ← Chart.js (copied from node_modules)
├── partials/
│   └── sidebar.html            ← Shared sidebar HTML (fetched async)
└── *.htmx.html                 ← App shell pages (one per route)

tests/e2e/
├── playwright.config.js        ← Multi-browser config, auth setup reference
├── helpers.js                  ← loginAs*, credentials, E2E UUIDs
├── setup/
│   └── auth.setup.js           ← Global: logs in 4 roles via API before tests
├── specs/
│   └── {feature}.spec.js       ← Tests named after features/pages, NOT controllers
└── .auth/                      ← Runtime: {role}.json cached session cookies
    └── (gitignored)
```

### Structure Rationale

- **`design-system.css` is the only token source:** All CSS custom properties (`--color-*`, `--space-*`, `--text-*`, etc.) live here. Component CSS in per-page files must use tokens, never raw values. This is the established contract — violating it creates maintenance chaos.
- **`app.css` is the universal entry point:** Every `.htmx.html` includes exactly `<link rel="stylesheet" href="/assets/css/app.css">`. Per-page CSS files (`login.css`, `wizard.css`) are loaded via additional `<link>` only for pages that need them.
- **HTMX is only loaded where needed:** Most pages are custom-JS-driven (fetch-based). Only `postsession.htmx.html` and `vote.htmx.html` include `htmx.min.js`. The "HTMX app" label is partly aspirational — the bulk of pages use vanilla JS calling REST APIs.
- **Sidebar is fetched async:** `shared.js` does `fetch('/partials/sidebar.html')` and injects it into `<aside data-include-sidebar>`. This means sidebar DOM is unavailable at DOMContentLoaded. Playwright tests targeting sidebar elements must `waitForSelector` or use `networkidle`.
- **Playwright auth is session-cookie-based:** `auth.setup.js` calls the API login endpoint once per role, stores `PHPSESSID` in `.auth/{role}.json`. `helpers.js` injects these cookies before each test, avoiding the 10/300s rate limit. Tests that deliberately test the login UX form (8 total across suite) stay safely under the limit.
- **E2E test data is deterministic:** `database/seeds/04_e2e.sql` uses fixed UUIDs (`eeeeeeee-e2e0-...`) that match constants in `helpers.js`. Tests reset only the records they need, not the whole database.

---

## Architectural Patterns

### Pattern 1: CSS Token Cascade (design-system.css to component CSS)

**What:** All visual values go through CSS custom properties defined in `:root` in `design-system.css`. Component CSS references tokens, never raw values.

**When to use:** Every time new or modified CSS is written.

**Trade-offs:** High consistency, tokens documented in one place. Cost: finding the right token name requires reading design-system.css.

**Example:**
```css
/* CORRECT — tokens only */
.my-component {
  background: var(--color-surface);
  padding: var(--space-4);
  border-radius: var(--radius-md);
  color: var(--color-text);
}

/* WRONG — raw values create drift */
.my-component {
  background: #F2F0EB;  /* bypasses dark-mode token overrides */
  padding: 16px;         /* not aligned to spacing scale */
}
```

### Pattern 2: CSS `@layer` for Override Safety

**What:** `design-system.css` declares `@layer base, components, v4;`. Base reset goes in `@layer base`, component styles in `@layer components`. Page-level CSS outside a layer wins over all layered styles.

**When to use:** Any new component style added to `design-system.css` should go in `@layer components`. Page overrides in per-page CSS files need no layer — they win by cascade order.

**Trade-offs:** Prevents specificity wars. Requires understanding which layer a rule belongs to.

### Pattern 3: Web Components for Reusable UI (ag-*)

**What:** Reusable UI elements are implemented as custom elements (`ag-modal`, `ag-toast`, `ag-badge`, etc.) in `public/assets/js/components/`. They are instantiated declaratively in HTML with attributes.

**When to use:** Any UI element used on 3+ pages, or any element with internal state (show/hide, loading, etc.).

**Trade-offs:** Self-contained, no framework needed. Cost: verbose attribute-based API, no JSX/template syntax. Cannot use `import` — scripts are loaded as plain `<script>` tags.

**Example:**
```html
<!-- Usage in HTML -->
<ag-toast id="myToast"></ag-toast>
<ag-modal id="confirmModal" title="Confirmer ?">
  <p>Action irreversible.</p>
</ag-modal>
```

```javascript
// Programmatic control in page JS
document.getElementById('myToast').show('Enregistre', 'success');
document.getElementById('confirmModal').open();
```

### Pattern 4: Sidebar Async Load Pattern

**What:** Every `.htmx.html` page has `<aside class="app-sidebar" data-include-sidebar data-page="{pageName}"></aside>`. `shared.js` detects this, fetches `/partials/sidebar.html`, injects it, and sets the active nav link.

**When to use:** Every app shell page.

**Trade-offs:** Sidebar HTML is fresh on each navigation (no stale state). Cost: sidebar DOM is unavailable synchronously at DOMContentLoaded — any code targeting sidebar elements must bind after the fetch resolves.

**Playwright implication:** Tests checking sidebar state must use `page.waitForLoadState('networkidle')` or `page.waitForSelector('.app-sidebar a')` before asserting sidebar content.

### Pattern 5: Role-Based Auth Injection for Playwright

**What:** `auth.setup.js` (global setup) calls `POST /api/v1/auth_login.php` once per role, saves `PHPSESSID` to `.auth/{role}.json`. `helpers.js` `loginAs*` functions inject these cookies via `page.context().addCookies()` rather than filling the login form.

**When to use:** All Playwright tests that require an authenticated session. Only tests explicitly testing the login form UX should use `loginWithEmail()`.

**Trade-offs:** Avoids the 10-request/300s rate limit when running specs in parallel. Cost: auth state must be refreshed if sessions expire (run global setup again).

**Example:**
```javascript
const { loginAsOperator } = require('../helpers');

test.beforeEach(async ({ page }) => {
  await loginAsOperator(page);  // injects PHPSESSID — no form interaction
});

test('should see dashboard', async ({ page }) => {
  await page.goto('/dashboard.htmx.html');
  await page.waitForLoadState('networkidle');
  await expect(page.locator('.page-title')).toBeVisible();
});
```

### Pattern 6: E2E Seed Data with Fixed UUIDs

**What:** `database/seeds/04_e2e.sql` inserts test fixtures with deterministic UUIDs in the `eeeeeeee-e2e0-*` range. `helpers.js` exports these as constants (`E2E_MEETING_ID`, `E2E_MOTION_1`, etc.).

**When to use:** Any Playwright test that needs a meeting, member, or motion to exist. Reference constants from `helpers.js`, not hardcoded strings.

**Trade-offs:** Tests are reproducible. Cost: seed must be re-applied if test data is mutated by a destructive test. `04_e2e.sql` starts with DELETEs to reset state before re-inserting.

---

## Data Flow

### CSS Rendering Flow

```
Browser request for *.htmx.html
    ↓
<link rel="stylesheet" href="/assets/css/app.css">
    ↓ app.css
@import design-system.css   (tokens, base reset, all components)
@import pages.css            (page-level extracted styles)
    ↓ (optional per-page)
<link rel="stylesheet" href="/assets/css/login.css">  (login page only)
    ↓
CSS custom properties from :root cascade to all elements
Dark mode: [data-theme="dark"] overrides token values
    ↓
theme-init.js runs synchronously in <head> to set data-theme
before paint (avoids flash of wrong theme)
```

### Page Bootstrap Flow (JS)

```
HTML loads → theme-init.js sets data-theme (sync, in <head>)
    ↓
DOMContentLoaded fires
    ↓ (all pages)
shared.js: fetch('/partials/sidebar.html') → inject into <aside>
    ↓
shell.js: bind sidebar pin, theme toggle, drawer system
    ↓
page-specific JS (e.g. dashboard.js): initialize page components
    ↓
Custom elements (ag-*) upgrade: connectedCallback() runs
    ↓
API calls: fetch('/api/v1/{endpoint}') → JSON → DOM update
    ↓ (SSE pages only)
event-stream.js: EventStream.connect(meetingId) → Redis SSE
```

### Playwright Test Flow

```
npm run test:e2e
    ↓
playwright.config.js: trigger globalSetup (auth.setup.js)
    ↓
auth.setup.js: POST /api/v1/auth_login.php x 4 roles
    → writes .auth/{role}.json
    ↓
Specs run in parallel (fullyParallel: true, CI: 1 worker)
    ↓
test.beforeEach: loginAs*(page) → addCookies from .auth file
    ↓
page.goto('/target.htmx.html')
page.waitForLoadState('networkidle')  ← wait for sidebar fetch
    ↓
Assertions on DOM state, API responses, visible text
```

---

## Integration Points

### New CSS vs Existing Templates

| What to modify | How | Risk |
|---------------|-----|------|
| Fix broken component style | Edit `design-system.css` in `@layer components` section | LOW — scoped by component class |
| Add new design token | Add to `:root` in `design-system.css` primitives section | LOW — additive |
| Page-level fix | Edit matching `{page}.css` or `pages.css` | LOW — isolated |
| Change app shell (sidebar/header) | Modify `design-system.css` + verify `partials/sidebar.html` | MEDIUM — affects all pages |

**Rule:** Never add `<style>` blocks to `.htmx.html` pages. Extract to `pages.css` or a per-page CSS file.

### New Playwright Specs vs Existing Infrastructure

| What to add | Where | How |
|------------|-------|-----|
| New spec file | `tests/e2e/specs/{feature}.spec.js` | Import helpers.js, use `loginAs*` |
| New auth role needed | `tests/e2e/setup/auth.setup.js` | Add to ACCOUNTS array |
| New E2E fixture | `database/seeds/04_e2e.sql` | Use `eeeeeeee-e2e0-*` UUID range |
| New shared helper | `tests/e2e/helpers.js` | Export from module.exports |

**Rule:** Spec files test page behavior from a user perspective (URLs, visible text, form interactions). Never test internal PHP classes in Playwright — that belongs in PHPUnit.

### HTMX vs Vanilla JS Architecture Note

Despite the `.htmx.html` naming convention, the application is primarily **vanilla JS + fetch() + custom elements**. HTMX attributes (`hx-get`, `hx-target`, etc.) appear in only 2 pages (`postsession.htmx.html`, `vote.htmx.html`). The rest use imperative `fetch()` calls in page JS files.

This means:
- "Fix HTMX wiring" in v1.1 context means fixing broken fetch() event handlers and DOM selectors in `pages/` JS files
- Playwright tests verify the result of these JS-driven DOM updates, not HTMX-specific responses
- `htmx.min.js` only needs to load on pages that actually use HTMX attributes

### PHP Templates vs Static HTML

Two distinct template patterns coexist:

| Type | Location | How rendered | When used |
|------|----------|-------------|----------|
| Static HTML + JS | `public/*.htmx.html` | Served as static files, JS fetches data via API | All app shell pages (dashboard, operator, meetings, etc.) |
| PHP templates | `app/Templates/*.php` | `HtmlView::render()` from controller | Public-facing forms only (vote form, password reset, setup, account) |

New pages follow the static HTML + JS pattern. PHP templates are only for unauthenticated public flows.

---

## Anti-Patterns

### Anti-Pattern 1: Inline `<style>` in HTML pages

**What people do:** Add a `<style>` block inside a `.htmx.html` page for a quick fix.

**Why it's wrong:** Bypasses the design system, creates invisible overrides, breaks dark mode token cascade, cannot be reused.

**Do this instead:** Add rules to `pages.css` (generic styles) or the matching per-page CSS file loaded via `<link>`.

### Anti-Pattern 2: Raw color/spacing values in CSS

**What people do:** Write `color: #1650E0` or `padding: 16px` in component CSS.

**Why it's wrong:** These values do not respond to theme changes. Dark mode works by overriding CSS custom properties — raw values ignore it.

**Do this instead:** Use `var(--color-primary)` and `var(--space-4)`. Check `design-system.css` for the full token catalog.

### Anti-Pattern 3: loginWithEmail() in Playwright beforeEach

**What people do:** Call `loginWithEmail(page, email, password)` in each test's beforeEach.

**Why it's wrong:** Each call consumes one of the 10 auth_login attempts per 300s. A parallel test run with 15+ spec files will hit the rate limit, causing cascading test failures.

**Do this instead:** Use `loginAsOperator(page)` (or the matching role helper). This injects the cached PHPSESSID cookie — no HTTP login request.

### Anti-Pattern 4: Targeting sidebar elements without waiting for async load

**What people do:** `await expect(page.locator('.app-sidebar a')).toBeVisible()` immediately after `page.goto()`.

**Why it's wrong:** The sidebar HTML is fetched asynchronously by `shared.js`. The `<aside>` element exists but is empty at DOMContentLoaded.

**Do this instead:** Use `await page.waitForLoadState('networkidle')` after `page.goto()` to ensure the sidebar fetch completes before asserting.

### Anti-Pattern 5: Adding new JS as `<script>` blocks in HTML

**What people do:** Add inline `<script>` to a `.htmx.html` page for page-specific logic.

**Why it's wrong:** Breaks the module structure, cannot be linted by ESLint, duplicates initialization logic.

**Do this instead:** Add logic to the matching `public/assets/js/pages/{page}.js` file, which is already loaded by the page.

### Anti-Pattern 6: Skipping `@layer` structure in design-system.css

**What people do:** Add new component styles outside any `@layer` declaration at the top of design-system.css.

**Why it's wrong:** Unlayered styles have higher specificity than layered styles, overriding things they should not.

**Do this instead:** Place new component styles inside `@layer components { }`.

---

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| Current (v1.1) | Monolithic CSS + IIFE JS modules, no build step for JS. Sufficient for this app size. |
| If CSS grows past 10k lines | Split design-system.css into sub-files, use `@import` from a manifest. No tool change needed. |
| If JS complexity grows | Consider a lightweight bundler (esbuild) for tree-shaking. Not needed now. |
| If Playwright suite grows past 50 specs | CI workers set to 1 to avoid rate limit. May need to increase rate limit window in Redis config or add a test-mode bypass. |

---

## Sources

- Codebase inspection: `public/assets/css/`, `public/assets/js/`, `tests/e2e/`, `public/*.htmx.html`
- `tests/e2e/playwright.config.js` — confirms multi-browser targets, globalSetup path, Docker dependency
- `tests/e2e/helpers.js` — auth strategy, rate-limit avoidance pattern, E2E UUID constants
- `tests/e2e/setup/auth.setup.js` — global auth setup, rate-limit clearing via Redis
- `database/seeds/04_e2e.sql` — E2E seed data structure
- `public/assets/css/design-system.css` — token structure, @layer declarations, component catalog
- `public/assets/css/app.css` — CSS entry point and import order
- `public/assets/js/core/shared.js` — sidebar async load pattern
- `public/assets/js/pages/operator-realtime.js` — SSE + fetch() API pattern (not HTMX)
- `app/View/HtmlView.php` — PHP template rendering for public-facing flows

---
*Architecture research for: AgVote v1.1 UI/UX coherence + Playwright E2E tests*
*Researched: 2026-04-07*
