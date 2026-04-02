---
phase: 47-hub-rebuild
plan: 01
subsystem: ui
tags: [html, css, hub, design-tokens, responsive]

# Dependency graph
requires:
  - phase: 46-operator-console-rebuild
    provides: hero card + two-column panel pattern, design token conventions, operator.css as CSS reference
provides:
  - Hub page HTML with hero card, two-column body (280px checklist + fluid right)
  - Hub CSS with all new classes: hub-hero, hub-body, hub-checklist-card, hub-quorum-card, hub-motions-card
  - Preserved DOM IDs for Plan 02 JS wiring
affects: [47-02, hub.js JS wiring]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Hero card with ::before accent stripe (3px gradient) as session identity — same as operator"
    - "Two-column CSS grid (280px + 1fr) below hero for sidebar + main content"
    - "hidden attribute (not display:none) for sections toggled by JS"
    - "Dot state modifiers: --done / --blocked / --pending on checklist items"

key-files:
  created: []
  modified:
    - public/hub.htmx.html
    - public/assets/css/hub.css

key-decisions:
  - "hub-hero replaces hub-identity: same icon/badges/meta structure but adds action buttons inline"
  - "3-item checklist (convocation/quorum/agenda) replaces 6-step stepper — simpler prerequisite model"
  - "hubOperatorBtn added as secondary CTA alongside hubMainBtn in hero (not in plan DOM IDs list but required by task spec)"
  - "hidden attr on hubQuorumSection, hubMotionsSection, hubConvocationSection — JS removeAttribute('hidden') to show"
  - "Old classes removed: hub-identity, hub-status-bar, hub-stepper-col, hub-layout-body, hub-action, hub-kpi, hub-documents, hub-warn-card"

patterns-established:
  - "Card-based hub layout mirrors operator console rebuild pattern from Phase 46"
  - "Checklist dot states via BEM modifiers: .hub-checklist-dot--done/.hub-checklist-dot--pending/.hub-checklist-dot--blocked"

requirements-completed: [REB-05]

# Metrics
duration: 3min
completed: 2026-03-22
---

# Phase 47 Plan 01: Hub Rebuild — HTML + CSS Summary

**Hub page rebuilt ground-up: hero card with session identity and CTAs, two-column layout (280px checklist sidebar + quorum/motions right panel), token-based dark mode — 1311-line CSS reduced to 485 lines**

## Performance

- **Duration:** ~3 min
- **Started:** 2026-03-22T16:41:47Z
- **Completed:** 2026-03-22T16:44:27Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- Rewrote hub.htmx.html: hero card with icon, session identity (title, type/status badges, date/place/participants meta), primary CTA (Ouvrir la seance) + secondary CTA (Aller a la console), two-column body with 3-item prerequisite checklist, quorum bar card, motions preview card, convocation section
- Rewrote hub.css: 1311 lines → 485 lines (-63%), all old styles removed, fresh implementation using design tokens throughout
- Preserved all critical DOM IDs for JS wiring (hubTitle, hubDate, hubPlace, hubParticipants, hubTypeTag, hubStatusTag, hubChecklist, hubQuorumSection, hubQuorumBar, hubQuorumPct, hubMotionsSection, hubMotionsList, hubConvocationSection, btnSendConvocations, hubMainBtn)

## Task Commits

Each task was committed atomically:

1. **Task 1: Rewrite hub.htmx.html from scratch** - `68cb07c` (feat)
2. **Task 2: Rewrite hub.css from scratch** - `207ce73` (feat)

## Files Created/Modified

- `public/hub.htmx.html` — Complete rewrite: hero card + two-column layout, 3-item checklist, quorum/motions/convoc sections
- `public/assets/css/hub.css` — Complete rewrite: hero, two-column grid, checklist states, quorum/motions cards, responsive

## Decisions Made

- `hubOperatorBtn` added as second CTA in hero alongside `hubMainBtn` — plan spec required "Aller a la console" button with that ID even though original DOM IDs list in context only mentioned hubMainBtn
- Checklist dot check icon (SVG) hidden by default in CSS, shown only when `--done` modifier is present
- `#fff` → `var(--color-text-inverse, white)` in checklist dot to avoid hardcoded hex

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

- Initial hub.css had 2 hardcoded hex values (`#fff`) in checklist dot icon — replaced with `var(--color-text-inverse, white)` before committing

## Next Phase Readiness

- HTML structure and CSS are complete and ready for Plan 02 JS wire-up
- All DOM IDs preserved; hub.js will need selector updates for new element structure (checklist items, quorum pct, motions list rendering)
- No blockers

---
*Phase: 47-hub-rebuild*
*Completed: 2026-03-22*
