---
phase: 04-clarte-et-jargon
plan: 01
subsystem: ui
tags: [htmx, ux, jargon, accessibility, voter-facing]

# Dependency graph
requires: []
provides:
  - Voter-facing pages free of technical jargon (CLAR-01)
  - Checkbox-only confirmation modal replacing "tapez VALIDER" pattern (CLAR-03)
  - "Seuil de participation" label in public projection and decision card
  - Plain-French vote FAQ with codes de vote, empreinte numerique
affects: [04-02-clarte-et-jargon]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Checkbox-only modal confirmation gate — no text input required from voter"
    - "Role-guarded FAQ sections keep technical terms; unguarded sections use plain French"

key-files:
  created: []
  modified:
    - public/public.htmx.html
    - public/help.htmx.html
    - public/validate.htmx.html
    - public/assets/js/pages/validate.js

key-decisions:
  - "SHA256 references in admin-guarded security FAQ section left intact — only voter-visible (unguarded) sections simplified"
  - "empreinte numerique introduced in voter-visible general FAQ answer about audit security"
  - "confirmText JS listeners and variable fully removed — checkbox is the sole gate for modal confirmation"

patterns-established:
  - "Voter-visible FAQ sections: no SHA256, no token, no hash — use empreinte numerique, code de vote"
  - "Quorum label in voter-facing projection uses 'Seuil de participation' not 'Quorum'"

requirements-completed: [CLAR-01, CLAR-03]

# Metrics
duration: 3min
completed: 2026-04-21
---

# Phase 4 Plan 01: Clarte et Jargon Summary

**Voter-facing jargon eliminated: 'Quorum' replaced by 'Seuil de participation', token/SHA256 replaced by plain French, and modal confirmation simplified to checkbox-only**

## Performance

- **Duration:** 3 min
- **Started:** 2026-04-21T10:16:26Z
- **Completed:** 2026-04-21T10:18:57Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments

- Replaced both "Quorum" visible labels in public projection page with "Seuil de participation" (element IDs untouched)
- Rewrote voter-visible FAQ entries: "tokens de vote" → "codes de vote a usage unique", "token est consomme" → "code est utilise une seule fois", SHA256 → plain French with "empreinte numerique" explanation
- Removed the "tapez VALIDER" text input from the validation modal entirely — confirmation now relies solely on the confirmIrreversible checkbox
- Cleaned validate.js of all confirmText references (variable, input listener, keydown listener, reset in closeValidateModal)

## Task Commits

Each task was committed atomically:

1. **Task 1: Eliminate voter-facing jargon (CLAR-01)** - `f864abca` (feat)
2. **Task 2: Replace tapez VALIDER with checkbox confirmation (CLAR-03)** - `8f49d67d` (feat)

## Files Created/Modified

- `public/public.htmx.html` - Two "Quorum" visible labels replaced with "Seuil de participation"
- `public/help.htmx.html` - Voter-visible FAQ sections (general + vote) de-jargonized; security section left intact
- `public/validate.htmx.html` - confirmText form-group removed from validation modal
- `public/assets/js/pages/validate.js` - confirmText variable and all dependent listeners removed; updateModalConfirmState simplified to checkbox-only

## Decisions Made

- SHA256 references in the admin-guarded security FAQ section (data-required-role="admin,auditor,assessor") were left intact — only voter-visible unguarded sections were simplified, as specified by CLAR-01 scope
- "empreinte numerique" was introduced in the voter-visible general FAQ security answer to satisfy the artifact requirement; the phrase was not present in any voter-visible section before this plan
- The `confirmText` JS code was removed entirely rather than left as dead code — eliminates TypeError risk if HTML and JS ever diverged

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- CLAR-01 and CLAR-03 complete — voter-facing interface uses plain French throughout
- Ready for Phase 4 Plan 02 (CLAR-02: admin/operator tooltips for retained technical terms)

---
*Phase: 04-clarte-et-jargon*
*Completed: 2026-04-21*
