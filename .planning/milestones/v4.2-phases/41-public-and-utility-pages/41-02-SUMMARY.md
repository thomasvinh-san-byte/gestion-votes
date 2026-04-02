---
phase: 41-public-and-utility-pages
plan: "02"
subsystem: ui
tags: [projector, report, fraunces, font-display, pv-timeline, download-cta, dark-mode]

# Dependency graph
requires:
  - phase: 41-01
    provides: public.htmx.html and report.htmx.html base structure from phase 41-01
provides:
  - Projector page with dramatic room-scale verdict typography (Fraunces, clamp 3.5rem)
  - Verdict-colored decision cards via CSS :has() selector (success/danger)
  - Upgraded waiting state with display font, large clamp sizing, 80px icon
  - Footer brand as Fraunces watermark (opacity 0.6)
  - Report page with 4-step PV status timeline (generated/validated/sent/archived)
  - Report page gradient download CTA with PDF badge
  - Export buttons with radius-xl and increased padding
affects: [public-pages, report-page, projector-display]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "CSS :has() for parent element styling based on child class state"
    - "color-mix() for semi-transparent border from design token"
    - "Fraunces display font at large clamp sizes for room-scale legibility"

key-files:
  created: []
  modified:
    - public/assets/css/public.css
    - public/assets/css/report.css
    - public/report.htmx.html

key-decisions:
  - "CSS :has() used for verdict card coloring — avoids adding JS class to parent, pure CSS selector approach"
  - "PV timeline inserted above Export complet Excel card — visible in natural page flow before export options"
  - "Download CTA placed above export grid inside Exports individuels card — prominent first action"

patterns-established:
  - "Pattern: verdict-card coloring via .decision-card:has(.decision-value.adopted/rejected) — no JS needed"
  - "Pattern: PV timeline with .done/.active step classes for status progression"

requirements-completed:
  - SEC-06
  - SEC-07

# Metrics
duration: 8min
completed: 2026-03-20
---

# Phase 41 Plan 02: Public & Utility Pages — Projector + Report Summary

**Dramatic Fraunces verdict display at room scale (3.5rem clamp) with CSS :has() verdict cards, PV status timeline, and gradient download CTA on report page**

## Performance

- **Duration:** ~8 min
- **Started:** 2026-03-20T07:33:00Z
- **Completed:** 2026-03-20T07:41:18Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments

- Projector `.decision-value` upgraded to Fraunces display font, clamp(1.5rem, 4vw, 3.5rem), weight 800 — legible from 5 meters at 1080p
- Decision cards now take verdict-colored border (3px) and background via CSS `:has(.decision-value.adopted/rejected)` — no JS changes
- Waiting state title upgraded to font-display clamp(1.5rem, 4vw, 3rem), icon enlarged 64px → 80px
- Footer brand watermark: Fraunces, opacity 0.6, letter-spacing 0.05em — subtle and branded
- PV status timeline (4 steps: Generé, Validé, Envoyé, Archivé) with dot connectors added to report.htmx.html
- Gradient download CTA (linear-gradient primary → primary-hover) with PDF badge inserted above export grid
- Export buttons upgraded to radius-xl and larger padding

## Task Commits

1. **Task 1: Projector display — dramatic verdict, waiting state, footer watermark** - `490305e` (feat)
2. **Task 2: Report page — PV timeline, download CTA, export button upgrade** - `3653bf8` (feat)

## Files Created/Modified

- `public/assets/css/public.css` - Upgraded `.decision-value` typography, added verdict `:has()` card rules, upgraded `.waiting-title`, `.waiting-icon` 80px, `.footer-brand` watermark, semi-transparent `.projection-footer` border
- `public/report.htmx.html` - Added `.pv-timeline` with 4 steps, `.pv-download-cta` with PDF badge
- `public/assets/css/report.css` - Added pv-timeline, pv-step-dot, pv-download-cta, pv-file-meta, export-btn radius-xl rules

## Decisions Made

- CSS `:has()` approach for verdict card coloring — avoids touching JS, pure CSS selector, works with existing class pattern where `.adopted`/`.rejected` are set on `.decision-value` span
- PV timeline placed as standalone element above Export complet Excel card for natural flow visibility
- Download CTA inserted as first item in Exports individuels card-body for maximum prominence

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 41 (Public & Utility Pages) is fully complete — both plans executed
- v4.2 Visual Redesign milestone is complete — all phases done
- All public-facing projection and report pages now meet v4.2 design standards

---
*Phase: 41-public-and-utility-pages*
*Completed: 2026-03-20*
