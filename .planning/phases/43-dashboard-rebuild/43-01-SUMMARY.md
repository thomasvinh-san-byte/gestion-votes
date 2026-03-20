---
phase: 43-dashboard-rebuild
plan: 01
subsystem: dashboard
tags: [html, css, dashboard, rebuild, kpi, layout]
dependency_graph:
  requires: []
  provides: [dashboard-html-rebuild, dashboard-css-rebuild]
  affects: [public/dashboard.htmx.html, public/assets/css/pages.css]
tech_stack:
  added: []
  patterns: [BEM, three-depth-surface-model, CSS-grid, sticky-aside]
key_files:
  created: []
  modified:
    - public/dashboard.htmx.html
    - public/assets/css/pages.css
decisions:
  - "Removed #taches ID from HTML — JS null-guards it, tasks panel not needed"
  - "Urgent banner starts hidden=true — JS sets hidden=false when live meeting found (Plan 02 wires the JS side)"
  - "KPI value elements have no color modifier classes — color comes from parent kpi-card--N variant via CSS"
  - "dashboard-kpis replaces kpi-grid namespace for cleaner scoping"
  - "dashboard-sessions replaces .card.dashboard-panel — owns its own surface, radius, border"
  - "Removed .grid-2, .grid-3 legacy utilities — were dead code in dashboard section"
metrics:
  duration: 8 min
  completed: 2026-03-20
  tasks_completed: 2
  files_modified: 2
---

# Phase 43 Plan 01: Dashboard Ground-Up Rebuild Summary

**One-liner:** Complete HTML+CSS rewrite of the dashboard — horizontal-first layout with BEM-structured urgent banner, 4-col KPI row, sessions panel, and sticky quick-actions aside.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Rewrite dashboard.htmx.html from scratch | 3bf0155 | public/dashboard.htmx.html |
| 2 | Rewrite dashboard CSS section in pages.css | f61d636 | public/assets/css/pages.css |

## What Was Built

### Task 1 — HTML Rewrite

`public/dashboard.htmx.html` is a ground-up rewrite. Key structural changes:

- **Urgent banner** (`#actionUrgente`): starts with `hidden` attribute, uses BEM classes `dashboard-urgent__icon/body/eyebrow/title/sub/arrow`
- **KPI row** (`dashboard-kpis`): 4 `ag-tooltip`-wrapped `kpi-card` links filling full width
- **Sessions panel** (`dashboard-sessions > #prochaines`): `<section>` with panel header + 3 skeleton placeholders
- **Aside** (`dashboard-aside`): 3 `ag-tooltip`-wrapped shortcut cards, no `.card` wrapper
- All 9 JS-anchored IDs preserved: `kpiSeances`, `kpiEnCours`, `kpiConvoc`, `kpiPV`, `urgentTitle`, `urgentSub`, `actionUrgente`, `prochaines`, `main-content`
- `#taches` ID removed — JS null-guards it in dashboard.js line 159

### Task 2 — CSS Rewrite

`public/assets/css/pages.css` lines 934-1345 replaced with 270-line focused ruleset:

- `.dashboard-urgent` + BEM modifiers — danger-bordered link with hover lift
- `.dashboard-kpis` — CSS grid 4-col, `ag-tooltip { display: contents }`
- `.dashboard-body` — 2-col grid `1fr 280px`, `align-items: start`
- `.kpi-card` — `--color-surface-raised` background, `--radius-xl`, hover translateY(-3px)
- `.dashboard-sessions` — own surface/border/radius, `.dashboard-panel-header` with bottom separator
- `.dashboard-aside` — `position: sticky; top: 80px`, `--radius-xl`, `--color-border-subtle`
- `.shortcut-card` — hover shows bg+border, no shadow/transform
- Responsive: 1024px body goes 1-col + aside static; 768px KPIs go 2-col

## Deviations from Plan

None — plan executed exactly as written.

## Verification

```
grep -c 'id="kpiSeances"\|...' public/dashboard.htmx.html  →  9 ✓
grep -c 'dashboard-kpis\|...' public/assets/css/pages.css  →  34 ✓
grep -c 'session-row\|task-row\|urgent-card-body\|kpi-grid' public/assets/css/pages.css  →  0 ✓
```

## Self-Check: PASSED

- [x] public/dashboard.htmx.html exists and contains all 9 IDs
- [x] public/assets/css/pages.css contains new dashboard classes (34 matches)
- [x] Legacy CSS classes (.session-row, .task-row, .kpi-grid, etc.) removed (0 matches)
- [x] Commits 3bf0155 and f61d636 exist
