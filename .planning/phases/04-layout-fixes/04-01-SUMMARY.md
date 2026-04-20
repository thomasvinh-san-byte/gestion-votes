---
phase: 04-layout-fixes
plan: 01
subsystem: frontend-layout
tags: [css, html, ux, cleanup]
dependency_graph:
  requires: []
  provides: [compact-hero, select-meeting-type, clean-kpi-css]
  affects: [landing-page, operator-page, meetings-page, wizard-page]
tech_stack:
  added: []
  patterns: [form-select-for-enums]
key_files:
  created: []
  modified:
    - public/assets/css/landing.css
    - public/operator.htmx.html
    - public/meetings.htmx.html
    - public/wizard.htmx.html
    - public/assets/css/design-system.css
decisions:
  - Kept .landing min-height 100vh (page container minimum, not hero)
  - Wizard select updated with value attributes and name for backend consistency
metrics:
  duration: 2min
  completed: 2026-04-20
---

# Phase 04 Plan 01: Layout Fixes Summary

Compact landing hero, convert meeting type radios to select dropdowns on 3 pages, remove dead KPI CSS from design-system.css.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Landing hero compaction for 1080p | 36628c6c | landing.css |
| 2 | Radio buttons to select on 3 pages | 15a658e7 | operator.htmx.html, meetings.htmx.html, wizard.htmx.html |
| 3 | Delete dead KPI code from design-system.css | 10f791d2 | design-system.css |

## Key Changes

1. **Hero compaction**: Changed `.hero` from `min-height: 100vh` to `min-height: auto` and reduced padding so roles section is visible on 1080p without scrolling.

2. **Meeting type select**: Replaced radio button groups with `<select class="form-select">` on operator and meetings pages. Wizard already had a select but lacked value attributes and the full set of options -- standardized to match (ag_ordinaire, ag_extraordinaire, conseil, bureau, autre).

3. **Dead CSS removal**: Deleted 31 lines of dead `.kpi-card`, `.kpi-value`, and `.kpi-label` rules from design-system.css. pages.css remains the single source of truth for KPI card styles (10 references intact).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing functionality] Wizard select lacked value attributes and name**
- **Found during:** Task 2
- **Issue:** Wizard already had a select but without `value` attributes or `name` attribute, making it incompatible with backend expectations
- **Fix:** Added `name="meetingType"`, value attributes, and the full 5-option set (bureau, autre were missing)
- **Files modified:** public/wizard.htmx.html
- **Commit:** 15a658e7

## Self-Check: PASSED

## Verification Results

- No `100vh` on `.hero` rule (`.landing` container retains its min-height as normal page wrapper)
- Zero radio buttons for meeting type on any of the 3 pages
- Zero `.kpi-card` or `.kpi-value` references in design-system.css
- pages.css KPI definitions intact (10 references)
