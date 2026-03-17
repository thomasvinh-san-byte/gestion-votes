---
phase: 06-layout-navigation
plan: "02"
subsystem: ui
tags: [html, accessibility, aria, notifications, mobile-nav, footer]

# Dependency graph
requires:
  - phase: 06-01
    provides: shell.js mobile bottom nav, logo injection, mobile-bnav CSS already authored
provides:
  - Notification panel limited to 6 items with "Voir tout" link
  - .notif-list max-height for scrollable overflow
  - app-footer element present in all 18 page HTML files with role="contentinfo"
  - role="banner" on app-header elements across all applicable pages
  - role="main" added where missing (help.htmx.html)
affects: [all page HTML files, shell.js notification rendering]

# Tech tracking
tech-stack:
  added: []
  patterns: [footer injected via static HTML (not JS) in .app-shell after </main>]

key-files:
  created: []
  modified:
    - public/assets/js/core/shell.js
    - public/assets/css/design-system.css
    - public/admin.htmx.html
    - public/analytics.htmx.html
    - public/archives.htmx.html
    - public/dashboard.htmx.html
    - public/docs.htmx.html
    - public/email-templates.htmx.html
    - public/help.htmx.html
    - public/hub.htmx.html
    - public/meetings.htmx.html
    - public/members.htmx.html
    - public/operator.htmx.html
    - public/postsession.htmx.html
    - public/public.htmx.html
    - public/report.htmx.html
    - public/trust.htmx.html
    - public/validate.htmx.html
    - public/vote.htmx.html
    - public/wizard.htmx.html

key-decisions:
  - "public.htmx.html and vote.htmx.html get app-footer with display:none (full-screen/voter layouts)"
  - "docs.htmx.html, help.htmx.html, operator.htmx.html lack app-header element so role=banner not added to those pages"
  - "public.htmx.html projection-header gets role=banner as the page-level header landmark"

patterns-established:
  - "Footer pattern: <footer class='app-footer' role='contentinfo'> placed inside .app-shell after </main>"
  - "Footer hidden on mobile via @media (max-width: 767.98px) .app-footer { display: none } in design-system.css"

requirements-completed: [NAV-03, NAV-04, NAV-05]

# Metrics
duration: 15min
completed: 2026-03-12
---

# Phase 06 Plan 02: Header/Footer Alignment and Notification Panel Summary

**Notification panel capped at 6 items with "Voir tout" link, app-footer added to all 18 pages with ARIA roles**

## Performance

- **Duration:** 15 min
- **Started:** 2026-03-12T12:23:37Z
- **Completed:** 2026-03-12T12:35:02Z
- **Tasks:** 3 (Task 1 pre-completed by 06-01; Tasks 2 and 3 executed)
- **Files modified:** 20

## Accomplishments

- Notification panel aligned to CONTEXT.md: items limited to 6 (was 10), "Voir tout" footer link added, .notif-list with max-height:320px for overflow
- Footer element added to all 18 page HTML files with correct content (logo, version, Aide, Accessibilite links) and role="contentinfo"
- ARIA landmark roles audited: role="banner" added to all applicable app-header elements, role="main" added to help.htmx.html which was missing it

## Task Commits

1. **Task 1: Update mobile bottom nav to 5 tabs, header logo, mobile-bnav CSS** - Pre-completed by plan 06-01 (verified: 5 tabs, logo injection, full CSS block present)
2. **Task 2: Align notification panel** - `90d4602` (feat)
3. **Task 3: Add footer element to all page HTML files** - `2af7390` (feat)

## Files Created/Modified

- `public/assets/js/core/shell.js` - slice(0, 6) for notifications, "Voir tout" link in panel HTML
- `public/assets/css/design-system.css` - .notif-list max-height:320px added
- `public/admin.htmx.html` - footer added, role="banner" on header
- `public/analytics.htmx.html` - footer added, role="banner" on header
- `public/archives.htmx.html` - footer added, role="banner" on header
- `public/dashboard.htmx.html` - footer added, role="banner" on header
- `public/docs.htmx.html` - footer added (no app-header present)
- `public/email-templates.htmx.html` - footer added, role="banner" on header
- `public/help.htmx.html` - footer added, role="main" added to main element
- `public/hub.htmx.html` - footer added, role="banner" on header
- `public/meetings.htmx.html` - footer added, role="banner" on header
- `public/members.htmx.html` - footer added, role="banner" on header
- `public/operator.htmx.html` - footer added (uses section.meeting-bar, no app-header)
- `public/postsession.htmx.html` - footer added, role="banner" on postsession-header
- `public/public.htmx.html` - hidden footer added, role="banner" on projection-header
- `public/report.htmx.html` - footer added, role="banner" on header
- `public/trust.htmx.html` - footer added, role="banner" on header
- `public/validate.htmx.html` - footer added, role="banner" on header
- `public/vote.htmx.html` - hidden footer added (vote already had role="contentinfo" on vote-footer)
- `public/wizard.htmx.html` - footer added, role="banner" on header

## Decisions Made

- public.htmx.html and vote.htmx.html receive app-footer with `style="display:none"` since these are full-screen/voter-mode layouts per wireframe
- docs.htmx.html and help.htmx.html lack a shell-level `<header>` element so role="banner" is not applicable to those pages
- operator.htmx.html uses `<section class="meeting-bar">` as its top element — role="banner" not appropriate on a section
- public.htmx.html's projection-header receives role="banner" as it is the page-level header landmark

## Deviations from Plan

None - plan executed exactly as written. Task 1 was already complete from 06-01; this was verified rather than re-executed.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- All pages now have consistent footer structure and ARIA landmarks
- Notification panel matches CONTEXT.md spec
- Ready for remaining layout-navigation work or next phase
- No blockers

---
*Phase: 06-layout-navigation*
*Completed: 2026-03-12*
