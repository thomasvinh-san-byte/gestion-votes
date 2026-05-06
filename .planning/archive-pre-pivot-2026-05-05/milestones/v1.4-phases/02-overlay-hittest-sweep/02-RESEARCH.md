# Phase 2: Overlay Hittest Sweep - Research

**Researched:** 2026-04-10
**Domain:** CSS specificity / HTML [hidden] attribute / Playwright computed-style assertions
**Confidence:** HIGH

## Summary

The `[hidden]` HTML attribute is supposed to hide elements (`display: none`), but CSS rules like `display: flex` on the same selector override it because author stylesheets beat the UA default `[hidden] { display: none }`. The codebase already has **16 manual `.selector[hidden] { display: none }` overrides** scattered across 10 CSS files -- evidence the problem is real and recurrent. The fix is a single low-specificity rule `:where([hidden]) { display: none !important }` in the `@layer base` reset section of `design-system.css`, which makes all 16 per-selector overrides redundant.

The audit scope is well-bounded: 25 CSS files with 660 occurrences of `display: flex|grid` across the codebase, but only selectors whose elements also receive `[hidden]` toggling (via JS `.hidden = true/false` or HTML attribute) are at risk. JS analysis shows 22 page scripts toggle `.hidden`, covering roughly 50+ element selectors. The intersection of "has display:flex/grid in CSS" AND "receives [hidden] in JS/HTML" is the audit surface.

**Primary recommendation:** Add `:where([hidden]) { display: none !important }` to the reset section of `@layer base` in `design-system.css` (line ~71, after the `*, *::before, *::after` reset), then audit and remove the 16 redundant per-selector `[hidden]` overrides, and write a Playwright spec asserting `getComputedStyle` on 3+ representative pages.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| OVERLAY-01 | A global CSS rule `:where([hidden]) { display: none !important }` blocks the `[hidden]` + `display: flex` conflict | Single rule in `@layer base` reset section; `:where()` has zero specificity so it never accidentally overrides intentional `display` when `[hidden]` is absent; `!important` ensures it wins over any author `display: flex/grid/block` |
| OVERLAY-02 | A codebase-wide audit documents all `display: flex/grid/block` selectors on elements that can receive `[hidden]`, with status OK/fixed/n-a | 16 existing per-selector overrides already map most conflict sites; full audit covers 25 CSS files x 660 display rules, filtered by cross-reference with JS `.hidden` toggling in 22 page scripts |
| OVERLAY-03 | A Playwright smoke test verifies `[hidden]` -> computed `display: none` on 3+ representative pages | Playwright `page.evaluate(() => getComputedStyle(el).display)` pattern; existing e2e infrastructure uses chromium project with `loginAsOperator`/`loginAsAdmin` helpers and stored auth state |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| CSS `:where()` | CSS Selectors L4 | Zero-specificity wrapper for global `[hidden]` rule | Supported in all target browsers; avoids specificity wars with component selectors |
| Playwright | (existing in project) | E2E computed-style assertions | Already used for 46 spec files; `page.evaluate` + `getComputedStyle` is the canonical way to assert CSS behavior |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| @axe-core/playwright | (existing) | Accessibility regression guard | Run existing accessibility.spec.js after CSS changes to catch regressions |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `:where([hidden])` | `[hidden]` without `:where` | Higher specificity (0,1,0) could interfere with intentional `.foo[hidden]` overrides; `:where` is strictly better |
| `!important` | Higher-layer `@layer` trick | `!important` is clearer intent and matches the semantics: `[hidden]` MUST hide, period |

## Architecture Patterns

### Where the Rule Lives

```css
/* design-system.css, inside @layer base, after the *, *::before, *::after reset (~line 71) */

/* [hidden] must always win over display:flex/grid/block — UA default is too weak */
:where([hidden]) {
  display: none !important;
}
```

**Why `:where()`:** Specificity is `(0,0,0)` -- identical to the universal selector. This means ANY author selector like `.settings-panel { display: flex }` naturally overrides it when `[hidden]` is absent. But when `[hidden]` IS present, the `!important` declaration wins. This is the standard modern pattern (recommended by MDN, WHATWG).

**Why `@layer base`:** The design-system uses `@layer base, components, v4`. Placing the rule in `base` (the lowest layer) is semantically correct -- it's a reset/normalization concern. With `!important`, layer order is inverted (base `!important` beats components `!important`), which is the desired behavior.

### Audit Document Structure

```
docs/audits/v1.4-overlay-hittest.md

# Overlay Hittest Audit — v1.4

| CSS File | Selector | display Value | Receives [hidden]? | Status |
|----------|----------|---------------|-------------------|--------|
| operator.css | .op-transition-card | flex | Yes (operator-exec.js) | FIXED - override removed |
| settings.css | .settings-panel | flex | Yes (settings.js) | FIXED - override removed |
| ... | ... | ... | ... | ... |
```

### Pattern: Identifying Conflict Sites

The audit methodology:
1. For each of the 25 CSS files, find selectors with `display: flex|grid|block`
2. Cross-reference with JS files that toggle `.hidden` on matching elements
3. Cross-reference with HTML files that have `hidden` attribute in markup
4. Mark status: **OK** (global rule handles it), **FIXED** (redundant override removed), **N/A** (element never receives `[hidden]`)

### Anti-Patterns to Avoid
- **Adding more per-selector `[hidden]` overrides:** The whole point is to eliminate these with one global rule
- **Using `visibility: hidden` instead of `display: none`:** The `[hidden]` attribute semantically means "not relevant" -- it should remove the element from layout, not just make it invisible
- **Removing `[hidden]` from JS and using classes instead:** `[hidden]` is the correct semantic HTML approach; the CSS just needs to respect it

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Hidden attribute conflict | Per-selector `.foo[hidden] { display: none }` overrides | Single `:where([hidden]) { display: none !important }` | 16 overrides already exist and will keep growing; one rule fixes all current and future conflicts |
| Computed style testing | Manual DOM inspection scripts | Playwright `page.evaluate(() => getComputedStyle(el).display)` | Built-in, reliable, already used in the project |

## Common Pitfalls

### Pitfall 1: Breaking Intentional display:flex[hidden] Patterns
**What goes wrong:** Some codebase patterns might intentionally show an element that has `[hidden]` (e.g., CSS transitions that animate before removing)
**Why it happens:** `!important` overrides everything
**How to avoid:** The codebase does NOT have this pattern. All 16 existing `[hidden]` overrides set `display: none`, confirming the intent is always to hide. The global rule is safe.
**Warning signs:** If any spec fails after adding the rule, check if that element uses `[hidden]` as a non-hiding semantic marker (unlikely but check)

### Pitfall 2: Shadow DOM Web Components
**What goes wrong:** Web Components with Shadow DOM have their own style scope -- the global `:where([hidden])` rule does NOT penetrate shadow roots
**Why it happens:** CSS cascade boundaries at shadow DOM
**How to avoid:** The `[hidden]` attribute on the custom element HOST is handled by the global rule (host elements live in the light DOM). Internal shadow elements using `[hidden]` would need their own rule, but grep shows no shadow-internal `[hidden]` usage -- components use `visibility: hidden` or `aria-hidden` internally.
**Warning signs:** None expected -- this is a non-issue for this codebase

### Pitfall 3: Forgetting to Remove Redundant Overrides
**What goes wrong:** The 16 per-selector `[hidden] { display: none }` overrides remain, creating dead code and confusion
**Why it happens:** Oversight during implementation
**How to avoid:** The audit document explicitly lists each override and its removal status. The plan should include removing all 16 as a discrete step.
**Warning signs:** grep for `\[hidden\]\s*\{` after cleanup should return zero matches in CSS files (only JS/HTML usage remains)

### Pitfall 4: Playwright Test Flakiness on Hidden Elements
**What goes wrong:** Testing `getComputedStyle` on elements that don't exist in the DOM yet
**Why it happens:** HTMX-loaded content may not be present at assertion time
**How to avoid:** Use `waitForSelector` before evaluating computed style; existing `waitForHtmxSettled` helper is available; the test should set `[hidden]` programmatically on a known element rather than relying on app state

## Code Examples

### Global Hidden Rule (OVERLAY-01)
```css
/* Source: design-system.css @layer base, after box-sizing reset */

/* HTML [hidden] attribute must always hide — prevent display:flex/grid override.
   :where() keeps specificity at (0,0,0); !important ensures it wins over author display. */
:where([hidden]) {
  display: none !important;
}
```

### Playwright Computed Style Test (OVERLAY-03)
```javascript
// Source: project Playwright patterns (keyboard-nav.spec.js, page-interactions.spec.js)
const { test, expect } = require('@playwright/test');
const { loginAsOperator } = require('../helpers');

test.describe('Overlay hittest — [hidden] forces display:none', () => {
  test('operator page: [hidden] element has computed display:none', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/operator.htmx.html', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('.op-transition-card', { timeout: 10000 });
    
    // Programmatically set [hidden] and verify computed style
    const display = await page.evaluate(() => {
      const el = document.querySelector('.op-transition-card');
      el.setAttribute('hidden', '');
      return getComputedStyle(el).display;
    });
    expect(display).toBe('none');
  });
});
```

### Redundant Override Removal
```css
/* BEFORE (scattered across 10 files): */
.settings-panel[hidden] { display: none; }
.op-transition-card[hidden] { display: none; }
.op-quorum-overlay[hidden] { display: none; }
/* ... 13 more */

/* AFTER: all removed — global :where([hidden]) handles everything */
```

## Known Conflict Sites (Pre-Audit)

These 16 selectors already have manual `[hidden] { display: none }` overrides, confirming they are conflict sites:

| File | Selector | Line |
|------|----------|------|
| meetings.css | `.onboarding-banner[hidden]` | 35 |
| validate.css | `.validate-modal-backdrop[hidden]` | 79 |
| members.css | `.members-onboarding[hidden]` | 110 |
| public.css | `.meeting-picker-overlay[hidden]` | 649 |
| public.css | `.app-footer[hidden]` | 1007 |
| vote.css | `.offline-banner[hidden]` | 151 |
| vote.css | `.current-speaker-banner[hidden]` | 187 |
| vote.css | `.vote-hint[hidden]` | 876 |
| vote.css | `.app-footer[hidden]` | 961 |
| vote.css | `.blocked-overlay[hidden]` | 1971 |
| trust.css | `.audit-modal-overlay[hidden]` | 516 |
| settings.css | `.settings-panel[hidden]` | 95 |
| operator.css | `.op-transition-card[hidden]` | 1112 |
| operator.css | `.op-quorum-overlay[hidden]` | 1142 |
| wizard.css | `.wiz-error-banner[hidden]` | 244 |
| login.css | `.demo-panel[hidden]` | 546 |

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Per-selector `[hidden]` overrides | `:where([hidden]) { display: none !important }` | CSS Selectors L4 (2020+) | Eliminates N overrides with 1 rule |
| UA stylesheet `[hidden]` | Author-layer reset | Always needed | UA `display:none` has lowest priority, always loses to author `display:flex` |

**Why the browser default fails:** The HTML spec defines `[hidden] { display: none }` in the UA stylesheet. But CSS specificity means any author rule like `.my-panel { display: flex }` (specificity 0,1,0) beats the UA `[hidden]` (specificity 0,1,0 but UA origin < author origin). The `!important` in author origin is the standard fix.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Playwright (existing, version per package.json) |
| Config file | `tests/e2e/playwright.config.js` |
| Quick run command | `npx playwright test tests/e2e/specs/overlay-hittest.spec.js --project=chromium` |
| Full suite command | `npx playwright test --project=chromium` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| OVERLAY-01 | `:where([hidden])` rule exists in design-system.css | unit (grep/CSS parse) | Verified by OVERLAY-03 Playwright test | N/A - CSS rule |
| OVERLAY-02 | Audit document lists all conflict sites | manual review | N/A - documentation artifact | N/A |
| OVERLAY-03 | `[hidden]` -> `display:none` on 3+ pages | e2e | `npx playwright test tests/e2e/specs/overlay-hittest.spec.js --project=chromium` | Wave 0 |

### Sampling Rate
- **Per task commit:** `npx playwright test tests/e2e/specs/overlay-hittest.spec.js --project=chromium`
- **Per wave merge:** `npx playwright test --project=chromium` (full suite regression)
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/e2e/specs/overlay-hittest.spec.js` -- covers OVERLAY-03
- [ ] `docs/audits/` directory -- does not exist yet, needs creation for OVERLAY-02

## Open Questions

1. **Which 3+ pages to test in Playwright?**
   - What we know: operator, settings, vote pages all have confirmed conflict sites with heavy `[hidden]` JS toggling
   - Recommendation: Use operator (`.op-transition-card`), settings (`.settings-panel`), and vote (`.blocked-overlay`) -- these are the most complex pages and cover diverse element types (overlay, panel, banner)

## Sources

### Primary (HIGH confidence)
- Codebase grep: 16 existing `[hidden]` overrides across 10 CSS files (direct evidence)
- Codebase grep: 660 `display: flex|grid` occurrences across 25 CSS files
- Codebase grep: 22 JS page scripts toggle `.hidden` property
- design-system.css line 59-61: `@layer base, components, v4` structure confirmed
- Existing Playwright config and 46 spec files examined for test patterns

### Secondary (MEDIUM confidence)
- MDN `:where()` documentation: zero-specificity behavior confirmed
- WHATWG HTML spec: `[hidden]` UA stylesheet is author-overridable by design

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - pure CSS, no library dependencies, browser support universal
- Architecture: HIGH - single rule placement in existing layer structure, clear audit methodology
- Pitfalls: HIGH - all 16 existing overrides confirm the problem pattern; no shadow DOM conflicts found

**Research date:** 2026-04-10
**Valid until:** 2026-05-10 (stable CSS domain, no expiry concern)
