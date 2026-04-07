# Feature Research

**Domain:** Admin panel UI/UX — professional voting and meeting management app (AgVote v1.1)
**Researched:** 2026-04-07
**Confidence:** HIGH (design patterns from official Playwright docs + verified HTMX community sources + codebase audit)

---

## Feature Landscape

### Table Stakes (Users Expect These)

Features professional admin panel users assume exist. Missing these makes the product feel unfinished or amateur.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Consistent design language across all pages | Every SaaS admin tool has a unified visual identity — incoherence signals instability | MEDIUM | Design tokens exist in `design-system.css` but not applied uniformly across 18+ page CSS files |
| Sidebar navigation that works on all pages | Standard shell pattern: left sidebar + content area. If JS wiring breaks, there is no nav | LOW | `shell.js` / `page-components.js` controls sidebar injection; wiring must be verified per-page |
| Login page with clear branding | First impression. Centered card without brand context reads as unfinished | MEDIUM | Current login is single-panel card. Desired: 2-panel split (branding left, form right) |
| Functional form validation with visible feedback | Required fields, error states, field-level messages | LOW–MEDIUM | Login form already thorough; operator/admin forms need audit |
| Breadcrumb navigation | Users in complex admin UIs need to know where they are | LOW | Already in HTML templates (`breadcrumb` + `bc-item` classes) — needs consistent presence |
| Responsive layout at common breakpoints | Professional tools work at 1280px (laptop) and 1024px (operator tablet) | MEDIUM | Operator page has 768px E2E test; no test for 1280px standard view |
| Dark mode that does not break layouts | Standard expectation in 2024+. Color tokens exist but visual parity needs verification | MEDIUM | `data-theme="dark"` toggle present; per-page CSS may not cover all dark states |
| Loading states and feedback on async actions | HTMX requests are async — without feedback, users click twice or think the app is frozen | MEDIUM | HTMX adds `.htmx-request` during inflight — CSS for this class must exist on all interactive elements |
| Error messages in French, no raw PHP/stack traces | Institutional context. Raw errors destroy trust | LOW | `ErrorDictionary.php` exists; E2E test already asserts no `Fatal error:` text leaks |
| Accessible markup (skip links, ARIA labels, focus management) | Required for public-sector usage; France has legal accessibility requirements | MEDIUM | Skip links present; ARIA labels on forms exist; audit needed for HTMX-injected content |

### Differentiators (Competitive Advantage)

Features that elevate AgVote above generic admin templates and voting tools.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Login 2-panel layout (branding + form split) | Communicates institutional credibility. Linear/Notion/Vercel style. B2B split-screen performs better than centered card | MEDIUM | Left panel: brand name, tagline, trust signals, feature highlights. Right panel: existing form unchanged. CSS restructure only |
| "Seance en direct" urgency banner on dashboard | Real-time status visible at a glance — operators never miss a live session | LOW | Already in `dashboard.htmx.html` as `#actionUrgente`; needs verified JS wiring |
| HTMX partial updates with settled-state CSS | Smooth content replacement without full page reload gives SPA feel without SPA complexity | MEDIUM | Requires `.htmx-settling` CSS and `htmx:afterSettle` event handling; no flash of unstyled content |
| Role-specific page layouts | Operator view vs admin view vs voter view each optimized for their workflow | HIGH | `data-page-role` attribute exists on pages; sidebar items and content can adapt per role |
| Quorum indicator visible on all session-management screens | Voting operators need quorum status at all times, not buried in a table | MEDIUM | Quorum overlay `#opQuorumOverlay` exists in operator page; must not intercept pointer events (E2E already checks this) |
| Horizontal-first layout for wide screens | User feedback: screens are horizontal. Forms and data tables should use width, not stack vertically | MEDIUM | Multi-column form layouts, side-by-side cards — use CSS Grid, not single-column stacking |
| Toast notifications for async action results | Non-intrusive feedback that does not shift layout, replaces inline error/success divs | MEDIUM | Requires centralized toast manager in JS; pages currently use per-page inline `.login-error` / `.login-success` pattern |
| Contextual onboarding tips per page | New operator sees help relevant to that specific page, not a generic FAQ link | LOW | Pattern already exists as `ag-popover` with `onboarding-tips` in dashboard — needs replication across pages |

### Anti-Features (Commonly Requested, Often Problematic)

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Full SPA client-side routing | Snappy navigation, no full page loads | Contradicts HTMX server-driven architecture. Would require duplicating rendering logic in JS | Use `hx-push-url` + `hx-boost` for perceived SPA feel while keeping server rendering |
| CSS framework replacement (Bootstrap / Tailwind) | Consistent utility classes, faster development | Custom design system (`design-system.css` v2.0 with OKLCH tokens, CSS layers) is mature. Replacing it invalidates all 18+ page CSS files | Extend the existing design system — it is already well-structured |
| Real-time chart animations | Visually impressive dashboards | Adds JS dependency (Chart.js / D3), increases bundle size, requires animation budget management | Static charts rendered server-side; animate only on the analytics page where it adds real value |
| Modal-heavy workflows | Group actions behind a modal to "save space" | Modals break the browser Back button, conflict with HTMX swap targets, create nested focus trap problems | Use slide-in panels or dedicated detail pages with breadcrumb navigation |
| Inline editing everywhere | Reduces perceived steps to update data | Inline editing conflicts with HTMX swap: partial DOM mutations can desync server state. Hard to Playwright-test | Keep edit as a dedicated form triggered by an explicit button; HTMX replaces the entire card on save |
| Keyboard shortcuts | Requested by power users | Explicitly out of scope per CLAUDE.md and project memory. Creates maintenance burden with no mobile benefit | Focus on keyboard accessibility (Tab, Enter, Escape) without custom shortcut bindings |
| Convocation / emargement PDFs | Admins expect all document generation in one tool | Explicitly out of scope per PROJECT.md and project memory | Point users to existing procuration PDF and XLSX/PDF results export |

---

## Feature Dependencies

```
[Design tokens in design-system.css]
    └──required by──> [Consistent light/dark mode]
    └──required by──> [All per-page CSS files]
    └──required by──> [Skeleton loading shimmer colors]

[Sidebar JS wiring (shell.js / page-components.js)]
    └──required by──> [Sidebar navigation on all pages]
    └──required by──> [Role-based nav items]
    └──required by──> [E2E navigation.spec.js passing]

[Login 2-panel layout]
    └──depends on──> [Existing login.js logic] — unchanged, CSS restructure only
    └──depends on──> [Responsive collapse < 768px]

[HTMX lifecycle CSS (.htmx-request, .htmx-settling)]
    └──required by──> [Loading states on all HTMX actions]
    └──required by──> [Playwright htmxReady() helper working correctly]

[Playwright globalSetup auth.setup.js]
    └──required by──> [All authenticated E2E specs]
    └──enables──> [Parallel test execution without rate-limit hits (10 req / 300s limit)]

[htmxReady() E2E utility]
    └──required by──> [Any spec that triggers HTMX requests]
    └──prevents──> [Race conditions in assertions after HTMX swaps]
```

### Dependency Notes

- **Design tokens before per-page CSS audit:** Standardizing to tokens is a prerequisite for reliable dark mode and any visual coherence work.
- **Sidebar wiring before navigation E2E tests:** `navigation.spec.js` requires the sidebar to actually render — if JS injection is broken, tests fail at setup before they test anything meaningful.
- **HTMX lifecycle CSS before Playwright helpers:** The `htmxReady()` helper relies on `.htmx-request` / `.htmx-settling` class presence. If the app does not emit those classes, the helper assertion is meaningless.
- **globalSetup auth before parallel specs:** Existing `setup/auth.setup.js` saves session cookies per role. This must be green before any authenticated spec can run reliably.

---

## MVP Definition (for this milestone — v1.1)

This is not a new product. It is a UI/UX coherence + Playwright coverage milestone on a shipped v1.0.

### Launch With (v1.1 baseline — required for milestone to close)

- [ ] Design token consistency audit — identify and fix pages that hardcode values instead of using CSS variables
- [ ] Login 2-panel layout — branding left, form right, responsive collapse to single column on mobile (< 768px)
- [ ] HTMX wiring repair — identify all broken `hx-*` attributes, missing `hx-target` IDs, broken JS DOM selectors, page by page
- [ ] Loading state CSS — `.htmx-request` class triggers spinner/disabled state on all interactive elements
- [ ] Playwright baseline specs green — auth, login UX, dashboard load, sidebar navigation. Tests must pass on Docker stack
- [ ] `htmxReady()` helper added to E2E test helpers — prevents race conditions in all subsequent specs

### Add After Validation (v1.1.x — when P1 is stable)

- [ ] Toast notification system — centralized JS manager, gradual migration from per-page inline feedback divs
- [ ] Role-specific sidebar item visibility — operator sees operational nav, admin sees admin nav, auditor sees audit-only nav
- [ ] Dark mode parity audit — verify all per-page CSS files handle `[data-theme="dark"]` correctly
- [ ] E2E coverage for operator workflow — meeting selection, quorum display, motion list, HTMX tab switching

### Future Consideration (v1.2+)

- [ ] Horizontal-first layout refactor — major layout change for wide-screen optimization, high effort relative to gain
- [ ] Onboarding tip propagation across all pages — low priority, existing pattern is clear
- [ ] Analytics chart animations — Chart.js integration, explicitly deferred per current scope

---

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| HTMX wiring repair (all pages) | HIGH | MEDIUM | P1 |
| Design token consistency audit | HIGH | MEDIUM | P1 |
| Login 2-panel layout | HIGH | LOW–MEDIUM | P1 |
| Playwright baseline + htmxReady() | HIGH | LOW | P1 |
| Loading state CSS (.htmx-request) | HIGH | LOW | P1 |
| Dark mode parity | MEDIUM | MEDIUM | P2 |
| Toast notification system | MEDIUM | MEDIUM | P2 |
| Role-based nav items | MEDIUM | LOW | P2 |
| Horizontal-first layout | MEDIUM | HIGH | P3 |
| Onboarding tip propagation | LOW | LOW | P3 |

**Priority key:**
- P1: Required for v1.1 — without these, the milestone goal ("professional, modern, coherent") is not met
- P2: Adds significant polish, ship when P1 is stable
- P3: Nice to have, defer to v1.2

---

## Playwright Testing Patterns for HTMX (core deliverable for this milestone)

This section is specific to the quality gate: Playwright patterns for server-driven UI, not SPA patterns.

### The Core Problem

HTMX is server-driven hypermedia: clicking a button sends an HTTP request, the server returns HTML, and HTMX swaps a DOM region. Playwright must wait for the swap to settle before asserting. Without this discipline, tests are either flaky (too fast) or slow (hardcoded delays).

Additionally, HTMX apps often hold an SSE connection open (`/api/v1/sse.php`). `waitForLoadState('networkidle')` waits for 500ms with no network activity — an SSE connection prevents this from ever firing. Several existing specs already use `networkidle` which may cause hangs.

### Anti-Pattern: `waitForLoadState('networkidle')` on HTMX Pages

```javascript
// FRAGILE — SSE connection keeps network active; this may hang
await page.waitForLoadState('networkidle');

// BETTER for pages with SSE/live connections
await page.waitForLoadState('domcontentloaded');
// Then assert the specific element you need
await expect(page.locator('#meetingSelect')).toBeVisible({ timeout: 10000 });
```

Source: Playwright docs behavior + htmx/discussions#2360 (MEDIUM confidence)

### Pattern 1: HTMX Settled Guard (add to helpers.js)

```javascript
// helpers.js
async function waitForHtmxSettle(page) {
  // HTMX adds these classes during inflight requests/swaps
  // Count = 0 means no HTMX operation is in progress
  await expect(
    page.locator('.htmx-request, .htmx-settling, .htmx-swapping, .htmx-added')
  ).toHaveCount(0, { timeout: 8000 });
}
module.exports = { ..., waitForHtmxSettle };
```

Call after triggering any HTMX action, before asserting results.

Source: htmx/discussions#2360 (MEDIUM confidence — community-verified pattern)

### Pattern 2: HTMX Initialization Check (beforeEach)

```javascript
// Before interacting with HTMX-wired elements
await page.waitForFunction(() => typeof window.htmx !== 'undefined', { timeout: 5000 });
```

Prevents race where Playwright clicks before HTMX has attached its event listeners.

### Pattern 3: Web-First Assertions Only (Playwright official)

```javascript
// BAD — returns immediately, races with HTMX swap
const visible = await page.locator('#result').isVisible();
expect(visible).toBeTruthy();

// GOOD — auto-waits up to timeout
await expect(page.locator('#result')).toBeVisible({ timeout: 5000 });
```

Source: playwright.dev/docs/best-practices (HIGH confidence — official)

### Pattern 4: waitForResponse Paired with DOM Assertion

For explicit HTMX actions where you know the API endpoint:

```javascript
// Set up waitForResponse BEFORE the action (race condition otherwise)
const [response] = await Promise.all([
  page.waitForResponse(resp => resp.url().includes('/api/v1/motions') && resp.status() === 200),
  page.click('#btnLoadMotions'),
]);
// Then assert the swapped DOM
await expect(page.locator('.motion-list')).toBeVisible({ timeout: 5000 });
```

Source: browserstack.com/guide/playwright-waitforresponse (MEDIUM confidence)

### Pattern 5: No `waitForTimeout` as HTMX Wait

```javascript
// BAD — hides timing bugs, fails on slow CI
await page.click('#btn');
await page.waitForTimeout(500);
await expect(page.locator('#result')).toBeVisible();

// GOOD — declarative, adapts to server speed
await page.click('#btn');
await expect(page.locator('#result')).toBeVisible({ timeout: 8000 });
```

Exception: `waitForTimeout(100)` is acceptable for browser autofill checks (intentional in `login.js`).

### Pattern 6: Locator Stability Across HTMX Swaps

HTMX replaces DOM nodes. Locators captured before a swap become stale references. Use role/text locators that re-evaluate on each assertion:

```javascript
// FRAGILE — may reference the old pre-swap node
const old = page.locator('#motions-list .motion-item:first-child');

// ROBUST — re-evaluated after every swap
const motionTitle = page.getByRole('listitem').filter({ hasText: 'Motion budgetaire' });
```

### Pattern 7: Auth Injection (already implemented — use it consistently)

The existing `helpers.js` `injectAuth()` pattern saves session cookies per role (from `globalSetup`) and injects them without hitting the rate-limit (10 req / 300s). All authenticated specs must use `loginAsOperator()` / `loginAsAdmin()` helpers, never `loginWithEmail()` in test bodies except in auth-flow specs.

### Pattern 8: Page Object for Complex Pages

The operator page has 7+ JS files and complex state. Wrap in a lightweight page object to keep selector management central:

```javascript
// tests/e2e/pages/OperatorPage.js
class OperatorPage {
  constructor(page) { this.page = page; }
  async goto() {
    await this.page.goto('/operator.htmx.html');
    await this.page.waitForLoadState('domcontentloaded');
    await this.page.waitForFunction(() => typeof window.htmx !== 'undefined');
  }
  meetingSelect() { return this.page.locator('#meetingSelect'); }
  quorumBar()     { return this.page.locator('[class*="quorum"]'); }
  async selectMeeting(id) { await this.meetingSelect().selectOption(id); }
}
module.exports = { OperatorPage };
```

---

## Design Patterns — Admin Panel Reference

### Table Stakes Visual Patterns

| Pattern | Status in AgVote |
|---------|-----------------|
| Dark sidebar + light content area | Shell present; sidebar token colors need verification |
| Primary action button top-right of header | Present in dashboard ("Nouvelle séance") — replicate consistently |
| Status badge on data rows (setup / running / closed / validated) | Needs consistent semantic color tokens: running=blue, closed=grey, validated=green |
| Empty state when list has no items | Missing on most list pages — add SVG illustration + French message when API returns `[]` |
| Skeleton loading for HTMX-loaded content | Better than spinners for list regions — CSS-only shimmer animation on placeholder divs |
| Focus-visible ring on all interactive elements | Must use consistent color from design tokens, not default browser blue outline |

### Login 2-Panel Layout — Specifics

**Left panel (branding):**
- Brand mark + "AG-VOTE" name + tagline ("Gestion des assembles deliberatives")
- 3 feature highlights with icons ("Votes securises", "Resultats en temps reel", "Quorum automatique")
- Trust signal (data security, French hosting if applicable)
- Subtle gradient or geometric background — not a photograph

**Right panel (form):**
- Existing form HTML and `login.js` logic — no changes required
- Surface/white background
- Padding: generous, vertically centered

**Responsive rule:** At `< 768px`, hide left panel, show only right panel (current single-card look is acceptable on mobile).

---

## Sources

- [Playwright best practices — official docs](https://playwright.dev/docs/best-practices) — HIGH confidence
- [HTMX Playwright race conditions — htmx/discussions#2360](https://github.com/bigskysoftware/htmx/discussions/2360) — MEDIUM confidence (community)
- [Playwright waitForResponse patterns — BrowserStack](https://browserstack.com/guide/playwright-waitforresponse) — MEDIUM confidence
- [Admin dashboard UI/UX best practices 2025 — Medium](https://medium.com/@CarlosSmith24/admin-dashboard-ui-ux-best-practices-for-2025-8bdc6090c57d) — LOW confidence (single source, directionally consistent with industry)
- [Split-screen login design examples — Mockplus](https://www.mockplus.com/blog/post/login-page-examples) — MEDIUM confidence (design research)
- [E-voting UX principles — Electpoll](https://electpoll.medium.com/the-ux-of-electronic-elections-db15daf8ad4e) — LOW confidence (single source)
- Codebase audit: `public/login.html`, `public/dashboard.htmx.html`, `public/hub.htmx.html`, `tests/e2e/` — HIGH confidence (first-hand)
- Design system audit: `public/assets/css/design-system.css` v2.0 — HIGH confidence (first-hand)

---

*Feature research for: AgVote v1.1 — UI/UX coherence, design language, Playwright E2E*
*Researched: 2026-04-07*
