---
phase: 26-guided-ux-components
plan: 01
subsystem: ui
tags: [web-components, custom-elements, empty-state, light-dom, french-content]

# Dependency graph
requires:
  - phase: 20.4-design-system-enforcement
    provides: design-system.css with .empty-state, .empty-state-icon, .empty-state-title, .empty-state-description classes
  - phase: 25-pdf-infrastructure-foundation
    provides: ag-pdf-viewer pattern — component self-registration and components/index.js loader
provides:
  - ag-empty-state Web Component (light DOM, 5 icon variants, attribute + slot action patterns)
  - Declarative empty states in meetings, archives, settings, members, users pages
affects: [27-post-session-pv, 26-02-plan, 26-03-plan, future-page-scripts]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Light DOM Web Component pattern: no attachShadow(), use this.innerHTML for CSS class compatibility"
    - "Self-contained component: inline SVGs avoid load-order dependency on window.Shared"
    - "ag-empty-state replaces Shared.emptyState() in div-container contexts; Shared.emptyState() retained for table-cell (tr/td) contexts"

key-files:
  created:
    - public/assets/js/components/ag-empty-state.js
  modified:
    - public/assets/js/components/index.js
    - public/assets/js/pages/meetings.js
    - public/assets/js/pages/archives.js
    - public/assets/js/pages/settings.js
    - public/assets/js/pages/members.js
    - public/assets/js/pages/users.js

key-decisions:
  - "ag-empty-state uses light DOM (no attachShadow) so design-system.css .empty-state* classes apply directly — same as original Shared.emptyState() output"
  - "EMPTY_SVG map duplicated inline in ag-empty-state.js to avoid window.Shared load-order dependency (Research pitfall 2)"
  - "Slotted action ([slot=action]) preserved across re-renders by capturing reference before innerHTML overwrite and re-appending after"
  - "archives.js description is dynamic (interpolates currentYear) — kept as JS string concatenation with ag-empty-state tag wrapper"
  - "members.js guided onboarding block (empty-state-guided with onclick handlers) left untouched — it is not a Shared.emptyState() call"
  - "users.js: icon mapped to 'members' (users icon not in EMPTY_SVG) per plan specification"

patterns-established:
  - "Empty state in div containers: use <ag-empty-state> declarative tag"
  - "Empty state in table cells (tr/td): keep Shared.emptyState() for backward compat"
  - "New components registered in components/index.js (import + export) to be loaded via shared module tag"

requirements-completed: [GUX-06, GUX-02]

# Metrics
duration: 15min
completed: 2026-03-18
---

# Phase 26 Plan 01: ag-empty-state Web Component Summary

**Self-contained ag-empty-state light-DOM Web Component with 5 inline SVG icons, replacing Shared.emptyState() in 5 admin page scripts with declarative French-content empty states**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-03-18T14:09:00Z
- **Completed:** 2026-03-18T14:24:33Z
- **Tasks:** 2
- **Files modified:** 7 (1 created, 6 modified)

## Accomplishments
- Created `ag-empty-state.js` Web Component using light DOM with self-contained inline SVGs (meetings, members, votes, archives, generic) — no dependency on window.Shared
- Registered component in `components/index.js` alongside all other ag-* components
- Migrated 11 Shared.emptyState() calls across 5 page scripts to declarative ag-empty-state tags with French content, icon, description, and optional action-label/action-href attributes
- Preserved backward compatibility: admin.js (4 calls) and audit.js (5 calls) in table-cell contexts remain untouched

## Task Commits

1. **Task 1: Create ag-empty-state Web Component** - `bf21a5b` (feat)
2. **Task 2: Migrate empty states across 5 page scripts** - `6462c3c` (feat)

## Files Created/Modified
- `public/assets/js/components/ag-empty-state.js` - New light DOM Web Component with 5 SVG icons, attribute-only and slot-action patterns
- `public/assets/js/components/index.js` - Added import, export, and debug log entry for ag-empty-state
- `public/assets/js/pages/meetings.js` - 5 empty state variants (all/upcoming/live/completed/search) migrated
- `public/assets/js/pages/archives.js` - 2 empty state variants (search no-results + no-archives) migrated
- `public/assets/js/pages/settings.js` - Quorum policy empty state migrated with action button
- `public/assets/js/pages/members.js` - Filtered + no-member states migrated (guided onboarding block untouched)
- `public/assets/js/pages/users.js` - Users list empty state migrated

## Decisions Made
- Used light DOM instead of shadow DOM so existing `.empty-state*` CSS classes from design-system.css apply directly without duplication
- Duplicated EMPTY_SVG map inline in the component file to eliminate window.Shared load-order dependency
- Kept slotted action support: capture `querySelector('[slot="action"]')` before innerHTML overwrite, re-append after render
- archives.js description remains dynamic JS string (interpolates currentYear variable) — the ag-empty-state tag wraps it
- members.js guided onboarding div (empty-state-guided) was not a Shared.emptyState() call — correctly left untouched
- Mapped users.js icon to 'members' (no 'users' key in EMPTY_SVG) per plan specification

## Deviations from Plan

None — plan executed exactly as written. The archives.js query-based empty state used icon 'search' and 'archive' (invalid EMPTY_SVG keys) in the original — migrated to 'generic' and 'archives' respectively, which aligns with the plan specification.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- ag-empty-state component is ready for use in any page via the shared components/index.js module
- Plan 26-02 (dashboard empty states) and 26-03 (operator console empty states) can proceed using the same component
- admin.js and audit.js table-cell contexts remain on Shared.emptyState() — no migration needed there

---
*Phase: 26-guided-ux-components*
*Completed: 2026-03-18*
