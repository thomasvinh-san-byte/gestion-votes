---
phase: 81-fix-ux-interactivity-blocking-popups-broken-layouts-fragile-frontend-wiring
plan: "03"
subsystem: ui
tags: [javascript, toast, feedback, ux, AgToast, setNotif, btnLoading]

# Dependency graph
requires:
  - phase: 81-02
    provides: modal/popup fixes that this plan's feedback improvements depend on

provides:
  - "users.js uses global setNotif (no local override) — AgToast feedback via utils.js delegation"
  - "operator-tabs.js AgToast.show(type, message) argument order corrected throughout"
  - "settings.js all 26 AgToast.show calls fixed to correct (type, message) argument order"
  - "settings.js section save buttons use Shared.btnLoading for double-submit prevention"
  - "wizard.js uses Shared.btnLoading on btnCreate, error feedback via AgToast.show directly"
  - "hub.js uses direct AgToast.show calls (no window.AgToast conditional guards)"
  - "No silent API failures in audited pages — all catch blocks show user-visible error toast"

affects: [operator, wizard, hub, settings, users, members]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "AgToast.show(type, message, duration?) — type is ALWAYS first argument"
    - "Shared.btnLoading(btn, true/false) for all user-initiated action buttons"
    - "No window.AgToast conditional guards — AgToast is always loaded, use directly"
    - "Global setNotif() from utils.js delegates to AgToast — no local overrides in page JS"

key-files:
  created: []
  modified:
    - public/assets/js/pages/users.js
    - public/assets/js/pages/operator-tabs.js
    - public/assets/js/pages/settings.js
    - public/assets/js/pages/wizard.js
    - public/assets/js/pages/hub.js

key-decisions:
  - "settings.js had all 26 AgToast.show calls with reversed (message, type) args — fixed all to (type, message)"
  - "saveSection() now accepts triggerBtn param for proper btnLoading lifecycle via Promise.all finally()"
  - "wizard btnCreate uses Shared.btnLoading instead of manual disabled/textContent manipulation"

patterns-established:
  - "Pattern A (create/delete): Shared.btnLoading(btn,true) -> try API -> toast -> finally Shared.btnLoading(btn,false)"
  - "Pattern B (data loads): silent catch with UI fallback (no toast needed for background refreshes)"

requirements-completed: [D-08, D-09, D-10]

# Metrics
duration: 25min
completed: 2026-04-03
---

# Phase 81 Plan 03: Toast Notification Wiring & Double-Submit Prevention Summary

**Fixed AgToast argument order bugs across all key pages, removed local setNotif override in users.js, and wired Shared.btnLoading for double-submit prevention on settings section saves and wizard creation.**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-04-03
- **Completed:** 2026-04-03
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments

- Removed local `setNotif()` override in users.js — all calls now fall through to global `setNotif()` in utils.js which delegates to `AgToast.show()`
- Fixed reversed `AgToast.show(message, type)` → `AgToast.show(type, message)` in operator-tabs.js (4 calls) and settings.js (26 calls)
- Removed all `window.AgToast ? window.AgToast.show(...) : setNotif(...)` ternary guards — replaced with direct `AgToast.show()` calls
- Added `Shared.btnLoading` to settings.js section save buttons and wizard btnCreate for double-submit prevention
- Added error feedback to `saveSection()` Promise.all failure path (was silently catching all errors)

## Task Commits

1. **Task 1: Fix toast wiring bugs** — `e721da8c` (fix)
2. **Task 2: Audit and wire missing feedback** — `de08d636` (fix)

## Files Created/Modified

- `public/assets/js/pages/users.js` — Removed local setNotif() override (lines 27-39), global version used
- `public/assets/js/pages/operator-tabs.js` — Fixed reversed AgToast args, removed window.AgToast ternary guards
- `public/assets/js/pages/settings.js` — Fixed all 26 AgToast arg order bugs, added btnLoading to section saves
- `public/assets/js/pages/wizard.js` — Replaced manual disabled/textContent pattern with Shared.btnLoading, fixed window.AgToast guards
- `public/assets/js/pages/hub.js` — Replaced window.AgToast conditional guards with direct AgToast.show() calls

## Decisions Made

- settings.js had a systematic bug: every single AgToast.show call used `(message, type)` order. Fixed all 26 calls — this was a Rule 1 auto-fix (bug causing wrong toast behavior: type shown as message text, message shown as type).
- `saveSection()` refactored to accept a `triggerBtn` parameter so btnLoading state is properly restored in `Promise.all().finally()` without a fragile setTimeout.
- wizard.js: replaced manual `btnCreate.disabled = true; btnCreate.textContent = '...'` pattern with `Shared.btnLoading(btnCreate, true, 'Création…')` for consistency.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] settings.js had all 26 AgToast.show calls with reversed argument order**
- **Found during:** Task 2 (Audit and wire missing error/loading feedback)
- **Issue:** Every `AgToast.show` call in settings.js used `(message, type)` order instead of the correct `(type, message)` — toast type was being displayed as message text, French message as the type string
- **Fix:** Fixed all 26 calls to use `AgToast.show(type, message)` correct order
- **Files modified:** public/assets/js/pages/settings.js
- **Verification:** `grep -n "AgToast.show" settings.js` shows all calls start with type string ('success', 'error', 'info', 'warning')
- **Committed in:** de08d636 (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 - bug)
**Impact on plan:** Essential fix — settings.js was silently broken for all toast notifications. No scope creep.

## Issues Encountered

None — both tasks completed cleanly. The settings.js reversed-args bug was discovered during the audit phase of Task 2 and fixed inline.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- Toast notification wiring is now standardized across all key pages
- AgToast.show(type, message) is the established pattern — operator-tabs, settings, wizard, hub, users all consistent
- Plan 81-04 can proceed with remaining UX fixes

---
*Phase: 81-fix-ux-interactivity-blocking-popups-broken-layouts-fragile-frontend-wiring*
*Completed: 2026-04-03*
