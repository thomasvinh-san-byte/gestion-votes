---
phase: 41-public-and-utility-pages
plan: 01
subsystem: ui
tags: [landing, css, trust-signals, dark-mode, cta, feature-cards]

# Dependency graph
requires: []
provides:
  - Landing page redesigned with trust signal strip (Sécurisé, Conforme, Temps réel) below hero bullets
  - Feature items upgraded from flat icon-list rows to cards with hover lift effect
  - Gradient CTA section before footer with "Prêt à commencer ?" call to action
  - Hero title dark mode fix (var(--color-text) instead of hardcoded color-text-dark)
  - Dual radial hero gradient for richer background depth
  - Login button gradient applied directly in landing.css (no login.css import)
  - v4.2 version indicator in footer
affects: [landing page visual identity, v4.2 milestone sign-off]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Trust strip pattern — inline SVG icons with color-success accent + text-muted labels
    - CTA section pattern — full-width gradient strip with white text and surface-raised button
    - Feature card pattern — column flex layout with surface bg, border, radius, hover translateY(-4px)

key-files:
  created: []
  modified:
    - public/index.html
    - public/assets/css/landing.css

key-decisions:
  - "Trust strip placed inside .hero-text div (after .hero-bullets ul) so it appears in left column alongside login card"
  - "features-grid changed to repeat(3, 1fr) fixed columns (not auto-fit) to enforce 3-column layout on desktop"
  - "Mobile features-grid fallback uses 1fr inside existing max-width:768px breakpoint"
  - "CTA section inserted between closing </section> of .features-section and footer — no JS dependency"
  - "Login button gradient scoped to .login-card .login-btn to avoid overriding other .btn-primary instances"

patterns-established:
  - "Landing CTA strip: linear-gradient primary→primary-hover, max-width 600px body, surface-raised button"
  - "Trust item: 16px success-colored SVG icon + text-muted label + font-medium weight"

requirements-completed: [SEC-02]

# Metrics
duration: 2min
completed: 2026-03-20
---

# Phase 41 Plan 01: Landing Page Trust Redesign Summary

**Landing page redesigned with hero trust strip (Sécurisé/Conforme/Temps réel), feature cards with hover lift, gradient CTA section, and dark-mode-safe hero title**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-20T07:58:58Z
- **Completed:** 2026-03-20T08:00:53Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Added hero trust strip with 3 trust signals (shield/check/clock SVG icons) below hero bullets
- Upgraded feature items from flat row layout to card layout with surface background, border, border-radius, and hover translateY(-4px) lift
- Added full-width gradient CTA section "Prêt à commencer ?" between features section and footer
- Fixed hero title dark mode bug (was using `var(--color-text-dark, #1a1a1a)` which rendered invisible in dark mode)
- Added dual radial hero gradient (60% primary opacity at top-right, 6% at bottom-left) for depth
- Added gradient login button via `.login-card .login-btn` scoped rule in landing.css
- Added v4.2 version to footer copyright

## Task Commits

Each task was committed atomically:

1. **Task 1: Landing page HTML additions — trust strip and CTA section** - `1e1b8ff` (feat)
2. **Task 2: Landing CSS — trust strip, feature cards, CTA section, dark mode fix, hero gradient** - `f9b6525` (feat)

## Files Created/Modified
- `public/index.html` - Added hero-trust-strip div, cta-section, v4.2 footer version
- `public/assets/css/landing.css` - Dark mode fix, dual radial gradient, trust strip styles, feature card upgrade, features-grid 3-column, CTA section, login-btn gradient

## Decisions Made
- Trust strip placed inside `.hero-text` (after `.hero-bullets`) so it appears on left side of hero body
- `.features-grid` changed from `auto-fit minmax(280px, 1fr)` to `repeat(3, 1fr)` for consistent 3-column desktop layout
- Mobile 1-column fallback for features-grid added inside existing `@media (max-width: 768px)` block
- Login-btn gradient scoped to `.login-card .login-btn` to avoid interfering with other `.btn-primary` buttons on the page

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness
- Landing page redesign complete, v4.2 visual identity established for public-facing entry point
- Phase 41 plans 02 and 03 handle projector display and utility pages respectively

---
*Phase: 41-public-and-utility-pages*
*Completed: 2026-03-20*
