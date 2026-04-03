# Phase 82: Token Foundation + Palette Shift - Research

**Researched:** 2026-04-03
**Domain:** CSS custom properties, oklch color space, color-mix(), design token migration
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**oklch Migration Strategy**
- Semantic tokens reference primitive vars (e.g., `--color-bg: var(--stone-200)`) rather than inline oklch values — primitives already carry oklch, so semantics inherit perceptual uniformity automatically
- No hex fallbacks for oklch — 95%+ global support, AG-VOTE targets modern browsers
- All 42 `color-mix(in srgb)` calls upgraded to `color-mix(in oklch)` in one pass (success criterion 4 requires zero srgb results)
- All `rgba()` calls in semantic tokens converted to oklch alpha syntax (e.g., `oklch(0.52 0.195 265 / 0.12)`)

**Warm Gray & Palette Tuning**
- Stone palette hue range 75-95 kept as-is — already warm-neutral, reads well
- Indigo primary hue 265 unchanged — "officiel et confiance" identity depends on it
- Dark mode surfaces warmed up — shift from cool blue-tinted (hue ~260) to warm-dark (hue 75-80) to align with warm-neutral identity
- Gray chroma stays subtle at 0.006-0.030 — enough warmth to feel intentional, not enough to look beige

**Derived Tokens & Dark Mode Sync**
- Derived hover/disabled states computed via `color-mix(in oklch, base%, black/white)` — self-updating when base changes
- Dark mode tokens manually overridden with explicit oklch values — dark mode needs intentional lightness inversion and reduced chroma that auto-derivation can't produce
- All 22 .htmx.html critical-tokens inline blocks updated in the same commit as token changes (prevents flash-of-wrong-color)
- Scope limited to design-system.css + critical-tokens only — per-page CSS hex cleanup deferred to Phase 84 (HARD-01)

### Claude's Discretion

No discretion areas specified — all decisions are locked.

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope. Per-page CSS hex cleanup is Phase 84 scope only.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| COLOR-01 | Semantic color tokens modernized for perceptual uniformity and automatic shade derivation | Migrate semantic tokens from hex to `var(--primitive)` references; primitives already carry oklch values as duplicate declarations |
| COLOR-02 | Warm-neutral gray ramp replaces current cool-toned grays across all surfaces (bg, surface, raised) | Light mode already uses stone palette (warm); dark mode needs bg/surface/raised/surface-alt shifted from hue ~260 to hue 75-80 |
| COLOR-03 | Accent color (indigo) appears only at interactive elements — CTAs, active nav, focus ring, inline links | Audit decorative uses of `--color-primary` and `--color-accent`; `.page-title .bar` and `.logo-mark` use primary decoratively — move to neutral/stone tokens |
| COLOR-04 | Derived tint/shade tokens (hover, disabled, subtle) computed from base values instead of manually maintained | Replace `--color-primary-hover: #1140C0` etc. with `color-mix(in oklch, var(--color-primary), black 12%)` pattern |
| COLOR-05 | Dark mode overrides explicitly re-declare all derived tokens to prevent stale light-mode computation | Dark mode block already re-declares tint tokens (lines 717-726); upgrade from `in srgb` to `in oklch` and add any missing derived tokens |
</phase_requirements>

---

## Summary

Phase 82 is a pure CSS token layer migration — no HTML changes, no per-page CSS changes. The entire scope fits within `design-system.css` `@layer base` and the `<style id="critical-tokens">` blocks inside each `.htmx.html` file.

The project already has a well-structured two-level token architecture (primitives → semantics) with oklch values defined as duplicate declarations on every primitive (lines 96-135). The semantic token block (:root, lines 275-585) still uses raw hex and rgba() values instead of referencing these primitives. The migration work is: (1) reroute semantic tokens to `var(--stone-*)` / `var(--blue-*)` etc., (2) convert rgba() alpha tokens to `oklch(L C H / alpha)` syntax, (3) upgrade all 42 `color-mix(in srgb)` calls to `color-mix(in oklch)`, (4) warm up the dark mode surface stack from cool hue ~260 to hue 75-80, and (5) sync the 21 critical-token `<style>` blocks (all identical: `--color-bg`, `--color-surface`, `--color-text`).

The critical constraint is that dark mode `[data-theme="dark"]` re-declares tint tokens (lines 717-726) using `color-mix(in srgb)` — these must be upgraded to `in oklch` and must explicitly re-declare ALL derived tokens including hover states so that light-mode `color-mix()` results are never inherited by dark mode.

**Primary recommendation:** Migrate in three discrete groups — (1) surface/background tokens via primitive references, (2) all `color-mix()` calls in-place from `srgb` to `oklch`, (3) critical-token sync. Each group is independently verifiable with a grep.

---

## Standard Stack

### Core
| Technology | Version | Purpose | Why Standard |
|-----------|---------|---------|--------------|
| CSS Custom Properties | Native (all modern browsers) | Token storage and cascade | No build step; native inheritance via `var()` |
| oklch() color function | CSS Color Level 4 (96% global support) | Perceptually uniform color space | Consistent lightness across hues; `color-mix()` avoids grey mud |
| color-mix() | CSS Color Level 5 (95%+ support) | Derived tint/shade computation | Declarative derivation that updates when base token changes |
| @layer | CSS Cascade Layers (95%+ support) | Cascade ordering (base/components/v4) | Already in use; no change needed |

### No External Dependencies
This phase has zero npm/composer dependencies. All work is in native CSS.

---

## Architecture Patterns

### Current Design-System Token Architecture

```
design-system.css
├── @layer base
│   ├── :root
│   │   ├── PRIMITIVES (lines 83-400)
│   │   │   ├── --stone-50 through --stone-900  (hex + oklch as duplicate)
│   │   │   ├── --blue-50 through --blue-800   (hex + oklch as duplicate)
│   │   │   └── other palettes (green, amber, red, purple)
│   │   └── SEMANTICS (lines 275-585)
│   │       ├── --color-bg: #EDECE6             ← MIGRATE to var(--stone-200)
│   │       ├── --color-primary: #1650E0        ← MIGRATE to var(--blue-600)
│   │       ├── rgba() tokens                   ← CONVERT to oklch(L C H / alpha)
│   │       └── color-mix(in srgb) tokens       ← UPGRADE to color-mix(in oklch)
│   └── [data-theme="dark"]  (lines 588-727)
│       ├── SEMANTICS overrides (hex + rgba)    ← MIGRATE + WARM-UP surfaces
│       └── color-mix(in srgb) tint tokens      ← UPGRADE to color-mix(in oklch)
└── @layer components (line 748+)
    └── color-mix(in srgb) inline usages        ← UPGRADE (22 occurrences)
```

### Pattern 1: Semantic Token via Primitive Reference (COLOR-01)

**What:** Semantic tokens reference primitive CSS vars instead of raw hex, so changing a primitive automatically propagates to all semantic consumers.

**When to use:** All surface, text, border, and solid color tokens.

**Before (current):**
```css
/* In :root */
--color-bg: #EDECE6;
--color-surface: #FAFAF7;
--color-text: #52504A;
--color-primary: #1650E0;
```

**After (target):**
```css
/* In :root */
--color-bg:      var(--stone-200);   /* oklch(0.922 0.013 95) */
--color-surface: var(--stone-50);    /* oklch(0.969 0.006 95) */
--color-text:    var(--stone-700);   /* oklch(0.530 0.025 80) */
--color-primary: var(--blue-600);    /* oklch(0.520 0.195 265) */
```

**Note:** The duplicate declaration pattern in primitives (hex then oklch on the same var name) means the last declaration wins in browsers that support oklch. Referencing `var(--stone-200)` therefore resolves to `oklch(0.922 0.013 95)` in modern browsers.

### Pattern 2: oklch Alpha Syntax for Transparency Tokens (COLOR-01)

**What:** Replace `rgba(R, G, B, alpha)` with `oklch(L C H / alpha)` so transparency tokens stay in the same color space as their solid counterparts.

**Before (current):**
```css
--color-border-focus: rgba(22, 80, 224, 0.50);
--color-primary-muted: rgba(22, 80, 224, 0.12);
--color-primary-glow:  rgba(22, 80, 224, 0.12);
```

**After (target):**
```css
--color-border-focus:  oklch(0.520 0.195 265 / 0.50);
--color-primary-muted: oklch(0.520 0.195 265 / 0.12);
--color-primary-glow:  oklch(0.520 0.195 265 / 0.12);
```

**Note:** `--blue-600` has oklch values `(0.520 0.195 265)`. Dark mode primary is `--blue-400` at `(0.680 0.130 265)`.

### Pattern 3: Derived Tint/Shade via color-mix(in oklch) (COLOR-04)

**What:** Hover, active, and disabled states computed from base token, not hardcoded hex.

**Before (current — manually maintained hex):**
```css
--color-primary:        #1650E0;
--color-primary-hover:  #1140C0;   /* manually maintained */
--color-primary-active: #0C30A0;   /* manually maintained */
```

**After (target — self-updating derivation):**
```css
--color-primary:        var(--blue-600);
--color-primary-hover:  color-mix(in oklch, var(--color-primary) 88%, black);
--color-primary-active: color-mix(in oklch, var(--color-primary) 76%, black);
```

**For tint tokens:**
```css
/* Light mode :root */
--color-primary-tint-10: color-mix(in oklch, var(--color-primary) 10%, white);
--color-primary-tint-5:  color-mix(in oklch, var(--color-primary) 5%,  white);
--color-primary-shade-10: color-mix(in oklch, var(--color-primary) 90%, black);
--color-surface-elevated: color-mix(in oklch, var(--color-surface) 97%, var(--color-primary));
```

### Pattern 4: Dark Mode Surface Warming (COLOR-02)

**What:** Dark mode `--color-bg` and surfaces shifted from cool hue ~260 to warm-dark hue 75-80.

**Current dark mode surfaces (cool blue-tinted):**
```css
[data-theme="dark"] {
  --color-bg:            #0B0F1A;   /* oklch ~(0.09 0.022 260) — cool */
  --color-bg-subtle:     #1B2030;   /* cool */
  --color-surface:       #141820;   /* cool */
  --color-surface-alt:   #1B2030;   /* cool */
  --color-surface-raised: #1E2438;  /* cool */
}
```

**Target dark mode surfaces (warm-dark hue 75-80):**
```css
[data-theme="dark"] {
  --color-bg:             oklch(0.090 0.008 78);   /* near-black, warm */
  --color-bg-subtle:      oklch(0.130 0.010 78);
  --color-surface:        oklch(0.115 0.009 78);
  --color-surface-alt:    oklch(0.130 0.010 78);
  --color-surface-raised: oklch(0.145 0.011 78);
}
```

**Chroma guidance:** Keep chroma at 0.006-0.012 range for dark surfaces — perceptible warmth without appearing brown. Scale chroma proportionally with lightness (lighter surfaces can take slightly more chroma).

### Pattern 5: Dark Mode Derived Token Re-Declaration (COLOR-05)

**What:** Dark mode block must explicitly re-declare ALL derived tokens so light-mode `color-mix()` values are never inherited. CSS custom properties do not re-evaluate `color-mix()` per theme automatically — the `var()` references are inherited.

**Current state:** Dark mode already re-declares the 10 tint tokens (lines 717-726) but uses `in srgb`. Must be upgraded to `in oklch`.

**Required dark mode re-declarations:**
```css
[data-theme="dark"] {
  /* Re-declare derived tints against dark surface, in oklch */
  --color-primary-tint-10: color-mix(in oklch, var(--color-primary) 10%, var(--color-surface));
  --color-primary-tint-5:  color-mix(in oklch, var(--color-primary) 5%,  var(--color-surface));
  --color-success-tint-10: color-mix(in oklch, var(--color-success) 10%, var(--color-surface));
  --color-success-tint-5:  color-mix(in oklch, var(--color-success) 5%,  var(--color-surface));
  --color-danger-tint-10:  color-mix(in oklch, var(--color-danger)  10%, var(--color-surface));
  --color-danger-tint-5:   color-mix(in oklch, var(--color-danger)  5%,  var(--color-surface));
  --color-warning-tint-10: color-mix(in oklch, var(--color-warning) 10%, var(--color-surface));
  --color-warning-tint-5:  color-mix(in oklch, var(--color-warning) 5%,  var(--color-surface));
  --color-primary-shade-10: color-mix(in oklch, var(--color-primary) 90%, var(--color-surface));
  --color-surface-elevated: color-mix(in oklch, var(--color-surface) 95%, var(--color-primary));
  /* Also re-declare hover/active derived tokens */
  --color-primary-hover:  color-mix(in oklch, var(--color-primary) 88%, white); /* lighter in dark */
  --color-primary-active: color-mix(in oklch, var(--color-primary) 76%, white);
}
```

**Important:** Dark mode hover states should mix toward white (lighten), not black (darken), since dark mode primary is already a lighter shade.

### Pattern 6: Component-Level color-mix() Upgrade

**What:** All 22 inline `color-mix(in srgb, ...)` calls in component CSS rules (lines 1489-5167) must be upgraded to `color-mix(in oklch, ...)`.

**Example:**
```css
/* Before */
background: linear-gradient(180deg, var(--color-primary) 0%, color-mix(in srgb, var(--color-primary), #000 8%) 100%);

/* After */
background: linear-gradient(180deg, var(--color-primary) 0%, color-mix(in oklch, var(--color-primary), black 8%) 100%);
```

**Note:** `#000` and `black` are equivalent; prefer the named keyword for readability after migration.

### Pattern 7: Critical-Tokens Sync

**What:** All 21 `.htmx.html` files contain an identical 4-line `<style id="critical-tokens">` block that pre-loads three tokens before the full stylesheet arrives, preventing flash-of-wrong-color.

**Current state (all 21 files identical):**
```html
<style id="critical-tokens">
  :root { --color-bg: #EDECE6; --color-surface: #FAFAF7; --color-text: #151510; }
  [data-theme="dark"] { --color-bg: #0B0D10; --color-surface: #141820; --color-text: #ECF0FA; }
  html, body { background: var(--color-bg); }
</style>
```

**Target (after migration):**
```html
<style id="critical-tokens">
  :root { --color-bg: oklch(0.922 0.013 95); --color-surface: oklch(0.969 0.006 95); --color-text: oklch(0.530 0.025 80); }
  [data-theme="dark"] { --color-bg: oklch(0.090 0.008 78); --color-surface: oklch(0.115 0.009 78); --color-text: oklch(0.908 0.015 265); }
  html, body { background: var(--color-bg); }
</style>
```

**Note:** The dark mode `--color-text` in critical-tokens is currently `#ECF0FA` (cool blue-white). The updated value should match the new semantic token. `--color-text-dark` in dark mode is `#ECF0FA` = `oklch(0.908 0.015 265)` — this stays as indigo-tinted (it's text, not a surface).

**File count:** The actual count is 21 files (not 22 as stated in STATE.md — verified by direct inspection of `/home/user/gestion_votes_php/public/`).

### Anti-Patterns to Avoid

- **Hex fallbacks on oklch:** `oklch(0.922 0.013 95)` requires no hex fallback for this project (modern browsers targeted). Adding them creates maintenance burden.
- **Renaming tokens:** Never rename existing token names (e.g., `--color-primary` must stay `--color-primary`). Shadow DOM components cache the name. Add new tokens alongside old if needed.
- **Alpha tokens via color-mix:** Do not use `color-mix()` to create transparency — use `oklch(L C H / alpha)` syntax. `color-mix()` is for lightness/shade derivation only.
- **Partial srgb→oklch pass:** The success criterion requires zero `color-mix(in srgb` results. Partial migration leaves the file in an inconsistent state.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Detecting hue shift quality | Manual eyeball check only | CSS color-mix test in browser devtools | OKLCH perceptual uniformity is the point — trust the math |
| Generating oklch values from hex | Manual hex-to-oklch calculator | Map directly from existing primitive table (lines 96-135) | Values already computed; just transcribe from the comment |
| Dark mode derived tint computation | New dark-specific token names | Re-declare same token names in `[data-theme="dark"]` block | CSS cascade handles override; component CSS uses the same token names |

---

## Common Pitfalls

### Pitfall 1: Duplicate Primitive Declaration Ordering

**What goes wrong:** The stone palette uses duplicate CSS var declarations (hex first, oklch second on the same var name). The last declaration wins. If semantic tokens are migrated to `var(--stone-200)` and something elsewhere re-declares `--stone-200` with the hex, semantics get hex back.

**Why it happens:** CSS custom property re-declaration within the same block is valid — later wins. The intent is progressive enhancement (hex for old browsers, oklch for modern).

**How to avoid:** Do not add a third declaration to primitives. The existing pattern is intentional. When writing oklch values in semantic tokens directly (e.g., critical-tokens), use the `oklch(L C H)` syntax directly without var() — critical-tokens are intentionally inlined to avoid the var() dependency chain before the stylesheet loads.

**Warning signs:** Dark mode surface appears warm in devtools Elements panel but renders cool visually — suggests a re-declaration earlier in the cascade is winning.

### Pitfall 2: Dark Mode color-mix() Inheriting Light Base

**What goes wrong:** `--color-primary-hover: color-mix(in oklch, var(--color-primary) 88%, black)` is declared in `:root`. In dark mode, `--color-primary` is overridden to a lighter blue. But if `--color-primary-hover` is not re-declared in `[data-theme="dark"]`, it keeps the light-mode `color-mix()` result computed against the light-mode primary — a too-dark blue in dark mode.

**Why it happens:** CSS custom properties store their declaration value (the string `color-mix(...)`) not the computed result. However, `var()` references inside `color-mix()` resolve at paint time. So if `--color-primary` is overridden in dark mode, `--color-primary-hover` from `:root` WILL pick up the dark-mode primary automatically. The issue is only with tokens that mix with hardcoded colors (white/black) — in dark mode, hover should mix toward white, not black.

**How to avoid:** Re-declare hover tokens in dark mode with direction reversed: `color-mix(in oklch, var(--color-primary) 88%, white)` for hover in dark mode.

**Warning signs:** Hover state appears too dark (nearly invisible) on dark mode interactive elements.

### Pitfall 3: Critical-Tokens Flash After Migration

**What goes wrong:** design-system.css is updated with new warm dark mode values, but `.htmx.html` critical-tokens still contain the old `#0B0D10` dark bg. On slow connections, the body briefly shows the old cool-dark background before the full stylesheet loads.

**Why it happens:** Critical-tokens are intentionally isolated from the main stylesheet. They are not automatically kept in sync.

**How to avoid:** The 21 critical-token blocks are all identical. Update them in the same commit as the design-system.css changes. The update is mechanical: replace the 3 hex values in the pattern with the new oklch values.

**Warning signs:** Running `grep "#0B0D10\|#EDECE6\|#FAFAF7\|#151510\|#141820\|#ECF0FA" public/*.htmx.html` returns results after migration.

### Pitfall 4: color-mix() Syntax Differences Between srgb and oklch

**What goes wrong:** `color-mix(in srgb, red 30%, transparent)` and `color-mix(in oklch, red 30%, transparent)` produce different results. oklch interpolation produces more visually consistent intermediate colors but can surprise if the percentage values were tuned for srgb.

**Why it happens:** Color space affects how the midpoint blend appears. oklch avoids the grey muddy midpoint common in srgb.

**How to avoid:** After migration, do a visual spot-check of the most prominent tinted borders (tag borders, search result highlights, form focus rings). Percentage values may need minor adjustment (±3-5%) to achieve equivalent visual weight.

**Warning signs:** Tag borders appear slightly more saturated or borders appear too faint after migration.

### Pitfall 5: Sidebar rgba() Tokens Not Covered by Primitive Migration

**What goes wrong:** Sidebar tokens (`--sidebar-bg: #0C1018`, `--sidebar-hover: rgba(255, 255, 255, 0.1)`) are not covered by primitive references since there is no primitive for near-black sidebar colors.

**Why it happens:** Sidebar uses a dark panel that exists in both light and dark mode — it has no warm/cool semantic meaning tied to the surface palette.

**How to avoid:** Convert sidebar rgba() to oklch alpha syntax but keep `--sidebar-bg` as a direct oklch value (not a primitive reference). `#0C1018` ≈ `oklch(0.080 0.015 260)` — this is intentionally cool/dark for contrast with content area.

**Warning signs:** Forgetting sidebar tokens in the rgba() sweep leaves inconsistent color space usage (oklch everywhere except sidebar).

---

## Code Examples

### Mapping Light Mode Surface Tokens to Primitives

```css
/* :root — light mode surface tokens via primitive reference */
--color-bg:             var(--stone-200);  /* oklch(0.922 0.013 95) */
--color-bg-subtle:      var(--stone-300);  /* oklch(0.893 0.017 90) */
--color-surface-alt:    var(--stone-300);  /* oklch(0.893 0.017 90) */
--color-surface:        var(--stone-50);   /* oklch(0.969 0.006 95) */
--color-surface-raised: oklch(1 0 0);      /* white — no primitive, direct oklch */
```

### Mapping Text Tokens to Primitives

```css
--color-text:          var(--stone-700);  /* oklch(0.530 0.025 80) */
--color-text-dark:     var(--stone-900);  /* oklch(0.180 0.012 75) */
--color-text-secondary: var(--stone-900); /* oklch(0.180 0.012 75) */
--color-text-muted:    var(--stone-600);  /* oklch(0.648 0.030 82) */
--color-text-light:    var(--stone-500);  /* oklch(0.760 0.028 85) */
--color-text-inverse:  oklch(1 0 0);      /* white */
--color-text-disabled: var(--stone-400);  /* oklch(0.833 0.022 88) */
```

### Mapping Border Tokens to Primitives

```css
--color-border:        var(--stone-400);  /* oklch(0.833 0.022 88) */
--color-border-subtle: oklch(0.912 0.015 92);  /* between stone-300 and stone-200 */
--color-border-strong: var(--stone-500);  /* oklch(0.760 0.028 85) */
--color-border-dash:   var(--stone-500);  /* oklch(0.760 0.028 85) */
--color-border-focus:  oklch(0.520 0.195 265 / 0.50);
```

### Converting rgba() to oklch Alpha

```css
/* Transparency tokens — oklch alpha syntax */
--color-surface-overlay: oklch(0.969 0.006 95 / 0.95);  /* was rgba(250,250,247,0.95) */
--color-glass:           oklch(0.969 0.006 95 / 0.95);
--color-primary-muted:   oklch(0.520 0.195 265 / 0.12); /* was rgba(22,80,224,0.12) */
--color-primary-glow:    oklch(0.520 0.195 265 / 0.12);
--color-backdrop:        oklch(0 0 0 / 0.50);            /* was rgba(0,0,0,0.5) */
--color-overlay-tint:    oklch(0 0 0 / 0.04);            /* was rgba(0,0,0,0.04) */
```

### Dark Mode Surface Warming Target Values

```css
[data-theme="dark"] {
  /* Warmed surfaces — hue 75-80 instead of previous hue ~260 */
  --color-bg:             oklch(0.090 0.008 78);
  --color-bg-subtle:      oklch(0.130 0.010 78);
  --color-surface:        oklch(0.115 0.009 78);
  --color-surface-alt:    oklch(0.130 0.010 78);
  --color-surface-raised: oklch(0.145 0.011 78);

  /* Dark mode text — keep cool-white tint for readability against dark bg */
  --color-text:          oklch(0.640 0.018 265);  /* was #7A8499 — slightly cooler for contrast */
  --color-text-dark:     oklch(0.908 0.015 265);  /* was #ECF0FA */
  --color-text-secondary: oklch(0.908 0.015 265);

  /* Dark mode alpha tokens */
  --color-surface-overlay: oklch(0.115 0.009 78 / 0.96);
  --color-glass:           oklch(0.115 0.009 78 / 0.96);
  --color-backdrop:        oklch(0 0 0 / 0.70);
  --color-overlay-tint:    oklch(1 0 0 / 0.03);
}
```

### Verifying Migration Completeness

```bash
# Must return 0 after migration
grep -c "color-mix(in srgb" public/assets/css/design-system.css

# Must return 0 after critical-tokens sync
grep -l "#EDECE6\|#FAFAF7\|#151510\|#0B0D10\|#141820\|#ECF0FA" public/*.htmx.html | wc -l

# Spot-check: remaining rgba() should be in shadow-color and sidebar only
grep "rgba(" public/assets/css/design-system.css | grep "^\s*--" | grep -v "shadow\|sidebar"
```

---

## State of the Art

| Old Approach | Current Approach | Impact |
|--------------|-----------------|--------|
| `rgba(R, G, B, alpha)` for transparency | `oklch(L C H / alpha)` | Single color space; no cross-space conversion artifacts |
| `color-mix(in srgb)` for blends | `color-mix(in oklch)` | Avoids grey desaturation at midpoints; perceptually uniform |
| Hardcoded hex in semantic tokens | `var(--primitive)` references | Palette changes propagate automatically |
| Manually maintained hover hex values | `color-mix(in oklch, base, black X%)` | Derived from base; self-corrects if base changes |

**Deprecated/outdated:**
- `rgba()` in semantic color tokens: replaced by oklch alpha syntax
- `color-mix(in srgb, ...)`: replaced by `color-mix(in oklch, ...)` project-wide

---

## Open Questions

1. **Exact oklch values for dark mode text tokens**
   - What we know: Current `--color-text` in dark mode is `#7A8499` (cool gray, hue ~260). The design intent is warm surfaces with legible text.
   - What's unclear: Should dark mode text retain the cool hue (for contrast against warm surfaces) or also shift warm?
   - Recommendation: Keep dark mode text at hue ~265 — warm text on warm dark surface reduces contrast. Cool-tinted text on warm-dark bg is intentional and matches best practice for dark UI legibility.

2. **Chroma calibration for intermediate steps**
   - What we know: Stone primitives go 0.006 to 0.030 chroma across the ramp.
   - What's unclear: `--color-border-subtle` (#DEDAD0) has no exact primitive match — it falls between stone-200 and stone-300.
   - Recommendation: Compute as `oklch(0.912 0.015 92)` directly. Document this as a "half-step" token that doesn't map to a named primitive.

3. **Success criterion 3 scope (COLOR-03 accent sparsity)**
   - What we know: `.page-title .bar` (line 1322) uses `var(--color-primary)` decoratively. `.logo-mark` (line 900) uses `var(--color-primary)` as a background.
   - What's unclear: Whether migrating `.page-title .bar` to `var(--color-text-muted)` is COLOR-03 scope or Phase 84 scope. Phase 82 scope is @layer base only, but `.page-title .bar` is a component rule in @layer components.
   - Recommendation: COLOR-03 token scope is in @layer base (defining `--color-accent` correctly). Component CSS usages are Phase 84 scope. The token `--color-accent` currently aliases `--color-purple`; its sparsity is enforced by not using it in component tokens within @layer base.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit (tests/Unit/) — no CSS-specific test framework |
| Config file | None applicable for CSS |
| Quick run command | `grep -c "color-mix(in srgb" public/assets/css/design-system.css` (should return 0) |
| Full suite command | `grep -rn "color-mix(in srgb" public/assets/css/` (should return no results) |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| COLOR-01 | No hex values in semantic token declarations in :root | grep audit | `grep -n "^\s\+--color-.*: #" public/assets/css/design-system.css \| awk 'NR>=275&&NR<=585'` | ✅ (grep, no file needed) |
| COLOR-02 | Dark mode surface tokens use hue 75-80 range | grep audit | `grep -A2 "data-theme" public/assets/css/design-system.css \| grep "color-bg\|color-surface" \| grep "oklch"` | ✅ (grep) |
| COLOR-03 | No `rgba()` in semantic token declarations | grep audit | `grep -n "rgba(" public/assets/css/design-system.css \| grep "^\s*--color-" \| grep -v "shadow\|sidebar"` | ✅ (grep) |
| COLOR-04 | Derived hover/shade tokens use color-mix(in oklch) | grep audit | `grep "color-primary-hover\|color-success-hover\|color-danger-hover" public/assets/css/design-system.css \| grep "oklch"` | ✅ (grep) |
| COLOR-05 | Zero `color-mix(in srgb` in entire file | grep audit | `grep -c "color-mix(in srgb" public/assets/css/design-system.css` returns 0 | ✅ (success criterion 4) |

**Critical-tokens sync test:**
```bash
# Must return empty — no old hex values remaining in critical-token blocks
grep -l "#EDECE6\|#FAFAF7\|#0B0D10\|#141820" public/*.htmx.html
```

### Sampling Rate

- **Per task commit:** Run the grep audit commands above (< 5 seconds total)
- **Per wave merge:** Full grep suite across all CSS files
- **Phase gate:** All grep audits pass + visual browser smoke test of dashboard in light and dark mode before `/gsd:verify-work`

### Wave 0 Gaps

None — no test framework installation needed. All verification is grep-based audit of CSS files.

---

## Sources

### Primary (HIGH confidence)
- Direct inspection of `/home/user/gestion_votes_php/public/assets/css/design-system.css` (5,258 lines) — full token inventory, color-mix call count (42), structure verified
- Direct inspection of all 21 `/home/user/gestion_votes_php/public/*.htmx.html` files — critical-tokens block structure confirmed identical across all files
- `82-CONTEXT.md` — all implementation decisions locked

### Secondary (MEDIUM confidence)
- oklch browser support 96%+ — consistent with MDN Color 4 compatibility data and Can I Use
- `color-mix(in oklch)` avoiding grey midpoint artifacts — documented CSS Color 5 behavior, consistent with perceptual uniformity property of oklch

### Tertiary (LOW confidence — not needed, decisions already locked)
- N/A — no open ecosystem questions remain

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no external dependencies, pure native CSS
- Architecture: HIGH — verified by direct inspection of actual file
- Pitfalls: HIGH — discovered from direct code analysis (stale srgb, missing dark re-declarations, critical-token mismatch)
- Token value mapping: MEDIUM — oklch values for dark mode warm shift are derived from the stated hue target (75-80) and chroma guidance (0.006-0.030); exact lightness levels should be calibrated visually

**Research date:** 2026-04-03
**Valid until:** This research is tied to the current state of design-system.css. Valid until any change to the primitive palette (none planned in v10.0).
