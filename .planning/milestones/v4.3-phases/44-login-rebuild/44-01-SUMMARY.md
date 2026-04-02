---
phase: 44-login-rebuild
plan: 01
subsystem: ui
tags: [css, floating-labels, animation, login, dark-mode, design-system]

# Dependency graph
requires: []
provides:
  - Ground-up login.html with floating-label field-groups, animated gradient orb, demo panel outside card
  - login.css with orb-drift animation, 420px elevated card, focus-within glow, full dark mode parity
affects: [44-02-login-js-update, phase-45-sessions-page, phase-46-admin-page]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Floating label CSS: .field-group > .field-input[placeholder=' '] + .field-label with :placeholder-shown and .has-value class"
    - "Animated gradient orb: position:fixed div with @keyframes orb-drift, will-change: transform opacity"
    - "Card focus-within glow: .login-card:focus-within adds --color-primary-glow box-shadow ring"
    - "@starting-style card entrance: opacity 0 + translateY(12px) -> normal"

key-files:
  created: []
  modified:
    - public/login.html
    - public/assets/css/login.css

key-decisions:
  - "Password floating label uses JS-assisted .has-value on .field-group (label is sibling of .field-input-wrap, not direct sibling of input)"
  - "Trust signal and footer moved outside .login-card into <main> (below card)"
  - "Demo panel is a static #demoPanel hidden div outside card — Plan 02 will wire JS to populate and unhide it"
  - "Brand mark increased to 48px (CSS) with unchanged SVG viewBox"

patterns-established:
  - "field-group pattern: position:relative wrapper with .field-input, .field-label sibling, .field-error-msg span"
  - "placeholder=' ' (single space) required on all floating label inputs for :placeholder-shown detection"

requirements-completed: [REB-02]

# Metrics
duration: 8min
completed: 2026-03-20
---

# Phase 44 Plan 01: Login Rebuild Summary

**Ground-up login page rewrite: floating-label form, animated gradient orb, 420px elevated card with focus-within glow, full dark mode parity — all CSS via design system tokens**

## Performance

- **Duration:** ~8 min
- **Started:** 2026-03-20T12:00:00Z
- **Completed:** 2026-03-20T12:08:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Rewrote `public/login.html` from scratch: floating-label field-groups, `.login-orb` div for animated background, `#demoPanel` outside card, trust signal and footer moved below card
- Rewrote `public/assets/css/login.css` entirely: 16 sections covering orb animation, 420px card, floating labels with `:placeholder-shown` + `.has-value`, password eye toggle, field error states, dark mode, responsive 1024/768/480px
- All 12 JS-required IDs preserved exactly (loginForm, email, password, errorBox, successBox, submitBtn, loginSpinner, togglePassword, forgotLink, forgotMsg, btnTheme, demoPanel)
- Zero raw hex or spacing values in CSS — 100% design system tokens

## Task Commits

Each task was committed atomically:

1. **Task 1: Rewrite login.html with floating labels, orb, and new structure** - `9f25e33` (feat)
2. **Task 2: Rewrite login.css with floating labels, orb animation, card glow, dark mode** - `be765bb` (feat)

## Files Created/Modified
- `public/login.html` - Complete ground-up HTML rewrite: floating-label .field-group structure, .login-orb, .login-card, #demoPanel outside card, trust signal and footer in <main>
- `public/assets/css/login.css` - Complete CSS rewrite: orb-drift animation, 420px elevated card, focus-within glow, @starting-style entrance, floating labels, dark mode, responsive

## Decisions Made
- Password floating label uses JS-assisted `.has-value` on `.field-group` because the label is a sibling of `.field-input-wrap` (not a direct sibling of the input), making the CSS `~` sibling combinator insufficient alone
- Trust signal and footer moved outside `.login-card` into `<main>` so the card glow (`:focus-within`) doesn't engulf them
- `#demoPanel` is a static hidden div — JS (Plan 02) will populate its innerHTML and call `removeAttribute('hidden')` instead of using `card.appendChild()` as before
- Brand mark size increased from 40px to 48px per CONTEXT.md decision; SVG viewBox unchanged

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- login.html and login.css fully rebuilt; visual layer complete
- Plan 02 will update login.js: fix `setFieldError()` to use `.field-input-wrap` instead of `.field-wrap`, update `showDemoHint()` to target `#demoPanel`, add `.has-value` class toggling on password field group

---
*Phase: 44-login-rebuild*
*Completed: 2026-03-20*
