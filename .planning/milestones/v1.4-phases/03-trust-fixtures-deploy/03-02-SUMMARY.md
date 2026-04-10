---
phase: 03-trust-fixtures-deploy
plan: 02
subsystem: testing
tags: [playwright, e2e, rbac, auditor, trust]

# Dependency graph
requires:
  - phase: 03-01
    provides: loginAsAuditor and loginAsAssessor Playwright helpers in helpers.js
provides:
  - All trust-related E2E specs use loginAsAuditor instead of loginAsAdmin/loginAsOperator
  - Zero admin fallback patterns remaining in trust specs
affects: [trust-specs, e2e-auth]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Trust page E2E specs authenticate as auditor role (the designed target role) not admin fallback"

key-files:
  created: []
  modified:
    - tests/e2e/specs/critical-path-trust.spec.js
    - tests/e2e/specs/accessibility.spec.js
    - tests/e2e/specs/contrast-audit.spec.js

key-decisions:
  - "No behavioral changes needed -- auditor role has access to all trust API endpoints (trust_anomalies, trust_checks require auditor|admin|operator)"

patterns-established:
  - "Role-accurate E2E auth: specs authenticate with the role the page is designed for, not a superuser fallback"

requirements-completed: [TRUST-03]

# Metrics
duration: 2min
completed: 2026-04-10
---

# Phase 03 Plan 02: Trust Spec Migration Summary

**All trust E2E specs migrated from loginAsAdmin/loginAsOperator to loginAsAuditor -- zero admin fallback patterns remain**

## Performance

- **Duration:** 2 min
- **Started:** 2026-04-10T06:37:35Z
- **Completed:** 2026-04-10T06:39:13Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- critical-path-trust.spec.js migrated from loginAsOperator to loginAsAuditor (import, call, JSDoc comment)
- accessibility.spec.js trust entry migrated from loginAsAdmin to loginAsAuditor with import added
- contrast-audit.spec.js trust entry migrated from loginAsAdmin to loginAsAuditor with import added
- Admin fallback comment removed from accessibility.spec.js (no longer applicable)
- Non-trust entries (users.htmx.html) deliberately unchanged -- genuinely admin-only

## Task Commits

Each task was committed atomically:

1. **Task 1: Migrate critical-path-trust.spec.js to loginAsAuditor** - `10429a02` (feat)
2. **Task 2: Migrate accessibility and contrast-audit trust entries to loginAsAuditor** - `a49f0884` (feat)

## Files Created/Modified
- `tests/e2e/specs/critical-path-trust.spec.js` - Replaced loginAsOperator with loginAsAuditor in import, call, and JSDoc
- `tests/e2e/specs/accessibility.spec.js` - Added loginAsAuditor to import, replaced loginAsAdmin for trust entry
- `tests/e2e/specs/contrast-audit.spec.js` - Added loginAsAuditor to import, replaced loginAsAdmin for trust entry

## Decisions Made
None - followed plan as specified. Auditor role has access to all trust API endpoints, so no behavioral changes were needed.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Phase 03 (trust-fixtures-deploy) fully complete
- All trust specs now use correct auditor role authentication
- Ready for Phase 04 (HTMX 2.0 Upgrade)

---
*Phase: 03-trust-fixtures-deploy*
*Completed: 2026-04-10*

## Self-Check: PASSED
