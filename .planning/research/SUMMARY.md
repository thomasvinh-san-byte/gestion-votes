# Research Summary — AG-VOTE v4.1 "Design Excellence"

**Project:** AG-VOTE v4.1
**Domain:** Premium light-first visual refonte for governance/SaaS web app
**Researched:** 2026-03-19
**Confidence:** HIGH

---

## Executive Summary

AG-VOTE is a fully functional general-assembly voting platform that has reached v4.0 feature-complete status. The v4.1 milestone is a pure visual refonte — zero new features, zero infrastructure changes. The diagnosis is blunt and supported by all four research files: the current design is objectively mediocre because it exhibits every pattern that distinguishes AI-generated CSS from premium, intentional design. The symptoms are specific and measurable: uniform border-radii regardless of element size, all surfaces at the same background depth, shadows applied at identical opacity everywhere, color used for decoration rather than signal, and spacing with no semantic hierarchy (16px applied to section breaks, field gaps, and icon-label pairs equally). Functionality works. What is broken is the visual language.

The recommended fix strategy is layered and sequential. It must begin with the token system rather than individual pages, because every page-level problem traces back to how tokens are defined and consumed. The current 265+ CSS variables are bloated — they skip the primitive layer and mix semantic with ad-hoc values, producing overlap, inconsistency, and an inability to change one concept without hunting through six files. The right target is roughly 80 semantic tokens backed by 60 well-named primitives, all consumed through semantic aliases in component CSS. Once the token foundation is correct, component-level upgrades (buttons, cards, tables, badges, forms) become reliable because they reference a trustworthy system. Only after components are solid should per-page layout work begin — because pages are composed from components, not the reverse.

The primary risk is scope creep in both directions: either the refonte stays superficial (changing colors without fixing the underlying spacing and elevation model) or it drifts into feature work (adding new components not present in v4.0). Research from PITFALLS.md also flags two specific technical hazards: the oklch color migration needs hex fallbacks to avoid transparent surfaces in older Safari, and the body font-size change from 16px to 14px must be executed as one atomic sweep — not incrementally — or page CSS breaks inconsistently. Both risks are mitigatable with discipline and are called out per phase below.

---

## Key Findings

### Design Philosophy (from STACK.md)

The overarching principle is that **premium design is made of intentional differences, not uniform tokens.** What makes Stripe, Linear, and Vercel look expensive is that they break their own patterns deliberately — a tighter shadow here, more padding there, a heavier weight in one place. AI-generated CSS applies everything uniformly. Every element gets the same border-radius, the same shadow opacity, the same 16px gap. The v4.1 refonte must introduce intentional variation at every level.

**Core design references and their applicable patterns:**
- **Stripe Dashboard** — three-depth background layering, restrained color use, KPI row layout
- **Linear** — neutral canvas approach (color for signal only), sidebar-always-dark pattern, premium spacing
- **Atlassian Design System** — elevation semantics, surface/raised/overlay model, spacing guidelines
- **Radix UI** — 12-step color scale mapping, semantic color token naming convention
- **shadcn/ui (new-york)** — component CSS reference for buttons, inputs, cards (exact values verified)
- **Sonner** — canonical toast/notification CSS (values extracted from source)

**The 5 AI anti-patterns that must be eliminated from every file:**
1. Uniform border-radius (8px on badge, card, modal, and input equally)
2. Identical shadow opacity everywhere (`0 2px 8px rgba(0,0,0,0.1)` on all components)
3. Color used for decoration not signal (tinted section headers, colored stat cards)
4. Monotone spacing (16px applied to section breaks, form gaps, and icon-label pairs)
5. Weak font-weight contrast (headings at 500 when body is 400 — imperceptible difference)

### Page Layout Specifications (from FEATURES.md)

FEATURES.md provides concrete CSS grid/flex specs and ASCII diagrams for all 6 page types in the application. These are not wireframes — they are implementation-ready layout specifications with exact values.

**Six page types identified, each with distinct layout philosophy:**

| Page | Layout Pattern | Key Constraint |
|------|---------------|----------------|
| Dashboard | KPI row (4-col) + sessions list + aside | max-width 1200px, session rows 72px min-height |
| Wizard | Centered single-column, max 640px | Stepper above form card, sidebar suppressed |
| Session detail | Header meta strip + 2-col (main + sidebar) | Info sidebar 320px, sticky at scroll |
| Operator console | Full-width data-dense table | Tighter row padding 8px 16px, monospace counts |
| Vote/ballot screen | Vertically centered single card | Full viewport height, voter-facing only |
| Results/PV | Stacked sections, print-ready | Max 880px for print layout parity |

**Critical layout decisions:**
- Dashboard sessions: vertical list, not card grid — data with status/dates/CTAs scans better as rows
- Wizard: no sidebar — navigation context fights user concentration on focused tasks
- Operator: 14px body throughout — data-dense, matches Linear/Jira/GitHub table conventions
- All pages: three-level background stack (body `#EDECE6` → surface `#FAFAF7` → raised `#FFFFFF`)

### Component Architecture (from ARCHITECTURE.md)

ARCHITECTURE.md provides exact CSS specifications for every shared component, sourced from shadcn/ui (new-york), Sonner, Shopify Polaris, and AG-VOTE's own verified token values. These are ready to implement.

**Major components with their premium specs:**

1. **Buttons** — 36px default height, 500 font-weight (not 600/700), explicit property transitions only (not `all`), three size variants (30px/36px/44px), six variants (primary/secondary/ghost/danger/danger-ghost/outline). `transform: scale(0.97)` on active state gives tactile feedback.

2. **Cards** — three-section anatomy (header 20px/24px padding, body 24px, footer 16px/24px). Resting: `--shadow-xs` + border. Hover: `--shadow-md` + `translateY(-1px)` + border lightens. The shadow-vs-border trade is the core elevation signal.

3. **Tables** — `12px 16px` cell padding standard, `8px 16px` for operator dense mode. First/last column get extra 24px horizontal padding. Headers in 13px uppercase with 0.04em tracking. Stripe pattern with `--color-bg-subtle` on odd rows.

4. **Forms** — 36px input height, 12px/16px padding, `--radius-md` (6px). Focus ring via `--shadow-focus` (double ring: 2px surface gap + 4px primary). Form groups 20px gap, label-to-input 8px. No `outline` overrides — use box-shadow only.

5. **Modals** — `--radius-xl` (12px), `--shadow-xl`, header/body/footer anatomy with border dividers. Backdrop `oklch(0 0 0 / 50%)`. Enter animation: `opacity` + `translateY(8px)` → `0` over 300ms `--ease-emphasized`.

6. **Toasts (Sonner-style)** — 356px width, `--shadow-lg`, `--radius-xl`, stacked with 8px gap between toasts. Rich variant uses left-border accent stripe (3px solid status color).

7. **Badges** — pill shape (`--radius-full`) for status, `--radius-sm` (5px) for inline labels. 2px/8px padding. Three-part construction: subtle bg + text + border, all from the semantic status color group.

8. **Steppers** — connector line 2px, step circle 28px diameter. Completed: primary fill + white check. Active: primary ring (2px outline offset). Pending: border only. Font 13px medium.

**Radius hierarchy (anti-uniform-radius rule):**
- 3px — table cell indicators, hairlines
- 5px — tags, badges, inline chips, tooltips
- 6px — buttons, inputs, selects (interactive elements)
- 8px — cards, panels, dropdowns (containers)
- 12px — modals, drawers, toasts (overlay surfaces)
- 999px — pill status badges, avatars

### CSS Architecture and Token System (from PITFALLS.md)

PITFALLS.md defines the complete token hierarchy and 7 specific pitfalls that will break the refonte if not actively prevented.

**Token hierarchy (three layers):**
- Layer 1 Primitives: raw values named after what they ARE (`--blue-600`, `--stone-200`). Never used in component CSS directly.
- Layer 2 Semantic: context-aware, theme-switchable (`--color-primary`, `--color-surface`). These are what components use.
- Layer 3 Component: scoped to a single component (`--btn-bg`, `--badge-border`). Optional, for complex overrides only.

**Target token count:** 80–100 semantic tokens in `:root`, 20–30 dark overrides in `[data-theme="dark"]`. Down from the current 265+ bloated set.

**Complete shadow system (7 levels):** All use `--shadow-color: 21 21 16` (warm near-black matching the palette) in light mode, switching to pure black in dark mode. Opacity ranges from 0.04 (2xs, barely visible) to 0.18 (2xl, floating panel). Dark mode multiplies opacity by roughly 3x for visibility.

**Typography correction:** Base body drops from 16px to 14px for data-dense app convention. This is a high-risk change if done incrementally — PITFALLS.md specifies a staged naming migration: add `--text-14: 0.875rem` first, sweep all components, then rename `--text-base` in one atomic commit.

**Dark mode strategy:** Sidebar stays always-dark regardless of theme (independent token set). Body elevation is expressed via lightness in dark mode (higher surface = lighter L value), not shadows. Borders use opacity-based values (`oklch(1 0 0 / 8%)`) not hex, so they adapt automatically.

### Critical Pitfalls (from PITFALLS.md)

1. **oklch Browser Fallback Gap** — OKLCH primitives without hex fallbacks silently produce transparent surfaces in Safari < 15.4. Prevention: declare hex first, oklch second as progressive enhancement. Use `@supports(color: oklch(0 0 0))` gates for color-mix derivations.

2. **color-mix() Circular Reference** — Redefining `--color-primary` using itself inside `color-mix()` resolves to `transparent`. Prevention: always use component-scoped tokens (`--btn-bg`) as the target of color-mix, never redefine the source token.

3. **Token Proliferation** — Adding per-page one-off variables without checking semantic tokens first. Prevention: enforce the naming convention strictly. If you reach for `--quorum-bar-success-bg`, use `--color-success-subtle` instead.

4. **Shadow Strength Mismatch** — Light-mode shadows tuned to 5–10% opacity are nearly invisible in dark mode without the `--shadow-color` variable pattern. Prevention: always override `--shadow-color` in `[data-theme="dark"]` so all shadow levels adapt automatically.

5. **Typography Base Size Regression** — Changing `--text-base` from 16px to 14px without an atomic sweep breaks every component at once in different ways. Prevention: two-phase migration — introduce `--text-14` as alias, sweep references, then rename.

6. **@layer Specificity vs Direct Values** — Component CSS in `@layer v4` using raw `oklch()` directly instead of tokens bypasses theming. Prevention: `@layer v4` may reassign token values but must never use raw color values in component rules.

7. **`transition: all` Performance** — 15+ page CSS files contain `transition: all 150ms ease`. On tables with 50+ rows, hover states trigger full composite reflow. Prevention: enumerate specific properties only. Use `--transition-color` and `--transition-ui` named tokens.

---

## Implications for Roadmap

The research strongly implies a three-phase sequence: foundation first, components second, pages third. This order is not arbitrary — it reflects real dependencies. Pages are composed of components; components depend on tokens. Attempting to fix the dashboard layout without first fixing the token system produces visual improvements that are inconsistent, hard to maintain, and will need to be redone when the token audit eventually happens.

### Phase 1: Token Foundation Audit and Upgrade

**Rationale:** Every page-level visual problem in AG-VOTE (flat backgrounds, uniform shadows, weak typography hierarchy) traces to token definitions and semantic misuse. Fixing tokens first makes every subsequent phase faster and more reliable. This is the high-leverage, low-visibility work that makes the rest possible.

**Delivers:**
- Cleaned semantic token set (~80 tokens, down from 265+)
- Complete oklch primitives with hex fallbacks
- Corrected shadow scale with `--shadow-color` variable pattern and full 7-level range
- Full dark mode token parity for all new and changed tokens
- Typography scale correction including `--text-base` 14px migration (two-stage)
- Border-radius semantic aliases (`--radius-btn`, `--radius-card`, `--radius-modal`)
- Named transition tokens (`--transition-color`, `--transition-ui`, `--transition-enter`)
- Spacing scale gaps filled (add `--space-1-5` 6px, `--space-2-5` 10px, semantic aliases)

**Avoids:** Token proliferation pitfall, oklch fallback gap, shadow strength mismatch, @layer specificity issue.

**Research flag:** Skip `/gsd:research-phase`. The exact token values are already fully specified in PITFALLS.md and ARCHITECTURE.md. Execution work only.

---

### Phase 2: Component Refresh

**Rationale:** Components are the unit of reuse. Fixing them once makes every page correct automatically for the shared elements. The research provides exact specifications for all 8 component categories — this is execution work, not design exploration.

**Delivers:**
- Buttons: correct height/padding/weight, explicit transitions, tactile active state, all 6 variants
- Cards: three-section anatomy, shadow-vs-border elevation model, hover lift with `translateY(-1px)`
- Tables: semantic density variants (standard vs operator-dense), monospace data values, proper column padding
- Forms: 36px inputs, double-ring focus, 8px label-to-input spacing, validation states
- Modals: 12px radius, XL shadow, enter/exit animations using `--ease-emphasized`, backdrop blur
- Toasts: Sonner-pattern stacking, 356px width, rich variant with accent stripe
- Badges: pill status vs rectangular label, three-part color construction from semantic tokens
- Steppers: 28px circles, connector lines, three-state visual model (completed/active/pending)

**Avoids:** Uniform border-radius, `transition: all`, weak hover states, color-mix circular reference.

**Research flag:** Skip `/gsd:research-phase`. ARCHITECTURE.md contains implementation-ready CSS specs with all values verified. The work is implementation, not research.

---

### Phase 3: Per-Page Layout Refonte

**Rationale:** With correct tokens and correct components, each page's layout becomes the specific scope. Pages are rebuilt to the FEATURES.md specs — which provide grid/flex layout, ASCII diagrams, breakpoints, and spacing rationale for each of the 6 page types.

**Delivers (one task per page type recommended):**
- Dashboard: 4-col KPI grid + conditional urgent banner + sessions vertical list + sticky 280px aside
- Wizard: centered 640px column, stepper above form card, sidebar suppressed or hidden
- Session detail: meta strip header + 2-col (main + 320px sticky sidebar)
- Operator console: full-width dense table, 14px throughout, live indicator pulse dots, monospace counts
- Vote/ballot: full-viewport centered card, voter-facing simplified layout, minimal chrome
- Results/PV: stacked sections with print-ready 880px max-width, `@media print` considerations

**Avoids:** Color-as-decoration on section headers, flat backgrounds (three-depth model enforced by Phase 1 tokens), monotone spacing (Phase 1 semantic aliases enforce hierarchy).

**Research flag:** Dashboard and Operator console warrant a brief pre-implementation review to validate grid proportions in context (especially the 1200px dashboard max-width vs the global 1440px). The other 4 pages follow patterns fully covered in research. Mark Dashboard and Operator for light validation rather than full `/gsd:research-phase`.

---

### Phase Ordering Rationale

- **Tokens before components** — components reference tokens; incorrect tokens make component specs wrong before implementation starts.
- **Components before pages** — pages composed of unrefreshed components will need rework when components change.
- **Dashboard last within the page phase, not first** — it is the most complex page (KPI grid + list + aside + conditional banner). Doing it last means the component library is fully refined and the risk of inconsistency is minimized.
- **Operator console is a special case** — its 14px/dense-table pattern differs meaningfully from every other page. It should be its own sub-task within Phase 3, not grouped with standard-layout pages.

### Research Flags Summary

| Phase | Research Needed | Reason |
|-------|----------------|--------|
| Phase 1: Token Foundation | None — skip | Full specs in PITFALLS.md and ARCHITECTURE.md |
| Phase 2: Components | None — skip | Exact CSS specs in ARCHITECTURE.md, all values verified from source |
| Phase 3: Wizard, Session, Vote, Results | None — skip | Well-specified in FEATURES.md with full breakpoint coverage |
| Phase 3: Dashboard | Light validation only | Most complex layout; confirm 1200px max-width feels right in context |
| Phase 3: Operator | Light validation only | Dense-table pattern differs from rest; confirm column widths in practice |

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Token system architecture | HIGH | Values verified directly from AG-VOTE design-system.css + Radix UI source + Tailwind v4 theme.css |
| Shadow scale | HIGH | Josh W. Comeau research + Atlassian elevation docs. Warm shadow color already correct in existing tokens. |
| Typography scale | HIGH | WCAG 2.1 + LearnUI.design guidelines + Linear/Jira/GitHub precedent for 14px on data apps |
| Component specs | HIGH (source values) / MEDIUM (synthesis) | shadcn/Sonner/Polaris values are exact. AG-VOTE mapping decisions are synthesis. |
| Page layouts | HIGH | Cross-verified against Stripe Dashboard, Linear, Notion. ASCII diagrams match research rationale. |
| Border-radius scale | MEDIUM | Vercel/Linear/Stripe analysis is visual rather than documented. The 6/8/12px hierarchy is consensus, not single authoritative source. |
| Dark mode strategy | HIGH | Atlassian + Radix verified. oklch lightness model for elevation is documented. |
| oklch browser support | HIGH | MDN + Evil Martians + caniuse. Chrome 119+, Firefox 128+, Safari 16.4+. Fallback pattern clear. |

**Overall confidence: HIGH**

### Gaps to Address

- **Dashboard max-width needs in-browser validation.** FEATURES.md specifies 1200px (not the global 1440px). This difference needs visual confirmation before committing. May need adjustment per page based on sidebar-collapsed vs sidebar-expanded state.

- **The 14px base body size decision requires explicit acknowledgment.** This is a significant perceptual shift from the current 16px. Document it in the phase plan so it is not mistaken for a regression bug after implementation.

- **Purple accent usage boundaries.** STACK.md resolves the blue/purple two-accent problem (purple only for voter persona contexts), but the boundary between "voter-specific" and "general UI" needs explicit documentation during Phase 3 when ballot and session-detail pages are built.

- **Print stylesheet scope for Results/PV.** FEATURES.md mentions print-ready layout at 880px max-width. Whether this requires a dedicated `@media print` stylesheet is not fully specified. Treat as a task-level decision during Phase 3 planning for that page.

---

## Sources

### Primary (HIGH confidence)
- AG-VOTE `public/assets/css/design-system.css` — ground truth for existing token values, verified by direct file read
- [Radix UI Colors source (GitHub)](https://github.com/radix-ui/colors/blob/main/src/light.ts) — 12-step scale hex values for blue, stone, green, amber, red
- [Tailwind CSS v4 theme.css (GitHub)](https://github.com/tailwindlabs/tailwindcss/blob/next/packages/tailwindcss/theme.css) — spacing, shadow, radius, and typography scale reference
- [shadcn/ui — new-york registry](https://ui.shadcn.com) — component CSS architecture, button/input/card anatomy
- [Atlassian Design System — Elevation](https://atlassian.design/foundations/elevation/) — surface layering model, shadow semantics
- [Atlassian Design System — Spacing](https://atlassian.design/foundations/spacing/) — container padding guidelines
- [OKLCH in CSS — Evil Martians](https://evilmartians.com/chronicles/oklch-in-css-why-quit-rgb-hsl) — color derivation formulas, browser support matrix
- [Designing Beautiful Shadows — Josh W. Comeau](https://www.joshwcomeau.com/css/designing-shadows/) — shadow formula, warm-toned shadow color matching
- [Radix UI Colors — Understanding the Scale](https://www.radix-ui.com/colors/docs/palette-composition/understanding-the-scale) — step-to-use-case semantic mapping

### Secondary (MEDIUM confidence)
- [Sonner CSS source (emilkowalski/sonner)](https://github.com/emilkowalski/sonner) — toast component exact CSS values
- [Shopify Polaris token library](https://polaris.shopify.com/tokens) — enterprise-grade component token reference
- [ishadeed.com — CSS Stepper](https://ishadeed.com) — stepper component implementation patterns
- [Linear UI Redesign article](https://linear.app/now/how-we-redesigned-the-linear-ui) — neutral canvas color approach, LCH color space rationale
- [Refactoring UI — Layout and Spacing](https://jacobshannon.com/blog/books/refactoring-ui/layout-and-spacing/) — whitespace philosophy, start-larger-than-comfortable rule
- [Font Size Guidelines — LearnUI.design](https://www.learnui.design/blog/mobile-desktop-website-font-size-guidelines.html) — 14px for data apps, 16px for prose
- [Why AI Websites Look the Same — AXE-WEB](https://axe-web.com/insights/ai-website-design-sameness/) — purple-blue gradient, uniform radius, Inter default patterns
- [Material Design 3 — Motion specs](https://m3.material.io/styles/motion/easing-and-duration/tokens-specs) — easing and duration scale
- [Vercel Geist Colors](https://vercel.com/geist/colors) — bg-100/bg-200 hierarchy, gray-alpha token pattern

---

*Research completed: 2026-03-19*
*Ready for roadmap: yes*
