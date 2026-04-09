---
phase: 09-tests-e2e-par-role
plan: 01
subsystem: testing
tags: [playwright, e2e, cookies, docker, auth-setup]

# Dependency graph
requires:
  - phase: 08-test-infrastructure-docker
    provides: Docker Playwright runner (bin/test-e2e.sh) and baseline failure analysis
provides:
  - "auth.setup.js writes session cookies with the correct domain for the runtime environment (app in Docker, localhost on host)"
  - "ERR_SSL_PROTOCOL_ERROR in browser-based E2E tests eliminated"
  - "Prerequisites for plans 09-02 through 09-05 satisfied"
affects: [09-02-PLAN, 09-03-PLAN, 09-04-PLAN, 09-05-PLAN]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "COOKIE_DOMAIN = new URL(BASE_URL).hostname — derive cookie domain from BASE_URL host, mirroring playwright.config.js baseURL logic"
    - "BASE_URL fallback uses IN_DOCKER flag to match playwright.config.js exactly"

key-files:
  created: []
  modified:
    - tests/e2e/setup/auth.setup.js

key-decisions:
  - "Derive COOKIE_DOMAIN via new URL(BASE_URL).hostname so cookie domain always matches the host Playwright navigates to"
  - "Mirror playwright.config.js BASE_URL fallback logic in auth.setup.js (IN_DOCKER ? app:8080 : localhost:8080) to avoid split-brain between setup and test config"
  - "operator-e2e.spec.js residual failure (missing meeting id) is an API contract / test data issue unrelated to this fix — deferred to plan 09-02+"

patterns-established:
  - "Pattern: auth setup and playwright config use identical BASE_URL resolution logic to guarantee cookie domain matches test navigation host"

requirements-completed: []

# Metrics
duration: 15min
completed: 2026-04-07
---

# Phase 09 Plan 01: Cookie Domain Fix Summary

**PHPSESSID cookie domain derived from BASE_URL hostname, eliminating ERR_SSL_PROTOCOL_ERROR and unblocking all browser-based E2E tests in Docker**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-04-07T00:00Z
- **Completed:** 2026-04-07T00:15Z
- **Tasks:** 2
- **Files modified:** 2 (auth.setup.js + 4 .auth/*.json artifacts)

## Accomplishments
- Replaced hard-coded `domain: 'localhost'` with `COOKIE_DOMAIN` constant derived from `new URL(BASE_URL).hostname`
- Updated `BASE_URL` fallback in auth.setup.js to mirror `playwright.config.js` (respects `IN_DOCKER` flag)
- Smoke run of `operator-e2e.spec.js` confirmed `ERR_SSL_PROTOCOL_ERROR` is gone — cookies are now sent with `domain: app` in Docker
- `.auth/operator.json` (and all 4 role files) now contain `"domain": "app"` instead of `"domain": "localhost"`

## Task Commits

1. **Task 1: Derive cookie domain from BASE_URL host** - `dd1f9033` (fix)
2. **Task 2: Smoke-verify fix via operator-e2e.spec.js run** - `7ac4124e` (chore — .auth state update)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `tests/e2e/setup/auth.setup.js` - Added `COOKIE_DOMAIN` constant and replaced `domain: 'localhost'` literal; updated `BASE_URL` fallback; improved console.log to include domain

## Decisions Made
- Derived `COOKIE_DOMAIN` via `new URL(BASE_URL).hostname` rather than a conditional `IN_DOCKER` check — more robust: works for any custom `BASE_URL` value (e.g., staging URLs), not just the two known hosts.
- Kept `BASE_URL` fallback consistent with `playwright.config.js` to avoid split-brain when auth.setup.js runs outside `bin/test-e2e.sh`.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

**operator-e2e.spec.js residual failure (not a regression):**
The spec now fails with `must have a meeting id to proceed` (line 95) rather than `ERR_SSL_PROTOCOL_ERROR`. This is an API contract / test data problem: the meeting creation step returns null `meetingId`, likely because the test expects specific DB state or a specific API response shape. This failure was NOT caused by the cookie domain bug — it existed in the baseline but was masked by the earlier SSL error. It is deferred to plans 09-02+.

Auth-setup log observed during smoke run:
```
[auth-setup] Saved auth state for operator (session: 3041d7a5..., domain: app)
[auth-setup] Saved auth state for admin (session: d9137cbc..., domain: app)
[auth-setup] Saved auth state for voter (session: 101b7813..., domain: app)
[auth-setup] Saved auth state for president (session: 06eb227f..., domain: app)
```

operator.json domain field after smoke run: `"domain": "app"` — correct.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Cookie domain prerequisite for all browser-based E2E tests is satisfied
- Plans 09-02 through 09-05 can now proceed — auth sessions will be accepted by the browser
- operator-e2e.spec.js has a pre-existing API contract failure (missing meeting id) that plans 09-02+ should investigate or skip if not in scope

---
*Phase: 09-tests-e2e-par-role*
*Completed: 2026-04-07*
