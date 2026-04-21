---
phase: 04-clarte-et-jargon
plan: 02
subsystem: ui
tags: [html, css, tooltips, export, accessibility, french]

# Dependency graph
requires:
  - phase: 04-clarte-et-jargon/04-01
    provides: jargon voter elimine, FAQ simplifiee
provides:
  - ag-tooltip sur termes techniques admin (quorum, CNIL, eIDAS, SHA-256, procuration)
  - export-desc visible sur 12 boutons d'export travers 4 pages
  - CSS export-btn-wrap + export-desc dans archives.css
affects: [phase-05-validation-gate]

# Tech tracking
tech-stack:
  added: []
  patterns: [ag-tooltip inside label/heading for technical terms, export-btn-wrap wrapper pattern for btn+desc pairs]

key-files:
  created: []
  modified:
    - public/settings.htmx.html
    - public/operator.htmx.html
    - public/postsession.htmx.html
    - public/audit.htmx.html
    - public/archives.htmx.html
    - public/trust.htmx.html
    - public/assets/css/archives.css

key-decisions:
  - "ag-tooltip placed INSIDE parent element (label, h3, div) never between label and input"
  - "export-btn-wrap--full replaces exports-zip-btn grid-column span — wrapper now owns grid placement"
  - "Procurations tooltip on section h3 heading (line 602) — button at line 1449 already had ag-tooltip"
  - "trust.htmx.html SHA-256 ag-popover unchanged — only export descriptions added"

patterns-established:
  - "export-btn-wrap: flex column wrapper with btn + small.export-desc for all export buttons"
  - "ag-tooltip inside heading/label text for technical term explanation on hover"

requirements-completed: [CLAR-02, CLAR-04]

# Metrics
duration: 25min
completed: 2026-04-21
---

# Phase 4 Plan 2: Tooltips techniques et descriptions d'export Summary

**ag-tooltip sur 6 termes techniques admin (quorum, CNIL, eIDAS, SHA-256, procuration) + export-desc en francais sur 12 boutons d'export travers 4 pages**

## Performance

- **Duration:** 25 min
- **Started:** 2026-04-21T10:25:00Z
- **Completed:** 2026-04-21T10:50:00Z
- **Tasks:** 2
- **Files modified:** 7

## Accomplishments

- 6 instances ag-tooltip sur termes techniques dans 4 pages admin/operateur (settings x2, operator x2, postsession x1, audit x1)
- 12 instances export-desc sur tous les boutons d'export (archives x7, audit x2, postsession x1, trust x2)
- CSS export-btn-wrap, export-btn-wrap--full, et export-desc ajoutes dans archives.css
- ZIP button dans archives : grid-column span migre du bouton vers le wrapper export-btn-wrap--full

## Task Commits

1. **Task 1: Add admin tooltips on technical terms (CLAR-02)** - `cef5f392` (feat)
2. **Task 2: Add export descriptions on all export buttons (CLAR-04)** - `b236f79f` (feat)

## Files Created/Modified

- `public/settings.htmx.html` - ag-tooltip sur "Seuil de quorum (%)" et "CNIL" dans card title
- `public/operator.htmx.html` - ag-tooltip sur quorumStatusLabel et section heading Procurations
- `public/postsession.htmx.html` - ag-tooltip sur "eIDAS" dans card title signature electronique + export-desc sur PDF
- `public/audit.htmx.html` - ag-tooltip sur "SHA-256" dans onboarding tips + export-desc sur 2 boutons export
- `public/archives.htmx.html` - 7 boutons export wraps dans export-btn-wrap avec export-desc
- `public/trust.htmx.html` - 2 boutons export wraps dans export-btn-wrap avec export-desc
- `public/assets/css/archives.css` - classes export-btn-wrap, export-btn-wrap--full, export-desc

## Decisions Made

- ag-tooltip place INSIDE l'element parent (label, h3, div) — jamais entre un label et son input pour ne pas casser click-to-focus
- export-btn-wrap--full remplace exports-zip-btn pour le grid-column span — le wrapper possede maintenant le placement grille
- Tooltip Procurations sur le heading h3 de section (plus prominent) — le bouton ligne 1449 avait deja ag-tooltip
- trust.htmx.html : ag-popover SHA-256 existant non modifie — seules les descriptions export ajoutees

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 4 complete (plans 01 et 02 termines) — CLAR-01, CLAR-02, CLAR-04 satisfaits
- Phase 5 Validation Gate peut verifier tooltips et descriptions visuellement
- trust.htmx.html SHA-256 ag-popover inchange comme prevu

---
*Phase: 04-clarte-et-jargon*
*Completed: 2026-04-21*
