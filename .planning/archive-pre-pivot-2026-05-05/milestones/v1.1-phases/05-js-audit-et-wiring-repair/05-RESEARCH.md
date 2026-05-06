# Phase 5: JS Audit et Wiring Repair - Research

**Researched:** 2026-04-07
**Domain:** Vanilla JS DOM wiring, async sidebar timing, Playwright test helpers
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
All implementation choices are at Claude's discretion — pure infrastructure phase. Use ROADMAP phase goal, success criteria, and codebase conventions to guide decisions.

Key research findings to incorporate:
- 1,269 querySelector/getElementById calls across page JS files — audit all against current HTML
- Only 2 pages use real HTMX (postsession, vote) — rest is vanilla JS + fetch()
- Sidebar loads async via fetch(/partials/sidebar.html) — shared.js, shell.js, auth-ui.js all depend on it
- vote.js:852 has known mismatch: getElementById(voteButtons) but HTML has class=vote-buttons

### Claude's Discretion
All implementation choices are at Claude's discretion.

### Deferred Ideas (OUT OF SCOPE)
None.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| WIRE-01 | Inventaire de tous les contrats ID (querySelector/getElementById) et verification vs HTML actuel | DOM scan methodology, comparison tooling approach, output format |
| WIRE-02 | Reparation de tous les fetch handlers et event handlers casses sur toutes les pages | Per-page JS/HTML cross-reference, known mismatches, repair patterns |
| WIRE-03 | Reparation du timing sidebar async (shared.js, shell.js, auth-ui.js) | Async loading chain documented, MutationObserver pattern, race condition vectors |
| WIRE-04 | Helper Playwright waitForHtmxSettled() pour eviter les race conditions HTMX | HTMX 1.9.12 settle event, Playwright waitForFunction pattern, placement in helpers/ |
</phase_requirements>

---

## Summary

Phase 5 repairs frontend wiring regressions introduced when v4.2 restructured the HTML without auditing the JS DOM contracts. The codebase has 1,461 `querySelector`/`getElementById` calls spread across 32 page JS files. Audit findings show the pattern is largely healthy — most IDs match their HTML counterparts (operator page is fully consistent, dashboard KPI IDs match, meetings page IDs match). The one confirmed broken selector is `vote.js:852` which calls `getElementById('voteButtons')` but the HTML defines `id="vote-buttons"` (the existing fallback `querySelector('.vote-buttons')` catches it silently).

The sidebar async chain is the primary systemic risk. `shared.js` initiates a `fetch('/partials/sidebar.html')`, then `shell.js` watches the sidebar element via `MutationObserver` to rebind pin/scroll/theme/nav-group handlers, and `auth-ui.js` (auto-loaded by shell.js via dynamic `<script>` injection) runs another `MutationObserver` to apply role filtering after the sidebar content arrives. This two-observer chain is sound but has a known silent failure mode: if `shared.js` fetch fails, the sidebar remains empty but `shell.js` MutationObserver never fires, leaving pin/scroll/nav-group handlers unbound without any visible error.

The `waitForHtmxSettled()` helper targets the 2 pages that actually load `htmx.min.js` (vote.htmx.html, postsession.htmx.html). HTMX 1.9.12 fires `htmx:afterSettle` after DOM settling completes. For the other 17 pages that use plain `fetch()`, no HTMX wait is needed — `waitForLoadState('networkidle')` is sufficient.

**Primary recommendation:** Audit-first, repair-second, helper-last. Do the full ID inventory (WIRE-01) before touching any JS or HTML. Fix confirmed broken selectors (WIRE-02). Harden the sidebar fallback path (WIRE-03). Add `waitForHtmxSettled()` to `tests/e2e/helpers/` (WIRE-04).

---

## Codebase Facts (Verified by Direct Inspection)

### Pages and JS File Map

| HTML Page | JS File(s) | Uses HTMX | Uses shell.js/sidebar |
|-----------|-----------|-----------|----------------------|
| dashboard.htmx.html | dashboard.js | No | Yes |
| meetings.htmx.html | meetings.js | No | Yes |
| members.htmx.html | members.js | No | Yes |
| operator.htmx.html | operator-exec.js, operator-tabs.js, operator-motions.js, operator-attendance.js, operator-realtime.js, operator-speech.js | No | Yes |
| hub.htmx.html | hub.js | No | Yes |
| vote.htmx.html | vote.js, vote-ui.js | **Yes (htmx.min.js)** | No (no `.app-sidebar`) |
| postsession.htmx.html | postsession.js | **Yes (htmx.min.js)** | No |
| admin.htmx.html | admin.js | No | Yes |
| analytics.htmx.html | analytics-dashboard.js | No | Yes |
| archives.htmx.html | archives.js | No | Yes |
| audit.htmx.html | audit.js | No | Yes |
| email-templates.htmx.html | email-templates-editor.js | No | Yes |
| help.htmx.html | help-faq.js | No | Yes |
| users.htmx.html | users.js | No | Yes |
| settings.htmx.html | settings.js | No | Yes |
| trust.htmx.html | trust.js | No | Yes |
| report.htmx.html | report.js | No | Yes |
| validate.htmx.html | validate.js | No | Yes |
| wizard.htmx.html | wizard.js | No | Yes |
| login.html | login.js, login-theme-toggle.js | No | No |
| public.htmx.html | public.js | No | No (voter-facing) |

**Key finding:** HTMX attributes (`hx-get`, `hx-post`, `hx-swap`) are used in **zero** static HTML files. HTMX is only loaded on vote and postsession pages, and it handles SSE/event-stream integration in those two pages via `htmx:configRequest` and `htmx:afterSwap` hooks in `utils.js`.

### Confirmed Broken Selector

```javascript
// vote.js:852 — getElementById fails, querySelector fallback rescues silently
var voteArea = document.getElementById('voteButtons')         // BROKEN: no such ID
           || document.querySelector('.vote-buttons')           // WORKS: class exists
           || document.querySelector('.vote-actions');          // fallback
```

HTML truth:
```html
<div class="vote-section" id="vote-buttons">    <!-- id is kebab-case -->
  <div class="vote-buttons" role="group">       <!-- class is kebab-case -->
```

Fix: Replace `getElementById('voteButtons')` with `getElementById('vote-buttons')` or `querySelector('#vote-buttons')`.

### Selector Count by File

| JS File | getElementById calls | querySelector calls |
|---------|---------------------|-------------------|
| vote.js | 26 | ~40 |
| meetings.js | 40 | ~14 |
| members.js | 54 | ~varied |
| operator-exec.js | ~80 | ~10 |
| Total pages dir | ~800 | ~660 |

### Script Loading Order (verified, e.g. dashboard.htmx.html)

```
/assets/js/theme-init.js          (synchronous, top of <head>)
/assets/js/core/utils.js          (synchronous, bottom of <body>)
/assets/js/core/shared.js         (synchronous — initiates sidebar fetch)
/assets/js/core/shell.js          (synchronous — sets MutationObserver, then injects auth-ui.js)
/assets/js/services/meeting-context.js
/assets/js/pages/{page}.js        (synchronous — runs after all core scripts)
```

`auth-ui.js` is NOT in the static `<script>` list. It is dynamically injected by `shell.js` at line 906:
```javascript
const authScript = document.createElement('script');
authScript.src = '/assets/js/pages/auth-ui.js';
document.head.appendChild(authScript);
```

---

## Standard Stack

### Core (all verified by direct file inspection)

| Library/Tool | Version | Purpose | Role in Phase |
|---|---|---|---|
| Vanilla JS (ES5/ES2017 mix) | N/A | All DOM wiring | Direct repair target |
| HTMX | 1.9.12 (vendored) | SSE/swap on vote+postsession | `htmx:afterSettle` for WIRE-04 |
| Playwright | ^1.50.0 (installed) | E2E test runner | `waitForHtmxSettled()` destination |
| Node.js | >=18 | Playwright runtime | Already satisfied |

### No External Libraries Needed

This phase is pure audit and repair. No new dependencies should be added.

---

## Architecture Patterns

### Sidebar Async Chain

```
DOM ready
  └─ shared.js runs synchronously
       └─ initSidebar() → fetch('/partials/sidebar.html')
            └─ [async] sidebar.innerHTML = html
                 ├─ shell.js MutationObserver fires
                 │    └─ rebinds: sidebarPin, scrollFade, themeToggle,
                 │               navGroupToggle, markActivePage, updateSidebarTop
                 └─ auth-ui.js MutationObserver fires (observeSidebarInclusion)
                      └─ filterSidebar() — hides items by role
```

**Timing risk:** `shell.js` binds `sidebarPin` button at page load (line 63: `bindPinButton()`) before the sidebar HTML is available. The MutationObserver re-binds after load. If the fetch is slow, the pin button is unresponsive until the fetch completes — silent UX failure. The `dataset.pinBound` guard prevents double-binding.

**Failure fallback (current):**
```javascript
.catch(function () {
  sidebar.innerHTML = '<div class="p-4 text-muted text-sm">Navigation unavailable</div>';
});
```
This fallback leaves shell.js MutationObserver waiting forever (it fires on childList changes but the `.retry-block` content won't have `[data-requires-role]` elements, so auth-ui.js observer never fires either). Shell.js handlers (pin, scroll, nav-group) DO fire because MutationObserver watches `childList: true, subtree: true` on any change.

**WIRE-03 repair target:** Add a `window.dispatchEvent(new CustomEvent('sidebar:loaded'))` or equivalent signal when the sidebar fetch completes (success or fallback) so page JS can reliably know sidebar is settled.

### ID Contract Pattern

All 19 admin pages follow the same convention:
- IDs in HTML are **camelCase** for JS-owned elements (e.g., `id="meetingsList"`, `id="kpiSeances"`)
- Class names are **kebab-case** for CSS-owned styling (e.g., `class="meetings-list"`)
- The `vote.js:852` bug is an anomaly: `id="vote-buttons"` uses kebab-case (breaking the camelCase convention) and the JS expected camelCase

**Convention to document in inventory:**
- JS-targeted IDs → camelCase (the standard)
- CSS-targeted classes → kebab-case
- Exceptions: `id="main-content"`, `id="vote-buttons"`, `id="auth-banner"` (kebab-case by choice)

### HTMX Settle Pattern

HTMX 1.9.12 fires events in this order:
1. `htmx:beforeRequest`
2. `htmx:afterRequest`
3. `htmx:beforeSwap`
4. `htmx:afterSwap` (DOM updated)
5. `htmx:beforeSettle`
6. `htmx:afterSettle` (animations, CSS transitions settled — `defaultSettleDelay: 20ms`)

For `waitForHtmxSettled()` in Playwright, the correct event is `htmx:afterSettle`.

### Playwright Helper Pattern (existing in helpers.js)

The existing `helpers.js` exports plain functions. The `waitForHtmxSettled()` helper should follow the same export pattern:

```javascript
// tests/e2e/helpers/waitForHtmxSettled.js  (new file)
async function waitForHtmxSettled(page, timeout = 5000) {
  await page.waitForFunction(
    () => {
      return new Promise((resolve) => {
        if (!window.htmx) { resolve(true); return; }
        // If no request in flight, settle immediately
        const handler = () => resolve(true);
        document.body.addEventListener('htmx:afterSettle', handler, { once: true });
        // Safety timeout — if no htmx activity, resolve after tick
        setTimeout(() => { resolve(true); }, 100);
      });
    },
    { timeout }
  );
}
module.exports = { waitForHtmxSettled };
```

**Note:** Because HTMX is only present on 2 pages, the helper must gracefully handle the case where `window.htmx` is undefined (resolve immediately).

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| ID inventory generation | Custom PHP/shell script | Simple bash: `grep -rn "getElementById\|querySelector" public/assets/js/pages/ > inventory.txt` | One-shot audit artifact, not a maintained tool |
| HTMX request tracking | Custom XHR interceptor | HTMX's own `htmx:afterSettle` event | HTMX already exposes the right lifecycle hook |
| Sidebar load detection | Custom timing/polling loop | MutationObserver (already in place) | Browser-native, already implemented — just fix the fallback path |

---

## Common Pitfalls

### Pitfall 1: Editing HTML Before Audit Is Complete
**What goes wrong:** Fix one page's IDs, accidentally break another page's JS selectors.
**Why it happens:** ID changes are not type-checked; there's no compile-time contract.
**How to avoid:** Complete WIRE-01 inventory first. Do not modify any HTML ID during WIRE-01.
**Warning signs:** Any HTML edit during the audit wave.

### Pitfall 2: Auth-UI.js Missing After Shell.js Fails
**What goes wrong:** If shell.js throws before line 906, auth-ui.js is never injected. Pages load without the auth banner, role enforcement, or session expiry warning.
**Why it happens:** Dynamic `<script>` injection is at the bottom of shell.js IIFE — any earlier exception aborts it.
**How to avoid:** Wrap the auth-ui.js injection in a `try/finally` or move it earlier/to a separate `<script>` tag.
**Warning signs:** Console error in shell.js with no auth banner visible.

### Pitfall 3: Double-Binding After Sidebar Reload
**What goes wrong:** If a page hot-reloads sidebar content (e.g., nav refresh), the MutationObserver fires again, re-binding event listeners that are already bound.
**Why it happens:** `shell.js` MutationObserver uses `dataset.pinBound` guard but not all handlers have guards.
**How to avoid:** Verify guards exist on all re-bindable handlers in the MutationObserver callback.
**Warning signs:** Button click fires handler twice.

### Pitfall 4: waitForHtmxSettled on Non-HTMX Pages
**What goes wrong:** Test hangs waiting for `htmx:afterSettle` on a page that never fires it.
**Why it happens:** 17 of 19 admin pages don't use HTMX at all.
**How to avoid:** The helper must check `window.htmx` first and resolve immediately if absent.
**Warning signs:** Test timeout in specs for dashboard, meetings, etc.

### Pitfall 5: Inventory Only Scans pages/ Dir
**What goes wrong:** Orphan selectors in `core/shell.js`, `core/shared.js`, or `core/auth-ui.js` are missed.
**Why it happens:** Audit focuses on per-page files only.
**How to avoid:** Include `core/` in the scan scope. Core files use `[data-include-sidebar]`, `[data-requires-role]`, `[data-page]`, `#auth-banner`, `#sidebarPin`, `#sidebarScroll`, `#sidebarFade` — all must be verified against the sidebar partial HTML.

---

## Code Examples

### Pattern: MutationObserver with Guard (shell.js, verified)
```javascript
// Source: public/assets/js/core/shell.js:53-63
function bindPinButton() {
  const btn = document.getElementById('sidebarPin');
  if (btn && !btn.dataset.pinBound) {        // guard against double-bind
    btn.dataset.pinBound = 'true';
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      togglePin();
    });
  }
}
bindPinButton();  // runs immediately (may find nothing)
// MutationObserver re-runs this after sidebar HTML arrives
```

### Pattern: Sidebar Async Load with Fallback (shared.js, verified)
```javascript
// Source: public/assets/js/core/shared.js:25-57
fetch('/partials/sidebar.html')
  .then(function (r) { return r.text(); })
  .then(function (html) {
    sidebar.innerHTML = html;
    // ... active page, meeting context wiring
  })
  .catch(function () {
    sidebar.innerHTML = '<div class="p-4 text-muted text-sm">Navigation unavailable</div>';
  });
```

### Pattern: Auth.ready Promise for Page Coordination (auth-ui.js, verified)
```javascript
// Source: public/assets/js/pages/auth-ui.js:26-27
// Page scripts can await window.Auth.ready to know auth state is resolved
window.Auth.ready = new Promise(function (resolve) { _resolveReady = resolve; });
// Called after whoami completes: _resolveReady();
```
Page JS that needs to know auth state (e.g., show/hide admin actions) should use:
```javascript
window.Auth.ready.then(function() {
  if (window.Auth.role === 'admin') { /* show admin UI */ }
});
```

### Pattern: Playwright waitForHtmxSettled (to be created)
```javascript
// Destination: tests/e2e/helpers/waitForHtmxSettled.js
// HTMX 1.9.12 — htmx:afterSettle fires after DOM settling (defaultSettleDelay: 20ms)
async function waitForHtmxSettled(page, timeout = 5000) {
  await page.waitForFunction(
    () => {
      return new Promise((resolve) => {
        if (!window.htmx) { resolve(true); return; }
        document.body.addEventListener(
          'htmx:afterSettle',
          () => resolve(true),
          { once: true }
        );
        setTimeout(() => resolve(true), 200); // safety: no activity in flight
      });
    },
    { timeout }
  );
}
module.exports = { waitForHtmxSettled };
```

---

## Audit Methodology for WIRE-01

The planner should structure WIRE-01 as a manual cross-reference audit, not code generation:

1. **Extract all ID selectors per page JS file** — `getElementById('X')` → ID `X`, `querySelector('#X')` → ID `X`
2. **Extract all IDs from matching HTML page** — `id="X"` attributes
3. **Diff** — IDs referenced in JS but absent from HTML = orphan selectors
4. **Document inventory artifact** as a markdown table or comment block in each JS file

Scope:
- `public/assets/js/pages/*.js` → cross-reference with `public/*.html`
- `public/assets/js/core/shell.js` → cross-reference with `public/partials/sidebar.html`
- `public/assets/js/core/auth-ui.js` → cross-reference with dynamically-created banner DOM (generated by JS, not HTML)
- `public/assets/js/core/shared.js` → no ID selectors (confirmed: uses `[data-include-sidebar]`, not IDs)

Pages that need audit (in priority order — by confirmed/suspected risk):
1. `vote.js` / `vote.htmx.html` — 1 confirmed broken selector
2. `operator-exec.js` / `operator.htmx.html` — 80+ getElementById calls, highest complexity
3. `members.js` / `members.htmx.html` — 54 getElementById calls
4. `meetings.js` / `meetings.htmx.html` — 40 getElementById calls (spot-checked: healthy)
5. All remaining 15 pages

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Playwright ^1.50.0 |
| Config file | `tests/e2e/playwright.config.js` |
| Quick run command | `cd tests/e2e && npx playwright test specs/vote.spec.js --project=chromium` |
| Full suite command | `cd tests/e2e && npx playwright test --project=chromium` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| WIRE-01 | Inventory artifact exists, no orphan selectors documented | manual review | N/A — documentation artifact | ❌ Wave 0 (doc) |
| WIRE-02 | vote.js broken selector fixed, all pages load without console errors | smoke | `cd tests/e2e && npx playwright test specs/vote.spec.js --project=chromium` | ✅ |
| WIRE-03 | Sidebar loads without flash, pin/scroll/nav handlers bound after async load | smoke | `cd tests/e2e && npx playwright test specs/navigation.spec.js --project=chromium` | ✅ |
| WIRE-04 | `waitForHtmxSettled()` available in helpers, used in vote spec | unit/helper | `cd tests/e2e && npx playwright test specs/vote.spec.js --project=chromium` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `cd tests/e2e && npx playwright test specs/{relevant}.spec.js --project=chromium`
- **Per wave merge:** `cd tests/e2e && npx playwright test --project=chromium`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/e2e/helpers/waitForHtmxSettled.js` — WIRE-04 helper (new file)
- [ ] `tests/e2e/helpers/index.js` — optional re-export barrel for helpers/ directory

---

## Open Questions

1. **Scope of sidebar.html IDs**
   - What we know: `shell.js` expects `#sidebarPin`, `#sidebarScroll`, `#sidebarFade` in the sidebar partial
   - What's unclear: Whether all three exist in the current `public/partials/sidebar.html`
   - Recommendation: Verify as first action in WIRE-01

2. **auth-ui.js injection timing risk**
   - What we know: shell.js injects auth-ui.js at line 906 (last line of IIFE)
   - What's unclear: Whether any earlier exception in shell.js is occurring in production that prevents auth-ui.js injection
   - Recommendation: Add try/finally guard around the injection as part of WIRE-03

3. **Operator page complexity**
   - What we know: operator-exec.js alone has 80+ getElementById calls and operator.htmx.html is 1500+ lines
   - What's unclear: How many of the operator IDs are dynamically generated (injected via JS) vs. static HTML
   - Recommendation: Spot-check `opTransitionCard`, `opBtnToggleVote`, `opBtnProclaim` which appear in both files

---

## Sources

### Primary (HIGH confidence)
- Direct file inspection: `public/assets/js/core/shared.js` — sidebar async load chain
- Direct file inspection: `public/assets/js/core/shell.js` — MutationObserver, auth-ui.js injection
- Direct file inspection: `public/assets/js/pages/auth-ui.js` — Auth.ready promise, observeSidebarInclusion
- Direct file inspection: `public/assets/js/pages/vote.js:852` — confirmed broken selector
- Direct file inspection: `public/vote.htmx.html:212` — `id="vote-buttons"` (kebab-case)
- Direct file inspection: `tests/e2e/helpers.js` — existing Playwright helper patterns
- Direct file inspection: `tests/e2e/playwright.config.js` — Playwright ^1.50.0 config
- Direct file inspection: `public/assets/vendor/htmx.min.js` — HTMX version 1.9.12

### Secondary (MEDIUM confidence)
- HTMX 1.9.12 event lifecycle (`htmx:afterSettle`) — inferred from htmx.min.js `version:"1.9.12"` and HTMX documentation pattern
- `defaultSettleDelay: 20` — read directly from htmx.min.js config object

### Tertiary (LOW confidence)
- None.

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all versions verified by direct file inspection
- Architecture: HIGH — all async patterns verified by reading source files
- Pitfalls: HIGH — pitfalls derived from observed code patterns, not speculation
- HTMX event API: MEDIUM — version confirmed (1.9.12), event names from minified source pattern

**Research date:** 2026-04-07
**Valid until:** 2026-05-07 (stable stack, no fast-moving dependencies)
