---
phase: 62-smtp-template-engine
plan: 02
subsystem: ui
tags: [email-templates, settings, javascript, html, preview, body_html]

# Dependency graph
requires:
  - phase: 62-01
    provides: EmailTemplatesController (body_html field), EmailController preview() API
provides:
  - Template editor saves and loads body_html field correctly
  - Server-side preview via POST /api/v1/email_templates_preview
  - Canonical variable tag buttons (member_name, meeting_title, vote_url, etc.)
  - Preview render container (#templatePreviewRender) in template editor card
affects: [63-email-workflows, any feature using template editor]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Debounced server-side preview (400ms) instead of client-side string substitution
    - Variable insertion via .variable-tag data-var buttons with click delegation

key-files:
  created: []
  modified:
    - public/assets/js/pages/settings.js
    - public/settings.htmx.html

key-decisions:
  - "Server-side preview preferred over client-side substitution — canonical variable values come from EmailTemplateService which has correct sample data"
  - "400ms debounce on preview API calls prevents excessive requests while typing"
  - "type field renamed to template_type in PUT payload for consistency with create() convention (update() ignores it, so harmless)"

patterns-established:
  - "Template fields must use body_html (not body) to match EmailTemplatesController contract"
  - "Variable tags use canonical names from EmailTemplateService::AVAILABLE_VARIABLES"

requirements-completed: [EMAIL-04]

# Metrics
duration: 2min
completed: 2026-04-01
---

# Phase 62 Plan 02: Template Editor Field Alignment Summary

**Fixed template editor field name mismatches (body->body_html) and replaced stale client-side preview with debounced server-side API call; added 11 canonical variable tag buttons and preview container to settings HTML**

## Performance

- **Duration:** 2 min
- **Started:** 2026-04-01T05:08:32Z
- **Completed:** 2026-04-01T05:10:45Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Fixed `tpl.body` -> `tpl.body_html` in `loadTemplate()` so loaded template content actually appears in the editor
- Fixed PUT save payload: `body` -> `body_html`, `type` -> `template_type` to match controller contract
- Replaced hardcoded client-side preview (using stale `{{nom}}`, `{{date}}`, `{{heure}}`) with server-side debounced call to `/api/v1/email_templates_preview`
- Added 11 canonical variable tag buttons (member_name, member_first_name, member_email, meeting_title, meeting_date, meeting_time, meeting_location, vote_url, app_url, tenant_name, current_date) to settings HTML
- Added `#templatePreviewRender` container for rendered preview output

## Task Commits

Each task was committed atomically:

1. **Task 1: Fix template editor field names and add server-side preview** - `d9a07509` (fix)
2. **Task 2: Add variable tag buttons and preview container to template editor HTML** - `71f26f36` (feat)

**Plan metadata:** (see final commit in docs step)

## Files Created/Modified
- `public/assets/js/pages/settings.js` - Fixed body_html field, server-side debounced preview
- `public/settings.htmx.html` - Added variable tag buttons and preview render container

## Decisions Made
- Server-side preview via `EmailController::preview()` preferred over client-side substitution — the backend uses `EmailTemplateService::preview()` which supplies canonical sample data with correct variable names
- 400ms debounce prevents API hammering while user types
- `type` -> `template_type` in PUT payload: update() ignores the field but consistency with create() is cleaner

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
- PHP test suite blocked by pre-existing environment issue: PHP 8.3.6 installed, composer requires >= 8.4.0. This is unrelated to the JS/HTML changes in this plan and was present before this execution.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Template editor fully wired: load reads body_html, save sends body_html, preview calls server API
- Variable tags use canonical names — matches EmailTemplateService::AVAILABLE_VARIABLES exactly
- Phase 63 (email workflows) can rely on correct template field names being persisted

---
*Phase: 62-smtp-template-engine*
*Completed: 2026-04-01*
