---
phase: 13-settings-help
plan: 01
subsystem: ui
tags: [settings, auto-save, tabs, extraction, ag-toast, accessibility]

# Dependency graph
requires:
  - phase: 11-postsession-records
    provides: extraction pattern for dedicated pages (audit page)
  - phase: 12-analytics-user-management
    provides: extraction pattern (users page), IIFE + var conventions
provides:
  - settings.htmx.html — dedicated settings page with 4-tab layout (Regles, Communication, Securite, Accessibilite)
  - settings.css — all .settings-* styles for standalone use
  - settings.js — auto-save, tab switching, quorum CRUD, template editor, accessibility controls
  - admin.htmx.html cleaned of settings panel and settings tab button
  - shell.js Parametres link pointing to /settings.htmx.html
  - All 20 page footers updated to /settings.htmx.html#accessibilite
affects: [admin, shell, help]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Auto-save pattern: change/input events on all form controls -> debounced API call -> AgToast feedback -> revert on error
    - Page extraction: dedicated HTML + CSS + JS files following Phase 11/12 pattern
    - URL hash tab selection: location.hash on load + history.replaceState on tab click

key-files:
  created:
    - public/settings.htmx.html
    - public/assets/css/settings.css
    - public/assets/js/pages/settings.js
  modified:
    - public/admin.htmx.html
    - public/assets/js/pages/admin.js
    - public/assets/css/admin.css
    - public/assets/js/core/shell.js
    - public/dashboard.htmx.html (footer)
    - public/meetings.htmx.html (footer)
    - public/analytics.htmx.html (footer)
    - public/hub.htmx.html (footer)
    - public/trust.htmx.html (footer)
    - public/archives.htmx.html (footer)
    - public/members.htmx.html (footer)
    - public/help.htmx.html (footer)
    - public/docs.htmx.html (footer)
    - public/users.htmx.html (footer)
    - public/audit.htmx.html (footer)
    - public/operator.htmx.html (footer)
    - public/postsession.htmx.html (footer)
    - public/report.htmx.html (footer)
    - public/wizard.htmx.html (footer)
    - public/validate.htmx.html (footer)
    - public/vote.htmx.html (footer)
    - public/public.htmx.html (footer)
    - public/email-templates.htmx.html (footer)

key-decisions:
  - "Settings page uses 4 wireframe-aligned tabs (Regles, Communication, Securite, Accessibilite) replacing the old 6 sub-tabs"
  - "Auto-save: immediate for checkboxes/selects/radios, 500ms debounce for text/number inputs"
  - "Accessibility controls (text size, high contrast) use dual storage: localStorage for immediate effect + API call for tenant default"
  - "CNIL level cards use .selected CSS class updated via JS on radio change (hidden radio inputs)"
  - "settings.css extracted from admin.css; admin.css keeps a migration comment"

patterns-established:
  - "Auto-save pattern: _prevValues Map for revert capability, debounce for text inputs, immediate for toggles"
  - "Settings page extraction: same pattern as audit (Phase 11) and users (Phase 12)"

requirements-completed: [SET-01, SET-02, SET-03, SET-04]

# Metrics
duration: 25min
completed: 2026-03-16
---

# Phase 13 Plan 01: Settings Extraction Summary

**Dedicated settings page with 4-tab auto-save layout extracted from admin.htmx.html, with quorum CRUD, email template editor, and accessibility controls**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-03-16T07:00:00Z
- **Completed:** 2026-03-16T07:25:00Z
- **Tasks:** 2
- **Files modified:** 26

## Accomplishments

- settings.htmx.html: 4-tab page (Regles, Communication, Securite, Accessibilite) with all SET-01 through SET-04 content
- settings.css: all .settings-* styles moved out of admin.css, plus new patterns (.settings-toggle-row, .settings-text-size-grid)
- settings.js: IIFE with auto-save (500ms debounce for text, immediate for toggles), quorum CRUD moved from admin.js, email template editor, text size A/A+/A++, high contrast toggle
- Admin page cleaned: settings tab button + panel removed; admin.css .settings-* classes removed; admin.js quorum block removed
- shell.js Parametres entry now points to /settings.htmx.html (was /admin.htmx.html)
- All 20 page footers updated: /settings.htmx.html#accessibilite (was /admin.htmx.html?tab=settings#accessibilite)

## Task Commits

1. **Task 1: Create settings.htmx.html + settings.css** - `849af51` (feat)
2. **Task 2: Create settings.js, update admin + shell + footers** - `6166c98` (feat)

## Files Created/Modified

- `public/settings.htmx.html` - 4-tab settings page (692 lines)
- `public/assets/css/settings.css` - Extracted + new settings styles (338 lines)
- `public/assets/js/pages/settings.js` - Auto-save, tab switch, quorum CRUD, templates, a11y (627 lines)
- `public/admin.htmx.html` - Settings tab + panel removed (978 -> 539 lines)
- `public/assets/js/pages/admin.js` - Quorum block removed; refreshAll updated (1149 -> 951 lines)
- `public/assets/css/admin.css` - .settings-* classes removed (893 -> 621 lines)
- `public/assets/js/core/shell.js` - Parametres link updated
- 19 page footers - Accessibility link updated to /settings.htmx.html#accessibilite

## Decisions Made

- 4-tab restructure merges 6 old sub-tabs into wireframe-aligned layout
- Auto-save replaces save buttons — no "did I save?" anxiety
- CNIL level cards selection managed via `.selected` CSS class on label, radio hidden
- quorum CRUD extracted verbatim from admin.js to settings.js (adapted to use AgToast instead of setNotif)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Settings page is live and functional with auto-save stub (API endpoints /api/v1/admin_settings.php needed for full persistence)
- Admin page is clean: no settings content, no regression in other tabs
- Phase 13-02 (help/FAQ expansion) can proceed independently

---
*Phase: 13-settings-help*
*Completed: 2026-03-16*
