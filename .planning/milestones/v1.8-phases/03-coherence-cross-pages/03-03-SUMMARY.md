---
phase: 03-coherence-cross-pages
plan: 03
subsystem: ui
tags: [modals, accessibility, aria, cross-page-coherence]

# Dependency graph
requires:
  - phase: none
    provides: none
provides:
  - "Standard modal-backdrop + modal classes on validate and trust pages"
  - "role=dialog and aria-modal=true on email-templates editor"
  - "Consistent ARIA labelling across all modal dialogs"
affects: [05-validation-gate]

# Tech tracking
tech-stack:
  added: []
  patterns: ["modal-backdrop/modal structural pattern", "hidden attribute for initial dialog state"]

# Key files
key-files:
  created: []
  modified:
    - public/validate.htmx.html
    - public/trust.htmx.html
    - public/email-templates.htmx.html
    - public/assets/js/pages/email-templates-editor.js
    - public/assets/css/validate.css
    - public/assets/css/trust.css

# Decisions
decisions:
  - "Keep semantic content classes (validate-modal-warning, audit-modal-row) unchanged"
  - "Add hidden attribute to template-editor with JS toggle for show/hide"
  - "meetings.htmx.html already compliant - no changes needed"

# Metrics
metrics:
  duration: "9min"
  completed: "2026-04-20"
  tasks_completed: 2
  tasks_total: 2
---

# Phase 03 Plan 03: Modal Pattern Unification Summary

Standard modal-backdrop/modal classes and role=dialog/aria-modal on all 4 target pages.

## What Was Done

### Task 1: Migrate validate and trust modals to standard pattern

- Renamed `validate-modal-backdrop` to `modal-backdrop` in validate.htmx.html
- Renamed structural classes (header, body, actions) to standard `modal-*` names
- Kept semantic content classes (`validate-modal-warning`, `validate-modal-checkbox`)
- Renamed `audit-modal-overlay` to `modal-backdrop` in trust.htmx.html
- Moved `role="dialog"` and `aria-modal="true"` to backdrop element
- Updated CSS selectors in validate.css and trust.css to use ID-scoped standard classes

**Note:** These HTML/CSS changes were found already committed from plan 03-01 execution (commits 38f1b8f8, 91e1aacf). No additional commit was needed.

### Task 2: Add dialog role to email-templates editor

- Added `role="dialog"`, `aria-modal="true"`, `aria-labelledby="editorTitle"` to template-editor div
- Added `hidden` attribute for proper initial hidden state
- Updated all JS show/hide paths to toggle `hidden` property alongside `.active` class
- Verified meetings.htmx.html already has complete ARIA attributes (no changes needed)

**Commit:** 2eb815ac

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] JS hidden attribute toggle needed**
- **Found during:** Task 2
- **Issue:** Global `:where([hidden]) { display: none !important }` rule would prevent `.active` class from overriding hidden state
- **Fix:** Added `templateEditor.hidden = false/true` to all JS show/hide code paths
- **Files modified:** public/assets/js/pages/email-templates-editor.js
- **Commit:** 2eb815ac

## Verification Results

- All 4 pages have `role="dialog"` on modal elements
- validate, trust, meetings use `modal-backdrop` class
- email-templates has `aria-modal="true"` on editor
- No `validate-modal-backdrop` or `audit-modal-overlay` remains in HTML
