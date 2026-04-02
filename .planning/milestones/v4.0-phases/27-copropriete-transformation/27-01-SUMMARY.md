---
phase: 27-copropriete-transformation
plan: 01
subsystem: ui
tags: [php, phpunit, javascript, html, css, vocabulary, refactoring]

# Dependency graph
requires: []
provides:
  - PHPUnit regression test for VoteEngine weighted vote correctness (CPR-05)
  - Vocabulary-neutral UI: zero copropriete-specific user-facing strings in core UI files
  - Dead code removed: lot field, openKeyModal, initDistributionKeys, Cles de repartition sections
  - Generic AG terminology across wizard.js, settings.js, settings.htmx.html, admin.htmx.html, help.htmx.html, index.html, shell.js
affects: [28-post-session-pv, 29-final-audit]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Regression test BEFORE vocabulary changes, verified AFTER — safety net pattern"
    - "value='tantiemes' preserved in select options for API compat while display text changes"

key-files:
  created:
    - tests/Unit/WeightedVoteRegressionTest.php
  modified:
    - public/assets/js/pages/wizard.js
    - public/assets/css/wizard.css
    - public/assets/js/pages/settings.js
    - public/settings.htmx.html
    - public/admin.htmx.html
    - public/help.htmx.html
    - public/index.html
    - public/assets/js/core/shell.js
    - app/Repository/AggregateReportRepository.php

key-decisions:
  - "VoteEngine::computeDecision uses >= threshold; tie test uses threshold 0.501 to model strict majority correctly"
  - "value='tantiemes' in select options preserved unchanged — API contract must not break"
  - "Copropriete transformation is vocabulary-only: voting_power, BallotsService, tantieme CSV alias all untouched"

patterns-established:
  - "Regression test pattern: write test against existing behavior first, then make changes, verify test still passes"

requirements-completed: [CPR-01, CPR-02, CPR-03, CPR-04, CPR-05]

# Metrics
duration: 5min
completed: 2026-03-18
---

# Phase 27 Plan 01: Copropriete Transformation Summary

**PHPUnit weighted-vote regression test (3 assertions) plus vocabulary-neutral UI across 9 core files — zero copropriete-specific user-facing strings remain**

## Performance

- **Duration:** ~5 min
- **Started:** 2026-03-18T15:13:48Z
- **Completed:** 2026-03-18T15:18:32Z
- **Tasks:** 2
- **Files modified:** 10 (1 created + 9 modified)

## Accomplishments
- PHPUnit regression test `WeightedVoteRegressionTest` verifies VoteEngine weighted tallies (POUR:3 vs CONTRE:1, unanimous, tie with strict threshold)
- Removed all copropriete-specific user-facing strings from wizard.js, settings.js, settings.htmx.html, admin.htmx.html, help.htmx.html, index.html, shell.js
- Removed dead code: lot field (display/CSV/prompt), openKeyModal function, initDistributionKeys function, Cles de repartition sections in both settings and admin pages
- Full PHPUnit suite passes: 2865 tests, 0 failures, 14 pre-existing skips

## Task Commits

Each task was committed atomically:

1. **Task 1: PHPUnit weighted-vote regression test** - `8514890` (test)
2. **Task 2: Vocabulary renames and dead code removal** - `57c5cf8` (feat)

**Plan metadata:** _(docs commit follows)_

## Files Created/Modified
- `tests/Unit/WeightedVoteRegressionTest.php` - 3 PHPUnit tests exercising VoteEngine::computeDecision with weighted inputs
- `public/assets/js/pages/wizard.js` - Removed lot field: member row display span, CSV column shift (r[1]=email, r[2]=voix), manual add prompt
- `public/assets/css/wizard.css` - Removed `.member-lot` CSS class
- `public/assets/js/pages/settings.js` - Removed initDistributionKeys() and openKeyModal() functions and call site
- `public/settings.htmx.html` - Changed 'Par tantièmes' to 'Par poids de vote', 3x copropriétaires to membres, removed Cles de repartition card, email template desc updated
- `public/admin.htmx.html` - Removed distribution-keys tab button and panel, 3x copropriétaires to membres, email template desc updated
- `public/help.htmx.html` - réunions de copropriété -> réunions d'organisation, tantièmes -> poids de vote, FAQ rewritten generically
- `public/index.html` - 'Pondération (tantièmes)' -> 'Pondération des voix'
- `public/assets/js/core/shell.js` - 'Annuaire des copropriétaires' -> 'Annuaire des membres'
- `app/Repository/AggregateReportRepository.php` - Comment: evolution des tantiemes -> poids de vote

## Decisions Made
- Used `threshold: 0.501` in tie test to model strict majority (the VoteEngine uses `>=` comparison, not `>`, so 0.5 at threshold 0.5 would be adopted)
- `value="tantiemes"` preserved in `<option>` elements — API contract unchanged
- Vocabulary transformation is surface-only: voting_power column, BallotsService, ImportService tantieme aliases all untouched

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Test 3 threshold adjusted from 0.5 to 0.501**
- **Found during:** Task 1 (WeightedVoteRegressionTest)
- **Issue:** Plan specified threshold 0.5 expecting tie NOT to be adopted. But VoteEngine uses `$majorityRatio >= $majorityThreshold` so 0.5 >= 0.5 = true (adopted). Plan's comment "threshold 0.5 means >50% needed" was incorrect — 0.5 means "at least 50%".
- **Fix:** Changed threshold to 0.501 to model strict majority. The test now correctly asserts that a 50% ratio does NOT meet a 50.1% threshold.
- **Files modified:** tests/Unit/WeightedVoteRegressionTest.php
- **Verification:** All 3 tests pass; behavior accurately reflects VoteEngine semantics
- **Committed in:** 8514890 (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (1 bug in test specification)
**Impact on plan:** Essential correctness fix for the regression test. No scope creep.

## Issues Encountered
- VoteEngine `>=` threshold semantics differed from plan's comment description. Adjusted test threshold to 0.501 for strict majority modeling.

## Next Phase Readiness
- All CPR requirements (CPR-01 through CPR-05) complete
- Application vocabulary is now generic — no copropriete-specific terms in user-facing UI
- Regression test in place to catch any future regressions in weighted vote logic
- Ready for Phase 28 (post-session PV) or Phase 29 (final audit)

---
*Phase: 27-copropriete-transformation*
*Completed: 2026-03-18*
