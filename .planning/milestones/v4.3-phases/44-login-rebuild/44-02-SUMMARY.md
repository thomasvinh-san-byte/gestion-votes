---
phase: 44-login-rebuild
plan: 02
subsystem: ui
tags: [javascript, floating-labels, form-validation, login, dark-mode, demo-panel]

# Dependency graph
requires:
  - phase: 44-01
    provides: "New login.html with .field-group/.field-input-wrap structure and #demoPanel static div"
provides:
  - login.js wired to new DOM: setFieldError targets .field-group, floating label .has-value toggling, #demoPanel populated and unhidden on demand
  - Auth flow fully functional: submit -> api() POST -> redirect by role, empty/wrong-credential error handling, whoami auto-redirect
affects: [phase-45-sessions-page, phase-46-admin-page]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Field error targeting: input.closest('.field-group').classList.add('field-error') — works regardless of nesting depth"
    - "Floating label JS assist: updateHasValue() toggles .has-value on .field-group for password (label is sibling of .field-input-wrap)"
    - "Demo panel: static #demoPanel hidden div; JS populates innerHTML then calls removeAttribute('hidden')"
    - "Autofill detection: setTimeout 100ms after page load calls updateHasValue on all fields"

key-files:
  created: []
  modified:
    - public/assets/js/pages/login.js

key-decisions:
  - "updateHasValue() applied to both fields for consistency, even though email can use CSS :not(:placeholder-shown) alone"
  - "Input listeners merged: single listener calls both setFieldError(field, false) and updateHasValue(field) — no duplicate listeners"
  - "Demo panel event delegation: single click handler on #demoPanel uses e.target.closest('.demo-fill-btn') — no per-button listeners"

patterns-established:
  - "DOM update pattern for floating labels: closest('.field-group') as the state container, CSS watches .has-value on that container"

requirements-completed: [REB-02, WIRE-01]

# Metrics
duration: 2min
completed: 2026-03-20
---

# Phase 44 Plan 02: Login JS Wire-Up Summary

**login.js rewired to new DOM: setFieldError targets .field-group via closest(), floating label .has-value toggling with autofill detection, showDemoHint populates #demoPanel instead of appending to card**

## Performance

- **Duration:** ~2 min
- **Started:** 2026-03-20T12:11:21Z
- **Completed:** 2026-03-20T12:13:00Z
- **Tasks:** 2 (1 auto + 1 checkpoint:human-verify — approved by user)
- **Files modified:** 1

## Accomplishments
- Updated `setFieldError()`: uses `field.closest('.field-group')` instead of old `.field-wrap` parent traversal — handles both email (direct child) and password (nested in `.field-input-wrap`)
- Added `updateHasValue()` function: toggles `.has-value` class on `.field-group` for password floating label support (CSS requires this because label is sibling of `.field-input-wrap`, not direct sibling of input)
- Merged input listeners: each field's listener now calls both `setFieldError(field, false)` and `updateHasValue(field)` in one handler
- Added `setTimeout(100ms)` autofill detection: calls `updateHasValue` on both fields after page load so browser-autofilled values float their labels
- Rewrote `showDemoHint()`: targets `document.getElementById('demoPanel')`, calls `removeAttribute('hidden')`, calls `updateHasValue()` on both fields after demo button auto-fill
- All auth logic preserved unchanged: `api('/api/v1/auth_login.php')`, `api('/api/v1/whoami.php')`, `redirectByRole()`, `isSafeRedirect()`, `forgotLink` handler, `roleLabel` object

## Task Commits

Each task was committed atomically:

1. **Task 1: Update login.js for new DOM structure, floating label support, and demo panel** - `19dfc38` (feat)
2. **Task 2: Browser verification of complete login page** - Checkpoint approved by user (no code commit — verification only)

## Files Created/Modified
- `public/assets/js/pages/login.js` - Updated: setFieldError to .field-group, updateHasValue added, showDemoHint to #demoPanel, merged input listeners, autofill setTimeout

## Decisions Made
- `updateHasValue()` applied to both email and password fields for consistency — email could rely on CSS `:not(:placeholder-shown)` alone but applying JS too costs nothing and keeps autofill handling consistent across fields
- Input listeners merged into single function call per field — avoids separate `addEventListener` calls that could execute in wrong order
- Demo panel click uses event delegation on the panel itself (`e.target.closest('.demo-fill-btn')`) — cleaner than per-button listeners and handles dynamically regenerated content

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Checkpoint approved — full login page (HTML + CSS + JS) visually and functionally confirmed working in browser
- login.html + login.css (Plan 01) + login.js (Plan 02) all complete; floating labels, auth flow, dark mode, demo panel all verified
- Phase 45 wizard rebuild can begin immediately

---
*Phase: 44-login-rebuild*
*Completed: 2026-03-20*
