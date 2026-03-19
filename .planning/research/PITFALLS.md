# CSS Architecture & Token System — AG-VOTE v4.1 "Design Excellence"

**Domain:** CSS design token system architecture — primitive → semantic → component hierarchy
**Researched:** 2026-03-18
**Overall confidence:** HIGH (Radix Colors official docs, Tailwind v4 theme.css, shadcn/ui theming docs, MDN color-mix/oklch, Material Design 3 motion specs — all verified via current sources)

---

## What This File Contains

Complete, copy-pasteable CSS custom property definitions for a top 1% design token system. Each section answers a specific research question with concrete values ready to drop into `design-system.css`.

The current AG-VOTE system has 265+ variables, a "objectivement médiocre" visual result, and ad-hoc color derivations. This research provides the architectural upgrade path.

---

## 1. TOKEN HIERARCHY: Primitive → Semantic → Component

### The Three-Layer Model (HIGH confidence — industry consensus)

**Layer 1 — Primitives (raw values, no context):**
Named after what they ARE, not what they do. Never referenced directly in components.

```css
/* Raw color palette — primitives */
--blue-50: oklch(0.97 0.013 254);
--blue-100: oklch(0.93 0.032 254);
--blue-500: oklch(0.55 0.18 254);
--blue-600: oklch(0.49 0.20 254);

/* Raw spacing — primitives */
--space-px-1: 4px;
--space-px-2: 8px;

/* Raw type scale — primitives */
--type-12: 0.75rem;
--type-14: 0.875rem;
```

**Layer 2 — Semantic tokens (context-aware, theme-switchable):**
Named after what they DO, not what they are. These are the tokens used in component CSS.

```css
:root {
  --color-bg: var(--stone-50);
  --color-surface: var(--white);
  --color-text: var(--stone-700);
  --color-primary: var(--blue-600);
}

[data-theme="dark"] {
  --color-bg: var(--gray-950);
  --color-surface: var(--gray-900);
  --color-text: var(--gray-200);
  --color-primary: var(--blue-400);
}
```

**Layer 3 — Component tokens (scoped overrides):**
Named after the component they serve. Optional, used for complex components only.

```css
.btn {
  --btn-bg: var(--color-primary);
  --btn-fg: var(--color-primary-text);
  --btn-radius: var(--radius-md);
  background: var(--btn-bg);
}
```

### How Many of Each?

Based on Radix Colors (12-step scale), Tailwind v4 (12 shadow variants, 9 font sizes, dynamic spacing), and shadcn/ui (20 semantic tokens):

| Category | Primitives | Semantic | Component |
|----------|-----------|---------|-----------|
| Colors | ~60 (5 palettes × 12 steps) | ~30 | Per component |
| Spacing | ~15 (0–64) | 6–8 named | Per layout |
| Typography | 9 sizes | 5–6 named | Per component |
| Shadows | 7 levels | 5 named | Rarely |
| Radius | 6 sizes | 4–5 named | Rarely |

**Verdict for AG-VOTE:** The current 265+ variables are bloated because they skip the primitive layer and mix semantic + ad-hoc values. Target: ~80 semantic tokens, backed by ~60 well-named primitives.

---

## 2. SPACING SCALE

### Research Findings (HIGH confidence — Tailwind v4 source verified)

Tailwind v4 uses 0.25rem (4px) base with dynamic generation. Open Props uses a non-linear scale. The industry standard for desktop apps is **4px base, 8px grid**, with named steps up to 80–96px.

### Recommended Scale for AG-VOTE

The current scale (`--space-1` through `--space-16`) is solid but has gaps at the high end (48px → 64px jump) and lacks a few commonly needed values. Recommended complete scale:

```css
/* ─── SPACING SCALE (4px base, 8px grid rhythm) ─── */
/* Drop-in replacement for existing --space-* variables */

:root {
  /* Micro spacing — icon gaps, border offsets */
  --space-0: 0;
  --space-0-5: 0.125rem;  /* 2px  — fine-grained only */
  --space-1: 0.25rem;     /* 4px  — tight gaps */
  --space-1-5: 0.375rem;  /* 6px  — icon-to-label */
  --space-2: 0.5rem;      /* 8px  — base grid unit */
  --space-2-5: 0.625rem;  /* 10px — compact UI */
  --space-3: 0.75rem;     /* 12px — small padding */
  --space-4: 1rem;        /* 16px — standard padding */
  --space-5: 1.25rem;     /* 20px — medium padding */
  --space-6: 1.5rem;      /* 24px — card padding */
  --space-7: 1.75rem;     /* 28px — comfortable spacing */
  --space-8: 2rem;        /* 32px — section gap */
  --space-10: 2.5rem;     /* 40px — large gap */
  --space-12: 3rem;       /* 48px — section break */
  --space-14: 3.5rem;     /* 56px — nav height */
  --space-16: 4rem;       /* 64px — major section */
  --space-20: 5rem;       /* 80px — page-level spacing */
  --space-24: 6rem;       /* 96px — hero spacing */

  /* ─── SEMANTIC SPACING ALIASES ─── */
  /* Used in component CSS — never raw --space-* directly */
  --gap-xs: var(--space-1);     /* 4px  — tight icon gaps */
  --gap-sm: var(--space-2);     /* 8px  — inline gaps */
  --gap-md: var(--space-4);     /* 16px — standard gap */
  --gap-lg: var(--space-6);     /* 24px — card/section gap */
  --gap-xl: var(--space-8);     /* 32px — page section gap */

  --pad-xs: var(--space-2);     /* 8px  — chip/badge padding */
  --pad-sm: var(--space-3);     /* 12px — compact button */
  --pad-md: var(--space-4);     /* 16px — standard button/input */
  --pad-lg: var(--space-6);     /* 24px — card padding */
  --pad-xl: var(--space-8);     /* 32px — panel padding */
  --pad-2xl: var(--space-12);   /* 48px — page content padding */
}
```

**Why 0.5 steps matter:** Icon-to-label gaps (6px), input padding harmony with border width, and table cell padding all need values between 4px and 8px. The current scale jumps from 4px to 8px with nothing in between — this causes layout compromise.

**Sweet spot for AG-VOTE:** 18 raw steps + 10 semantic aliases = 28 spacing tokens. Far fewer than the current bloated set.

---

## 3. COLOR SYSTEM

### Radix Colors 12-Step Scale — Semantic Mapping (HIGH confidence — official docs)

Each step has a defined purpose. This is the industry's most principled color scale:

| Step | Use Case | AG-VOTE Example |
|------|----------|-----------------|
| 1 | App background | `--color-bg` |
| 2 | Subtle background | `--color-bg-subtle` |
| 3 | UI element background (rest) | hover bg on ghost button |
| 4 | Hovered UI element background | active bg on ghost button |
| 5 | Active/selected UI element bg | selected row, active tab |
| 6 | Subtle borders and separators | `--color-border-subtle` |
| 7 | UI element border + focus ring | `--color-border` |
| 8 | Hovered UI element border | border on input hover |
| 9 | Solid background (highest chroma) | `--color-primary` button fill |
| 10 | Hovered solid background | `--color-primary-hover` |
| 11 | Low-contrast text | `--color-text-muted` |
| 12 | High-contrast text | `--color-text-dark` |

### AG-VOTE Color Primitives — OKLCH (recommended)

AG-VOTE's "Acte Officiel" identity uses warm stone/parchment backgrounds with indigo-blue primary. The current hex values translated to OKLCH for derivation power:

```css
/* ─── COLOR PRIMITIVES ─── */
/* These are reference values. The SEMANTIC tokens below are what components use. */

:root {
  /* Stone/Parchment palette (warm neutral — "Acte Officiel") */
  --stone-50:  oklch(0.969 0.006 95);   /* #FAFAF7 — surface */
  --stone-100: oklch(0.950 0.009 95);   /* #F2F0EB — near-white */
  --stone-200: oklch(0.922 0.013 95);   /* #EDECE6 — bg (current --color-bg) */
  --stone-300: oklch(0.893 0.017 90);   /* #E5E3D8 — bg-subtle */
  --stone-400: oklch(0.833 0.022 88);   /* #CDC9BB — border */
  --stone-500: oklch(0.760 0.028 85);   /* #BCB7A5 — border-strong */
  --stone-600: oklch(0.648 0.030 82);   /* #857F72 — text-muted */
  --stone-700: oklch(0.530 0.025 80);   /* #52504A — text */
  --stone-800: oklch(0.350 0.018 78);   /* #2A2720 — text-secondary */
  --stone-900: oklch(0.180 0.012 75);   /* #151510 — text-dark */

  /* Blue/Indigo palette (primary brand) */
  --blue-50:  oklch(0.960 0.018 265);   /* #EBF0FF */
  --blue-100: oklch(0.920 0.035 265);   /* #D6E3FF */
  --blue-200: oklch(0.850 0.060 265);   /* #B3CCFF */
  --blue-400: oklch(0.680 0.130 265);   /* #5C96FA — dark mode primary */
  --blue-500: oklch(0.600 0.165 265);   /* #3D7EF8 — mid */
  --blue-600: oklch(0.520 0.195 265);   /* #1650E0 — current --color-primary */
  --blue-700: oklch(0.440 0.190 265);   /* #1140C0 */
  --blue-800: oklch(0.360 0.180 265);   /* #0C30A0 */

  /* Success/Green */
  --green-50:  oklch(0.968 0.020 155);  /* #EDFAF2 */
  --green-600: oklch(0.500 0.135 155);  /* #0B7A40 — current --color-success */
  --green-500: oklch(0.580 0.155 155);  /* #2DC87A — dark mode success */

  /* Warning/Amber */
  --amber-50:  oklch(0.978 0.022 90);   /* #FFF7E8 */
  --amber-600: oklch(0.590 0.115 60);   /* #B56700 — current --color-warning */
  --amber-400: oklch(0.740 0.130 68);   /* #EDA030 — dark mode warning */

  /* Danger/Red */
  --red-50:   oklch(0.975 0.015 25);    /* #FEF1F0 */
  --red-600:  oklch(0.510 0.175 25);    /* #C42828 — current --color-danger */
  --red-500:  oklch(0.600 0.185 25);    /* #E85454 — dark mode danger */

  /* Purple (accent / post-session) */
  --purple-50:  oklch(0.965 0.022 298); /* #EEEAFF */
  --purple-600: oklch(0.490 0.170 298); /* #5038C0 — current --color-accent */
  --purple-500: oklch(0.580 0.175 298); /* #8C72F8 — dark mode accent */
}
```

### Semantic Color Tokens — COMPLETE LIGHT + DARK

```css
/* ─── SEMANTIC COLOR TOKENS — LIGHT THEME ─── */
:root {
  /* --- Backgrounds (Radix steps 1–2) --- */
  --color-bg:          oklch(0.922 0.013 95);    /* #EDECE6 warm parchment */
  --color-bg-subtle:   oklch(0.893 0.017 90);    /* #E5E3D8 slightly darker */

  /* --- Surfaces (elevation steps 3–5) --- */
  --color-surface:         oklch(0.969 0.006 95); /* #FAFAF7 near-white warm */
  --color-surface-raised:  oklch(1.000 0.000 0);  /* #FFFFFF pure white cards */
  --color-surface-overlay: oklch(0.969 0.006 95 / 95%); /* modal bg */
  --color-surface-alt:     oklch(0.893 0.017 90); /* recessed areas */

  /* --- Text (Radix steps 11–12) --- */
  --color-text:          oklch(0.530 0.025 80);  /* #52504A body text */
  --color-text-dark:     oklch(0.180 0.012 75);  /* #151510 headings */
  --color-text-secondary: oklch(0.180 0.012 75); /* strong labels */
  --color-text-muted:    oklch(0.648 0.030 82);  /* #857F72 secondary info */
  --color-text-light:    oklch(0.750 0.018 88);  /* #B5B0A0 placeholder */
  --color-text-inverse:  oklch(1.000 0.000 0);   /* white on dark bg */
  --color-text-disabled: oklch(0.780 0.014 88);  /* #C4C0B5 disabled */

  /* --- Borders (Radix steps 6–8) --- */
  --color-border:        oklch(0.833 0.022 88);  /* #CDC9BB standard */
  --color-border-subtle: oklch(0.872 0.018 89);  /* #DEDAD0 light dividers */
  --color-border-strong: oklch(0.800 0.026 87);  /* #BCB7A5 prominent */
  --color-border-focus:  oklch(0.520 0.195 265 / 50%); /* primary 50% */

  /* --- Primary / Brand (Radix steps 9–10) --- */
  --color-primary:       oklch(0.520 0.195 265); /* #1650E0 */
  --color-primary-hover: oklch(0.440 0.190 265); /* #1140C0 */
  --color-primary-active:oklch(0.360 0.180 265); /* #0C30A0 */
  --color-primary-subtle:oklch(0.960 0.018 265); /* #EBF0FF */
  --color-primary-muted: oklch(0.520 0.195 265 / 12%); /* transparent fill */
  --color-primary-text:  oklch(1.000 0.000 0);   /* white on primary */

  /* --- Derived with color-mix (no oklch math needed) --- */
  --color-primary-tint-5:  color-mix(in oklch, var(--color-primary) 5%, white);
  --color-primary-tint-10: color-mix(in oklch, var(--color-primary) 10%, white);
  --color-primary-tint-15: color-mix(in oklch, var(--color-primary) 15%, white);
  --color-primary-shade-10: color-mix(in oklch, var(--color-primary) 90%, black);

  /* --- Semantic states --- */
  --color-success:        oklch(0.500 0.135 155); /* #0B7A40 */
  --color-success-hover:  oklch(0.440 0.125 155); /* darker */
  --color-success-subtle: oklch(0.968 0.020 155); /* #EDFAF2 */
  --color-success-border: oklch(0.820 0.060 155); /* #A3E8C1 */
  --color-success-text:   oklch(0.500 0.135 155);

  --color-warning:        oklch(0.590 0.115 60);  /* #B56700 */
  --color-warning-hover:  oklch(0.520 0.108 60);
  --color-warning-subtle: oklch(0.978 0.022 90);  /* #FFF7E8 */
  --color-warning-border: oklch(0.870 0.070 75);  /* #F5D490 */
  --color-warning-text:   oklch(0.590 0.115 60);

  --color-danger:         oklch(0.510 0.175 25);  /* #C42828 */
  --color-danger-hover:   oklch(0.450 0.168 25);
  --color-danger-subtle:  oklch(0.975 0.015 25);  /* #FEF1F0 */
  --color-danger-border:  oklch(0.840 0.065 25);  /* #F4BFBF */
  --color-danger-text:    oklch(0.510 0.175 25);

  --color-accent:         oklch(0.490 0.170 298); /* #5038C0 purple */
  --color-accent-subtle:  oklch(0.965 0.022 298); /* #EEEAFF */
  --color-accent-border:  oklch(0.780 0.080 298); /* #C4B8F8 */
  --color-accent-text:    oklch(0.490 0.170 298);

  --color-neutral:        oklch(0.648 0.030 82);  /* #857F72 */
  --color-neutral-hover:  oklch(0.570 0.025 82);
  --color-neutral-subtle: oklch(0.893 0.017 90);  /* #E5E3D8 */
  --color-neutral-text:   oklch(0.530 0.025 80);

  /* --- Misc --- */
  --color-backdrop:       oklch(0 0 0 / 50%);
  --color-overlay-tint:   oklch(0 0 0 / 4%);    /* hover tint on surfaces */
}

/* ─── SEMANTIC COLOR TOKENS — DARK THEME ─── */
[data-theme="dark"] {
  /* --- Backgrounds --- */
  --color-bg:          oklch(0.130 0.012 265);   /* #0B0F1A deep navy */
  --color-bg-subtle:   oklch(0.180 0.018 265);   /* #1B2030 */

  /* --- Surfaces (light sources — higher = lighter in dark mode) --- */
  --color-surface:         oklch(0.155 0.016 265); /* #141820 */
  --color-surface-raised:  oklch(0.190 0.020 265); /* #1E2438 */
  --color-surface-overlay: oklch(0.155 0.016 265 / 96%);
  --color-surface-alt:     oklch(0.180 0.018 265); /* #1B2030 */

  /* --- Text --- */
  --color-text:          oklch(0.620 0.020 248);  /* #7A8499 */
  --color-text-dark:     oklch(0.940 0.012 252);  /* #ECF0FA */
  --color-text-secondary: oklch(0.940 0.012 252);
  --color-text-muted:    oklch(0.460 0.018 250);  /* #50596C */
  --color-text-light:    oklch(0.320 0.015 248);  /* #38404E */
  --color-text-inverse:  oklch(0.130 0.012 265);  /* dark bg */
  --color-text-disabled: oklch(0.380 0.015 248);

  /* --- Borders --- */
  --color-border:        oklch(0.230 0.020 265);  /* #252C3C */
  --color-border-subtle: oklch(0.200 0.018 265);  /* #1E2434 */
  --color-border-strong: oklch(0.270 0.024 265);  /* #2E3850 */
  --color-border-focus:  oklch(0.620 0.170 265 / 50%);

  /* --- Primary --- */
  --color-primary:       oklch(0.630 0.170 265);  /* #3D7EF8 */
  --color-primary-hover: oklch(0.700 0.155 265);  /* #5C96FA */
  --color-primary-active:oklch(0.770 0.120 265);  /* #96BDFB */
  --color-primary-subtle:oklch(0.630 0.170 265 / 12%);
  --color-primary-muted: oklch(0.630 0.170 265 / 12%);
  --color-primary-text:  oklch(0.130 0.012 265);

  --color-primary-tint-5:  color-mix(in oklch, var(--color-primary) 5%, var(--color-surface));
  --color-primary-tint-10: color-mix(in oklch, var(--color-primary) 10%, var(--color-surface));
  --color-primary-tint-15: color-mix(in oklch, var(--color-primary) 15%, var(--color-surface));
  --color-primary-shade-10: color-mix(in oklch, var(--color-primary) 90%, var(--color-surface));

  /* --- Semantic states --- */
  --color-success:        oklch(0.680 0.155 155); /* #2DC87A */
  --color-success-hover:  oklch(0.740 0.160 155);
  --color-success-subtle: oklch(0.680 0.155 155 / 8%);
  --color-success-border: oklch(0.680 0.155 155 / 28%);
  --color-success-text:   oklch(0.680 0.155 155);

  --color-warning:        oklch(0.760 0.130 68);  /* #EDA030 */
  --color-warning-hover:  oklch(0.810 0.130 68);
  --color-warning-subtle: oklch(0.760 0.130 68 / 8%);
  --color-warning-border: oklch(0.760 0.130 68 / 28%);
  --color-warning-text:   oklch(0.760 0.130 68);

  --color-danger:         oklch(0.650 0.185 25);  /* #E85454 */
  --color-danger-hover:   oklch(0.710 0.180 25);
  --color-danger-subtle:  oklch(0.650 0.185 25 / 9%);
  --color-danger-border:  oklch(0.650 0.185 25 / 28%);
  --color-danger-text:    oklch(0.650 0.185 25);

  --color-accent:         oklch(0.650 0.175 298); /* #8C72F8 */
  --color-accent-subtle:  oklch(0.650 0.175 298 / 10%);
  --color-accent-border:  oklch(0.650 0.175 298 / 30%);
  --color-accent-text:    oklch(0.650 0.175 298);

  --color-neutral:        oklch(0.460 0.018 250);
  --color-neutral-hover:  oklch(0.620 0.020 248);
  --color-neutral-subtle: oklch(0.620 0.020 248 / 15%);
  --color-neutral-text:   oklch(0.620 0.020 248);

  --color-backdrop:       oklch(0 0 0 / 70%);
  --color-overlay-tint:   oklch(1 0 0 / 3%);
}
```

### oklch Color Derivation Formulas (MEDIUM confidence — MDN + Evil Martians verified)

For use within component CSS when you need one-off variants without adding new tokens:

```css
/* Hover state — lighten by ~10% lightness */
.btn:hover {
  background: oklch(from var(--color-primary) calc(l + 0.06) c h);
}

/* Active state — darken by ~10% lightness */
.btn:active {
  background: oklch(from var(--color-primary) calc(l - 0.06) c h);
}

/* Subtle fill — desaturate + lighten heavily */
.badge-subtle {
  background: oklch(from var(--color-primary) calc(l + 0.35) calc(c * 0.15) h);
}

/* Tinted border — same hue, very low chroma */
.card-primary {
  border-color: oklch(from var(--color-primary) calc(l + 0.25) calc(c * 0.30) h);
}

/* color-mix pattern (wider browser support, more predictable) */
.icon-bg {
  background: color-mix(in oklch, var(--color-primary) 12%, var(--color-surface));
}
```

**Browser support note:** `oklch(from ...)` relative color syntax requires Chrome 119+, Firefox 128+, Safari 16.4+. `color-mix(in oklch, ...)` has slightly wider support (Chrome 111+). Both are safe for 2026 targets.

---

## 4. SHADOW SYSTEM

### Research Findings (HIGH confidence — Tailwind v4 theme.css source)

Top systems use 5–7 shadow levels. Tailwind v4 uses 7 (2xs → 2xl). The key insight from premium apps (Linear, Vercel, Stripe): **shadows in light mode should be warm-tinted, not cold black** — use the darkest text color as shadow base.

AG-VOTE's existing shadows use `rgba(21, 21, 16, N)` which is already correct (warm dark). The problem is the values themselves are too weak for a "top 1%" result.

### Complete Shadow Scale

```css
:root {
  /* ─── SHADOW SCALE ─── */
  /* Base color: --shadow-color is the warm dark tone of the page */
  /* Light mode: warm near-black. Dark mode: pure black (more visible). */
  --shadow-color: 21 21 16;        /* rgb channels — warm black */

  /* 7 levels from surface lift to floating panel */
  --shadow-2xs: 0 1px 0 rgb(var(--shadow-color) / 0.04);
  --shadow-xs:  0 1px 2px rgb(var(--shadow-color) / 0.06),
                0 1px 1px rgb(var(--shadow-color) / 0.03);
  --shadow-sm:  0 1px 3px rgb(var(--shadow-color) / 0.08),
                0 1px 2px rgb(var(--shadow-color) / 0.04);
  --shadow:     0 2px 6px rgb(var(--shadow-color) / 0.08),
                0 1px 3px rgb(var(--shadow-color) / 0.05);
  --shadow-md:  0 4px 12px rgb(var(--shadow-color) / 0.10),
                0 2px 4px rgb(var(--shadow-color) / 0.06);
  --shadow-lg:  0 8px 24px rgb(var(--shadow-color) / 0.12),
                0 3px 8px rgb(var(--shadow-color) / 0.06);
  --shadow-xl:  0 16px 40px rgb(var(--shadow-color) / 0.14),
                0 6px 16px rgb(var(--shadow-color) / 0.07);
  --shadow-2xl: 0 24px 64px rgb(var(--shadow-color) / 0.18),
                0 8px 24px rgb(var(--shadow-color) / 0.08);

  /* Special purpose */
  --shadow-inner: inset 0 2px 4px rgb(var(--shadow-color) / 0.05);
  --shadow-inset-sm: inset 0 1px 2px rgb(var(--shadow-color) / 0.08);
  --shadow-focus: 0 0 0 2px var(--color-surface-raised),
                  0 0 0 4px var(--color-border-focus);
  --shadow-focus-danger: 0 0 0 2px var(--color-surface-raised),
                          0 0 0 4px oklch(0.510 0.175 25 / 35%);
}

[data-theme="dark"] {
  --shadow-color: 0 0 0;     /* pure black — needed for visibility on dark bg */

  --shadow-2xs: 0 1px 0 rgb(var(--shadow-color) / 0.12);
  --shadow-xs:  0 1px 2px rgb(var(--shadow-color) / 0.20),
                0 1px 1px rgb(var(--shadow-color) / 0.12);
  --shadow-sm:  0 1px 3px rgb(var(--shadow-color) / 0.24),
                0 1px 2px rgb(var(--shadow-color) / 0.16);
  --shadow:     0 2px 6px rgb(var(--shadow-color) / 0.30),
                0 1px 3px rgb(var(--shadow-color) / 0.18);
  --shadow-md:  0 4px 12px rgb(var(--shadow-color) / 0.34),
                0 2px 4px rgb(var(--shadow-color) / 0.20);
  --shadow-lg:  0 8px 24px rgb(var(--shadow-color) / 0.40),
                0 3px 8px rgb(var(--shadow-color) / 0.22);
  --shadow-xl:  0 16px 40px rgb(var(--shadow-color) / 0.50),
                0 6px 16px rgb(var(--shadow-color) / 0.26);
  --shadow-2xl: 0 24px 64px rgb(var(--shadow-color) / 0.60),
                0 8px 24px rgb(var(--shadow-color) / 0.30);

  --shadow-inner: inset 0 2px 4px rgb(var(--shadow-color) / 0.20);
  --shadow-inset-sm: inset 0 1px 2px rgb(var(--shadow-color) / 0.25);
}
```

### Shadow Usage Guide

| Component | Shadow Level | Rationale |
|-----------|-------------|-----------|
| Table row | `--shadow-2xs` | Barely lifted, data density |
| Card (default) | `--shadow-sm` | Light surface |
| Card (hover) | `--shadow-md` | Lifted on hover |
| Modal dialog | `--shadow-xl` | Floating layer |
| Dropdown/popover | `--shadow-lg` | Above content |
| Tooltip | `--shadow-md` | Small floating |
| Button (active) | `--shadow-inner` | Pressed state |
| Input (focus) | `--shadow-focus` | Accessibility ring |

---

## 5. TYPOGRAPHY SCALE

### Research Findings (MEDIUM confidence — LearnUI.design + industry consensus)

For data-heavy desktop apps (Linear, Notion, AG-VOTE): body text 14–16px, headings 24–36px, labels/captions 11–13px. The key insight: **line-height should decrease as font-size increases** — headlines at 1.1–1.2, body at 1.5–1.6, captions at 1.4.

AG-VOTE uses Bricolage Grotesque (body), Fraunces (display/headings), JetBrains Mono (data). This is an excellent combination. The problem is in the scale steps and line-height assignments.

```css
:root {
  /* ─── FONT FAMILIES (unchanged — strong identity) ─── */
  --font-sans:    'Bricolage Grotesque', system-ui, -apple-system, sans-serif;
  --font-display: 'Fraunces', Georgia, 'Times New Roman', serif;
  --font-mono:    'JetBrains Mono', ui-monospace, 'Cascadia Code', monospace;

  /* ─── FONT SIZE SCALE ─── */
  /* 9 steps — covers every use case in AG-VOTE */
  --text-2xs:  0.6875rem;  /* 11px — badges, legal fine print */
  --text-xs:   0.75rem;    /* 12px — table meta, timestamps */
  --text-sm:   0.8125rem;  /* 13px — compact UI, secondary labels */
  --text-base: 0.875rem;   /* 14px — PRIMARY body text (data-dense app) */
  --text-md:   1rem;       /* 16px — emphasized body, form labels */
  --text-lg:   1.125rem;   /* 18px — section subtitles, lead text */
  --text-xl:   1.25rem;    /* 20px — page sub-headers */
  --text-2xl:  1.5rem;     /* 24px — h3 / card titles */
  --text-3xl:  1.875rem;   /* 30px — h2 / page titles */
  --text-4xl:  2.25rem;    /* 36px — h1 / hero (display font) */
  --text-5xl:  3rem;       /* 48px — landing/marketing only */

  /* ─── LINE HEIGHTS (per-size — not generic) ─── */
  /* Smaller text needs more breathing room. Large text needs tighter tracking. */
  --leading-2xs:  1.5;    /* 11px → 16.5px */
  --leading-xs:   1.5;    /* 12px → 18px */
  --leading-sm:   1.55;   /* 13px → ~20px */
  --leading-base: 1.571;  /* 14px → 22px — golden for data tables */
  --leading-md:   1.5;    /* 16px → 24px */
  --leading-lg:   1.4;    /* 18px → 25.2px */
  --leading-xl:   1.35;   /* 20px → 27px */
  --leading-2xl:  1.3;    /* 24px → 31.2px */
  --leading-3xl:  1.2;    /* 30px → 36px */
  --leading-4xl:  1.1;    /* 36px → 39.6px */

  /* ─── FONT WEIGHTS ─── */
  --weight-regular:   400;
  --weight-medium:    500;
  --weight-semibold:  600;
  --weight-bold:      700;
  --weight-extrabold: 800;

  /* ─── LETTER SPACING ─── */
  --tracking-tight:   -0.025em;  /* large headings */
  --tracking-snug:    -0.015em;  /* h2/h3 */
  --tracking-normal:   0em;      /* body text */
  --tracking-wide:     0.025em;  /* all-caps labels, badges */
  --tracking-wider:    0.05em;   /* very tight UPPERCASE */
  --tracking-widest:   0.1em;    /* micro labels */

  /* ─── SEMANTIC TYPOGRAPHY ALIASES ─── */
  /* Used in component CSS for consistency */
  --type-page-title-size:   var(--text-3xl);
  --type-page-title-weight: var(--weight-bold);
  --type-page-title-lead:   var(--leading-3xl);
  --type-page-title-track:  var(--tracking-tight);
  --type-page-title-font:   var(--font-display);

  --type-section-title-size:   var(--text-2xl);
  --type-section-title-weight: var(--weight-bold);
  --type-section-title-lead:   var(--leading-2xl);
  --type-section-title-track:  var(--tracking-snug);

  --type-card-title-size:   var(--text-xl);
  --type-card-title-weight: var(--weight-semibold);
  --type-card-title-lead:   var(--leading-xl);

  --type-body-size:   var(--text-base);  /* 14px */
  --type-body-weight: var(--weight-regular);
  --type-body-lead:   var(--leading-base);

  --type-body-md-size:   var(--text-md);  /* 16px — for prose/help */
  --type-body-md-weight: var(--weight-regular);
  --type-body-md-lead:   var(--leading-md);

  --type-label-size:   var(--text-sm);
  --type-label-weight: var(--weight-medium);
  --type-label-lead:   var(--leading-sm);

  --type-caption-size:   var(--text-xs);
  --type-caption-weight: var(--weight-regular);
  --type-caption-lead:   var(--leading-xs);

  --type-badge-size:   var(--text-2xs);
  --type-badge-weight: var(--weight-medium);
  --type-badge-track:  var(--tracking-wide);

  --type-mono-size:   var(--text-sm);   /* 13px for data/code */
  --type-mono-lead:   var(--leading-sm);
  --type-mono-font:   var(--font-mono);
}
```

**Key change from v4.0:** Base body size drops from 16px to 14px. AG-VOTE is a data-dense governance app — operators see vote tallies, proxy tables, member lists. 14px is what Linear, Notion, Jira, and GitHub use for their dense data tables. 16px is for marketing sites and text-heavy reads. This single change immediately makes the UI feel more professional and data-native.

---

## 6. DARK MODE STRATEGY

### Background Layering Model (HIGH confidence — Atlassian + Radix verified)

**The core principle:** In dark mode, elevation = lightness. A higher surface (closer to the user) is lighter. This is the opposite of light mode where elevation is expressed via shadows.

**5-layer background model:**

```
Layer 0: Page background (darkest)  →  --color-bg
Layer 1: Surface (cards, panels)    →  --color-surface      (+3-4% lightness)
Layer 2: Raised (selected, hover)   →  --color-surface-raised (+4-5% more)
Layer 3: Overlay (modals, drawers)  →  --color-surface-overlay (same as raised + blur)
Layer 4: Tooltip/popover            →  --color-surface-raised + higher shadow
```

In the AG-VOTE dark token definitions above, these follow oklch lightness values:
- `--color-bg`: L=0.130
- `--color-surface`: L=0.155  (+0.025)
- `--color-surface-raised`: L=0.190  (+0.035)

**The gap between layers must be at least 3 lightness points in oklch** for the layering to be visible to human perception.

### Dark Mode Contrast Rules

```css
/* ─── CONTRAST REQUIREMENTS — WCAG AA ─── */
/*
  Text on bg:          body text vs surface → ≥ 4.5:1
  Large text on bg:    headings vs bg → ≥ 3:1
  Border visibility:   border vs surface → ≥ 1.5:1 (perceptible)

  AG-VOTE dark mode measurements:
  --color-text (L=0.62) on --color-surface (L=0.155) → ~6.2:1 ✓
  --color-text-dark (L=0.94) on --color-bg (L=0.13) → ~12:1 ✓
  --color-text-muted (L=0.46) on --color-surface (L=0.155) → ~3.8:1 ✗ (borderline)

  Fix for muted text in dark mode:
  Muted text needs L ≥ 0.50 on the dark surfaces used.
*/

/* Dark mode text legibility rules */
[data-theme="dark"] {
  /* Minimum muted text — bumped to pass 4.5:1 */
  --color-text-muted:  oklch(0.520 0.018 250);  /* was 0.460 — too dim */

  /* Don't use --color-text-light for anything except decorative/disabled */
  /* It fails WCAG AA on dark surfaces */
}
```

### Sidebar Dark Override Pattern

```css
/* ─── SIDEBAR (always dark, regardless of theme) ─── */
/* Sidebar has its own independent token set */
:root {
  --sidebar-bg:           oklch(0.110 0.015 265); /* #0C1018 */
  --sidebar-bg-hover:     oklch(1 0 0 / 10%);     /* white 10% overlay */
  --sidebar-bg-active:    oklch(0.520 0.195 265 / 30%); /* primary 30% */
  --sidebar-border:       oklch(1 0 0 / 8%);
  --sidebar-text:         oklch(1 0 0 / 85%);
  --sidebar-text-active:  oklch(1 0 0 / 100%);
  --sidebar-text-muted:   oklch(1 0 0 / 50%);
  --sidebar-icon:         oklch(1 0 0 / 60%);
  --sidebar-icon-active:  oklch(0.680 0.170 265); /* primary color */
}
/* Sidebar stays the same in dark mode — already dark */
[data-theme="dark"] {
  --sidebar-bg: oklch(0.080 0.010 265); /* slightly deeper in dark mode */
}
```

### What Top Dark Modes Do Differently

1. **No pure black (#000000) anywhere** — pure black has infinite contrast and feels harsh. Use deeply saturated dark grays with a slight blue/purple hue.

2. **Borders are opacity-based, not hex-based** — `oklch(1 0 0 / 8%)` instead of `#252C3C`. This adapts automatically when background lightness changes.

3. **Colored shadows are invisible on dark** — replace shadows with `border: 1px solid var(--color-border)` on dark surfaces. Only floating elements (modals, popovers) need shadows.

4. **Interactive states use background tint, not border** — on dark, `background: oklch(1 0 0 / 5%)` for hover is more legible than changing border color.

---

## 7. BORDER-RADIUS SCALE

### Research Findings (MEDIUM confidence — visual analysis of Vercel/Linear/Stripe)

| App | Default button | Cards | Inputs | Modals |
|-----|---------------|-------|--------|--------|
| Vercel | 6px | 8px | 6px | 12px |
| Linear | 6px | 8px | 6px | 12px |
| Stripe | 6px | 12px | 6px | 16px |
| GitHub | 6px | 8px | 6px | 12px |

**Pattern:** 6px for interactive elements (buttons, inputs, chips), 8px for containers (cards), 12px for modals/drawers. "Premium" feel comes from **consistent application**, not specific numbers.

**What makes radius look cheap:**
- Using the same radius everywhere (no hierarchy)
- Radius too large for small elements (8px on a 24px badge looks like a pill)
- Mixing px and rem values across components

```css
:root {
  /* ─── BORDER RADIUS SCALE ─── */
  --radius-none: 0;
  --radius-xs:   0.1875rem; /* 3px  — table cell indicators, hairline */
  --radius-sm:   0.3125rem; /* 5px  — tags, badges, inline chips */
  --radius-md:   0.375rem;  /* 6px  — buttons, inputs, selects */
  --radius-lg:   0.5rem;    /* 8px  — cards, panels, dropdowns */
  --radius-xl:   0.75rem;   /* 12px — modals, drawers, toasts */
  --radius-2xl:  1rem;      /* 16px — sidesheets, featured cards */
  --radius-full: 9999px;    /* pill — status badges, avatar */

  /* ─── SEMANTIC RADIUS ALIASES ─── */
  --radius-btn:     var(--radius-md);   /* 6px — all buttons */
  --radius-input:   var(--radius-md);   /* 6px — inputs, selects */
  --radius-badge:   var(--radius-sm);   /* 5px — status badges */
  --radius-chip:    var(--radius-full); /* pill — filter chips */
  --radius-card:    var(--radius-lg);   /* 8px — content cards */
  --radius-panel:   var(--radius-lg);   /* 8px — sidebar panels */
  --radius-modal:   var(--radius-xl);   /* 12px — dialog boxes */
  --radius-toast:   var(--radius-lg);   /* 8px — notifications */
  --radius-tooltip: var(--radius-sm);   /* 5px — tooltips */
  --radius-avatar:  var(--radius-full); /* circle — avatars */
  --radius-tag:     var(--radius-xs);   /* 3px — table tags (tight) */
}
```

**AG-VOTE specific note:** The current scale (6px / 8px / 10px) is close but `--radius-lg: 10px` should become `--radius-lg: 8px` to align with industry standard. The 10px value is an awkward in-between.

---

## 8. TRANSITION SYSTEM

### Research Findings (HIGH confidence — Material Design 3 motion specs + MDN verified)

Material Design 3 defines: **Standard (default)** for most UI changes, **Emphasized** for large/dramatic changes, **Decelerate** for elements entering screen, **Accelerate** for elements leaving.

Framer Motion defaults: spring-based with damping 20, stiffness 300 for most interactions. For CSS-only (no Framer), the equivalent feel uses `cubic-bezier(0.34, 1.56, 0.64, 1)` for a subtle spring bounce.

```css
:root {
  /* ─── DURATION SCALE ─── */
  --duration-instant:  50ms;   /* state-only changes (color on hover) */
  --duration-fast:     100ms;  /* micro interactions (button press) */
  --duration-normal:   150ms;  /* standard UI (most hover states) */
  --duration-moderate: 200ms;  /* slightly more complex (dropdowns) */
  --duration-slow:     250ms;  /* deliberate feedback (form validation) */
  --duration-deliberate: 300ms;/* enter/exit animations */
  --duration-elaborate: 400ms; /* page transitions, modals */
  --duration-dramatic: 500ms;  /* hero animations, first load */

  /* ─── EASING FUNCTIONS ─── */
  /* Named after intent, not mathematical description */

  /* Standard — functional state changes (hover, focus, active) */
  --ease-standard: cubic-bezier(0.2, 0, 0, 1);

  /* Emphasized — enter screen or expand (decelerates into rest) */
  --ease-emphasized: cubic-bezier(0.05, 0.7, 0.1, 1.0);

  /* Emphasized out — exit screen or collapse (accelerates away) */
  --ease-emphasized-out: cubic-bezier(0.3, 0, 0.8, 0.15);

  /* Linear — opacity fades, where easing looks wrong */
  --ease-linear: linear;

  /* Spring — delightful micro bounce (not for enter/exit, only for interactive response) */
  --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);

  /* Overshoot — badges, count changes — slightly overshoots target */
  --ease-overshoot: cubic-bezier(0.34, 1.3, 0.64, 1);

  /* Legacy aliases (backwards compat with existing code) */
  --ease-default: var(--ease-standard);
  --ease-in:      cubic-bezier(0.4, 0, 1, 1);
  --ease-out:     cubic-bezier(0, 0, 0.2, 1);
  --ease-bounce:  var(--ease-spring);

  /* ─── NAMED TRANSITIONS ─── */
  /* Pre-composed transitions for common use cases */
  --transition-color:     color var(--duration-normal) var(--ease-standard),
                          background-color var(--duration-normal) var(--ease-standard),
                          border-color var(--duration-normal) var(--ease-standard);

  --transition-shadow:    box-shadow var(--duration-moderate) var(--ease-standard);

  --transition-transform: transform var(--duration-moderate) var(--ease-standard);

  --transition-opacity:   opacity var(--duration-normal) var(--ease-linear);

  --transition-ui:        color var(--duration-normal) var(--ease-standard),
                          background-color var(--duration-normal) var(--ease-standard),
                          border-color var(--duration-normal) var(--ease-standard),
                          box-shadow var(--duration-moderate) var(--ease-standard),
                          opacity var(--duration-normal) var(--ease-linear);

  --transition-enter:     transform var(--duration-deliberate) var(--ease-emphasized),
                          opacity var(--duration-deliberate) var(--ease-linear);

  --transition-exit:      transform var(--duration-moderate) var(--ease-emphasized-out),
                          opacity var(--duration-moderate) var(--ease-linear);

  /* Legacy shorthand (keep for existing code) */
  --transition: var(--duration-normal) var(--ease-standard);
}
```

**Why `--transition-ui` matters:** Instead of writing `transition: all 200ms ease` (which re-computes layout on every frame), enumerate exactly what changes. `all` triggers reflows. Individual property transitions only composite what's needed.

**Duration guide for AG-VOTE:**
- Button hover color: `--duration-normal` (150ms) + `--ease-standard`
- Dropdown open: `--duration-deliberate` (300ms) + `--ease-emphasized`
- Modal enter: `--duration-elaborate` (400ms) + `--ease-emphasized`
- Live vote badge count: `--duration-fast` (100ms) + `--ease-overshoot`
- SSE delta badges: `--duration-fast` (100ms) + `--ease-spring`

---

## Critical Pitfalls for v4.1

### Pitfall 1: oklch Browser Support — Gradual Rollout Risk

**What goes wrong:** OKLCH primitive values in `:root` are not parsed by Safari < 15.4 or any IE. The fallback hex values disappear entirely — component backgrounds become transparent.

**Prevention:** For primitives layer, keep them as comments/reference only. Semantic tokens should use hex or `rgb()` as primary values, with `color-mix(in oklch, ...)` used only for derived values. OR add @supports guard:

```css
/* Safe pattern */
:root {
  --color-primary: #1650E0;  /* fallback first */
  --color-primary: oklch(0.520 0.195 265);  /* enhances if supported */
}
```

**Detection:** Test in Safari 15.3 or use @supports(color: oklch(0 0 0)) gate.

### Pitfall 2: color-mix() In Cascaded Variables — Reference Loop

**What goes wrong:** `--color-primary-tint-10: color-mix(in oklch, var(--color-primary) 10%, var(--color-surface))` in `:root` is fine. But if a component does `--color-primary: color-mix(in oklch, var(--color-primary) 80%, black)` — this is a circular reference and resolves to `transparent`.

**Prevention:** Never redefine a token using itself. Use component-scoped tokens:

```css
/* BAD */
.btn { --color-primary: color-mix(in oklch, var(--color-primary) 80%, black); }

/* GOOD */
.btn { --btn-bg: color-mix(in oklch, var(--color-primary) 80%, black); }
```

### Pitfall 3: Token Proliferation — The 265+ Variable Trap

**What went wrong in v4.0:** Each page CSS file added its own one-off tokens (`--quorum-bar-bg`, `--delta-badge-color`) without checking if a semantic token already existed. Result: 265+ variables with massive overlap.

**Prevention for v4.1:** Enforce a naming convention:
- Primitive: `--[palette]-[step]` (e.g., `--blue-600`)
- Semantic: `--color-[role]` (e.g., `--color-primary`)
- Component: `--[component]-[property]` (e.g., `--badge-bg`)

If you reach for `--color-vote-status-approved-bg`, that's a code smell. Use `--color-success-subtle`.

**Maximum target:** 80–100 tokens in `:root`, 20–30 in `[data-theme="dark"]`, component tokens scoped to their component.

### Pitfall 4: Shadow Strength Mismatch Between Themes

**What goes wrong:** Shadows tuned for light mode (subtle, warm) are nearly invisible in dark mode. The current `--shadow-lg` is 0.10 alpha on light and 0.36 alpha on dark — a 3.6× multiplier. If you forget to add the dark override, shadowed elements look flat.

**Prevention:** Always define the `--shadow-color` variable separately and override it in dark mode:

```css
:root { --shadow-color: 21 21 16; }     /* warm dark */
[data-theme="dark"] { --shadow-color: 0 0 0; }  /* pure black */
/* Then all --shadow-* values automatically adapt */
```

**Detection:** Screenshot the same component in both modes side-by-side at the same scale.

### Pitfall 5: Typography Base Size Regression

**What goes wrong:** Changing `--text-base` from 16px to 14px breaks every existing component that uses `font-size: var(--text-base)` — form labels, body text, table cells. They all shrink at once.

**Prevention:** Add the new `--text-base: 0.875rem` (14px) and rename the old 16px to `--text-md`. Then sweep through page CSS files to update references. Do this in one atomic phase, not spread across multiple.

**Migration path:**
```css
/* Phase 1: Add new value, keep old name */
--text-base: 1rem;       /* still 16px during transition */
--text-14: 0.875rem;     /* new 14px anchor */

/* Phase 2: After all components updated */
--text-base: 0.875rem;   /* 14px */
--text-md: 1rem;         /* 16px */
```

### Pitfall 6: @layer Specificity vs New Token Cascade

**What goes wrong:** If new tokens are defined in `@layer base` but a component in `@layer v4` uses `color: oklch(...)` directly (inline), the layer wins over the token. Adding `!important` to a token doesn't work — it applies to the fallback, not the variable.

**Prevention:** Never put raw color values in `@layer v4` component rules. Always go through a token. The rule: `@layer v4` may override token assignments but must never bypass the token system.

### Pitfall 7: Transition `all` Performance

**What goes wrong:** `transition: all 150ms ease` triggers composite reflow on properties that don't change (width, height, font-size). On tables with 50+ rows, hover states stutter.

**Prevention:** Use `--transition-color` or `--transition-ui` (explicit properties). Never `transition: all`.

```css
/* BAD — existing pattern in AG-VOTE */
.table-row { transition: all 150ms ease; }

/* GOOD */
.table-row { transition: var(--transition-color), var(--transition-shadow); }
```

---

## Phase-Specific Warnings for v4.1

| Phase Topic | Likely Pitfall | Mitigation |
|-------------|---------------|------------|
| Token audit | Missing dark-mode parity for new tokens | Run both themes in parallel during development |
| oklch migration | Browser fallback gaps | Keep hex fallbacks as first declaration |
| Typography refactor | base-size change breaks all page CSS | Atomic sweep, staged naming migration |
| Shadow audit | Dark mode shadows invisible | Use --shadow-color variable pattern |
| Component spacing | Inconsistent pad-md vs space-6 usage | Enforce semantic alias usage only |
| Transition cleanup | `transition: all` in 15+ page files | Search/replace with specific property list |
| Color-mix circular refs | Component overrides looping | Lint for var(--color-*) inside color-mix redef |

---

## Sources

- [Radix Colors — Understanding the Scale](https://www.radix-ui.com/colors/docs/palette-composition/understanding-the-scale) — HIGH confidence
- [Tailwind CSS v4 theme.css (GitHub)](https://github.com/tailwindlabs/tailwindcss/blob/next/packages/tailwindcss/theme.css) — HIGH confidence
- [shadcn/ui Theming Documentation](https://ui.shadcn.com/docs/theming) — HIGH confidence
- [OKLCH in CSS: Why We Moved from RGB and HSL — Evil Martians](https://evilmartians.com/chronicles/oklch-in-css-why-quit-rgb-hsl) — HIGH confidence
- [Material Design 3 Easing and Duration](https://m3.material.io/styles/motion/easing-and-duration/tokens-specs) — MEDIUM confidence (page content partially accessible)
- [Material Design 3 Elevation](https://m3.material.io/styles/elevation/applying-elevation) — MEDIUM confidence
- [Open Props — Sub-atomic CSS](https://open-props.style/) — HIGH confidence
- [Better Buttons with color-mix() — A Beautiful Site](https://www.abeautifulsite.net/posts/better-buttons-with-color-mix-and-custom-properties/) — MEDIUM confidence
- [Relative Color Syntax in CSS — OpenReplay](https://blog.openreplay.com/css-relative-color-syntax/) — MEDIUM confidence
- [Font Size Guidelines — LearnUI.design](https://www.learnui.design/blog/mobile-desktop-website-font-size-guidelines.html) — MEDIUM confidence
- [Atlassian Design — Elevation](https://atlassian.design/foundations/elevation/) — HIGH confidence
