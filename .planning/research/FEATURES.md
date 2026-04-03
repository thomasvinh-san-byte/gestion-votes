# Feature Research — Visual Identity Evolution

**Domain:** Modern SaaS/business app visual design patterns
**Researched:** 2026-04-03
**Milestone:** v10.0 Visual Identity Evolution
**Confidence:** HIGH (cross-verified across multiple products and official sources)

---

## Context

AG-VOTE already ships a complete design system: token hierarchy, 23 Web Components, 25 per-page CSS files,
dark/light mode, "officiel et confiance" identity (bleu/indigo, Bricolage Grotesque + Fraunces + JetBrains Mono).
This milestone evolves the *visual expression* of that system — not its architecture.

Research scope: what do top-tier SaaS products (Linear, Vercel, Stripe, Notion, Clerk) do in 2025-2026
that makes them feel premium, trustworthy, and calm? What patterns are table stakes, what differentiates,
and what should be avoided?

---

## Feature Landscape

### Table Stakes (Users Expect These)

Features users assume any "serious" business app has. Their absence makes the product feel unpolished.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Neutral-dominant color palette | Premium SaaS products since 2023 have moved away from heavy brand colors in the UI chrome; neutrals convey "tool", color conveys "moment" | LOW | AG-VOTE's indigo should appear sparingly — primary CTA, active nav state, focus ring. 90-95% of UI surface should be gray/neutral |
| Dark mode that looks intentional | Dark mode is now expected by default on professional tools; users notice when it is an afterthought | MEDIUM | Must use dark grays (#121212 to #1c1c1e range) not pure black; surface elevation via lighter grays, not shadows |
| Consistent border radius language | Every premier SaaS product picks a radius and holds it across all components; mixing radiuses reads as unfinished | LOW | Choose: either sharp (0-4px, Vercel/Linear style) or soft (8-12px, Notion/Clerk style). Cannot mix |
| Semantic focus rings | WCAG and user expectation: interactive elements must have visible, branded focus indicators | LOW | Use `outline: 2px solid var(--color-accent)` with `outline-offset: 2px`. Replaces generic browser default |
| Hover state on every clickable element | Users need immediate feedback that a surface is interactive | LOW | Subtle background shift (3-5% lightness change), not color changes. 150ms ease. |
| Tabular numbers for data | Any financial, vote-count, or percentage value displayed in a column misaligns visually without `font-variant-numeric: tabular-nums` | LOW | JetBrains Mono already in use — apply it to all numeric KPI values. |
| Skeleton loading states | Perceived performance: modern users expect content placeholders, not spinners, for page loads | MEDIUM | Replace spinner-based loading on dashboard KPIs and session lists with skeleton shimmer |
| Single-level shadow vocabulary | Products with 2-3 shadow depths feel designed; products with 7+ shadow variants feel inconsistent | LOW | Establish: `shadow-sm` (card border), `shadow-md` (dropdown), `shadow-lg` (modal). Zero other variants. |

### Differentiators (Competitive Advantage)

Features that elevate AG-VOTE above generic admin panels and reinforce "officiel et confiance."

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Warm-neutral gray base (not cool-gray) | Linear's 2024-2025 shift: moved from cool blue-gray to warmer gray "that still feels crisp, but less saturated." Warm grays feel more human and trustworthy for civic contexts | MEDIUM | Requires re-tuning the gray ramp in CSS tokens. HSL: shift gray hues from 220-230 (cold blue) toward 200-210 (warmer but still professional). Not warm enough to read "beige". |
| Ambient gradient accents on hero surfaces | The 2025-2026 gradient trend is "smoky, ambient, layered — used as lighting not decoration." Linear uses them on marketing, Vercel uses subtle radial gradients on empty hero cards | MEDIUM | Apply only to: login page orb (already present), empty state hero areas, operator console header bar. Never on functional UI chrome. |
| Progressive disclosure that removes noise | Linear's core principle: "navigation should recede once users reach their destination." Sidebar dims in use, advanced actions hide behind contextual menus | MEDIUM | Requires per-page audit. Operator console is the key candidate: hide secondary controls until vote is open. |
| Density modes (compact vs. comfortable) | Lists (sessions, members, votes) should support a compact mode. Linear, Notion, and GitHub all offer density toggles | HIGH | Stored in localStorage or user profile. Affects padding on `ag-table` and list rows. This is a differentiator because few civic apps offer it. |
| Celebration micro-animations on completion | "Delight is no longer a consumer-app luxury" (SaaS UI 2026 trends). A subtle checkmark animation when a vote closes, a confetti burst on PV generation — these are memorable | MEDIUM | Only on terminal success states. Never on destructive or warning actions. Use `prefers-reduced-motion` guard. |
| Structural subtlety: "felt not seen" borders | Linear's 2025 refresh philosophy: structure should be "felt not seen" — borders at 8% white opacity in dark mode, not visible hairlines | LOW | Change border token from solid color to `rgba` or `oklch` with alpha. `--color-border: oklch(50% 0 0 / 0.12)` pattern. |
| Attention hierarchy (dim inactive surfaces) | Elements that are contextually inactive should visually "recede". Tab headers dim when not selected. Sidebar recedes to near-invisible when user is deep in a flow. | MEDIUM | Implement using `--color-text-muted` and `--color-surface-subtle` already in token system. Requires per-component audit. |

### Anti-Features (Commonly Requested, Often Problematic)

Features that seem visually appealing but undermine the "officiel et confiance" identity or create regressions.

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Full glassmorphism on data cards | Looks trendy in design mockups | Destroys text contrast on dynamic backgrounds; inaccessible; reads as "consumer app" not "official tool"; nearly impossible to do well at WCAG AA | Use a single frosted-glass effect on the login page orb only — the one place AG-VOTE already uses it intentionally |
| Rainbow color-coding for status badges | Seems informative; color = meaning | Color alone is not accessible (WCAG 1.4.1); too many hues creates visual noise; forces users to learn a color vocabulary | Use: one semantic color per status (success=green, warning=amber, error=red, info=blue, neutral=gray) with icon + label always present |
| Heavy drop shadows on cards | Creates "material" depth, feels tangible | Shadows feel outdated in flat/calm design era; heavy shadows also read as "unpolished" on dark mode | Use border + subtle background elevation for card distinction. Save `box-shadow` for modals and dropdowns only. |
| Bold color fills on nav sidebar | Differentiates active states | A bright-color sidebar rail draws too much attention to navigation chrome; the product content should be the focal point | Active nav item: left border in accent color + slightly lighter background. No filled colored background. |
| Decorative illustration on every empty state | Looks friendly and designed | Adds visual weight; slows perceived performance; can conflict with the official/trust identity | Use a simple icon (from existing icon set) + one-line description + single action CTA. Reserve illustration for onboarding only. |
| Persistent toast/banner notifications | Keeps users informed | Too many persistent notifications create visual noise and "alert fatigue"; users begin ignoring them | Use ephemeral toasts (3-4 second auto-dismiss) for non-critical feedback. Reserve persistent banners for genuinely blocking states (SSE disconnect, session expiry). |
| Animation on every state transition | Feels polished and responsive | Overanimation increases perceived latency; users in production workflows find it distracting; fails `prefers-reduced-motion` | Use 150ms for hover/active feedback, 250ms for panel reveals, 0ms for data updates. Never animate row insertions in live vote view. |
| Custom decorative typeface for body text | Brand differentiation | Bricolage Grotesque is already distinctive as a display font; using it for body text reduces readability at small sizes and data-dense contexts | Bricolage Grotesque for headings and page titles only. Consider Inter or system-ui for body text if readability becomes a pain point in user testing. |

---

## Feature Dependencies

```
Warm-neutral gray ramp (token update)
    └──enables──> Structural-subtlety borders (alpha-based borders look right on warm neutrals)
    └──enables──> Dark mode tuning (warm dark grays need corresponding token values)

Attention hierarchy (dim inactive surfaces)
    └──requires──> Per-component audit of text/surface token usage
    └──requires──> Consistent use of semantic tokens (zero hardcoded hex — already enforced)

Skeleton loading states
    └──requires──> Per-page loading state audit (identify which pages use spinner vs. empty div)
    └──enhances──> Perceived performance without backend changes

Celebration micro-animations
    └──requires──> prefers-reduced-motion guards (already have animation timing contracts from v9.0)
    └──conflicts with──> Persistent toast/banner overuse (one feedback channel at a time)

Progressive disclosure (noise removal)
    └──requires──> Per-page content hierarchy audit
    └──enhances──> Attention hierarchy (dimming inactive surfaces)
```

### Dependency Notes

- **Warm-neutral gray ramp requires token update first:** All downstream visual decisions (border alpha, dark mode depths, elevation overlays) depend on the base gray hue. This is the foundational change that makes everything else feel coherent.
- **Skeleton loading requires page-by-page audit:** Cannot be done globally — must identify per-page which data loads asynchronously.
- **Celebration animations conflict with overuse:** If AG-VOTE already has too many toasts/banners (a known issue from v9.0), adding celebration animations on top creates noise competition. Fix the noise first.

---

## MVP Definition

### Launch With (v10.0 Phase 1 — Foundation)

Minimum changes to make the entire product feel "2025-grade" without risky per-page rewrites.

- [ ] **Warm-neutral gray ramp update** — Re-tune the gray token ramp from cool-blue-gray toward warmer gray. Affects every surface. Highest ROI single change.
- [ ] **Accent sparsity audit** — Audit all 25 CSS files for overuse of `--color-accent`/indigo. Replace with neutral gray in UI chrome, preserve accent only for: primary CTAs, active nav, focus rings, status success.
- [ ] **Border alpha treatment** — Update `--color-border` and `--color-border-subtle` to use alpha-based values (`oklch` with `/0.12` alpha) instead of solid gray values. Produces the "structural subtlety" effect.
- [ ] **Consistent border radius decision** — Pick one language (recommendation: 6-8px medium-rounded, matching Notion/Clerk quality tier) and enforce across all components via a single `--radius-base` token. Eliminate radius inconsistencies.
- [ ] **Shadow vocabulary reduction** — Enforce max 3 shadow levels across all CSS files. Remove decorative shadows; keep only structural ones (dropdown, modal, popover).
- [ ] **Focus ring standardization** — Ensure every interactive element has `outline: 2px solid var(--color-accent-primary); outline-offset: 2px`. Remove custom focus overrides that deviate from this.

### Add After Validation (v10.0 Phase 2 — Elevation)

Once foundation is solid, add targeted high-ROI visual upgrades.

- [ ] **Skeleton loading on dashboard + session list** — Replace load spinners with shimmer skeletons on the two most-visited pages.
- [ ] **Sidebar attention hierarchy** — Dim the sidebar chrome when user is deep in a workflow (operator console, vote page, hub). Active nav dims to `--color-text-muted`.
- [ ] **Celebration micro-animation on vote close and PV generation** — Single-use terminal success animations. 400ms max duration. Reduced-motion guard.
- [ ] **Tabular numbers enforcement** — Audit all KPI values, vote counts, percentages, and apply `font-variant-numeric: tabular-nums` + JetBrains Mono where missing.
- [ ] **Progressive disclosure audit on operator console** — Identify secondary controls that are always visible but only needed during active vote. Move to contextual reveal.

### Future Consideration (v11+)

Defer until v10.0 is validated and design direction confirmed.

- [ ] **Density mode toggle** — Low/medium/high density for tables and lists. Requires user profile storage and significant CSS work. High value for power users but not needed for launch.
- [ ] **Custom illustration system** — Only if onboarding or empty state design direction validates the need. Risk of conflicting with "officiel" identity.
- [ ] **Variable font exploration** — Bricolage Grotesque is available as a variable font; weight-based responsiveness at headings could be visually interesting. Deferred: high complexity, uncertain ROI.
- [ ] **Per-page ambient gradient tuning** — Expand the login-page orb gradient treatment to 2-3 other hero surfaces (operator console header, hub hero card). Requires visual QA across dark/light modes.

---

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Warm-neutral gray ramp | HIGH — affects every surface | LOW — token-only change | P1 |
| Accent sparsity audit | HIGH — reduces visual noise immediately | LOW — CSS audit + find/replace | P1 |
| Border alpha treatment | HIGH — "structurally subtle" feel | LOW — token update + test | P1 |
| Consistent border radius | HIGH — cohesion | LOW — token + per-component pass | P1 |
| Shadow vocabulary reduction | MEDIUM — removes clutter | LOW | P1 |
| Focus ring standardization | HIGH — accessibility + brand | LOW | P1 |
| Skeleton loading | HIGH — perceived performance | MEDIUM — per-page work | P2 |
| Sidebar attention hierarchy | MEDIUM — polish | MEDIUM — behavioral JS + CSS | P2 |
| Celebration micro-animations | MEDIUM — delight | MEDIUM — animation + guards | P2 |
| Tabular numbers enforcement | MEDIUM — data precision | LOW — CSS audit | P2 |
| Progressive disclosure (operator) | HIGH — operator UX | HIGH — JS + layout changes | P2 |
| Density mode toggle | HIGH — power user value | HIGH — architecture + storage | P3 |
| Per-page ambient gradients | LOW — visual interest only | MEDIUM | P3 |
| Variable font exploration | LOW | HIGH | P3 |

**Priority key:**
- P1: Foundation — must ship in Phase 1 of v10.0
- P2: Elevation — ship in Phase 2 once foundation is stable
- P3: Future — defer to v11+

---

## Competitor Pattern Analysis

Concrete observations from Linear, Vercel, Stripe, Notion, Clerk in 2025-2026.

| Pattern | Linear | Vercel | Stripe | Notion/Clerk | AG-VOTE Direction |
|---------|--------|--------|--------|--------------|-------------------|
| Gray base hue | Warm gray (2025 refresh: shifted from cool blue-gray) | Pure neutral gray, no temperature | Neutral-cool | Neutral-warm (Notion), clean neutral (Clerk) | Shift toward warm neutral to match Linear/Notion quality tier |
| Accent color ratio | ~5% of UI surface — accent only on active states, icons | ~3% — blue only on links/CTAs | ~8% — purple/indigo on CTAs and key moments | ~5% Notion orange / Clerk purple | Reduce indigo to 5-8% of surfaces; neutralize chrome |
| Border treatment | Barely-visible dividers, soft contrast | 8% opacity white in dark mode; gray-200 in light | Subtle borders, never heavy | Hairline borders, low opacity | Move to alpha-based `oklch` borders |
| Border radius | 6-8px on cards, 4px on inputs | 4-6px — sharp-to-medium | 6-8px on cards | 8-12px (Notion soft, Clerk rounded) | 6-8px medium — trustworthy without cold sharpness |
| Shadow philosophy | Minimal — elevation via color not shadow | Zero shadows on marketing, minimal in dashboard | Functional shadows only (modals, dropdowns) | Soft shadows on floating elements | Max 3 levels: border-only card / dropdown shadow / modal shadow |
| Typography sizing | Dense but legible: 13-14px body in app | 16px body, -0.04em display tracking | 15px body in dashboard | 14-15px in Notion app | 14px app body, 15-16px for comfortable reading passages |
| Dark mode background | Dark gray (~#1a1a24) not pure black | Pure black (#000) — developer brand | Dark gray surface | Near-black gray | Use #1a1b26 (indigo-tinted dark) to maintain "officiel" feel |
| Navigation dimming | Sidebar dims when user is in content | Minimal persistent nav | Left nav stays visible but subtle | Sidebar present but not dominant | Dim sidebar text to `--color-text-muted` on operator/vote pages |
| Loading patterns | Skeleton shimmer | Skeleton shimmer | Skeleton shimmer | Skeleton (Notion) | Replace spinners with skeletons on dashboard + session list |
| Empty states | Icon + 1-2 lines + single CTA | Minimal icon + headline | Minimal illustration + action | Illustrated (Notion) / minimal icon (Clerk) | Keep current icon-based empty states; resist adding illustration |

---

## Dependencies on Existing AG-VOTE Design System

These features build directly on v9.0's shipped infrastructure:

| Existing Infrastructure | How v10.0 Extends It |
|------------------------|----------------------|
| CSS token hierarchy (primitive→semantic→component) | Re-tune primitive gray ramp tokens; semantic tokens pick up changes automatically |
| Three-depth background model (bg/surface/raised) | Add fourth level: `--color-bg-elevated` for ambient gradient zones (login, hero) |
| Animation timing contracts (`--duration-*` tokens, `prefers-reduced-motion`) | Use for celebration animations + hover state durations |
| Zero hardcoded hex (enforced since v4.4) | Border alpha migration is clean — no scattered color values to hunt down |
| Dark/light mode token parity | Gray ramp re-tune must maintain both modes; test both after each token change |
| 23 Web Components | Component-level border radius and shadow cleanup can be done in component CSS, not per-page |
| Per-page CSS files (25 files) | Accent sparsity audit is a per-file pass; no architecture change needed |

---

## Sources

- [Linear Design — The SaaS design trend that's boring and bettering UI (LogRocket)](https://blog.logrocket.com/ux-design/linear-design/)
- [Linear — A calmer interface for a product in motion (official)](https://linear.app/now/behind-the-latest-design-refresh)
- [Linear — How we redesigned the Linear UI Part II](https://linear.app/now/how-we-redesigned-the-linear-ui)
- [Vercel Design System Breakdown: Colors, Typography, Tokens (Seedflip)](https://seedflip.co/blog/vercel-design-system)
- [Vercel Web Interface Guidelines (official)](https://vercel.com/design/guidelines)
- [Stripe — Designing Accessible Color Systems](https://stripe.com/blog/accessible-color-systems)
- [7 SaaS UI Design Trends in 2026 (SaaSUI Blog)](https://www.saasui.design/blog/7-saas-ui-design-trends-2026)
- [7 Emerging Web Design Trends for SaaS in 2026 (Envizn Labs)](https://enviznlabs.com/blogs/7-emerging-web-design-trends-for-saas-in-2026-ai-layouts-glow-effects-and-beyond)
- [Dark Mode Color Palettes: Complete Guide 2025 (MyPaletteTool)](https://mypalettetool.com/blog/dark-mode-color-palettes)
- [Complete Dark Mode Design Guide 2025 (UI Deploy)](https://ui-deploy.com/blog/complete-dark-mode-design-guide-ui-patterns-and-implementation-best-practices-2025)
- [Top Dashboard Design Trends for SaaS Products in 2025 (UITop)](https://uitop.design/blog/design/top-dashboard-design-trends/)
- [Micro-interactions in web design 2025 (Stan Vision)](https://www.stan.vision/journal/micro-interactions-2025-in-web-design)
- [Clerk Mosaic Design System (official)](https://clerk.com/blog/introducing-mosaic-bring-your-brand-to-every-authentication-flow)
- [SaaS Typography Playbook (FullStop Insights)](https://fullstop360.com/blog/insights/branding/saas-typography-playbook-what-leading-companies-use)

---

*Feature research for: Visual identity patterns for modern SaaS/business apps*
*Researched: 2026-04-03*
*Used by: v10.0 Visual Identity Evolution milestone roadmap*
