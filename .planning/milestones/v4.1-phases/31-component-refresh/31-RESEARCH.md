# Phase 31: Component Refresh - Research

**Researched:** 2026-03-19
**Domain:** CSS custom properties, Web Component Shadow DOM styling, design token application
**Confidence:** HIGH — all findings verified directly from codebase; no external library research required

## Summary

Phase 31 is a pure restyling phase. All 8 component types already exist in `design-system.css` (`@layer components`) and as Shadow DOM Web Components (ag-modal, ag-toast, ag-badge, ag-stepper). Phase 30 delivered a complete token system — primitives, semantics, and component aliases are all in `:root`. The work is to reconcile what components currently render with what the token system specifies, and to eliminate hardcoded values inside Web Component inline `<style>` blocks.

The central technical constraint is Shadow DOM piercing. Web Component internal styles are isolated; they cannot inherit component-layer CSS class rules from the outer document. The solution is already established by the project: Web Components consume tokens via `var(--token-name)` with fallback literals, and the canonical visual spec lives in `design-system.css` component classes. Where a Web Component's hardcoded fallback diverges from the token, the fallback wins in practice — which is the source of every known inconsistency listed in CONTEXT.md.

The stepper has a second complication: two incompatible visual systems coexist. `ag-stepper.js` renders 20px dots with connector lines; `design-system.css` `.stepper-item` renders 28px circles inside card boxes. These must be reconciled to one canonical pattern.

**Primary recommendation:** Fix all Web Component hardcoded values first (Wave 1), then sweep design-system.css component classes to apply token-specified radius/shadow/spacing/focus patterns (Wave 2). Never mix both in the same task — isolate the Web Component reconciliation work to make DevTools inspection the verification method.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Style Source (CSS vs Web Components)**
- Web Components keep Shadow DOM but MUST use CSS custom properties (`var(--token)`) for ALL visual values
- Zero hardcoded px/hex/rgb inside ag-modal.js, ag-toast.js, ag-badge.js, ag-stepper.js
- design-system.css `:root` tokens are the single source of truth for values
- Component Shadow DOM styles reference tokens via `var()` — debuggable in DevTools (you see `var(--radius-card)` not `12px`)
- CSS design-system component classes (.modal, .toast, .badge, .stepper) stay as the canonical spec; Web Components consume them

**Dimensions & Heights**
- Claude decides heights following shadcn/Radix/Polaris best practices
- CMP spec values (36px buttons, 48px rows, 36px inputs) are targets, not hard constraints
- Consistency between components is more important than matching an exact pixel value
- All heights use design-system spacing tokens

**Radius Mapping (standard differentiation)**
- Badge: `var(--radius-full)` (pill)
- Button: `var(--radius-md)` (6px)
- Input: `var(--radius-md)` (6px)
- Card: `var(--radius-xl)` (12px)
- Modal: `var(--radius-xl)` (12px)
- Toast: `var(--radius-lg)` (8px)
- Differentiation comes from radius + shadow + height combined, not radius alone

**Hover & Elevation Model**
- Interactive components change shadow level on hover: cards sm→md, elevated elements md→lg
- `translateY(-1px)` lift reserved for clickable cards only — not buttons, not badges
- Buttons use gradient shift + shadow deepening on hover (no vertical lift)
- Table rows use background-color change only (subtle primary tint)
- No gratuitous animations — every effect signals interactivity

**Focus Ring (unified)**
- Double-ring pattern everywhere: 2px gap (surface color) + 2px ring (primary color)
- Applied uniformly: buttons, inputs, selects, textareas, cards, modal close buttons
- Works on all backgrounds (light/dark) because gap uses surface color
- Replaces current inconsistent approach (buttons: offset+ring, inputs: box-shadow)
- WCAG AA compliant focus indicators

### Claude's Discretion
- Exact transition durations and easing curves (within design-system transition tokens)
- Toast slide-in direction and animation specifics
- Stepper connector line thickness and gap
- Table alternating row implementation (keep or remove)
- Dark mode component-specific adjustments beyond token inheritance

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| CMP-01 | Buttons — 36px default height, consistent padding/radius/weight across primary/secondary/ghost/danger variants | `.btn` base has `min-height: 40px`; needs height token, hover model fix (remove translateY from primary), unified focus ring |
| CMP-02 | Cards — 24px padding minimum, shadow-sm default, shadow-md on hover with subtle lift, border-radius-lg | `.card` already uses `var(--shadow-sm)` + `var(--radius-lg)` but radius-lg=8px, not 12px (needs `var(--radius-xl)`) |
| CMP-03 | Tables — 48px row height, sticky 40px header, right-aligned numbers in monospace, hover state | `.table th` is sticky but no explicit height; rows need `height: var(--space-12)`, numeric cols need `.col-num` utility |
| CMP-04 | Form inputs — consistent height (36px), proper focus ring, error state with border-color change, label at 14px/600 | `min-height: 42px` currently; focus uses `box-shadow: 0 0 0 3px var(--ring-color)` — needs double-ring via `var(--shadow-focus)` |
| CMP-05 | Modals — centered with shadow-xl, proper backdrop opacity, header/content/footer sections, close button | CSS `.modal` correct; `ag-modal.js` has `border-radius: var(--radius-lg, 16px)` fallback instead of `var(--radius-modal, 12px)`, backdrop `rgba(0,0,0,.45)` vs CSS `.35` |
| CMP-06 | Toasts — left-border accent via inset box-shadow, 356px width, slide-in animation | `ag-toast.js` has `border-left: 3px solid` correctly but `min-width: 240px; max-width: 340px` — needs fixed `width: var(--toast-width, 356px)` |
| CMP-07 | Badges — pill shape (rounded-full), semantic color variants (success/warning/danger/info), 12px font | `ag-badge.js` has `padding: 3px 10px; font-weight: 700` vs CSS `var(--space-1) var(--space-2); var(--font-medium)` — reconcile to token values |
| CMP-08 | Steppers — proper circle size, connector lines, active/done/pending states with color differentiation | Two incompatible visual systems: ag-stepper.js (20px dots + connectors) vs .stepper-item (28px circles in card boxes) — must choose one canonical pattern |
</phase_requirements>

---

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| CSS Custom Properties | Native | Token consumption via `var()` | No build step; tokens cascade from `:root` into Shadow DOM fallback chains |
| Shadow DOM | Native | Web Component style encapsulation | Already established for ag-modal, ag-toast, ag-badge, ag-stepper |
| `design-system.css` `@layer components` | Project | Canonical component specs | Single CSS file, all pages import via app.css |

### No New Dependencies

This phase adds zero new libraries. All work is CSS and JS editing of 5 files:
1. `public/assets/css/design-system.css` — component layer update
2. `public/assets/js/components/ag-modal.js` — Shadow DOM reconciliation
3. `public/assets/js/components/ag-toast.js` — Shadow DOM reconciliation
4. `public/assets/js/components/ag-badge.js` — Shadow DOM reconciliation
5. `public/assets/js/components/ag-stepper.js` — Shadow DOM reconciliation + visual system choice

---

## Architecture Patterns

### Pattern 1: Shadow DOM Token Consumption

**What:** Web Components inherit CSS custom properties from the document `:root` through Shadow DOM boundaries. `var(--token-name, fallback)` inside a Shadow DOM `<style>` block uses the `:root` value when the token exists, and the fallback only when it doesn't.

**When to use:** Always. The fallback literal is a safety net for environments where the design system CSS hasn't loaded, NOT the primary value.

**Current bug pattern (what to fix):**
```css
/* WRONG — fallback diverges from token value */
border-radius: var(--radius-lg, 16px);
/* radius-lg = 8px in :root, but fallback says 16px
   In browsers: 8px wins. In old/broken envs: 16px shows. */
```

**Correct pattern:**
```css
/* RIGHT — fallback matches token definition */
border-radius: var(--radius-modal, var(--radius-xl, 12px));
/* Uses component alias → semantic → literal — all consistent */
```

**Key insight:** When a Web Component's fallback literal disagrees with the token, the token wins in all normal conditions. The CONTEXT.md "inconsistencies" are therefore cosmetic mismatches in the source code, not runtime bugs. But they are still wrong — they make code untrustworthy and break DevTools tracing.

### Pattern 2: Unified Focus Ring via `--shadow-focus`

**What:** A single CSS variable `--shadow-focus` delivers the double-ring pattern everywhere. Already defined in Phase 30 tokens:

```css
/* From :root — already exists */
--shadow-focus: 0 0 0 2px var(--color-surface-raised),
                0 0 0 4px var(--color-border-focus);
--shadow-focus-danger: 0 0 0 2px var(--color-surface-raised),
                        0 0 0 4px rgba(196, 40, 40, 0.35);
```

**Apply uniformly:**
```css
/* Replaces current inconsistent approaches */
.btn:focus-visible,
.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  box-shadow: var(--shadow-focus);
}

.form-input.is-error:focus,
.form-select.is-error:focus {
  box-shadow: var(--shadow-focus-danger);
}
```

**Note:** Current `.btn:focus-visible` already uses `--ring-offset` / `--ring-width` / `--ring-color` tokens in a box-shadow formula. This should be replaced with `var(--shadow-focus)` for consistency.

### Pattern 3: Card Elevation Model

**Current state:** `.card` uses `border-radius: var(--radius-lg)` (8px) — should be `var(--radius-card)` (12px = `--radius-xl`). `.card-clickable` uses `translateY(-2px)` — CONTEXT.md locks this at `translateY(-1px)`.

**Corrected card elevation:**
```css
.card {
  border-radius: var(--radius-card);  /* var(--radius-xl) = 12px */
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--color-border);
  transition: var(--transition-ui);
}

.card:hover {
  box-shadow: var(--shadow-md);
  border-color: var(--color-border-strong);
}

.card-clickable:hover {
  transform: translateY(-1px);  /* locked: -1px not -2px */
  box-shadow: var(--shadow-md);
  border-color: color-mix(in srgb, var(--color-primary) 25%, var(--color-border));
}
```

### Pattern 4: Table Data Density

**Target:** 48px rows, 40px sticky header, right-aligned numeric columns in JetBrains Mono.

```css
.table th {
  height: var(--space-10);  /* 40px */
  padding: 0 var(--space-4);
  /* existing sticky top: 0 preserved */
}

.table td {
  height: var(--space-12);  /* 48px */
  padding: 0 var(--space-4);
}

/* New utility class for numeric columns */
.col-num {
  text-align: right;
  font-family: var(--font-mono);
  font-size: var(--text-sm);
  font-variant-numeric: tabular-nums;
}
```

### Pattern 5: Toast Width and Stack

**Target:** Fixed `356px` width, `8px` gap between stacked toasts, slide from right.

```css
/* In ag-toast.js Shadow DOM style */
.toast {
  width: var(--toast-width, 356px);
  /* Remove min-width/max-width */
}
```

```css
/* In design-system.css .toast-container */
.toast-container {
  gap: var(--space-2);  /* 8px — already correct */
}
```

**The `--toast-width` token:** Not yet defined in Phase 30 tokens. Must be added as a component alias in `:root` during this phase.

### Pattern 6: Stepper — Chosen Visual System

**Decision required by implementation:** The ag-stepper.js visual (dots + connector lines) and the .stepper-item CSS (card boxes) cannot both be canonical. Research recommendation: **use connector-line style (matching ag-stepper.js)** because:
- ag-stepper.js is what renders in the DOM; `.stepper-item` CSS is never applied to the same elements
- Connector-line style (dots + lines) is the industry standard (shadcn Steps, Ant Design Steps, MUI Stepper)
- Card-box style is more compact but loses the "progress track" visual signal

The CSS `.stepper-item` classes should be updated to match the connector-line pattern. The ag-stepper.js dot size should be updated from 20px to 28px (matching `.stepper-number` spec).

**Reconciled stepper CSS:**
```css
.stepper {
  display: flex;
  align-items: center;
  gap: 0;  /* connectors fill the gap */
}

.stepper-item {
  flex: 1;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  /* Remove card-box background/border */
}

/* Connector line via pseudo-element */
.stepper-item:not(:last-child)::after {
  content: '';
  flex: 1;
  height: 2px;
  background: var(--color-border);
  margin: 0 var(--space-2);
}
```

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Double focus ring | Custom box-shadow formula per component | `var(--shadow-focus)` already in Phase 30 tokens | Token is already correct; hand-rolling creates drift |
| Shadow token | Per-component rgba() values | `var(--shadow-sm)` / `var(--shadow-md)` etc. | Shadow system already theme-aware via `--shadow-color` variable |
| Monospace numeric columns | Custom font embedding | `var(--font-mono)` = 'JetBrains Mono' — already loaded | Font already in the stack |
| Toast width | min/max-width pair | Single `width` + new `--toast-width` component token | Consistent with how modal uses `min(520px, 100%)` |
| Transition values | Per-property ms values | `var(--transition-ui)` or `var(--duration-fast) var(--ease-default)` | Transition tokens already standardized |

---

## Common Pitfalls

### Pitfall 1: Shadow DOM Fallback Confusion

**What goes wrong:** Developer sets `var(--radius-modal, 16px)` as the fallback. Token value is 12px. Browser uses 12px. Developer inspects DevTools, sees the token resolving correctly, and thinks the fallback doesn't matter. The fallback matters when the CSS file fails to load (dev environments, race conditions) — but more importantly, it's semantically misleading.

**How to avoid:** Fallback literal must always equal the token's own definition value. For nested aliases: `var(--radius-modal, var(--radius-xl, 12px))`.

**Warning signs:** Fallback literal (e.g. `16px`) does not match the token value (e.g. `0.75rem` = 12px).

### Pitfall 2: Button Hover Lift Contradiction

**What goes wrong:** CONTEXT.md locks `translateY(-1px)` for clickable cards only. Current `.btn-primary:hover` has `transform: translateY(-1px)`. Removing it may visually flatten buttons that users have come to expect a lift on.

**How to avoid:** Replace lift with shadow deepening. `.btn-primary:hover` should drop the `translateY(-1px)` and instead strengthen the box-shadow from the current `0 4px 8px rgba(22,80,224,.35)` to use `var(--shadow-md)` combined with the inset highlight. Test all button variants — `.btn-success` and `.btn-danger` also have `translateY(-1px)`.

**Warning signs:** Any `translateY()` on a `.btn` hover rule.

### Pitfall 3: ag-stepper.js vs .stepper-item Mismatch

**What goes wrong:** `.stepper-item` CSS is updated to connector-line style, but ag-stepper.js still renders its own `<style>` block with the old 20px dots. Since Shadow DOM isolates styles, the CSS update to `.stepper-item` has zero effect on ag-stepper instances.

**How to avoid:** ag-stepper.js must be updated in the SAME task as the CSS update. The JS Shadow DOM style must be the definitive implementation; the CSS `.stepper-item` classes exist for non-component usage (plain HTML steppers, if any).

**Warning signs:** Inspecting an `<ag-stepper>` in DevTools showing 20px dots after the CSS was updated to 28px circles.

### Pitfall 4: Card Radius Token Mismatch

**What goes wrong:** `.card` uses `var(--radius-lg)` (8px). The CONTEXT.md specifies cards at `var(--radius-xl)` (12px) via `--radius-card`. Changing `.card` to `var(--radius-card)` also affects `.card-footer` which explicitly sets `border-radius: 0 0 var(--radius-lg) var(--radius-lg)` — that bottom-corner radius must also be updated.

**How to avoid:** Search for all radius references on `.card` and sub-elements. `.card-footer` needs `border-radius: 0 0 var(--radius-card) var(--radius-card)`.

**Warning signs:** Card body shows correct 12px corners but footer shows 8px corners.

### Pitfall 5: Form Input Height Inconsistency

**What goes wrong:** `min-height: 42px` (current) and `min-height: 34px` (sm variant) will produce visually inconsistent heights across form-heavy pages. The target is ~36px default.

**How to avoid:** Use `height` (not min-height) for inputs at a specific token value: `height: calc(var(--space-4) + var(--space-4) + 1.5em)` is fragile; instead use a component token `--input-height: var(--space-9)` (36px) added in Phase 30 component aliases, or if not yet added, add it here. Alternatively `min-height: 36px` using `min-height: var(--space-9)` where `--space-9: 2.25rem = 36px`. Check: `--space-9` does not currently exist in the token scale (jumps from --space-8: 32px to --space-10: 40px). Options: add `--space-9: 2.25rem` to spacing scale or use `--input-height: 36px` as a component alias.

**Warning signs:** Inputs displaying at 42px height when buttons are at 36px — height mismatch creates visual dissonance in form rows.

### Pitfall 6: Toast Border-Left vs Inset Box-Shadow

**What goes wrong:** CMP-06 spec says "left-border accent via inset box-shadow" but current implementation uses `border-left: 4px solid var(--color-success)` in `.css` and `border-left: 3px solid` in `ag-toast.js`. Using `border-left` changes the box model; using `inset box-shadow` does not. They look identical but behave differently when combined with border-radius (inset shadow respects radius, border-left creates a square notch at the corner).

**How to avoid:** Use `box-shadow: inset 4px 0 0 var(--color-success)` instead of `border-left`. This is the Sonner pattern and it works correctly with rounded corners.

**Warning signs:** Left accent appears as a square notch at the top-left corner where the border-radius should curve.

---

## Code Examples

### Unified Focus Ring (applying `--shadow-focus`)

```css
/* Source: design-system.css :root — Phase 30 tokens */

/* Before: buttons */
.btn:focus-visible {
  outline: none;
  box-shadow: 0 0 0 var(--ring-offset) var(--color-surface),
              0 0 0 calc(var(--ring-offset) + var(--ring-width)) var(--ring-color);
}

/* After: buttons */
.btn:focus-visible {
  outline: none;
  box-shadow: var(--shadow-focus);
}

/* Before: inputs */
.form-input:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px var(--ring-color);
}

/* After: inputs */
.form-input:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: var(--shadow-focus);
}
```

### Web Component Radius Reconciliation

```javascript
// Source: ag-modal.js — before
`.modal {
  border-radius: var(--radius-lg, 16px);   /* WRONG: radius-lg=8px, fallback=16px */
}`

// After
`.modal {
  border-radius: var(--radius-modal, var(--radius-xl, 12px));  /* Component alias → semantic → literal */
}`
```

### ag-badge.js Token Alignment

```javascript
// Source: ag-badge.js — before
`.badge {
  padding: 3px 10px;
  font-weight: 700;
  gap: 0.375rem;   /* 6px hardcoded */
}`

// After (using tokens with correct fallbacks)
`.badge {
  padding: var(--space-1, 4px) var(--space-2, 8px);
  font-weight: var(--font-medium, 500);
  gap: var(--space-1, 4px);
}`
```

### Toast Inset Border Pattern

```javascript
// Source: ag-toast.js — before
`:host([type="success"]) .toast { border-left: 3px solid var(--color-success); }`

// After — inset box-shadow for proper radius behavior
`:host([type="success"]) .toast {
  box-shadow: var(--shadow-lg), inset 3px 0 0 var(--color-success, #0b7a40);
}`
```

### ag-stepper.js — Size Upgrade

```javascript
// Source: ag-stepper.js — before
`.dot {
  width: 20px; height: 20px;
}`

// After — matches .stepper-number spec (28px)
`.dot {
  width: var(--stepper-dot-size, 28px);
  height: var(--stepper-dot-size, 28px);
}`
```

---

## Inventory of Hardcoded Values to Eliminate

Verified by inspection of 4 Web Component files. Sorted by impact:

### ag-modal.js

| Location | Hardcoded Value | Correct Token |
|----------|----------------|---------------|
| `border-radius: var(--radius-lg, 16px)` | fallback `16px` wrong | `var(--radius-modal, var(--radius-xl, 12px))` |
| `background: rgba(0,0,0,.45)` | backdrop opacity `.45` | `var(--color-backdrop)` (defined as `rgba(0,0,0,0.5)`) or use `.35` to match CSS |
| `padding: 16px` (backdrop) | `16px` hardcoded | `var(--space-4, 16px)` (fallback value is now correct) |
| `.modal-h { padding: 14px 20px }` | mixed hardcoded | `var(--space-4) var(--space-5)` → maps to `16px 20px` |
| `.modal-b { padding: 18px 20px }` | `18px` not on scale | `var(--space-5)` (20px) |
| `.modal-f { gap: 6px }` | `6px` not on scale | `var(--space-2)` (8px) |
| `font-size: 13px; font-weight: 700` (.modal-h .t) | literals | `var(--text-sm); var(--font-bold)` |
| `z-index: 100` | literal | `var(--z-modal)` |

### ag-toast.js

| Location | Hardcoded Value | Correct Token |
|----------|----------------|---------------|
| `padding: 12px 16px` | literals | `var(--space-3) var(--space-4)` |
| `max-width: 340px; min-width: 240px` | range | `width: var(--toast-width, 356px)` |
| `border-left: 3px solid` | `3px` literal | keep `3px` or define `--toast-accent-width: 3px` |
| `border-radius: 50%` (.toast-icon) | fixed | `var(--radius-full, 9999px)` |
| `border-radius: 4px` (.toast-close) | `4px` | `var(--radius-sm, 4px)` — fallback correct |
| `font-size: 13px` (.toast) | literal | `var(--text-sm)` |
| `gap: 10px` | `10px` not on scale | `var(--space-2-5)` (10px) — fallback correct |
| Position: `top: 20px; right: 20px` (static JS) | inline style | convert to `var(--space-5)` or `var(--space-4)` |

### ag-badge.js

| Location | Hardcoded Value | Correct Token |
|----------|----------------|---------------|
| `padding: 3px 10px` | non-scale | `var(--space-1) var(--space-2)` |
| `font-weight: 700` | literal | `var(--font-medium)` (500 per spec) |
| `gap: 0.375rem` (6px) | non-scale | `var(--space-1)` (4px per spec) |
| `font-size: 12px` | literal | `var(--text-xs)` |
| `padding: 2px 7px` (sm) | non-scale | `var(--space-0-5) var(--space-2)` |

### ag-stepper.js

| Location | Hardcoded Value | Correct Token |
|----------|----------------|---------------|
| `width: 20px; height: 20px` (dot) | `20px` | `var(--stepper-dot-size, 28px)` |
| `gap: 6px` (.step) | `6px` | `var(--space-1-5)` |
| `height: 2px` (connector) | literal | `var(--stepper-connector-height, 2px)` |
| `font-size: 11px` (.dot) | `11px` | `var(--text-2xs)` |
| `font-size: 11px` (.step-label) | `11px` | `var(--text-2xs)` |
| `margin-bottom: 14px` (.stepper) | non-scale | `var(--space-4)` |
| `padding: 0 4px` (.step) | `4px` | `var(--space-1)` |

---

## State of the Art

| Old Approach | Current Approach | Status | Impact |
|--------------|------------------|--------|--------|
| `border-left: Npx solid` for toast accent | `box-shadow: inset Npx 0 0 color` | Needs change | Radius compatibility |
| `translateY(-Npx)` on all interactive hover | Only clickable cards lift; buttons use shadow | Needs change | 3 button variants |
| Per-element focus ring formula | `var(--shadow-focus)` token everywhere | Needs change | All focusable elements |
| Literal px values in Web Component styles | `var(--token, fallback)` with correct literal | Needs change | All 4 Web Components |
| `.card` at `var(--radius-lg)` = 8px | `.card` at `var(--radius-card)` = 12px | Needs change | Card visual weight |

---

## New Tokens Required in Phase 31

These component alias tokens do not yet exist in Phase 30's `:root` and must be added to `design-system.css` `:root` COMPONENT ALIASES section:

| Token | Value | Purpose |
|-------|-------|---------|
| `--toast-width` | `356px` | Fixed toast width per CMP-06 |
| `--toast-accent-width` | `3px` | Left accent stripe thickness |
| `--stepper-dot-size` | `28px` | Dot/circle diameter |
| `--stepper-connector-height` | `2px` | Connector line thickness |
| `--input-height` | `36px` | Form input target height |
| `--btn-height` | `36px` | Button target height |

Note: `--space-9` (36px) could substitute for `--input-height` and `--btn-height` if added to the spacing scale. Current scale jumps `--space-8: 32px` → `--space-10: 40px`. Adding `--space-9: 2.25rem` is cleaner.

---

## Open Questions

1. **Space-9 gap in spacing scale**
   - What we know: No `--space-9` token exists; scale jumps 32px → 40px
   - What's unclear: Was 36px intentional omission or oversight?
   - Recommendation: Add `--space-9: 2.25rem` (36px) to spacing scale and use for input/button height. Alternatively use component-specific tokens `--input-height` and `--btn-height` to avoid modifying the spacing scale.

2. **Modal backdrop color alignment**
   - What we know: `ag-modal.js` uses `rgba(0,0,0,.45)`; CSS `.modal-backdrop` uses `rgba(0,0,0,.35)`; `--color-backdrop` token = `rgba(0,0,0,0.5)`
   - What's unclear: Which value is intentional? Three different values across three places.
   - Recommendation: Use `var(--color-backdrop)` = 0.5 in the Web Component; update CSS `.modal-backdrop` to also use `var(--color-backdrop)`. Lock the value in the token.

3. **Stepper canonical system: CSS card-boxes or connector lines**
   - What we know: Two visual systems exist; ag-stepper.js renders in production DOM; .stepper-item is rarely used in plain HTML
   - What's unclear: Do any pages use `.stepper-item` in plain HTML (not via `<ag-stepper>`)?
   - Recommendation: Check if `.stepper-item` is used on any page before deprecating. If so, update both systems to match.

---

## Validation Architecture

> `workflow.nyquist_validation` is not set in config.json — section included.

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit (vendor/bin/phpunit) |
| Config file | phpunit.xml (assumed) |
| Quick run command | `vendor/bin/phpunit tests/Unit/ --no-coverage` |
| Full suite command | `vendor/bin/phpunit --no-coverage` |

### Phase Requirements — Test Map

CSS visual changes are not automatically testable via PHPUnit. Testing strategy is visual/browser-based for this phase.

| Req ID | Behavior | Test Type | Method | Notes |
|--------|----------|-----------|--------|-------|
| CMP-01 | Button height ≈36px, variants consistent | Manual visual | DevTools computed styles | No automated CSS test |
| CMP-02 | Card shadow-sm at rest, shadow-md on hover, radius-xl | Manual visual | DevTools hover inspect | |
| CMP-03 | Table row 48px, header 40px, monospace numerics | Manual visual | DevTools box model | |
| CMP-04 | Input 36px height, double-ring focus, red error border | Manual visual | Tab through form fields | |
| CMP-05 | Modal shadow-xl, backdrop aligned, sections present | Manual visual | Open modal, inspect Shadow DOM | |
| CMP-06 | Toast 356px, inset left accent, slide-in | Manual visual | Trigger AgToast.show() | |
| CMP-07 | Badge pill, font-medium, correct padding | Manual visual | Render all badge variants | |
| CMP-08 | Stepper 28px dots, connectors, state colors | Manual visual | Render stepper with 4 steps | |

### DevTools Verification Protocol

The primary verification for this phase is DevTools inspection, not automated tests. For each Web Component:

1. Select the `<ag-modal>` (or other) element in Elements panel
2. Click into Shadow DOM (`#shadow-root (open)`)
3. Inspect computed styles on `.modal` — every value should show `var(--token-name)` in the source, not a literal
4. If a computed value shows as a literal in the source column, that's a hardcoded value to fix

### Wave 0 Gaps

None — no new test files required. Visual verification is the validation method for CSS/Shadow DOM styling changes.

---

## Sources

### Primary (HIGH confidence)
- Direct codebase inspection — design-system.css (5054 lines), ag-modal.js, ag-toast.js, ag-badge.js, ag-stepper.js
- 31-CONTEXT.md — user decisions and locked constraints
- REQUIREMENTS.md — CMP-01 through CMP-08 specifications
- STATE.md — Phase 30 decisions and token system decisions

### Secondary (MEDIUM confidence)
- shadcn/ui component dimensions referenced as targets (shadcn Input height: 36px, shadcn Button default: 36px) — consistent with CMP spec
- Sonner toast library pattern (inset box-shadow for left accent) — established best practice for rounded-corner toast accents

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — vanilla CSS/JS, no external dependencies, all patterns verified in codebase
- Architecture: HIGH — token system fully documented in design-system.css, patterns clearly established
- Pitfalls: HIGH — identified by direct inspection of hardcoded values in Web Component source files
- Hardcoded values inventory: HIGH — extracted verbatim from source files

**Research date:** 2026-03-19
**Valid until:** 2026-04-18 (stable domain; tokens won't change unless Phase 30 is re-run)
