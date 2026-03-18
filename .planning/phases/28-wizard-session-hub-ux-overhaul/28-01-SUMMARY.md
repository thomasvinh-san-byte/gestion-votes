---
phase: 28-wizard-session-hub-ux-overhaul
plan: "01"
subsystem: ui
tags: [wizard, forms, progressive-disclosure, vanilla-js, iife, localstorage, step-navigation]

requires:
  - phase: 27-copropriete-transformation
    provides: "Vocabulary-clean wizard HTML/JS (resoKey field identified as copropriété remnant to remove)"

provides:
  - "Named stepper labels: Informations / Membres / Résolutions / Révision"
  - "Optional steps 2 and 3 — no blocking validation guards"
  - "Step 4 sectioned review card (buildReviewCard) with Modifier back-navigation links"
  - "3 hardcoded motion templates with quick-select buttons (MOTION_TEMPLATES)"
  - "Progressive disclosure toggle for voting power (toggleVotingPower)"
  - "Inline member add form replacing window.prompt()"
  - "votingPowerEnabled persisted in localStorage draft"

affects:
  - "28-02-PLAN.md (hub enhancements — same phase)"
  - "Any future plan touching wizard.htmx.html or wizard.js"

tech-stack:
  added: []
  patterns:
    - "MOTION_TEMPLATES array of objects — hardcoded; filter by id for applyTemplate()"
    - "toggleVotingPower(show) pattern — syncs toggle checkbox + DOM element visibility"
    - "buildReviewCard() — sectioned card with Modifier buttons wired via querySelectorAll after innerHTML set"
    - "Inline form replace for window.prompt() — btnAddMemberInline reads named inputs, clears on submit"

key-files:
  created: []
  modified:
    - public/wizard.htmx.html
    - public/assets/js/pages/wizard.js

key-decisions:
  - "Steps 2 (Membres) and 3 (Résolutions) are optional — validateStep n=1 and n=2 return true; step 4 review shows warnings but does not block creation"
  - "resoKey (Clé de répartition) select removed from wizard step 3 — copropriété-specific vocabulary; key hard-coded to 'Charges générales' in setupAddReso()"
  - "window.prompt() for manual member add replaced by inline form (wizMemberAddForm) to support progressive disclosure of voting_power field"
  - "btnDownloadPdf removed from step 4 — window.print() not relevant to review step"
  - "MOTION_TEMPLATES are hardcoded JS objects (not DB-stored) — v5+ deferred per plan"

patterns-established:
  - "Progressive disclosure: show/hide via style.display toggle + checkbox sync in toggleVotingPower()"
  - "Review card Modifier links: data-goto attribute on buttons, wired after innerHTML assignment via querySelectorAll"

requirements-completed: [WIZ-01, WIZ-02, WIZ-03, WIZ-04, WIZ-05]

duration: 5min
completed: 2026-03-18
---

# Phase 28 Plan 01: Wizard Session Hub UX Overhaul Summary

**Named stepper (Informations/Membres/Résolutions/Révision), optional steps 2/3, sectioned review card with Modifier links, 3 motion templates, progressive voting power toggle, and inline member form replacing window.prompt()**

## Performance

- **Duration:** ~5 min
- **Started:** 2026-03-18T16:44:38Z
- **Completed:** 2026-03-18T16:49:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- Wizard stepper now shows named labels matching locked decisions exactly (Informations, Membres, Résolutions, Révision)
- Steps 2 and 3 are fully optional — `validateStep()` returns `true` for n=1 and n=2; step 4 shows contextual warnings without blocking
- `buildReviewCard()` replaces `buildRecap()` — 4 sectioned card (Informations, Membres, Résolutions, Documents) each with "Modifier" button that calls `showStep(n)`
- `MOTION_TEMPLATES` array + `applyTemplate()` function wired to 3 quick-select buttons above the reso-add-panel
- `toggleVotingPower(show)` progressively reveals voting power input and `.member-votes` column; state saved to localStorage draft as `votingPowerEnabled`
- Inline member form (`wizMemberAddForm`) replaces `window.prompt()` — name, email, optional voting power input

## Task Commits

1. **Task 1: Wizard HTML restructure** — `b5811e3` (feat)
2. **Task 2: Wizard JS overhaul** — `7727a36` (feat)

## Files Created/Modified

- `/home/user/gestion_votes_php/public/wizard.htmx.html` — Stepper labels updated, inline member form added, voting power toggle added, CSV drop zone text cleaned, motion template buttons added, resoKey removed, step 4 title changed to Révision, btnDownloadPdf removed, btnCreate updated to btn-primary with arrow text, ctx-panel Guide removed from step 4
- `/home/user/gestion_votes_php/public/assets/js/pages/wizard.js` — MOTION_TEMPLATES, applyTemplate(), toggleVotingPower(), buildReviewCard() (replaces buildRecap()), relaxed validateStep() for n=1/n=2, removed showFieldErrors/clearFieldErrors blocks for n=1/n=2, inline form handler, template button wiring, toggle wiring, votingPowerEnabled in saveDraft/restoreDraft, resoKey removed from setupAddReso()

## Decisions Made

- **resoKey removed** — copropriété vocabulary ("Clé de répartition") has no meaning in general assembly context; `key` field in resolution objects hard-coded to `'Charges générales'` since the API appears to accept it as a string
- **window.prompt() eliminated** — inline form is required to support progressive disclosure of the voting power field (prompt() cannot conditionally show/hide a field)
- **ctx-panel Guide removed from step 4** — redundant now that the review card itself provides clear context; reduces visual noise

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Updated btnCreate text handling in create flow**
- **Found during:** Task 2 (reviewing create button error recovery)
- **Issue:** btnCreate in JS still set innerHTML with SVG checkmark on loading/error states; button now has no SVG in HTML (changed to text-only)
- **Fix:** Changed `btnCreate.innerHTML = '<svg>...' + 'Création…'` to `btnCreate.textContent = 'Création…'` and error recovery to `'Créer la séance →'`
- **Files modified:** public/assets/js/pages/wizard.js
- **Verification:** grep confirms no SVG in btnCreate handler
- **Committed in:** 7727a36 (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 - bug in button text recovery)
**Impact on plan:** Minor correctness fix — button text now matches new HTML. No scope creep.

## Issues Encountered

None — both tasks executed cleanly. All acceptance criteria met on first attempt.

## Next Phase Readiness

- Wizard functional overhaul complete (WIZ-01 through WIZ-05)
- Plan 28-02 (hub enhancements: WIZ-06, WIZ-07, WIZ-08) can proceed independently
- No blocking issues

## Self-Check: PASSED

All files exist, all commits verified.

---
*Phase: 28-wizard-session-hub-ux-overhaul*
*Completed: 2026-03-18*
