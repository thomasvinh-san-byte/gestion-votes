---
phase: 48-settings-admin-rebuild
plan: 01
subsystem: ui
tags: [html, css, design-system, settings, admin, kpi-cards]

# Dependency graph
requires:
  - phase: 47-hub-rebuild
    provides: hub.htmx.html rebuilt pattern (hero card, 1200px centered layout)
  - phase: 43-dashboard-rebuild
    provides: kpi-card/kpi-card--N CSS pattern, app-shell layout reference
provides:
  - settings.htmx.html — complete rewrite with 200px sidebar tabs + horizontal field groups
  - settings.css — clean design-token CSS for settings page (sidebar, field-group-h, level cards)
  - admin.htmx.html — complete rewrite with KPI row + user management section
  - admin.css — clean design-token CSS for admin page (kpi grid, user rows, pagination)
affects:
  - 48-02 (JS wiring verification — both pages rebuilt here, JS needs updating in Plan 02)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - settings-layout grid (200px sidebar + 1fr panels) with sticky sidenav
    - field-group-h horizontal label-left/input-right layout
    - admin-kpis 4-column KPI grid reusing kpi-card/kpi-card--N from pages.css
    - admin-users-section card with section-header, create form grid, user rows, pagination bar

key-files:
  created: []
  modified:
    - public/settings.htmx.html
    - public/assets/css/settings.css
    - public/admin.htmx.html
    - public/assets/css/admin.css

key-decisions:
  - "settings.htmx.html: hidden attr on inactive tab panels (2-4), not CSS display:none"
  - "settings CNIL level cards use :has(input:checked) + .selected class for JS toggle compat"
  - "admin.htmx.html: removed onboarding banner, tabs, policies, system/demo sections — Plan 02 updates admin.js"
  - "admin KPI IDs renamed: adminKpiUsers->adminKpiMembers, adminKpiAdmins->adminKpiSessions, adminKpiOperators->adminKpiVotes (Plan 02 updates admin.js)"
  - "parse-time JS errors expected until Plan 02 (mrMeeting, btnAssignRole, etc. removed from HTML)"

patterns-established:
  - "settings-layout: CSS grid 200px+1fr, sidebar sticky top:80px, panels flex column"
  - "field-group-h: grid 220px+1fr, border-bottom separator, last-child no border"
  - "admin-content: max-width 1200px centered, flex column gap"

requirements-completed: [REB-06]

# Metrics
duration: 15min
completed: 2026-03-22
---

# Phase 48 Plan 01: Settings + Admin Rebuild Summary

**Ground-up HTML+CSS rewrite of settings.htmx.html (200px sidebar tabs, horizontal fields) and admin.htmx.html (4-card KPI row, full-width user management table) — both files reduced 57-84% in size**

## Performance

- **Duration:** 15 min
- **Started:** 2026-03-22T17:21:00Z
- **Completed:** 2026-03-22T17:36:27Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments
- Settings page rebuilt with vertical sidebar nav (4 tabs), horizontal field-group-h layout for all settings, CNIL level cards, text size radio cards, toggle switch — all JS DOM IDs preserved
- Admin page rebuilt with 4 KPI cards (Members/Sessions/Votes/Active), user management section (search, filter, create form, list container, pagination) with all parse-time JS IDs present
- Both CSS files rewritten using only design tokens — dark mode works without any overrides

## Task Commits

Each task was committed atomically:

1. **Task 1: Rewrite settings.htmx.html and settings.css** - `b3c82b9` (feat)
2. **Task 2: Rewrite admin.htmx.html and admin.css** - `42b5f2d` (feat)

## Files Created/Modified
- `public/settings.htmx.html` — 380 lines (was 888, -57%) — 4-tab sidebar layout, all settings panels
- `public/assets/css/settings.css` — 381 lines (was 631, -40%) — sidebar nav, field-group-h, CNIL cards, responsive
- `public/admin.htmx.html` — 191 lines (was 1218, -84%) — KPI row + user management section
- `public/assets/css/admin.css` — 343 lines (was 1175, -71%) — KPI grid, user rows, pagination bar

## Decisions Made
- Settings inactive tab panels use `hidden` attribute, not `display:none` — consistent with JS which sets `panel.hidden`
- CNIL level cards: used `<label>` wrapping `<input type="radio">` so CSS `:has(input:checked)` works natively, plus `.selected` class for JS backwards compat
- Admin KPI IDs renamed to semantic names (Members/Sessions/Votes) — Plan 02 updates admin.js accordingly
- Removed all non-user-management sections from admin (onboarding, tabs, policies, system/demo) — parse-time JS errors expected until Plan 02 rewrites admin.js

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
- Admin.js has parse-time `getElementById().addEventListener()` calls for removed elements (mrMeeting, btnAssignRole, voteList, btnResetDemo). These will throw runtime errors until Plan 02 updates admin.js. This is expected and documented in the plan ("JS errors expected until Plan 02").

## Next Phase Readiness
- Both pages have complete, clean HTML + CSS structure
- All DOM IDs required by settings.js exist and are preserved
- All DOM IDs required by admin.js user management section exist at parse time
- Plan 02: Verify settings.js wiring works end-to-end; update admin.js (new KPI IDs + remove dead sections)

---
*Phase: 48-settings-admin-rebuild*
*Completed: 2026-03-22*
