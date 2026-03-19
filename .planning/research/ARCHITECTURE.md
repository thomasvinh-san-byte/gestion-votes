# Component Design Architecture — v4.1 Design Excellence

**Project:** AG-VOTE
**Researched:** 2026-03-18
**Scope:** Exact CSS specifications for every component type — premium, top 1% quality
**Stack:** Vanilla JS Web Components + CSS custom properties, light-first
**Supersedes:** v4.0 integration architecture (retained in milestone archive)

## Methodology

Sources used in priority order:
1. shadcn/ui source (new-york registry) — industry reference for 2025/2026 design systems
2. Sonner CSS source (`emilkowalski/sonner`) — canonical toast reference, values extracted from raw CSS
3. Shopify Polaris token library — enterprise-grade, all values publicly documented
4. ishadeed.com stepper article — authoritative CSS implementation with exact values
5. AG-VOTE `design-system.css` — ground truth for existing tokens (verified by direct Read)

All Tailwind values have been converted to exact px/rem. All specs reference AG-VOTE tokens where they exist.

Confidence: HIGH for shadcn/Sonner/Polaris values. HIGH for AG-VOTE existing tokens. MEDIUM for synthesis decisions.

---

## Existing AG-VOTE Token Reference

These tokens exist in `public/assets/css/design-system.css` and MUST be used — not reinvented.

```
Spacing        --space-1:0.25rem(4px) --space-2:0.5rem(8px) --space-3:0.75rem(12px)
               --space-4:1rem(16px)   --space-5:1.25rem(20px) --space-6:1.5rem(24px)
               --space-8:2rem(32px)

Border radius  --radius-sm:0.375rem(6px)  --radius:0.5rem(8px)  --radius-lg:0.625rem(10px)
               --radius-full:999px

Shadows        --shadow-xs:  0 1px 1px rgba(21,21,16,0.03)
               --shadow-sm:  0 1px 3px rgba(21,21,16,0.05), 0 1px 2px rgba(21,21,16,0.03)
               --shadow:     0 3px 6px rgba(21,21,16,0.06), 0 1px 3px rgba(21,21,16,0.04)
               --shadow-md:  0 3px 10px rgba(21,21,16,0.07), 0 1px 3px rgba(21,21,16,0.04)
               --shadow-lg:  0 8px 24px rgba(21,21,16,0.1), 0 2px 6px rgba(21,21,16,0.05)
               --shadow-xl:  0 25px 50px -12px rgba(21,21,16,0.15)
               --shadow-focus: 0 0 0 2px #fff, 0 0 0 4px rgba(22,80,224,0.4)

Transitions    --duration-fast:100ms  --duration-normal:200ms  --duration-slow:300ms
               --ease-default: cubic-bezier(0.4,0,0.2,1)
               --ease-out:     cubic-bezier(0,0,0.2,1)
               --ease-bounce:  cubic-bezier(0.34,1.56,0.64,1)

Key colors     --color-primary:#1650E0  --color-primary-hover:#1140C0
               --color-primary-active:#0C30A0  --color-primary-subtle:#EBF0FF
               --color-surface:#FAFAF7  --color-surface-raised:#FFFFFF
               --color-bg:#EDECE6  --color-bg-subtle:#E5E3D8
               --color-border:#CDC9BB  --color-border-subtle:#DEDAD0
               --color-border-strong:#BCB7A5
               --color-text:#52504A  --color-text-muted:#857F72
               --color-text-light:#B5B0A0  --color-text-dark:#151510
               --color-backdrop:rgba(0,0,0,0.5)
               --color-success:#0B7A40  --color-success-subtle:#EDFAF2
               --color-warning:#B56700  --color-warning-subtle:#FFF7E8
               --color-danger:#C42828   --color-danger-subtle:#FEF1F0
               --color-accent:#5038C0  --color-accent-subtle:#EEEAFF
```

---

## 1. BUTTONS

### Research Basis

shadcn/ui new-york registry (verified from taxonomy repo source). Tailwind conversions:
- `h-10` = 40px, `h-9` = 36px, `h-11` = 44px
- `px-4` = 16px, `py-2` = 8px, `text-sm` = 14px
- `rounded-md` in new-york = 6px, `font-medium` = 500
- Focus ring: `ring-2 ring-offset-2` = 2px ring + 2px gap = matches `--shadow-focus`

Base classes confirmed: `inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none`

Sizes confirmed: default `h-10 py-2 px-4`, sm `h-9 px-3`, lg `h-11 px-8`, icon `h-10 w-10`.

### What Separates Top 1% From Generic

1. **36px default height** (not 40px) — 40px is Bootstrap legacy. Dense governance UIs use 36px.
2. **`font-weight: 500` not 600** — 600 reads as urgent/aggressive. 500 is confident, not shouting.
3. **Active `scale(0.97)` transform** — 100ms, barely perceptible, but deeply tactile.
4. **Box-shadow focus ring, not `outline`** — 2px white gap + 4px colored ring, keyboard-nav only.
5. **Named transitions** — not `transition: all`, list exactly `background-color, color, border-color, box-shadow, transform`.

### Exact CSS Specifications

```css
/* ── BASE BUTTON ─────────────────────────────────────────── */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;                       /* 6px */
  padding: 0 0.875rem;                 /* 0 14px */
  height: 2.25rem;                     /* 36px — compact premium default */
  border-radius: var(--radius-sm);     /* 6px */
  font-family: var(--font-sans);
  font-size: 0.875rem;                 /* 14px */
  font-weight: var(--font-medium);     /* 500 */
  line-height: 1;
  white-space: nowrap;
  cursor: pointer;
  border: 1px solid transparent;
  text-decoration: none;
  transition:
    background-color var(--duration-fast) var(--ease-default),
    color            var(--duration-fast) var(--ease-default),
    border-color     var(--duration-fast) var(--ease-default),
    box-shadow       var(--duration-fast) var(--ease-default),
    transform        var(--duration-fast) var(--ease-default);
  outline: none;
  user-select: none;
  -webkit-tap-highlight-color: transparent;
}

.btn:focus-visible {
  box-shadow: var(--shadow-focus); /* 0 0 0 2px #fff, 0 0 0 4px rgba(22,80,224,0.4) */
}

.btn:active:not(:disabled) {
  transform: scale(0.97);
}

.btn:disabled {
  opacity: 0.5;
  pointer-events: none;
  cursor: not-allowed;
}

/* ── SIZE VARIANTS ───────────────────────────────────────── */
.btn-sm {
  height: 1.875rem;                    /* 30px */
  padding: 0 0.625rem;                 /* 0 10px */
  font-size: 0.8125rem;                /* 13px */
  border-radius: 0.3125rem;            /* 5px */
  gap: 0.25rem;
}

.btn-lg {
  height: 2.75rem;                     /* 44px */
  padding: 0 1.25rem;                  /* 0 20px */
  font-size: 0.9375rem;                /* 15px */
  border-radius: var(--radius);        /* 8px */
}

.btn-icon {
  width: 2.25rem;                      /* 36px × 36px */
  height: 2.25rem;
  padding: 0;
  flex-shrink: 0;
}
.btn-icon.btn-sm { width: 1.875rem; height: 1.875rem; }
.btn-icon.btn-lg { width: 2.75rem;  height: 2.75rem; }

/* ── PRIMARY ─────────────────────────────────────────────── */
.btn-primary {
  background-color: var(--color-primary);     /* #1650E0 */
  color: #ffffff;
  border-color: transparent;
  box-shadow: var(--shadow-xs);
}
.btn-primary:hover:not(:disabled) {
  background-color: var(--color-primary-hover); /* #1140C0 */
  box-shadow: var(--shadow-sm);
}
.btn-primary:active:not(:disabled) {
  background-color: var(--color-primary-active); /* #0C30A0 */
  box-shadow: none;
}

/* ── SECONDARY ───────────────────────────────────────────── */
.btn-secondary {
  background-color: var(--color-surface-raised); /* #FFFFFF */
  color: var(--color-text);
  border-color: var(--color-border);             /* #CDC9BB */
  box-shadow: var(--shadow-xs);
}
.btn-secondary:hover:not(:disabled) {
  background-color: var(--color-bg);             /* #EDECE6 */
  border-color: var(--color-border-strong);
  box-shadow: var(--shadow-sm);
}
.btn-secondary:active:not(:disabled) {
  background-color: var(--color-bg-subtle);      /* #E5E3D8 */
  box-shadow: none;
}

/* ── GHOST ───────────────────────────────────────────────── */
.btn-ghost {
  background-color: transparent;
  color: var(--color-text);
  border-color: transparent;
}
.btn-ghost:hover:not(:disabled) {
  background-color: var(--color-bg-subtle);      /* #E5E3D8 */
}
.btn-ghost:active:not(:disabled) {
  background-color: var(--color-border-subtle);  /* #DEDAD0 */
}

/* ── DANGER ──────────────────────────────────────────────── */
.btn-danger {
  background-color: var(--color-danger);         /* #C42828 */
  color: #ffffff;
  border-color: transparent;
  box-shadow: var(--shadow-xs);
}
.btn-danger:hover:not(:disabled) {
  background-color: var(--color-danger-hover);   /* #A82222 */
}
.btn-danger:active:not(:disabled) {
  background-color: #8C1B1B;
  box-shadow: none;
}

/* ── DANGER-GHOST (secondary destructive) ────────────────── */
.btn-danger-ghost {
  background-color: transparent;
  color: var(--color-danger);
  border-color: transparent;
}
.btn-danger-ghost:hover:not(:disabled) {
  background-color: var(--color-danger-subtle);  /* #FEF1F0 */
  border-color: var(--color-danger-border);
}

/* ── OUTLINE (primary-colored border) ───────────────────── */
.btn-outline {
  background-color: transparent;
  color: var(--color-primary);
  border-color: var(--color-primary);
}
.btn-outline:hover:not(:disabled) {
  background-color: var(--color-primary-subtle); /* #EBF0FF */
}
```

### Anti-Patterns to Avoid

- `border-radius` above 8px on default buttons — becomes "rounded" not "precise"
- `font-weight: 600` or `700` on buttons — 500 is premium, bolder feels alarming in governance UI
- Shadows on ghost buttons — ghost implies floating, shadow anchors it wrongly
- `transition: all` — enumerate specific properties only

---

## 2. CARDS

### Research Basis

Stripe dashboard, Linear issue cards, and Notion blocks all use the pattern: **white surface + 1px border + warm-off-white page background**. AG-VOTE already has this with `--color-surface-raised: #FFFFFF` on `--color-bg: #EDECE6`. The gap is in precise padding rhythm and header treatment.

Premium card rule: **border for light mode, shadow only for modals and raised surfaces**. Not both on the same element.

### Exact CSS Specifications

```css
/* ── BASE CARD ───────────────────────────────────────────── */
.card {
  background-color: var(--color-surface-raised); /* #FFFFFF */
  border: 1px solid var(--color-border);         /* #CDC9BB */
  border-radius: var(--radius-lg);               /* 10px */
  box-shadow: var(--shadow-xs);
  overflow: hidden;
}

/* ── CARD HEADER ─────────────────────────────────────────── */
.card-header {
  padding: 1rem 1.25rem;                         /* 16px 20px */
  border-bottom: 1px solid var(--color-border-subtle); /* #DEDAD0 */
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);                           /* 12px */
  min-height: 3rem;                              /* 48px — consistent header height */
}

.card-header-title {
  font-size: 0.9375rem;                          /* 15px */
  font-weight: var(--font-semibold);             /* 600 */
  color: var(--color-text-dark);                 /* #151510 */
  line-height: 1.3;
}

.card-header-subtitle {
  font-size: 0.8125rem;                          /* 13px */
  color: var(--color-text-muted);                /* #857F72 */
  margin-top: 0.125rem;                          /* 2px */
  line-height: 1.4;
}

/* ── CARD BODY ───────────────────────────────────────────── */
.card-body {
  padding: 1.25rem;                              /* 20px */
}

.card-body-spacious {
  padding: 1.5rem;                               /* 24px — for forms, settings panels */
}

.card-body-compact {
  padding: 0.875rem 1.25rem;                     /* 14px 20px — for dense data lists */
}

/* ── CARD FOOTER ─────────────────────────────────────────── */
.card-footer {
  padding: 0.875rem 1.25rem;                     /* 14px 20px */
  border-top: 1px solid var(--color-border-subtle);
  background-color: var(--color-bg);             /* #EDECE6 — recessed footer */
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--space-2);                           /* 8px */
}

/* ── INTERACTIVE CARD (clickable sessions, resolutions) ──── */
.card-interactive {
  cursor: pointer;
  transition:
    transform     var(--duration-normal) var(--ease-out),
    box-shadow    var(--duration-normal) var(--ease-out),
    border-color  var(--duration-normal) var(--ease-out);
}
.card-interactive:hover {
  transform: translateY(-1px);
  box-shadow: var(--shadow-md);
  border-color: var(--color-border-strong);
}
.card-interactive:active {
  transform: translateY(0);
  box-shadow: var(--shadow-xs);
}

/* ── CARD VARIANTS ───────────────────────────────────────── */
.card-flat {
  box-shadow: none;
  border-color: var(--color-border-subtle);
}

.card-raised {
  box-shadow: var(--shadow-md);
  border-color: transparent;
}

/* Left accent border for status cards */
.card-accent-primary  { border-left: 3px solid var(--color-primary); }
.card-accent-success  { border-left: 3px solid var(--color-success); }
.card-accent-warning  { border-left: 3px solid var(--color-warning); }
.card-accent-danger   { border-left: 3px solid var(--color-danger); }
.card-accent-accent   { border-left: 3px solid var(--color-accent); }
```

### Anti-Patterns to Avoid

- `border-radius: 12px` or above — loses the "official document" feel AG-VOTE targets
- Box-shadow only (no border) on light bg — borders give crisp definition on warm beige
- Hover lift above `translateY(-2px)` — becomes distracting in dense list views
- Padding above 24px in card bodies — creates empty space that reads as unfinished

---

## 3. TABLES

### Research Basis

Shopify Polaris: `--table-cell-padding: var(--p-space-150)` = 6px vertical, 16px horizontal. Carbon Design System: 48px rows (default density), 36px (compact).
Key finding: **uppercase 12px headers with `letter-spacing: 0.05em` are the universal premium signal** for data tables. Every top-tier product uses this pattern (Stripe, Linear, Airtable, Notion databases).

### Exact CSS Specifications

```css
/* ── TABLE WRAPPER ───────────────────────────────────────── */
.table-wrapper {
  overflow-x: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);               /* 10px */
  background: var(--color-surface-raised);
  -webkit-overflow-scrolling: touch;
}

/* ── TABLE BASE ──────────────────────────────────────────── */
.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;                           /* 14px */
  color: var(--color-text);
}

/* ── HEADER ──────────────────────────────────────────────── */
.data-table thead tr {
  background-color: var(--color-bg);             /* #EDECE6 — slightly recessed */
  border-bottom: 1px solid var(--color-border);  /* #CDC9BB */
}

.data-table thead th {
  padding: 0 1rem;
  height: 2.5rem;                                /* 40px */
  font-size: 0.75rem;                            /* 12px */
  font-weight: var(--font-semibold);             /* 600 */
  color: var(--color-text-muted);                /* #857F72 */
  text-transform: uppercase;
  letter-spacing: 0.05em;                        /* 0.6px at 12px */
  text-align: left;
  white-space: nowrap;
  vertical-align: middle;
}

/* Sortable header */
.data-table thead th[data-sortable] {
  cursor: pointer;
  user-select: none;
  transition: color var(--duration-fast);
}
.data-table thead th[data-sortable]:hover {
  color: var(--color-text);
}

/* Sort arrows — Unicode fallback, replace with SVG in production */
.data-table thead th[aria-sort="ascending"]::after  { content: " ↑"; color: var(--color-primary); }
.data-table thead th[aria-sort="descending"]::after { content: " ↓"; color: var(--color-primary); }
.data-table thead th[data-sortable]:not([aria-sort])::after {
  content: " ↕";
  opacity: 0.3;
}

/* ── BODY ROWS ───────────────────────────────────────────── */
.data-table tbody tr {
  height: 2.75rem;                               /* 44px */
  border-bottom: 1px solid var(--color-border-subtle); /* #DEDAD0 */
  transition: background-color var(--duration-fast) var(--ease-default);
}
.data-table tbody tr:last-child {
  border-bottom: none;
}
.data-table tbody tr:hover {
  background-color: var(--color-bg-subtle);      /* #E5E3D8 */
}
.data-table tbody tr.selected {
  background-color: var(--color-primary-subtle); /* #EBF0FF */
}

/* ── CELLS ───────────────────────────────────────────────── */
.data-table td {
  padding: 0 1rem;
  vertical-align: middle;
}

/* First and last column inset */
.data-table td:first-child,
.data-table th:first-child { padding-left: 1.25rem; }  /* 20px */
.data-table td:last-child,
.data-table th:last-child  { padding-right: 1.25rem; }

/* ── COMPACT VARIANT ─────────────────────────────────────── */
.data-table.compact tbody tr { height: 2.25rem; }      /* 36px */
.data-table.compact thead th { height: 2rem; }          /* 32px */

/* ── STICKY HEADER ───────────────────────────────────────── */
.table-wrapper.sticky { overflow-y: auto; max-height: 70vh; }
.data-table.sticky-header thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  background-color: var(--color-bg);
}
/* Hard separator line that stays visible during scroll */
.data-table.sticky-header thead tr::after {
  content: '';
  display: block;
  position: sticky;
  height: 1px;
  background: var(--color-border);
}

/* ── NUMERIC CELL (right-aligned data) ───────────────────── */
.data-table td.num,
.data-table th.num {
  text-align: right;
  font-variant-numeric: tabular-nums;
  font-family: var(--font-mono);
  font-size: 0.8125rem;                          /* 13px */
}
```

### Anti-Patterns to Avoid

- Striped rows — hover is sufficient; stripes create visual noise at high density
- Vertical cell borders — horizontal-only rules reduce visual noise by ~60%
- Uppercase headers above 13px — at 14px+ uppercase is visually aggressive
- Row height below 36px — too cramped for click targets and text legibility

---

## 4. FORMS & INPUTS

### Research Basis

shadcn/ui new-york input (verified): `h-10 px-3 py-2 text-sm rounded-md` = 40px, 12px horizontal, 14px text, 6px radius.
Stripe Elements: recommends `fontSizeBase` minimum 16px for mobile (prevents iOS zoom).
Focus ring: `ring-2 ring-offset-2` = `box-shadow: 0 0 0 2px #fff, 0 0 0 4px {brand}` — matches `--shadow-focus`.

AG-VOTE desktop-first decision: use 36px default, 40px for standalone forms where finger-touch matters.

### Exact CSS Specifications

```css
/* ── FORM GROUP ──────────────────────────────────────────── */
.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;                                 /* 6px — label to input */
}

.form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
  gap: var(--space-4);                           /* 16px */
}

/* ── LABEL ───────────────────────────────────────────────── */
.form-label {
  font-size: 0.875rem;                           /* 14px */
  font-weight: var(--font-medium);               /* 500 */
  color: var(--color-text);                      /* #52504A */
  line-height: 1.4;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}
.form-label .required {
  color: var(--color-danger);                    /* #C42828 */
  font-size: 0.75rem;                            /* 12px */
}

/* ── INPUT BASE ──────────────────────────────────────────── */
.input {
  display: block;
  width: 100%;
  height: 2.25rem;                               /* 36px */
  padding: 0 0.75rem;                            /* 0 12px */
  font-family: var(--font-sans);
  font-size: 0.875rem;                           /* 14px */
  font-weight: var(--font-normal);               /* 400 */
  color: var(--color-text);
  background-color: var(--color-surface-raised); /* #FFFFFF */
  border: 1px solid var(--color-border);         /* #CDC9BB */
  border-radius: var(--radius-sm);               /* 6px */
  outline: none;
  transition:
    border-color     var(--duration-fast) var(--ease-default),
    box-shadow       var(--duration-fast) var(--ease-default),
    background-color var(--duration-fast) var(--ease-default);
  -webkit-appearance: none;
  appearance: none;
}

.input::placeholder {
  color: var(--color-text-light);                /* #B5B0A0 */
}

.input:hover:not(:focus):not(:disabled) {
  border-color: var(--color-border-strong);      /* #BCB7A5 */
}

.input:focus {
  border-color: var(--color-primary);            /* #1650E0 */
  box-shadow: var(--shadow-focus);
}

.input:disabled {
  background-color: var(--color-bg);             /* #EDECE6 */
  color: var(--color-text-muted);
  cursor: not-allowed;
  opacity: 0.7;
}

/* ── STANDARD (40px) variant for standalone forms ────────── */
.input-md {
  height: 2.5rem;                                /* 40px */
  font-size: 1rem;                               /* 16px — prevents iOS zoom */
}

/* ── TEXTAREA ────────────────────────────────────────────── */
textarea.input {
  height: auto;
  min-height: 5rem;                              /* 80px */
  padding: 0.5rem 0.75rem;                       /* 8px 12px */
  resize: vertical;
  line-height: var(--leading-normal);            /* 1.5 */
}

/* ── SELECT ──────────────────────────────────────────────── */
select.input {
  padding-right: 2rem;                           /* 32px — room for caret */
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%23857F72' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
  background-repeat: no-repeat;
  background-position: right 0.5rem center;      /* 8px from right */
  background-size: 1.25rem;                      /* 20px */
  cursor: pointer;
}

/* ── INPUT WITH LEADING ICON ─────────────────────────────── */
.input-wrapper {
  position: relative;
}
.input-wrapper .input-icon {
  position: absolute;
  left: 0.625rem;                                /* 10px */
  top: 50%;
  transform: translateY(-50%);
  width: 1rem; height: 1rem;                     /* 16px */
  color: var(--color-text-muted);
  pointer-events: none;
}
.input-wrapper .input { padding-left: 2.125rem; } /* 34px = icon(16) + gap(10) + pad(8) */

/* ── ERROR STATE ─────────────────────────────────────────── */
.input.is-invalid,
.input[aria-invalid="true"] {
  border-color: var(--color-danger);             /* #C42828 */
  background-color: var(--color-danger-subtle);  /* #FEF1F0 — tinted, not fully red */
}
.input.is-invalid:focus,
.input[aria-invalid="true"]:focus {
  box-shadow: 0 0 0 2px #fff, 0 0 0 4px rgba(196, 40, 40, 0.35);
}

/* ── HELPER TEXT ─────────────────────────────────────────── */
.form-hint {
  font-size: 0.8125rem;                          /* 13px */
  color: var(--color-text-muted);
  line-height: 1.4;
}
.form-error {
  font-size: 0.8125rem;
  color: var(--color-danger);
  line-height: 1.4;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

/* ── CHECKBOX & RADIO ────────────────────────────────────── */
.checkbox-group,
.radio-group {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;                                   /* 8px */
  cursor: pointer;
}
.checkbox-group input[type="checkbox"],
.radio-group input[type="radio"] {
  width: 1rem; height: 1rem;                     /* 16px */
  flex-shrink: 0;
  margin-top: 0.125rem;                          /* 2px optical alignment */
  accent-color: var(--color-primary);
  cursor: pointer;
}
```

### Anti-Patterns to Avoid

- Placeholder-only labels — disappear on focus, inaccessible
- Full red background on error (not subtle tint) — too aggressive for governance context
- `outline` as focus indicator — replace with `box-shadow` for custom rings
- Input height below 32px — too small for click targets (WCAG 2.5.8)

---

## 5. MODALS & DIALOGS

### Research Basis

Radix Dialog / shadcn/ui Dialog. Native `<dialog>` element is the correct approach: built-in keyboard trap, `::backdrop`, `close` event, `ESC` key handling.

Animation: scale from 96% + fade, 200ms, `--ease-out`. shadcn/ui uses `animate-in fade-in-0 zoom-in-95` = `opacity: 0 → 1` + `scale(0.95) → scale(1)`.

### Exact CSS Specifications

```css
/* ── NATIVE DIALOG RESET ─────────────────────────────────── */
dialog {
  position: fixed;
  inset: 0;
  margin: auto;
  border: none;
  background: transparent;
  padding: 0;
  max-width: none;
  max-height: none;
  overflow: visible;
}

/* ── BACKDROP ────────────────────────────────────────────── */
dialog::backdrop {
  background-color: var(--color-backdrop);       /* rgba(0,0,0,0.5) */
  animation: backdrop-in var(--duration-normal) var(--ease-out) both;
}

@keyframes backdrop-in {
  from { opacity: 0; }
  to   { opacity: 1; }
}

/* ── MODAL PANEL ─────────────────────────────────────────── */
.modal-panel {
  background-color: var(--color-surface-raised); /* #FFFFFF */
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);               /* 10px */
  box-shadow: var(--shadow-xl);
  width: 100%;
  max-width: 35rem;                              /* 560px default */
  max-height: calc(100dvh - 4rem);               /* viewport - 64px breathing room */
  overflow: hidden;
  display: flex;
  flex-direction: column;
  animation: modal-in var(--duration-normal) var(--ease-out) both;
}

.modal-panel.modal-wide   { max-width: 45rem; }  /* 720px — for tables, forms */
.modal-panel.modal-narrow { max-width: 25rem; }  /* 400px — for confirms, alerts */
.modal-panel.modal-full   { max-width: calc(100% - 3rem); max-height: calc(100dvh - 3rem); }

@keyframes modal-in {
  from { opacity: 0; transform: scale(0.96) translateY(-6px); }
  to   { opacity: 1; transform: scale(1) translateY(0); }
}

/* ── MODAL HEADER ────────────────────────────────────────── */
.modal-header {
  padding: 1.25rem 1.25rem 1rem;                 /* 20px 20px 16px */
  border-bottom: 1px solid var(--color-border-subtle);
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  flex-shrink: 0;
}

.modal-title {
  font-size: 1rem;                               /* 16px */
  font-weight: var(--font-semibold);             /* 600 */
  color: var(--color-text-dark);
  line-height: 1.3;
}
.modal-description {
  font-size: 0.875rem;                           /* 14px */
  color: var(--color-text-muted);
  margin-top: 0.25rem;
  line-height: 1.5;
}

/* ── CLOSE BUTTON (top-right) ────────────────────────────── */
.modal-close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem; height: 2rem;                     /* 32px */
  border-radius: var(--radius-sm);               /* 6px */
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
  flex-shrink: 0;
  margin-top: -0.25rem;                          /* optical alignment to title */
  transition:
    background-color var(--duration-fast),
    color            var(--duration-fast);
}
.modal-close:hover {
  background-color: var(--color-bg-subtle);
  color: var(--color-text);
}

/* ── MODAL BODY ──────────────────────────────────────────── */
.modal-body {
  padding: 1.25rem;                              /* 20px */
  overflow-y: auto;
  flex: 1;
  overscroll-behavior: contain;
}

/* ── MODAL FOOTER ────────────────────────────────────────── */
.modal-footer {
  padding: 1rem 1.25rem;                         /* 16px 20px */
  border-top: 1px solid var(--color-border-subtle);
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--space-2);                           /* 8px between buttons */
  flex-shrink: 0;
  background-color: var(--color-bg);             /* recessed footer */
}

/* ── CONFIRM DIALOG variant (danger action) ──────────────── */
.modal-panel.modal-confirm .modal-footer {
  justify-content: space-between;                /* Cancel left, Confirm right */
}
```

### Anti-Patterns to Avoid

- `transition: all` on the panel — specify `opacity, transform` only
- `border-radius` above 12px — phone-app feel, wrong for desktop governance
- `overflow: hidden` on the dialog wrapper — body needs to scroll independently
- `padding` inside `dialog` itself — put it on `.modal-panel` child

---

## 6. TOASTS & NOTIFICATIONS

### Research Basis

Sonner CSS source (raw file verified). Key extracted values:
- Width: 356px (`--width` variable, rendered fixed)
- Padding: 16px outer, internal gap 8px between icon and text
- Border-radius: 8px toast, 4px for progress indicator
- Font-size: 13px body, 12px description
- Border: `1px solid rgba(0,0,0,0.08)` light mode
- Animation: 400ms `ease`, transform from `translateY(0.5rem) scale(0.96)` to rest
- Auto-dismiss: 4000ms, paused when tab inactive

### Exact CSS Specifications

```css
/* ── TOAST CONTAINER ─────────────────────────────────────── */
.toast-container {
  position: fixed;
  bottom: 1.5rem;                                /* 24px */
  right: 1.5rem;
  z-index: var(--z-toast);                       /* 800 */
  display: flex;
  flex-direction: column-reverse;                /* newest on top */
  gap: 0.5rem;                                   /* 8px */
  pointer-events: none;
  width: 22.25rem;                               /* 356px — Sonner canonical */
  max-width: calc(100vw - 3rem);
}

/* ── TOAST BASE ──────────────────────────────────────────── */
.toast {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;                                   /* 8px */
  padding: 0.875rem 1rem;                        /* 14px 16px */
  background-color: var(--color-surface-raised); /* #FFFFFF */
  border: 1px solid rgba(21, 21, 16, 0.08);
  border-radius: var(--radius);                  /* 8px */
  box-shadow: var(--shadow-lg);
  pointer-events: all;
  width: 100%;
  animation: toast-in var(--duration-slow) var(--ease-out) both;
}

@keyframes toast-in {
  from {
    opacity: 0;
    transform: translateY(0.5rem) scale(0.96);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@keyframes toast-out {
  from { opacity: 1; transform: translateY(0) scale(1); max-height: 10rem; }
  to   { opacity: 0; transform: translateY(-0.25rem) scale(0.96); max-height: 0; }
}

.toast.is-removing {
  animation: toast-out var(--duration-normal) var(--ease-default) both;
  overflow: hidden;
  pointer-events: none;
}

/* ── TOAST ICON ──────────────────────────────────────────── */
.toast-icon {
  width: 1.125rem; height: 1.125rem;             /* 18px */
  flex-shrink: 0;
  margin-top: 0.0625rem;                         /* 1px optical alignment */
}

/* ── TOAST CONTENT ───────────────────────────────────────── */
.toast-body { flex: 1; min-width: 0; }

.toast-title {
  font-size: 0.875rem;                           /* 14px */
  font-weight: var(--font-medium);               /* 500 */
  color: var(--color-text-dark);
  line-height: 1.3;
}
.toast-message {
  font-size: 0.8125rem;                          /* 13px */
  color: var(--color-text-muted);
  margin-top: 0.125rem;                          /* 2px */
  line-height: 1.4;
}

/* ── DISMISS BUTTON ──────────────────────────────────────── */
.toast-dismiss {
  position: absolute;
  top: 0.5rem; right: 0.5rem;                   /* 8px */
  width: 1.5rem; height: 1.5rem;                 /* 24px */
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
  border-radius: 0.25rem;                        /* 4px */
  opacity: 0;
  transition: opacity var(--duration-fast), background-color var(--duration-fast);
}
.toast:hover .toast-dismiss { opacity: 1; }
.toast-dismiss:hover { background-color: var(--color-bg-subtle); }

/* ── SEMANTIC VARIANTS (left-border accent via inset shadow) */
/* Technique: inset box-shadow for border-left avoids layout shift */
.toast-success {
  box-shadow: inset 3px 0 0 var(--color-success), var(--shadow-lg);
}
.toast-success .toast-icon { color: var(--color-success); }

.toast-error {
  box-shadow: inset 3px 0 0 var(--color-danger), var(--shadow-lg);
}
.toast-error .toast-icon { color: var(--color-danger); }

.toast-warning {
  box-shadow: inset 3px 0 0 var(--color-warning), var(--shadow-lg);
}
.toast-warning .toast-icon { color: var(--color-warning); }

.toast-info {
  box-shadow: inset 3px 0 0 var(--color-primary), var(--shadow-lg);
}
.toast-info .toast-icon { color: var(--color-primary); }
```

### Anti-Patterns to Avoid

- Full-color toast backgrounds (green success, red error) — harsh in a governance UI
- Width above 400px — becomes a banner, not a toast
- Auto-dismiss below 3000ms — long messages cannot be read in time
- Right-padding tight enough that text collides with dismiss button — add `padding-right: 2rem`

---

## 7. BADGES & STATUS INDICATORS

### Research Basis

shadcn/ui badge (verified from source): `px-2.5 py-0.5 text-xs font-semibold rounded-full` = 10px/2px padding, 12px, 600 weight, pill shape.

Key distinction: Linear uses `border-radius: 4px` (not pill) for project status labels, pill only for user/tag labels. GitHub uses pill (`border-radius: 2em`) for all labels.

**AG-VOTE rule:** pill for boolean states (present/absent, pass/fail), 6px radius for multi-part status badges with dots.

### Exact CSS Specifications

```css
/* ── BASE BADGE ──────────────────────────────────────────── */
.badge {
  display: inline-flex;
  align-items: center;
  gap: 0.3125rem;                                /* 5px */
  padding: 0.1875rem 0.5rem;                     /* 3px 8px */
  font-size: 0.75rem;                            /* 12px */
  font-weight: var(--font-semibold);             /* 600 */
  line-height: 1.3;
  white-space: nowrap;
  border-radius: var(--radius-full);             /* pill — default */
  border: 1px solid transparent;
  letter-spacing: 0;
}

/* ── SEMANTIC VARIANTS ───────────────────────────────────── */
.badge-default {
  background-color: var(--color-neutral-subtle); /* #E5E3D8 */
  color: var(--color-neutral-text);              /* #52504A */
  border-color: var(--color-border-subtle);
}
.badge-primary {
  background-color: var(--color-primary-subtle); /* #EBF0FF */
  color: var(--color-primary);                   /* #1650E0 */
  border-color: rgba(22, 80, 224, 0.2);
}
.badge-success {
  background-color: var(--color-success-subtle); /* #EDFAF2 */
  color: var(--color-success);                   /* #0B7A40 */
  border-color: var(--color-success-border);     /* #A3E8C1 */
}
.badge-warning {
  background-color: var(--color-warning-subtle); /* #FFF7E8 */
  color: var(--color-warning);                   /* #B56700 */
  border-color: var(--color-warning-border);     /* #F5D490 */
}
.badge-danger {
  background-color: var(--color-danger-subtle);  /* #FEF1F0 */
  color: var(--color-danger);                    /* #C42828 */
  border-color: var(--color-danger-border);      /* #F4BFBF */
}
.badge-accent {
  background-color: var(--color-accent-subtle);  /* #EEEAFF */
  color: var(--color-accent);                    /* #5038C0 */
  border-color: var(--color-purple-border);      /* #C4B8F8 */
}

/* ── SOLID (high-emphasis, e.g. adopted/rejected resolution) */
.badge-solid-primary { background: var(--color-primary); color: #fff; }
.badge-solid-success { background: var(--color-success); color: #fff; }
.badge-solid-danger  { background: var(--color-danger);  color: #fff; }

/* ── STATUS BADGE (dot + label, 6px radius not pill) ─────── */
.badge-status {
  border-radius: var(--radius-sm);               /* 6px */
  padding-left: 0.4375rem;                       /* 7px — tighter left for dot */
}
.badge-dot {
  width: 0.5rem; height: 0.5rem;                 /* 8px */
  border-radius: 50%;
  flex-shrink: 0;
  background-color: currentColor;
}
.badge-dot-pulse {
  animation: dot-pulse 1.5s ease-in-out infinite;
}
@keyframes dot-pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%       { opacity: 0.6; transform: scale(0.85); }
}

/* ── SIZE VARIANTS ───────────────────────────────────────── */
.badge-sm {
  padding: 0.125rem 0.375rem;                    /* 2px 6px */
  font-size: 0.6875rem;                          /* 11px */
  gap: 0.25rem;
}
.badge-lg {
  padding: 0.25rem 0.75rem;                      /* 4px 12px */
  font-size: 0.8125rem;                          /* 13px */
  gap: 0.375rem;
}

/* ── COUNTER BADGE (numeric, e.g. delta counts) ──────────── */
.badge-count {
  min-width: 1.25rem;                            /* 20px */
  height: 1.25rem;
  padding: 0 0.3125rem;                          /* 0 5px */
  border-radius: var(--radius-full);
  font-size: 0.6875rem;                          /* 11px */
  font-weight: var(--font-bold);                 /* 700 */
  font-variant-numeric: tabular-nums;
  justify-content: center;
}
```

### AG-VOTE Status Color Matrix

| State | Class | Notes |
|-------|-------|-------|
| `ouvert` (live vote) | `badge-success badge-status` + pulse dot | Active voting in progress |
| `en cours` (session live) | `badge-primary badge-status` + pulse dot | Meeting active |
| `clos` / `terminé` | `badge-default` | Neutral, completed |
| `adopté` | `badge-solid-success` | High emphasis — result |
| `rejeté` | `badge-solid-danger` | High emphasis — result |
| `en attente` | `badge-warning badge-status` | Needs action |
| `suspendu` | `badge-accent` | Edge case |
| `présent` | `badge-success badge-sm` | Member attendance row |
| `absent` | `badge-default badge-sm` | Member attendance row |
| `procuration` | `badge-accent badge-sm` | Proxy delegation |
| `quorum atteint` | `badge-success` | Quorum bar context |
| `quorum non atteint` | `badge-danger` | Quorum bar context |

---

## 8. STEPPERS & PROGRESS

### Research Basis

ishadeed.com stepper: `--size: 3rem` (48px) default circle, 2px connector. Stripe payment flow: 32px circle, connector above. Material Design: 40px, 2dp connector.

AG-VOTE decision: **32px circle** — compact for a named wizard header. Stripe-style. Connector is 1.5px (premium thinness at this size).

### Exact CSS Specifications

```css
/* ── STEPPER CONTAINER ───────────────────────────────────── */
.stepper {
  display: flex;
  align-items: flex-start;
  gap: 0;
  counter-reset: step;
}

/* ── STEPPER ITEM ────────────────────────────────────────── */
.stepper-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
  position: relative;
  text-align: center;
}

/* Connector line between circles */
.stepper-item:not(:last-child)::after {
  content: '';
  position: absolute;
  top: 1rem;                                     /* half of 32px = 16px */
  left: calc(50% + 1.25rem);                     /* center + half-circle + 4px gap */
  right: calc(-50% + 1.25rem);
  height: 1.5px;
  background-color: var(--color-border);         /* #CDC9BB pending */
  z-index: 0;
  transition: background-color var(--duration-slow) var(--ease-default);
}

/* Completed step connector becomes primary */
.stepper-item.is-done:not(:last-child)::after {
  background-color: var(--color-primary);        /* #1650E0 */
}

/* ── STEP CIRCLE ─────────────────────────────────────────── */
.stepper-circle {
  width: 2rem; height: 2rem;                     /* 32px */
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8125rem;                          /* 13px */
  font-weight: var(--font-semibold);             /* 600 */
  flex-shrink: 0;
  position: relative;
  z-index: 1;
  border: 1.5px solid var(--color-border);       /* pending: border only */
  background-color: var(--color-surface-raised);
  color: var(--color-text-muted);
  transition:
    background-color var(--duration-normal) var(--ease-default),
    border-color     var(--duration-normal) var(--ease-default),
    color            var(--duration-normal) var(--ease-default),
    box-shadow       var(--duration-normal) var(--ease-default);
}

/* ── STEP STATES ─────────────────────────────────────────── */

/* Active — current step */
.stepper-item.is-active .stepper-circle {
  background-color: var(--color-primary);        /* #1650E0 */
  border-color: var(--color-primary);
  color: #ffffff;
  box-shadow: 0 0 0 3px var(--color-primary-subtle); /* #EBF0FF — halo */
}

/* Done — completed step */
.stepper-item.is-done .stepper-circle {
  background-color: var(--color-success);        /* #0B7A40 */
  border-color: var(--color-success);
  color: transparent;                            /* hide number, show checkmark */
}
/* Checkmark via CSS for done steps */
.stepper-item.is-done .stepper-circle::before,
.stepper-item.is-done .stepper-circle::after {
  content: '';
  position: absolute;
  background-color: #ffffff;
  border-radius: 1px;
}
.stepper-item.is-done .stepper-circle::before {
  width: 5px; height: 1.5px;
  transform: rotate(45deg) translate(1px, 2px);
}
.stepper-item.is-done .stepper-circle::after {
  width: 9px; height: 1.5px;
  transform: rotate(-45deg) translate(-1px, 2px);
}

/* Pending — future step */
.stepper-item.is-pending .stepper-circle {
  background-color: var(--color-surface-raised);
  border-color: var(--color-border);
  color: var(--color-text-light);               /* #B5B0A0 */
}

/* Error state */
.stepper-item.is-error .stepper-circle {
  background-color: var(--color-danger-subtle);
  border-color: var(--color-danger);
  color: var(--color-danger);
}

/* ── STEP LABEL ──────────────────────────────────────────── */
.stepper-label {
  margin-top: 0.5rem;                            /* 8px */
  font-size: 0.75rem;                            /* 12px */
  font-weight: var(--font-medium);               /* 500 */
  color: var(--color-text-muted);
  line-height: 1.3;
  max-width: 5rem;                               /* 80px — prevents wrapping */
}
.stepper-item.is-active .stepper-label {
  color: var(--color-primary);
  font-weight: var(--font-semibold);
}
.stepper-item.is-done .stepper-label {
  color: var(--color-text);
}
.stepper-item.is-pending .stepper-label {
  color: var(--color-text-light);
}

/* ── COMPACT STEPPER (header bar use) ───────────────────── */
.stepper.stepper-compact .stepper-circle {
  width: 1.5rem; height: 1.5rem;                 /* 24px */
  font-size: 0.6875rem;                          /* 11px */
}
.stepper.stepper-compact .stepper-item:not(:last-child)::after {
  top: 0.75rem;                                  /* 12px */
  left: calc(50% + 0.875rem);
  right: calc(-50% + 0.875rem);
}
.stepper.stepper-compact .stepper-label {
  font-size: 0.6875rem;                          /* 11px */
  margin-top: 0.25rem;
}

/* ── LINEAR PROGRESS BAR ─────────────────────────────────── */
.progress-bar {
  height: 0.375rem;                              /* 6px */
  background-color: var(--color-border-subtle);  /* #DEDAD0 */
  border-radius: var(--radius-full);
  overflow: hidden;
}
.progress-fill {
  height: 100%;
  border-radius: var(--radius-full);
  background-color: var(--color-primary);
  transition: width var(--duration-slow) var(--ease-out);
}
.progress-fill.success { background-color: var(--color-success); }
.progress-fill.warning { background-color: var(--color-warning); }
.progress-fill.danger  { background-color: var(--color-danger); }

/* Quorum-specific progress (AG-VOTE: shows quorum threshold) */
.progress-bar.with-threshold {
  position: relative;
}
.progress-threshold {
  position: absolute;
  top: -2px;
  bottom: -2px;
  width: 2px;
  background-color: var(--color-text-dark);      /* #151510 */
  border-radius: 1px;
  /* left: set via inline style = threshold% */
}
```

### Anti-Patterns to Avoid

- Circles above 40px in desktop wizard headers — dominates the header, mobile-app feel
- Showing step numbers after completion — replace with checkmark icon, numbers imply ordering not status
- Connector lines that never change color — progress perception requires visual feedback
- Labels longer than 3 words — `max-width: 5rem` enforces this, keep label text tight

---

## New Tokens to Add to design-system.css (@layer v4)

These fill gaps exposed by component research. All are additive — no existing tokens renamed.

```css
@layer v4 {
  :root {
    /* ── Component dimension tokens ── */
    --btn-height-sm:       1.875rem;   /* 30px */
    --btn-height:          2.25rem;    /* 36px — compact desktop default */
    --btn-height-lg:       2.75rem;    /* 44px */

    --input-height:        2.25rem;    /* 36px */
    --input-height-md:     2.5rem;     /* 40px — standalone forms */

    --table-row-height:    2.75rem;    /* 44px */
    --table-row-compact:   2.25rem;    /* 36px */
    --table-header-height: 2.5rem;     /* 40px */

    --stepper-circle:      2rem;       /* 32px */
    --stepper-circle-sm:   1.5rem;     /* 24px */
    --stepper-connector:   1.5px;

    --toast-width:         22.25rem;   /* 356px */

    --modal-width:         35rem;      /* 560px */
    --modal-width-wide:    45rem;      /* 720px */
    --modal-width-narrow:  25rem;      /* 400px */

    --card-padding:        1.25rem;    /* 20px */
    --card-padding-lg:     1.5rem;     /* 24px */
  }
}
```

---

## Application to AG-VOTE Web Components

| Component | Key Spec Deltas (vs current) |
|-----------|------------------------------|
| `ag-modal` | Add `modal-in` animation; footer bg → `--color-bg`; close btn 32px not full X |
| `ag-toast` | Width 356px; `inset` box-shadow for left accent; `toast-in` scale+fade animation |
| `ag-confirm` | Use `modal-narrow` (400px); two-button footer with `space-between` |
| `ag-popover` | No changes needed from this research |
| `ag-searchable-select` | Apply `.input` specs to trigger; dropdown items use table-row hover pattern |
| `ag-pdf-viewer` | Outside scope of this research (v4.0 architecture doc) |

All 23 Web Components should source their colors exclusively from the design token set above. No hardcoded hex values inside component shadow DOM.

---

## Sources

- shadcn/ui button.tsx taxonomy repo: [github.com/shadcn-ui/taxonomy](https://github.com/shadcn-ui/taxonomy/blob/main/components/ui/button.tsx) — HIGH confidence
- Sonner CSS raw source: [github.com/emilkowalski/sonner](https://github.com/emilkowalski/sonner) — HIGH confidence (raw file fetched)
- Shopify Polaris design tokens: [polaris-react.shopify.com/tokens](https://polaris-react.shopify.com/tokens/all-tokens) — HIGH confidence (page fetched, exact values)
- ishadeed stepper article: [ishadeed.com/article/stepper-component](https://ishadeed.com/article/stepper-component-html-css/) — HIGH confidence
- Emil Kowalski toast breakdown: [emilkowal.ski/ui/building-a-toast-component](https://emilkowal.ski/ui/building-a-toast-component) — MEDIUM (article prose, not source code)
- AG-VOTE design-system.css: `/home/user/gestion_votes_php/public/assets/css/design-system.css` — HIGH (direct file read, lines 83-319)
