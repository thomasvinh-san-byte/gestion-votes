# Architecture Research — Visual Identity Evolution Integration

**Domain:** CSS design system evolution for existing production web app
**Researched:** 2026-04-03
**Confidence:** HIGH
**Scope:** v10.0 — how visual identity changes integrate with the existing AG-VOTE CSS architecture

---

## Standard Architecture

### System Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│  HTML PAGES (22 .htmx.html + login.html + index.html)               │
│  Each loads: app.css + optional {page}.css + Google Fonts            │
├─────────────────────────────────────────────────────────────────────┤
│  app.css (single entrypoint)                                         │
│  ├── @import design-system.css  (foundation, ~5258 lines)           │
│  └── @import pages.css          (shared cross-page classes)          │
├─────────────────────────────────────────────────────────────────────┤
│  design-system.css  (@layer base, components, v4)                   │
│                                                                      │
│  @layer base                                                         │
│  ├── Reset, box-sizing                                               │
│  ├── :root  { PRIMITIVES → SEMANTIC → COMPONENT ALIASES }           │
│  ├── [data-theme="dark"] { overrides for all three layers }         │
│  ├── Typography global rules (h1–h4, utility classes)               │
│  └── Layout shell (app-shell, app-header, app-sidebar, nav-item…)   │
│                                                                      │
│  @layer components                                                   │
│  └── Shared component CSS (.btn, .card, .badge, .form-*, .table…)   │
│                                                                      │
│  @layer v4                                                           │
│  └── Progressive enhancement (CSS @starting-style, view-transition) │
├─────────────────────────────────────────────────────────────────────┤
│  Per-page CSS files (25 files, loaded individually per page)        │
│  hub.css, wizard.css, operator.css, vote.css, …                     │
│  All consume tokens from design-system.css via var(--…)             │
├─────────────────────────────────────────────────────────────────────┤
│  Web Components (23 components, all with Shadow DOM)                │
│  ag-toast, ag-modal, ag-confirm, ag-kpi, ag-stepper, …             │
│  Each embeds its own <style> that reads host document CSS vars       │
│  Shadow DOM inherits custom properties from document :root          │
└─────────────────────────────────────────────────────────────────────┘
```

### Token Hierarchy (Three Layers in :root)

```
PRIMITIVES (raw values, never used in components directly)
  --stone-50 … --stone-900    Parchment/warm gray palette
  --blue-50  … --blue-800     Primary blue/indigo palette
  --green-500, --amber-400, --red-500, --purple-500…
  --font-sans, --font-display, --font-mono
  --text-2xs … --text-5xl
  --space-0 … --space-24

SEMANTIC (context-aware, theme-switchable, used in components)
  --color-bg, --color-surface, --color-surface-raised
  --color-text, --color-text-muted, --color-text-dark
  --color-primary, --color-primary-hover, --color-primary-subtle
  --color-success/warning/danger/info (+ -hover, -subtle, -border, -text)
  --shadow-2xs … --shadow-2xl  (via --shadow-color: 21 21 16 rgb channels)
  --space-section, --space-card, --space-field

COMPONENT ALIASES (scoped to one component, composed from semantic)
  --radius-btn, --radius-card, --radius-modal, --radius-badge…
  --type-page-title-size, --type-body-size, --type-label-weight…
  --btn-height, --input-height, --toast-width…
```

### Dark Mode Architecture

```
:root           → light theme (default)
[data-theme="dark"]  → full token override set for every SEMANTIC token

theme-init.js   → runs synchronously in <head>, reads localStorage,
                   sets data-theme="dark" on <html> before first paint

critical-tokens <style> in each page <head>
                → embeds --color-bg, --color-surface, --color-text
                   as inline style to prevent FOUC on initial load
```

### Shadow DOM Token Inheritance

```
document :root
  └── defines --color-primary, --color-surface, etc.

  Shadow root (inside ag-toast, ag-modal, etc.)
    └── custom properties INHERIT through shadow boundary
    └── each component's embedded <style> uses:
          var(--color-surface-raised, #fallback)
          var(--color-primary, #fallback)
        The fallback value is the light-mode hex literal.
        This means dark mode tokens propagate automatically —
        no special Shadow DOM theming needed.
```

---

## Component Responsibilities

| Layer | Owner | Responsibility |
|-------|-------|----------------|
| `design-system.css @layer base` | All pages | Token definitions, resets, typography globals, shell layout |
| `design-system.css @layer components` | All pages | Shared component classes: btn, card, badge, table, form |
| `design-system.css @layer v4` | Progressive enhancement | @starting-style, view-transition-name |
| `pages.css` | Shared cross-page | Components that appear on many pages (session-card, stat-card, etc.) |
| `{page}.css` | One page only | Page-specific layout and component variants not reused elsewhere |
| Web Components (`ag-*.js`) | Shadow DOM | Self-contained UI with embedded styles reading host-document tokens |
| `app.css` | Entry point | @import orchestration only — no styles of its own beyond minor brand overrides |

---

## Recommended Structure for v10 Visual Identity

### What Changes vs. What Stays

```
CHANGE (token values only — in design-system.css :root and [data-theme="dark"])
  Primitive palette         → new hue/saturation/lightness values
  Semantic color aliases    → may remap to different primitives
  Typography primitives     → font families (Google Fonts URL in 22 HTML files)
  Shadow scale              → --shadow-color if warm tone changes
  color-mix() derived tokens → auto-update when source tokens change

PROPAGATES AUTOMATICALLY (zero additional work)
  All 25 per-page CSS files  → consume via var(--), update for free
  All 23 Web Components      → inherit through Shadow DOM boundary
  Dark mode                  → [data-theme="dark"] block is the single override
  critical-tokens inline styles → must be manually updated (22 pages × 3 tokens each)

CHANGE (if fonts change — 22 HTML files)
  Google Fonts <link> URL   → one URL per page (identical string, 22 occurrences)
  --font-sans, --font-display, --font-mono in :root
```

### Migration Strategy: Token-First

**Rule:** never touch a page CSS file or Web Component to change visual identity. Change the token source, let consumers inherit.

The three layers of change are strictly ordered:

```
Step 1: Primitives (no visible effect yet — just renaming raw values)
  → Update --stone-* / --blue-* / --green-* etc. in :root

Step 2: Semantic remapping (visible everywhere at once)
  → Remap --color-primary, --color-bg, --color-surface, etc.
    to point at new primitive values
  → Update [data-theme="dark"] semantic overrides in parallel

Step 3: Component alias review (spot fixes only)
  → Check --radius-btn, --btn-height, --type-* roles
  → Most require no change if semantic layer is correct
```

This order ensures that at no point is the system in a mixed state where some pages have new colors and others have old ones.

---

## Architectural Patterns

### Pattern 1: Token Isolation via Fallback Values

**What:** Every `var()` call in Web Components includes a hardcoded fallback.
**When to use:** Always in Shadow DOM — avoids blank renders when tokens are slow to apply.
**Trade-offs:** Fallback values must be kept in sync when primitives change. Fallbacks are light-mode hex values; they do not adapt to dark mode on their own.

**Example (current pattern in all 23 components):**
```css
/* Inside ag-kpi shadow root */
background: var(--color-surface, #ffffff);
border: 1px solid var(--color-border, #d5dbd2);
color: var(--color-text-dark, #1a1a1a);
```

**For v10:** When primitive hex values change, update both the `:root` semantic token AND the fallback literal in the component. Fallbacks are secondary; the token value drives the visual. Audit with: `grep -rn "var(--color-" public/assets/js/components/`.

### Pattern 2: Dark Theme as Full Override Block

**What:** `[data-theme="dark"]` in `@layer base` redefines all semantic and shadow tokens.
**When to use:** All theme-switchable values go here, no `prefers-color-scheme` in component CSS.
**Trade-offs:** Single selector to maintain, but it requires matching every :root token that changes between themes.

**For v10:** After updating `:root` primitives, the `[data-theme="dark"]` block must be updated in parallel. The shadow scale already uses `--shadow-color` (light: `21 21 16`, dark: `0 0 0`) so all shadow levels adapt automatically when only `--shadow-color` is changed.

### Pattern 3: @layer Cascade Order

**What:** `@layer base, components, v4` declared at top of design-system.css.
**When to use:** Page CSS files loaded after app.css sit outside any named layer, which gives them highest specificity regardless of source order.
**Trade-offs:** Page CSS will override @layer rules even with equal specificity. This is intentional and correct — page-specific overrides should win.

**For v10 identity work:** Token changes belong in `@layer base` (the `:root` block). Component changes (border-radius values, shadow levels, button height) belong in `@layer components`. The `@layer v4` block is reserved for progressive enhancement animations only — do not add identity tokens there.

### Pattern 4: color-mix() Derived Tokens

**What:** Several tokens are computed from base tokens at declaration time.
**When to use:** Tinted backgrounds, hover states that are a percentage mix of base color.
**Trade-offs:** If the source token is a new hue, derived values update automatically. But circular references (mixing a token with itself) produce `transparent`.

**Current examples:**
```css
/* Light mode */
--color-primary-tint-10: color-mix(in srgb, var(--color-primary) 10%, white);
--color-surface-elevated: color-mix(in srgb, var(--color-surface) 97%, var(--color-primary));

/* Dark mode override */
--color-primary-tint-10: color-mix(in srgb, var(--color-primary) 10%, var(--color-surface));
```

**For v10:** When changing `--color-primary`, these derived tokens update for free. Add new derived tokens using the same pattern. Never redefine the source token using itself inside color-mix.

### Pattern 5: Font Loading via Google Fonts

**What:** Fonts loaded as `<link rel="stylesheet" href="https://fonts.googleapis.com/css2?...">` in every page's `<head>`. Font tokens (`--font-sans`, `--font-display`, `--font-mono`) reference the loaded families.
**When to use:** Current approach — 22 HTML files each carry the same font URL string.
**Trade-offs:** If font families change, 22 files need the same URL update. The token names stay stable; only the URL and the family name in the `@font-face`/URL change.

**For v10:** Changing the font means: (1) update the Google Fonts URL in 22 `.htmx.html` files + `login.html` + `index.html`, (2) update `--font-sans`, `--font-display`, `--font-mono` values in `:root`. Pages and components consume via token — no other changes needed.

---

## Data Flow

### Token Update Flow

```
1. Update primitive(s) in :root
        ↓
2. Semantic tokens that reference those primitives
   update automatically (CSS var resolution is live)
        ↓
3. Component aliases update automatically
        ↓
4. All 25 page CSS files see new values (no file changes needed)
        ↓
5. All 23 Web Components inherit through shadow boundary
   → their var(--color-primary, fallback) now resolves to new value
        ↓
6. [data-theme="dark"] must be updated separately
   (dark overrides are explicit, not derived from light primitives)
```

### Theme Switch Flow

```
User clicks theme toggle
        ↓
JS sets document.documentElement.setAttribute('data-theme', 'dark')
        ↓
CSS selector [data-theme="dark"] activates
        ↓
All semantic tokens in that block override their :root values
        ↓
Shadow DOM components inherit new values automatically
        ↓
critical-tokens inline style in <head> is light-mode only
   → acceptable: it only affects the very first paint before JS loads
```

### Font Change Flow

```
Update Google Fonts URL in all 22 .htmx.html files (+ login.html, index.html)
        ↓
Update --font-sans / --font-display / --font-mono values in :root
        ↓
All typography rules referencing var(--font-sans) update automatically
        ↓
Web Components using var(--font-mono, 'JetBrains Mono', monospace)
need fallback string updated if family name changes
```

---

## Build Order to Avoid Regressions

### Phase A: Token Foundation (design-system.css only)

This is the highest-leverage and safest work — all changes are isolated to one file.

```
A1. Update color primitives (--stone-*, --blue-*, --green-*, etc.)
    → No visual effect yet — primitives are not directly used in components
    → Verification: no visible change

A2. Remap semantic color tokens to new primitives
    → First visible effect — all pages update simultaneously
    → Verify: light mode on dashboard, login, operator, vote pages
    → Verify: dark mode toggle on same pages
    → Verify: status colors (success/warning/danger) on members or audit page

A3. Update [data-theme="dark"] semantic override block
    → Must be done in same commit as A2 — never leave dark mode broken
    → color-mix() dark overrides reference var(--color-surface) not white — check these

A4. Update shadow-color if warm tone of new palette changes
    → --shadow-color: R G B  (warm near-black rgb channels)
    → All shadow levels adapt automatically

A5. Update component aliases (--radius-*, --type-*, --btn-height, etc.)
    → Only if identity change requires different component geometry
    → Verify: buttons, cards, modals across all pages
```

### Phase B: Font Change (22 HTML files + :root)

Font change is mechanical but touches many files.

```
B1. Update Google Fonts URL string
    → Run: grep -rn "fonts.googleapis.com" public/*.html public/*.htmx.html
    → Edit: 22 .htmx.html files + login.html + index.html (22 total)
    → Use sed or per-file edit — they all carry the same URL

B2. Update --font-sans / --font-display / --font-mono in :root
    → Fallback stack in token value should match new family name

B3. Update fallback strings in Web Components
    → grep -rn "font-mono, '\|font-display, '\|font-sans, '" public/assets/js/components/
    → Update the literal family names in fallback position

B4. Update critical-tokens inline styles in HTML files
    → These embed --color-bg, --color-surface, --color-text only
    → Not affected by font change — skip
```

### Phase C: Per-Page CSS (only if needed)

After token changes, page CSS files should require no edits IF they consume tokens properly. The cases that require page-level edits are:

```
Case 1: Page CSS contains hardcoded hex or rgba instead of var(--*)
  → These are the ~10 known occurrences (hub.css fallbacks, analytics.css, etc.)
  → They will NOT update with token changes — must be manually corrected

Case 2: A page uses a component alias that was deprecated or renamed
  → Unlikely in v10 unless radius/dimension tokens are restructured

Case 3: New component variants or layout patterns introduced in v10
  → New work, not migration — add to page CSS or design-system.css components layer
```

Known hardcoded values to audit (from grep at time of research):
- `hub.css` — 3 rgba fallbacks using `rgba(22, 80, 224, …)` in fallback position (acceptable, non-blocking)
- `analytics.css` — `#1650E0` as fallback in border-top-color
- `meetings.css` — `#1650E0` in color: fallback position
- `users.css`, `public.css`, `vote.css` — `rgba(22,80,224,…)` in fallback position
- `design-system.css` — ~8 hardcoded `rgba(22, 80, 224, …)` in shadow box-shadow values (not in tokens)

### Phase D: Web Component Fallback Audit

After token values are finalized:

```
D1. grep -rn "var(--color-" public/assets/js/components/
    → Collect all fallback hex literals
    → Compare against new semantic token values
    → Update only where the visual gap between fallback and token would cause
      flash-of-wrong-color on component mount

D2. ag-vote-button.js — has rgba hardcoded for hover backgrounds
    → rgba(11,122,64,.12), rgba(196,40,40,.12) for for/against states
    → Update if success/danger hues change significantly

D3. ag-stepper.js, ag-toast.js, ag-modal.js — all use semantic tokens correctly
    → Only fallback literals need updating if hex values change
```

### Phase E: critical-tokens Inline Styles (22 HTML files)

```
Each .htmx.html contains:
  <style id="critical-tokens">
    :root { --color-bg: #EDECE6; --color-surface: #FAFAF7; --color-text: #151510; }
    [data-theme="dark"] { --color-bg: #0B0D10; --color-surface: #141820; --color-text: #ECF0FA; }
    html, body { background: var(--color-bg); }
  </style>

Update these 6 hex values if --color-bg or --color-surface changes.
Grep: grep -rn "critical-tokens" public/
These 3 tokens × 2 themes = 6 values across 22 files.
Update with sed or bulk editor — all identical.
```

---

## Integration Points by Layer

### Layer 1: Primitives (design-system.css :root)

| Token group | Files to change | Propagates to |
|-------------|----------------|---------------|
| `--stone-*`, `--blue-*`, `--green-*` | design-system.css | Nowhere directly — primitives are only referenced by semantic layer |
| `--font-sans`, `--font-display`, `--font-mono` | design-system.css + 22 HTML files | All CSS rules using var(--font-*), Web Component fallback strings |
| `--text-*` scale | design-system.css only | All typography rules — no page CSS changes needed |
| `--shadow-color` | design-system.css :root + [data-theme="dark"] | All 7 shadow levels auto-update |

### Layer 2: Semantic Colors (design-system.css)

| Token group | Required co-change | Risk |
|-------------|-------------------|------|
| `--color-bg`, `--color-surface`, `--color-surface-raised` | critical-tokens inline styles in 22 HTML files | Flash-of-wrong-color if not updated together |
| `--color-primary`, `--color-primary-hover`, `--color-primary-*` | `--ring-color`, `--color-border-focus`, `--sidebar-active`, hardcoded rgba in 8 places | Medium — some values are still hardcoded as rgba(22,80,224) |
| `--color-success/warning/danger/info` | None — all consumed via var() correctly | Low |
| `[data-theme="dark"]` overrides | Must be updated in same commit as :root | High — leaving dark mode broken causes immediate visible regression |

### Layer 3: Component Aliases (design-system.css)

| Token group | Impact | Risk |
|-------------|--------|------|
| `--radius-btn/card/modal/badge/…` | All components using that alias | Low — global update, no per-page work |
| `--btn-height`, `--input-height` | Buttons and inputs across all pages | Medium — may break grid alignment in operator.css, wizard.css |
| `--type-page-title-*` | h1 rendering on all pages | Low |

### Layer 4: Per-Page CSS (25 files)

| File | Token compliance | Action needed |
|------|-----------------|---------------|
| hub.css | HIGH — 334+ token usages, 3 rgba fallbacks | Fallback-only update if primary hue changes |
| operator.css | HIGH | Likely no changes needed |
| vote.css | HIGH — 1 rgba fallback | Fallback-only update |
| wizard.css | HIGH | Likely no changes needed |
| analytics.css | MEDIUM — 1 hardcoded hex | Fix hardcoded #1650E0 |
| meetings.css | MEDIUM — 2 hardcoded hex | Fix hardcoded #1650E0 |
| public.css | HIGH — 1 rgba fallback | Fallback-only update |
| users.css | HIGH — 1 rgba fallback | Fallback-only update |
| pages.css | MEDIUM — session-card has some hardcoded values | Audit needed |
| All others | HIGH | Likely no changes needed |

### Layer 5: Web Components (23 files)

| Component | Shadow DOM approach | Token usage | Action needed |
|-----------|-------------------|-------------|---------------|
| ag-toast | Embedded `<style>` with var() + fallback | Semantic tokens correctly | Update fallback hex if values change |
| ag-modal | Embedded `<style>` with var() + fallback | Semantic tokens correctly | Update fallback hex |
| ag-confirm | Embedded `<style>` with var() + fallback | Semantic tokens correctly | Update fallback hex |
| ag-kpi | Embedded `<style>` with var() + fallback | Semantic tokens + color-mix | Update fallback hex |
| ag-vote-button | Embedded `<style>` with var() + fallback | 4 hardcoded rgba for states | Update rgba if success/danger hues change |
| ag-stepper | Embedded `<style>` with var() + fallback | Semantic tokens correctly | Update fallback hex |
| ag-tooltip | Embedded `<style>` | Semantic tokens | Update fallback hex |
| ag-badge, ag-breadcrumb, ag-donut, ag-mini-bar, ag-page-header, ag-pagination, ag-pdf-viewer, ag-popover, ag-quorum-bar, ag-scroll-top, ag-searchable-select, ag-spinner, ag-time-input, ag-tz-picker | Embedded `<style>` | Semantic tokens | Update fallback hex |

---

## Anti-Patterns

### Anti-Pattern 1: Changing Page CSS Before Tokens

**What people do:** Edit `hub.css` colors directly to test the new palette.
**Why it's wrong:** Creates drift between page CSS and token system. When tokens are later updated, page CSS overrides persist and cannot be distinguished from intentional page-specific overrides.
**Do this instead:** Change the token in design-system.css, observe the automatic propagation. Only edit page CSS if the token change produces an unintended result specific to that page.

### Anti-Pattern 2: Adding Raw Values to @layer v4

**What people do:** Add `color: oklch(0.6 0.15 265)` directly in the v4 layer block.
**Why it's wrong:** Raw color values bypass the theming system. The v4 layer is for progressive enhancement (animations, view transitions) only. Token values in v4 would not respond to `[data-theme="dark"]`.
**Do this instead:** Add new tokens to `:root` in `@layer base`, reference them from `@layer components` or page CSS.

### Anti-Pattern 3: Separate Dark Mode Fallbacks in Web Components

**What people do:** Add `@media (prefers-color-scheme: dark) { … }` inside Shadow DOM embedded styles.
**Why it's wrong:** AG-VOTE uses `[data-theme="dark"]` as its theme switch, not `prefers-color-scheme`. The `[data-theme]` selector works across the shadow boundary via CSS custom property inheritance. A media query inside Shadow DOM fires for OS preference regardless of user's in-app choice.
**Do this instead:** Rely on `var(--color-*, fallback)` — when document-level `[data-theme="dark"]` activates, tokens change, and Shadow DOM inherits them automatically. No media queries needed inside components.

### Anti-Pattern 4: Updating critical-tokens After Token Changes

**What people do:** Update design-system.css first, deploy, then update critical-tokens inline styles separately.
**Why it's wrong:** Between deploys, a user loading the page sees the old color flash for the 50–200ms before app.css loads.
**Do this instead:** Update critical-tokens inline styles in the same commit as the semantic token changes. These 22 files contain 6 hex values each — a single sed command updates all of them.

### Anti-Pattern 5: Hardcoded rgba Over var() Fallbacks

**What people do:** Write `rgba(22, 80, 224, 0.12)` directly instead of `var(--color-primary-subtle)`.
**Why it's wrong:** When primary hue changes, this value becomes visually wrong without any lint error. It is invisible in grep unless you know the old hex.
**Do this instead:** Use the semantic token. If no semantic token captures the exact intent (e.g., a 12%-opacity variant of primary), add a derived token using color-mix() rather than hardcoding the rgba.

---

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| Token change only (hue/saturation shift) | Zero file changes outside design-system.css + 22 HTML critical-tokens. Extremely safe. |
| Font family change | 22 HTML files + :root + 23 component fallback strings. Mechanical, low-risk. |
| New color palette + fonts + component geometry | All three layers + critical-tokens. Highest scope, but each layer is independent and can be done in separate commits. |
| Adding a new semantic token | design-system.css :root + [data-theme="dark"] block. No page or component changes unless they opt in. |

---

## Sources

- `public/assets/css/design-system.css` — direct read (5258 lines), ground truth for current token hierarchy, @layer structure, shadow scale, dark mode override block
- `public/assets/js/components/ag-toast.js`, `ag-modal.js`, `ag-kpi.js`, `ag-stepper.js`, `ag-vote-button.js` — direct read, Shadow DOM token consumption pattern verified
- `public/dashboard.htmx.html`, `public/wizard.htmx.html` — direct read, font loading and critical-tokens inline style pattern confirmed
- `public/assets/css/app.css` — direct read, @import chain confirmed
- `public/assets/js/theme-init.js` — direct read, [data-theme] mechanism confirmed
- Grep audit of hardcoded values across all 25 CSS files and 23 component files
- `AG-VOTE PROJECT.md` — v10.0 milestone scope confirmed

---

*Architecture research for: CSS visual identity evolution integration*
*Researched: 2026-04-03*
*Supersedes: v4.1 component architecture (retained for historical component specs)*
