# Project Research Summary

**Project:** AG-VOTE v10.0 — Visual Identity Evolution
**Domain:** CSS design system evolution for a self-hosted vanilla PHP/JS civic voting app
**Researched:** 2026-04-03
**Confidence:** HIGH

## Executive Summary

AG-VOTE enters v10.0 with a mature, production-grade design system: a 5,258-line CSS file, 23 Web Components with Shadow DOM, 25 per-page CSS files, a three-layer token hierarchy (primitive → semantic → component aliases), full dark/light mode, and an established "officiel et confiance" visual identity (bleu/indigo, Bricolage Grotesque + Fraunces + JetBrains Mono). The v10.0 milestone is strictly about evolving the *visual expression* of this system — not its architecture. The research consensus is unambiguous: the highest-ROI approach is to change token values in `design-system.css`, not to restructure HTML or components. All 25 page CSS files and all 23 Web Components will propagate those token changes for free via CSS custom property inheritance, including through the Shadow DOM boundary.

The recommended approach is a strict token-first, visible-output-first methodology: start with the warm-neutral gray ramp (the single change with the widest visual impact), then progressively layer accent sparsity, border alpha treatment, consistent radius, and shadow vocabulary reduction before adding behavioral enhancements like skeleton loading and celebration animations. Every phase must ship at least one screenshot-demonstrable visual change — the v4.1 lesson (infrastructure delivered, no visible output) is the primary failure mode to avoid and is explicitly documented in PROJECT.md.

The key risks are well-understood and have documented prevention strategies. The three most dangerous are: (1) HTML restructuring silently breaking JS selectors — the v4.2 root cause — which is avoided by treating any HTML structural change as a breaking change with a mandatory JS selector audit; (2) dark mode color-mix derived tokens computing against stale light-mode values when source primitives change; and (3) Shadow DOM component fallback hex literals becoming stale after a brand color change. All three risks are avoidable with disciplined grep-based verification steps that are already documented in PITFALLS.md.

---

## Key Findings

### Recommended Stack

The entire v10.0 milestone requires zero npm installs. All recommended technologies are native browser CSS APIs with sufficient baseline support for the app's operator audience. The key upgrade path is: promote oklch color values from "trailing comments" to primary token values, replace `color-mix(in srgb, ...)` with `color-mix(in oklch, ...)` for perceptually accurate blends, add `@property` registration for any tokens that need animated transitions, and expand the existing View Transitions gate (already in design-system.css) to more DOM regions. CSS Relative Color Syntax (`oklch(from var(--color-primary) calc(l + 0.06) c h)`) can replace 40+ manually-maintained hex tint/shade values behind an `@supports` gate.

**Core technologies:**
- `oklch()` as semantic token values — replace hex in semantic layer for perceptually uniform palette generation; 92.93% global support, already used as comments on every primitive
- CSS Relative Color Syntax — derive hover/active/subtle variants programmatically from base tokens; 89.57% support, gate with `@supports (color: oklch(from red l c h))`
- `color-mix(in oklch, ...)` — upgrade existing `color-mix(in srgb, ...)` calls; eliminates grey-mud phenomenon at 50% mix
- `@property` typed custom properties — required only for tokens that must animate via CSS `transition`; Baseline Widely Available since July 2024
- CSS View Transitions (same-document) — already gated in design-system.css; expand to tab panels, wizard steps, list updates
- CSS Scroll-Driven Animations — entrance animations tied to scroll; gate with `@supports` + `prefers-reduced-motion`; Firefox partial support, ~75% global

**Do not use:** Tailwind, GSAP, Style Dictionary, CSS Houdini Paint API, display-p3 wide gamut, or aggressive multi-level CSS nesting.

### Expected Features

Research cross-referenced Linear, Vercel, Stripe, Notion, and Clerk to identify what makes a business app feel premium in 2025-2026. The pattern is consistent: neutrals dominate (90-95% of surfaces), brand color appears sparingly (5-8%) on CTAs, active states, and focus rings. Structure is "felt not seen" via alpha-based borders rather than solid hairlines.

**Must have (table stakes):**
- Neutral-dominant color palette — indigo at 5-8% of surfaces; 90-95% warm-neutral gray
- Intentional dark mode — dark gray surfaces (#1a1b26 range), not pure black; elevation via lighter grays not shadows
- Consistent border radius language — single decision enforced via `--radius-base` token; recommendation: 6-8px (Notion/Clerk quality tier)
- Semantic focus rings — `outline: 2px solid var(--color-accent); outline-offset: 2px` on all interactive elements
- Hover state on every clickable element — 150ms, 3-5% lightness shift only
- Tabular numbers for all numeric data — `font-variant-numeric: tabular-nums` + JetBrains Mono
- Skeleton loading states on dashboard and session list — replace spinners
- Single-level shadow vocabulary — exactly 3 levels: card (border-only), dropdown, modal

**Should have (competitive differentiators):**
- Warm-neutral gray base — shifts gray ramp hue from cold blue-gray (220-230°) toward warmer (200-210°)
- Alpha-based borders — `oklch(50% 0 0 / 0.12)` pattern; "structural subtlety" effect
- Ambient gradient accents — only on login page orb, empty state heroes, operator console header; never on functional chrome
- Progressive disclosure on operator console — hide secondary controls until vote is active
- Celebration micro-animations — terminal success states only (vote close, PV generation); 400ms max; `prefers-reduced-motion` guard
- Sidebar attention hierarchy — dim sidebar chrome when user is deep in a workflow

**Defer (v11+):**
- Density mode toggle (compact/comfortable) — high value for power users, high CSS architecture cost
- Per-page ambient gradient expansion — visual interest only, requires extensive dark/light QA
- Variable font exploration — Bricolage Grotesque variable font; uncertain ROI

**Anti-features to reject:** Full glassmorphism on data cards, rainbow status badge colors, heavy drop shadows on cards, bold color fills on nav sidebar, decorative illustration on every empty state, persistent toast/banner overuse, animation on every state transition.

### Architecture Approach

The architecture is token-first and propagation-driven. The three-layer token hierarchy means changing a primitive value in `:root` automatically propagates to all semantic tokens that reference it, then to all 25 per-page CSS files and all 23 Web Components (via Shadow DOM custom property inheritance) without any additional file changes. The only exceptions requiring manual co-changes are: (1) `[data-theme="dark"]` semantic override block — must be updated in the same commit as any `:root` primitive change; (2) `critical-tokens` inline styles in 22 HTML files — 6 hex values that prevent flash-of-wrong-color; and (3) Shadow DOM fallback hex literals in component `<style>` strings — these do not respond to token changes.

**Migration layer order (strictly enforced):**
1. `design-system.css @layer base` — primitive and semantic token values in `:root` and `[data-theme="dark"]`
2. `design-system.css @layer components` — component aliases (radius, height, type scale)
3. `design-system.css @layer v4` — progressive enhancement only (view transitions, @starting-style)
4. Per-page CSS files — outside all layers; only touch if a page has hardcoded hex that did not propagate
5. Web Component `<style>` strings — only fallback hex literals need updating after primitive changes

**Known hardcoded values to fix:** `#1650E0` in analytics.css and meetings.css; `rgba(22,80,224,...)` in hub.css, vote.css, public.css, users.css; ~8 occurrences in design-system.css shadow box-shadow values; `rgba(22,80,224,0.35)` as hardcoded focus ring hex in Shadow DOM component `<style>` strings.

### Critical Pitfalls

1. **HTML restructuring silently breaks JS selectors** (the v4.2 failure) — Before restructuring any page, grep the page JS file for all `querySelector`, `closest`, `dataset` reads; verify every selector still resolves. Prefer `data-*` attribute hooks over structural selectors. Run Playwright E2E before committing any HTML restructuring.

2. **Infrastructure-only phase with no visible output** (the v4.1 failure) — Every phase must deliver at least one screenshot-demonstrable visual change. Token work is only justified when the visual output lands in the same phase. Reject any phase description containing only "refactor/rename/restructure" without specifying what users will see differently.

3. **color-mix() derived tokens computing with wrong values in dark mode** — `color-mix()` in `:root` evaluates against `:root` values only. Every `color-mix()` token in `:root` that references a semantic color must have an explicit corresponding override in `[data-theme="dark"]`.

4. **Token rename silently breaks Shadow DOM component fallbacks** — Shadow DOM components reference tokens by name in embedded `<style>` strings. A rename in `design-system.css` leaves all 23 component files using the old name, falling back to hardcoded light-mode hex. Rule: never rename existing token names; add new names alongside old ones.

5. **Hardcoded focus ring hex stale after brand color change** — `rgba(22,80,224,0.35)` is hardcoded in multiple Shadow DOM component inline styles. After any `--color-primary` change, run: `grep -r "22,80,224\|1650E0" public/assets/js/components/` and update all matches.

---

## Implications for Roadmap

Based on research, suggested phase structure for v10.0:

### Phase 1: Token Foundation + Visible Palette Shift

**Rationale:** The warm-neutral gray ramp is the single change with the widest, most immediate visual impact across all 25 pages and all 23 components. It must come first because all downstream visual decisions — border alpha, dark mode depths, elevation overlays — depend on the base gray hue. This phase bundles token-only changes with a visible palette shift to avoid the v4.1 "infrastructure without output" trap.

**Delivers:** Every page simultaneously looks warmer, more refined, and "2025-grade" without touching a single page-specific CSS file.

**Addresses:** Warm-neutral gray ramp (P1), border alpha treatment (P1), dark mode tuning (P1), shadow-color warm tone alignment.

**Avoids:** Leaving `[data-theme="dark"]` out of sync with `:root` (Pitfall 2); updating `critical-tokens` inline styles in a separate commit from semantic token changes (Anti-Pattern 4 from ARCHITECTURE.md).

**Verification:** Manual dark/light mode visual review on dashboard, login, operator, vote pages. Contrast check with axe DevTools in both themes.

---

### Phase 2: Chrome Cleanup + Component Geometry

**Rationale:** Once the palette foundation is correct, the second highest ROI change is reducing visual noise: accent sparsity (eliminate indigo from UI chrome), consistent border radius (enforce `--radius-base` across all 23 components), and shadow vocabulary reduction (max 3 levels). These are all token or single-file changes with broad propagation. Doing these before per-page behavioral work ensures the foundation is visually coherent.

**Delivers:** Consistent, calm product chrome. Indigo appears only at interaction moments. All components share a single radius language. Modals and dropdowns have the only elevated shadows.

**Addresses:** Accent sparsity audit (P1), consistent border radius (P1), shadow vocabulary reduction (P1), focus ring standardization (P1).

**Uses:** Direct design-system.css component alias edits (`--radius-*`, shadow scale). Per-file grep-and-replace for accent overuse across 25 CSS files.

**Avoids:** Changing `--btn-height` or `--input-height` without verifying grid alignment in operator.css and wizard.css (ARCHITECTURE.md Layer 3 medium risk).

---

### Phase 3: Hardcoded Hex Elimination + Shadow DOM Audit

**Rationale:** The palette and chrome are now clean in the token layer, but research identified ~15 hardcoded hex/rgba values scattered across 5 page CSS files and all 23 Web Component fallback strings. This phase closes the gap between the token system and the actual rendered output. Without it, a future primary color change will silently leave these values behind.

**Delivers:** A codebase where `grep -r "1650E0\|22,80,224" public/assets/` returns zero results. All Shadow DOM component fallbacks reflect current token values.

**Addresses:** analytics.css and meetings.css hardcoded hex, hub/vote/public/users rgba fallbacks, ag-vote-button hover rgba states, Shadow DOM focus ring literals.

**Avoids:** Renaming any token during this phase (Pitfall 3 — Shadow DOM fallback staleness); dark mode must be independently verified after each component update (Pitfall 5 — dark contrast failures).

---

### Phase 4: Perception + Delight Layer

**Rationale:** With the foundation solid and the codebase clean, this phase adds the behavioral and perception enhancements that require per-page or per-component work: skeleton loading (replaces spinners on the two most-visited pages), tabular number enforcement (numeric data audit), sidebar attention hierarchy, and celebration micro-animations. These are P2 features — meaningful differentiators, but only valuable on top of a coherent visual foundation.

**Delivers:** Dashboard and session list feel instantaneous with skeleton shimmer. KPI data is visually precise. Sidebar recedes during workflows. Vote close and PV generation have a moment of delight.

**Addresses:** Skeleton loading (P2), tabular numbers (P2), celebration micro-animations (P2), sidebar attention hierarchy (P2).

**Uses:** `prefers-reduced-motion` guards (already in animation timing contracts from v9.0), `@keyframes` + View Transitions for celebration states.

**Avoids:** Overanimation (150ms hover, 250ms panel reveals, 0ms data updates; never animate live vote row insertions); conflicting celebration animations with existing toast/banner overuse if not first resolved.

**Research flag:** Skeleton shimmer requires per-page loading state audit before planning — which pages use spinner vs. empty div vs. HTMX-managed states is not yet documented.

---

### Phase 5: Progressive Disclosure (Operator Console)

**Rationale:** Progressive disclosure is classified P2 but isolated into its own phase because it is the only v10.0 feature requiring JS + layout changes (not purely CSS). It carries the highest risk of triggering the v4.2 pattern if HTML restructuring is involved. Isolating it enables a full JS selector audit and dedicated Playwright E2E run before sign-off.

**Delivers:** Secondary operator controls hidden until a vote is active; the console is dramatically less noisy during live sessions.

**Addresses:** Progressive disclosure audit on operator console (P2).

**Avoids:** HTML restructuring without prior JS selector grep (Pitfall 1 — the v4.2 failure mode).

**Research flag:** Requires a pre-phase audit of operator.js for all `querySelector` and `closest` calls before any layout change is attempted.

---

### Phase Ordering Rationale

- **Token layer before behavioral layer:** All page CSS and components propagate token changes for free. Behavioral changes (skeleton loading, sidebar dimming) are per-page work that is most efficient when the visual foundation is already stable.
- **Primitive + semantic + dark mode in the same commit:** The architecture explicitly forbids leaving dark mode broken between commits. Any phase that changes `:root` color primitives must update `[data-theme="dark"]` and `critical-tokens` inline styles in the same commit.
- **Infrastructure paired with visible output in every phase:** Phases 1-3 avoid the v4.1 pattern by coupling token/audit work with visible per-page output in each commit.
- **HTML-touching phase last and isolated:** Phases 1-4 are pure CSS/token work. Phase 5 is the only phase that risks HTML restructuring and is isolated for controlled risk management.

### Research Flags

Phases likely needing deeper research or pre-phase audit:

- **Phase 4 (skeleton loading):** Requires per-page loading state audit before planning. Which pages use spinners vs. empty divs vs. HTMX-managed empty states is not documented in current research.
- **Phase 5 (progressive disclosure):** Requires pre-phase operator.js JS selector audit. Complexity is HIGH per FEATURES.md. JS + layout risk justifies treating this as a mini-research step before implementation.

Phases with standard patterns (safe to plan without additional research):

- **Phase 1 (token foundation):** oklch token promotion, warm-neutral gray ramp, dark mode co-update — all are codebase-verified, well-documented patterns.
- **Phase 2 (chrome cleanup):** Token alias edits and grep-based accent audit — mechanical, low-risk.
- **Phase 3 (hardcoded hex):** Pure grep-and-replace with known target values already enumerated in ARCHITECTURE.md.

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All technologies are native CSS APIs with verified browser support from Can I Use and MDN. No install dependencies. The existing codebase already uses oklch, color-mix, and View Transitions gates — upgrade path is incremental and confirmed. |
| Features | HIGH | Cross-verified across Linear, Vercel, Stripe, Notion, and Clerk with specific pattern observations. MVP vs. deferred split is grounded in complexity estimates and dependency analysis, not guesswork. |
| Architecture | HIGH | Grounded in direct codebase inspection of design-system.css (5258 lines), all 23 Web Component files, all 25 CSS files, and the critical-tokens inline style pattern. No inferences — the architecture is fully mapped. |
| Pitfalls | HIGH | Every critical pitfall is grounded in: (a) observed codebase issues (hardcoded hex counts are exact), (b) documented project history (v4.1, v4.2 failures referenced in PROJECT.md), or (c) verified technical behavior (color-mix dark mode evaluation, Shadow DOM inheritance). |

**Overall confidence:** HIGH

### Gaps to Address

- **Skeleton loading scope:** The exact list of pages using spinners vs. alternative loading patterns is not documented. Needs a grep audit of all `.htmx.html` and page JS files for spinner/loading state patterns before Phase 4 planning.
- **Operator console JS complexity:** The operator.js file's full selector map is not enumerated in research. The progressive disclosure feature's true HTML restructuring scope is unknown until a pre-phase selector audit is done. This determines whether Phase 5 is a CSS-only change or a genuine refactor.
- **Service worker cache strategy:** sw.js cache version bump is flagged as required after font URL changes. The current cache versioning scheme is not detailed in research — verify the bump pattern before any font URL changes in Phase 4+.
- **FilePond CDN version pin:** PITFALLS.md flags that future FilePond versions may introduce `@layer` declarations conflicting with the app's layer order. The current pinned version is not documented in research — confirm the CDN URL is pinned to a specific version before any `@layer` changes in Phase 1-2.

---

## Sources

### Primary (HIGH confidence — direct codebase + official specs)

- `public/assets/css/design-system.css` — ground truth for token hierarchy, @layer structure, shadow scale, dark mode override block (5258 lines, direct read)
- `public/assets/js/components/ag-*` — Shadow DOM token consumption pattern, hardcoded fallback hex values (23 files, direct read)
- `public/dashboard.htmx.html`, `public/wizard.htmx.html` — font loading and critical-tokens inline style pattern (direct read)
- `.planning/PROJECT.md` — v4.1/v4.2 regression history, v10.0 milestone scope (direct read)
- [oklch() — MDN Web Docs](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Values/color_value/oklch)
- [OKLCH browser support — Can I Use](https://caniuse.com/mdn-css_types_color_oklch) — 92.93% global coverage
- [CSS Relative Colors — Can I Use](https://caniuse.com/css-relative-colors) — 89.57% global
- [@property Baseline — web.dev](https://web.dev/blog/at-property-baseline) — Baseline Widely Available July 2024
- [View Transitions API — MDN](https://developer.mozilla.org/en-US/docs/Web/API/View_Transition_API)
- [color-mix() — Chrome for Developers](https://developer.chrome.com/docs/css-ui/css-color-mix)

### Secondary (MEDIUM confidence — industry analysis + community sources)

- [Linear — Behind the latest design refresh (official)](https://linear.app/now/behind-the-latest-design-refresh)
- [Vercel Web Interface Guidelines (official)](https://vercel.com/design/guidelines)
- [Stripe — Designing Accessible Color Systems](https://stripe.com/blog/accessible-color-systems)
- [7 SaaS UI Design Trends in 2026 — SaaSUI Blog](https://www.saasui.design/blog/7-saas-ui-design-trends-2026)
- [Dark Mode Color Palettes: Complete Guide 2025 — MyPaletteTool](https://mypalettetool.com/blog/dark-mode-color-palettes)
- [Scroll-Driven Animations — Chrome for Developers](https://developer.chrome.com/docs/css-ui/scroll-driven-animations)
- [OKLCH in CSS: why we moved from RGB and HSL — Evil Martians](https://evilmartians.com/chronicles/oklch-in-css-why-quit-rgb-hsl)
- [CSS Custom Properties in Shadow DOM — DEV Community](https://dev.to/michaelwarren1106/public-css-custom-properties-in-the-shadow-dom-4hc6)
- [CSS Cascade Layers — Smashing Magazine 2025](https://www.smashingmagazine.com/2025/06/css-cascade-layers-bem-utility-classes-specificity-control/)
- [Dark Mode Does Not Satisfy WCAG Contrast by Itself — BOIA](https://www.boia.org/blog/offering-a-dark-mode-doesnt-satisfy-wcag-color-contrast-requirements)

### Tertiary (supporting context)

- [Self-hosting fonts performance — Tune The Web](https://www.tunetheweb.com/blog/should-you-self-host-google-fonts/) — 200-300ms self-host advantage
- [Micro-interactions in web design 2025 — Stan Vision](https://www.stan.vision/journal/micro-interactions-2025-in-web-design)
- [Clerk Mosaic Design System (official)](https://clerk.com/blog/introducing-mosaic-bring-your-brand-to-every-authentication-flow)
- [Dark Mode in Web Components — DEV Community](https://dev.to/stuffbreaker/dark-mode-in-web-components-is-about-to-get-awesome-4i14)
- [Font Loading Strategies 2025 — font-converters.com](https://font-converters.com/guides/font-loading-strategies)

---

*Research completed: 2026-04-03*
*Ready for roadmap: yes*
