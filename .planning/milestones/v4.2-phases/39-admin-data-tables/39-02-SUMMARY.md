---
phase: 39-admin-data-tables
plan: "02"
subsystem: admin-pages
tags: [audit, archives, ui, design-system, tooltips, inline-expansion, filter]
dependency_graph:
  requires: [39-01]
  provides: [audit-inline-detail, audit-tooltips, archives-status-filter, archives-kpi-cleanup]
  affects: [public/audit.htmx.html, public/assets/css/audit.css, public/assets/js/pages/audit.js, public/archives.htmx.html, public/assets/css/archives.css, public/assets/js/pages/archives.js]
tech_stack:
  added: []
  patterns: [ag-tooltip, inline-row-expansion, data-attribute-css, hover-reveal, filter-count-badges]
key_files:
  created: []
  modified:
    - public/audit.htmx.html
    - public/assets/css/audit.css
    - public/assets/js/pages/audit.js
    - public/archives.htmx.html
    - public/assets/css/archives.css
    - public/assets/js/pages/archives.js
decisions:
  - "Inline detail expansion uses insertAdjacentHTML after clicked row; second click removes it — no external state needed"
  - "severity mapped to high/medium/low for CSS data-severity (danger→high, warning→medium, info/success→low)"
  - "resetFilters() exposed as window.resetFilters for inline onclick on empty state button"
  - "archive-stats responsive rules removed from CSS alongside the deleted HTML block"
  - "Type filter tab selector narrowed to #archiveTypeFilter .filter-tab to avoid conflict with new status filter"
metrics:
  duration: "~8 min"
  completed: "2026-03-20"
  tasks_completed: 2
  tasks_total: 2
  files_modified: 6
---

# Phase 39 Plan 02: Audit and Archives Page Redesign Summary

Audit gets self-explanatory inline detail expansion (replacing modal), ag-tooltip column headers and KPI labels, category count badges, initials avatars in user column, severity-colored timeline with upgraded dots. Archives gets design-system KPI cleanup, status filter pills, hover-reveal card actions, status left-border accents, and list view polish.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Audit page — ag-tooltip headers, inline detail expansion, filter counts, severity upgrade, timeline polish | 6ed43e3 | audit.htmx.html, audit.css, audit.js |
| 2 | Archives page — KPI cleanup, duplicate stats removal, status filter pills, hover-reveal card actions, status left-border accent | 72efbfc | archives.htmx.html, archives.css, archives.js |

## Changes Summary

### Task 1 — Audit Page

**HTML (audit.htmx.html):**
- All 5 column headers (except checkbox) wrapped in ag-tooltip with French explanations
- All 4 KPI card labels wrapped in ag-tooltip with metric descriptions
- KPI icons changed from `icon icon-text` to `icon kpi-icon` (24px)
- Modal marked with `data-legacy="true"` and kept hidden as fallback

**CSS (audit.css):**
- Severity dot: 6px → 8px with `box-shadow: 0 0 0 2px var(--color-surface)` ring
- `.audit-detail-inline` + `.audit-detail-panel` new styles: slide-down animation, 2x2 grid, description + hash full-width
- `.audit-user-cell` + `.audit-user-avatar`: 24px circle with initials, primary-subtle background
- `.kpi-card .kpi-icon`: 24px sizing
- `[data-anomaly="true"]`: red value color for anomalies KPI
- Timeline dot: 10px → 12px with double border ring
- Timeline content: left-border 3px colored by `data-severity` (high=danger, medium=warning, low=success)
- Severity-specific hover preserves left-border color (override generic border-color transition)

**JS (audit.js):**
- `updateFilterCounts()`: counts events per category, injects `.count` badges in filter pills
- `buildUserCell()`: generates 24px initials avatar + name span
- `buildDetailPanelHtml()`: constructs inline detail row HTML
- Row click handler: toggle inline expansion instead of calling `openDetailModal()`
- Timeline render: adds `data-severity` attribute (maps danger→high, warning→medium, info/success→low)
- `populateKPIs()`: sets `data-anomaly="true"` on kpiAnomalies when count > 0
- `applyFilters()`: calls `updateFilterCounts(_allEvents)` after filtering

### Task 2 — Archives Page

**HTML (archives.htmx.html):**
- KPI cards: added icon SVG before value, wrapped labels in ag-tooltip
- Added `#archiveStatusFilter` filter pills row (Tous statuts / Validee / Archivee / PV envoye)
- Removed entire `.archive-stats` div (statTotal, statWithPV, statMotions, statBallots) — was duplicate of KPI grid

**CSS (archives.css):**
- Removed local `.kpi-grid`, `.kpi-card`, `.kpi-value`, `.kpi-label` definitions (design-system.css takes over)
- Removed responsive overrides for those same selectors from @media blocks
- Removed `.archive-stats`, `.archive-stat`, `.archive-stat-value`, `.archive-stat-label` rules
- Added `.kpi-card .kpi-icon` sizing (20px, muted color)
- `.archive-card-enhanced:hover`: removed `translateY(-1px)`, now uses `shadow-md + border-strong + surface-raised`
- Status left-border: `[data-status="validated"]`=primary, `[data-status="archived"]`=success, `[data-status="pv_sent"]`=warning
- `.archive-card-actions`: `opacity:0` by default, `opacity:1` on card hover; `@media(hover:none)` always visible
- Added `.archive-date` (JetBrains Mono) and `.archive-list-table` classes

**JS (archives.js):**
- `currentStatusFilter` state variable added
- `resetFilters()`: resets all filters, re-runs applyFilters(), exposed as `window.resetFilters`
- `render()`: empty state shows "Effacer les filtres" button when any filter is active
- `renderCardView()`: added `data-status` attribute on card element, action buttons wrapped in `.archive-card-actions`, dates wrapped in `.archive-date`
- `renderListView()`: table uses `.archive-list-table` class, headers wrapped in ag-tooltip, dates use `.archive-date`
- `resetKPIs()`: removed stat element references (statTotal etc. no longer exist)
- `loadArchives()`: removed stat DOM updates, added `updateTypeFilterCounts(allArchives)` call
- `updateTypeFilterCounts()`: counts archives per type, injects `.count` badges
- `applyFilters()`: added status filter check
- Type filter selector narrowed to `#archiveTypeFilter .filter-tab` (avoids conflict with status pills)
- Status filter handler on `#archiveStatusFilter .filter-tab` added

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing] Type filter selector scoping**
- **Found during:** Task 2
- **Issue:** Existing type filter handler used `.filter-tab[data-type]` which would match the new status pills' parent `.filter-tab` elements when adding status filter, causing double-bind
- **Fix:** Narrowed selector to `#archiveTypeFilter .filter-tab` for type filter; new status filter uses `#archiveStatusFilter .filter-tab`
- **Files modified:** archives.js

## Self-Check: PASSED

- All 6 source files exist on disk
- Commit 6ed43e3 (Task 1 — audit) verified in git log
- Commit 72efbfc (Task 2 — archives) verified in git log
