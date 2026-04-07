# Pitfalls Research

**Domain:** UI/UX design system + Playwright E2E tests on an existing HTMX/PHP 8.4 app
**Researched:** 2026-04-07
**Confidence:** HIGH (grounded in codebase inspection + verified community patterns)

---

## Critical Pitfalls

### Pitfall 1: The v4.2 Disaster — Restructuring HTML Without Verifying JS Contracts

**What goes wrong:**
Visual improvements require changing HTML structure (wrapping elements, adding containers, renaming/removing IDs, restructuring tab panels). The JS wiring that depended on exact IDs and class names silently breaks. Nothing throws an error — `getElementById()` returns `null`, the null check is missing, and the feature simply stops working. Users see a visually improved page that does nothing.

**Why it happens:**
JS files in this codebase contain 1,269 `querySelector`/`getElementById` calls across all page files. These calls reference specific IDs and class names as implicit contracts between HTML and JS. These contracts are invisible — there is no type system, no import, no linkage that would flag a broken reference at build time. When HTML is restructured for visual reasons, the author does not see the JS impact. The v4.2 redesign restructured HTML for cosmetic improvements and broke event handlers, HTMX targets, and state management on every page.

**Real examples found in this codebase:**
- `vote.js` line 852: `getElementById('voteButtons')` — the ID does not exist in `vote.htmx.html`; only `class="vote-buttons"` exists. The code has a fallback querySelector, but this is an example of a pre-existing mismatch surviving from a previous restructure.
- `shell.js` binds sidebar pin/collapse/scroll behavior after the sidebar HTML is fetched asynchronously. Changing the sidebar partial structure without checking `shell.js` breaks all sidebar interactions.
- `auth-ui.js` uses a `MutationObserver` to detect when the sidebar is dynamically inserted (`data-include-sidebar`). Changing the attribute name or the sidebar inclusion mechanism breaks role filtering silently.

**How to avoid:**
1. Before touching any HTML file, extract all IDs and data attributes it uses: `grep -n 'id="\|data-' page.htmx.html`. Cross-reference against the companion JS file to build an explicit ID contract list.
2. Treat every `id=` attribute in HTML as a public API — renaming requires updating all JS references.
3. Write the Playwright test for a page's JS interactions BEFORE restructuring the HTML. The test acts as a regression guard.
4. Apply an explicit HTML comment above JS-critical IDs: `<!-- JS: vote.js:852 getElementById -->` to make the dependency visible during review.
5. Never restructure HTML and add visual CSS in the same commit. Separate concerns: first CSS-only visual changes (safe), then structural HTML changes (require JS audit).

**Warning signs:**
- You find yourself adding a wrapper div around an existing element for layout purposes.
- You rename a CSS class that is also referenced in a JS `querySelector`.
- You split a large `<section>` into multiple tabs or panels — JS tab managers bind by class name.
- The page "looks fine" after a change but buttons do nothing when clicked.

**Phase to address:**
Phase 1 (JS Audit and Wiring Repair) — before any HTML restructuring for design. Build the ID contract inventory. Phase 2 (Design System Application) — only CSS-scope changes allowed unless Phase 1 audit is done.

---

### Pitfall 2: CSS Infrastructure Delivered, Visible Results Absent

**What goes wrong:**
A design system phase produces a comprehensive token file (`design-system.css` with 5,278 lines already exists), `@property` declarations, OKLCH color scales, spacing scales, and component primitives. Pages look exactly the same as before because the tokens are defined but not applied — existing page-specific CSS files still use hardcoded values, and no visible page was redesigned end-to-end.

**Why it happens:**
Token infrastructure work feels like progress. Defining `--color-primary: oklch(0.520 0.195 265)` is satisfying but zero value until page CSS actually uses it. The app already has a `design-system.css` and it already uses OKLCH tokens. The infrastructure exists. Doing it again differently delivers nothing visible.

**How to avoid:**
1. Never plan a phase whose entire output is CSS variable definitions or token files. Token work is only valid if it produces at least one fully redesigned page as output in the same phase.
2. For each phase, define a "page-complete" criterion: login page ships as 2-panel layout, dashboard ships with redesigned KPI cards, etc. If no page looks different, the phase failed.
3. Resist the urge to "generalize first, apply later." Apply first, then extract common patterns.

**Warning signs:**
- Phase deliverable is described as "establish design tokens" with no page name attached.
- CSS changes touch only `:root {}` and no page-specific selectors.
- Design review finds pages unchanged despite a "design system" phase being complete.

**Phase to address:**
Every design phase must have a named, visible page deliverable. SUMMARY.md phase structure must list pages by name, not abstract concepts.

---

### Pitfall 3: Horizontal Layout Ignored — Vertical Stacking Regresses

**What goes wrong:**
Forms and content sections are styled with `max-width: 600px; margin: auto` or stack fields vertically in a single column. On horizontal screens (the target platform), this wastes 60-70% of the screen width. The app looks like a mobile app displayed on desktop.

**Why it happens:**
Default CSS frameworks and AI code generators default to single-column, centered, max-width-constrained layouts because they are safe and work on mobile. The developer applies these defaults without questioning the target context. Wizard forms are the highest-risk surface — multi-step forms with 8-10 fields naturally stack vertically.

**Specific context:**
- Wizard form (`wizard.htmx.html`): must fit all fields for a step on one screen using horizontal arrangement, NOT a vertical scroll.
- Members import form: upload zone + configuration fields must be side-by-side.
- Operator console (`operator.htmx.html`): already horizontal with tabs — do not regress to stacked panels.

**How to avoid:**
1. Use CSS Grid with named areas for all form layouts. Default to two or three columns, not one.
2. Playwright viewport: set `1440x900` or `1280x800` as the default test viewport — never `375px mobile`.
3. Visual check protocol: after any layout change, screenshot at 1440px width and verify content fills at least 70% of width.
4. Ban `max-width` on inner content containers unless it is a prose reading block (help page, email template preview).

**Warning signs:**
- Any new `max-width: [number]px` applied to a form wrapper.
- Fields stacked as `<div class="field">` inside a single-column flex/grid.
- Playwright screenshot shows large whitespace on left and right of a form.

**Phase to address:**
Phase 2 (Login + Landing visual redesign). Phase 3 (Wizard refactor). Playwright tests must include viewport-width assertions.

---

### Pitfall 4: Custom Element / Web Component Interactions Break Under DOM Restructuring

**What goes wrong:**
The codebase uses custom elements (`ag-badge`, `ag-modal`, `ag-toast`, `ag-popover`, `ag-pdf-viewer`, `ag-tooltip`, `ag-spinner`, `ag-stepper`, `ag-kpi`, `ag-donut`, `ag-quorum-bar`, `ag-pagination`, `ag-searchable-select`). These are defined in `public/assets/js/components/`. When their parent container is restructured for layout (adding a wrapper div, changing the DOM depth), three failure modes occur: (1) `connectedCallback` has already run and the component has captured stale parent references, (2) slots stop working because a wrapping div broke the slot projection path, (3) `ag-pdf-viewer` and `ag-modal` use `document.body.appendChild` for overlay — if HTML adds a `position: relative` ancestor with overflow hidden, the overlay is clipped.

**Why it happens:**
Custom elements initialize once when connected. If layout restructuring moves an element to a different DOM position after definition, the component does not re-initialize. Shadow DOM selectors only see within their own shadow root — a structural change outside the shadow DOM can still break slot projection.

**How to avoid:**
1. Never wrap a custom element in a new `<div>` without verifying slot structure. Check the component's `render()` or `connectedCallback` for slot names.
2. For modal and overlay components (`ag-modal`, `ag-pdf-viewer`, `ag-popover`): verify they append to `document.body`, not to a local parent. If they do use local parent, no `overflow: hidden` or `position: relative` ancestor is safe.
3. After any structural change near a custom element, manually test open/close and slot content in the browser.
4. Playwright test: for each modal-type component, assert it appears visually above all other content (bounding box check).

**Warning signs:**
- You add `overflow: hidden` to a layout container that contains `ag-pdf-viewer` or `ag-modal`.
- A popover or tooltip is clipped by a parent container.
- Slot content disappears after a wrapper div is added.

**Phase to address:**
Phase 1 (JS Audit) should inventory all custom element placements. Phase 2+ must not restructure containers holding overlay-type components without explicit verification.

---

### Pitfall 5: Sidebar Dynamic Loading Creates Timing-Dependent Behavior

**What goes wrong:**
The sidebar is loaded asynchronously: `fetch('/partials/sidebar.html')` injects HTML into `<aside data-include-sidebar>`. Code in `shell.js` (pin button, scroll fade, nav-group collapse) and `auth-ui.js` (role filtering, MutationObserver) depends on the sidebar HTML being present before it runs. If a design change modifies when or how `shared.js` runs the sidebar fetch, or if a new structural element is added before the `aside`, the MutationObserver target changes and role filtering breaks silently.

**Why it happens:**
The `bindPinButton()` call in `shell.js` runs at parse time — before the sidebar HTML exists. It checks `btn.dataset.pinBound` to avoid double-binding, but if the sidebar loads after `bindPinButton()` runs and the ID `sidebarPin` did not exist yet, the pin button is never bound. This is a known timing issue acknowledged in the code comment: "Bind pin button (may be loaded dynamically via sidebar partial)."

**How to avoid:**
1. Never restructure the `<aside data-include-sidebar>` element or change its attributes without reading all code that queries `[data-include-sidebar]` (three files: `shared.js`, `shell.js`, `auth-ui.js`).
2. Any design change to `sidebar.html` partial must verify: pin button has `id="sidebarPin"`, scroll container has `id="sidebarScroll"`, fade wrapper has `id="sidebarFade"`, nav items have `data-page` and `data-requires-role` attributes.
3. The `sidebar.html` partial has its own implicit ID/attribute contract — document it explicitly.

**Warning signs:**
- Sidebar active page highlight stops working.
- Pinned state reverts on page reload.
- Role-filtered nav items are shown to wrong roles.
- Scroll fade indicators are absent.

**Phase to address:**
Phase 1 (JS Audit). The sidebar partial contract must be documented before any visual redesign of the sidebar.

---

### Pitfall 6: Playwright Tests Written for Happy Path Only — Missing the Wiring Regressions

**What goes wrong:**
Playwright tests verify that pages load and display content. They do not verify that button clicks produce the expected JS behavior (state changes, modal opens, vote submission). A test suite passes at 100% while every interactive feature is broken — because the tests never interact with anything beyond navigation.

**Why it happens:**
Happy-path tests are faster to write. Testing JS interactions requires understanding what each button should do and what DOM state change proves it worked. For HTMX apps, there is an additional issue: HTMX requests fire asynchronously, and naive tests do not wait for `htmx:afterSettle` before asserting.

**HTMX-specific race condition (confirmed by community):**
HTMX actions can fail in Playwright because interactions occur before HTMX has installed callbacks. The solution is to wait for `window.htmx` to exist, then wait for HTMX state classes (`htmx-request`, `htmx-settling`, `htmx-swapping`, `htmx-added`) to reach count 0 before asserting post-interaction state.

**How to avoid:**
1. Define a `waitForHtmxSettled()` utility in Playwright setup: `await page.waitForFunction(() => document.querySelectorAll('.htmx-request, .htmx-settling, .htmx-swapping').length === 0)`.
2. For each page test, include at least one interaction test: click a primary button, assert a state change (modal appears, form field updates, API call fires, DOM element becomes visible).
3. Use `page.waitForResponse()` set up BEFORE the click (not after) to avoid race conditions. Pattern: `const resp = page.waitForResponse('/api/v1/...'); await page.click('#btn'); await resp;`.
4. Never use `page.waitForTimeout()` — use actionability checks (`waitForSelector`, `waitForResponse`, `waitForFunction`).
5. Test at `1440x900` viewport. Add one test at `768px` to catch mobile regressions.

**Warning signs:**
- All Playwright tests are `page.goto()` + `expect(page).toHaveTitle()` — no clicks, no interactions.
- Tests pass in headless mode but fail interactively (classic HTMX timing race).
- Test suite runs in under 30 seconds for 20 pages — interaction tests take time, fast = superficial.

**Phase to address:**
Phase dedicated to Playwright — every page must have at minimum: load test + one primary interaction test + one API call assertion.

---

### Pitfall 7: Per-Page CSS Files Create Specificity Wars With Design System

**What goes wrong:**
The app has 20+ per-page CSS files (`meetings.css`, `members.css`, `operator.css`, etc.). When the design system tokens are applied to shared components (`btn`, `card`, `kpi-card`), per-page overrides written with higher specificity block the design system from taking effect. Visual changes appear on some pages but not others, with no obvious reason.

**Why it happens:**
Per-page CSS files accumulate overrides over time. A selector like `.members-main .btn { background: #old-color }` will override a design system rule `.btn { background: var(--color-primary) }` because of element chain specificity. When the design system is retrofitted, it cannot win these specificity battles without `!important` — which creates its own problems.

**How to avoid:**
1. When applying design system tokens to shared components, add a specificity reset layer using `@layer`: `@layer base, components, pages`. The design system goes in `components`, page overrides go in `pages`. This gives explicit cascade control without `!important`.
2. Audit per-page CSS files for hardcoded color values before applying design system: `grep -rn "#[0-9a-fA-F]\{3,6\}\|rgb(" public/assets/css/ --include="*.css"`. Every hardcoded value is a candidate for token replacement.
3. Migrate page-specific overrides before applying the design system, not after.

**Warning signs:**
- A button has the correct color on dashboard but wrong color on members page.
- `!important` appears in design system CSS rules.
- Developer tools show a design system rule being overridden by a page-specific rule.

**Phase to address:**
Phase 2 (Design System Application). Must audit per-page CSS specificity before applying tokens to shared components.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Hardcoded pixel values in per-page CSS instead of tokens | Faster to write one-off styles | Design inconsistency accumulates; changing brand color requires 20+ file edits | Never — always use `var(--spacing-*)` or `var(--color-*)` |
| CSS class rename without grep across JS | Faster visual iteration | Breaks JS `querySelector` silently | Never — always grep before renaming |
| `waitForTimeout(2000)` in Playwright | Test passes reliably on fast machines | Flaky on CI, masks real async issues | Never — use actionability-based waits |
| Global `!important` overrides in design system | Forces visual consistency fast | Creates specificity debt that cannot be resolved without refactor | Never — use `@layer` instead |
| Skipping Playwright test for a "simple" page | Saves time | Simple pages break too; no regression coverage | Only for pure static content pages (help, docs) |
| Single-column form layout for "simplicity" | Easier to implement | Wastes horizontal space; violates user expectation for desktop app | Never — use 2-column grid minimum |

---

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| HTMX + Playwright | Clicking a button immediately asserting result | Set up `waitForResponse` promise before click, await after |
| Custom elements + layout restructuring | Adding wrapper div without checking slot/shadow behavior | Verify no `overflow: hidden` on ancestors; check `connectedCallback` for parent references |
| Sidebar async load + shell.js | Assuming sidebar DOM exists when `shell.js` runs | Use the existing `bindPinButton()` re-call pattern; verify `sidebarPin` ID is preserved |
| Design tokens + per-page CSS | Applying tokens to base classes without checking page overrides | Audit per-page CSS specificity first; use `@layer` for cascade control |
| Google Fonts + CSP headers | Fonts fail to load when CSP `font-src` does not include `fonts.gstatic.com` | Verify Content-Security-Policy headers allow `fonts.googleapis.com` and `fonts.gstatic.com` |
| `oklch()` color function + browser compat | Assuming oklch works everywhere — it does not in Safari < 15.4 | Already in use; verify target browser support; add fallback hex values for critical colors |
| MutationObserver for sidebar + role filtering | Observer fires before `data-requires-role` elements are inserted | Existing code handles this correctly — do not remove the subtree:true option |

---

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| Google Fonts loaded on every page from CDN | 300-600ms font load adds latency, FOUC on slow connections | Self-host fonts via `@font-face` in production; current CDN approach is acceptable in dev | Every page load — cumulative across the app |
| 5,278-line `design-system.css` loaded on every page | Page CSS bundle is large | Split into critical (tokens + layout) and non-critical (components) — lazy load component styles | Not a current problem at current scale; revisit at 500+ concurrent users |
| SSE connection held open while Playwright tests run | Tests that navigate away leave SSE connections open; port exhaustion on CI | Always disconnect SSE in `afterEach` Playwright hook; verify `event-stream.js` cleanup runs on navigation | CI environment with many parallel test workers |
| Skeleton placeholders never replaced if API fails silently | Users see "loading" state forever | Playwright test: verify skeletons are removed within 5s; set explicit API timeout in JS | Any page with skeleton loading pattern (dashboard KPI, members list) |

---

## Security Mistakes

These are unchanged from v1.0 — no new security surface is introduced by UI/UX work. The relevant concern for this milestone:

| Mistake | Risk | Prevention |
|---------|------|------------|
| Playwright test credentials committed to repo | CI leaks valid user credentials | Use dedicated test tenant with non-production credentials; never commit `.env.playwright` |
| CSRF token not included in Playwright-triggered form submissions | Tests pass (no CSRF on test env) but form fails in production | Verify CSRF middleware is active in test environment; include CSRF token fetch in test setup |

---

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Wizard form spans multiple scroll lengths vertically | Users lose context; cannot see what they filled above | Two-column grid; all fields for one step visible without scrolling at 1440x900 |
| Login page is a centered card on plain background | Looks generic; no brand identity | Two-panel layout: left panel = branding/product value, right panel = form |
| Action buttons at bottom of long pages | Users scroll down, complete action, page jumps — disorienting | Sticky footer bar for primary actions on long pages (operator console already does this correctly) |
| Status badges without consistent color convention | Operator cannot quickly scan session state | Use `ag-badge` variants consistently: `live`=green+pulse, `draft`=muted, `closed`=primary, etc. — already defined, enforce uniformly |
| Form validation errors in alert box below form | User must scroll past form to see error | Inline field-level errors using `aria-describedby`; existing `ag-toast` for global errors |

---

## "Looks Done But Isn't" Checklist

- [ ] **Login redesign:** Verify the 2-panel layout works at 1440px AND collapses gracefully at 768px. Check dark mode. Verify CSRF token is in the form. Verify redirect after login still works.
- [ ] **Wizard form:** All fields for each step visible without vertical scroll at 1440x900. Tab navigation between steps works. Back button restores previous field values.
- [ ] **Sidebar:** Active page highlight works. Pin state persists on reload. Role-filtered items hidden for non-admin users. Sidebar loads correctly on pages where `MeetingContext` is undefined.
- [ ] **HTMX wiring:** Playwright test clicks primary action on each page and verifies state change — not just page load.
- [ ] **Custom elements:** `ag-modal`, `ag-popover`, `ag-pdf-viewer` open above all other content (no clipping). `ag-toast` appears and auto-dismisses.
- [ ] **Dark mode:** Design tokens apply correctly to redesigned pages. No hardcoded colors visible in dark mode.
- [ ] **Operator console:** Real-time updates via SSE still fire and update DOM after any HTML changes. Motion list updates. Quorum bar updates. Timer runs.
- [ ] **Vote page:** Presence toggle works. Vote buttons appear when a motion is open. Confirmation state shows after vote. Blocked overlay appears for non-eligible voters.

---

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| v4.2-style JS breakage discovered after HTML restructure | HIGH | Run Playwright suite to identify broken pages; diff HTML IDs before/after using git; restore broken IDs or update JS references; add regression test before re-merging |
| Per-page CSS specificity wars | MEDIUM | Add `@layer` declarations to CSS files; move page overrides into a `pages` layer; avoid `!important` |
| Playwright race conditions on HTMX | LOW | Add `waitForHtmxSettled()` utility; replace all `waitForTimeout` calls; re-run suite |
| Custom element overlay clipping | MEDIUM | Remove `overflow: hidden` from parent chain; verify custom element appends to `document.body`; add Playwright assertion for overlay z-index/position |
| Horizontal layout regression | LOW | Playwright screenshot comparison at 1440px; restore CSS Grid layout; remove `max-width` constraints |

---

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| v4.2 HTML restructure breaks JS (Pitfall 1) | Phase 1: JS Audit before any HTML changes | ID contract inventory complete; no `getElementById` returning null in Playwright |
| CSS infrastructure without visible results (Pitfall 2) | Every phase: require named page deliverable | Design review shows at least one page visually changed per phase |
| Horizontal layout regression (Pitfall 3) | Phase 2: Login redesign, Phase 3: Wizard | Playwright screenshot at 1440px shows no wasted horizontal space |
| Custom element DOM breakage (Pitfall 4) | Phase 1: JS Audit inventories all custom element placements | Modal/popover/overlay Playwright tests pass |
| Sidebar timing-dependent breakage (Pitfall 5) | Phase 1: Document sidebar partial contract | Sidebar pin, role filter, active-page tests pass |
| Playwright happy-path only (Pitfall 6) | Phase for Playwright: require interaction tests | Every page has click + state-change test, not just navigation |
| CSS specificity wars (Pitfall 7) | Phase 2: Audit per-page CSS before token rollout | No design system rule overridden by page-specific rule in DevTools |

---

## Sources

- Codebase inspection: `vote.js` (1,473 lines, 1,269 total `querySelector`/`getElementById` calls across all pages), `shell.js` (sidebar pin/scroll binding patterns), `auth-ui.js` (MutationObserver sidebar role filtering), `shared.js` (async sidebar fetch), `operator-exec.js` (DOM ID contracts for operator console)
- Confirmed ID mismatch: `vote.js:852` searches `getElementById('voteButtons')` — ID absent in `vote.htmx.html` (only `class="vote-buttons"` exists)
- HTMX + Playwright race condition: [htmx GitHub Discussion #2360](https://github.com/bigskysoftware/htmx/discussions/2360) — HTMX callbacks not installed before Playwright interaction
- Playwright best practices: [Playwright Official Docs — Best Practices](https://playwright.dev/docs/best-practices), [17 Playwright Mistakes to Avoid](https://elaichenkov.github.io/posts/17-playwright-testing-mistakes-you-should-avoid/)
- Custom elements + HTMX: [htmx Web Components documentation](https://htmx.org/examples/web-components/), [HTMX and Web Components — a Perfect Match](https://binaryigor.com/htmx-and-web-components-a-perfect-match.html)
- CSS cascade layers for specificity control: [CSS Cascade Layers Guide — CSS-Tricks](https://css-tricks.com/css-cascade-layers/)
- Project history: v4.2 broke JS interactions after HTML restructure (documented in PROJECT.md and MEMORY.md)

---
*Pitfalls research for: UI/UX design system + Playwright E2E on existing HTMX/PHP 8.4 app (AgVote v1.1)*
*Researched: 2026-04-07*
