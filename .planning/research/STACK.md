# Stack Research

**Domain:** Visual identity evolution — CSS design system refinement for a self-hosted vanilla PHP/JS voting app
**Researched:** 2026-04-03
**Confidence:** HIGH

---

## Context: What Already Exists (Do Not Re-Research)

The design system is mature. These are validated and in production:
- CSS custom property hierarchy: primitive → semantic → component aliases (~5,258 LOC in design-system.css)
- `@layer base, components, v4` cascade ordering
- oklch values documented as comments on every primitive (but semantic tokens still use hex)
- `color-mix(in srgb, ...)` already used for tint/shade tokens
- Three-depth background model (bg / surface / raised)
- `prefers-reduced-motion` media queries at ~3 locations in design-system.css
- `@supports (view-transition-name: test)` already gating view transitions
- Animation contracts: `--duration-*` and `--ease-*` token families
- Fonts loaded via Google Fonts CDN (preconnect + stylesheet link)
- No `@property` typed custom properties yet

The milestone is about *evolving the visual identity* — colors, typography, component aesthetics — not rebuilding infrastructure.

---

## Recommended Stack

### Core Technologies (No Install — Pure CSS Browser APIs)

| Technology | Version/Spec | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| `oklch()` as semantic token values | CSS Color Level 4 | Replace hex values in semantic tokens with perceptually uniform colors | Already in primitives as comments; 92.93% global support (Chrome 111+, Firefox 113+, Safari 15.4+). Enables smooth palette generation — equal lightness steps produce equal perceived brightness, unlike hex/HSL |
| CSS Relative Color Syntax | CSS Color Level 5 | Derive hover/active/subtle variants programmatically from a base oklch token | 89.57% support (Chrome 131+, Firefox 133+, Safari 18+). Eliminates 40+ manually-maintained tint/shade hex values. Pattern: `oklch(from var(--color-primary) calc(l + 0.06) c h)` |
| `color-mix(in oklch, ...)` | CSS Color Level 5 | Replace `color-mix(in srgb, ...)` calls for perceptually better blends | Already used in srgb mode; upgrading the interpolation space to oklch eliminates the "grey mud" phenomenon at 50% mix |
| `@property` typed custom properties | CSS Houdini / Properties & Values API Level 1 | Register animatable color tokens so CSS `transition` can interpolate between them | Baseline Widely Available since July 2024 (Chrome 85+, Firefox 128+, Safari 16.4+). Required for `transition: --color-primary 200ms` to actually animate between values |
| CSS View Transitions (same-document) | View Transition API Level 1 | Animate content swaps: tab panels, wizard steps, list updates | Already gated with `@supports (view-transition-name: test)` in design-system.css — expand usage. Chrome 111+, Firefox 133+, Safari 18+. No JS library needed |
| CSS Scroll-Driven Animations | CSS Animations Level 2 | Subtle entrance animations tied to scroll position for page sections | Chrome 115+, Safari 26+. Firefox partial. Gate with `@supports (animation-timeline: view())`. Must wrap in `@media not (prefers-reduced-motion)` |

### Supporting Libraries (Browser Tools — Zero Install)

| Library | Purpose | When to Use |
|---------|---------|-------------|
| `oklch.fyi` (browser tool) | Generate perceptually uniform palettes during design phase | When choosing new hue or chroma values for the evolved identity |
| `Magiklch` (browser tool) | Generate 11-step OKLCH scales from a single base color with APCA contrast scores | When defining new primitive color ramps |
| APCA Contrast Checker (apcacontrast.com) | Verify perceptual contrast for evolved color palette | Run on every semantic color change — more accurate than WCAG 2.1 ratio for modern fonts |

### Development Tools

| Tool | Purpose | Notes |
|------|---------|-------|
| Browser DevTools color picker (oklch mode) | Inspect and edit oklch values directly in computed styles | Chrome DevTools supports oklch inspection natively since v111 |
| PostCSS oklch plugin (`postcss-oklab-function`) | Transform oklch() to sRGB fallback for legacy browsers | Only if supporting Safari < 15.4 or legacy Android (< 7% of traffic in typical self-hosted deployment) |

---

## Installation

No npm packages are required for this milestone. All recommended technologies are native browser CSS APIs.

```bash
# Nothing to install — all capabilities are native CSS in supported browsers.

# Optional: Self-host fonts instead of Google Fonts CDN
npm install -D @fontsource/bricolage-grotesque @fontsource/fraunces @fontsource/jetbrains-mono
# Then serve from /public/assets/fonts/ with @font-face rules instead of CDN link.

# Optional: PostCSS oklch fallback transformation (only if legacy browser support is a hard requirement)
npm install -D postcss postcss-oklab-function
```

---

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|-------------------------|
| `oklch()` as semantic token values | HSL with hand-tuned values | Never for new tokens — HSL has non-uniform perceived lightness, making palette consistency a manual, error-prone process |
| CSS Relative Color Syntax | Pre-computed hex shades (current approach) | Only if supporting Safari < 18 with strict no-@supports policy. The 7% gap is real but narrows monthly |
| CSS View Transitions | GSAP / Framer Motion | Never for this project — these libraries are incompatible with the no-framework vanilla JS identity and add 50-100KB bundle weight. View Transitions are GPU-accelerated |
| `color-mix(in oklch, ...)` | `color-mix(in srgb, ...)` (current) | Use srgb only during a backward-compat transition period when matching legacy hex blends exactly is required |
| Self-hosted WOFF2 fonts | Google Fonts CDN (current) | Google Fonts CDN is acceptable for the admin/operator audience. Self-host only if LCP > 2.5s or GDPR data-residency is a concern |
| `@property` for animatable tokens | Untyped CSS variables (current) | Untyped vars work fine for static tokens. `@property` is only needed when CSS must animate the variable value itself through a transition |

---

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| display-p3 wide gamut `color(display-p3 ...)` | AG-VOTE serves operators on office/government hardware — wide-gamut monitors are rare. display-p3 adds `@supports` fallback complexity with near-zero perceptual benefit for the "officiel et confiance" muted-blue palette | Stay in sRGB gamut expressed as oklch. oklch IS perceptually uniform without requiring wide gamut |
| CSS Houdini Paint API / Worklet | Requires a bundled worker script, incompatible with the zero-build-step philosophy | CSS gradients + mask + mix-blend-mode for visual effects |
| Tailwind CSS or utility-first framework migration | Explicitly out of scope in PROJECT.md. The 35k LOC CSS token system is coherent and working | Continue the custom property + `@layer` approach |
| GSAP / Anime.js | No build step, 50-100KB for effects native CSS handles as of 2026 | CSS transitions + `@keyframes` + View Transitions API |
| Style Dictionary (build tool for tokens) | Requires a Node.js pipeline where none exists. `design-system.css` IS the token source of truth | Edit design-system.css directly; comment blocks serve as the token spec |
| Aggressive CSS nesting (multi-level `& > & .foo`) | Still a focus area for Interop 2026 — Firefox has partial bugs in complex nesting. The existing flat `.selector` pattern is battle-tested | Simple `&:hover`, `&:focus` nesting only |

---

## Stack Patterns by Use Case

**Evolving the primary color hue:**
- Change `--blue-*` primitive oklch values — adjust only the `h` (hue) parameter, preserve L and C
- Semantic tokens (`--color-primary`, `--color-primary-hover`, etc.) resolve automatically
- Run APCA check on new hue at same L/C values — oklch chroma is perceptually uniform across hues so same C = same vibrance

**Adding a new accent or persona color:**
- Generate 11-step OKLCH scale using Magiklch from a single base color
- Add to primitives block in design-system.css following existing ramp naming
- Create semantic aliases (subtle/hover/active/text) following existing naming convention
- Never reference primitives directly in component CSS — always via semantic aliases

**Animating a color token (gradient fade on hover, focus ring pulse):**
- Register the token with `@property`:
  ```css
  @property --color-primary {
    syntax: '<color>';
    inherits: true;
    initial-value: oklch(0.52 0.195 265);
  }
  ```
- Apply `transition: --color-primary 200ms var(--ease-standard)` on the element
- This is the only way CSS natively interpolates between custom property color values

**Upgrading font loading (if LCP optimization needed):**
- Replace Google Fonts `<link>` with self-hosted `@font-face` blocks in `app.css`
- Use `font-display: swap` with `size-adjust` descriptor to minimize layout shift
- Preload only the primary weight: `<link rel="preload" href="/assets/fonts/bricolage-grotesque-latin-600.woff2" as="font" crossorigin>`
- Maximum 2 preload hints (one per primary family, most-used weight only)
- Self-hosting saves 200-300ms vs CDN on first visit

**Upgrading color-mix interpolation space:**
- Find/replace `color-mix(in srgb,` → `color-mix(in oklch,` in design-system.css
- Visual output will be slightly more vibrant (expected, correct)
- One-line change per token, safe atomic upgrade

---

## Version Compatibility

| Feature | Chrome | Firefox | Safari | Global Support | Notes |
|---------|--------|---------|--------|----------------|-------|
| `oklch()` | 111+ | 113+ | 15.4+ | 92.93% | Use as semantic token values without fallback |
| `color-mix()` | 111+ | 113+ | 16.2+ | ~94% | Baseline Widely Available. Already used |
| CSS Relative Color Syntax | 131+ (full) | 133+ (full) | 18+ (full) | 89.57% | Gate with `@supports (color: oklch(from red l c h))` |
| `@property` | 85+ | 128+ | 16.4+ | ~94% | Baseline since July 2024. Safe for production |
| View Transitions (same-document) | 111+ | 133+ | 18+ | ~88% | Already gated in design-system.css |
| Scroll-Driven Animations | 115+ | partial | 26+ | ~75% | Gate with `@supports` + `@media not (prefers-reduced-motion)` |
| CSS Nesting (`&:hover`) | 112+ | 117+ | 16.5+ | ~93% | Safe for simple single-level nesting |

---

## Integration with Existing design-system.css

The existing file has a clean, non-breaking upgrade path:

1. **oklch promotion in primitives**: Primitives already have oklch as trailing comments (e.g., `--stone-50: #FAFAF7;  --stone-50: oklch(0.969 0.006 95);`). The duplicate declaration pattern is valid CSS — the second one wins. The comments confirm the values are already equivalent. Flip to use oklch as the primary value.

2. **Semantic token upgrade**: `--color-primary: #1650E0` → `--color-primary: oklch(0.52 0.195 265)`. Visually identical for sRGB displays; gains perceptual computation benefits for derived tokens.

3. **Relative color for derived tokens**: The 12 `color-mix(in srgb, var(--color-primary) X%, white)` tokens can become relative color syntax. Gate with `@supports` for progressive enhancement; keep color-mix as fallback.

4. **@property registration block**: Add a `@layer properties` layer before `@layer base` to register animatable tokens. Does not affect existing cascade order.

5. **color-mix interpolation space upgrade**: Change `color-mix(in srgb, ...)` to `color-mix(in oklch, ...)` — produces more vibrant, perceptually accurate blends. One-line change per token.

6. **View Transitions expansion**: The `@supports (view-transition-name: test)` block in design-system.css already assigns `view-transition-name` to `op-tab-panel` and `wiz-step-body`. Expand to additional DOM regions as the visual identity work reveals transition opportunities.

---

## Sources

- [oklch() — MDN Web Docs](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Values/color_value/oklch) — syntax and usage, HIGH confidence
- [OKLCH browser support — Can I Use](https://caniuse.com/mdn-css_types_color_oklch) — 92.93% global coverage, verified
- [CSS Relative Colors — Can I Use](https://caniuse.com/css-relative-colors) — 89.57% global (full + partial), Chrome 131+, Firefox 133+, Safari 18+, verified
- [@property Baseline — web.dev](https://web.dev/blog/at-property-baseline) — Baseline Widely Available July 2024, HIGH confidence
- [OKLCH in CSS: why we moved from RGB and HSL — Evil Martians](https://evilmartians.com/chronicles/oklch-in-css-why-quit-rgb-hsl) — rationale for oklch over HSL, MEDIUM confidence
- [color-mix() — Chrome for Developers](https://developer.chrome.com/docs/css-ui/css-color-mix) — interpolation space comparison, HIGH confidence
- [View Transitions API — MDN](https://developer.mozilla.org/en-US/docs/Web/API/View_Transition_API) — same-document support matrix, HIGH confidence
- [Scroll-Driven Animations — Chrome for Developers](https://developer.chrome.com/docs/css-ui/scroll-driven-animations) — animation-timeline support, HIGH confidence
- [Best practices for fonts — web.dev](https://web.dev/articles/font-best-practices) — preload + font-display strategy, HIGH confidence
- [Self-hosting fonts performance — Tune The Web](https://www.tunetheweb.com/blog/should-you-self-host-google-fonts/) — 200-300ms self-host advantage, MEDIUM confidence
- [New CSS color spaces — web.dev](https://web.dev/blog/color-spaces-and-functions) — display-p3 vs oklch tradeoffs, HIGH confidence

---

*Stack research for: AG-VOTE v10.0 Visual Identity Evolution*
*Researched: 2026-04-03*
