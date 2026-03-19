# Phase 32: Page Layouts — Core Pages - Research

**Researched:** 2026-03-19
**Domain:** CSS layout restructuring — six core pages rebuilt to grid specs with three-depth background model
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Three-Depth Background Model**
- Layer 1 — Body (bg): `var(--color-bg)` (#EDECE6 light) — the page canvas, visible between cards and in margins
- Layer 2 — Surface: `var(--color-surface)` (#FAFAF7 light) — cards, panels, sidebars — the primary content container
- Layer 3 — Raised: `var(--color-surface-raised)` (#FFFFFF light) — elevated elements inside cards: form inputs, table headers, selected rows, nested panels
- Apply systematically: `.app-main` = bg, `.card` = surface, form inputs/table headers = raised
- Dark mode: tokens auto-derive — no per-page dark blocks needed

**Content Width Constraints**
- Dashboard: `max-width: 1200px; margin: 0 auto` on content wrapper
- Wizard: Form track `max-width: 680px; margin: 0 auto`, fields inside capped at `max-width: 480px`
- Operator console: No max-width — fluid layout fills available space
- Data tables: `max-width: 1400px; margin: 0 auto`
- Settings: Content column `max-width: 720px`
- Mobile voter: No max-width needed — mobile viewport is the constraint

**Dashboard Layout (LAY-01)**
- KPI row: `display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--space-card)` (24px)
- Below KPIs: session list as vertical card stack (not side-by-side grid)
- Right aside: 280px sticky sidebar for quick-actions — `position: sticky; top: 80px`
- Main + aside: `display: grid; grid-template-columns: 1fr 280px; gap: var(--space-card)`
- At 1024px breakpoint: aside stacks below main, KPIs go to 2-column
- KPI cards use `var(--color-surface-raised)` to stand out from the card surface

**Wizard Layout (LAY-02)**
- Stepper: `ag-stepper` above the form card, outside the scrollable area (sticky)
- Form card: centered at 680px, `var(--color-surface)` background
- Fields inside: `max-width: 480px` — forms don't need full card width
- Footer nav: sticky to bottom of viewport (not card) — `position: sticky; bottom: 0; background: var(--color-surface)`
- Back/Next buttons: `justify-content: space-between` in footer
- No sidebar competes — the wizard is a focused, single-column experience

**Operator Console Layout (LAY-03)**
- CSS Grid 3-row layout: `grid-template-rows: auto auto 1fr` (status bar, tab nav, main content)
- Left agenda sidebar: `280px` fixed width, scrollable independently
- Grid: `grid-template-columns: 280px 1fr` with `grid-template-areas`
- Status bar: fixed at top of the grid, full width across sidebar + main — `background: var(--color-surface-raised)`
- Tab nav: below status bar, full main width
- Main area: fluid, scrolls independently from sidebar
- At 768px: sidebar collapses to off-canvas drawer

**Data Tables Layout (LAY-04)**
- Shared structure: `.table-page` wrapper → toolbar card → table card → pagination bar
- Toolbar: `display: flex; align-items: center; justify-content: space-between; gap: var(--space-4)` — search left, actions right
- Table: sticky 40px header (`var(--color-surface-raised)` background), 48px row height
- Numeric columns: `text-align: right; font-family: var(--font-mono)` via `.col-num` utility (from Phase 31)
- Pagination: `display: flex; justify-content: space-between; padding: var(--space-3) var(--space-card)` — count left, page controls right
- All 4 table pages (audit, archives, members, users) share this exact structure

**Settings Layout (LAY-05)**
- Two-column: `display: grid; grid-template-columns: 220px 1fr; gap: var(--space-card)`
- Left sidenav: 220px, `position: sticky; top: 80px; align-self: start` — stays visible while content scrolls
- Content: `max-width: 720px` — section cards stacked vertically
- Each section: `.card` with its own save button in `.card-footer`
- At 768px: sidenav converts to horizontal scrollable tab bar above content

**Mobile Voter Layout (LAY-06)**
- `height: 100dvh` (already implemented)
- Vote buttons: `min-height: 72px` — large touch targets
- Typography: `font-size: clamp(0.875rem, 2.5vw, 1.125rem)` for body text — fluid scaling
- Headings: `font-size: clamp(1.125rem, 3.5vw, 1.5rem)`
- Safe area: `padding-bottom: env(safe-area-inset-bottom, 16px)` on bottom nav
- Bottom nav: `position: fixed; bottom: 0` — always visible, not in scroll flow
- No horizontal scrolling at 375px — all content uses `padding: 0 var(--space-4)` (16px) side margins

### Claude's Discretion
- Exact transition timing for layout changes at breakpoints
- Whether to use `container-query` or `@media` for responsive behavior
- Animation for sidebar collapse/expand
- Exact gap sizes within cards (as long as they use spacing tokens)
- Whether to add subtle borders between the three background layers

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| LAY-01 | Dashboard — 4-column KPI grid, session cards as list, max-width 1200px content, quick-actions aside | Existing `.kpi-grid` class uses 4-col; need to add aside grid + max-width wrapper |
| LAY-02 | Wizard — centered 680px track, sticky footer nav with space-between, fields max-width 480px | Stepper currently scrolls away; footer not sticky; wizard.css refactor needed |
| LAY-03 | Operator console — 280px agenda sidebar + fluid main, fixed status bar + tab nav, CSS grid 3-row layout | operator.css already attempts grid overrides but override is inert (flex container bug) |
| LAY-04 | Data tables (audit, archives, members, users) — proper column alignment, sticky header, toolbar + pagination bars | Each page duplicates CSS; need shared `.table-page` base + per-page overrides |
| LAY-05 | Settings/Admin — 220px left sidenav, 720px content column, section cards with per-section save | settings.css has flex layout, sidenav at 200px; needs grid + sticky + wider |
| LAY-06 | Mobile voter — 100dvh, 72px vote buttons, safe-area padding, clamp() fluid typography | 100dvh exists; missing clamp(), safe-area, and bottom-nav fix |
</phase_requirements>

---

## Summary

Phase 32 is a pure CSS and HTML restructuring pass across six pages: dashboard, wizard, operator console, four data table pages, settings, and the mobile voter interface. No backend changes. No new features. No new libraries. All tokens were defined in Phase 30 and all components refreshed in Phase 31 — this phase composes those building blocks into correct page-level layouts.

The existing codebase is well-structured (one CSS file per page, `data-page-role` scoping, design tokens throughout) but each page has specific layout deficiencies documented in `32-CONTEXT.md`. The three-depth background model (`--color-bg` / `--color-surface` / `--color-surface-raised`) is already token-defined but not systematically applied. The main implementation work is structural: adding CSS Grid layouts, applying max-width wrappers, making sticky elements actually sticky, and extracting shared table page structure.

The operator console has an existing latent bug: `operator.css` attempts a CSS Grid override on `.app-shell`, but `.app-shell` is defined as `display: flex` in `design-system.css`. The grid override is silently ignored because the property doesn't cascade into a property already set. This must be fixed by explicitly setting `display: grid` on the operator page's `.app-shell`.

**Primary recommendation:** Work page-by-page in dependency order: shared table base first (LAY-04) since it's reused across four pages, then dashboard (LAY-01), wizard (LAY-02), operator (LAY-03), settings (LAY-05), mobile voter (LAY-06).

---

## Standard Stack

### Core

| Library/Pattern | Version/Spec | Purpose | Why Standard |
|-----------------|-------------|---------|--------------|
| CSS Custom Properties | Native | Three-depth token application | Already defined in Phase 30 — zero config |
| CSS Grid | Native | Dashboard aside, operator 3-row, settings two-col | Best fit for named template areas and fixed+fluid columns |
| CSS Flexbox | Native | Toolbar rows, pagination rows, KPI card internals | Row-level alignment where grid is overkill |
| `position: sticky` | Native | Wizard stepper, settings sidenav, table headers | Keeps context visible without JS |
| `clamp()` | Native | Mobile voter fluid typography | Replaces media-query font size steps |
| `env(safe-area-inset-bottom)` | Native | Mobile voter bottom nav | Avoids iPhone notch / home bar overlap |
| `100dvh` | Native | Mobile voter full height | Accounts for mobile browser chrome correctly |

### Tokens Available from Phase 30/31

| Token | Value | Use |
|-------|-------|-----|
| `--color-bg` | #EDECE6 light | Page canvas / `.app-main` background |
| `--color-surface` | #FAFAF7 light | Cards, panels, sidebars |
| `--color-surface-raised` | #FFFFFF light | Table headers, form inputs, KPI cards |
| `--space-card` | 24px | Primary layout gap between cards |
| `--space-4` | 16px | Inner padding, mobile side margins |
| `--space-3` | 12px | Pagination padding |
| `--sidebar-rail` | 58px | Fixed sidebar width |
| `--sidebar-expanded` | 252px | Pinned sidebar width |
| `--font-mono` | JetBrains Mono | Numeric columns |
| `--shadow-sm` | defined | Card default shadow |

### No New Dependencies

This phase installs nothing. All work is in existing page HTML files and page CSS files.

---

## Architecture Patterns

### Page Anatomy (All Admin Pages)

```
.app-shell (flex-column, 100vh)
├── .app-sidebar (fixed, 58px rail)
├── .app-header (56px, flex-shrink: 0)
└── .app-main (flex: 1, overflow-y: auto, padding-left: calc(58px + 22px))
    └── [page-specific content]
```

The sidebar is `position: fixed` — it never participates in document flow. `.app-main` compensates with `padding-left: calc(var(--sidebar-rail) + 22px)`.

### Pattern 1: Dashboard Layout (LAY-01)

**What:** Content wrapper with max-width 1200px, 4-column KPI grid, session list as vertical stack, right aside.

**Structure:**
```
.app-main
└── .dashboard-content (max-width: 1200px; margin: 0 auto)
    ├── .kpi-grid (grid, 4 col, gap: var(--space-card))
    │   └── .kpi-card x4 (background: var(--color-surface-raised))
    └── .dashboard-body (grid, "1fr 280px", gap: var(--space-card))
        ├── .dashboard-main
        │   ├── .sessions-list (vertical card stack)
        │   └── [other cards]
        └── .dashboard-aside (sticky, top: 80px)
```

**Key CSS:**
```css
/* Source: 32-CONTEXT.md locked decisions */
.dashboard-content {
  max-width: 1200px;
  margin: 0 auto;
}

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--space-card);
  margin-bottom: var(--space-card);
}

.kpi-card {
  background: var(--color-surface-raised); /* stands out from .card surface */
}

.dashboard-body {
  display: grid;
  grid-template-columns: 1fr 280px;
  gap: var(--space-card);
  align-items: start;
}

.dashboard-aside {
  position: sticky;
  top: 80px; /* header (56px) + buffer */
}

@media (max-width: 1024px) {
  .dashboard-body { grid-template-columns: 1fr; }
  .kpi-grid { grid-template-columns: repeat(2, 1fr); }
}
```

**HTML change:** Add `.dashboard-content` wrapper around `.app-main` inner content. Rename current `grid-2` / `grid-3` sections into `.dashboard-body` / `.dashboard-aside`.

### Pattern 2: Wizard Layout (LAY-02)

**What:** Centered single-column track, stepper sticky at top, sticky footer with back/next.

**Structure:**
```
.app-main.wiz-main (overflow-y: auto)
├── .wiz-progress-wrap (sticky, top: 0 — already styled, needs position)
└── .wiz-content (max-width: 680px; margin: 0 auto)
    └── .card (form card)
        └── .form-field inputs (max-width: 480px)
.wiz-footer (position: sticky; bottom: 0; background: var(--color-surface))
    ├── .btn (back)
    └── .btn (next)
```

**Key CSS:**
```css
/* Source: 32-CONTEXT.md locked decisions */
.wiz-progress-wrap {
  position: sticky;
  top: 0;
  z-index: var(--z-sticky);
  background: var(--color-surface);
}

.wiz-content {
  max-width: 680px;
  margin: 0 auto;
  padding-bottom: 80px; /* space for sticky footer */
}

.wiz-content .form-group input,
.wiz-content .form-group select,
.wiz-content .form-group textarea {
  max-width: 480px;
}

.wiz-footer {
  position: sticky;
  bottom: 0;
  background: var(--color-surface);
  border-top: 1px solid var(--color-border);
  padding: var(--space-4) var(--space-card);
  display: flex;
  justify-content: space-between;
  z-index: var(--z-sticky);
}
```

**HTML change:** Wrap stepper + form content in `.wiz-content`. Move `.step-nav` buttons to a `.wiz-footer` element outside `.wiz-content` but inside `.app-main`.

### Pattern 3: Operator Console Layout (LAY-03)

**What:** Fix the latent CSS Grid bug. Make `.app-shell` actually use grid for operator page. Add 280px agenda sidebar beside fluid main.

**The Bug:** `operator.css` sets `grid-template-rows` on `.app-shell`, but `design-system.css` sets `.app-shell { display: flex }`. Grid properties are ignored on a flex container. Must explicitly set `display: grid`.

**Key CSS:**
```css
/* Source: 32-CONTEXT.md + operator.css latent bug analysis */
[data-page-role="operator"] .app-shell {
  display: grid;                    /* FIX: was flex, grid props were inert */
  grid-template-rows: auto auto 1fr;
  grid-template-columns: auto 280px 1fr;
  grid-template-areas:
    "sidebar statusbar statusbar"
    "sidebar tabnav   tabnav"
    "sidebar agenda   main";
  height: 100vh;
  overflow: hidden;
}

[data-page-role="operator"] .meeting-bar {
  grid-area: statusbar;
  background: var(--color-surface-raised);
}

[data-page-role="operator"] .tabs-nav {
  grid-area: tabnav;
}

[data-page-role="operator"] .op-agenda {
  grid-area: agenda;
  width: 280px;
  overflow-y: auto;
  border-right: 1px solid var(--color-border);
}

[data-page-role="operator"] .app-main {
  grid-area: main;
  padding-left: var(--space-card); /* sidebar is in grid — no rail offset needed */
}

@media (max-width: 768px) {
  [data-page-role="operator"] .app-shell {
    grid-template-columns: 1fr;
    grid-template-areas:
      "statusbar"
      "tabnav"
      "main";
  }
  [data-page-role="operator"] .op-agenda {
    /* off-canvas drawer handled by JS toggle */
  }
}
```

**HTML change:** Add `.op-agenda` section in the operator HTML between the tab nav and the current main content.

### Pattern 4: Data Tables Shared Structure (LAY-04)

**What:** Extract a shared `.table-page` base that all four table pages (audit, archives, members, users) inherit. Each page CSS overrides only page-specific column widths.

**Shared structure:**
```
.app-main
└── .table-page (max-width: 1400px; margin: 0 auto)
    ├── .table-toolbar (.card — search left, actions right)
    │   ├── [search input]
    │   └── [action buttons]
    ├── .table-card (.card — table container)
    │   └── .table-wrapper (overflow-x: auto)
    │       └── table.table
    │           ├── thead (sticky, 40px, var(--color-surface-raised))
    │           └── tbody (48px row height)
    └── .table-pagination (display: flex; space-between)
        ├── [count]
        └── [page controls]
```

**Key CSS (in design-system.css or a new shared tables.css):**
```css
/* Source: 32-CONTEXT.md locked decisions */
.table-page {
  max-width: 1400px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: var(--space-card);
}

.table-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-4);
  padding: var(--space-4) var(--space-card);
}

.table thead th {
  position: sticky;
  top: 0;
  height: 40px;
  background: var(--color-surface-raised);
  z-index: var(--z-base);
}

.table tbody tr {
  height: 48px;
}

.col-num {
  text-align: right;
  font-family: var(--font-mono);
}

.table-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-3) var(--space-card);
}
```

**Decision:** Add `.table-page`, `.table-toolbar`, `.table-pagination` to `design-system.css` (or a new `tables.css` shared file). Each page's own CSS (`audit.css`, `archives.css`, etc.) adds column-width overrides only.

### Pattern 5: Settings Layout (LAY-05)

**What:** Convert existing flex layout to CSS Grid with 220px sidenav and sticky behavior.

**Existing state:** `settings.css` uses flexbox with 200px sidenav. Sidenav has no sticky behavior. No responsive collapse.

**Key CSS:**
```css
/* Source: 32-CONTEXT.md locked decisions */
.settings-layout {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: var(--space-card);
  align-items: start;
}

.settings-sidenav {
  width: 220px;          /* was 200px */
  position: sticky;
  top: 80px;             /* below header */
  align-self: start;
}

.settings-content {
  max-width: 720px;
}

@media (max-width: 768px) {
  .settings-layout {
    grid-template-columns: 1fr;
  }
  .settings-sidenav {
    position: static;
    width: 100%;
    display: flex;
    overflow-x: auto;
    gap: var(--space-2);
  }
  .settings-sidenav-item {
    flex-shrink: 0;
    white-space: nowrap;
  }
}
```

### Pattern 6: Mobile Voter Layout (LAY-06)

**What:** Add `clamp()` typography, safe-area padding, and fix bottom nav to `position: fixed`.

**Existing state:** `vote.css` already has `height: 100dvh` and a `.vote-app` flex container. Bottom nav is in scroll flow. No clamp() typography. No safe-area padding.

**Key CSS:**
```css
/* Source: 32-CONTEXT.md locked decisions */

/* Fluid typography */
.vote-app {
  font-size: clamp(0.875rem, 2.5vw, 1.125rem);
}

.vote-resolution-title,
.vote-section-title {
  font-size: clamp(1.125rem, 3.5vw, 1.5rem);
}

/* Vote buttons — large touch targets */
.vote-btn {
  min-height: 72px;
}

/* Content padding — prevents horizontal scroll at 375px */
.vote-content {
  padding: 0 var(--space-4); /* 16px each side */
}

/* Bottom nav — fixed, not in scroll flow */
.vote-bottom-nav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  padding-bottom: env(safe-area-inset-bottom, 16px);
  background: var(--color-surface);
  border-top: 1px solid var(--color-border);
  z-index: var(--z-fixed);
}

/* Add padding to scrollable content to clear fixed bottom nav */
.vote-scrollable {
  padding-bottom: calc(60px + env(safe-area-inset-bottom, 16px));
}
```

### Anti-Patterns to Avoid

- **Hardcoded hex values:** All colors must use design tokens. operator.css's `border-top: 3px solid var(--persona-operateur)` is fine — it's a token.
- **`display: grid` override without resetting children:** When switching `.app-shell` to grid for operator, the `.app-sidebar` (which is `position: fixed`) exits the grid flow — this is correct and expected.
- **Sticky without overflow context:** `position: sticky` only works when the parent does NOT have `overflow: hidden`. Verify `.app-main` has `overflow-y: auto` (it does: line 1250 of design-system.css).
- **`100vh` on mobile:** Already using `100dvh` in vote.css — do not regress to `100vh`.
- **Table header sticky inside non-overflow container:** The `.table-wrapper` must have `overflow-x: auto` (for horizontal scroll) but the sticky thead works because the page-level scroll is on `.app-main`.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Sticky sidebar nav | Custom JS scroll listener | `position: sticky; top: 80px; align-self: start` | CSS handles it — no JS needed |
| Responsive sidebar collapse | JS width toggle | CSS Grid column collapse + `@media` | Clean, no JS for layout |
| Safe-area padding | Hardcoded 34px for iPhones | `env(safe-area-inset-bottom, 16px)` | Correct on all devices including future ones |
| Fluid typography steps | Three `@media` font-size rules | `clamp(min, preferred, max)` | Single declaration handles all viewport widths |
| Table scroll | Custom JS scroll management | `overflow-x: auto` on `.table-wrapper` | Native browser behavior |
| Sticky table headers | `position: fixed` with JS top calculation | `position: sticky; top: 0` inside scrolling container | CSS only, no scroll event listener |

**Key insight:** Every layout requirement in this phase can be achieved with native CSS — Grid, Flexbox, sticky, clamp(), env(), dvh. No JavaScript should be added for layout purposes.

---

## Common Pitfalls

### Pitfall 1: Operator Console Grid Override Silently Fails

**What goes wrong:** `operator.css` sets `grid-template-rows` and `grid-template-areas`, but the layout renders as flexbox.
**Why it happens:** `design-system.css` sets `.app-shell { display: flex }`. CSS Grid properties on a flex container are ignored. The property precedence doesn't override `display`.
**How to avoid:** In the `[data-page-role="operator"] .app-shell` override, explicitly set `display: grid`. This is the primary fix for LAY-03.
**Warning signs:** If the meeting bar does not span full width, or the agenda sidebar doesn't appear beside main, the grid override is still inert.

### Pitfall 2: Sticky Fails Due to Overflow Ancestor

**What goes wrong:** `position: sticky` elements don't stick — they scroll away with content.
**Why it happens:** An ancestor element has `overflow: hidden` or `overflow: auto`, which creates a new scroll container and limits sticky range.
**How to avoid:** Verify the sticky element's scroll container is the intended one. `.app-main` has `overflow-y: auto` — sticky children of `.app-main` will stick within `.app-main`'s scroll, which is correct. The wizard stepper `position: sticky; top: 0` will stick at the top of `.app-main`'s scroll area.
**Warning signs:** The sticky element moves with the page but stops before reaching the top.

### Pitfall 3: Dashboard Aside Breaks at Tablet Width

**What goes wrong:** The `1fr 280px` aside grid causes the aside to overflow or wrap incorrectly at intermediate viewports.
**Why it happens:** The 280px aside is inflexible — at 900px viewport with sidebar rail (58px + 22px offset), the main area is only ~620px before adding the 280px aside.
**How to avoid:** The breakpoint at 1024px stacks the aside below main. Between 1024px and 768px, the layout is single-column which is correct.
**Warning signs:** Aside content overflows the aside column or causes horizontal scroll.

### Pitfall 4: Mobile Voter Content Hidden by Fixed Bottom Nav

**What goes wrong:** Vote buttons or content at the bottom of the page are hidden beneath the fixed bottom nav.
**Why it happens:** Fixed elements don't affect layout flow — the scrollable content has no padding-bottom to compensate.
**How to avoid:** Add `padding-bottom: calc(bottomNavHeight + env(safe-area-inset-bottom, 16px))` to the scrollable content container. Estimate bottom nav at 60px.
**Warning signs:** Last vote option or action button is partially hidden.

### Pitfall 5: Shared Table CSS Breaks Page-Specific Overrides

**What goes wrong:** Adding shared `.table-page` styles to design-system.css conflicts with existing audit.css / members.css overrides that use the same class names.
**Why it happens:** Specificity conflicts or cascade order issues when shared base is added.
**How to avoid:** The shared base goes in design-system.css (which loads before page CSS via `app.css`). Page-specific CSS has higher cascade position and higher specificity (uses `.audit-table` not `.table`) — no conflict.
**Warning signs:** Audit table rows are the wrong height or pagination looks wrong on one page but correct on others.

### Pitfall 6: Settings Sidenav Sticky Top Calculation

**What goes wrong:** Settings sidenav sticks too high (behind the 56px app header).
**Why it happens:** `top: 0` sticks to top of scroll container, not below the fixed header.
**How to avoid:** Use `top: 80px` (56px header + 24px buffer) to keep sidenav visible below header.
**Warning signs:** Sidenav disappears under the header when scrolling.

---

## Code Examples

### Three-Depth Background Application

```css
/* Source: 32-CONTEXT.md locked decisions + design-system.css token values */

/* Layer 1 — Page canvas */
.app-main {
  background: var(--color-bg); /* #EDECE6 light — already applied */
}

/* Layer 2 — Content containers */
.card,
.table-toolbar,
.table-card,
.dashboard-aside {
  background: var(--color-surface); /* #FAFAF7 light */
}

/* Layer 3 — Elevated elements within cards */
.table thead th,
.kpi-card,
.form-input,
.meeting-bar {
  background: var(--color-surface-raised); /* #FFFFFF light */
}
```

### Operator Console Grid Template Areas

```css
/* Source: 32-CONTEXT.md locked decisions */
[data-page-role="operator"] .app-shell {
  display: grid;
  grid-template-rows: auto auto 1fr;
  grid-template-columns: auto 280px 1fr;
  grid-template-areas:
    "sidebar statusbar statusbar"
    "sidebar tabnav   tabnav"
    "sidebar agenda   main";
  height: 100vh;
  overflow: hidden;
}
```

Note: `.app-sidebar` is `position: fixed` — it does not participate in the grid flow. The `auto` column for "sidebar" collapses to 0. The `280px` column is the agenda sidebar, and `1fr` is the main content area.

Simplified alternative: Keep sidebar fixed, use only 2-column grid for content area:
```css
[data-page-role="operator"] .app-shell {
  display: grid;
  grid-template-rows: auto auto 1fr;
  grid-template-columns: 1fr;
  grid-template-areas:
    "statusbar"
    "tabnav"
    "main";
}

[data-page-role="operator"] .app-main {
  display: grid;
  grid-template-columns: 280px 1fr;
  padding: 0;
  overflow: hidden;
}

[data-page-role="operator"] .op-agenda {
  overflow-y: auto;
  border-right: 1px solid var(--color-border);
  padding: var(--space-4);
}

[data-page-role="operator"] .op-main-content {
  overflow-y: auto;
  padding: var(--space-4) var(--space-card);
}
```

This approach (nested grid) is cleaner because it doesn't fight the fixed sidebar.

### Clamp() Fluid Typography

```css
/* Source: 32-CONTEXT.md locked decisions */
.vote-app { font-size: clamp(0.875rem, 2.5vw, 1.125rem); }
.vote-resolution-title { font-size: clamp(1.125rem, 3.5vw, 1.5rem); }
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `100vh` for full-height mobile | `100dvh` (dynamic viewport height) | Chrome 108, Safari 15.4, 2022 | Accounts for mobile browser chrome (address bar) — already used in vote.css |
| Fixed-px font sizes + media queries | `clamp(min, vw, max)` | CSS Level 4, ~2020 | Single declaration replaces 3+ media query blocks |
| `padding-bottom: 34px` for iPhone notch | `env(safe-area-inset-bottom, 16px)` | iOS 11+, 2017 | Device-agnostic safe area |
| JS-calculated sticky headers | `position: sticky` | All major browsers, ~2017 | No JS needed for sticky headers/sidebars |
| Vendor-prefixed `grid-template-areas` | Unprefixed CSS Grid | All modern browsers | No prefixes needed in 2026 |

**No deprecated features in scope** — all CSS used in this phase has universal modern browser support.

---

## Open Questions

1. **Operator console agenda sidebar content**
   - What we know: The sidebar should be 280px and scrollable independently, showing the session agenda
   - What's unclear: Does operator.htmx.html currently have an agenda sidebar element, or does it need to be added in HTML?
   - Recommendation: Read `public/operator.htmx.html` lines 80-200 during planning to identify existing sidebar-like elements. The agenda may be in a tab panel that needs extracting to a fixed sidebar.

2. **Members page table structure**
   - What we know: `members.css` uses a two-column layout (search sidebar + results) rather than the standard toolbar/table/pagination pattern
   - What's unclear: LAY-04 says all 4 table pages share the same structure — but members currently uses a very different layout (card grid, not table). Does LAY-04 apply to members or only audit/archives/users?
   - Recommendation: Re-read members.htmx.html structure. If it's a card grid, the `.table-page` shared structure may need a variant `.table-page--grid`.

3. **Wizard footer HTML placement**
   - What we know: The footer must be `position: sticky; bottom: 0` but inside `.app-main` (which has `overflow-y: auto`)
   - What's unclear: Current step navigation buttons are inside each `.step-panel`. Moving them to a shared footer requires JS changes to show/hide and wire back/next.
   - Recommendation: During planning, verify whether the step navigation can be a fixed DOM element with JS controlling the button labels, or whether it needs to remain per-panel.

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Playwright (e2e), from `tests/e2e/playwright.config.js` |
| Config file | `tests/e2e/playwright.config.js` |
| Quick run command | `cd tests/e2e && npx playwright test --project=chromium specs/dashboard.spec.js` |
| Full suite command | `cd tests/e2e && npx playwright test` |

### Phase Requirements — Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| LAY-01 | Dashboard has no horizontal overflow | e2e visual | `npx playwright test --project=chromium specs/dashboard.spec.js` | ✅ (partial — overflow test exists) |
| LAY-01 | KPI grid is 4-column at desktop | e2e visual | Add to dashboard.spec.js | ❌ Wave 0 |
| LAY-01 | Dashboard aside is 280px and sticky | e2e visual | Add to dashboard.spec.js | ❌ Wave 0 |
| LAY-02 | Wizard stepper stays at top when scrolling | e2e visual | Add to wizard.spec.js | ❌ Wave 0 |
| LAY-02 | Wizard footer stays at bottom | e2e visual | Add to wizard.spec.js | ❌ Wave 0 |
| LAY-03 | Operator layout renders without horizontal overflow | e2e visual | `npx playwright test --project=chromium specs/operator.spec.js` | ✅ (partial) |
| LAY-03 | Operator agenda sidebar is 280px | e2e visual | Add to operator.spec.js | ❌ Wave 0 |
| LAY-04 | Audit table has no horizontal overflow | e2e | `npx playwright test --project=chromium specs/audit-regression.spec.js` | ✅ |
| LAY-04 | Table headers are sticky | e2e visual | Add to audit-regression.spec.js | ❌ Wave 0 |
| LAY-05 | Settings sidenav is visible during scroll | e2e visual | Add to settings.spec.js | ❌ Wave 0 (no settings.spec.js) |
| LAY-06 | Mobile voter has no horizontal scroll at 375px | e2e | `npx playwright test --project=mobile-chrome specs/mobile-viewport.spec.js` | ✅ (partial) |
| LAY-06 | Vote buttons are at least 72px tall | e2e | `npx playwright test --project=mobile-chrome specs/vote.spec.js` | ❌ Wave 0 |

### Sampling Rate

- **Per task commit:** `cd /home/user/gestion_votes_php/tests/e2e && npx playwright test --project=chromium specs/dashboard.spec.js specs/operator.spec.js specs/audit-regression.spec.js specs/mobile-viewport.spec.js`
- **Per wave merge:** `cd /home/user/gestion_votes_php/tests/e2e && npx playwright test --project=chromium`
- **Phase gate:** Full suite green (all projects including mobile-chrome) before `/gsd:verify-work`

### Wave 0 Gaps

- [ ] `tests/e2e/specs/wizard.spec.js` — covers LAY-02 stepper sticky + footer sticky
- [ ] `tests/e2e/specs/settings.spec.js` — covers LAY-05 sidenav sticky
- [ ] Add to `tests/e2e/specs/dashboard.spec.js` — LAY-01 KPI 4-col check, aside 280px check
- [ ] Add to `tests/e2e/specs/operator.spec.js` — LAY-03 agenda sidebar width check
- [ ] Add to `tests/e2e/specs/vote.spec.js` — LAY-06 vote button min-height 72px check

---

## Sources

### Primary (HIGH confidence)

- `public/assets/css/design-system.css` — Shell layout (lines 863-1254), token definitions (lines 270-460)
- `public/assets/css/operator.css` — Existing grid override and the flex/grid bug
- `public/assets/css/wizard.css` — Stepper styles, current state of progress wrap
- `public/assets/css/vote.css` — `100dvh` implementation, current vote app structure
- `public/assets/css/settings.css` — Current flex layout at 200px sidenav
- `public/assets/css/pages.css` (lines 1006-1143) — Dashboard grid definitions
- `public/assets/css/audit.css`, `members.css` — Per-page table CSS baseline
- `.planning/phases/32-page-layouts-core-pages/32-CONTEXT.md` — All locked decisions

### Secondary (MEDIUM confidence)

- MDN CSS Grid `grid-template-areas` — native CSS, no external dependency
- MDN `env(safe-area-inset-bottom)` — well-established iOS 11+ feature
- MDN `clamp()` — Level 4 CSS, all modern browsers 2020+

### Tertiary (LOW confidence)

- None — all findings verified from project source files and native CSS specifications

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all tokens defined in Phase 30, all components in Phase 31; no new dependencies
- Architecture patterns: HIGH — derived directly from locked CONTEXT.md decisions and verified against actual CSS files
- Pitfalls: HIGH — operator grid bug confirmed by reading both design-system.css and operator.css; others derived from CSS specification behavior

**Research date:** 2026-03-19
**Valid until:** 2026-06-19 (stable vanilla CSS — no version dependencies)
