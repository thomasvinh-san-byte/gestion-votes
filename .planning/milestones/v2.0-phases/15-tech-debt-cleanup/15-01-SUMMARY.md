---
phase: 15-tech-debt-cleanup
plan: "01"
subsystem: ui
tags: [svg, icons, lucide, shell, notifications]

# Dependency graph
requires: []
provides:
  - icon-help-circle symbol in SVG sprite
  - icon-pause symbol in SVG sprite
  - icon-smartphone symbol in SVG sprite
  - icon-plus-circle symbol in SVG sprite
  - clean notification panel link (no dead query param)
affects: [all pages using SVG icon sprite, shell.js notification panel]

# Tech tracking
tech-stack:
  added: []
  patterns: [Tech Debt Cleanup comment block groups new icons added post-audit]

key-files:
  created: []
  modified:
    - public/assets/icons.svg
    - public/assets/js/core/shell.js

key-decisions:
  - "icon-pause uses rect elements (two vertical bars, stroke style) not polygon fill — consistent with existing Lucide stroke pattern"
  - "?tab=notifications removed from notification Voir tout link — admin.js never reads tab param, removal is simpler than adding dead URL-parsing code"

patterns-established:
  - "New icons added post-audit grouped under <!-- Tech Debt Cleanup --> comment for traceability"

requirements-completed: []

# Metrics
duration: 3min
completed: 2026-03-16
---

# Phase 15 Plan 01: SVG Icon Sprite + Notification Link Fix Summary

**Added 4 missing Lucide icons (help-circle, pause, smartphone, plus-circle) to the SVG sprite and removed dead `?tab=notifications` query parameter from shell.js notification panel link**

## Performance

- **Duration:** 3 min
- **Started:** 2026-03-16T10:27:02Z
- **Completed:** 2026-03-16T10:30:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- 4 new Lucide SVG `<symbol>` elements added to icons.svg, eliminating blank icon placeholders across 12+ usages on 10 pages
- Dead `?tab=notifications` query parameter removed from the notification panel "Voir tout" anchor in shell.js
- icons.svg remains valid XML (verified with python3 xml.etree.ElementTree)

## Task Commits

Each task was committed atomically:

1. **Task 1: Add 4 missing Lucide icons to SVG sprite** - `d574d86` (feat)
2. **Task 2: Fix notification panel query parameter** - `3fd1ccf` (fix)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `public/assets/icons.svg` - Added 4 new symbol elements under `<!-- Tech Debt Cleanup -->` comment block
- `public/assets/js/core/shell.js` - Changed `href="/admin.htmx.html?tab=notifications"` to `href="/admin.htmx.html"`

## Decisions Made
- icon-pause uses two `<rect>` elements (stroke style) consistent with existing Lucide stroke-only pattern — no filled polygon
- Removed `?tab=notifications` rather than implementing tab-switching in admin.js — simpler, correct, no dead code

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- All 4 icon symbols now available for `<use href="icons.svg#icon-*">` references across all pages
- Notification panel link navigates cleanly to admin page without dead query parameter
- Phase 15 Plan 02 (if any) can proceed immediately

## Self-Check: PASSED

- FOUND: public/assets/icons.svg
- FOUND: public/assets/js/core/shell.js
- FOUND: commit d574d86 (feat(15-01): add 4 missing Lucide icons to SVG sprite)
- FOUND: commit 3fd1ccf (fix(15-01): remove dead ?tab=notifications query param from notification panel link)

---
*Phase: 15-tech-debt-cleanup*
*Completed: 2026-03-16*
