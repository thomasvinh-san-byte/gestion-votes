---
phase: 39-admin-data-tables
plan: "01"
subsystem: ui
tags: [html, css, javascript, design-system, ag-tooltip, ag-badge, members, users, admin]

# Dependency graph
requires:
  - phase: 38-results-and-history
    provides: design patterns for row lists, hover-reveal actions, semantic badges
provides:
  - Members page stats bar with surface-raised elevation, stat icons, mono values, ag-tooltip labels
  - Member avatars upgraded to 40px circles with success ring
  - Member action icons wrapped in ag-tooltip, filter chip count badges via JS
  - Users page role distribution panel (4 cards with ag-badge + live counts)
  - Users role filter replaced with pill tabs (filter-tab pattern)
  - Users column header row with ag-tooltip on each column
  - Users actions as hover-reveal icon buttons with ag-tooltip
  - User last-login dates in JetBrains Mono
affects:
  - future people-management pages using similar row patterns

# Tech tracking
tech-stack:
  added: []
  patterns:
    - ag-tooltip wrapping stat labels for inline inline explanation without visual noise
    - filter-chip .count badge updated by JS after every render
    - hover-reveal actions via opacity:0 default + opacity:1 on :hover
    - role distribution panel as 4-column grid with mono counts

key-files:
  created: []
  modified:
    - public/members.htmx.html
    - public/assets/css/members.css
    - public/assets/js/pages/members.js
    - public/users.htmx.html
    - public/assets/css/users.css
    - public/assets/js/pages/users.js

key-decisions:
  - "Members stats bar uses surface-raised (not bg-subtle) to create visible card elevation above page"
  - "Avatar circle ring uses box-shadow 0 0 0 2px (not border) to avoid box model issues on circle"
  - "Filter chip .count badge appended by JS to avoid duplicate HTML; updateFilterChipCounts() called on every renderMembers()"
  - "Users filterRole select replaced with filter-tab pills; _currentRoleFilter state var avoids DOM reads in loadUsers()"
  - "Users role distribution counts use all _allUsers (not filtered) so panel reflects total distribution"
  - "Old roles-explainer CSS removed; filter-tab pattern reused from members for visual consistency"

patterns-established:
  - "hover-reveal pattern: opacity:0 default, opacity:1 on .parent:hover .actions, plus @media (hover:none) always-visible"
  - "column header row: .users-list-header with .ulh-* span widths matching .user-row layout"
  - "role dist panel: 4-column grid with ag-badge + mono count + muted desc text"

requirements-completed: [DATA-03, DATA-04]

# Metrics
duration: 5min
completed: 2026-03-20
---

# Phase 39 Plan 01: Admin Data Tables Summary

**Members and Users pages redesigned to Linear-quality density — elevated stats bars with icons and tooltips, 40px circular avatars with role rings, hover-reveal icon actions, pill filter tabs, role distribution panel with live counts, and column header row.**

## Performance

- **Duration:** 5 min
- **Started:** 2026-03-20T06:18:12Z
- **Completed:** 2026-03-20T06:23:00Z
- **Tasks:** 2
- **Files modified:** 6

## Accomplishments
- Members stats bar: surface-raised background, 16px icons above each stat, mono values, ag-tooltip on all 6 labels
- Member avatars: 40px circles (was 36px squares), success ring via box-shadow on active members
- Member actions: 32x32 icon buttons wrapped in ag-tooltip, filter chips show live count badges
- Users role panel: replaced flat tag list with 4-column distribution cards showing live user counts populated by JS
- Users filter: select dropdown replaced with pill tab buttons (filter-tab pattern)
- Users column headers: sticky header row with ag-tooltip explaining each column
- Users actions: hover-reveal icon buttons with ag-tooltip (opacity 0 default, 1 on hover)
- Users last-login: JetBrains Mono font for scannable date column

## Task Commits

1. **Task 1: Members page** - `03d1eef` (feat)
2. **Task 2: Users page** - `eb2ab0f` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `public/members.htmx.html` - Stats bar icons + ag-tooltip labels
- `public/assets/css/members.css` - Stats bar elevation, circular avatar, stat-icon, filter chip count, upload zone
- `public/assets/js/pages/members.js` - Action icon ag-tooltip, updateFilterChipCounts()
- `public/users.htmx.html` - Role distribution panel, filter-tab pills, users-list-header with tooltips
- `public/assets/css/users.css` - Role dist panel, filter-tab, hover-reveal actions, column header, mono last-login
- `public/assets/js/pages/users.js` - updateRoleCounts(), _currentRoleFilter state, icon action buttons with ag-tooltip

## Decisions Made
- Members stats bar uses `surface-raised` (not `bg-subtle`) to create visible card elevation — consistent with Phase 38 elevated panels pattern
- Avatar circle ring uses `box-shadow 0 0 0 2px` instead of `border` to avoid border-box sizing side effects on circles
- Filter chip `.count` badge appended by JS (not duplicated in HTML) so counts stay in sync with data
- Users `_currentRoleFilter` state avoids reading the DOM in `loadUsers()` — matches pattern established in other JS pages
- Role distribution counts always use `_allUsers` (unfiltered) so panel reflects true distribution regardless of current search

## Deviations from Plan
None — plan executed exactly as written.

## Issues Encountered
None.

## Next Phase Readiness
- Members and Users pages visually upgraded, ready for visual checkpoint review
- Pattern established: hover-reveal + ag-tooltip + column headers can be applied to any future list page
- Role distribution panel pattern ready to reuse in any role-based admin page

---
*Phase: 39-admin-data-tables*
*Completed: 2026-03-20*
