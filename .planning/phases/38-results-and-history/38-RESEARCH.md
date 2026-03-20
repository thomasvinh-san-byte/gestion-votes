# Phase 38: Results & History - Research

**Researched:** 2026-03-20
**Domain:** Visual redesign — Post-session workflow, Analytics dashboard, Meetings list
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Design Philosophy (carried from Phase 35-37)**
- Stripe Dashboard reference for data presentation clarity and density
- JetBrains Mono for all numeric data (KPIs, percentages, counts, dates)
- ag-tooltip on every metric and action explaining what it means
- Dramatic visible improvement — not subtle refinements

**Post-Session Visual Redesign (CORE-05)**
- 4-step stepper: Horizontal at top (Résultats → Validation → PV → Archivage). Completed steps show green checkmark, active step has primary glow, pending steps are muted. Sticky while scrolling
- Result cards: Each motion result as a collapsible card. Header shows: motion number badge, title, ADOPTÉ (green) or REJETÉ (red) verdict badge large and prominent. Collapsed by default after first view
- Vote breakdown: Inside expanded card — colored bar chart (pour/contre/abstention segments), exact counts in JetBrains Mono, percentage next to each. Quorum status indicator
- Section spacing: 48px (--space-section) between major sections (Résultats, Validation, PV, Archivage). Each section has a clear heading with step number
- PV section: Preview card with document thumbnail/icon, download CTA button (gradient primary), generation status badge
- Archival section: Clean checklist of archival steps with checkmarks for completed items
- Status tooltips: Each stepper step has a tooltip explaining what happens at that stage

**Analytics Visual Redesign (DATA-05)**
- KPI row: Top row of 4 KPI cards (like dashboard but for statistics — total sessions, votes cast, participation rate, average quorum). Each card with large mono number, trend indicator if data available, tooltip explaining the metric
- Chart layout: Responsive grid (min 2 columns at 1024px). Each chart in a card with a clear title, subtitle explaining the data, and the chart canvas with proper padding
- Chart styling: Chart.js canvases with consistent color palette using design-system tokens (primary for main data, muted for secondary). Grid lines subtle, axis labels in --text-xs
- Data density: Compact table below charts for detailed breakdown — session-by-session data. Uses .table-page shared structure from Phase 32
- Time filters: Clean filter bar at top — period selector (7j, 30j, 90j, 1an, Tout) as pill buttons, date range picker
- Responsive: Charts maintain 2-column minimum at 1024px. KPI row goes 2-col at 768px
- Metric tooltips: Every KPI and chart title has an ag-tooltip explaining what the metric means and how it's calculated

**Meetings List Visual Redesign (DATA-06)**
- Session cards: Each session as a card with: title (semibold), date (mono), type badge (AG ordinaire/extraordinaire), status badge (brouillon/convoquée/en cours/terminée/archivée) using semantic ag-badge colors
- Card density: 12px gap between cards (--space-3). Cards show key info at a glance — no need to click to understand session state
- Action buttons: Hover-reveal pattern (like dashboard session cards from Phase 35). "Ouvrir" / "Reprendre" / "Voir résultats" depending on session state
- Filter/sort toolbar: Above the card list — status filter as pill buttons, sort by date/title, search field
- Empty state: When no sessions match filters, clear message with CTA to create a new session
- Max-width: 1200px centered (matching dashboard pattern)
- Pagination: If many sessions — bottom pagination bar with page numbers and count

### Claude's Discretion
- Chart.js color palette exact values
- Whether to add sparklines to KPI cards
- Post-session result card expand/collapse animation style
- Filter pill button active state styling
- Whether meetings list uses infinite scroll or pagination

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| CORE-05 | Post-session — redesign visuel (stepper, result cards, progression archivage, espacement sections, status tooltips) | Stepper CSS upgrade, result card verdict prominence, bar chart inside card, section spacing with --space-section, ag-tooltip wrapping |
| DATA-05 | Analytics — redesign visuel (KPI cards, chart layout, responsive grid, metric tooltips) | Replace .overview-card with .kpi-card pattern, analytics KPI grid, chart card subtitle slot, period pill redesign, ag-tooltip on every card |
| DATA-06 | Meetings list — redesign visuel (session cards, status badges, actions) | Migrate .session-item to .session-card pattern from design-system, add hover-reveal CTA, type badge, ag-tooltip on actions |
</phase_requirements>

---

## Summary

Phase 38 redesigns three pages that together form the "after the session" workflow: post-session guided closure (CORE-05), analytics/statistics (DATA-05), and the sessions list (DATA-06). All three pages already exist with functioning logic — this phase is purely visual CSS and minimal HTML additions (badges, tooltips, layout upgrades).

The design system already contains the patterns these pages need. `.kpi-card` with `.kpi-icon` and `.kpi-value` (from pages.css, Phase 35) replaces both the analytics `.overview-card` and the post-session `.ps-validation-kpi`. `.session-card` with `.session-card-cta` hover-reveal (from design-system.css, Phase 35) replaces `.session-item` in meetings.js. The stepper needs a visual upgrade: rounded pill steps with icons, glow on active, green checkmark on done — all achievable with CSS-only changes to `.ps-seg`.

The critical constraint from v4.2 lessons: every change must produce a visible before/after contrast visible at 1080p. Token swaps that only move colors without changing typography scale, spacing structure, or composition are insufficient.

**Primary recommendation:** Upgrade CSS for all three pages to use the established `.kpi-card`, `.session-card`, and `.ps-seg` design system patterns; add ag-tooltip wrappers where missing; add verdict prominence through font-size escalation and left-border color coding on result cards.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Vanilla CSS custom properties | n/a | All styling via design tokens | No build step — project constraint |
| Chart.js | UMD (already loaded) | Canvas chart rendering | Already integrated in analytics page |
| ag-tooltip | project component | Hover tooltips on metrics/actions | Established in phases 35-37 |
| ag-badge | project component | Status pill rendering | Established in phases 35-37 |

### Reusable Patterns from Design System
| Pattern | CSS File | Classes | Purpose |
|---------|----------|---------|---------|
| KPI Card | pages.css (L.1116-1167) | `.kpi-card`, `.kpi-icon`, `.kpi-value`, `.kpi-label`, `.kpi-card--N` | Large mono numbers with icon accent |
| Session Card | design-system.css (L.4962-5070) | `.session-card`, `.session-card-cta`, `.session-card-title`, `.session-card-meta` | Card row with hover-reveal CTA |
| KPI Grid | pages.css (L.1018-1028) | `.kpi-grid`, `.kpi-grid ag-tooltip { display: contents; }` | 4-column responsive grid with tooltip wrapper fix |

---

## Architecture Patterns

### Recommended Project Structure
No new files. Changes are within:
```
public/assets/css/postsession.css    — stepper upgrade, verdict prominence
public/assets/css/analytics.css     — KPI card migration, chart card subtitle, period pills
public/assets/css/meetings.css      — session-item → session-card migration, hover-reveal
public/postsession.htmx.html        — ag-tooltip wrappers on stepper steps, verdict badge HTML
public/analytics.htmx.html          — KPI HTML structure + ag-tooltip wrappers
public/meetings.htmx.html           — filter pills markup alignment (minor)
public/assets/js/pages/meetings.js  — renderSessionItem() to emit .session-card HTML
public/assets/js/pages/postsession.js — renderResultCards() verdict badge scale
```

### Pattern 1: Post-Session Stepper Visual Upgrade

**What:** Replace flat `.ps-seg` segments with pill-style steps that have icons, glow on active, and green checkmark on complete.

**Current state:**
```css
/* BEFORE — flat colored segment */
.ps-seg.active {
  background: var(--color-primary);
  color: var(--color-text-inverse);
  font-weight: 800;
}
```

**After:**
```css
/* AFTER — pill stepper with glow */
.ps-seg {
  flex: 1;
  padding: 10px 16px;
  border-radius: var(--radius-full);          /* pill shape */
  border: 1.5px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  transition: all var(--duration-normal) var(--ease-standard);
}
.ps-seg.active {
  background: var(--color-primary);
  color: var(--color-primary-text);
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px var(--color-primary-glow);  /* primary glow */
  font-weight: 700;
}
.ps-seg.done,
.ps-seg.step-complete {
  background: var(--color-success-subtle);
  color: var(--color-success-text);
  border-color: var(--color-success-border);
}
```

**Step connector line:** Insert a thin `--color-border` line between pills using a pseudo-element on `.ps-stepper` rather than per-segment gaps.

### Pattern 2: Result Card Verdict Prominence

**What:** The ADOPTÉ/REJETÉ verdict must be readable across a meeting room. Current size is `var(--text-sm)` on the summary and `var(--text-2xl)` inside the expanded body. Upgrade the summary badge to be immediately visible.

**Current HTML structure (JS-generated in postsession.js):**
```html
<summary class="result-card-summary">
  <span class="result-card-num">Résolution 1</span>
  <span class="result-card-title">Motion title</span>
  <span class="result-card-verdict result-adopted">✓ ADOPTÉ</span>  <!-- text-sm -->
</summary>
```

**After — summary verdict badge upgrade:**
```css
/* AFTER */
.result-card-verdict {
  font-size: var(--text-base);    /* up from text-sm */
  font-weight: 800;
  padding: var(--space-1) var(--space-3);
  border-radius: var(--radius-full);
  letter-spacing: 0.04em;
}
.result-adopted {
  background: var(--color-success-subtle);
  color: var(--color-success-text);
  border: 1px solid var(--color-success-border);
}
.result-rejected {
  background: var(--color-danger-subtle);
  color: var(--color-danger-text);
  border: 1px solid var(--color-danger-border);
}

/* Left-border color coding on the card itself */
.result-card[data-verdict="adopted"]  { border-left: 4px solid var(--color-success); }
.result-card[data-verdict="rejected"] { border-left: 4px solid var(--color-danger); }
```

JS change in `renderResultCards()`: add `data-verdict="${adopted ? 'adopted' : 'rejected'}"` on `<details>`. No other JS changes needed.

**Expanded verdict (large prominent display):**
```css
/* AFTER */
.result-card-verdict-large {
  font-size: 2.5rem;              /* up from text-2xl=1.5rem */
  font-weight: 900;
  font-family: var(--font-display, 'Bricolage Grotesque', sans-serif);
  letter-spacing: -0.02em;
  text-align: center;
  padding: var(--space-6) var(--space-4);
}
```

### Pattern 3: Analytics KPI Migration

**What:** Replace `.overview-card` / `.overview-grid` with the established `.kpi-card` / `.kpi-grid` pattern from pages.css (Phase 35). This gives JetBrains Mono large numbers, colored icons, and ag-tooltip wrappers.

**Current HTML (analytics.htmx.html L.93-126):**
```html
<div class="overview-grid" id="overviewCards">
  <div class="overview-card">
    <div class="overview-card-label">Séances</div>
    <div class="overview-card-value primary" id="kpiMeetings">—</div>
    <div class="overview-card-trend" id="kpiMeetingsTrend">...</div>
  </div>
  ...
</div>
```

**After HTML:**
```html
<div class="kpi-grid analytics-kpi-grid" id="overviewCards" aria-live="polite">
  <ag-tooltip text="Nombre total de séances clôturées sur la période sélectionnée" position="bottom">
    <div class="kpi-card kpi-card--1">
      <div class="kpi-icon">
        <svg class="icon icon-sm"><use href="/assets/icons.svg#icon-calendar"></use></svg>
      </div>
      <div class="kpi-value" id="kpiMeetings">—</div>
      <div class="kpi-label">Séances</div>
      <div class="overview-card-trend" id="kpiMeetingsTrend">...</div>
    </div>
  </ag-tooltip>
  <!-- repeat for 3 other KPIs -->
</div>
```

**CSS change — analytics.css:**
```css
/* Reuse .kpi-grid from pages.css; override grid gap for analytics page */
.analytics-kpi-grid {
  margin-bottom: var(--space-section);  /* 48px breathing room before charts */
}

/* Keep .analytics-main padding consistent */
.app-main.analytics-main {
  padding: var(--space-6) var(--space-6) var(--space-6) calc(var(--sidebar-rail) + var(--space-6));
}
```

### Pattern 4: Analytics Period Filter Upgrade

**What:** Replace the current `.period-selector` pill group with a design-system-consistent filter pill style (matching meetings page). Add 7j/30j/90j/1an options as locked in CONTEXT.

**Current HTML:**
```html
<div class="charts-controls">
  <div class="period-selector">
    <button class="period-btn" data-period="month">Mois</button>
    ...
  </div>
</div>
```

**After HTML:**
```html
<div class="analytics-filter-bar" role="toolbar" aria-label="Filtres analytics">
  <div class="analytics-period-pills" role="group" aria-label="Période">
    <button class="analytics-period-pill active" data-period="7j">7j</button>
    <button class="analytics-period-pill" data-period="30j">30j</button>
    <button class="analytics-period-pill" data-period="90j">90j</button>
    <button class="analytics-period-pill" data-period="1an">1 an</button>
    <button class="analytics-period-pill" data-period="all">Tout</button>
  </div>
  <div class="analytics-filter-actions">
    <button class="btn btn-ghost btn-icon btn-sm" id="refreshBtn" ...>...</button>
    <button class="btn btn-secondary btn-sm" id="btnExportCsv">CSV</button>
    <button class="btn btn-secondary btn-sm" id="btnExportPdf">PDF</button>
  </div>
</div>
```

**CSS:**
```css
.analytics-filter-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-4);
  margin-bottom: var(--space-card);
  flex-wrap: wrap;
}
.analytics-period-pills {
  display: flex;
  gap: var(--space-1);
  background: var(--color-bg-subtle);
  padding: 3px;
  border-radius: var(--radius-full);
}
.analytics-period-pill {
  padding: 6px 14px;
  border: none;
  background: transparent;
  border-radius: var(--radius-full);
  cursor: pointer;
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  font-weight: 600;
  transition: all var(--duration-fast);
}
.analytics-period-pill.active {
  background: var(--color-surface);
  color: var(--color-primary);
  box-shadow: var(--shadow-sm);
}
```

### Pattern 5: Meetings Session-Item to Session-Card Migration

**What:** The existing `.session-item` is a custom row. `design-system.css` already has `.session-card` with hover-reveal CTA. Meetings page should use that established pattern.

**Current JS output (meetings.js `renderSessionItem()`):**
```html
<div class="session-item" data-id="..." onclick="...">
  <span class="session-dot completed"></span>
  <div class="session-info">
    <div class="session-title">AG Ordinaire 2026</div>
    <div class="session-meta">...</div>
  </div>
  <span class="meeting-card-status closed">...</span>
  <div class="session-actions">
    <button class="session-menu-btn">...</button>
  </div>
</div>
```

**After JS output — `renderSessionItem()` change:**
```html
<div class="session-card" data-id="..." data-status="closed">
  <div class="session-card-info">
    <div class="session-card-title">AG Ordinaire 2026</div>
    <div class="session-card-meta">
      <span class="session-card-date font-mono">15 mars 2026</span>
      <span class="session-card-meta-sep">·</span>
      <span>12 participants</span>
      <span class="session-card-meta-sep">·</span>
      <span class="session-meta-item resolutions">5 résolutions</span>
    </div>
  </div>
  <!-- Type badge: ag_ordinaire / extraordinaire etc -->
  <span class="ag-badge ag-badge--neutral meeting-type-badge">AG ordinaire</span>
  <!-- Status badge via ag-badge -->
  <ag-badge variant="success" size="sm">Terminée</ag-badge>
  <!-- Hover-reveal CTA -->
  <a class="btn btn-primary btn-sm session-card-cta" href="/hub.htmx.html?meeting_id=...">
    Voir résultats
  </a>
  <!-- 3-dot menu remains -->
  <button class="btn btn-ghost btn-icon btn-sm session-menu-btn" ...>...</button>
</div>
```

**CSS additions to meetings.css:**
```css
/* Use design-system .session-card — override gap for meetings density */
.sessions-list .session-card {
  gap: var(--space-3);       /* tighter than dashboard default 14px */
}

/* Type badge — muted neutral pill */
.meeting-type-badge {
  font-size: 0.6875rem;
  padding: 2px 8px;
  border-radius: var(--radius-full);
  background: var(--color-neutral-subtle, var(--color-bg-subtle));
  color: var(--color-text-muted);
  border: 1px solid var(--color-border);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  flex-shrink: 0;
  white-space: nowrap;
}

/* Date in mono */
.sessions-list .session-card-date {
  font-family: var(--font-mono);
  font-size: var(--text-xs);
}

/* State-based left border accent (optional, reinforces status) */
.sessions-list .session-card[data-status="live"] {
  border-left: 3px solid var(--color-danger);
}
.sessions-list .session-card[data-status="closed"],
.sessions-list .session-card[data-status="validated"],
.sessions-list .session-card[data-status="archived"] {
  border-left: 3px solid var(--color-success);
}
```

### Pattern 6: Chart Card Subtitle Slot

**What:** Each chart card needs a subtitle explaining what the data means (per CONTEXT). Add `.chart-card-subtitle` below the title.

**Current HTML per chart card:**
```html
<div class="chart-card-header">
  <h3 class="chart-card-title">Taux de participation par séance</h3>
</div>
```

**After:**
```html
<div class="chart-card-header">
  <div>
    <h3 class="chart-card-title">Taux de participation par séance</h3>
    <p class="chart-card-subtitle">% membres présents / membres inscrits</p>
  </div>
  <ag-tooltip text="Rapport entre membres présents et membres inscrits pour chaque séance" position="bottom">
    <button class="btn btn-xs btn-ghost chart-info-btn" aria-label="Information sur ce graphique">
      <svg class="icon icon-xs"><use href="/assets/icons.svg#icon-info"></use></svg>
    </button>
  </ag-tooltip>
</div>
```

```css
.chart-card-subtitle {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
  margin: 2px 0 0;
  font-weight: 400;
}
```

### Pattern 7: Post-Session ag-tooltip on Stepper Steps

**What:** Each ps-seg step needs an ag-tooltip explaining what that stage does. Wrap each `.ps-seg` in `<ag-tooltip>`.

**HTML change in postsession.htmx.html:**
```html
<!-- BEFORE -->
<div class="ps-seg active" data-step="1" aria-current="step">
  <span class="ps-seg-num">1.</span> Vérification
</div>

<!-- AFTER -->
<ag-tooltip text="Vérifiez que tous les votes sont enregistrés et les résultats cohérents" position="bottom">
  <div class="ps-seg active" data-step="1" aria-current="step">
    <span class="ps-seg-num">1.</span> Vérification
  </div>
</ag-tooltip>
```

**CSS fix required** — ag-tooltip inside flex stepper needs `display: contents` to not break layout:
```css
.ps-stepper ag-tooltip {
  display: contents;
}
```

### Anti-Patterns to Avoid

- **Replacing `.result-card` with a non-`<details>` element:** The `<details>/<summary>` pattern is what drives the collapse/expand without JS. Keep it — just restyle.
- **Changing `renderResultCards()` function signature:** The function is called directly from `loadVerification()`. Any refactor must maintain the same container parameter contract.
- **Removing `.overview-card` class entirely from analytics.css:** The JS file references `overview-card-trend` for the trend arrow update. Either keep the class or update the JS reference simultaneously.
- **Overriding `.kpi-grid` in analytics.css to change column count:** The existing `.kpi-grid` uses `repeat(4, 1fr)`. Analytics needs 4 KPI cards — no override needed, just add `analytics-kpi-grid` as a modifier for spacing tweaks.
- **Touching the Chart.js canvas IDs:** All chart IDs (`participationChart`, `sessionsByMonthChart` etc.) are referenced in `analytics-dashboard.js`. Never rename them in HTML.
- **Changing `.session-item` class without updating JS:** `meetings.js` emits `.session-item` in `renderSessionItem()`. The CSS and JS changes must be coordinated in a single plan wave.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Verdict badge color | Custom inline style | `.result-adopted` / `.result-rejected` classes | Already defined in postsession.css |
| Status pills | New badge component | `.meeting-card-status` classes (already in meetings.css) / ag-badge | All variants already styled |
| Hover-reveal actions | Custom JS mouseenter/leave | CSS `opacity: 0` → `.session-card:hover .session-card-cta { opacity: 1 }` | Pattern already in design-system.css L.4982-4991 |
| KPI trend arrows | Unicode hack or custom icon | `.overview-card-trend.up/down` + `.trend-up` / `.trend-down` CSS classes | Already in analytics.css L.550-573 |
| Chart loading spinner | JS-injected spinner | `.chart-container::after` CSS spinner + `.loaded` class removal | Already in analytics.css L.300-318 |
| Pill filter buttons | Third-party filter lib | `.filter-pill` / `.analytics-period-pill` CSS patterns | Established pattern in meetings.css and analytics.css |

---

## Common Pitfalls

### Pitfall 1: ag-tooltip wrapper breaks flexbox layout
**What goes wrong:** Wrapping `.ps-seg` or `.kpi-card` in `<ag-tooltip>` inserts a shadow-DOM element that breaks `display: flex` parent layout — the grid/flex children count changes.
**Why it happens:** `<ag-tooltip>` is an inline-flex element by default (`:host { display: inline-flex; }`). Inside a flex row, it becomes a flex item with different sizing than the raw child.
**How to avoid:** Add `display: contents` to the tooltip host:
```css
.ps-stepper ag-tooltip { display: contents; }
.kpi-grid ag-tooltip { display: contents; }  /* already in pages.css L.1026 */
.analytics-kpi-grid ag-tooltip { display: contents; }
```
**Warning signs:** Stepper steps collapse to minimum width or KPI grid breaks to fewer columns than expected.

### Pitfall 2: .overview-card-trend JS reference after HTML migration
**What goes wrong:** `analytics-dashboard.js` selects trend elements by ID (`kpiMeetingsTrend`, etc.) and updates `.overview-card-trend` classes. If HTML structure changes but IDs remain, the JS still works. But if `.overview-card-trend` class is removed from analytics.css, the trend direction classes (`.up`, `.down`) lose their color styling.
**Why it happens:** The trend color is bound to `.overview-card-trend.up { color: var(--color-success); }`.
**How to avoid:** Keep `.overview-card-trend` CSS rules in analytics.css even after migrating the KPI card HTML. The IDs are on the div, not the class — both can coexist.
**Warning signs:** Trend arrows appear but are all `--color-text-muted` with no green/red color.

### Pitfall 3: result-card `<details>` open attribute styling
**What goes wrong:** Adding `border-left` color to `.result-card` via `data-verdict` attribute only works if the attribute is set in HTML. The JS generates `<details class="result-card">` without `data-verdict`.
**Why it happens:** `renderResultCards()` in postsession.js builds HTML strings without `data-verdict`.
**How to avoid:** Add `data-verdict` to the HTML string in `renderResultCards()`:
```js
html += '<details class="result-card" data-verdict="' + (adopted ? 'adopted' : 'rejected') + '">'
```
**Warning signs:** All result cards have the same left border color regardless of verdict.

### Pitfall 4: meetings.js renderSessionItem() class mismatch
**What goes wrong:** If CSS adds `.session-card` styles but JS still emits `.session-item`, the new styles have no effect. Vice versa — if JS is updated to `.session-card` but CSS still only has `.session-item`, the hover-reveal from design-system.css does not apply because `.session-item` is not `.session-card`.
**Why it happens:** CSS and JS are changed in different plan waves without coordination.
**How to avoid:** The plan must change both `meetings.js` and `meetings.css` in the same wave. After wave completion, verify in browser that `.session-card:hover .session-card-cta` triggers opacity change.
**Warning signs:** Session list items render unstyled (no border radius, no hover effect) after the wave.

### Pitfall 5: post-session container max-width mismatch
**What goes wrong:** The `.container` in postsession HTML has a max-width applied via `.postsession-main .container` in postsession.css (900px). The `.ps-stepper` is inside `.container`, so it inherits that constraint. If the stepper pill layout is designed for wider screens, it will wrap at 900px.
**Why it happens:** 900px is narrow for 4 pills. At 600px-900px, pills may wrap to 2x2 grid.
**How to avoid:** The existing responsive rule already handles this:
```css
@media (max-width: 768px) {
  .ps-seg { flex: 1 1 calc(50% - 2px); }
}
```
Ensure the pill design does not require a minimum width greater than `(900px - gaps) / 4 ≈ 218px`.

---

## Code Examples

### KPI Card HTML Pattern (verified from pages.css + Phase 35 implementation)
```html
<!-- Source: pages.css L.1116-1167, Phase 35 dashboard -->
<div class="kpi-grid analytics-kpi-grid">
  <ag-tooltip text="Nombre total de séances clôturées" position="bottom">
    <div class="kpi-card kpi-card--1">
      <div class="kpi-icon">
        <svg class="icon icon-sm" aria-hidden="true">
          <use href="/assets/icons.svg#icon-calendar"></use>
        </svg>
      </div>
      <div class="kpi-value" id="kpiMeetings">—</div>
      <div class="kpi-label">Séances</div>
    </div>
  </ag-tooltip>
  <ag-tooltip text="Total des votes exprimés sur toutes les résolutions" position="bottom">
    <div class="kpi-card kpi-card--2">
      <div class="kpi-icon">
        <svg class="icon icon-sm" aria-hidden="true">
          <use href="/assets/icons.svg#icon-check-square"></use>
        </svg>
      </div>
      <div class="kpi-value" id="kpiResolutions">—</div>
      <div class="kpi-label">Résolutions votées</div>
    </div>
  </ag-tooltip>
  <ag-tooltip text="% de résolutions adoptées parmi toutes les résolutions mises au vote" position="bottom">
    <div class="kpi-card kpi-card--3">
      <div class="kpi-icon">
        <svg class="icon icon-sm" aria-hidden="true">
          <use href="/assets/icons.svg#icon-trending-up"></use>
        </svg>
      </div>
      <div class="kpi-value" id="kpiAdoptionRate">—</div>
      <div class="kpi-label">Taux d'adoption</div>
    </div>
  </ag-tooltip>
  <ag-tooltip text="Taux de participation moyen (membres présents / membres inscrits)" position="bottom">
    <div class="kpi-card kpi-card--4">
      <div class="kpi-icon">
        <svg class="icon icon-sm" aria-hidden="true">
          <use href="/assets/icons.svg#icon-users"></use>
        </svg>
      </div>
      <div class="kpi-value" id="kpiParticipation">—</div>
      <div class="kpi-label">Participation</div>
    </div>
  </ag-tooltip>
</div>
```

### Session Card JS Render Pattern (meetings.js renderSessionItem)
```javascript
// Source: design-system.css session-card pattern (L.4962-5070)
function renderSessionItem(m) {
  var id = m.id || m.meeting_id;
  var status = m.status || 'draft';
  var statusLabel = STATUS_LABELS[status] || status;
  var tagVariant = TAG_VARIANT_MAP[status] || 'muted';
  var typeLabel = TYPE_LABELS[m.meeting_type] || 'Séance';
  var ctaLabel = getCtaLabel(status);  // 'Ouvrir' / 'Reprendre' / 'Voir résultats'
  var ctaHref = getCtaHref(status, id);

  return '<div class="session-card" data-id="' + id + '" data-status="' + status + '">' +
    '<div class="session-card-info">' +
      '<div class="session-card-title">' + Utils.escapeHtml(m.title || '') + '</div>' +
      '<div class="session-card-meta">' +
        '<span class="session-card-date">' + Utils.formatDate(m.scheduled_at) + '</span>' +
        '<span class="session-card-meta-sep">·</span>' +
        '<span>' + (m.participant_count || 0) + ' participants</span>' +
        '<span class="session-card-meta-sep session-meta-item resolutions">·</span>' +
        '<span class="session-meta-item resolutions">' + (m.motions_count || 0) + ' résolutions</span>' +
      '</div>' +
    '</div>' +
    '<span class="meeting-type-badge">' + Utils.escapeHtml(typeLabel) + '</span>' +
    '<span class="meeting-card-status ' + status + '">' +
      '<span class="status-dot"></span>' + statusLabel +
    '</span>' +
    '<a class="btn btn-sm btn-primary session-card-cta" href="' + ctaHref + '">' + ctaLabel + '</a>' +
    '<button class="btn btn-ghost btn-icon btn-sm session-menu-btn" data-meeting-id="' + id + '" aria-label="Actions" onclick="event.stopPropagation()">' +
      '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>' +
    '</button>' +
  '</div>';
}

function getCtaLabel(status) {
  if (status === 'live' || status === 'paused') return 'Reprendre';
  if (status === 'closed' || status === 'validated' || status === 'archived') return 'Voir résultats';
  return 'Ouvrir';
}

function getCtaHref(status, id) {
  if (status === 'closed' || status === 'validated' || status === 'archived') {
    return '/postsession.htmx.html?meeting_id=' + id;
  }
  return '/hub.htmx.html?meeting_id=' + id;
}
```

### Post-Session Stepper with Tooltips (postsession.htmx.html)
```html
<!-- Source: CONTEXT.md decisions, ag-tooltip component pattern -->
<div class="ps-stepper" id="stepper" role="navigation" aria-label="Étapes de clôture">
  <ag-tooltip text="Vérifiez que tous les votes sont enregistrés et les résultats cohérents avant de continuer" position="bottom">
    <div class="ps-seg active" data-step="1" aria-current="step">
      <span class="ps-seg-num">1.</span> Vérification
    </div>
  </ag-tooltip>
  <ag-tooltip text="Le président valide officiellement les résultats — action irréversible" position="bottom">
    <div class="ps-seg" data-step="2">
      <span class="ps-seg-num">2.</span> Validation
    </div>
  </ag-tooltip>
  <ag-tooltip text="Générez et vérifiez le procès-verbal. Le hash garantit l'intégrité du document" position="bottom">
    <div class="ps-seg" data-step="3">
      <span class="ps-seg-num">3.</span> Procès-verbal
    </div>
  </ag-tooltip>
  <ag-tooltip text="Envoyez le PV aux participants et archivez définitivement la séance" position="bottom">
    <div class="ps-seg" data-step="4">
      <span class="ps-seg-num">4.</span> Envoi &amp; Archivage
    </div>
  </ag-tooltip>
</div>
```

### Chart.js Color Palette (Claude's discretion — using design tokens)
```javascript
// Source: design-system.css color tokens
// Chart.js colors aligned to CSS design tokens
var CHART_COLORS = {
  primary:   '#1650E0',   // --color-primary
  success:   '#0B7A40',   // --color-success
  danger:    '#C42828',   // --color-danger
  warning:   '#B8860B',   // --color-warning
  muted:     '#95A3A4',   // --color-text-muted
  primaryAlpha: 'rgba(22, 80, 224, 0.12)',   // --color-primary-glow
  successAlpha: 'rgba(11, 122, 64, 0.12)',
  dangerAlpha:  'rgba(196, 40, 40, 0.12)',
};
// Dark mode: use --color-primary on [data-theme="dark"] = #3D7EF8
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `.session-item` rows (flat list) | `.session-card` with hover-reveal CTA | Phase 35 dashboard | Meetings page not yet migrated |
| `.overview-card` with generic sizing | `.kpi-card` with mono numbers + colored icon | Phase 35 | Analytics page not yet migrated |
| Flat colored `.ps-seg` segments | Pill stepper with glow + green checkmark | Phase 33 partial → Phase 38 full upgrade | Post-session not fully at Phase 35 design level |
| Chart export btn always visible | Hover-reveal opacity 0→1 | Current | Already in analytics.css, correct |

**Items that need Phase 38 alignment:**
- `analytics.htmx.html` KPI grid: still uses `overview-card` not `kpi-card`
- `meetings.js` renderSessionItem: still emits `session-item` not `session-card`
- `postsession.css` `.ps-seg`: flat segments without pill shape or glow
- `analytics.htmx.html` period buttons: missing 7j/30j/90j period options

---

## Open Questions

1. **Analytics JS file name discrepancy**
   - What we know: `analytics.htmx.html` loads `analytics-dashboard.js` not `analytics.js`. The CONTEXT.md reference lists `analytics.js` as the file to read.
   - What's unclear: Is `analytics.js` a different file or does it not exist?
   - Recommendation: Treat `analytics-dashboard.js` as the authoritative analytics JS. No `analytics.js` was found in `/assets/js/pages/`.

2. **ag-badge component vs. `.meeting-card-status` CSS class**
   - What we know: `ag-badge` is referenced in CONTEXT and the component exists. `.meeting-card-status` CSS class is already fully styled in meetings.css with all status variants.
   - What's unclear: The CONTEXT says "using semantic ag-badge colors" — whether this means use the `<ag-badge>` web component or the CSS class.
   - Recommendation: Keep `.meeting-card-status` CSS class for status badges (zero JS change needed). Add a `meeting-type-badge` class for the type pill. Reserve `<ag-badge>` for future standardization.

3. **Validation KPIs (Step 2) vs. new dashboard KPI style**
   - What we know: Post-session Step 2 has `.ps-validation-kpis` / `.ps-validation-kpi` (4 mini KPIs). These should also be upgraded per CONTEXT.
   - What's unclear: Should these use the full `.kpi-card` pattern or a compact inline version?
   - Recommendation: Use a compact version (no icon, smaller mono value `--text-3xl`, same border/surface as `.kpi-card`) so they fit the centered 900px layout. Name them `.ps-kpi-grid` / `.ps-kpi-card` to avoid collision.

---

## Validation Architecture

`workflow.nyquist_validation` key absent from config.json — treating as enabled.

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Manual browser visual verification (v4.2 pure CSS/HTML phase) |
| Config file | none |
| Quick run command | Open browser at `http://localhost/postsession.htmx.html`, `analytics.htmx.html`, `meetings.htmx.html` |
| Full suite command | Visual check all three pages at 1080p and 768px viewport |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| CORE-05 | Stepper pills with glow/checkmark | visual | n/a — browser visual | ❌ Wave 0 |
| CORE-05 | Result card verdict ADOPTÉ/REJETÉ prominent at 1080p | visual | n/a | ❌ Wave 0 |
| CORE-05 | Bar chart inside expanded result card | visual | n/a | ❌ Wave 0 |
| CORE-05 | 48px section spacing between stepper panels | visual | n/a | ❌ Wave 0 |
| CORE-05 | ag-tooltip on each stepper step | visual | n/a | ❌ Wave 0 |
| DATA-05 | 4 KPI cards with JetBrains Mono numbers | visual | n/a | ❌ Wave 0 |
| DATA-05 | Chart cards have subtitle text | visual | n/a | ❌ Wave 0 |
| DATA-05 | Period pills (7j/30j/90j/1an/Tout) | visual | n/a | ❌ Wave 0 |
| DATA-05 | ag-tooltip on each KPI card | visual | n/a | ❌ Wave 0 |
| DATA-06 | Session cards with hover-reveal CTA | visual | n/a | ❌ Wave 0 |
| DATA-06 | Type badge + status badge per card | visual | n/a | ❌ Wave 0 |
| DATA-06 | CTA label changes by status (Ouvrir/Reprendre/Voir résultats) | visual | n/a | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** Open the changed page in browser, confirm no console errors, confirm visible change
- **Per wave merge:** All three pages at 1080p and 768px; dark mode check; verify JS still loads data
- **Phase gate:** All three pages pass visual review before `/gsd:verify-work`

### Wave 0 Gaps
None — no test framework to install. Visual verification is the test mode for v4.2 CSS phases.

---

## Sources

### Primary (HIGH confidence)
- `public/assets/css/postsession.css` — full current state read
- `public/assets/css/analytics.css` — full current state read
- `public/assets/css/meetings.css` — full current state read
- `public/assets/css/pages.css` L.1015-1167 — KPI card pattern
- `public/assets/css/design-system.css` L.4962-5070 — session-card hover-reveal pattern
- `public/assets/css/design-system.css` L.61-366 — all design tokens
- `public/postsession.htmx.html` — full HTML structure
- `public/analytics.htmx.html` — full HTML structure
- `public/meetings.htmx.html` — full HTML structure
- `public/assets/js/pages/postsession.js` — full JS logic
- `public/assets/js/pages/meetings.js` — full JS logic including `renderSessionItem()`
- `public/assets/js/components/ag-tooltip.js` — component API

### Secondary (MEDIUM confidence)
- `.planning/phases/38-results-and-history/38-CONTEXT.md` — user decisions and constraints
- `.planning/STATE.md` — accumulated project decisions from phases 35-37

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all CSS files and JS files read directly from source
- Architecture: HIGH — patterns verified in pages.css and design-system.css
- Pitfalls: HIGH — ag-tooltip flex pitfall confirmed by reading Phase 35 fix in pages.css L.1026
- JS render output: HIGH — meetings.js `renderSessionItem()` read line-by-line

**Research date:** 2026-03-20
**Valid until:** 2026-04-20 (CSS design system stable; no third-party library churn)
