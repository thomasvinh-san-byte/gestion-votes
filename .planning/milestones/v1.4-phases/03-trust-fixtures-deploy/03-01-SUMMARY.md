---
phase: 03-trust-fixtures-deploy
plan: 01
subsystem: testing
tags: [playwright, e2e, rbac, php, fixtures, auth]

# Dependency graph
requires: []
provides:
  - seedUser API endpoint (POST /api/v1/test/seed-user) with route-level production gate
  - loginAsAuditor() and loginAsAssessor() Playwright helpers
  - Dedicated assessor E2E user with meeting_roles assignment
affects: [03-02, trust-specs, e2e-auth]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Route-level production gate: wrap dev-only routes in if (!in_array($appEnv, ['production', 'prod'])) block in routes.php"
    - "Seed endpoint: no auth middleware, env gate is security boundary for bootstrapping"

key-files:
  created: []
  modified:
    - app/Controller/DevSeedController.php
    - app/routes.php
    - tests/Unit/DevSeedControllerTest.php
    - tests/e2e/helpers.js
    - tests/e2e/setup/auth.setup.js
    - database/seeds/04_e2e.sql

key-decisions:
  - "Route-level env gate wraps all dev seed routes (not just seed-user) for defense-in-depth"
  - "seedUser endpoint has no auth middleware — route-level env gate is sufficient for bootstrapping"
  - "Assessor user added to SQL seed (04_e2e.sql) for database-level reliability rather than API-only creation"
  - "findByEmail uses tenantId parameter (not findByEmailGlobal) since seed endpoint runs with auth context"

patterns-established:
  - "Route-level production gate: conditional route registration in routes.php based on config('env')"
  - "Meeting role fixture: viewer system role + meeting_roles(assessor) assignment on E2E meeting"

requirements-completed: [TRUST-01, TRUST-02]

# Metrics
duration: 4min
completed: 2026-04-10
---

# Phase 03 Plan 01: Trust Fixtures Deploy Summary

**seedUser endpoint with route-level production gate plus Playwright loginAsAuditor/loginAsAssessor helpers and dedicated assessor E2E user**

## Performance

- **Duration:** 4 min
- **Started:** 2026-04-10T06:31:16Z
- **Completed:** 2026-04-10T06:35:03Z
- **Tasks:** 2
- **Files modified:** 6

## Accomplishments
- seedUser endpoint in DevSeedController creates users with optional meeting role assignment, with duplicate-email upsert handling
- All dev seed routes wrapped in route-level production gate (404 in production, defense-in-depth with existing guardProduction)
- loginAsAuditor and loginAsAssessor helpers exported from helpers.js with credentials in CREDENTIALS object
- Dedicated assessor-e2e user in 04_e2e.sql with viewer system role and assessor meeting role on E2E meeting
- 5 new unit tests: GET rejection, validation, success path, meeting role assignment, route-level gate source verification

## Task Commits

Each task was committed atomically:

1. **Task 1: Add seedUser endpoint with route-level production gate** - `93d37f23` (feat)
2. **Task 2: Add Playwright auth fixtures for auditor and assessor** - `aba2de5a` (feat)

## Files Created/Modified
- `app/Controller/DevSeedController.php` - Added seedUser() method with POST parsing, bcrypt hashing, duplicate handling, optional meeting role
- `app/routes.php` - Wrapped dev seed routes in production env gate, added test/seed-user route
- `tests/Unit/DevSeedControllerTest.php` - 5 new tests for seedUser endpoint validation, success paths, and route gate
- `tests/e2e/helpers.js` - Added auditor/assessor credentials, loginAsAuditor/loginAsAssessor functions and exports
- `tests/e2e/setup/auth.setup.js` - Added auditor and assessor to ACCOUNTS array, updated rate limit comment
- `database/seeds/04_e2e.sql` - Added assessor-e2e user (viewer role) with meeting_roles(assessor) on E2E meeting

## Decisions Made
- Route-level env gate wraps ALL dev seed routes (including existing seedMembers/seedAttendances) rather than only seed-user, providing consistent defense-in-depth
- seedUser endpoint uses no auth middleware since it bootstraps test users before any login exists; route-level env gate is the security boundary
- Assessor added to SQL seed for database-level reliability (hybrid approach recommended by research)
- Used findByEmail with tenantId (not findByEmailGlobal) since the endpoint runs within an authenticated tenant context

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed void method mock return values**
- **Found during:** Task 1 (unit tests)
- **Issue:** PHPUnit 10.5 rejects `willReturn(null)` on void methods (IncompatibleReturnValueException)
- **Fix:** Changed to `expects($this->once())->method('createUser')` without willReturn
- **Files modified:** tests/Unit/DevSeedControllerTest.php
- **Verification:** All 19 tests pass
- **Committed in:** 93d37f23

**2. [Rule 1 - Bug] Fixed regex pattern for route gate test**
- **Found during:** Task 1 (unit tests)
- **Issue:** Regex `/if\s*\(\s*!in_array\s*\(\s*\$appEnv.*production.*\).*seed-user/s` didn't match because `{` brace between gate and route wasn't in pattern
- **Fix:** Added `\{.*?` between production gate and seed-user in regex
- **Files modified:** tests/Unit/DevSeedControllerTest.php
- **Verification:** testRouteLevelProductionGateExists passes
- **Committed in:** 93d37f23

---

**Total deviations:** 2 auto-fixed (2 bugs)
**Impact on plan:** Both auto-fixes necessary for test correctness. No scope creep.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- loginAsAuditor and loginAsAssessor helpers ready for trust spec migration (Plan 03-02)
- seedUser endpoint available for programmatic user creation in E2E tests
- Assessor user seeded in database with meeting role, ready for trust.htmx.html access tests

---
*Phase: 03-trust-fixtures-deploy*
*Completed: 2026-04-10*
