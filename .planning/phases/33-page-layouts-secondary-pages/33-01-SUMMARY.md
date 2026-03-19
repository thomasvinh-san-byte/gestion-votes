---
phase: 33-page-layouts-secondary-pages
plan: "01"
subsystem: ui
tags: [css, layout, grid, sticky, design-tokens, hub, postsession]

# Dependency graph
requires:
  - phase: 32-page-layouts-core-pages
    provides: Layout patterns established — grid, sticky, max-width constraints, token usage
  - phase: 30-token-foundation
    provides: Design tokens (--space-card, --space-section, --color-surface-raised, --color-primary)
  - phase: 31-component-refresh
    provides: Component CSS patterns including sticky nav and card surface tokens
provides:
  - Hub CSS Grid 220px+1fr two-column layout with sticky sidebar stepper
  - Hub quorum section with raised surface background and accent border
  - Post-session content centered at max-width 900px
  - Post-session stepper sticky at top:80px with z-index:10
  - Post-session panel inter-section spacing via var(--space-section)
affects:
  - 34-quality-assurance-final-audit

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Sidebar stepper pattern: 220px grid column, sticky top:80px, align-self:start"
    - "Single-column guided flow: max-width:900px, sticky top stepper, space-section panel gaps"
    - "Quorum prominence: color-surface-raised background + border-left accent (3px solid primary)"

key-files:
  created: []
  modified:
    - public/assets/css/hub.css
    - public/assets/css/postsession.css

key-decisions:
  - "Hub sidebar column: 220px (not 260px) matching Phase 32 settings/operator patterns for consistent sidebar width"
  - "Post-session sticky stepper uses background:var(--color-bg) to prevent content bleed-through on scroll"
  - "Stepper background must match page background (not surface) to prevent z-fighting with content cards below"

patterns-established:
  - "Sidebar stepper pattern: grid-template-columns:220px 1fr, stepper col sticky top:80px align-self:start"
  - "Centered guided flow: postsession-main .container max-width:900px margin:0 auto"
  - "Inter-section breathing room: .ps-panel + .ps-panel margin-top:var(--space-section) (48px)"

requirements-completed: [LAY-07, LAY-08]

# Metrics
duration: 15min
completed: 2026-03-19
---

# Phase 33 Plan 01: Secondary Page Layouts — Hub and Post-Session Summary

**Hub two-column CSS Grid (220px+1fr sidebar stepper) and post-session centered 900px layout with sticky stepper and 48px inter-section spacing**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-03-19T08:30:00Z
- **Completed:** 2026-03-19T08:45:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- Hub layout converted from flexbox to CSS Grid with 220px fixed sidebar column containing sticky stepper
- Hub quorum section elevated visually with var(--color-surface-raised) background and 3px primary border-left accent
- Hub spacing fully tokenized: hub-checklist, hub-motions-section, hub-convocation-section, hub-action all use var(--space-card)
- Legacy .hub-layout grid also updated to 220px+1fr with var(--space-card) gap
- Post-session content constrained to 900px max-width centered with .postsession-main .container selector
- Post-session stepper made sticky at top:80px with z-index:10 and var(--color-bg) background
- Post-session panels separated by var(--space-section) (48px) using adjacent sibling selector
- Post-session hardcoded 14px values replaced with var(--space-4) across complete-banner, irreversible-warning, validation-kpis

## Task Commits

Each task was committed atomically:

1. **Task 1: Hub layout — CSS Grid + quorum prominence (LAY-07)** - `4c47360` (feat)
2. **Task 2: Post-session — centered max-width + sticky stepper + section spacing (LAY-08)** - `c520df9` (feat)

## Files Created/Modified

- `public/assets/css/hub.css` - CSS Grid two-column layout, quorum visual prominence, tokenized spacing
- `public/assets/css/postsession.css` - Centered 900px constraint, sticky stepper, inter-section spacing

## Decisions Made

- Hub sidebar: 220px (consistent with Phase 32 settings page — same sidebar width pattern across all stepper pages)
- Post-session sticky stepper background set to var(--color-bg) not var(--color-surface) to prevent cards from showing through

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- Hub and post-session layout patterns established and committed
- Pattern library complete: sidebar-stepper (hub), single-column-guided-flow (post-session)
- Phase 34 final audit has these layouts as reference implementations
- No blockers

---
*Phase: 33-page-layouts-secondary-pages*
*Completed: 2026-03-19*
