# Pitfalls Research

**Domain:** Visual identity evolution — existing app with design tokens, Shadow DOM, dark/light mode
**Researched:** 2026-04-03
**Confidence:** HIGH (grounded in codebase inspection + verified against current MDN/community sources)

---

## The v4.2 Pattern — What Went Wrong and Why

v4.2 shipped a visual redesign that restructured HTML to achieve new layouts. The restructuring silently invalidated JavaScript `querySelector` selectors that targeted specific DOM shapes (class names, parent-child relationships, element order). JS interactions broke. v4.3 required a ground-up rebuild of 6 pages to fix the regressions.

The lesson: **visual changes that restructure HTML are a different class of change than visual changes that only touch CSS.** Every pitfall below is evaluated through this lens — does this change touch HTML structure, and if so, what JS breaks?

---

## Critical Pitfalls

### Pitfall 1: HTML Restructuring Silently Breaks JS Selectors

**What goes wrong:**
A visual redesign wraps elements in new container divs, promotes children to siblings, or changes the element type (e.g., `<div>` to `<section>`). CSS changes work. The page looks correct. JS event listeners, `querySelector` calls, `closest()` traversals, and attribute reads that depended on the old DOM shape silently return `null` or attach to wrong elements. Clicks do nothing. SSE updates render in wrong containers. Form submissions fail.

**Why it happens:**
CSS and JS are written by different mental models. When a developer thinks "I'm just changing the layout," they focus on the visual output and do not audit all the page JS files for selector dependencies. In a IIFE + `var` codebase without TypeScript, there is no compile-time check.

**How to avoid:**
- Treat HTML restructuring as a **breaking change**, not a visual change.
- Before restructuring any page, grep the corresponding page JS file for all `querySelector`, `querySelectorAll`, `closest`, `getElementById`, `dataset`, and attribute reads. List every selector. Verify each one still resolves after the restructuring.
- Prefer `data-*` attributes as JS hooks over structural selectors (`> .card:first-child`, `.row .col .btn`). A `data-action="submit-wizard"` attribute survives layout changes; `.wizard-row .right-col button` does not.
- After any HTML restructuring, run the full Playwright E2E suite for that page before committing.

**Warning signs:**
- New layout requires adding wrapper divs around existing interactive elements.
- A component that previously had a flat DOM now has additional nesting levels.
- A `querySelector` in the page JS targets a class that was renamed or removed in the new design.
- JS file is not modified when the HTML file is modified for a page that has JS interactions.

**Phase to address:** Every phase that restructures page HTML. Must be enforced as a checklist item before phase sign-off.

---

### Pitfall 2: color-mix() Derived Tokens Break in Dark Mode

**What goes wrong:**
The design system uses `color-mix()` to derive tint/shade tokens (e.g., `--color-primary-tint-10`, `--color-surface-elevated`). When a new color is introduced for light mode (e.g., changing `--color-primary`), the `color-mix()` expressions recompute correctly in light mode. But in dark mode, `--color-primary` is separately defined in `[data-theme="dark"]`. If derived `color-mix()` tokens are only declared in `:root` and not overridden in `[data-theme="dark"]`, the expression in `:root` does not automatically re-evaluate against the dark overrides — the computed dark token is derived from light primitive values.

**Why it happens:**
The primitive palette in `:root` contains 100+ tokens. The `[data-theme="dark"]` block overrides a subset of semantic tokens. When adding a new color, developers remember to add it to `:root` and `[data-theme="dark"]` but forget that derived `color-mix()` tokens in `:root` that reference semantic tokens also need corresponding overrides in `[data-theme="dark"]`.

**How to avoid:**
- Maintain a strict rule: every `color-mix()` derived token in `:root` that references a semantic color token must have a corresponding explicit override in `[data-theme="dark"]`.
- When adding a new color token, immediately add all three: primitive in `:root`, semantic in `:root`, semantic override in `[data-theme="dark"]`.
- Add a comment above each `color-mix()` block in `design-system.css`: `/* NOTE: override in [data-theme="dark"] required if base token changes */`.

**Warning signs:**
- `--color-surface-elevated` looks subtly wrong in dark mode (not elevated relative to the dark background).
- Tinted backgrounds on KPI cards differ between themes in unexpected ways.
- A color change that looked fine in light mode produces broken contrast or wrong tones in dark mode.

**Phase to address:** Token/palette phase — specifically the earliest phase that changes any color primitive.

---

### Pitfall 3: Token Rename Silently Breaks Shadow DOM Components

**What goes wrong:**
The 23 Web Components all use Shadow DOM (`attachShadow({ mode: 'open' })`). Their internal styles are inline `<style>` strings that reference CSS custom properties with hardcoded fallback values (e.g., `var(--color-surface-raised, #ffffff)`). When a token is renamed during a visual identity evolution, the Shadow DOM component inline styles continue referencing the old property name. Since the old name no longer exists in `:root`, the component falls back to the hardcoded hex — which is always a light-mode value. In dark mode, the component renders with a white/light background.

**Why it happens:**
Shadow DOM styles are embedded in JS string literals. A CSS linter or search-replace that updates `.css` files will not find these strings unless explicitly targeting JS files. The developer assumes the rename is complete after updating `design-system.css`, but all 23 components are untouched.

**How to avoid:**
- **Do not rename existing token names.** Add new tokens alongside old ones and keep old names as aliases pointing to the new name.
- If a rename is unavoidable, run: `grep -r "old-token-name" public/assets/js/components/` before committing. Zero results required.
- Note: CSS custom properties DO inherit through the Shadow DOM boundary automatically. The risk is specifically token renames, not token inheritance itself. The current inheritance mechanism works correctly.

**Warning signs:**
- A Web Component (ag-modal, ag-toast, ag-confirm, ag-popover) renders with a visually wrong background in dark mode after a token change.
- A grep of `*.js` component files finds the old token name after a rename was applied only to `*.css` files.
- A component looks correct in light mode but wrong in dark mode.

**Phase to address:** Any phase that changes token names in `design-system.css`. Token additions are safe; renames are dangerous.

---

### Pitfall 4: Hardcoded Focus Ring Hex Not Updated After Brand Color Change

**What goes wrong:**
Multiple Shadow DOM components contain the focus ring color as a hardcoded rgba literal: `rgba(22,80,224,0.35)`. This value is the current primary blue (`#1650E0`) at 35% opacity. It appears in fallback values for `--shadow-focus` inside component inline styles. If the brand primary blue changes, all CSS token references update automatically — but these hardcoded rgba literals in JS string templates remain the old color. Focus rings on interactive elements inside Web Components (modal close button, confirm buttons, popover triggers) show the old brand blue.

**Why it happens:**
The component authors correctly embedded focus ring styles for graceful degradation when tokens fail. But they hardcoded the rgba value rather than using a fully token-referenced value. This creates a hidden coupling to the exact hex of the current primary.

**How to avoid:**
- After any brand blue change, run: `grep -r "22,80,224\|1650E0" public/assets/js/components/` and update all matches.
- Consider using `var(--ring-color, rgba(22,80,224,0.35))` as the fallback form — the token resolves correctly when the stylesheet is loaded; the rgba is only the degradation fallback.

**Warning signs:**
- A brand color phase was applied but no `*.js` component files were modified.
- Focus rings on Shadow DOM components show a different blue than native browser focus rings on the page.

**Phase to address:** Brand color phase — any phase that changes `--color-primary`.

---

### Pitfall 5: Dark Mode Contrast Passes Light Mode But Fails Independently

**What goes wrong:**
A new color combination — e.g., a softer muted text on a card surface — passes WCAG AA (4.5:1) in light mode. The designer verifies this. The dark mode equivalent uses corresponding dark tokens. But dark mode contrast is never independently verified. For example, `--color-text-muted` in dark mode (`#50596C`) on `--color-surface` (`#141820`) produces approximately 3.2:1 — below the 4.5:1 threshold for normal-size body text.

**Why it happens:**
Designers verify contrast in their primary design tool using the light mode palette. Dark mode is treated as "automatic" because tokens use the same semantic names. But contrast ratio depends on absolute luminance values, which are entirely different between themes. A pair that passes in light may fail in dark and vice versa.

**How to avoid:**
- For every new semantic color pair introduced, verify contrast in **both** themes independently.
- Run a browser accessibility audit (Lighthouse, axe DevTools) after every palette change in both themes.
- `--color-text-muted` and `--color-text-light` are by design lower-contrast — use only for non-body-text elements (captions, metadata, labels under large primary text) where WCAG allows 3:1 for large text or decorative content.
- Never promote `--color-text-muted` to primary content text to achieve a "softer" visual.

**Warning signs:**
- A Lighthouse accessibility audit flags contrast violations after a palette change.
- Text that was previously `--color-text-dark` is changed to `--color-text-muted` for aesthetic reasons.
- A new foreground color is introduced without documenting its contrast ratio on each surface it appears on.

**Phase to address:** Every phase introducing new color combinations, especially typography and palette phases.

---

### Pitfall 6: @layer Ordering Disrupted by Uncoordinated Layer Additions

**What goes wrong:**
`design-system.css` declares `@layer base, components, v4;` at the top. Page-specific CSS files (`wizard.css`, `hub.css`, etc.) write unlayered rules that have higher precedence than all layered rules regardless of specificity. If a new phase introduces a new layer without pre-declaring it in the global list, or if a page CSS file adds `@layer some-name { ... }` that was not pre-registered, the cascade priority silently shifts. Overrides that previously worked stop working. New rules have no effect.

**Why it happens:**
`@layer` ordering is set by the first declaration of that layer name encountered during stylesheet parse order. If a layer is added in a page file without being registered in `design-system.css` first, its position in the cascade depends on parse order — which is load order dependent and fragile.

**How to avoid:**
- All new layers must be declared in the `@layer base, components, v4;` registration line in `design-system.css` before being used anywhere.
- Do not introduce `@layer` declarations in page-specific CSS files without coordinating with the global declaration.
- Prefer adding visual identity overrides to the existing `v4` layer rather than creating `v5`.

**Warning signs:**
- A new CSS rule has no visible effect despite correct class targeting and no specificity conflicts.
- A style override that "should win" is being overridden by a rule it should beat.
- A page CSS file contains `@layer` that is not in the `design-system.css` registration list.

**Phase to address:** Any phase modifying CSS layer structure or adding new component CSS files.

---

### Pitfall 7: Font Loading Regression — Weight Changes Not Propagated to All Pages

**What goes wrong:**
The app loads three Google Fonts families via a single `<link rel="stylesheet">` URL that is duplicated across 20+ `.htmx.html` files. If a visual identity evolution changes font weights (e.g., adding weight 300 or removing weight 800 from Bricolage Grotesque), updating one page's URL does not update all pages. Pages with the old URL continue loading old weight subsets. The service worker may cache the old Google Fonts CSS response. Some pages get the new weights; others get stale subsets. Font rendering is inconsistent across pages and sessions.

**Why it happens:**
There is no single source of truth for the Google Fonts URL. It is a copy-pasted string across all HTML files. Developers update the page they are working on and miss the rest.

**How to avoid:**
- When changing font weights or families, search all `.htmx.html` files for the Google Fonts URL and update every instance: `grep -r "fonts.googleapis.com" public/ --include="*.html"`.
- After updating, bump the service worker cache version in `sw.js` to force stale cache invalidation.
- The `display=swap` parameter is already in place — correct. The `crossorigin` attribute on the preconnect tag is already present — do not remove it. Without `crossorigin` on the preconnect, the font preconnect is ignored by the browser.

**Warning signs:**
- A new font weight is visible on some pages but not others after a deploy.
- Font rendering differs between a fresh network load and a service-worker-cached load.
- A weight was added to Fraunces but display headings still appear in the old weight on pages where the URL was not updated.

**Phase to address:** Typography phase. Also: any phase that modifies the font weight axis in the Google Fonts URL.

---

### Pitfall 8: Infrastructure-Only Phase Delivers No Visible Design Change (v4.1 Repeat)

**What goes wrong:**
A phase restructures design tokens, renames variables, improves the token hierarchy, adds new aliases, or refactors the `@layer` structure — all in service of "a better foundation for the visual redesign." The phase ships. Pages look identical to before. No user-visible change was delivered. The next phase still has to do the actual visual work. The infrastructure phase consumed milestone budget without user-facing output, and stakeholders see no progress.

**Why it happens:**
Token infrastructure work feels like design work — it touches `design-system.css`, changes variable names, creates semantic aliases. But it does not change computed browser output unless it also changes the values those tokens resolve to. The gap between "infrastructure" and "visual output" is easy to underestimate.

**How to avoid:**
- Every phase in a visual identity milestone must produce at least one **visible, per-page change** demonstrable in a screenshot.
- Token work is justified only if it unblocks a specific visual change that is **also delivered in the same phase**.
- The v4.1 lesson is explicitly noted in PROJECT.md as "Revisit — infrastructure delivered but no visible visual impact." Do not repeat it. Token work must be coupled to visible output in the same commit.

**Warning signs:**
- A phase description contains only "refactor," "rename," "restructure," "hierarchy" without specifying what users will see differently.
- A phase modifies only `design-system.css` without touching any page-specific CSS, HTML, or JS.
- A phase is titled "Foundation for v10.x" without a list of concrete visual deliverables.

**Phase to address:** Milestone planning stage. This is a planning pitfall. The milestone roadmap must enforce that every phase delivers visible output.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Hardcoded hex fallbacks in Shadow DOM component `<style>` strings | Components degrade gracefully when tokens fail to load | Fallbacks become stale after palette changes; must be manually audited per change | Acceptable — document which hex maps to which token |
| Google Fonts URL duplicated across 20+ HTML files | No build step needed; simple to understand | Font weight changes require editing 20+ files; inconsistency risk on missed files | Acceptable until font changes become frequent |
| Per-page CSS files outside `@layer` | Page styles always win without specificity fights | Unlayered rules are fragile against future layer additions | Acceptable — but never add page-level `@layer` without global coordination |
| `color-mix()` derived tokens computed at parse time | Automatic tint/shade derivation, no manual calculation | Derived tokens in `:root` do not automatically update in `[data-theme="dark"]`; dark overrides required | Acceptable — but enforce paired dark overrides as a code review gate |
| Light-mode hex as CSS variable fallback (`var(--color-X, #FFFFFF)`) | Correct rendering during token load delay | Always light-mode fallback — dark mode path gets wrong color on load failure | Acceptable for robustness; prefer truly neutral fallbacks on dark-critical paths |

---

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| Shadow DOM + dark mode via `[data-theme="dark"]` on `<html>` | Assuming Shadow DOM does not inherit CSS custom properties | CSS custom properties DO inherit through shadow boundaries automatically. The token is available inside Shadow DOM. The risk is renames and fallback staleness, not inheritance failure. |
| Shadow DOM + new token introduction | Adding a new token to `:root` and assuming all Shadow DOM components receive it | Shadow DOM components only use a new token if they explicitly reference it in their inline `<style>` string. New tokens require explicit adoption inside each component. |
| `color-mix()` in dark mode | Defining `color-mix()` once in `:root` expecting it to recompute with dark values | `color-mix()` in `:root` evaluates against `:root` values only. Dark `[data-theme="dark"]` block must explicitly re-declare derived tokens to override the `:root` computation. |
| Google Fonts + service worker | Updating font URL without bumping service worker cache version | Always bump the `sw.js` cache version when changing font URLs to force invalidation of cached stylesheet responses. |
| FilePond CSS + `@layer` | FilePond CSS loaded from CDN may introduce its own `@layer` in future versions | Pin FilePond to the specific version in the CDN URL. Verify new versions do not add `@layer` that conflicts with the app's layer order. |

---

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| Adding more Google Fonts weight variants | LCP degrades; font swap is more pronounced | Audit which weights are actually used before adding. Each additional weight axis is ~50-100KB. | Immediately on the pages where the heavier URL is loaded |
| FOUT with new font or weight causing CLS | Text shifts when fallback swaps to custom font; Cumulative Layout Shift score increases | Use a system font fallback that matches the metric dimensions of Bricolage Grotesque; add `size-adjust` to the fallback `@font-face` | Every page load until the font is browser-cached |
| `color-mix()` with `oklch` on older Firefox | Colors render incorrectly or fall back to `currentColor` on Firefox < 113 | The current codebase already declares both hex and oklch forms for primitives. Keep this dual-declaration pattern; do not drop the hex fallback. | Firefox versions below 113 — edge case in practice |

---

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Changing base font size for UI chrome | Operators running live sessions have ergonomic muscle memory for UI density; a size shift disrupts scanning speed | Preserve `--text-base: 0.875rem` (14px). Font size changes are acceptable only for display/heading (`--font-display`) contexts. |
| Reducing color contrast for a "softer" aesthetic | Fails WCAG AA; users with low vision cannot read primary content | Never reduce contrast below 4.5:1 for body text, 3:1 for large text. Verify both light and dark themes. |
| Changing `--sidebar-width` globally | All 20+ page layouts shift simultaneously; content areas reflow | Sidebar width is a global layout constraint. Any change cascades to every page. Treat as a milestone-level decision, not a per-phase change. |
| Adding CSS transition to `[data-theme="dark"]` override block | Theme toggle lags; all color-dependent properties animate on switch | The existing `--transition-color` is applied at component level. Do not add transition to the `[data-theme="dark"]` block itself — it causes a flash on every theme switch. |
| Replacing Fraunces with a different display typeface | Breaks the "officiel et confiance" typographic identity established across v4.0–v4.3 | Fraunces is the identity font for h1 and display use. Any replacement requires explicit milestone sign-off. |

---

## "Looks Done But Isn't" Checklist

- [ ] **Dark mode**: Every new color pair verified for contrast in dark mode independently, not just light mode.
- [ ] **Shadow DOM token rename**: After any rename, `grep -r "old-token" public/assets/js/components/` returns zero results.
- [ ] **Focus ring hex**: After a primary blue change, `grep -r "22,80,224\|1650E0" public/assets/js/components/` returns zero or updated results.
- [ ] **color-mix() dark overrides**: Every `color-mix()` expression added to `:root` has a corresponding explicit override in `[data-theme="dark"]`.
- [ ] **Google Fonts URL**: All `.htmx.html` files use the updated font URL after a weight change.
- [ ] **Service worker cache**: `sw.js` cache version incremented after any font URL or CSS file path change.
- [ ] **JS selectors**: Every HTML restructuring was preceded by a grep of the page JS file for selectors targeting the changed elements. All selectors verified to still resolve.
- [ ] **Playwright E2E**: Page-specific E2E spec passes green after any change to that page's HTML or JS.
- [ ] **Visible output**: The phase produced at least one screenshot-demonstrable visual change on at least one real page.
- [ ] **@layer registration**: No new `@layer name { ... }` exists in any file that was not pre-declared in `design-system.css`'s `@layer base, components, v4;` line.

---

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| HTML restructuring broke JS interactions (v4.2 pattern) | HIGH — often requires page rebuild | Revert HTML to last known working state. Apply visual changes purely via CSS if possible. If restructuring is unavoidable, fix JS selectors page by page with E2E verification at each step before proceeding to the next page. |
| Token rename broke Shadow DOM components | MEDIUM — grep + search-replace across JS files | `grep -r "old-token-name" public/assets/js/components/` → update all occurrences → verify visually in both themes. |
| Dark mode contrast failure | LOW — single token value adjustment | Update the failing token value in `[data-theme="dark"]` → re-run contrast check → verify with axe DevTools. |
| Service worker serving stale fonts | LOW — cache version bump | Increment cache version constant in `sw.js` → deploy → users hard-refresh once. |
| color-mix() derived token wrong in dark mode | LOW — add missing dark override | Add explicit computed value for the derived token in `[data-theme="dark"]` block. |
| @layer ordering conflict | MEDIUM — requires CSS audit | Identify conflicting layer declarations across all CSS files → consolidate layer order in `design-system.css` → test 3+ representative pages. |
| Infrastructure-only phase with no visible output | MEDIUM — requires phase replanning | Identify one page-level visual change that can be shipped alongside or instead of the infrastructure work. Merge both into the same commit. |

---

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| HTML restructuring breaks JS selectors | Every page-touching phase | Playwright E2E for that page passes before phase closes |
| color-mix() tokens wrong in dark mode | Token/palette phase | Manual dark mode visual review on all modified pages |
| Token rename breaks Shadow DOM components | Any token rename phase | `grep -r "old-name" public/assets/js/components/` returns zero |
| Hardcoded focus ring hex stale after brand color change | Brand color phase | `grep -r "22,80,224" public/assets/js/components/` returns zero or updated |
| Dark mode contrast violations | Any phase introducing new colors | Lighthouse accessibility score maintained; axe scan passes |
| @layer ordering disrupted | CSS architecture phase | Computed styles match expected on 3 representative pages |
| Font loading regression | Typography phase | Network tab shows correct font file on all pages; sw.js version incremented |
| Infrastructure-only phase with no visible output | Milestone planning stage | Phase sign-off requires screenshot evidence of visible change per page |

---

## Sources

- Codebase inspection: `/home/user/gestion_votes_php/public/assets/css/design-system.css`, `/home/user/gestion_votes_php/public/assets/js/components/ag-modal.js`, `ag-toast.js`, `ag-confirm.js`, `/home/user/gestion_votes_php/public/wizard.htmx.html`, `/home/user/gestion_votes_php/.planning/PROJECT.md`
- [CSS Custom Properties in Shadow DOM — DEV Community](https://dev.to/michaelwarren1106/public-css-custom-properties-in-the-shadow-dom-4hc6)
- [Shadow roots and inheritance — Kitty Giraudel](https://kittygiraudel.com/2021/08/23/shadow-roots-and-inheritance/)
- [Dark Mode in Web Components — DEV Community](https://dev.to/stuffbreaker/dark-mode-in-web-components-is-about-to-get-awesome-4i14)
- [Dark Mode Does Not Satisfy WCAG Contrast by Itself — BOIA](https://www.boia.org/blog/offering-a-dark-mode-doesnt-satisfy-wcag-color-contrast-requirements)
- [Designing accessible color systems across themes — DEV Community](https://dev.to/beefedai/designing-accessible-color-systems-and-ensuring-contrast-across-themes-2i43)
- [Font Loading Strategies 2025 — font-converters.com](https://font-converters.com/guides/font-loading-strategies)
- [Preload web fonts — web.dev](https://web.dev/articles/codelab-preload-web-fonts)
- [CSS Cascade Layers vs BEM — Smashing Magazine 2025](https://www.smashingmagazine.com/2025/06/css-cascade-layers-bem-utility-classes-specificity-control/)
- [Refactoring CSS: Strategy and Regression — Smashing Magazine](https://www.smashingmagazine.com/2021/08/refactoring-css-strategy-regression-testing-maintenance-part2/)
- PROJECT.md v4.1 decision note: "infrastructure delivered but no visible visual impact; page-by-page redesign needed" — confirms v4.1/v4.2 regression pattern as the canonical failure mode to avoid

---
*Pitfalls research for: visual identity evolution in existing app with Shadow DOM, dark/light mode, 35k CSS, 23 Web Components*
*Researched: 2026-04-03*
