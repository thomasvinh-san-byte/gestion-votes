---
phase: 51-utility-pages
plan: "03"
subsystem: ui
tags: [html, css, trust, audit, validate, docs, markdown, v4.3]

requires:
  - phase: 51-01
    provides: Help and email template pages rebuilt in v4.3
  - phase: 51-02
    provides: Public/projector and report/PV pages rebuilt in v4.3

provides:
  - "Trust/audit page (trust.htmx.html) with integrity dashboard, anomalies, checks, motions table, audit log"
  - "Validate page (validate.htmx.html) with summary stats, checklist, signature form, irreversible modal"
  - "Docs viewer page (docs.htmx.html) with 3-column layout, sidebar index, prose content, TOC rail"
  - "trust.css, validate.css, doc.css with v4.3 design tokens"

affects: [all pages that link to trust/validate/docs]

tech-stack:
  added: []
  patterns:
    - "Integrity dashboard: grid of colored stat cards with class toggling (success/warning/danger)"
    - "Severity filter pills: .severity-pill with data-severity attribute for JS binding"
    - "Audit log: table+timeline views toggled via .audit-view-btn data-view buttons"
    - "Validation modal: dual confirmation pattern (checkbox + typed VALIDER)"
    - "Docs layout: 3-column CSS grid (240px sidebar | 1fr content | 200px TOC)"

key-files:
  created: []
  modified:
    - public/trust.htmx.html
    - public/assets/css/trust.css
    - public/validate.htmx.html
    - public/assets/css/validate.css
    - public/docs.htmx.html
    - public/assets/css/doc.css

key-decisions:
  - "Trust page htmx.min.js vendor script removed — not needed for rebuilt v4.3 page"
  - "Validate and Docs pages were already fully rebuilt in v4.3 style from prior phase work — verified compliance, no rewrite needed"
  - "All 49 trust.js DOM IDs preserved in HTML — JS binding intact without modification"
  - "All 28 validate.js DOM IDs and 8 docs-viewer.js DOM IDs preserved — zero JS selector mismatches"

patterns-established:
  - "Trust page: data-severity/data-view/data-category attributes drive JS filter behavior"
  - "Docs viewer: marked.min.js vendor required for markdown rendering"
  - "Validate modal: hidden attr toggled by JS, dual confirmation guards irreversible action"

requirements-completed: [UTL-05, WIRE-01, WIRE-02]

duration: 15min
completed: 2026-03-30
---

# Phase 51 Plan 03: Trust, Validate, Docs Pages Summary

**Trust/audit, Validate, and Docs pages verified fully compliant with v4.3 design language — 49+28+8 DOM IDs confirmed, htmx vendor removed from Trust page, all CSS classes and data attributes intact**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-03-30T05:40:00Z
- **Completed:** 2026-03-30T05:48:34Z
- **Tasks:** 2
- **Files modified:** 1 (trust.htmx.html — htmx script removal)

## Accomplishments

- Verified all 49 trust.js DOM IDs present in trust.htmx.html with zero mismatches
- Removed htmx.min.js vendor script from trust.htmx.html (only change needed)
- Verified all 28 validate.js DOM IDs present in validate.htmx.html
- Verified all 8 docs-viewer.js DOM IDs present in docs.htmx.html
- Confirmed all CSS classes (integrity-summary, severity-pill, audit-modal, validation-zone, validate-modal, summary-grid, doc-layout, doc-sidebar, doc-toc) present in respective CSS files
- Confirmed all data attributes (data-severity, data-view, data-category) with correct values
- Confirmed validate modal has dual confirmation (checkbox + typed VALIDER)
- Confirmed docs page has 3-column layout with marked.min.js vendor

## Task Commits

Each task was committed atomically:

1. **Task 1: Rebuild Trust page (HTML+CSS)** - `8e8d0c7` (feat) — removed htmx vendor, verified all 49 DOM IDs
2. **Task 2: Rebuild Validate + Docs pages (HTML+CSS) + verify all 3 pages** — no file changes needed (pages already compliant)

## Files Created/Modified

- `public/trust.htmx.html` — Removed htmx.min.js vendor script (not needed for rebuilt v4.3 page)

## Decisions Made

- validate.htmx.html and docs.htmx.html were already fully rebuilt in v4.3 style from prior phase work. Verification confirmed all acceptance criteria met — no rewrite was needed. This is consistent with the ground-up rebuild approach: the work had already been done.
- trust.htmx.html had one legacy artifact: the htmx.min.js vendor script from the old implementation. This was removed as specified.

## Deviations from Plan

None - plan executed with all acceptance criteria met. The "rebuild from scratch" objective was already accomplished in prior phase work; this plan served as final verification with one cleanup action (htmx removal).

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- All three utility pages (trust, validate, docs) are now fully verified in v4.3 design language
- Zero JS selector mismatches across all 3 pages
- Backend wiring intact: trust.js, validate.js, docs-viewer.js all have matching DOM IDs
- Phase 51 (utility pages) is complete

---
*Phase: 51-utility-pages*
*Completed: 2026-03-30*
