# Page Layout Specifications — v4.1 Design Excellence

**Domain:** AG-VOTE — General Assembly voting platform
**Researched:** 2026-03-19
**Scope:** Gold-standard layout for every page type — CSS grid/flex specs, ASCII diagrams, breakpoints
**Confidence:** HIGH (cross-verified against multiple sources and existing codebase tokens)

---

## Context and Constraints

AG-VOTE already has a solid design system (`@layer base, components, v4`) with 265+ CSS custom properties, an 8px spacing grid, and a fixed sidebar rail (58px collapsed, 252px expanded). v4.1 is a **refonte** — every page layout gets rebuilt to a research-driven gold standard while preserving the existing design tokens and vanilla CSS approach.

**Fixed constraints from PROJECT.md:**
- `--sidebar-rail: 58px` (collapsed) / `--sidebar-expanded: 252px` (expanded/pinned)
- `--header-height: 56px`
- `--content-max: 1440px` / `--content-narrow: 720px`
- Breakpoints: `480px` (sm) / `768px` (md) / `1024px` (lg)
- Fonts: Bricolage Grotesque (body), Fraunces (display), JetBrains Mono (data)
- Light-first, dark mode parity maintained
- `@layer base, components, v4` structure preserved

---

## PAGE TYPE 1 — DASHBOARD (KPI cards + session list)

### Reference: Stripe Dashboard, Linear dashboard pattern

**Used on:** `dashboard.htmx.html`

### Layout Philosophy
The dashboard is the "mission control" entry point. Primary purpose: answer "what needs my attention right now?" in under 3 seconds. The layout uses a top KPI row (scannable in one line), then an urgent action banner if applicable, then the sessions list as the main content. No card grid for sessions — a vertical list with strong row design beats a grid for data that has status, dates, and action CTAs.

### ASCII Diagram

```
┌─────────────────────────────────────────────────────────┐
│ [sidebar rail 58px]  [header 56px — title + CTA]        │
├─────────────────────────────────────────────────────────┤
│                 CONTENT (max-width: 1200px, mx auto)     │
│                                                         │
│  ┌──────┐  ┌──────┐  ┌──────┐  ┌──────┐               │
│  │ KPI  │  │ KPI  │  │ KPI  │  │ KPI  │  ← 4-col grid │
│  │  28px│  │  28px│  │  28px│  │  28px│    gap: 16px   │
│  └──────┘  └──────┘  └──────┘  └──────┘               │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ URGENT ACTION CARD (full width, conditionally)  │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  ┌───────────────────────────────┐  ┌───────────────┐  │
│  │ SESSIONS LIST                 │  │ QUICK ACTIONS │  │
│  │ (upcoming + draft)            │  │ (secondary)   │  │
│  │                               │  │               │  │
│  │ row: 72px min-height          │  │               │  │
│  │ ─────────────────────────     │  │               │  │
│  │ row                           │  └───────────────┘  │
│  │ ─────────────────────────     │                     │
│  │ row                           │                     │
│  └───────────────────────────────┘                     │
└─────────────────────────────────────────────────────────┘
```

### CSS Grid Specifications

```css
@layer v4 {

/* ── Dashboard content wrapper ── */
.dashboard-content {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* ── KPI Row — 4 columns, equal width ── */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

/* KPI card anatomy */
.kpi-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);   /* 10px */
  padding: 20px 24px;
  min-height: 96px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.kpi-card__value {
  font-size: var(--text-3xl);        /* 30px */
  font-weight: var(--font-extrabold);
  font-family: var(--font-display);  /* Fraunces */
  line-height: 1;
  color: var(--color-text-dark);
}

.kpi-card__label {
  font-size: var(--text-sm);         /* 14px */
  color: var(--color-text-muted);
  font-weight: var(--font-medium);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.kpi-card__delta {
  font-size: var(--text-xs);         /* 12px */
  font-family: var(--font-mono);
  margin-top: auto;
}

/* ── Urgent action banner — full width ── */
.urgent-banner {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 20px;
  background: var(--color-primary-subtle);
  border: 1px solid var(--color-primary);
  border-left: 4px solid var(--color-primary);
  border-radius: var(--radius-lg);
  text-decoration: none;
  transition: background var(--duration-fast);
}

/* ── Main body — sessions list + sidebar ── */
.dashboard-body {
  display: grid;
  grid-template-columns: 1fr 280px;
  gap: 24px;
  align-items: flex-start;
}

/* ── Session list rows ── */
.session-list {
  display: flex;
  flex-direction: column;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.session-row {
  display: grid;
  grid-template-columns: auto 1fr auto auto;
  align-items: center;
  gap: 16px;
  padding: 16px 20px;
  min-height: 72px;
  border-bottom: 1px solid var(--color-border-subtle);
  transition: background var(--duration-fast);
}

.session-row:last-child { border-bottom: none; }
.session-row:hover { background: var(--color-bg-subtle); }

/* ── Quick actions sidebar ── */
.dashboard-aside {
  display: flex;
  flex-direction: column;
  gap: 16px;
  position: sticky;
  top: calc(var(--header-height) + 24px);
}

/* ── Responsive ── */
@media (max-width: 1024px) {
  .kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .dashboard-body {
    grid-template-columns: 1fr;
  }
  .dashboard-aside {
    position: static;
    flex-direction: row;
    flex-wrap: wrap;
  }
}

@media (max-width: 768px) {
  .dashboard-content {
    padding: 0 16px;
    gap: 16px;
  }
  .kpi-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
  }
}

@media (max-width: 480px) {
  .kpi-grid {
    grid-template-columns: 1fr 1fr;
    gap: 8px;
  }
  .kpi-card {
    padding: 14px 16px;
    min-height: 80px;
  }
  .kpi-card__value {
    font-size: var(--text-2xl);
  }
}

} /* @layer v4 */
```

### Spacing Rationale
- **Max-width 1200px**: Stripe, Linear, and most SaaS dashboards use 1200–1280px. At 1440px (our global max) with a 58px sidebar, the content feels too sparse. 1200px keeps card proportions healthy.
- **KPI 4-column gap 16px**: Tight enough that the row reads as a single unit, not four separate items.
- **Session row 72px min-height**: Large enough to be scannable, matches the voter card height convention from v4.0.
- **Quick-actions aside 280px**: Standard sidebar secondary column — matches Notion sidebar, Linear detail panel.

---

## PAGE TYPE 2 — WIZARD (4-step session creation)

### Reference: Stripe Checkout, Notion database creation flow

**Used on:** `wizard.htmx.html`

### Layout Philosophy
A wizard is a focused task. Remove all noise. The sidebar and its navigation context actively fight the user's concentration — they suggest escape routes. The gold-standard approach (Stripe Checkout) is a **centered single-column form**, max 640px wide, on a neutral page background. The stepper goes above the form card, not inside it, so it signals progress without competing with the form fields. Primary action is always at bottom-right, secondary (back) at bottom-left.

### ASCII Diagram

```
┌──────────────────────────────────────────────────────────────┐
│ [header: back link left, step label right]                   │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│          ┌─────────────────────────────────┐                │
│          │  STEPPER PROGRESS BAR           │  ← max 680px   │
│          │  [1] [──] [2] [──] [3] [──] [4] │                │
│          └─────────────────────────────────┘                │
│                                                              │
│          ┌─────────────────────────────────┐                │
│          │  STEP CARD                      │                │
│          │                                 │  ← max 640px   │
│          │  [Step title]                   │    centered    │
│          │                                 │                │
│          │  Field label                    │                │
│          │  [────────────────────────────] │                │
│          │                                 │                │
│          │  Field label                    │                │
│          │  [────────────────────────────] │                │
│          │                                 │                │
│          │  ─────────────────────────────  │  ← divider     │
│          │  [← Précédent]    [Suivant →]  │  ← sticky bar  │
│          └─────────────────────────────────┘                │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### CSS Grid / Flex Specifications

```css
@layer v4 {

/* ── Wizard page shell — no sidebar ── */
/* The wizard page uses .app-shell.no-sidebar */
[data-page-role="wizard"] .app-main {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 32px 24px 80px; /* bottom clearance for sticky bar */
  background: var(--color-bg);
}

/* ── Wizard track — constrained column ── */
.wizard-track {
  width: 100%;
  max-width: 680px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* ── Stepper bar ── */
.wiz-progress-wrap {
  /* Existing styles maintained — pill stepper */
  /* Tokens: padding 4px, gap 4px, radius-lg */
  /* Step items: flex 1, padding 14px 18px */
  /* Active: background primary-subtle, color primary */
  /* Done: background success-subtle, color success */
}

/* On narrow screens, collapse stepper to icon+number only */
@media (max-width: 640px) {
  .wiz-step-label {
    display: none;
  }
  .wiz-step-item {
    flex: 0 0 auto;
    padding: 12px 14px;
  }
}

/* ── Step form card ── */
.step-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
  overflow: hidden;
}

.step-card__header {
  padding: 24px 28px 0;
}

.step-card__title {
  font-size: var(--text-xl);           /* 20px */
  font-weight: var(--font-bold);
  font-family: var(--font-display);
  line-height: var(--leading-tight);
}

.step-card__body {
  padding: 24px 28px;
  display: flex;
  flex-direction: column;
  gap: 20px;                           /* 20px between field groups */
}

/* ── Field group ── */
.field-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-label {
  font-size: var(--text-sm);           /* 14px */
  font-weight: var(--font-semibold);
  color: var(--color-text-dark);
}

.field-hint {
  font-size: var(--text-xs);           /* 12px */
  color: var(--color-text-muted);
  margin-top: 4px;
}

/* Fields never 100% by default — max 480px for inputs */
/* Exception: textarea, description fields = 100% */
.field-input {
  max-width: 480px;
  width: 100%;
}

.field-input--full {
  max-width: none;
}

/* ── Two-column field row (e.g., date + time) ── */
.field-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

@media (max-width: 480px) {
  .field-row {
    grid-template-columns: 1fr;
  }
}

/* ── Step navigation bar — sticky at card bottom ── */
.step-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 28px;
  border-top: 1px solid var(--color-border);
  background: var(--color-surface);
  position: sticky;
  bottom: 0;
  z-index: var(--z-sticky);
}

/* Both buttons minimum 120px wide, 40px tall */
.step-card__footer .btn {
  min-width: 120px;
  height: 40px;
}

/* ── Responsive ── */
@media (max-width: 768px) {
  [data-page-role="wizard"] .app-main {
    padding: 16px 16px 80px;
  }
  .wizard-track {
    max-width: 100%;
  }
  .step-card__header,
  .step-card__body,
  .step-card__footer {
    padding-left: 20px;
    padding-right: 20px;
  }
}

} /* @layer v4 */
```

### Spacing Rationale
- **Max-width 680px for track, 640px for inner form area**: Stripe Checkout uses ~600px. GitHub's new issue form uses ~640px. The wider 680px accommodates the stepper bar which includes step labels.
- **Field gap 20px**: Enough breathing room that each field is a distinct decision, not a form wall.
- **Footer sticky bottom**: The navigation buttons must always be visible. Long forms (step 2: resolution list) make non-sticky footers unusable.
- **Button space-between**: Universal pattern for wizards — back on left (escape), forward on right (goal). Never center both buttons.

---

## PAGE TYPE 3 — SPLIT-PANEL (Operator Console)

### Reference: Slack/Discord channel layout, Bloomberg terminal philosophy

**Used on:** `operator.htmx.html`

### Layout Philosophy
The operator console is a **power tool for live sessions**. It must display maximum information density without cognitive overload. The gold-standard split-panel (Slack, Discord, VS Code) uses: a narrow left panel for navigation/list context, a dominant main panel for primary work, and an optional right drawer for details. For AG-VOTE specifically: left = agenda/navigation, main = active vote or current question, status bar = persistent session state at top.

The critical insight from Slack/Discord: **the left panel is always visible and never collapses on desktop** (1024px+). On tablet (768–1024px), the left panel overlays. On mobile, it's a bottom sheet.

### ASCII Diagram

```
┌────────────────────────────────────────────────────────────────┐
│ [sidebar rail 58px] │  STATUS BAR — 52px — session state, live │
│                     ├──────────────────────────────────────────┤
│                     │  TAB NAR — 44px — Ordre du jour / Live   │
│                     ├──────────┬───────────────────────────────┤
│                     │          │                               │
│                     │  AGENDA  │   MAIN PANEL                  │
│                     │  PANEL   │                               │
│                     │  280px   │   Current question/vote       │
│                     │          │   Result chart                │
│                     │  Agenda  │   Actions                     │
│                     │  items   │                               │
│                     │  list    │                               │
│                     │          │   ─────────────────────────   │
│                     │          │   QUORUM / ATTENDEES ROW      │
│                     │          │   (sticky at panel bottom)    │
│                     └──────────┴───────────────────────────────┘
└────────────────────────────────────────────────────────────────┘
```

### CSS Grid Specifications

```css
@layer v4 {

/* ── Operator page takes over app-main ── */
[data-page-role="operator"] .app-main {
  padding: 0;
  padding-left: var(--sidebar-rail); /* 58px */
  display: grid;
  grid-template-rows: 52px 44px 1fr;
  grid-template-columns: 1fr;
  grid-template-areas:
    "statusbar"
    "tabnav"
    "console";
  height: 100vh;
  overflow: hidden;
}

/* When sidebar is pinned, shift left */
[data-page-role="operator"] .app-sidebar.pinned ~ .app-main {
  padding-left: var(--sidebar-expanded); /* 252px */
}

/* ── Status bar ── */
.op-status-bar {
  grid-area: statusbar;
  height: 52px;
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 0 20px;
  background: var(--color-surface);
  border-bottom: 1px solid var(--color-border);
  border-top: 3px solid var(--persona-operateur, var(--color-primary));
  overflow: hidden;
}

/* ── Tab navigation ── */
.op-tab-nav {
  grid-area: tabnav;
  height: 44px;
  display: flex;
  align-items: stretch;
  padding: 0 20px;
  gap: 4px;
  background: var(--color-bg-subtle);
  border-bottom: 1px solid var(--color-border);
  overflow-x: auto;
}

/* ── Console body — split horizontal ── */
.op-console {
  grid-area: console;
  display: grid;
  grid-template-columns: 280px 1fr;
  overflow: hidden;
}

/* ── Agenda panel — left, scrollable ── */
.op-agenda-panel {
  width: 280px;
  border-right: 1px solid var(--color-border);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--color-surface);
}

.op-agenda-panel__header {
  padding: 12px 16px;
  border-bottom: 1px solid var(--color-border);
  font-size: var(--text-sm);
  font-weight: var(--font-semibold);
  flex-shrink: 0;
}

.op-agenda-panel__list {
  flex: 1;
  overflow-y: auto;
  padding: 8px 0;
}

/* Agenda item row */
.agenda-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 10px 16px;
  min-height: 52px;
  cursor: pointer;
  border-left: 3px solid transparent;
  transition: background var(--duration-fast), border-color var(--duration-fast);
}

.agenda-item:hover { background: var(--color-bg-subtle); }
.agenda-item.active {
  background: var(--color-primary-subtle);
  border-left-color: var(--color-primary);
}
.agenda-item.done { opacity: 0.6; }

/* ── Main panel — right, scrollable ── */
.op-main-panel {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--color-bg);
}

.op-main-panel__content {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
}

/* ── Quorum bar — sticky at panel bottom ── */
.op-quorum-bar {
  flex-shrink: 0;
  height: 52px;
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 0 24px;
  background: var(--color-surface);
  border-top: 1px solid var(--color-border);
  font-size: var(--text-sm);
}

/* ── Responsive: collapse agenda panel ── */
@media (max-width: 1024px) {
  .op-console {
    grid-template-columns: 1fr;
  }
  .op-agenda-panel {
    position: fixed;
    top: calc(var(--header-height) + 52px + 44px);
    left: calc(var(--sidebar-rail));
    width: 280px;
    height: calc(100vh - var(--header-height) - 52px - 44px);
    z-index: var(--z-fixed);
    transform: translateX(-100%);
    transition: transform var(--duration-normal) var(--ease-out);
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
  }
  .op-agenda-panel.open {
    transform: translateX(0);
  }
}

@media (max-width: 768px) {
  [data-page-role="operator"] .app-main {
    grid-template-rows: 52px 44px 1fr;
  }
  .op-status-bar {
    padding: 0 12px;
    gap: 8px;
  }
  .op-main-panel__content {
    padding: 16px;
  }
}

} /* @layer v4 */
```

### Spacing Rationale
- **Agenda panel 280px**: Matches Slack's channel list (220–240px) but needs more width for AG resolution titles which can be long French text. 280px is the practical minimum for a truncated two-line title + status badge.
- **Status bar 52px**: Slightly taller than header (56px) to stand out as a different component type. Contains: session name, status badge, timer, live participant count. Must all fit in one row.
- **Tab nav 44px**: Compact enough that the console area maximizes space. Two tabs: "Ordre du jour" (setup mode) / "En séance" (live mode).
- **Quorum bar 52px at bottom**: Permanently visible during live voting — operators must never scroll to find quorum data.

---

## PAGE TYPE 4 — DATA TABLES (Audit, Archives, Members, Users)

### Reference: Linear issue list, GitHub PR list, Airtable grid view

**Used on:** `audit.htmx.html`, `archives.htmx.html`, `members.htmx.html`, `admin.htmx.html`

### Layout Philosophy
Data tables must maximize information density while remaining scannable. The gold standard (Linear, Airtable) uses:
1. A toolbar row (search + filters) pinned above the table
2. A sticky header row that shows column names while scrolling
3. Consistent row heights (48px for dense data, 56px for comfortable)
4. No zebra striping — a clean hover highlight is sufficient and more modern
5. Right-aligned numbers (quantities, votes, percentages) — never centered

### ASCII Diagram

```
┌─────────────────────────────────────────────────────────────┐
│ PAGE HEADER — title, description, count badge               │
├─────────────────────────────────────────────────────────────┤
│ TOOLBAR — [🔍 Search...] [Filter ▾] [Export] [+ Add]  ↕    │
│                                     ← 48px height ─────────  │
├─────────────────────────────────────────────────────────────┤
│ TABLE (scroll container, overflow-y: auto)                  │
│ ┌──┬─────────────────┬──────────┬───────┬───────────────┐  │
│ │☐ │ Name ↕          │ Status   │ Date  │ Actions       │  │
│ ├──┼─────────────────┼──────────┼───────┼───────────────┤  │
│ │☐ │ Item row        │ [badge]  │ date  │ [···]         │  │
│ ├──┼─────────────────┼──────────┼───────┼───────────────┤  │
│ │☐ │ Item row        │ [badge]  │ date  │ [···]         │  │
│ ├──┼─────────────────┼──────────┼───────┼───────────────┤  │
│ │☐ │ Item row        │ [badge]  │ date  │ [···]         │  │
│ └──┴─────────────────┴──────────┴───────┴───────────────┘  │
├─────────────────────────────────────────────────────────────┤
│ PAGINATION — [←] 1 2 3 ... [→]   Showing 1-25 of 142 items │
└─────────────────────────────────────────────────────────────┘
```

### CSS Specifications

```css
@layer v4 {

/* ── Table page content — no extra padding, full width ── */
.table-page-main {
  padding: 0;
  padding-left: calc(var(--sidebar-rail) + 0px);
  display: flex;
  flex-direction: column;
  height: 100vh;
  overflow: hidden;
}

/* ── Page header strip ── */
.table-page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 16px 24px;
  background: var(--color-surface);
  border-bottom: 1px solid var(--color-border);
  flex-shrink: 0;
}

/* ── Toolbar strip ── */
.table-toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 24px;
  height: 52px;
  background: var(--color-bg-subtle);
  border-bottom: 1px solid var(--color-border);
  flex-shrink: 0;
  overflow-x: auto;
}

.table-toolbar__search {
  flex: 0 0 280px;        /* fixed width search, never grows to fill all space */
  max-width: 320px;
  height: 32px;
}

.table-toolbar__filters {
  display: flex;
  align-items: center;
  gap: 6px;
  flex: 1;
  min-width: 0;
}

.table-toolbar__actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
  margin-left: auto;
}

/* ── Table scroll container ── */
.table-scroll {
  flex: 1;
  overflow: auto;        /* both axes */
  position: relative;
}

/* ── Table ── */
.data-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  table-layout: fixed;   /* prevents column width jitter on data load */
}

/* ── Sticky header ── */
.data-table thead th {
  position: sticky;
  top: 0;
  z-index: var(--z-sticky);
  background: var(--color-surface);
  border-bottom: 2px solid var(--color-border);
  padding: 0 16px;
  height: 40px;              /* header row: 40px */
  font-size: var(--text-xs); /* 12px */
  font-weight: var(--font-semibold);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-text-muted);
  white-space: nowrap;
  text-align: left;
  user-select: none;
}

/* Sortable header */
.data-table th.sortable {
  cursor: pointer;
}
.data-table th.sortable:hover {
  color: var(--color-text);
  background: var(--color-bg-subtle);
}

/* Right-align numeric columns */
.data-table th.col-number,
.data-table td.col-number {
  text-align: right;
  font-family: var(--font-mono);
  letter-spacing: 0;
}

/* ── Table rows ── */
.data-table tbody tr {
  height: 48px;              /* standard row: 48px */
  border-bottom: 1px solid var(--color-border-subtle);
  transition: background var(--duration-fast);
}

.data-table tbody tr:hover {
  background: var(--color-bg-subtle);
}

.data-table tbody tr:last-child {
  border-bottom: none;
}

.data-table tbody td {
  padding: 0 16px;
  font-size: var(--text-sm);  /* 14px */
  vertical-align: middle;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* First column — always left, slightly bolder */
.data-table tbody td:first-child {
  font-weight: var(--font-medium);
  color: var(--color-text-dark);
}

/* Checkbox column */
.data-table .col-check {
  width: 44px;
  padding: 0 12px;
  text-align: center;
}

/* Actions column — right-aligned, never truncated */
.data-table .col-actions {
  width: 80px;
  text-align: right;
  padding-right: 12px;
  overflow: visible;     /* action menus must escape the cell */
}

/* ── Pagination bar ── */
.table-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 24px;
  height: 52px;
  background: var(--color-surface);
  border-top: 1px solid var(--color-border);
  flex-shrink: 0;
}

.table-pagination__info {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  font-family: var(--font-mono);
}

.table-pagination__nav {
  display: flex;
  align-items: center;
  gap: 4px;
}

.pagination-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  font-size: var(--text-sm);
  cursor: pointer;
  transition: background var(--duration-fast), border-color var(--duration-fast);
}
.pagination-btn:hover { background: var(--color-bg-subtle); }
.pagination-btn.active {
  background: var(--color-primary-subtle);
  border-color: var(--color-primary);
  color: var(--color-primary);
  font-weight: var(--font-semibold);
}

/* ── Responsive ── */
@media (max-width: 1024px) {
  .table-toolbar__search {
    flex-basis: 200px;
  }
}

@media (max-width: 768px) {
  .table-page-header,
  .table-toolbar,
  .table-pagination {
    padding-left: 16px;
    padding-right: 16px;
  }
  .table-toolbar {
    height: auto;
    min-height: 52px;
    flex-wrap: wrap;
    padding-top: 8px;
    padding-bottom: 8px;
  }
  .table-toolbar__search {
    flex-basis: 100%;
    max-width: 100%;
  }
  /* Horizontal scroll on mobile — do not reflow columns */
  .table-scroll {
    -webkit-overflow-scrolling: touch;
  }
  /* Hide lower-priority columns on mobile via class */
  .data-table .col-hide-mobile {
    display: none;
  }
}

} /* @layer v4 */
```

### Column Width Conventions

| Column Type | Width | Alignment | Font |
|-------------|-------|-----------|------|
| Checkbox | 44px | Center | — |
| Status badge | 120px | Left | sans |
| Name / Title | auto (1fr) | Left | medium weight |
| Date | 140px | Left | mono |
| Number / Count | 100px | Right | mono |
| Percentage | 80px | Right | mono |
| Actions menu | 80px | Right | — |

---

## PAGE TYPE 5 — SETTINGS/ADMIN (Tabbed forms)

### Reference: GitHub Settings, Linear workspace settings, Stripe account settings

**Used on:** `settings.htmx.html`, `admin.htmx.html`, `email-templates.htmx.html`

### Layout Philosophy
Settings pages are for infrequent, deliberate configuration. The gold-standard (GitHub Settings) uses:
1. **Left vertical nav** (not top tabs) for sections — gives more room than a tab bar, works better with many sections
2. A **constrained content column** (max 720px) — wide enough for forms, narrow enough to feel focused
3. **Section-divided cards** — each logical group is its own card with a heading
4. **Fields never 100% width** — a full-width input inside a 720px card feels like an overwhelmingly long form. Constrain inputs to their semantic width.
5. **Save button at section level**, not page level — one save per form section prevents lost-changes anxiety

### ASCII Diagram

```
┌──────────────────────────────────────────────────────────────┐
│ [sidebar rail 58px]  PAGE HEADER — Settings                  │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌─────────────┐  ┌────────────────────────────────────┐   │
│  │ SIDE NAV    │  │ CONTENT COLUMN (max-width: 720px)  │   │
│  │ 220px       │  │                                    │   │
│  │             │  │  ┌──────────────────────────────┐  │   │
│  │ > Section 1 │  │  │ SECTION CARD                 │  │   │
│  │   Section 2 │  │  │ ─────────────────────────    │  │   │
│  │   Section 3 │  │  │ Heading  [sub-description]   │  │   │
│  │   Section 4 │  │  │                              │  │   │
│  │             │  │  │ Label                        │  │   │
│  │             │  │  │ [Input ── 480px max ───────] │  │   │
│  │             │  │  │ hint text                    │  │   │
│  │             │  │  │                              │  │   │
│  │             │  │  │ Label                        │  │   │
│  │             │  │  │ [Input ── 360px max ───────] │  │   │
│  │             │  │  │                              │  │   │
│  │             │  │  │ ───────────────── [Save]     │  │   │
│  │             │  │  └──────────────────────────────┘  │   │
│  │             │  │                                    │   │
│  │             │  │  ┌──────────────────────────────┐  │   │
│  │             │  │  │ SECTION CARD 2               │  │   │
│  │             │  │  └──────────────────────────────┘  │   │
│  └─────────────┘  └────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────┘
```

### CSS Specifications

```css
@layer v4 {

/* ── Settings main layout ── */
.settings-main {
  padding: 24px;
  padding-left: calc(var(--sidebar-rail) + 24px);
  display: flex;
  flex-direction: column;
  gap: 0;
}

/* ── Settings body — sidenav + content ── */
.settings-layout {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: 32px;
  align-items: flex-start;
  max-width: 1040px;   /* 220 + 32 + 720 + breathing room */
}

/* ── Left nav ── */
.settings-sidenav {
  width: 220px;
  flex-shrink: 0;
  position: sticky;
  top: calc(var(--header-height) + 24px);
}

.settings-sidenav__group-label {
  font-size: var(--text-xs);
  font-weight: var(--font-semibold);
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--color-text-muted);
  padding: 12px 12px 4px;
}

.settings-sidenav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 9px 12px;
  border-radius: var(--radius);     /* 8px */
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  color: var(--color-text-muted);
  cursor: pointer;
  border: none;
  background: transparent;
  text-align: left;
  transition: background var(--duration-fast), color var(--duration-fast);
}

.settings-sidenav-item:hover {
  background: var(--color-bg-subtle);
  color: var(--color-text);
}

.settings-sidenav-item.active {
  background: var(--color-primary-subtle);
  color: var(--color-primary);
  font-weight: var(--font-semibold);
}

/* ── Content column ── */
.settings-content {
  max-width: 720px;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* ── Section card ── */
.settings-section {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.settings-section__header {
  padding: 20px 24px 16px;
  border-bottom: 1px solid var(--color-border-subtle);
}

.settings-section__title {
  font-size: var(--text-base);     /* 16px */
  font-weight: var(--font-semibold);
  color: var(--color-text-dark);
}

.settings-section__description {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  margin-top: 4px;
}

.settings-section__body {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* ── Form field inside settings ── */
.settings-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

/* Input width semantics — NEVER 100% for standard inputs */
.settings-field input[type="text"],
.settings-field input[type="email"],
.settings-field input[type="url"],
.settings-field input[type="number"] {
  max-width: 480px;
}

.settings-field input[type="color"] {
  width: 48px;
  height: 32px;
}

.settings-field select {
  max-width: 320px;
}

.settings-field textarea {
  max-width: 100%;    /* Exception: textareas span full section width */
  min-height: 100px;
}

/* ── Section footer — save button row ── */
.settings-section__footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  background: var(--color-bg-subtle);
  border-top: 1px solid var(--color-border);
}

/* Danger zone — visually separated ── */
.settings-section--danger {
  border-color: var(--color-danger-border);
}
.settings-section--danger .settings-section__header {
  background: var(--color-danger-subtle);
}
.settings-section--danger .settings-section__title {
  color: var(--color-danger);
}

/* ── Responsive ── */
@media (max-width: 1024px) {
  .settings-layout {
    grid-template-columns: 180px 1fr;
    gap: 24px;
  }
}

@media (max-width: 768px) {
  .settings-main {
    padding: 16px;
    padding-left: 16px;
  }
  .settings-layout {
    grid-template-columns: 1fr;
  }
  .settings-sidenav {
    position: static;
    width: 100%;
    /* Collapse to horizontal scrolling tab bar on mobile */
    display: flex;
    flex-direction: row;
    overflow-x: auto;
    padding-bottom: 4px;
    gap: 4px;
  }
  .settings-sidenav__group-label {
    display: none;
  }
  .settings-sidenav-item {
    flex-shrink: 0;
    white-space: nowrap;
  }
}

} /* @layer v4 */
```

### Field Width Conventions

| Field Type | Max-width | Rationale |
|------------|-----------|-----------|
| Short text (name, title) | 480px | One concept, not a sentence |
| URL, email | 480px | URLs can be long but not infinite |
| Select / dropdown | 320px | Options are fixed-length |
| Short number | 160px | Port, count, year |
| Color picker | 48px | The swatch IS the width |
| Textarea / description | 100% of section | Multi-line content needs space |
| Toggle / checkbox | Natural (no width) | Don't stretch boolean inputs |

---

## PAGE TYPE 6 — MOBILE VOTER (Full-screen ballot)

### Reference: Slido live polling, Apple Pay sheet, native polling apps

**Used on:** `vote.htmx.html` (mobile-first, 100dvh)

### Layout Philosophy
The voter view is the **only** page explicitly mobile-first in AG-VOTE. A physical assembly room member pulls out their phone and votes on a resolution. The stakes are high (legally binding votes), the time window is short (60–90 seconds), and the environment is noisy (crowded room, hand-raised speakers, projector glare).

Gold standard requirements:
1. **Full viewport height** — `100dvh` (dynamic viewport height handles iOS Safari chrome)
2. **One question, one decision per screen** — no scrolling to find the vote buttons
3. **Vote buttons: minimum 72px tall, spanning ≥60% viewport width** — cannot be fat-fingered
4. **Confirmation state is instant** — optimistic feedback, color the selected button immediately
5. **Status header fixed top** — session name + resolution title never scroll away
6. **Zero ambiguity** — "Pour" (green), "Contre" (red), "Abstention" (grey), "Blanc" (neutral) — color + text + icon, never just color

### ASCII Diagram

```
┌────────────────────────────────────┐  ← 100dvh
│  HEADER BAR (56px)                 │
│  [Session name]     [Status badge] │
├────────────────────────────────────┤
│                                    │
│  RESOLUTION CARD                   │  ← flex: 1, scrollable
│                                    │     if title is very long
│  [N° de résolution]                │
│  ─────────────────────────────     │
│  Titre de la résolution             │
│  (font-display, 20–24px)           │
│                                    │
│  [Résumé / context if needed]      │
│  (font-sm, text-muted)             │
│                                    │
│  [PDF icon - see doc]              │
│                                    │
├────────────────────────────────────┤
│                                    │
│  VOTE BUTTONS AREA                 │  ← flex-shrink: 0
│  (padding: 20px, gap: 12px)        │     always visible
│                                    │
│  ┌──────────────────────────────┐  │
│  │  POUR         ✓  [72px tall] │  │  ← success color
│  └──────────────────────────────┘  │
│  ┌──────────────────────────────┐  │
│  │  CONTRE       ✗  [72px tall] │  │  ← danger color
│  └──────────────────────────────┘  │
│  ┌────────────┐  ┌───────────┐    │
│  │ ABSTENTION │  │  BLANC    │    │  ← 2-col, 56px tall
│  └────────────┘  └───────────┘    │
│                                    │
│  [env safe-area-inset-bottom]      │
└────────────────────────────────────┘
```

### CSS Specifications

```css
@layer v4 {

/* ── Voter shell — full height, no scroll ── */
.vote-app {
  display: flex;
  flex-direction: column;
  height: 100vh;
  height: 100dvh;       /* iOS Safari dynamic viewport */
  background: var(--color-bg);
  overflow: hidden;
}

/* ── Vote header ── */
.vote-header {
  flex-shrink: 0;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
  background: var(--color-surface);
  border-bottom: 1px solid var(--color-border);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.vote-header__session {
  font-size: var(--text-sm);
  font-weight: var(--font-semibold);
  color: var(--color-text-dark);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 60%;
}

/* ── Resolution card — scrollable middle ── */
.vote-resolution-area {
  flex: 1;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  padding: 24px 20px 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.vote-resolution-number {
  font-size: var(--text-xs);
  font-weight: var(--font-semibold);
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--color-text-muted);
}

.vote-resolution-title {
  font-family: var(--font-display);   /* Fraunces */
  font-size: clamp(1.125rem, 4vw, 1.5rem);  /* 18–24px fluid */
  font-weight: var(--font-semibold);
  line-height: var(--leading-snug);
  color: var(--color-text-dark);
}

.vote-resolution-body {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  line-height: var(--leading-relaxed);
}

/* ── Vote buttons area — never scrolls away ── */
.vote-actions {
  flex-shrink: 0;
  padding: 16px 20px;
  padding-bottom: calc(16px + env(safe-area-inset-bottom)); /* iOS safe area */
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: var(--color-surface);
  border-top: 1px solid var(--color-border);
}

/* Primary vote buttons — POUR + CONTRE */
.vote-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  width: 100%;
  min-height: 72px;                   /* MINIMUM — do not reduce */
  border-radius: var(--radius-lg);
  font-size: var(--text-lg);          /* 18px */
  font-weight: var(--font-bold);
  border: 2px solid transparent;
  cursor: pointer;
  transition:
    background var(--duration-fast),
    transform 80ms var(--ease-bounce),
    box-shadow var(--duration-fast);
  -webkit-tap-highlight-color: transparent;
  user-select: none;
}

.vote-btn:active {
  transform: scale(0.97);
}

/* POUR — green */
.vote-btn--pour {
  background: var(--color-success);
  color: #fff;
}
.vote-btn--pour:hover { background: var(--color-success-hover); }
.vote-btn--pour.selected {
  box-shadow: 0 8px 32px rgba(11, 122, 64, 0.35);
  transform: scale(1.01);
}

/* CONTRE — red */
.vote-btn--contre {
  background: var(--color-danger);
  color: #fff;
}
.vote-btn--contre:hover { background: var(--color-danger-hover); }
.vote-btn--contre.selected {
  box-shadow: 0 8px 32px rgba(196, 40, 40, 0.35);
  transform: scale(1.01);
}

/* Secondary buttons row — ABSTENTION + BLANC (2-column) */
.vote-btn-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.vote-btn--secondary {
  min-height: 56px;                   /* Secondary: 56px — still large but not dominant */
  font-size: var(--text-base);
  background: var(--color-surface);
  border-color: var(--color-border);
  color: var(--color-text);
}
.vote-btn--secondary:hover {
  background: var(--color-bg-subtle);
  border-color: var(--color-border-strong);
}

/* ABSTENTION — muted neutral */
.vote-btn--abstention.selected {
  background: var(--color-neutral-subtle);
  border-color: var(--color-neutral);
  color: var(--color-neutral-text);
}

/* BLANC — very neutral */
.vote-btn--blanc.selected {
  background: var(--color-bg-subtle);
  border-color: var(--color-border-strong);
}

/* ── Confirmation overlay ── */
.vote-confirmed-overlay {
  position: fixed;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  z-index: var(--z-overlay);
  /* background tinted by vote choice via JS data attribute */
}

.vote-confirmed-icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.vote-confirmed-label {
  font-family: var(--font-display);
  font-size: var(--text-2xl);
  font-weight: var(--font-bold);
  text-align: center;
}

/* ── Waiting state ── */
.vote-waiting {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 40px 24px;
  text-align: center;
}

.vote-waiting__spinner {
  /* Use existing ag-spinner component or CSS animation */
  width: 48px;
  height: 48px;
}

/* ── Responsive override for tablet ── */
/* On tablets (768px+), keep same pattern but allow slightly larger typography */
@media (min-width: 768px) {
  .vote-btn {
    min-height: 80px;
    font-size: var(--text-xl);
  }
  .vote-btn--secondary {
    min-height: 64px;
  }
  .vote-resolution-area {
    max-width: 560px;
    margin: 0 auto;
    width: 100%;
  }
  .vote-actions {
    max-width: 560px;
    margin: 0 auto;
    width: 100%;
    padding-left: 0;
    padding-right: 0;
  }
}

} /* @layer v4 */
```

### Touch Target Compliance

| Element | Min Height | Width | WCAG 2.5.5 |
|---------|-----------|-------|------------|
| POUR button | 72px | 100% | Pass (>44px) |
| CONTRE button | 72px | 100% | Pass |
| ABSTENTION | 56px | ~50% | Pass |
| BLANC | 56px | ~50% | Pass |
| Header icons | 44px | 44px | Pass (minimum) |

The 72px primary button height comes from: WCAG AA minimum 44px, but given the high-stakes real-time context (crowded room, hurried vote) and Slido/Mentimeter patterns, 72px provides substantial tap comfort and reduces error anxiety.

---

## Cross-Cutting Layout Patterns

### App Shell Structure (all authenticated pages)

The current app shell (`flex-column + fixed sidebar`) is sound. v4.1 refinements:

```css
@layer v4 {

/* ── App main scroll container — prevent double scrollbar ── */
.app-main {
  flex: 1;
  overflow-y: auto;
  min-height: 0;           /* critical for flex children scroll */
  /* padding from design-system.css preserved */
}

/* ── Page content inner wrapper ── */
/* Used by dashboard, meetings, archives, etc. */
.page-content-wrap {
  max-width: var(--content-max);    /* 1440px */
  margin: 0 auto;
  padding: 24px;
  /* Use page-content-wrap--narrow for wizard/settings */
}

.page-content-wrap--narrow {
  max-width: 860px;
}

} /* @layer v4 */
```

### Sidebar Rail + Content Offset Pattern

The 58px sidebar rail creates a layout offset for all non-operator pages:

```
Content left edge = 58px (rail) + 22px (inner padding) = 80px
Content right edge = 22px
```

When sidebar is pinned (252px):
```
Content left edge = 252px (expanded) + 22px = 274px
```

This is currently implemented with `padding-left: calc(var(--sidebar-rail) + 22px)` and the JS class `.pinned` override. The v4.1 refonte preserves this.

### Page Header Anatomy (all pages)

Every page header follows this exact structure:

```css
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  /* margin-bottom: 14px — set in app.css, do not override */
}

/* Left: breadcrumb + h1 + sub */
/* Right: CTAs (max 2 buttons + 1 icon action) */
```

**Never put more than 2 primary buttons in the page header.** If more actions are needed, use a dropdown trigger (kebab, "Actions ▾").

---

## Implementation Phasing for v4.1

| Page | Layout Type | Effort | Priority |
|------|-------------|--------|----------|
| `dashboard.htmx.html` | DASHBOARD | Medium | High — entry point |
| `wizard.htmx.html` | WIZARD | Low | High — creation flow |
| `operator.htmx.html` | SPLIT-PANEL | High | High — live meeting |
| `vote.htmx.html` | MOBILE VOTER | Low | High — voter UX |
| `members.htmx.html` | DATA TABLE | Medium | High — most-used admin |
| `audit.htmx.html` | DATA TABLE | Low | Medium |
| `archives.htmx.html` | DATA TABLE | Low | Medium |
| `settings.htmx.html` | SETTINGS | Medium | Medium |
| `admin.htmx.html` | SETTINGS | Medium | Medium |
| `hub.htmx.html` | HYBRID (cards) | Medium | High — pre-meeting staging |
| `postsession.htmx.html` | WIZARD-like | Low | Medium |
| `meetings.htmx.html` | DATA TABLE | Low | Medium |
| `analytics.htmx.html` | DASHBOARD-like | Medium | Low |
| `report.htmx.html` | READ-ONLY | Low | Low |
| `public.htmx.html` | READ-ONLY | Low | Low |

---

## Sources

Research sources (all verified March 2026):

- [CSS Grid Admin Dashboard — Max Böck](https://mxb.dev/blog/css-grid-admin-dashboard/) — 4-column pattern, 2rem gap, responsive switching
- [Responsive Dashboard Layout with CSS Grid — Compile7](https://compile7.org/decompile/build-a-responsive-dashboard-layout-with-css-grid) — sidebar 240px, gap 24px, 4-col KPI
- [Dashboard Design Best Practices — Brand.dev](https://www.brand.dev/blog/dashboard-design-best-practices) — KPI placement conventions
- [CSS Sidebar — Every Layout](https://every-layout.dev/layouts/sidebar/) — flex-basis sizing, min-inline-size 50% breakpoint
- [Stripe Dashboard Design Patterns — Stripe Docs](https://docs.stripe.com/stripe-apps/patterns) — design token approach, card anatomy
- [CSS Grid Guide — CSS-Tricks](https://css-tricks.com/complete-guide-css-grid-layout/) — authoritative grid reference
- [Position Sticky and Table Headers — CSS-Tricks](https://css-tricks.com/position-sticky-and-table-headers/) — sticky header implementation
- Existing AG-VOTE design-system.css — confirmed token values, spacing scale, breakpoints
