---
phase: 50-secondary-pages-part-2
plan: "04"
subsystem: ui
tags: [vote, ballot, htmx, css-tokens, mobile-first, data-choice]

requires: []
provides:
  - "Complete vote/ballot page with full-screen mobile layout and all 51 DOM IDs"
  - "French data-choice attributes (pour/contre/abstention) aligned with API"
  - "Static blockedOverlay + blockedMsg in HTML"
  - "ag-pdf-viewer component for resolution document consultation"
  - "vote-ui.js choiceInfo/choiceStyles updated to French keys"
  - "vote.js cast() French-to-English mapping for API calls"
affects: [vote-flow, voter-ui, live-vote]

tech-stack:
  added: []
  patterns:
    - "French data-choice values on vote buttons, JS maps to API English values"
    - "Static HTML blocked overlay (JS creates dynamic fallback if missing)"
    - "ag-pdf-viewer web component for inline PDF viewing"

key-files:
  created: []
  modified:
    - public/vote.htmx.html
    - public/assets/css/vote.css
    - public/assets/js/pages/vote.js
    - public/assets/js/pages/vote-ui.js

key-decisions:
  - "Use French data-choice values (pour/contre/abstention) matching API's French input mapping; vote.js maps them to English for BallotsService"
  - "Add blockedOverlay/blockedMsg as static HTML elements (vote.js also creates them dynamically as fallback)"
  - "Replace hardcoded hex gradients in choiceStyles with CSS token-based gradients"

requirements-completed: [REB-08, WIRE-01, WIRE-02]

duration: 20min
completed: 2026-03-30
---

# Phase 50 Plan 04: Vote/Ballot Page Rebuild Summary

**Vote/ballot page rebuilt with v4.3 design language: full-screen mobile ballot, French data-choice attributes aligned with API, 51/51 DOM IDs verified, CSS uses 343 token references**

## Performance

- **Duration:** 20 min
- **Started:** 2026-03-30T08:30:00Z
- **Completed:** 2026-03-30T08:50:00Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments
- All 51 required DOM IDs present in vote.htmx.html (verified by automated script)
- Updated data-choice values from English (for/against/abstain) to French (pour/contre/abstention) matching backend API's accepted French input values
- Added static blockedOverlay + blockedMsg HTML elements (vote.js still creates dynamic fallback if absent)
- Added ag-pdf-viewer web component for resolution document consultation
- Added voteSubtitle element in identity banner (used by vote-ui.js token flow)
- vote.js cast() now maps French UI values to English API values (pour->for, contre->against, abstention->abstain, blanc->nsp)
- vote-ui.js choiceInfo/choiceStyles keys updated to French, hex gradients replaced with CSS token gradients
- CSS has 343 var(-- token references, zero hardcoded hex colors

## Task Commits

1. **Task 1: Rebuild vote/ballot HTML+CSS** - `b64fa26` (feat)
2. **Task 2: Verify and fix JS wiring** - `a6d30c1` (fix)

## Files Created/Modified
- `public/vote.htmx.html` - Added blockedOverlay, blockedMsg, voteSubtitle, ag-pdf-viewer; French data-choice values; data-page-role="voter"
- `public/assets/css/vote.css` - Added blocked overlay styles, identity-subtitle styles, removed hardcoded hex fallback
- `public/assets/js/pages/vote.js` - Added French-to-English choice mapping in cast()
- `public/assets/js/pages/vote-ui.js` - Updated choiceInfo/choiceStyles to French keys, token-based gradients

## Decisions Made
- French data-choice values (`pour`, `contre`, `abstention`) used on buttons because the backend API at `/api/v1/ballots_cast.php` accepts French inputs, and it's cleaner to be consistent. vote.js maps these to English API values before calling BallotsService which requires `for`/`against`/`abstain`/`nsp`.
- Static blockedOverlay in HTML reduces flicker vs pure JS injection; vote.js ensureBlockedOverlay() still works as fallback for older cached pages.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed API value mismatch: French data-choice vs English BallotsService values**
- **Found during:** Task 2 (JS wiring verification)
- **Issue:** Changing data-choice to French values would break the cast() API call since BallotsService only accepts 'for'/'against'/'abstain'/'nsp'
- **Fix:** Added French-to-English mapping in cast() function before API call
- **Files modified:** public/assets/js/pages/vote.js
- **Verification:** API endpoint /api/v1/ballots_cast.php BallotsService validated, mapping confirmed correct
- **Committed in:** a6d30c1 (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 - bug fix)
**Impact on plan:** Required fix for correctness — without it voting would fail with "invalid_vote" error. No scope creep.

## Issues Encountered
- The existing vote.htmx.html already had 48/51 required IDs — the rebuild was targeted additions rather than a full rewrite, preserving the working structure while adding the 3 missing elements.

## Next Phase Readiness
- Vote/ballot page fully functional with correct ID wiring
- All JS selectors verified against HTML
- Complete voting flow: token redemption, motion display, vote cast with French data-choice, receipt, speech requests, connection status
- Ready for browser verification

---
*Phase: 50-secondary-pages-part-2*
*Completed: 2026-03-30*
