---
phase: 02-sidebar-navigation
plan: 01
subsystem: ui
tags: [css, sidebar, navigation, wcag, touch-targets]

# Dependency graph
requires:
  - phase: 01-typographie-espacement
    provides: design tokens (--text-base, --gap-sm) consumed by sidebar rules
provides:
  - Static 200px sidebar always visible, labels always shown, no hover/pin mechanism
  - WCAG 2.5.8 touch targets 44px on all nav items and nav groups
  - Mon compte nav item (all roles, /settings) and Voter nav item in main nav
  - shell.js stripped of all pin/expand logic
affects: [03-empty-states, 04-voter-jargon, all pages using app-sidebar layout]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Static sidebar: width hardcoded to 200px, no JS toggle, CSS-only layout"
    - "Always-visible labels: opacity:1, max-width:180px, text-overflow:ellipsis"
    - "Touch targets: height:44px for nav-item, min-height:44px for nav-group (min-height avoids clipping)"

key-files:
  created: []
  modified:
    - public/assets/css/design-system.css
    - public/partials/sidebar.html
    - public/assets/js/core/shell.js

key-decisions:
  - "Used min-height:44px (not height:44px) for nav-group to avoid clipping section labels"
  - "Mon compte has no data-requires-role — visible to all authenticated users including voter"
  - "Both admin Parametres (/settings, admin-only) and voter Mon compte (/settings, all roles) coexist — same destination, different audience label"
  - "Removed .nav-group::after divider entirely — was a rail-mode affordance, irrelevant at 200px"
  - "Removed sidebar-device-section — Apercu votant replaced by Voter in main nav with proper role filter"

patterns-established:
  - "No pin/localStorage sidebar state — sidebar is always 200px, CSS handles layout statically"
  - "app-main padding-left: calc(200px + 20px) — static, no JS override needed"

requirements-completed: [NAV-01, NAV-02, NAV-03]

# Metrics
duration: 25min
completed: 2026-04-21
---

# Phase 2 Plan 01: Sidebar Navigation Summary

**Static 200px sidebar with always-visible labels, 44px touch targets, Mon compte + Voter nav items, pin/unpin mechanism fully removed from CSS/HTML/JS**

## Performance

- **Duration:** 25 min
- **Started:** 2026-04-21T07:30:00Z
- **Completed:** 2026-04-21T07:55:02Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- Sidebar permanently open at 200px — no hover needed, labels always visible for 55+ users
- All nav items 44px height, nav groups min-height 44px (WCAG 2.5.8 compliant)
- Pin/unpin mechanism completely removed: CSS rules, HTML button, JS logic (PIN_KEY, togglePin, bindPinButton, SidebarPin export, localStorage restore)
- Voter and Mon compte nav items added to main nav; Mon compte visible to all roles pointing to /settings

## Task Commits

Each task was committed atomically:

1. **Task 1: CSS static 200px sidebar, remove hover/pin rules, touch targets 44px** - `3ef572ce` (feat)
2. **Task 2: Remove pin button, add Mon compte + Voter, strip pin logic from shell.js** - `5f98f867` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `public/assets/css/design-system.css` - --sidebar-width:200px, static sidebar, always-visible labels, 44px targets, pin block deleted
- `public/partials/sidebar.html` - Pin button removed, Voter + Mon compte added to main nav, device section removed
- `public/assets/js/core/shell.js` - PIN_KEY, togglePin, bindPinButton, SidebarPin export all removed; scroll fade, nav group toggle, active marking preserved

## Decisions Made
- Used `min-height: 44px` (not `height`) for `.nav-group` to avoid clipping content when label wraps
- `Mon compte` has no `data-requires-role` so voter role sees it; admin Parametres retains `data-requires-role="admin"` — both point to `/settings` intentionally
- Removed `.nav-group::after` divider rule entirely (was a rail-mode visual affordance, meaningless at 200px)
- Removed `sidebar-device-section` and moved Voter link into main nav with proper role filter

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Removed stale .sidebar-pin reference from mobile media query**
- **Found during:** Task 1 verification
- **Issue:** Mobile `@media` open state rule included `.app-sidebar.open .sidebar-pin` selector — the pin button no longer exists in HTML, leaving a dead selector
- **Fix:** Removed the `.sidebar-pin` line from the `.app-sidebar.open` multi-selector in the mobile block
- **Files modified:** public/assets/css/design-system.css
- **Verification:** grep for sidebar-pin in CSS shows only --z-sidebar-pin token (no selectors)
- **Committed in:** 3ef572ce (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (1 missing critical — dead selector cleanup)
**Impact on plan:** Minor cleanup, no scope creep. Mobile block still intact and functional.

## Issues Encountered
None — all acceptance criteria satisfied on first attempt.

## Next Phase Readiness
- Sidebar layout foundation complete; ready for Phase 2 Plan 2 (voter role filtering / empty states)
- Mobile hamburger behavior unaffected (media query left intact)
- Any page using `.app-sidebar` now gets 200px static layout automatically

---
*Phase: 02-sidebar-navigation*
*Completed: 2026-04-21*
