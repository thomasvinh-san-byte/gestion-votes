---
phase: 40-configuration-cluster
plan: "01"
subsystem: ui
tags: [settings, css-toggles, ag-tooltip, email-templates, two-pane-editor, design-system]

# Dependency graph
requires:
  - phase: 39-admin-data-tables
    provides: filter-tab pills, kpi-card pattern, design-system.css canonical patterns
provides:
  - CSS-only 36x20px visual toggle switches replacing raw checkboxes in all settings forms
  - Sidenav left-border accent (3px primary) with 16px section icons on all 4 nav items
  - ag-tooltip on every complex settings field with French impact explanation
  - per-section .card-footer with Enregistrer btn-primary + unsaved-dot dirty indicator
  - CNIL warning-border and security info-border accent cards
  - Email template editor two-pane layout (1fr + 400px) with live preview
  - Clickable .variable-tag buttons with ag-tooltip, cursor-insert JS handler
  - updateTemplatePreview() live rendering with bold sample variable substitution
affects: 40-02, 40-configuration-cluster

# Tech tracking
tech-stack:
  added: []
  patterns:
    - CSS-only toggle switch via .toggle-switch + .toggle-track::after (no JS, no component)
    - settings-toggle-row with .settings-toggle-header flex row for label + tooltip
    - .card-footer pattern for per-section explicit save with .unsaved-dot dirty indicator
    - template-editor-grid (1fr 400px) two-pane inline editor within card body

key-files:
  created: []
  modified:
    - public/settings.htmx.html
    - public/assets/css/settings.css
    - public/assets/js/pages/settings.js

key-decisions:
  - "Toggle switches: CSS-only via .toggle-switch + .toggle-track::after — no new JS component, no new dependency"
  - "Section save + auto-save coexist: auto-save silently runs; per-section btn adds explicit confidence affordance"
  - "All input IDs preserved when wrapping with toggle-switch HTML (Pitfall 3 respected) — auto-save continues to work"
  - "Template editor restructured as inline two-pane within #templateEditor card (not a modal) — Pitfall 6 respected"
  - "email-templates.css .variable-tag not modified — that file is for modal editor on separate context; settings.css version is canonical for settings page"

patterns-established:
  - "Pattern: CSS-only toggle switch — .toggle-switch label + hidden input + .toggle-track span with ::after thumb"
  - "Pattern: settings-toggle-row with settings-toggle-header wrapping toggle + label-group for label + info icon"
  - "Pattern: .card-footer with .btn-save-section + .unsaved-dot for dirty tracking on card changes"
  - "Pattern: template-editor-grid two-pane inline within card body (not modal)"

requirements-completed: [CORE-06, SEC-04]

# Metrics
duration: 18min
completed: 2026-03-20
---

# Phase 40 Plan 01: Configuration Cluster — Settings Page Summary

**Settings page redesigned to Notion/Clerk quality: CSS-only visual toggle switches, ag-tooltip on every complex field, per-section Enregistrer with unsaved-dot dirty tracking, CNIL/security card accents, and two-pane email template editor with live preview and click-to-insert variable tags.**

## Performance

- **Duration:** 18 min
- **Started:** 2026-03-20T~07:00Z
- **Completed:** 2026-03-20T~07:18Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments

- Replaced all 10 raw checkboxes with 36x20px CSS-only visual toggle switches — thumb slides right on check, track turns primary color
- Added SVG icons to all 4 sidenav items + left 3px primary border accent on active item (no layout shift via transparent reserved border)
- Added 66 ag-tooltip instances on complex field labels explaining French impact (quorum threshold, SMTP ports, CNIL articles, etc.)
- Added .card-footer with "Enregistrer" + unsaved-dot to every settings card (11 footers total)
- Added CNIL card warning-amber accent and 2FA/security cards info-blue accent
- Restructured templateEditor card body as two-pane grid: editor form + 400px live preview panel
- Clickable variable-tag buttons insert at textarea cursor position via JS; live preview replaces variables with bold sample data
- Added "Envoyer un test" ghost button in preview panel

## Task Commits

Each task was committed atomically:

1. **Task 1: Sidenav icons + toggle switches + ag-tooltips + card footers** - `bd08c67` (feat)
2. **Task 2: Email template two-pane layout + live preview + clickable variable tags** - `b5200c3` (feat)

**Plan metadata:** (included in this commit)

## Files Created/Modified

- `public/settings.htmx.html` — Sidenav icons, toggle-switch HTML wrappers, ag-tooltip on all labels, card-footer save buttons, variable-tag buttons, two-pane template editor
- `public/assets/css/settings.css` — Sidenav left-border accent, CSS-only toggle switch, card-footer, unsaved-dot, CNIL/security card accents, template-editor-grid, variable-tag badge, variable-tags-row
- `public/assets/js/pages/settings.js` — initSectionSave() handler + dirty tracking, initTemplatePreview() with updateTemplatePreview(), variable-tag click-to-insert, btnTestEmail handler

## Decisions Made

- CSS-only toggle switch (no new JS component, no new dependency) — preferred over JS-driven toggle
- Auto-save + per-section save coexist: auto-save runs silently for graceful navigation; section button gives explicit confidence affordance; unsaved-dot shows pending changes
- All input IDs preserved exactly when wrapping with toggle-switch HTML (Pitfall 3 from RESEARCH)
- Template editor remains inline within Communication tab card (not a separate page / modal) — Pitfall 6 from RESEARCH respected
- email-templates.css .variable-tag left unchanged — different page context, settings.css version is canonical for settings page

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- Settings page visually complete for phase 40 verification
- Admin page (40-02) and Help/FAQ (40-03) remain in phase 40 scope
- All settings input IDs preserved — auto-save and HTMX interactions remain intact

---
*Phase: 40-configuration-cluster*
*Completed: 2026-03-20*
