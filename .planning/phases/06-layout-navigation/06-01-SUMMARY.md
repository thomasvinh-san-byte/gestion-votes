---
phase: 06-layout-navigation
plan: 01
subsystem: navigation
tags: [sidebar, nav, wireframe, css, badges]
dependency_graph:
  requires: []
  provides: [sidebar-5-section-layout, nav-badge-css]
  affects: [public/partials/sidebar.html, public/assets/css/design-system.css]
tech_stack:
  added: []
  patterns: [data-count attribute-driven badge visibility, CSS margin-left:auto badge positioning]
key_files:
  created: []
  modified:
    - public/partials/sidebar.html
    - public/assets/css/design-system.css
decisions:
  - "nav-badge uses margin-left:auto (flex flow) for visible state, not position:absolute, for correct layout in expanded sidebar"
  - "nav-badge visibility driven by [data-count] attribute selector — no JS needed to show/hide, only data-count value changes"
  - "Collapsed rail badge is 8px dot (no number visible) positioned absolute on icon — number count hidden at rail width"
metrics:
  duration: 72s
  completed: 2026-03-12
  tasks_completed: 2
  files_modified: 2
---

# Phase 6 Plan 01: Sidebar 5-Section Layout & Nav Badge Styles Summary

**One-liner:** Sidebar aligned to wireframe 5-section layout with attribute-driven nav-badge CSS pattern.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Restructure sidebar.html to wireframe 5-section layout | a90cb2f | public/partials/sidebar.html |
| 2 | Align sidebar CSS dimensions and add nav-badge styles | 4dffcae | public/assets/css/design-system.css |

## What Was Built

### Task 1: Sidebar HTML restructure

The sidebar.html partial was already partially updated (uncommitted changes from prior planning session). The changes applied:

- Renamed "En direct" group label to "Séance en direct" (wireframe exact match)
- Renamed "Après" group label to "Après la séance" (wireframe exact match)
- Removed standalone "Aide" group (`data-group="help"`)
- Moved Guide & FAQ nav-item into the Système group as its last item
- Added `<span class="nav-badge" style="display:none"></span>` to Séances item (meetings)
- Added `<span class="nav-badge" style="display:none"></span>` to Clôture & PV item (postsession)
- Updated GO-LIVE-STATUS comment: "6 groupes" → "5 groupes"

Result: Exactly 5 nav-group sections: Préparation, Séance en direct, Après la séance, Contrôle, Système.

### Task 2: CSS nav-badge styles

Replaced the old `position: absolute` nav-badge implementation with the plan's attribute-driven pattern:

- `.nav-badge`: default `display: none`, uses `margin-left: auto` and `flex-shrink: 0` for correct placement in flex nav-item
- `.nav-badge[data-count]:not([data-count="0"])`: shows as `inline-flex` when count attribute is non-zero
- `.app-sidebar:not(:hover):not(.pinned) .nav-badge[data-count]:not([data-count="0"])`: collapses to 8px dot in rail mode (no text)

Sidebar CSS custom properties confirmed correct (already from Phase 4):
- `--sidebar-rail: 58px`
- `--sidebar-expanded: 252px`

`.nav-item` already has `position: relative` (no change needed).

## Verification

1. `grep -c 'nav-group-label' public/partials/sidebar.html` → `5` PASS
2. Labels: Préparation, Séance en direct, Après la séance, Contrôle, Système PASS
3. `grep 'data-group="help"' public/partials/sidebar.html` → 0 matches PASS
4. `grep 'nav-badge' public/assets/css/design-system.css` → 3 matches PASS

## Deviations from Plan

None - plan executed exactly as written.

The sidebar HTML changes were found as uncommitted working tree modifications from prior planning work. They implemented exactly what Task 1 required — they were committed as part of this plan's Task 1.

## Self-Check: PASSED
