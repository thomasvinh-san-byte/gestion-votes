---
phase: 35-entry-points
plan: "01"
subsystem: dashboard
tags: [visual-redesign, typography, micro-interactions, components]
dependency_graph:
  requires: []
  provides: [kpi-card-redesign, session-card-badge, hover-cta, tooltip-guidance]
  affects: [public/dashboard.htmx.html, public/assets/css/pages.css, public/assets/css/design-system.css, public/assets/js/pages/dashboard.js]
tech_stack:
  added: []
  patterns: [ag-tooltip wrapper, ag-badge status, hover-reveal CTA, JetBrains Mono KPI numbers, positional modifier classes]
key_files:
  created: []
  modified:
    - public/dashboard.htmx.html
    - public/assets/css/pages.css
    - public/assets/css/design-system.css
    - public/assets/js/pages/dashboard.js
decisions:
  - "Used .kpi-card--N positional modifier classes instead of :nth-child because ag-tooltip wrappers are the grid children — nth-child would have targeted ag-tooltip elements, not kpi-cards"
  - "Added display:contents to kpi-grid ag-tooltip so wrapped <a> cards become proper grid cells"
  - "Kept .session-card-status-dot CSS rule as a comment (not deleted) to document the replacement for future reference"
metrics:
  duration: "~15 minutes"
  tasks_completed: 2
  tasks_total: 2
  files_modified: 4
  completed_date: "2026-03-19"
---

# Phase 35 Plan 01: Dashboard Visual Redesign Summary

**One-liner:** KPI cards with JetBrains Mono numbers and per-card colored icon backgrounds, ag-badge session statuses with hover-reveal CTAs, and ag-tooltip guidance on all 7 interactive entry points.

## What Was Built

### Task 1: KPI Cards — Mono Numbers, Colored Icons, Tooltips

Transformed the 4 KPI cards from centered generic boxes into left-aligned data display cards:

- **ag-tooltip wrappers**: All 4 KPI cards wrapped in `<ag-tooltip position="bottom">` with descriptive text explaining each metric's meaning
- **kpi-icon divs**: New `<div class="kpi-icon">` wrapper inside each card; SVG `stroke` changed from hardcoded `var(--color-X)` to `currentColor` so CSS can control icon color
- **Positional modifier classes**: `.kpi-card--1` through `.kpi-card--4` added to distinguish icon colors (primary/danger/warning/accent) without breaking nth-child when ag-tooltip is the grid child
- **JetBrains Mono numbers**: `font-family: var(--font-mono)` on `.kpi-value`, size upgraded to `var(--text-4xl)` with `font-variant-numeric: tabular-nums`
- **Left alignment**: `text-align: left`, `flex-direction: column`, `gap: var(--space-3)` — KPI value becomes the visual anchor
- **Uppercase labels**: `text-transform: uppercase`, `letter-spacing: var(--tracking-wider, 0.05em)` for label hierarchy
- **Panel header separator**: Dashboard panels upgraded to `24px` padding with `border-bottom` on `.flex-between` header row
- **Grid fix**: `ag-tooltip { display: contents }` so ag-tooltip host doesn't break the CSS grid layout

### Task 2: Session Cards with ag-badge + Hover CTA, Aside Shortcuts

Replaced the minimal status dot pattern with a full badge + structured meta layout:

- **ag-badge status**: `renderSessionCard()` in dashboard.js now renders `<ag-badge variant="..." pulse>` with French status labels (Brouillon/Planifiée/En cours/etc.) — status-dot removed entirely
- **Structured meta**: Date in `<span class="session-card-date">` (semibold), separated by middle-dot `\u00B7` spans with `.session-card-meta-sep` class — no more flat dash-joined string
- **Hover-reveal CTA**: `.session-card .session-card-cta` is `opacity: 0; transform: translateX(4px)` at rest; transitions to `opacity: 1; transform: translateX(0)` on hover — CTA slides in from right
- **Touch device fallback**: `@media (hover: none)` forces `opacity: 1; transform: none` so mobile users always see the CTA
- **Session card base upgrade**: `border-radius: var(--radius-lg)`, `box-shadow: var(--shadow-md)` on hover, `background: var(--color-surface-raised)` — visible depth change
- **Shortcut card bg-change**: `.shortcut-card:hover` now uses `background: var(--color-bg-subtle)` and `border-color: var(--color-border)` instead of `transform: translateY(-2px)` — no lift, just highlight
- **Aside shortcut tooltips**: All 3 shortcut cards wrapped in `<ag-tooltip position="left">` with descriptive action text

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] nth-child selectors incompatible with ag-tooltip grid wrappers**
- **Found during:** Task 1
- **Issue:** The plan specified `.kpi-card:nth-child(N) .kpi-icon` selectors. But ag-tooltip wraps each kpi-card, making ag-tooltip the nth-child of kpi-grid — not the kpi-card itself. CSS nth-child would have targeted the wrong element.
- **Fix:** Added positional modifier classes `.kpi-card--1` through `.kpi-card--4` to each card in HTML, updated CSS selectors to use those classes. Also added `display: contents` to `.kpi-grid ag-tooltip` so the grid layout is unaffected.
- **Files modified:** `public/dashboard.htmx.html`, `public/assets/css/pages.css`
- **Commit:** 6cd88f2

## Self-Check

### Files exist:

- [x] `public/dashboard.htmx.html` — modified (ag-tooltip wrappers, kpi-icon divs, shortcut tooltips)
- [x] `public/assets/css/pages.css` — modified (KPI redesign, shortcut bg-change hover)
- [x] `public/assets/css/design-system.css` — modified (session card redesign, hover-reveal CTA)
- [x] `public/assets/js/pages/dashboard.js` — modified (ag-badge renderer, structured meta)

### Commits:

- 6cd88f2 — feat(35-01): KPI cards redesign — mono numbers, colored icons, ag-tooltip wrappers
- 2ca378c — feat(35-01): session cards ag-badge + hover CTA, shortcut tooltips

## Self-Check: PASSED
