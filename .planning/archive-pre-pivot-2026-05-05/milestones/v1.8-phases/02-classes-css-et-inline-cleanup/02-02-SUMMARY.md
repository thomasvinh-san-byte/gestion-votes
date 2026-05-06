---
phase: 02-classes-css-et-inline-cleanup
plan: 02
subsystem: ui
tags: [css, html, inline-styles, hidden-attribute, design-system, csp]

# Dependency graph
requires:
  - phase: 01-palette-et-tokens
    provides: CSS custom properties (--color-border, --color-surface, --color-text-muted, etc.)
provides:
  - Zero static inline styles across 10 HTML files
  - New CSS utility classes in design-system.css (template-preview, donut-legend-dot--, popover-help-text, field-hint-sm, cursor-pointer)
  - JS files updated to use hidden property instead of style.display for toggling
affects: [03-coherence-cross-pages, 05-validation-gate]

# Tech tracking
tech-stack:
  added: []
  patterns: [hidden-attribute-for-initial-hide, css-class-over-inline-style]

key-files:
  created: []
  modified:
    - public/assets/css/design-system.css
    - public/settings.htmx.html
    - public/analytics.htmx.html
    - public/hub.htmx.html
    - public/operator.htmx.html
    - public/docs.htmx.html
    - public/email-templates.htmx.html
    - public/members.htmx.html
    - public/report.htmx.html
    - public/validate.htmx.html
    - public/wizard.htmx.html
    - public/assets/js/pages/docs-viewer.js
    - public/assets/js/pages/email-templates-editor.js
    - public/assets/js/pages/members.js
    - public/assets/js/pages/report.js
    - public/assets/js/pages/wizard.js

key-decisions:
  - "Use HTML hidden attribute instead of style=display:none for initial element hiding"
  - "Update JS toggle code to use el.hidden property for consistency with hidden attribute"
  - "Remove redundant inline position:relative on op-kpi-item (already in operator.css)"

patterns-established:
  - "hidden attribute: Use hidden for initially-hidden elements, JS uses el.hidden = true/false"
  - "CSS-first styling: All visual styles in design-system.css, zero inline styles in HTML"

requirements-completed: [UI-05]

# Metrics
duration: 8min
completed: 2026-04-20
---

# Phase 02 Plan 02: Inline Style Cleanup Summary

**Replaced all 20+ static inline styles across 10 HTML files with CSS classes and hidden attributes, plus JS compatibility fixes**

## Performance

- **Duration:** 8 min
- **Started:** 2026-04-20T11:36:05Z
- **Completed:** 2026-04-20T11:44:05Z
- **Tasks:** 2
- **Files modified:** 16

## Accomplishments
- Eliminated all style="display:none" (18 occurrences) across 6 HTML files using the hidden attribute
- Replaced 8 complex inline styles in settings.htmx.html with CSS classes (template-preview, field-hint-sm, etc.)
- Added 10 new utility classes to design-system.css for reuse
- Updated 5 JS files to use el.hidden property for compatibility with the hidden attribute pattern

## Task Commits

Each task was committed atomically:

1. **Task 1: Replace display:none with hidden attribute** - `b1424c37` (feat)
2. **Task 2: Replace complex inline styles with CSS classes** - `24ca4910` (feat)

## Files Created/Modified
- `public/assets/css/design-system.css` - Added 10 utility classes for inline style replacements
- `public/settings.htmx.html` - 8 inline styles replaced with CSS classes
- `public/analytics.htmx.html` - 3 donut legend dot colors moved to CSS classes
- `public/hub.htmx.html` - Popover help text moved to CSS class
- `public/operator.htmx.html` - cursor:pointer and redundant position:relative removed
- `public/docs.htmx.html` - 3 display:none replaced with hidden
- `public/email-templates.htmx.html` - 1 display:none replaced with hidden
- `public/members.htmx.html` - 5 display:none replaced with hidden
- `public/report.htmx.html` - 4 display:none replaced with hidden
- `public/validate.htmx.html` - 2 display:none replaced with hidden
- `public/wizard.htmx.html` - 2 display:none replaced with hidden
- `public/assets/js/pages/docs-viewer.js` - Use el.hidden instead of style.display
- `public/assets/js/pages/email-templates-editor.js` - Use el.hidden instead of style.display
- `public/assets/js/pages/members.js` - Use el.hidden for tab panels, filters, import output
- `public/assets/js/pages/report.js` - Use el.hidden for pvFrame
- `public/assets/js/pages/wizard.js` - Use el.hidden for reso-add-panel

## Decisions Made
- Used HTML hidden attribute instead of CSS .hidden class for initial element hiding (native browser support, no CSS dependency)
- Updated corresponding JS files to use el.hidden property to maintain compatibility (style.display='' does not override hidden attribute)
- Removed redundant inline position:relative on op-kpi-item since operator.css already sets it

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Updated JS files for hidden attribute compatibility**
- **Found during:** Task 1 (display:none replacement)
- **Issue:** JS code using style.display='' to show elements would not override the hidden attribute
- **Fix:** Updated 5 JS files to use el.hidden = true/false instead of style.display
- **Files modified:** docs-viewer.js, email-templates-editor.js, members.js, report.js, wizard.js
- **Verification:** All show/hide paths now use consistent hidden property
- **Committed in:** b1424c37 (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Essential fix for correctness -- without JS updates, elements would stay hidden when toggled.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- All inline styles eliminated from target files
- CSS design-system is now single source of truth for visual styling
- Ready for Phase 02 Plan 03 (drawer/panel classes) or Phase 03

---
*Phase: 02-classes-css-et-inline-cleanup*
*Completed: 2026-04-20*
