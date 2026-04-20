---
phase: 03-coherence-cross-pages
plan: 01
subsystem: ui
tags: [version, templating, str_replace, cross-page-coherence]

# Dependency graph
requires:
  - phase: none
    provides: none
provides:
  - "Single-source APP_VERSION constant in PageController"
  - "%%APP_VERSION%% placeholder injection in all htmx pages"
affects: [04-layout-fixes, 05-validation-gate]

# Tech tracking
tech-stack:
  added: []
  patterns: ["str_replace placeholder injection for dynamic values in static HTML"]

key-files:
  created: []
  modified:
    - "app/Controller/PageController.php"
    - "public/partials/sidebar.html"
    - "public/*.htmx.html (21 files)"
    - "public/index.html"

key-decisions:
  - "Version set to v2.0 as clean major version for unified display"
  - "index.html uses hardcoded v2.0 with maintenance comment since not served through PageController"

patterns-established:
  - "%%PLACEHOLDER%% pattern: static HTML files use double-percent placeholders replaced at serve time by PageController"

requirements-completed: [UI-07]

# Metrics
duration: 2min
completed: 2026-04-20
---

# Phase 3 Plan 01: Unified Version String Summary

**Single APP_VERSION constant in PageController replaces 4 different version strings (v3.19/v4.3/v4.4/v5.0) across 23 files with v2.0**

## Performance

- **Duration:** 2 min
- **Started:** 2026-04-20T14:19:28Z
- **Completed:** 2026-04-20T14:21:03Z
- **Tasks:** 2
- **Files modified:** 24

## Accomplishments
- Defined `PageController::APP_VERSION = 'v2.0'` as single source of truth
- All 21 htmx pages now use `%%APP_VERSION%%` placeholder in footer spans
- Sidebar partial uses placeholder, injected at serve time
- Removed misleading version references from GO-LIVE-STATUS comments

## Task Commits

Each task was committed atomically:

1. **Task 1: Create version constant and add injection to PageController** - `382081a6` (feat)
2. **Task 2: Replace all hardcoded versions in HTML files with placeholder** - `38f1b8f8` (feat)

## Files Created/Modified
- `app/Controller/PageController.php` - Added APP_VERSION constant and str_replace injection
- `public/partials/sidebar.html` - Replaced v3.19 with %%APP_VERSION%%
- `public/*.htmx.html` (21 files) - Replaced footer version spans with placeholder
- `public/index.html` - Updated v5.0 to v2.0 with maintenance comment

## Decisions Made
- Used v2.0 as the unified version (clean major version, fresh start after consolidation)
- index.html gets hardcoded v2.0 with HTML comment since it is served statically by nginx/Apache, not through PageController

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Version placeholder pattern established, ready for additional placeholder needs
- All pages now coherent, ready for footer accent (03-02) and modal unification (03-03)

---
*Phase: 03-coherence-cross-pages*
*Completed: 2026-04-20*
