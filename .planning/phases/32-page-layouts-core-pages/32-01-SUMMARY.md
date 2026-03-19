---
phase: 32-page-layouts-core-pages
plan: 01
subsystem: ui
tags: [css, layout, dashboard, table, design-system, responsive]

# Dependency graph
requires:
  - phase: 31-component-refresh
    provides: design tokens (--space-card, --color-surface-raised, --radius-lg, --color-border, etc.)
  - phase: 30-token-foundation
    provides: CSS custom property foundation
provides:
  - Dashboard layout: .dashboard-content (1200px), .kpi-grid (4-col raised), .dashboard-body (1fr 280px), .dashboard-aside (sticky 80px)
  - Shared table-page structure: .table-page (1400px), .table-toolbar, .table-card, .table-pagination in design-system.css
  - All 4 data table pages use shared structural CSS
affects:
  - 32-02 (secondary page layouts — should use same table-page pattern)
  - 33-page-layouts-secondary (same shared structure)
  - 34-quality-assurance-final-audit (verifies layout consistency)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Three-depth background model: --color-bg (app) / --color-surface (cards) / --color-surface-raised (headers, KPIs)"
    - "table-page shared layout pattern: design-system.css provides base, page CSS adds page-specific overrides"
    - "Dashboard two-region layout: kpi-grid above, dashboard-body (main + aside) below"

key-files:
  created: []
  modified:
    - public/assets/css/pages.css
    - public/assets/css/design-system.css
    - public/assets/css/audit.css
    - public/dashboard.htmx.html
    - public/audit.htmx.html
    - public/archives.htmx.html
    - public/members.htmx.html
    - public/users.htmx.html

key-decisions:
  - "Dashboard aside uses sticky top: 80px (matches app-header height) — keeps quick-actions visible on scroll"
  - "table-page max-width 1400px (wider than dashboard 1200px) to allow table columns to breathe"
  - "Members page wraps only members-layout in table-page (not full main) to preserve full-bleed stats-bar and management panels"
  - "audit.css .audit-table th now inherits sticky/background from shared .table-card .table thead th — no duplication"
  - "Legacy .grid-2 and .grid-3 kept in pages.css for other potential uses but not used in dashboard HTML"

patterns-established:
  - "table-page wrapper pattern: wrap app-main content in div.table-page for consistent max-width centering on data pages"
  - "table-toolbar: flex row with justify-content space-between for search/filter left, controls right"
  - "table-card: surface background, border, overflow hidden; inner table-wrapper for horizontal scroll"
  - "table-pagination: flex row space-between for count left, page controls right"

requirements-completed: [LAY-01, LAY-04]

# Metrics
duration: 20min
completed: 2026-03-19
---

# Phase 32 Plan 01: Page Layouts — Core Pages Summary

**Dashboard rebuilt with 1200px centered layout, 4-column raised KPI grid, and 280px sticky aside; four data table pages unified under shared .table-page/.table-toolbar/.table-card/.table-pagination structure at 1400px max-width**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-03-19T07:30:00Z
- **Completed:** 2026-03-19T07:50:00Z
- **Tasks:** 2
- **Files modified:** 8

## Accomplishments
- Dashboard now has proper three-depth background model with `--color-surface-raised` KPI cards, `--color-surface` session panel, on `--color-bg` main canvas
- Dashboard content centered at 1200px with 4-column KPI row + 1fr/280px body grid (session list + sticky aside)
- Responsive breakpoint at 1024px: 2-col KPIs, stacked body, static aside
- Shared `.table-page`, `.table-toolbar`, `.table-card`, `.table-pagination` added to design-system.css — eliminates duplicate structural CSS across 4 pages
- Audit, archives, users pages converted to shared table-page structure with sticky 40px raised headers and 48px row height
- Members page gains max-width centering via table-page wrapper without breaking full-bleed stats bar

## Task Commits

Each task was committed atomically:

1. **Task 1: Dashboard layout rebuild (LAY-01)** - `c4b31ae` (feat)
2. **Task 2: Shared table-page structure + data tables layout (LAY-04)** - `dc8043e` (feat)

## Files Created/Modified
- `public/assets/css/pages.css` — Dashboard layout section replaced: .dashboard-content, .kpi-grid, .dashboard-body, .dashboard-aside; responsive @media 1024px
- `public/assets/css/design-system.css` — Added .table-page, .table-toolbar, .table-card, .table-pagination shared styles after .app-main block
- `public/assets/css/audit.css` — Removed duplicated sticky header background/position from .audit-table th
- `public/dashboard.htmx.html` — Added .dashboard-content wrapper, .kpi-grid (no more .dashboard-grid), .dashboard-body/.dashboard-main/.dashboard-aside structure
- `public/audit.htmx.html` — Wrapped in .table-page, toolbar/card/pagination converted to shared classes; table gets class="table" for shared thead th targeting
- `public/archives.htmx.html` — Wrapped in .table-page with shared toolbar/card/pagination structure
- `public/users.htmx.html` — Wrapped in .table-page; filter bar becomes .table-toolbar; users-list wrapped in .table-card
- `public/members.htmx.html` — .members-layout wrapped in div.table-page for max-width centering

## Decisions Made
- Dashboard aside uses `position: sticky; top: 80px` — matches app-header height so aside stays visible on scroll
- Members page: only wrap `.members-layout` section in `.table-page` (not full `<main>`) to preserve full-bleed `.stats-bar` and `.members-management` sections
- Audit table gets `class="table audit-table"` to enable shared `.table-card .table thead th` selector without altering JavaScript
- Legacy `.grid-2`/`.grid-3` kept in pages.css for potential other uses, removed from dashboard HTML

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## Next Phase Readiness
- Dashboard layout complete — ready for 32-02 (meetings/sessions pages) and 32-03+ secondary pages
- Shared table-page pattern established — subsequent pages can adopt it with minimal CSS additions
- Three-depth background model operational across dashboard and data tables

---
*Phase: 32-page-layouts-core-pages*
*Completed: 2026-03-19*
