---
phase: 27-copropriete-transformation
plan: 02
subsystem: ui
tags: [vocabulary, seed-data, documentation, wireframe, terminology]

# Dependency graph
requires:
  - phase: 27-01
    provides: PHP/JS/CSS/HTML vocabulary transformation for copropriete → generic AG terms
provides:
  - Seed SQL comments use generic terminology (poids de vote, not tantiemes/copropriete)
  - Documentation files use generic AG vocabulary throughout
  - Wireframe files (docs/wireframe/ and root copy) fully cleaned of copropriete-specific language
  - Public HTML files (wizard, postsession) use generic vocabulary
  - Full codebase audit confirming zero copropri matches outside preserved backend aliases
affects: [all future phases, documentation, onboarding]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Generic AG vocabulary enforced: membres (not coproprietaires), poids de vote (not tantiemes), organisation (not copropriete)"
    - "Backend ImportService.php tantieme CSV aliases preserved per CPR-04"
    - "LOT-xxx member_reference data values preserved as historical seed data"

key-files:
  created: []
  modified:
    - database/seeds/06_test_weighted.sql
    - database/seeds/03_demo.sql
    - database/seeds/08_demo_az.sql
    - docs/FAQ.md
    - docs/GUIDE_FONCTIONNEL.md
    - docs/directive-projet.md
    - docs/wireframe/ag_vote_v3_19_2.html
    - public/wizard.htmx.html
    - public/postsession.htmx.html
    - tests/Integration/WorkflowValidationTest.php
    - ag_vote_wireframe.html

key-decisions:
  - "Root-level ag_vote_wireframe.html is a stale copy of docs/wireframe — applied identical vocabulary updates to keep both in sync"
  - "Test fixture description in WorkflowValidationTest.php updated from 'copropietaires' to 'membres' — only string content, no logic impact"
  - "public/wizard.htmx.html and postsession.htmx.html were user-facing HTML not covered in plan but auto-fixed per Rule 2 (missing critical consistency)"

patterns-established:
  - "Vocabulary audit command: grep -rni copropri --include=*.php/js/css/html/sql/md | grep -v vendor/ | grep -v ImportService.php | grep -v LOT-"

requirements-completed: [CPR-01, CPR-04]

# Metrics
duration: 15min
completed: 2026-03-18
---

# Phase 27 Plan 02: Seed Data and Documentation Vocabulary Summary

**Zero copropriete-specific language remains across 11 files — seed comments, docs, wireframes, and public HTML all converted to generic AG vocabulary with 2865 PHPUnit tests passing**

## Performance

- **Duration:** 15 min
- **Started:** 2026-03-18T15:20:00Z
- **Completed:** 2026-03-18T15:35:00Z
- **Tasks:** 2
- **Files modified:** 11

## Accomplishments

- 4 seed SQL files cleaned: comments updated from "tantiemes/copropriete" to "poids de vote/membres"
- 4 documentation files updated: FAQ, GUIDE_FONCTIONNEL, directive-projet (3 sections), wireframe
- 3 additional files auto-fixed during audit: public/wizard.htmx.html, public/postsession.htmx.html, ag_vote_wireframe.html (root copy)
- Full codebase audit confirmed: 0 copropri matches outside approved preserved locations (ImportService.php CSV aliases, LOT- data values)
- All 2865 PHPUnit tests pass, 0 failures, 0 errors

## Task Commits

Each task was committed atomically:

1. **Task 1: Update seed data comments and documentation vocabulary** - `a2a3cf1` (feat)
2. **Task 2: Full codebase audit and final regression verification** - `8f6bc93` (feat)

## Files Created/Modified

- `database/seeds/06_test_weighted.sql` - Header comment: "Copro 100 membres avec tantièmes" → "100 membres avec poids de vote"
- `database/seeds/03_demo.sql` - Comment cleaned, 2 motion descriptions updated (syndic de copropriete, coproprietaires)
- `database/seeds/08_demo_az.sql` - 2 comments: "tantiemes" → "poids de vote"
- `docs/FAQ.md` - "réunions de copropriété" → "réunions d'organisation"
- `docs/GUIDE_FONCTIONNEL.md` - "copropriétaires âgés" → "membres âgés"
- `docs/directive-projet.md` - Domain description, majority rules table (3 rows), multi-organisations row
- `docs/wireframe/ag_vote_v3_19_2.html` - ~12 copropriete/tantieme/Copropriétaire occurrences replaced
- `public/wizard.htmx.html` - Quorum option label and wizard guide text (auto-fix)
- `public/postsession.htmx.html` - pvReserves placeholder (auto-fix)
- `tests/Integration/WorkflowValidationTest.php` - Test fixture description (auto-fix)
- `ag_vote_wireframe.html` - Root-level stale copy aligned with docs/wireframe (auto-fix)

## Decisions Made

- Root-level `ag_vote_wireframe.html` is a stale copy of `docs/wireframe/ag_vote_v3_19_2.html`. Applied identical vocabulary updates to keep both files consistent. Future: consider removing the root copy or symlinking.
- Test fixture description in `WorkflowValidationTest.php` updated — pure string content, no impact on test assertions.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Vocabulary fixes in public HTML files not in plan scope**
- **Found during:** Task 2 (full codebase audit)
- **Issue:** `public/wizard.htmx.html` and `public/postsession.htmx.html` contained copropriete vocabulary in user-visible text
- **Fix:** Updated quorum option label, wizard guide text, and pvReserves placeholder
- **Files modified:** public/wizard.htmx.html, public/postsession.htmx.html
- **Verification:** Final grep audit returned 0 matches for copropri in public/ directory
- **Committed in:** 8f6bc93 (Task 2 commit)

**2. [Rule 2 - Missing Critical] Root-level wireframe stale copy not in plan scope**
- **Found during:** Task 2 (full codebase audit)
- **Issue:** `ag_vote_wireframe.html` at project root is a stale copy of the docs wireframe, retained all pre-transformation vocabulary
- **Fix:** Applied identical vocabulary replacements as docs/wireframe (AG Copropriete → AG B, copropriétaires → membres, etc.)
- **Files modified:** ag_vote_wireframe.html
- **Verification:** grep -ni copropri ag_vote_wireframe.html returns 0 matches
- **Committed in:** 8f6bc93 (Task 2 commit)

**3. [Rule 2 - Missing Critical] Test fixture description contained copropriete vocabulary**
- **Found during:** Task 2 (full codebase audit)
- **Issue:** `tests/Integration/WorkflowValidationTest.php` line 86 used "AG annuelle des copropriétaires" as fixture description
- **Fix:** Updated to "AG annuelle des membres"
- **Files modified:** tests/Integration/WorkflowValidationTest.php
- **Verification:** PHPUnit suite passes 2865/2865
- **Committed in:** 8f6bc93 (Task 2 commit)

---

**Total deviations:** 3 auto-fixed (all Rule 2 — missing critical vocabulary consistency)
**Impact on plan:** All auto-fixes required by the plan's acceptance criterion (zero copropri in codebase audit). No scope creep.

## Issues Encountered

- `npm run lint:ci` fails with ESLint configuration error ("Unexpected top-level property ignorePatterns") — this is a pre-existing environment incompatibility unrelated to this plan's changes. Noted in deferred items.

## Next Phase Readiness

- Phase 27 transformation is complete: zero user-facing copropriete vocabulary across entire codebase
- Backend tantieme CSV aliases preserved in ImportService.php (CPR-04 satisfied)
- LOT-xxx member_reference data values preserved in seed SQL (historical data integrity)
- Ready for next v4.0 phase

---
*Phase: 27-copropriete-transformation*
*Completed: 2026-03-18*
