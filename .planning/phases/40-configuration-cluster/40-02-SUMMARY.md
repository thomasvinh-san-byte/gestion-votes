---
phase: 40-configuration-cluster
plan: "02"
subsystem: ui-pages
tags: [admin, help, faq, tooltips, kpi-strip, filter-tabs, design-system]
dependency_graph:
  requires: [40-01]
  provides: [admin-self-explanatory-ui, help-premium-navigation]
  affects: [public/admin.htmx.html, public/assets/css/admin.css, public/assets/js/pages/admin.js, public/help.htmx.html, public/assets/css/help.css]
tech_stack:
  added: []
  patterns: [ag-tooltip, kpi-grid, filter-tab-pills, left-border-accent, doc-links-card]
key_files:
  modified:
    - public/admin.htmx.html
    - public/assets/css/admin.css
    - public/assets/js/pages/admin.js
    - public/help.htmx.html
    - public/assets/css/help.css
decisions:
  - "Users KPI strip placed above admin tabs as dashboard summary — ag-tooltip inside kpi-card wrapping kpi-label only (not whole card, per anti-pattern from RESEARCH)"
  - "admin.js fetches admin_users.php separately for KPI strip (updateAdminUserKpis) rather than reusing loadUsers to avoid coupling with table state"
  - "help-header-subtitle was already present from previous phase — no change needed"
  - "help-support card uses flex layout with icon + body + btn — btn keeps flex-shrink:0 inline to avoid CSS specificity issues on mobile"
metrics:
  duration: "~10 min"
  completed_date: "2026-03-20"
  tasks_completed: 2
  tasks_total: 2
  files_modified: 5
---

# Phase 40 Plan 02: Admin + Help/FAQ Page Redesign Summary

Admin and Help/FAQ pages upgraded to match configuration-cluster quality bar — ag-tooltip on every KPI and tab, users KPI strip replaces compact card, filter-tab pills replace solid tab navigation.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Admin page — KPI tooltips, tab icons, users KPI strip, state node tooltips | `131723f` | admin.htmx.html, admin.css, admin.js |
| 2 | Help/FAQ page — filter-tab pills, section title accents, doc card, support CTA | `99d2989` | help.htmx.html, help.css |

## What Was Built

### Admin Page (Task 1)

**KPI tooltips:** All 4 dashboard KPI labels (AG à venir, En cours, Convocations, PV en attente) wrapped in `<ag-tooltip>` with French explanations of what each metric measures.

**Users KPI strip:** Compact one-line users card replaced with a 4-kpi `.kpi-grid` strip using design-system classes directly — Total utilisateurs, Admins, Opérateurs, Actifs (7j). Values populated by `updateAdminUserKpis()` in admin.js which fetches from `/api/v1/admin_users.php`.

**Tab icons:** All 5 admin tabs (Rôles de séance, Politiques, Permissions, Machine à états, Système) now have 16px inline SVG icons before the label. Active tab icon inherits primary color. Each tab label also wrapped in `ag-tooltip` explaining what the tab manages.

**State machine tooltips:** All 7 state nodes in the legend (Brouillon, Programmée, Verrouillée, En cours, Clôturée, Validée, Archivée) wrapped in `ag-tooltip` explaining available actions from each state.

### Help/FAQ Page (Task 2)

**Filter-tab pills:** `.help-tab` replaced solid primary background with pill pattern — border + border-radius-full, `color-primary-subtle` background + `color-primary` border on active. Tab bar bottom border removed.

**Section title left-border:** `.faq-section-title` bottom underline replaced with `border-left: 4px solid var(--color-primary)` accent. Font upgraded to `font-weight: 700`.

**FAQ answer accent:** `.faq-answer` gets `border-left: 2px solid var(--color-primary-subtle)` for visual continuity when expanded.

**Search upgrade:** `.search-input-wrap` inputs upgraded to 48px height, `border-radius-lg`, shadow-sm base, shadow-md on focus.

**Tour cards:** `.tour-icon` sized up to 44px, hover gets shadow-md + lift.

**Doc links card:** Existing `.doc-links` div replaced with `.doc-links-card` structure — card with `doc-links-card-header` (Documentation title + icon) and `doc-links-card-body`.

**Support card CTA:** Completely restructured — 48px headphones SVG icon, `.help-support-title`, `.help-support-desc`, prominent layout with shadow.

## Deviations from Plan

None — plan executed exactly as written. The `help-header-subtitle` was already present in the HTML from a previous phase, confirmed no duplicate added.

## Self-Check

All files confirmed modified and committed:
- `public/admin.htmx.html` — 24 ag-tooltip instances, kpi-grid present
- `public/assets/css/admin.css` — admin-tab-icon rules present
- `public/assets/js/pages/admin.js` — updateAdminUserKpis function present
- `public/help.htmx.html` — doc-links-card and help-support-icon present
- `public/assets/css/help.css` — border-radius-full (filter-tab pill) and border-left 4px present

## Self-Check: PASSED
