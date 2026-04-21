---
phase: 01-typographie-et-espacement
plan: 02
subsystem: ui
tags: [css, design-tokens, html, header, layout]

# Dependency graph
requires:
  - phase: 01-typographie-et-espacement
    provides: "Typography tokens and design-system.css baseline (01-01)"
provides:
  - "--header-height token updated to 64px on desktop and mobile"
  - ".app-header uses var(--header-height) instead of hardcoded 56px"
  - "All 13 page headers cleaned: no decorative bar spans, no subtitle text"
  - "wizard wiz-step-subtitle preserved for dynamic JS updates"
  - "postsession meetingTitle preserved as hidden span for JS compatibility"
affects: [sidebar, layout, page-headers, all-htmx-pages]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "CSS token-driven header height: --header-height controls .app-header via var()"
    - "Hidden span pattern for JS-referenced elements no longer visually rendered"

key-files:
  created: []
  modified:
    - public/assets/css/design-system.css
    - public/admin.htmx.html
    - public/archives.htmx.html
    - public/audit.htmx.html
    - public/dashboard.htmx.html
    - public/email-templates.htmx.html
    - public/help.htmx.html
    - public/hub.htmx.html
    - public/meetings.htmx.html
    - public/members.htmx.html
    - public/postsession.htmx.html
    - public/settings.htmx.html
    - public/users.htmx.html
    - public/wizard.htmx.html

key-decisions:
  - "postsession meetingTitle: replaced <p class=page-sub> with <span id=meetingTitle hidden> — postsession.js reads textContent from this element, visibility not required"
  - "wizard page-sub kept: wiz-step-subtitle is dynamically updated by JS on each wizard step — only decorative bar removed"
  - "Mobile override also set to 64px: no intermediate size between desktop and mobile for header height"

patterns-established:
  - "Token-driven layout: all layout dimensions should use CSS custom properties, never hardcoded px values"
  - "Hidden span for JS hooks: when removing a visible element referenced by JS, replace with <span id=... hidden> rather than deleting"

requirements-completed: [TYPO-03]

# Metrics
duration: 8min
completed: 2026-04-21
---

# Phase 1 Plan 02: Header Height + Page Header Cleanup Summary

**64px header token applied via CSS var(), decorative bars and subtitle text removed from 13 page headers while preserving wizard dynamic subtitle and postsession JS hook**

## Performance

- **Duration:** ~8 min
- **Started:** 2026-04-21T06:57:19Z
- **Completed:** 2026-04-21T07:02:30Z
- **Tasks:** 2
- **Files modified:** 14

## Accomplishments
- `--header-height` token changed from 56px to 64px (desktop + mobile), `.app-header` now uses `var(--header-height)` — no more hardcoded pixel value
- Decorative `<span class="bar"></span>` removed from all 13 page-title headers
- `<p class="page-sub">` subtitle text removed from 11 pages (hub had none; wizard kept)
- `postsession.htmx.html` meetingTitle converted from visible subtitle to `<span hidden>` preserving JS read access

## Task Commits

1. **Task 1: Update header height token and fix .app-header hardcoded value** - `a9f3ff79` (feat)
2. **Task 2: Remove page-sub and decorative bar from HTML templates** - `590187cf` (feat)

## Files Created/Modified
- `public/assets/css/design-system.css` — --header-height 56px→64px (:root + mobile), height: var(--header-height) in .app-header
- `public/admin.htmx.html` — removed bar span + page-sub
- `public/archives.htmx.html` — removed bar span + page-sub
- `public/audit.htmx.html` — removed bar span + page-sub
- `public/dashboard.htmx.html` — removed bar span + page-sub
- `public/email-templates.htmx.html` — removed bar span + page-sub
- `public/help.htmx.html` — removed bar span + page-sub
- `public/hub.htmx.html` — removed bar span only (no page-sub present)
- `public/meetings.htmx.html` — removed bar span + page-sub
- `public/members.htmx.html` — removed bar span + page-sub
- `public/postsession.htmx.html` — removed bar span, replaced page-sub with `<span id="meetingTitle" hidden>`
- `public/settings.htmx.html` — removed bar span + page-sub
- `public/users.htmx.html` — removed bar span + page-sub
- `public/wizard.htmx.html` — removed bar span only (wiz-step-subtitle kept)

## Decisions Made
- postsession `meetingTitle` id is referenced by `postsession.js` (lines 639, 643) via `document.getElementById('meetingTitle')` to update `.textContent`. The element doesn't need to be visible — replaced with `<span hidden>` instead of deleting.
- wizard `wiz-step-subtitle` is dynamically updated per step by JS — removing it would break the wizard step indicator. Only the decorative bar was removed.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Header is now a clean 64px bar with breadcrumb + title only on all 13 pages
- CSS token architecture is sound: any future header height change requires editing one line in design-system.css
- Ready for Phase 1 Plan 03 (spacing and layout tokens)

---
*Phase: 01-typographie-et-espacement*
*Completed: 2026-04-21*
