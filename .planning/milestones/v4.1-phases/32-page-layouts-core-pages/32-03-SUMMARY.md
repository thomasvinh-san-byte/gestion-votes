---
phase: 32-page-layouts-core-pages
plan: "03"
subsystem: ui
tags: [css-grid, sticky, clamp, mobile, safe-area, fluid-typography, touch-targets]

# Dependency graph
requires:
  - phase: 30-token-foundation
    provides: CSS custom property tokens (--space-card, --color-surface, --color-border, etc.)
  - phase: 31-component-refresh
    provides: card, button, form input components with design-system tokens
provides:
  - Settings page CSS Grid 220px sticky sidenav + 720px content column with responsive collapse
  - Mobile voter clamp() fluid typography, fixed bottom nav with safe-area padding, 72px touch targets
affects: [33-page-layouts-secondary-pages, 34-quality-assurance-final-audit]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "CSS Grid two-column layout: grid-template-columns: 220px 1fr for sidebar+content pages"
    - "Sticky sidenav: position:sticky top:80px align-self:start inside CSS Grid"
    - "Responsive sidenav collapse: grid-template-columns:1fr + display:flex overflow-x:auto at 768px"
    - "Fluid typography: clamp(min, vw, max) for body and heading font sizes"
    - "Fixed bottom nav: position:fixed with env(safe-area-inset-bottom) for iOS safe area"
    - "Bottom nav clearance: padding-bottom:calc(60px + env(safe-area-inset-bottom)) on scrollable content"

key-files:
  created: []
  modified:
    - public/assets/css/settings.css
    - public/assets/css/vote.css

key-decisions:
  - "Settings sidenav converted from flex to CSS Grid 220px+1fr — enables true two-column layout with automatic 1fr content fill"
  - "sticky top:80px set for settings sidenav — matches assumed app header height; avoids overlap"
  - "Mobile vote-bottom-nav uses position:fixed (not flex-shrink:0) — fixed nav stays visible even when content overflows 100dvh"
  - "vote-btn min-height kept at 72px minimum per LAY-06 spec (was 120px; 72px is the floor for touch targets)"
  - "motion-title clamp updated to 3.5vw midpoint (from 2.6vw) — better fluid scaling on mid-range viewports"

patterns-established:
  - "Clerk/Stripe-style two-column layout: 220px sticky sidenav + 720px max-width content column"
  - "Mobile voter safe-area pattern: env(safe-area-inset-bottom, 16px) on fixed bottom nav + calc() padding on scroll container"

requirements-completed: [LAY-05, LAY-06]

# Metrics
duration: 15min
completed: 2026-03-19
---

# Phase 32 Plan 03: Settings Layout + Mobile Voter Summary

**CSS Grid 220px sticky settings sidenav with responsive collapse, plus mobile voter clamp() fluid typography and fixed safe-area bottom nav**

## Performance

- **Duration:** 15 min
- **Started:** 2026-03-19T00:00:00Z
- **Completed:** 2026-03-19T00:15:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- Settings page rebuilt with CSS Grid `grid-template-columns: 220px 1fr` — sidenav is sticky at top:80px, content limited to max-width:720px
- Settings sidenav collapses to horizontal scrollable tab bar at 768px via `overflow-x: auto`
- Mobile voter gains `clamp(0.875rem, 2.5vw, 1.125rem)` body font size and `clamp(1.125rem, 3.5vw, 1.5rem)` heading size on `.motion-title`
- Vote bottom nav is now `position:fixed` with `env(safe-area-inset-bottom, 16px)` — works on iOS with notch/home indicator
- Vote main area gets `padding-bottom: calc(60px + env(safe-area-inset-bottom, 16px))` to prevent content hidden under fixed nav

## Task Commits

Each task was committed atomically:

1. **Task 1: Settings CSS Grid layout with sticky sidenav (LAY-05)** - `b6da09f` (feat)
2. **Task 2: Mobile voter fluid typography + fixed nav + safe area (LAY-06)** - `7b9291e` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified

- `public/assets/css/settings.css` - Converted .settings-layout to CSS Grid 220px+1fr, sticky sidenav, max-width:720px content, responsive horizontal scroll sidenav at 768px
- `public/assets/css/vote.css` - Added clamp() font sizes, 72px min-height vote buttons, fixed bottom nav with safe-area, padding-bottom on vote-main for mobile

## Decisions Made

- Settings sidenav converted from `display: flex` to CSS Grid two-column — enables proper sticky behavior and auto-filling content column
- `sticky top: 80px` on settings sidenav — accounts for fixed app header height without hardcoding to a specific header element
- Vote bottom nav changed from flex-shrink document flow to `position: fixed` — ensures nav is always accessible even when scrollable content overflows
- Vote button min-height updated to 72px (was 120px) — 72px is the minimum touch target requirement; existing `padding: 2rem 1rem` ensures visual height remains well above this floor
- `.motion-title` clamp midpoint updated from 2.6vw to 3.5vw — matches LAY-06 spec and provides better scaling on 375px–768px range

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- Settings page layout ready for Phase 33 secondary pages (session detail, ballot forms)
- Mobile voter safe-area foundation ready for any future mobile-specific improvements
- Both LAY-05 and LAY-06 requirements fulfilled

---
*Phase: 32-page-layouts-core-pages*
*Completed: 2026-03-19*
