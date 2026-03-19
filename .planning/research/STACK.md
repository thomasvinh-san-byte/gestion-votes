# Design Excellence Research — v4.1 "Design Excellence"

**Project:** AG-VOTE v4.1
**Domain:** Premium light-first visual design for governance/SaaS web app
**Researched:** 2026-03-19
**Overall Confidence:** HIGH (shadow values verified via official design systems; color values extracted from Radix UI source; spacing patterns verified against Atlassian, Linear, Refactoring UI)

---

> **SCOPE NOTICE:** This file covers v4.1 "Design Excellence" design research only.
> v4.0 technology research (Driver.js, PDF.js, FilePond, Anime.js) is archived.
> The technical stack (PHP 8.4, PostgreSQL, Redis, vanilla JS, Web Components) is unchanged.
> This milestone is purely about visual quality: light theme, typography, spacing, color, and avoiding AI-generated design patterns.

---

## The Single Overarching Principle

**Premium design is made of intentional differences, not uniform tokens.**

AI-generated CSS applies the same border-radius, shadow, spacing, and color weight to every element. What makes Stripe, Linear, and Vercel look expensive is that they break their own patterns deliberately — a tighter shadow here, more padding there, a heavier weight in one place — creating visual rhythm through controlled variation. Every rule below exists to introduce that intentionality.

---

## 1. Light Theme Premium Patterns

### 1.1 Background Layering: Three Depths, Not One

The single most common mistake in light themes is using `#ffffff` everywhere. Premium apps layer three or more background values to create depth without shadows.

**The AG-VOTE palette already has the right structure.** The problem is not using it consistently enough. Every page must apply all three levels:

| Level | Token | Value | Use |
|-------|-------|-------|-----|
| Page (deepest) | `--color-bg` | `#EDECE6` | Body background, app shell |
| Surface (mid) | `--color-surface` | `#FAFAF7` | Cards, panels, sidebars |
| Raised (highest) | `--color-surface-raised` | `#FFFFFF` | Active card, modal, popover, focused input |

**Before (cheap) — everything on the same plane:**
```css
/* Every element uses the same white */
.page { background: #ffffff; }
.card { background: #ffffff; }
.modal { background: #ffffff; }
.input { background: #ffffff; }
/* Result: flat, no depth, no hierarchy */
```

**After (premium) — three-level background stack:**
```css
/* Page shell: warmest gray — provides contrast for all content */
body { background: var(--color-bg); }           /* #EDECE6 */

/* Cards and panels: warm white — lifts off the page */
.card,
.panel,
.sidebar-content { background: var(--color-surface); }  /* #FAFAF7 */

/* Active states, modals, popovers: pure white — the highest elevation */
.modal-panel,
.dropdown-menu,
.card:hover,
input:focus { background: var(--color-surface-raised); }  /* #FFFFFF */
```

**Source:** Atlassian design system (verified): surface = `#ffffff`, raised = `#ffffff` + shadow, sunken = `#f8f8f8`. The distinction between `--color-surface` and `--color-surface-raised` must be used structurally on every page.

### 1.2 Shadow Depth: Three Elevations with Colored Shadows

AG-VOTE already defines shadow tokens. The gap is that most components use `--shadow-sm` uniformly. Premium apps define three distinct elevation tiers and apply them semantically.

**Before (cheap) — same shadow on everything:**
```css
.card    { box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.modal   { box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.tooltip { box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
/* All elements appear at the same elevation */
```

**After (premium) — semantic elevation tiers using warm-toned shadow color:**
```css
/* The shadow color is warm and matches the page palette — not cold black */
/* Use the existing #151510 (--color-text-dark) for shadow hue */

/* Tier 1: Resting cards — barely lifted, border does most of the work */
.card {
  box-shadow: var(--shadow-sm);
  /* = 0 1px 3px rgba(21,21,16,.05), 0 1px 2px rgba(21,21,16,.03) */
  border: 1px solid var(--color-border);
}

/* Tier 2: Interactive / hover state — clearly elevated */
.card:hover,
.panel-raised {
  box-shadow: var(--shadow-md);
  /* = 0 3px 10px rgba(21,21,16,.07), 0 1px 3px rgba(21,21,16,.04) */
  border-color: var(--color-border-subtle);
}

/* Tier 3: Overlay (modal, dropdown, popover) — floating above the page */
.modal-panel,
.dropdown-menu,
.popover {
  box-shadow: var(--shadow-lg);
  /* = 0 8px 24px rgba(21,21,16,.10), 0 2px 6px rgba(21,21,16,.05) */
  border: 1px solid var(--color-border-subtle);
}
```

**Why the existing shadows are already good (confidence: HIGH):** The existing `--shadow-*` tokens already use `rgba(21, 21, 16, ...)` — a warm, near-black shadow color matched to the warm paper palette. This is exactly what Josh W. Comeau's shadow research prescribes: match shadow hue to the surface color. The issue is semantic misuse, not values.

### 1.3 Border vs. Shadow Decision

**Rule:** Use borders for structure, shadows for elevation. Never both at equal weight.

```css
/* Resting state: border defines the shape, shadow is almost invisible */
.card {
  border: 1px solid var(--color-border);       /* #CDC9BB */
  box-shadow: var(--shadow-xs);               /* nearly imperceptible */
}

/* Elevated state: border fades, shadow does the work */
.card:hover {
  border-color: var(--color-border-subtle);   /* lighter: #DEDAD0 */
  box-shadow: var(--shadow-md);              /* shadow takes over */
  transition: border-color 200ms, box-shadow 200ms;
}

/* Full overlay: no border at all, pure shadow float */
.modal-panel {
  border: none;
  box-shadow: var(--shadow-xl);
  /* = 0 25px 50px -12px rgba(21,21,16,.15) */
}
```

---

## 2. Color Restraint: The 90/10 Rule

### 2.1 Color Budget

**Premium governance apps use color for signal only, not decoration.**

Stripe, Linear, and Vercel apply the same discipline: the entire UI is built from neutral grays with one accent used exclusively for CTAs and active states. Color appears only to communicate status.

| Budget | Use | Tokens |
|--------|-----|--------|
| 90% | Neutral grays (backgrounds, text, borders) | `--color-bg`, `--color-surface`, `--color-text`, `--color-text-muted`, `--color-border` |
| 8% | Primary blue (CTAs, active states, links) | `--color-primary` (#1650E0) |
| 2% | Semantic status (success, warning, danger) | `--color-success`, `--color-warning`, `--color-danger` |

**Before (cheap) — color used for decoration:**
```css
/* Color used to differentiate sections instead of using space/weight */
.section-header { background: var(--color-primary-subtle); color: var(--color-primary); }
.stats-card     { background: var(--color-success-subtle); }
.info-panel     { background: var(--color-info-subtle); }
/* Result: colorful UI that looks like a dashboard template */
```

**After (premium) — color used for signal only:**
```css
/* Everything lives on warm neutrals — color appears only for action/status */
.section-header {
  background: transparent;
  color: var(--color-text-dark);
  border-bottom: 1px solid var(--color-border);
}

/* Color only appears on active state */
.nav-item[aria-current="page"] {
  color: var(--color-primary);
  background: var(--color-primary-subtle);
}

/* Status only uses color for semantic meaning */
.badge--success { background: var(--color-success-subtle); color: var(--color-success-text); }
.badge--warning { background: var(--color-warning-subtle); color: var(--color-warning-text); }
```

### 2.2 The Governance Blue Palette (Confirmed Values)

The primary color `#1650E0` maps to approximately **oklch(48% 0.20 258)** — a deep governance blue in the Radix blue scale range between `blue9` (#0090ff) and `blue11` (#0d74ce).

**Verified Radix UI blue scale for reference (light mode):**

| Step | Hex | Use |
|------|-----|-----|
| blue1 | `#fbfdff` | Subtle background tint |
| blue2 | `#f4faff` | Hover background |
| blue3 | `#e6f4fe` | Active/selected background |
| blue4 | `#d5efff` | Solid hover bg |
| blue5 | `#c2e5ff` | Focused/selected bg |
| blue6 | `#acd8fc` | Active border |
| blue7 | `#8ec8f6` | Strong border |
| blue8 | `#5eb1ef` | Icon, low-contrast text |
| blue9 | `#0090ff` | Solid primary (lighter) |
| blue10 | `#0588f0` | Solid hover |
| blue11 | `#0d74ce` | Accessible text on white |
| blue12 | `#113264` | High-contrast text/heading |

**Mapping to AG-VOTE tokens:**
```css
:root {
  /* Primary blue — already correct at #1650E0 (between blue9 and blue11) */
  --color-primary:        #1650E0;   /* Main CTA, active state */
  --color-primary-hover:  #1140C0;   /* Hover */
  --color-primary-active: #0C30A0;   /* Active/pressed */
  --color-primary-subtle: #EBF0FF;   /* Background tint (≈ blue2/blue3) */
  --color-primary-glow:   rgba(22, 80, 224, 0.12); /* Focus ring fill */
}
```

**Confidence: HIGH** — values verified from existing codebase; Radix blue scale values extracted directly from GitHub source.

### 2.3 The "Purple" Problem

The current system has `--color-accent: #5038C0` (purple). This creates two accent colors — blue (#1650E0) and purple (#5038C0) — which dilutes the premium single-accent discipline.

**Resolution for v4.1:**
- `--color-accent` / `--color-purple`: use only for role-based persona distinction (operator = blue, voter = purple). Never use as a general UI accent.
- All interactive UI elements use blue exclusively.
- Purple appears only in persona-differentiated contexts (voter ballot cards, voter-specific page elements).

---

## 3. Visual Hierarchy Through Typography

### 3.1 The Scale Problem: Too Many Sizes, Too Little Contrast

The current scale has 8 sizes (12px → 36px). For a data-heavy governance app, the right approach is 5 sizes used with strong weight contrast to create hierarchy — not 8 sizes at similar weights.

**Before (cheap) — many sizes, weak weight contrast:**
```css
/* 8 sizes, all at font-weight: 400 or 500 */
.page-title    { font-size: var(--text-3xl); font-weight: 600; }  /* 30px */
.section-title { font-size: var(--text-2xl); font-weight: 500; }  /* 24px */
.card-title    { font-size: var(--text-xl);  font-weight: 500; }  /* 20px */
.body-text     { font-size: var(--text-base);font-weight: 400; }  /* 16px */
.label         { font-size: var(--text-sm);  font-weight: 400; }  /* 14px */
.caption       { font-size: var(--text-xs);  font-weight: 400; }  /* 12px */
/* Result: waterfall of sizes with no visual anchoring */
```

**After (premium) — 5 sizes with strong weight and color contrast:**
```css
/* Page title: Fraunces display, heavy weight, primary text color */
.page-title {
  font-family: var(--font-display);    /* Fraunces */
  font-size: var(--text-3xl);          /* 30px — use sparingly, once per page */
  font-weight: var(--font-bold);       /* 700 */
  line-height: var(--leading-tight);   /* 1.25 */
  color: var(--color-text-dark);       /* #151510 — near-black */
  letter-spacing: -0.02em;            /* tight tracking for large display text */
}

/* Section heading: Bricolage Grotesque, semibold, full text color */
.section-title {
  font-family: var(--font-sans);
  font-size: var(--text-xl);           /* 20px */
  font-weight: var(--font-semibold);   /* 600 */
  line-height: var(--leading-snug);    /* 1.375 */
  color: var(--color-text-dark);       /* #151510 */
  letter-spacing: -0.01em;
}

/* Card title / component label: medium weight, standard text color */
.card-title,
.component-label {
  font-family: var(--font-sans);
  font-size: var(--text-base);         /* 16px */
  font-weight: var(--font-semibold);   /* 600 — weight does the work, not size */
  line-height: var(--leading-normal);  /* 1.5 */
  color: var(--color-text);            /* #52504A */
}

/* Body / primary reading text */
.body-text,
p {
  font-family: var(--font-sans);
  font-size: var(--text-base);         /* 16px */
  font-weight: var(--font-normal);     /* 400 */
  line-height: var(--leading-relaxed); /* 1.625 — comfortable for reading */
  color: var(--color-text);            /* #52504A */
}

/* UI labels, form labels, table headers */
.ui-label,
.form-label,
th {
  font-family: var(--font-sans);
  font-size: var(--text-sm);           /* 14px — intentionally smaller than body */
  font-weight: var(--font-medium);     /* 500 */
  line-height: var(--leading-normal);  /* 1.5 */
  color: var(--color-text-muted);      /* #857F72 — secondary signal */
  letter-spacing: 0.01em;             /* slight opening for labels at small size */
}

/* Data values: JetBrains Mono for all numbers and codes */
.data-value,
.vote-count,
.percentage,
.id-field,
code {
  font-family: var(--font-mono);       /* JetBrains Mono */
  font-size: var(--text-sm);           /* 14px */
  font-weight: var(--font-normal);     /* 400 */
  font-feature-settings: "tnum" 1;    /* tabular numbers for alignment */
  color: var(--color-text);
}
```

### 3.2 Font Size for Data Apps: The 14px Body Question

Research finding: 14px is acceptable **only for UI chrome elements** (labels, table headers, form field hints). Not for body text or reading content.

**Decision for AG-VOTE:**
- **16px body** for all reading content (descriptions, instructions, status messages)
- **14px** for UI labels (form labels, table column headers, metadata, counts in badges)
- **12px** only for captions and secondary metadata (timestamps, IDs)
- **Never** use 12px for anything interactive or essential

**Source:** LearnUI.Design font size guidelines (MEDIUM confidence), reinforced by WCAG 2.1 (HIGH confidence — minimum 16px for sustained reading).

### 3.3 Monospace for Data: The Tabular Numbers Rule

**Before (cheap):**
```css
.vote-count { font-family: var(--font-sans); }
/* Numbers shift width when changing from "99" to "100" */
```

**After (premium):**
```css
/* All data values use monospace + tabular numbers */
.vote-count,
.quorum-percentage,
.member-id,
.timestamp {
  font-family: var(--font-mono);      /* JetBrains Mono */
  font-feature-settings: "tnum" 1;   /* fixed-width digits — numbers never jump */
  font-size: var(--text-sm);          /* 14px */
}
```

### 3.4 Letter Spacing Rules

```css
/* Tight: large display headings (reduces optical spacing at large size) */
.page-title    { letter-spacing: -0.02em; }

/* Slight tightening: section headings */
.section-title { letter-spacing: -0.01em; }

/* Neutral: body text */
.body-text     { letter-spacing: 0; }

/* Slight opening: small caps labels and 12px text */
.ui-label      { letter-spacing: 0.01em; }
.caption-text  { letter-spacing: 0.02em; }

/* Never: all-caps with tight tracking at small size */
/* BAD: text-transform: uppercase + letter-spacing: -0.01em at 12px = illegible */
```

---

## 4. Spacing That Breathes

### 4.1 The Spacing Philosophy: Start Larger Than You Think

The single most impactful change from "AI-generated" to "premium" is using more whitespace than feels comfortable. Research from Refactoring UI (HIGH confidence) and every premium design system agrees: **start with too much space and reduce, never start tight and try to add air**.

**Before (cheap) — tight everything:**
```css
.page-header    { padding: 16px 24px; }
.card           { padding: 16px; }
.form-group     { margin-bottom: 12px; }
.section        { margin-bottom: 24px; }
/* Result: cramped, low-confidence, low-trust visual appearance */
```

**After (premium) — generous breathing room:**
```css
.page-header    { padding: 32px 40px; }      /* 2x what AI would write */
.card           { padding: 24px; }            /* minimum for content cards */
.card--compact  { padding: 16px 20px; }       /* tables, list items */
.form-group     { margin-bottom: 20px; }      /* form fields need air */
.section        { margin-bottom: 48px; }      /* sections need major separation */
/* Result: content feels important, trustworthy, not rushed */
```

### 4.2 The Spacing Scale by Context

These values are concrete, opinionated, and not negotiable for top-1% quality:

**Page-level structure:**
```css
/* Page shell — outer container */
.page-content {
  padding: var(--space-8) var(--space-10);    /* 32px 40px */
  max-width: var(--content-max);             /* 1440px */
  margin: 0 auto;
}

/* Page header (title + actions row) */
.page-header {
  padding-bottom: var(--space-8);            /* 32px bottom gap before content */
  margin-bottom: var(--space-8);             /* 32px */
  border-bottom: 1px solid var(--color-border);
}
```

**Section-level structure:**
```css
/* Between major sections */
.section + .section {
  margin-top: var(--space-12);               /* 48px — strong visual break */
}

/* Section header */
.section-header {
  margin-bottom: var(--space-6);             /* 24px */
}

/* Grid/list of cards */
.card-grid {
  display: grid;
  gap: var(--space-6);                       /* 24px — standard card gap */
}

/* Tighter grid for metadata/badge-like items */
.tag-grid {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);                       /* 8px */
}
```

**Component-level padding:**
```css
/* Standard content card */
.card {
  padding: var(--space-6);                   /* 24px all sides */
}

/* Table card / data-dense card */
.card--table {
  padding: 0;                                /* no padding — table has its own */
}
.card--table .card-header {
  padding: var(--space-4) var(--space-6);   /* 16px 24px */
}

/* Form elements */
.form-group { margin-bottom: var(--space-5); }     /* 20px */
.form-label { margin-bottom: var(--space-2); }     /* 8px above input */

input, select, textarea {
  padding: var(--space-3) var(--space-4);          /* 12px 16px */
}

/* Buttons */
.btn {
  padding: var(--space-3) var(--space-5);          /* 12px 20px */
}
.btn--sm {
  padding: var(--space-2) var(--space-4);          /* 8px 16px */
}
.btn--lg {
  padding: var(--space-4) var(--space-6);          /* 16px 24px */
}
```

**Table row density:**
```css
th, td {
  padding: var(--space-3) var(--space-4);          /* 12px 16px — data density */
}

/* First/last column gets extra horizontal padding */
th:first-child, td:first-child { padding-left: var(--space-6); }  /* 24px */
th:last-child, td:last-child   { padding-right: var(--space-6); } /* 24px */
```

### 4.3 The Inner ≤ Outer Spacing Rule

**Rule:** Spacing between components must always be greater than or equal to the internal padding of those components.

```css
/* BAD: card has 24px padding but grid has only 16px gap */
.card { padding: 24px; }
.card-grid { gap: 16px; }    /* gap < padding = elements appear to collide */

/* GOOD: gap equals or exceeds card padding */
.card { padding: 24px; }
.card-grid { gap: 24px; }    /* equal = balanced */

/* ALSO GOOD: gap exceeds card padding for airy layout */
.card { padding: 24px; }
.card-grid { gap: 32px; }    /* gap > padding = open, premium feel */
```

**Source:** Atlassian Design System spacing guidelines (HIGH confidence); Refactoring UI (MEDIUM confidence).

---

## 5. What Makes AI-Generated CSS Look Cheap

This section is the most important for v4.1. Avoiding these patterns requires active vigilance, not just following a design system.

### 5.1 Uniform Border Radius

**The tell:** border-radius: 8px on everything, including large modals, small badges, and input fields.

**Why it's a problem:** Real physical objects have different radii depending on their size. A large object with the same radius as a small one looks scaled wrongly — like a giant rounded-corner rectangle that should be a modal.

**Before (cheap):**
```css
/* Same radius everywhere */
.card    { border-radius: 8px; }
.badge   { border-radius: 8px; }   /* should be pill or 4px */
.modal   { border-radius: 8px; }   /* should be 12-16px for large surfaces */
.input   { border-radius: 8px; }
.btn     { border-radius: 8px; }
.tooltip { border-radius: 8px; }   /* should be 4-6px for compact */
```

**After (premium) — radius scales with element size:**
```css
/* XS: 4px — inline tags, tiny badges, chips */
.badge,
.tag,
.chip { border-radius: var(--radius-sm); }   /* 6px (close enough) */

/* S: 6px (--radius-sm) — inputs, buttons, small components */
input, select, textarea, .btn { border-radius: var(--radius-sm); }  /* 6px */

/* M: 8px (--radius) — cards, panels, standard components */
.card,
.panel,
.table-wrapper { border-radius: var(--radius); }  /* 8px */

/* L: 10px (--radius-lg) — modals, large overlay surfaces */
.modal-panel,
.drawer,
.dropdown-menu { border-radius: var(--radius-lg); }  /* 10px */

/* Pill: 999px (--radius-full) — status badges, toggle switches, only */
.status-badge,
.toggle { border-radius: var(--radius-full); }
```

### 5.2 Uniform Shadows: The 0.1 Opacity Pattern

**The tell:** `box-shadow: 0 2px 8px rgba(0,0,0,0.1)` applied identically to cards, modals, dropdowns, and tooltips. Everything appears at the same elevation.

**Before (cheap):**
```css
.card, .modal, .dropdown, .tooltip {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}
/* All components float at exactly the same height */
```

**After (premium) — elevation semantics (use the existing AG-VOTE tokens):**
```css
/* Cards at rest: barely there — border does most of the work */
.card {
  box-shadow: var(--shadow-xs);
  /* 0 1px 1px rgba(21,21,16,.03) */
}

/* Cards on hover: clearly lifted */
.card:hover {
  box-shadow: var(--shadow-md);
  /* 0 3px 10px rgba(21,21,16,.07), 0 1px 3px rgba(21,21,16,.04) */
}

/* Dropdowns/popovers: visibly floating */
.dropdown-menu,
.popover {
  box-shadow: var(--shadow-lg);
  /* 0 8px 24px rgba(21,21,16,.10), 0 2px 6px rgba(21,21,16,.05) */
}

/* Modals: maximum elevation, almost theatrical */
.modal-panel {
  box-shadow: var(--shadow-xl);
  /* 0 25px 50px -12px rgba(21,21,16,.15) */
}
```

### 5.3 No Visual Rhythm: Monotone Spacing

**The tell:** Every component has `gap: 16px` or `margin-bottom: 16px`, regardless of whether it's a section break, a field gap, or an icon-label pair. No breathing hierarchy between levels.

**Before (cheap):**
```css
/* 16px applied to everything without semantic distinction */
.page { padding: 16px; }
.card { padding: 16px; margin-bottom: 16px; }
.form-group { margin-bottom: 16px; }
.form-label { margin-bottom: 16px; } /* wrong — label-to-input is 8px */
.section { margin-bottom: 16px; }    /* wrong — sections need 48px */
```

**After (premium) — spacing communicates relationship:**
```css
/* Tight: elements that belong together (icon+label, label+input) */
.icon-label-pair { gap: var(--space-2); }         /* 8px */
.form-label      { margin-bottom: var(--space-2); } /* 8px to input */

/* Standard: siblings within a component (fields in a form) */
.form-group { margin-bottom: var(--space-5); }   /* 20px */

/* Generous: between distinct components in a container */
.card-body > * + * { margin-top: var(--space-4); } /* 16px */

/* Large: between sections/groups */
.section + .section { margin-top: var(--space-12); } /* 48px */

/* Page-level: between major page areas */
.main-header + .main-content { margin-top: var(--space-8); } /* 32px */
```

### 5.4 Color Overuse: Purple-Blue Gradient Default

**The tell:** AI reaches for gradient backgrounds, colored section headers, and tinted cards to add "visual interest" because it cannot create interest through spacing and typography alone.

**Before (cheap — AI-generated pattern):**
```css
/* AI adds color to differentiate content, not to communicate meaning */
.hero-section { background: linear-gradient(135deg, #5038C0, #1650E0); }
.kpi-card     { background: var(--color-primary-subtle); }
.info-panel   { background: var(--color-info-subtle); }
.stat-section { background: var(--color-success-subtle); }
/* Result: looks like a colored dashboard template, not a governance tool */
```

**After (premium) — neutral canvas with color only for semantic signal:**
```css
/* All sections live on the neutral canvas */
.hero-section { background: var(--color-surface); }

/* Color appears only on status/role/action */
.kpi-card.kpi--quorum-met  { border-left: 3px solid var(--color-success); }
.kpi-card.kpi--quorum-low  { border-left: 3px solid var(--color-warning); }

/* Or as a subtle left accent (Linear-style) */
.alert--info {
  background: var(--color-surface-raised);
  border-left: 3px solid var(--color-primary);
}
.alert--success {
  background: var(--color-surface-raised);
  border-left: 3px solid var(--color-success);
}
/* The alert communicates through the accent stripe, not a colored background */
```

### 5.5 Weak Font Weight Contrast

**The tell:** Headings at `font-weight: 500` when body text is `400`. The difference is imperceptible. AI uses weight 500 or 600 for "headers" and 400 for body but applies them at sizes too close together to matter.

**Before (cheap):**
```css
h1 { font-size: 24px; font-weight: 600; color: #333; }
h2 { font-size: 20px; font-weight: 600; color: #333; }
h3 { font-size: 18px; font-weight: 500; color: #333; } /* no distinction from h2 */
p  { font-size: 16px; font-weight: 400; color: #333; } /* same color as headings */
/* Everything reads at similar visual weight */
```

**After (premium) — weight + color + size together create strong contrast:**
```css
/* Use the display font (Fraunces) for the topmost heading — completely different voice */
.page-title {
  font-family: var(--font-display);  /* Fraunces — totally different from body */
  font-size: var(--text-3xl);        /* 30px */
  font-weight: 700;
  color: var(--color-text-dark);     /* #151510 — near black */
}

/* Section heading: same family as body but heavier + darker */
.section-title {
  font-family: var(--font-sans);
  font-size: var(--text-xl);
  font-weight: 700;                  /* much heavier than body's 400 */
  color: var(--color-text-dark);     /* #151510 — fully dark */
}

/* Component label: lighter than section, uses muted color */
.card-label {
  font-size: var(--text-sm);         /* 14px — actually smaller than body */
  font-weight: 600;                  /* semi-bold for emphasis at small size */
  color: var(--color-text-muted);    /* #857F72 — recedes visually */
  text-transform: uppercase;
  letter-spacing: 0.06em;           /* uppercase labels need wider tracking */
}

/* Body text: clearly the default, not competing with anything */
p {
  font-size: var(--text-base);
  font-weight: 400;
  color: var(--color-text);          /* #52504A — warmer than pure black */
}
```

### 5.6 Hover States with No Direction

**The tell:** `background-color: #f0f0f0` on hover. No transform, no shadow change, no border change. Flat state change with no physical implication.

**Before (cheap):**
```css
.card:hover { background: #f0f0f0; }
.btn:hover  { background: #1140C0; }
/* No physical feedback — feels digital, not tactile */
```

**After (premium) — direction + depth:**
```css
/* Cards: lift on hover */
.card {
  transition: transform 150ms var(--ease-out),
              box-shadow 150ms var(--ease-out),
              border-color 150ms;
}
.card:hover {
  transform: translateY(-1px);
  box-shadow: var(--shadow-md);
  border-color: var(--color-border-subtle);
}

/* Buttons: lift on hover, press on active */
.btn {
  transition: transform 100ms var(--ease-out),
              box-shadow 100ms var(--ease-out),
              background 150ms;
}
.btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 8px var(--color-primary-glow);
}
.btn:active {
  transform: translateY(0) scale(0.98);
  box-shadow: none;
}

/* Nav items: no transform — they're not elevated, just indicated */
.nav-item:hover {
  background: var(--color-primary-subtle);  /* tint shift only */
  color: var(--color-primary);
}
```

---

## 6. Applied Patterns for AG-VOTE Pages

### 6.1 Page Shell Template

This template applies to every page in the app. The page-level hierarchy is the first thing a user sees and sets the tone for the entire experience.

```css
/* Every page uses this structure — no exceptions */

/* App shell background: warm gray creates depth for everything above it */
.app-shell {
  background: var(--color-bg);          /* #EDECE6 */
  min-height: 100vh;
}

/* Main content area: lifts off the shell */
.page-surface {
  background: var(--color-surface);     /* #FAFAF7 */
  min-height: calc(100vh - var(--header-height));
  padding: var(--space-8) var(--space-10);   /* 32px 40px — generous */
}

/* Page title row */
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: var(--space-8);        /* 32px */
}

/* Page actions always right-aligned, never scattered */
.page-actions {
  display: flex;
  gap: var(--space-3);                  /* 12px between actions */
}
```

### 6.2 Card Anatomy

```css
/* A card has a consistent structure: header, body, optional footer */
.card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  box-shadow: var(--shadow-xs);
  transition: box-shadow 150ms, border-color 150ms, transform 150ms var(--ease-out);
}

.card-header {
  padding: var(--space-5) var(--space-6);   /* 20px 24px */
  border-bottom: 1px solid var(--color-border);
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.card-body {
  padding: var(--space-6);              /* 24px */
}

.card-footer {
  padding: var(--space-4) var(--space-6);   /* 16px 24px */
  border-top: 1px solid var(--color-border);
  background: var(--color-bg-subtle);   /* slightly darker footer */
  border-radius: 0 0 var(--radius) var(--radius);
}
```

### 6.3 Status / Badge System

```css
/* Status badges use color for semantic meaning only */
.badge {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);                  /* 4px icon-text gap */
  padding: 2px var(--space-2);         /* 2px 8px — very tight vertically */
  font-size: var(--text-xs);            /* 12px */
  font-weight: var(--font-medium);      /* 500 */
  border-radius: var(--radius-full);    /* pill */
  line-height: 1.4;
}

.badge--success {
  background: var(--color-success-subtle);   /* #EDFAF2 */
  color: var(--color-success-text);          /* #0B7A40 */
  border: 1px solid var(--color-success-border); /* #A3E8C1 */
}

.badge--warning {
  background: var(--color-warning-subtle);
  color: var(--color-warning-text);
  border: 1px solid var(--color-warning-border);
}

.badge--danger {
  background: var(--color-danger-subtle);
  color: var(--color-danger-text);
  border: 1px solid var(--color-danger-border);
}

/* Neutral badge for counts, IDs — never colored */
.badge--neutral {
  background: var(--color-neutral-subtle);  /* #E5E3D8 */
  color: var(--color-neutral-text);          /* #52504A */
  border: 1px solid var(--color-border);
}
```

---

## 7. The Operator Console: High-Density Data Pattern

The operator page is the most data-dense in the application. Special rules apply.

```css
/* Operator layout: no extra whitespace — every pixel matters */
.operator-shell {
  background: var(--color-bg);
}

/* Dense table rows for operator */
.operator-table th,
.operator-table td {
  padding: var(--space-2) var(--space-4);   /* 8px 16px — tighter than standard */
  font-size: var(--text-sm);                /* 14px — denser than body */
}

/* Vote counts always in monospace — numbers must align in columns */
.vote-count,
.percentage-value {
  font-family: var(--font-mono);
  font-feature-settings: "tnum" 1;
  font-size: var(--text-sm);
  font-variant-numeric: tabular-nums;
}

/* Live indicators use minimal color — pulse effect not background flood */
.live-indicator {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: var(--radius-full);
  background: var(--color-success);
  /* Pulse: CSS only, no JS */
}

@keyframes status-pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%       { opacity: 0.6; transform: scale(0.85); }
}

.live-indicator--active {
  animation: status-pulse 1.5s ease-in-out infinite;
}
```

---

## 8. Dark Mode Parity Checklist

Dark mode uses the same structural patterns — the rules below ensure parity without separate design work.

```css
[data-theme="dark"] {
  /* The background hierarchy inverts (dark surfaces use additive lighting) */
  --color-bg:           #1A1A17;   /* deepest dark */
  --color-surface:      #242420;   /* card surface */
  --color-surface-raised: #2E2E2A; /* elevated dark */

  /* Shadows in dark mode are subtle — dark on dark has low contrast */
  /* Reduce shadow opacity by ~40% in dark mode */
  --shadow-xs: 0 1px 1px rgba(0,0,0,.15);
  --shadow-sm: 0 1px 3px rgba(0,0,0,.20), 0 1px 2px rgba(0,0,0,.15);
  --shadow-md: 0 3px 10px rgba(0,0,0,.25), 0 1px 3px rgba(0,0,0,.15);
  --shadow-lg: 0 8px 24px rgba(0,0,0,.30), 0 2px 6px rgba(0,0,0,.20);
  --shadow-xl: 0 25px 50px -12px rgba(0,0,0,.40);

  /* Borders are lighter in dark mode to be visible */
  --color-border:        rgba(255,255,255,.10);
  --color-border-subtle: rgba(255,255,255,.06);
}
```

**Rule:** If a design decision looks correct in light mode and broken in dark mode, it was using hardcoded values instead of tokens. All v4.1 changes must use tokens exclusively.

---

## 9. The Anti-Checklist: What to Reject in Code Review

Any CSS matching these patterns should be refactored before merge:

| Pattern | Problem | Fix |
|---------|---------|-----|
| `box-shadow: 0 2px 8px rgba(0,0,0,0.1)` | Non-semantic, cold shadow, uniform elevation | Use `var(--shadow-sm/md/lg/xl)` |
| `border-radius: 8px` on everything | Uniform radii regardless of element size | Use `--radius-sm/radius/radius-lg` by element size |
| `background: var(--color-primary-subtle)` on non-interactive sections | Color as decoration, not signal | Use neutral `var(--color-surface)` |
| `font-weight: 500` on "important" text next to `400` body | Imperceptible contrast | Use `600` or `700`; use `--font-display` for top heading |
| `gap: 16px` everywhere | No spatial hierarchy | Use scale: 8px/12px/16px/20px/24px/32px/48px by semantic level |
| `margin-bottom: 16px` between sections | Section breaks need minimum 48px | Use `var(--space-12)` for section separation |
| `font-size: 12px` on anything interactive | Accessibility failure | Minimum 14px for interactive elements |
| `color: #333` or `color: #666` | Hardcoded values outside token system | Use `--color-text` or `--color-text-muted` |
| `transition: all 0.3s ease` | Over-broad transition, degrades performance | Enumerate specific properties: `transform, box-shadow, background` |
| Same component style on resting AND hover state | No physical depth feedback | Add `transform: translateY(-1px)` or shadow change on hover |
| No `letter-spacing: -0.01em` on large headings | Optical gap at large size | Add negative tracking to any heading ≥ 20px |
| `padding: 8px` on a content card | Too tight, low-trust visual | Minimum `padding: 20px`, standard `24px` |

---

## 10. Typography Quick Reference

```
Font usage summary:
- Fraunces     → Page titles, hero numbers, display contexts only
- Bricolage G. → All UI text, headings, labels, buttons
- JetBrains M. → All numbers, IDs, codes, timestamps, vote counts

Size usage summary:
- 30px (--text-3xl)  → Page title, once per page, Fraunces 700
- 20px (--text-xl)   → Section heading, Bricolage 700
- 16px (--text-base) → Body, card titles, Bricolage 600 (card) / 400 (body)
- 14px (--text-sm)   → UI labels, table headers, metadata, Bricolage 500
- 12px (--text-xs)   → Captions, timestamps, secondary metadata only

Weight usage summary:
- 700 → Page titles, section headings (creates strong contrast)
- 600 → Card titles, form labels at 16px, buttons
- 500 → UI labels at 14px, badge text
- 400 → Body text, descriptions, table cell content
```

---

## Sources

- [Designing Beautiful Shadows in CSS — Josh W. Comeau](https://www.joshwcomeau.com/css/designing-shadows/) — HIGH confidence (shadow formulas and layered approach)
- [Atlassian Design System — Elevation](https://atlassian.design/foundations/elevation/) — HIGH confidence (verified surface/shadow CSS values: `#ffffff`, `#f8f8f8`, `#f0f1f2`)
- [Radix UI Colors GitHub Source](https://github.com/radix-ui/colors/blob/main/src/light.ts) — HIGH confidence (exact hex values for gray and blue scales)
- [Linear UI Redesign Article](https://linear.app/now/how-we-redesigned-the-linear-ui) — MEDIUM confidence (LCH color space, three-variable theme generation)
- [Linear Design — LogRocket Blog](https://blog.logrocket.com/ux-design/linear-design/) — MEDIUM confidence (color restraint, whitespace principles)
- [Why AI Websites Look the Same — AXE-WEB](https://axe-web.com/insights/ai-website-design-sameness/) — MEDIUM confidence (purple-blue gradient pattern, uniform radii, Inter default)
- [Refactoring UI — Layout and Spacing](https://jacobshannon.com/blog/books/refactoring-ui/layout-and-spacing/) — MEDIUM confidence (start with excess space, systematic spacing)
- [Atlassian Design System — Spacing](https://atlassian.design/foundations/spacing/) — HIGH confidence (container size guidelines: L=24px, M=16px, XS=8px)
- [Font Size Guidelines — LearnUI.Design](https://www.learnui.design/blog/mobile-desktop-website-font-size-guidelines.html) — MEDIUM confidence (14px for UI labels, 16px for body)
- [Shadow Depth CSS Layers — Vinish.Dev](https://vinish.dev/shadows-depth-css-layers) — MEDIUM confidence (shadow depth and layering)
- [CSS Shadow Generator — Designing Better CSS Shadows](https://theosoti.com/blog/designing-shadows/) — MEDIUM confidence (elevation formula, layered shadow pattern)
- [Vercel Geist Colors](https://vercel.com/geist/colors) — MEDIUM confidence (bg-100/bg-200 hierarchy, gray-alpha tokens)
- [Stripe Accessible Color Systems](https://stripe.com/blog/accessible-color-systems) — MEDIUM confidence (perceptually uniform color space approach, CIELAB)
- AG-VOTE `design-system.css` existing token values — HIGH confidence (current shadow values, color palette, spacing scale confirmed by reading the source file)

---

*Design research for: AG-VOTE v4.1 "Design Excellence" — Light-first premium visual quality*
*Researched: 2026-03-19*
*Previous version (v4.0 technology stack) replaced — see git history if needed*
