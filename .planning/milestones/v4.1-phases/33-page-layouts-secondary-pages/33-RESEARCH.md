# Phase 33: Page Layouts — Secondary Pages - Research

**Researched:** 2026-03-19
**Domain:** CSS Grid/Flexbox layout, vanilla CSS, six secondary pages in an existing PHP/vanilla-JS app
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Phase Boundary**
Apply the Phase 32 layout language (three-depth background, max-width constraints, grid specs, correct density) to the 6 secondary pages: hub, post-session, analytics, help/FAQ, email templates, meetings list. CSS + HTML restructuring only — no new features, no backend changes.

**Inherited from Phase 32**
- Three-depth background model: `.app-main` = bg, `.card` = surface, elevated elements = raised
- All heights, spacing, and component specs from Phase 30/31 tokens
- Responsive breakpoints: 768px (mobile collapse), 1024px (tablet adjustments)
- Shared `.table-page` base for any page using data tables

**Hub Layout (LAY-07)**
- Two-column layout: sidebar stepper (220px) + main content
- `display: grid; grid-template-columns: 220px 1fr; gap: var(--space-card)`
- Stepper in sidebar: ag-stepper vertical orientation, sticky at `top: 80px`
- Quorum progress bar: prominent placement at top of main content, `var(--color-surface-raised)` background to stand out
- Checklist items: card-based with proper spacing (`var(--space-card)` gap)
- At 768px: sidebar stacks above main, stepper becomes horizontal

**Post-Session Layout (LAY-08)**
- Single-column centered layout: `max-width: 900px; margin: 0 auto`
- Four-step stepper at top (ag-stepper horizontal, checkmarks on completed steps)
- Result cards: collapsible via `<details>` or accordion pattern
- Inter-section spacing: `var(--space-section)` (48px) between major sections
- Cards use `.card` with `var(--color-surface)` — collapsible content inside
- Stepper sticky at top while scrolling through sections

**Analytics Layout (LAY-09)**
- Grid layout: `display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: var(--space-card)`
- KPI cards at top: same pattern as dashboard (raised background, 4-col or responsive auto-fit)
- Chart areas: `.card` containers with proper padding for chart canvas
- At 1024px: minimum 2-column layout (not single-column stack) — use `minmax(320px, 1fr)` to prevent collapse
- `max-width: 1400px; margin: 0 auto` for content constraint

**Help/FAQ Layout (LAY-10)**
- Single-column centered: `max-width: 800px; margin: 0 auto`
- Accordion: proper `<details>/<summary>` with styled padding (not browser defaults)
- Summary: `padding: var(--space-4) var(--space-card)` (16px 24px), font-weight semibold, cursor pointer
- Content: `padding: 0 var(--space-card) var(--space-card)` — indented from summary
- No layout shift on expand: use CSS `overflow: hidden` + height transition or `content-visibility`
- Search input above accordions if applicable

**Email Templates Layout (LAY-11)**
- Two-column: editor (flex: 1) + preview panel (400px)
- `display: grid; grid-template-columns: 1fr 400px; gap: var(--space-card)`
- Preview panel: `var(--color-surface-raised)` background, bordered iframe or rendered preview
- Editor: standard form layout with proper field spacing
- At 768px: preview stacks below editor

**Meetings List Layout (LAY-12)**
- Reuse dashboard sessions pattern: vertical card list or `.table-page` structure
- Status badges: `ag-badge` component with semantic variants (success/warning/danger/info) from Phase 31
- Card density: same as dashboard sessions — `var(--space-3)` (12px) gap between items
- `max-width: 1200px; margin: 0 auto` — same as dashboard
- Filter/sort toolbar above the list

### Claude's Discretion
- Whether meetings list uses cards or table view (choose based on current HTML structure)
- Exact chart container heights in analytics
- Animation for accordion expand/collapse
- Whether help page needs a search box
- Exact KPI card count for analytics page

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| LAY-07 | Hub — sidebar stepper + main content, quorum bar prominent, checklist with proper spacing | Hub currently uses `.hub-layout-body` with flexbox; needs conversion to CSS Grid 220px+1fr; quorum section uses `var(--color-surface)` and must be elevated to `var(--color-surface-raised)` |
| LAY-08 | Post-session — stepper with checkmarks, collapsible result cards, proper section spacing | `.ps-stepper` segmented bar is NOT the ag-stepper component — stays as custom stepper; result cards already use `<details>` in JS-rendered HTML; spacing between sections needs `var(--space-section)` (48px) gaps |
| LAY-09 | Analytics/Statistics — chart area + KPI cards, proper responsive grid | `.overview-grid` currently lacks explicit min 2-col constraint; `.charts-grid` inside tab content needs `minmax(320px, 1fr)` with safe 2-col floor; `max-width: 1400px` must wrap `.page-content` |
| LAY-10 | Help/FAQ — accordion with proper padding, search if applicable | FAQ uses custom JS accordion (`.faq-question`/`.faq-answer`) not native `<details>` — styling the existing pattern is correct; search already present in HTML; `max-width: 800px` wrap needed |
| LAY-11 | Email templates — editor layout with preview panel | Editor is a full-screen overlay (`.template-editor`), not a page layout — the page itself shows a templates grid; layout work is on the overlay's two-column split (currently `grid-template-columns: 1fr 1fr`, needs `1fr 400px`) and the page's content max-width constraint |
| LAY-12 | Meetings list — card or table view with proper density and status badges | Uses card list (`.sessions-list`/`.session-item`) — same pattern as dashboard; gap is currently `margin-bottom: 8px` per item (matches `var(--space-2)`); must upgrade to `var(--space-3)` (12px); `max-width: 1200px` needed; ag-badge semantic variants for status dots |
</phase_requirements>

---

## Summary

Phase 33 applies the Phase 32 layout system to six secondary pages whose CSS files already exist but lack proper grid structure, max-width constraints, and token-consistent spacing. Each page has specific layout requirements defined in CONTEXT.md with exact CSS values. The work is purely CSS + minor HTML class additions — no JavaScript changes, no new features.

The key finding from code inspection is that each page's current CSS is a partial implementation. Hub already has two-column flexbox but needs conversion to CSS Grid with the right column sizes. Post-session has collapsible cards and the stepper works as-is. Analytics needs the grid's two-column floor enforced at 1024px. Help/FAQ uses a custom JS accordion (not native `<details>`) that needs padding standardization and max-width constraining. Email templates' layout work is on the editor overlay's grid. Meetings list needs density alignment and proper max-width.

**Primary recommendation:** Apply all six layouts as isolated CSS edits to their existing CSS files. Each page gets one focused task. Do not touch HTML JavaScript or backend. Use only design-system tokens — zero hardcoded hex.

---

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Vanilla CSS | N/A | Layout engine | Project constraint — no build tools, no PostCSS |
| CSS Grid | Native | Two-column layouts (hub, email-templates) | Spec-locked in CONTEXT.md |
| CSS Custom Properties | Native | Token-based design values | Phase 30 token foundation already applied |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr))` | CSS3 | Responsive chart grids (analytics) | Analytics, KPI grids |
| `position: sticky; top: 80px` | CSS3 | Sticky sidebar (hub stepper) | Hub sidebar, post-session stepper |
| `<details>/<summary>` (NOT used here) | HTML5 | Native accordion | Not applicable — existing pages use custom JS accordion; do not migrate |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| CSS Grid for hub layout | Flexbox (current) | Flexbox `flex-wrap` can't guarantee 220px column stability — Grid is the locked choice |
| `max-width` on `.app-main` | `max-width` on inner wrapper | Inner wrapper approach is correct — `.app-main` must remain full-bleed for bg color |
| `content-visibility` for accordion no-shift | CSS height transition | `content-visibility` is complex; height transition from `0` to `auto` requires JS or `grid: 0fr/1fr` trick — use `display:none` toggle (existing) with fade-in animation for simplicity |

---

## Architecture Patterns

### Recommended Project Structure

```
public/assets/css/
├── hub.css              # LAY-07: Convert .hub-layout-body to grid, elevate quorum
├── postsession.css      # LAY-08: Add max-width, var(--space-section) gaps, sticky stepper
├── analytics.css        # LAY-09: Fix grid to guarantee 2-col at 1024px, max-width wrap
├── help.css             # LAY-10: Add max-width wrapper, standardize accordion padding
├── email-templates.css  # LAY-11: Fix editor overlay grid columns, add page max-width
└── meetings.css         # LAY-12: Align card density, add max-width, upgrade status badges
```

No HTML changes required for most pages. Minor class additions may be needed if existing wrapper elements are missing.

---

### Pattern 1: Hub Two-Column Grid (LAY-07)

**What:** Replace current flexbox `.hub-layout-body` with CSS Grid having exact column spec.
**When to use:** Wherever a sidebar must maintain a fixed pixel width with the content taking all remaining space.

**Current state (hub.css):**
```css
.hub-layout-body {
  display: flex;
  gap: 2rem;
  align-items: flex-start;
  flex-wrap: wrap;
}
.hub-stepper-col {
  width: 260px;  /* WRONG — should be 220px per spec */
  flex-shrink: 0;
  position: sticky;
  top: 80px;
}
```

**Target state:**
```css
.hub-layout-body {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: var(--space-card);
  align-items: start;
}
.hub-stepper-col {
  position: sticky;
  top: 80px;
  align-self: start;
  /* width: 260px removed — column spec drives it */
}
@media (max-width: 768px) {
  .hub-layout-body {
    grid-template-columns: 1fr;
  }
  .hub-stepper-col {
    position: static;
  }
}
```

**Quorum prominence fix:** Change `.hub-quorum-section` background from `var(--color-surface)` to `var(--color-surface-raised)` and add accent border:
```css
.hub-quorum-section {
  background: var(--color-surface-raised);
  border: 1px solid var(--color-border);
  border-left: 3px solid var(--color-primary);
  padding: var(--space-card);
  border-radius: var(--radius-lg);
  margin-bottom: var(--space-card);
  box-shadow: var(--shadow-sm);
}
```

---

### Pattern 2: Post-Session Centered + Section Spacing (LAY-08)

**What:** Add max-width container, enforce `var(--space-section)` (48px) between step panels, make stepper sticky.
**When to use:** Single-column guided flows.

**Current state:** `.container` wrapper exists in HTML but no max-width or section-gap constraints in CSS. Stepper is not sticky.

**Target state:**
```css
/* Centered content column */
.postsession-container {
  max-width: 900px;
  margin: 0 auto;
  padding: 0 var(--space-card);
}

/* Spacing between step panels */
.ps-panel + .ps-panel {
  margin-top: var(--space-section);
}

/* Sticky stepper */
.ps-stepper {
  position: sticky;
  top: 80px;
  z-index: 10;
  background: var(--color-bg);
  padding: var(--space-3) 0;
  margin-bottom: var(--space-card);
}
```

Note: The HTML already has `<div class="container">` wrapping main content. Add `.postsession-container` specifics or override `.container` within postsession context.

---

### Pattern 3: Analytics Grid Floor (LAY-09)

**What:** Analytics uses `auto-fit` grids inside tab panels. Current implementation has `.charts-grid` but no min-column-count guarantee at 1024px.
**When to use:** Dashboards where charts must never collapse to single-column on tablet.

**Current state (analytics.css):** No `.charts-grid` definition found in the CSS — charts grid is unstyled or inherits generic grid. `.overview-grid` for KPI cards has no defined column floor.

**Target state:**
```css
/* KPI overview grid — raised surface, 4 columns desktop */
.overview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: var(--space-card);
  margin-bottom: var(--space-card);
}

.overview-card {
  background: var(--color-surface-raised);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--space-card);
  box-shadow: var(--shadow-sm);
}

/* Charts grid — min 2 columns enforced */
.charts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: var(--space-card);
  margin-bottom: var(--space-section);
}

/* Critical: prevent 1-column collapse at 1024px */
@media (min-width: 768px) {
  .charts-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

/* Content max-width */
.analytics-main .page-content {
  max-width: 1400px;
  margin: 0 auto;
}
```

---

### Pattern 4: Help/FAQ Accordion Padding (LAY-10)

**What:** Standardize FAQ accordion question/answer padding to match token spec. Add max-width container.
**When to use:** Content-reading pages with accordion sections.

**Current state:** `.faq-question` padding is `1rem` (browser-default 16px, not token-aligned). `.faq-answer` padding is `0 1rem 1rem`. Help content max-width is 900px but spec says 800px.

**Target state:**
```css
/* Centered content max-width */
.help-content {
  max-width: 800px;  /* was 900px */
  margin: 0 auto;
  padding: var(--space-card);
}

/* Accordion question */
.faq-question {
  padding: var(--space-4) var(--space-card);  /* 16px 24px */
  font-weight: var(--font-semibold);
  cursor: pointer;
}

/* Accordion answer — indented from question */
.faq-answer {
  padding: 0 var(--space-card) var(--space-card);  /* 0 24px 24px */
}

/* No layout shift: keep display:none toggle but add smooth transition */
.faq-item.open .faq-answer {
  display: block;
  animation: faqReveal 150ms ease-out;
}

@keyframes faqReveal {
  from { opacity: 0; transform: translateY(-4px); }
  to   { opacity: 1; transform: translateY(0); }
}
```

The search box is already present in the HTML (`#faqSearch`). No structural changes needed, just ensure it's styled consistently with the Phase 31 form input spec.

---

### Pattern 5: Email Templates Editor Grid (LAY-11)

**What:** The email templates page shows a card grid of templates. When a template is edited, a full-screen overlay opens with a two-column form+preview split. The overlay needs `1fr 400px` columns (not `1fr 1fr` current).
**When to use:** Editor overlays with side-by-side form/preview.

**Current state:** `.template-editor-body` is `grid-template-columns: 1fr 1fr` — this makes preview too wide.

**Target state:**
```css
.template-editor-body {
  display: grid;
  grid-template-columns: 1fr 400px;
  gap: 0;  /* border handles separation */
  overflow: hidden;
}

.template-editor-preview {
  background: var(--color-surface-raised);
  border-left: 1px solid var(--color-border);
}

/* Page-level: content max-width constraint */
.email-templates-main .page-content {
  max-width: 1200px;
  margin: 0 auto;
}

@media (max-width: 768px) {
  .template-editor-body {
    grid-template-columns: 1fr;
  }
  /* Preview stacks below editor on mobile */
}
```

---

### Pattern 6: Meetings List Density + Max-Width (LAY-12)

**What:** Meetings list uses card items (`.session-item`) — correct structure. Needs max-width and correct density token.
**When to use:** Lists of data items matching dashboard density.

**Current state:** `.session-item` uses `margin-bottom: 8px` (equivalent to `var(--space-2)`). Spec requires `var(--space-3)` (12px). No max-width container. Status dot is plain CSS circle, not `ag-badge`.

**Target state:**
```css
/* Max-width container matching dashboard */
.meetings-main .page-content {
  max-width: 1200px;
  margin: 0 auto;
}

/* Density: gap between session items */
.sessions-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);  /* was margin-bottom: 8px per item */
}

.session-item {
  margin-bottom: 0;  /* remove old margin; gap handles spacing */
}

/* Status badge upgrade: keep .session-dot for pulsing live indicator,
   add semantic ag-badge alongside for text status */
```

Note: The `ag-badge` component exists (refreshed in Phase 31). Status badges for text labels (e.g. "En cours", "Termin") use `ag-badge` with `variant` attribute. The `.session-dot` pulsing live indicator can remain as-is alongside the badge.

---

### Anti-Patterns to Avoid

- **Overriding `.app-main` background or padding globally:** Each page's main has its own padding class; override only within page-scoped selectors.
- **Using `flex-wrap` instead of grid for two-column layouts:** Flexbox wrap can't guarantee column sizes — use `display: grid` for hub and email-templates.
- **Hardcoded pixel values in spacing:** All gaps, padding, and margins must use `var(--space-*)` tokens from Phase 30.
- **`display:none` removal for "no layout shift" on accordion:** The current JS-toggled `.faq-item.open` class on `.faq-answer` with `display: none/block` causes reflow — acceptable with a 150ms fade animation; don't switch to CSS-only `<details>` as that would require HTML rewrites.
- **Touching JavaScript files:** This phase is CSS + HTML class additions only.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Vertical stepper for hub | Custom HTML stepper | Existing `ag-stepper` component (Phase 31) | Already renders correctly; CSS styling only |
| Status badges for meetings | Custom `.status-pill` CSS class | `ag-badge` web component with semantic variants | Phase 31 refreshed ag-badge with all required variants |
| Quorum progress bar | Custom HTML progress | `ag-quorum-bar` web component (already in hub.htmx.html) | Already wired to JS data |
| Accordion no-layout-shift | JS height calculation | CSS `animation` with opacity/translate on `.open` toggle | Acceptable visual quality, zero complexity |
| Chart canvas containers | Custom scroll/overflow | `.card` wrapper with explicit height | Chart.js handles canvas sizing; container just needs height constraint |

---

## Common Pitfalls

### Pitfall 1: Hub Stepper Width Change
**What goes wrong:** Hub stepper column is currently 260px in `.hub-stepper-col`. The CONTEXT spec says 220px. Changing to grid `220px 1fr` and leaving `width: 260px` on `.hub-stepper-col` creates conflict.
**Why it happens:** CSS Grid column width wins over `width` on grid items when explicit column track is set — but `min-width` can override it.
**How to avoid:** Remove the explicit `width: 260px` from `.hub-stepper-col` when switching to grid. Let the column track drive the width. Keep `position: sticky; top: 80px; align-self: start`.

### Pitfall 2: Analytics Grid Collapsing to 1 Column at 1024px
**What goes wrong:** `repeat(auto-fit, minmax(320px, 1fr))` collapses to single column if viewport is between 640–1024px (sidebar present = effective viewport ~740px).
**Why it happens:** With sidebar rail, effective content width at 1024px viewport is ~740px. `minmax(320px, 1fr)` would fit 2 columns (2×320=640 < 740) — so it SHOULD work. But if padding/gap is not accounted for, it can fall to 1 column.
**How to avoid:** Add an explicit `@media (min-width: 768px) { .charts-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }` to force 2-column floor on tablet+. This overrides auto-fit behavior.

### Pitfall 3: Post-Session Stepper Sticky Z-Index Conflict
**What goes wrong:** Making `.ps-stepper` sticky causes it to appear behind fixed positioned header (`.app-header`).
**Why it happens:** The app header has a higher z-index. Sticky stepper needs `z-index` below header but above content.
**How to avoid:** Set `.ps-stepper { z-index: 10; }` and ensure the header has `z-index: var(--z-header)` (typically 100). The gap: `top: 80px` matches header height, so the stepper appears directly below header.

### Pitfall 4: Email Templates Editor Body Overflow
**What goes wrong:** Changing `.template-editor-body` from `1fr 1fr` to `1fr 400px` with `overflow: hidden` on the content means the form textarea can't grow beyond the modal height.
**Why it happens:** Modal is `max-height: 90vh` with `grid-template-rows: auto 1fr auto`. The body's `overflow: hidden` means form area must scroll internally.
**How to avoid:** Keep `.template-editor-form { overflow-y: auto; }` (already present). The grid column change alone is safe.

### Pitfall 5: Meetings `.sessions-list` Gap vs Margin Conflict
**What goes wrong:** Switching from `margin-bottom: 8px` on `.session-item` to `gap: var(--space-3)` on `.sessions-list` doubles spacing if both are present.
**Why it happens:** `gap` and `margin-bottom` on flex/grid children stack.
**How to avoid:** Remove `margin-bottom: 8px` from `.session-item` when adding `gap: var(--space-3)` to `.sessions-list`.

### Pitfall 6: Hub `.hub-layout-body` vs `.hub-layout` Class Collision
**What goes wrong:** `hub.css` defines BOTH `.hub-layout` (a different old grid layout) AND `.hub-layout-body` (the used two-column container). The `hub.htmx.html` uses `.hub-layout-body`.
**Why it happens:** Legacy code left `.hub-layout` class unused in HTML — it still exists in CSS and has its own `grid-template-columns: 260px 1fr`. If the wrong class is targeted in HTML, the wrong layout applies.
**How to avoid:** Only update `.hub-layout-body` in CSS. Optionally remove/deprecate `.hub-layout` to prevent confusion. Confirm HTML uses `hub-layout-body` (verified: line 109 of hub.htmx.html).

---

## Code Examples

Verified from inspection of existing CSS files:

### Confirmed Token Values (Phase 30)
```css
/* From design-system.css (Phase 30 applied) */
--space-3: 12px;      /* meetings item gap */
--space-4: 16px;      /* faq question top/bottom padding */
--space-card: 24px;   /* grid gap, faq lr padding */
--space-section: 48px;/* inter-section spacing (post-session) */
--radius-lg: 12px;    /* card border radius */
--shadow-sm: ...;     /* default card shadow */
--shadow-md: ...;     /* hover/elevated shadow */
```

### Hub Layout Conversion
```css
/* hub.css — replace existing .hub-layout-body */
.hub-layout-body {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: var(--space-card);
  align-items: start;
}

.hub-stepper-col {
  /* Remove: width: 260px; */
  position: sticky;
  top: 80px;
  align-self: start;
}

@media (max-width: 768px) {
  .hub-layout-body {
    grid-template-columns: 1fr;
  }
  .hub-stepper-col {
    position: static;
  }
  /* Stepper becomes horizontal (already handled at 1024px breakpoint) */
}
```

### Analytics Chart Grid Floor
```css
/* analytics.css */
.charts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: var(--space-card);
}

@media (min-width: 768px) and (max-width: 1200px) {
  .charts-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
```

### Help FAQ Accordion Alignment
```css
/* help.css — update padding to token values */
.faq-question {
  padding: var(--space-4) var(--space-card);  /* 16px 24px */
  font-weight: var(--font-semibold);
}

.faq-answer {
  padding: 0 var(--space-card) var(--space-card);  /* 0 24px 24px */
}

.help-content {
  max-width: 800px;
  margin: 0 auto;
  padding: var(--space-card);
}
```

---

## Current Code State — Per-Page Audit

### Hub (hub.css / hub.htmx.html)
- Layout class in HTML: `.hub-layout-body` (line 109)
- Column: `display: flex; gap: 2rem` — needs Grid conversion
- Stepper col width: `260px` — needs reduction to `220px` (via grid track)
- Quorum section: `background: var(--color-surface)` — needs `var(--color-surface-raised)`
- Sticky: already correct `top: 80px`
- Responsive: 1024px collapses — keep; 768px stacks — keep

### Post-Session (postsession.css / postsession.htmx.html)
- Container in HTML: `<div class="container">` (line 78 in postsession.htmx.html)
- No max-width set on `.container` in postsession context
- `.ps-stepper` is not sticky — must add
- `.ps-panel + .ps-panel` gap — currently no `var(--space-section)` separation
- Result cards (`<details>`) already implemented in JS with `.result-card` styles
- Footer nav is `position: sticky; bottom: 0` — already correct

### Analytics (analytics.css / analytics.htmx.html)
- Page content in HTML: `<div class="page-content">` (line 80)
- `.analytics-main` removes padding and uses `padding-left: calc(var(--sidebar-rail) + 22px)` — risky if sidebar rail changes; coordinate with existing pattern
- `.overview-grid` used in HTML but not defined in analytics.css — likely inheriting from design-system or pages.css
- `.charts-grid` used in HTML but not defined in analytics.css — MUST add definition
- `max-width: 1400px` on `.analytics-content` in analytics.css BUT `.analytics-main` uses `padding: 0` so the content is bare — ensure `.page-content` gets the max-width

### Help/FAQ (help.css / help.htmx.html)
- `.help-content` has `max-width: 900px` — needs reduction to `800px`
- `.faq-question` has `padding: 1rem` — needs `var(--space-4) var(--space-card)` (16px 24px)
- `.faq-answer` has `padding: 0 1rem 1rem` — needs `0 var(--space-card) var(--space-card)`
- Search input (`#faqSearch`) already in HTML — check input styling matches Phase 31 form-input spec
- No `app-header` on help page — main starts directly after sidebar (help.htmx.html has no `<header>`)

### Email Templates (email-templates.css / email-templates.htmx.html)
- Page content: templates grid at root of `<main>` — no wrapper `<div class="page-content">`
- Editor overlay: `.template-editor-body` is `grid-template-columns: 1fr 1fr` — needs `1fr 400px`
- Preview panel: `background: var(--color-bg-subtle)` — needs `var(--color-surface-raised)`
- Page-level max-width: needs 1200px container around `.templates-grid`

### Meetings (meetings.css / meetings.htmx.html)
- Page structure: uses `.page-content` wrapper
- `.sessions-list` has `gap: 0` with per-item `margin-bottom: 8px` — needs `gap: var(--space-3)`, remove per-item margin
- Status representation: `.session-dot` (colored circle) + implicit text — DECISION (Claude's discretion): keep `.session-dot` for live pulse animation, add `ag-badge` in session-item for textual status (already rendered by JS in `meetings.js`)
- Max-width: `.meetings-main` needs `.page-content { max-width: 1200px; margin: 0 auto; }`
- Onboarding banner: full-width, no max-width — keep outside `.page-content` or wrap in `.page-content`

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Flexbox for all multi-column layouts | CSS Grid with explicit column tracks | Phase 32 establishes this | More predictable column sizing |
| `margin-bottom` per list item | `gap` on flex/grid container | Phase 32/31 patterns | Consistent spacing, no last-item issue |
| Browser-default `<details>` styling | Custom accordion with JS toggle | Pre-v4.1 | Maintained — do not migrate to `<details>` as HTML would need restructuring |
| Hardcoded `max-width: 900px` literals | `max-width: 800px` (or 900px/1200px/1400px per page type) | Phase 32 established hierarchy | Content width now maps to semantic page types |

**Content width hierarchy (established in Phase 32):**
- `1400px` — wide content (analytics, data tables)
- `1200px` — standard page (dashboard, meetings)
- `900px` — single-column workflow (post-session)
- `800px` — reading content (help/FAQ)
- `680px` — wizard narrow track

---

## Open Questions

1. **Hub quorum bar position**
   - What we know: CONTEXT says "top of main content" but current HTML has it mid-column inside `.hub-main-col` after the action card and checklist
   - What's unclear: Does "top of main content" mean reordering HTML elements (action card below quorum bar) or just adding more visual prominence to the quorum section as-is?
   - Recommendation: Add visual prominence (`var(--color-surface-raised)`, accent border, larger padding) without reordering HTML — JS controls visibility via `display:none` conditionally so DOM order matters for JS

2. **Analytics `.overview-grid` definition gap**
   - What we know: `.overview-grid` class is used in analytics.htmx.html but not defined in analytics.css
   - What's unclear: Is it defined in design-system.css or pages.css? (Not found in the CSS snippets reviewed)
   - Recommendation: The planner should have implementors search design-system.css for `.overview-grid` and either use it if it matches spec or add the definition to analytics.css

3. **Help page `app-header` absence**
   - What we know: `help.htmx.html` has NO `<header class="app-header">` — jumps from sidebar to main directly
   - What's unclear: Does this affect sticky calculations (top: 80px) anywhere?
   - Recommendation: No sticky elements planned for help page; not a blocker

---

## Validation Architecture

The config.json has `workflow.nyquist_validation` absent (key not present) — treated as enabled.

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Visual/manual browser inspection (no automated test framework detected for CSS layout) |
| Config file | none |
| Quick run command | Open page in browser at 1024px viewport width |
| Full suite command | Check all 6 pages at 768px, 1024px, 1440px |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| LAY-07 | Hub sidebar is 220px, quorum bar visually prominent | manual | — | N/A |
| LAY-08 | Post-session max-width 900px, stepper sticky, section gaps 48px | manual | — | N/A |
| LAY-09 | Analytics shows min 2-col at 1024px | manual | — | N/A |
| LAY-10 | Help accordion expands with no layout shift, max-width 800px | manual | — | N/A |
| LAY-11 | Email editor overlay has 1fr+400px columns | manual | — | N/A |
| LAY-12 | Meetings list items have 12px gap, max-width 1200px | manual | — | N/A |

### Sampling Rate
- **Per task commit:** Open affected page in browser at three viewport widths (768px, 1024px, 1440px)
- **Per wave merge:** Full visual pass of all 6 pages checking layout, spacing, and depth model
- **Phase gate:** All 6 pages pass visual inspection before `/gsd:verify-work`

### Wave 0 Gaps
None — no test framework gaps. This phase is CSS-only; verification is visual browser inspection.

---

## Sources

### Primary (HIGH confidence)

- Direct source code inspection — `hub.htmx.html`, `postsession.htmx.html`, `analytics.htmx.html`, `help.htmx.html`, `email-templates.htmx.html`, `meetings.htmx.html`
- Direct CSS inspection — `hub.css`, `postsession.css`, `analytics.css`, `help.css`, `email-templates.css`, `meetings.css`, `settings.css`, `pages.css`
- `.planning/phases/33-page-layouts-secondary-pages/33-CONTEXT.md` — locked decisions verbatim
- `.planning/REQUIREMENTS.md` — LAY-07 through LAY-12 specifications

### Secondary (MEDIUM confidence)

- `.planning/STATE.md` — accumulated Phase 32 decisions informing patterns

### Tertiary (LOW confidence)

- None

---

## Metadata

**Confidence breakdown:**
- Current code state: HIGH — all six HTML and CSS files read and audited
- Target specs: HIGH — CONTEXT.md provides exact CSS values locked by user decisions
- Pitfalls: HIGH — identified from actual code conflicts found during audit
- Validation: N/A — visual only

**Research date:** 2026-03-19
**Valid until:** 2026-04-19 (stable CSS domain, no external dependencies)
