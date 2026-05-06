# Phase 9: Tests E2E par Role - Context

**Gathered:** 2026-04-08
**Status:** Ready for planning
**Mode:** Auto-generated (infrastructure phase - test code, no UI decisions needed)

<domain>
## Phase Boundary

4 specs Playwright qui couvrent le chemin critique de chaque role (admin, operator, president, votant). Chaque spec execute un parcours complet end-to-end, est re-runnable avec unique IDs, et valide les points de passage critiques par role.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices at Claude's discretion.

### CRITICAL PREREQUISITE discovered in Phase 8 baseline
Cookie domain bug in tests/e2e/setup/auth.setup.js: cookies are written with domain='localhost'
but tests in container hit http://app:8080, so sessions are not sent.
Symptom: ERR_SSL_PROTOCOL_ERROR or redirects to /login.html on page.goto.

THIS MUST BE FIXED IN PHASE 9 PLAN 01 (not Phase 11) because:
- Phase 11 FIX-01 comes AFTER Phase 10 UAT
- Phase 9 tests cannot pass until the cookie domain bug is fixed
- Writing failing tests then fixing them 2 phases later makes no sense

Approach: Make auth.setup.js use HOST env var (default localhost, or use the same IN_DOCKER
env var used in playwright.config.js baseURL logic to determine the right cookie domain).

### Implementation Approach per Role

Each spec follows the same skeleton:
1. Login via cookie injection (loginAsX helper) or fresh login form
2. Navigate through critical path steps using real selectors from existing HTML
3. Each step asserts an observable DOM change (element visible, URL change, badge text)
4. Use unique runId timestamp suffix for re-runnability (e.g., meeting title, member emails)
5. Clean up where simple (API DELETE), otherwise rely on unique IDs

### Role-Specific Critical Paths

**E2E-01 Admin:** login - /settings - change a non-destructive setting - /users - view users list - /audit - view audit events - logout

**E2E-02 Operator:** login - /meetings - create new meeting via POST /api/v1/meetings (hybrid) - /members - add 2-3 members - /operator?meeting_id - open session - launch a vote - close vote - view results

**E2E-03 President:** login - /hub active meeting - open a vote on a motion - modify quorum settings (if allowed) - close vote

**E2E-04 Votant:** uses vote token flow, no session - navigate to /vote/TOKEN - select option - submit - assert confirmation screen

</decisions>

<code_context>
## Existing Code Insights

### Infrastructure (Phase 8)
- tests/e2e/playwright.config.js - conditional baseURL on IN_DOCKER env var
- bin/test-e2e.sh - wrapper script executing in container
- docker-compose.yml - tests service with profiles:[test]

### Existing helpers
- tests/e2e/helpers.js - loginAsOperator, loginAsAdmin, loginAsPresident, loginAsVoter
- tests/e2e/setup/auth.setup.js - global auth setup (NEEDS FIX for cookie domain)
- tests/e2e/.auth/*.json - saved sessions per role
- tests/e2e/helpers/waitForHtmxSettled.js - HTMX settle helper (Phase 5)

### Existing reference specs
- tests/e2e/specs/workflow-meeting.spec.js - covers parts of operator workflow
- tests/e2e/specs/operator.spec.js - operator-specific actions
- tests/e2e/specs/vote.spec.js - vote page interactions
- tests/e2e/specs/operator-e2e.spec.js - Phase 7 hybrid API+UI operator test
- tests/e2e/specs/page-interactions.spec.js - Phase 7 per-page interaction tests

### HTML pages to inspect for selectors
- public/settings.htmx.html (admin)
- public/users.htmx.html (admin)
- public/audit.htmx.html (admin)
- public/operator.htmx.html (operator)
- public/meetings.htmx.html (operator)
- public/members.htmx.html (operator)
- public/hub.htmx.html (president)
- public/vote.htmx.html (votant)

### API endpoints to use (hybrid API+UI strategy proven in Phase 7)
- POST /api/v1/auth_login (form auth)
- POST /api/v1/meetings (create meeting)
- POST /api/v1/members (add member)
- POST /api/v1/motions (create motion)
- POST /api/v1/votes (cast vote via token)
- GET /api/v1/meetings (list)
- GET /api/v1/csrf_token.php (CSRF for state-changing requests)

</code_context>

<specifics>
## Specific Ideas

- Hybrid API+UI proven in Phase 7 (operator-e2e.spec.js) — use it again for setup data
- Real user journey emphasis: the tests should read like user stories, not unit tests
- Single test() per spec file for clarity (easier to reason about failures)
- Generous timeouts on first assertions after navigation
- NEVER use networkidle (Phase 7 lesson)
- Use @critical-path tag on all 4 tests for easy filtering

</specifics>

<deferred>
## Deferred Ideas

- Visual regression on critical path - future milestone
- Performance benchmarks - future milestone
- Mobile viewport testing - future milestone
- Multi-browser (firefox/webkit) - future milestone

</deferred>
