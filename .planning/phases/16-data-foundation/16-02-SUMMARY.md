---
phase: 16-data-foundation
plan: 02
status: complete
started: "2026-03-16"
completed: "2026-03-16"
---

# Plan 16-02 Summary: Wizard Redirect + Hub Real Data

## What Was Built

Updated the wizard success flow to pass real member/resolution counts and replaced the hub's demo data fallback with real API data + error handling.

## Key Changes

### public/assets/js/pages/wizard.js
- Success toast now shows "Séance créée • N membres • M résolutions" using counts from 201 response
- Error handler extracts per-item validation details (422 responses) for specific error messages
- Draft cleared only after confirmed 201 success

### public/assets/js/pages/hub.js
- **Deleted** `DEMO_SESSION` and `DEMO_FILES` constants entirely
- `loadData()` fetches real session data from `wizard_status` API
- Missing session ID → redirects to dashboard with "Identifiant de séance manquant" toast
- `meeting_not_found` → redirects to dashboard with "Séance introuvable" toast
- API failure → 1 automatic retry, then error banner with "Réessayer" button via `showHubError()`
- Documents fallback changed from DEMO_FILES to empty array

## Verification

- `grep -c "DEMO_SESSION\|DEMO_FILES" public/assets/js/pages/hub.js` → 0
- Human-verified: wizard-to-hub flow works end-to-end with real data
- `./vendor/bin/phpunit --no-coverage` → 2843 tests, 5974 assertions, all green

## Commits

1. `aacb2a9` — feat(phase-16/02): wire wizard redirect with counts, hub real data + error handling

## Key Files

### Created
- `.planning/phases/16-data-foundation/16-02-SUMMARY.md`

### Modified
- `public/assets/js/pages/wizard.js` — success toast with counts, error details
- `public/assets/js/pages/hub.js` — real API data, no demo fallback, error handling

## Deviations

None.
