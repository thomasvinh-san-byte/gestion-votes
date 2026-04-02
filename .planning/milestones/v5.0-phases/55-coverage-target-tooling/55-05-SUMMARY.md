---
phase: 55-coverage-target-tooling
plan: 05
subsystem: tests/unit/controllers
tags: [testing, coverage, controllers, phpunit]
dependency_graph:
  requires: [55-03]
  provides: [operator-controller-tests, admin-controller-tests, analytics-controller-tests, ballots-controller-tests, audit-controller-tests]
  affects: [COV-02]
tech_stack:
  added: []
  patterns: [ControllerTestCase-extend, RepositoryFactory-reflection-injection, callController-dispatch]
key_files:
  created: []
  modified:
    - tests/Unit/OperatorControllerTest.php
    - tests/Unit/AdminControllerTest.php
    - tests/Unit/AnalyticsControllerTest.php
    - tests/Unit/BallotsControllerTest.php
    - tests/Unit/AuditControllerTest.php
decisions:
  - "VoteEngine constructor fetches policyRepo and attendanceRepo eagerly from RepositoryFactory even when no policy IDs are set — all 5 repos must be injected in any test that exercises result()"
  - "AuditController::export() is untestable via callController() because it outputs raw CSV/JSON headers+echo without calling api_ok/api_fail — excluded from coverage scope"
  - "BallotRepository::tally() returns a flat associative dict (count_for, weight_for, etc.) not an array of row objects — mock must return flat dict"
  - "VoteEngine::computeMotionResult uses motion_id and motion_title keys (not id/title) from findWithVoteContext result"
metrics:
  duration_minutes: 90
  completed_date: "2026-03-30"
  tasks_completed: 2
  files_modified: 5
---

# Phase 55 Plan 05: Controller Tests Batch 2 Summary

Rewrote 5 controller test files (Operator, Admin, Analytics, Ballots, Audit) using ControllerTestCase with RepositoryFactory injection — delivering 153 execution-based tests that verify real controller dispatch paths with mocked repositories.

## What Was Built

All 5 controller test files now extend `ControllerTestCase` and use `injectRepos()` / `callController()` for HTTP-level dispatch testing with full mock repository isolation.

### Test Counts by Controller

| Controller | Tests | Status |
|---|---|---|
| OperatorControllerTest | 21 | All passing |
| AdminControllerTest | 45 | All passing |
| AnalyticsControllerTest | 25 | All passing |
| BallotsControllerTest | 33 | All passing |
| AuditControllerTest | 29 | All passing |
| **Total** | **153** | **All passing** |

### Coverage by Controller

**OperatorController** (workflowState, openVote, anomalies):
- All 3 methods tested: validation errors, meeting not found (404), happy paths
- workflowState: quorum policy, open motion detection, null meeting handling
- anomalies: duplicate ballot detection, invalid/missing motion IDs

**AdminController** (users, roles, meetingRoles, systemStatus, auditLog):
- Full CRUD for users: set_password, rotate_key, revoke_key, toggle, delete, update, create with validation
- meetingRoles: assign (with president guard), revoke, unknown action
- systemStatus: data shape + auth failure alerts
- auditLog: events list, limit clamping, action filter

**AnalyticsController** (analytics, reportsAggregate):
- All 7 analytics type variants: overview, participation, motions, vote_duration, proxies, anomalies, vote_timing
- All period variants; invalid type -> 400
- reportsAggregate: all 6 JSON report types, meeting_ids filter with valid+invalid UUIDs

**BallotsController** (listForMotion, cast, cancel, result, manualVote, reportIncident):
- listForMotion: validation, not found, happy path
- cancel: missing reason, motion not found, motion closed, ballot not found, not manual vote, success
- result: happy path with all 5 repos injected (VoteEngine eagerly fetches all in constructor)
- manualVote: all vote value variants (for/against/abstain/nsp), meeting live/motion open guards
- reportIncident: validation and success

**AuditController** (timeline, meetingAudit, meetingEvents, verifyChain, operatorEvents):
- timeline: pagination, limit clamping, unknown action label formatting, payload parsing
- verifyChain: valid chain (hashes match), broken chain (error count), empty events
- operatorEvents: filter params propagated correctly to repository call

## Commits

- `ee11a27` feat(55-05): rewrite OperatorControllerTest with ControllerTestCase and mocked repos
- `0c32af8` feat(55-05): rewrite AdminControllerTest with ControllerTestCase and mocked repos
- `665e016` feat(55-05): rewrite AnalyticsControllerTest with ControllerTestCase and mocked repos
- `2eac58d` test(55-05): fix BallotsControllerTest - inject policy+attendance repos for VoteEngine
- `8c46554` test(55-05): add AuditControllerTest - 29 tests for all 5 testable endpoints

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] VoteEngine requires all 5 repos injected even with no policy IDs**
- **Found during:** Task 2 (BallotsControllerTest testResultHappyPath)
- **Issue:** `VoteEngine` constructor fetches `policyRepo` and `attendanceRepo` from `RepositoryFactory::getInstance()` unconditionally — even when the motion has no policy IDs, so the repos are initialized. Without injecting them into the factory cache, `RepositoryFactory::getInstance()->policy()` falls through to `new PolicyRepository(null)` -> `db()` -> RuntimeException.
- **Fix:** Added `PolicyRepository` and `AttendanceRepository` mocks to `injectRepos()` in `testResultHappyPath`
- **Files modified:** `tests/Unit/BallotsControllerTest.php`
- **Commit:** `2eac58d`

**2. [Rule 1 - Bug] Wrong mock data keys for findWithVoteContext**
- **Found during:** Task 2 (BallotsControllerTest testResultHappyPath)
- **Issue:** Mock returned `'id'` and `'title'` but VoteEngine reads `$motion['motion_id']` and `$motion['motion_title']`
- **Fix:** Updated mock to return correct keys; also updated assertion from `motion_id` top-level to `motion`/`tallies`/`decision` structure
- **Files modified:** `tests/Unit/BallotsControllerTest.php`
- **Commit:** `2eac58d`

**3. [Rule 1 - Bug] AuditController::export() not testable via callController()**
- **Found during:** Task 2 (AuditControllerTest design)
- **Issue:** `export()` outputs raw headers + echo/fputcsv without calling `api_ok()`/`api_fail()` — no `ApiResponseException` is thrown for `callController()` to catch
- **Fix:** Excluded `export()` from test coverage; all other 5 methods tested
- **Files modified:** None (design decision)

## Self-Check: PASSED

Files exist:
- `tests/Unit/OperatorControllerTest.php` - FOUND
- `tests/Unit/AdminControllerTest.php` - FOUND
- `tests/Unit/AnalyticsControllerTest.php` - FOUND
- `tests/Unit/BallotsControllerTest.php` - FOUND
- `tests/Unit/AuditControllerTest.php` - FOUND

Commits exist:
- `ee11a27` - FOUND
- `0c32af8` - FOUND
- `665e016` - FOUND
- `2eac58d` - FOUND
- `8c46554` - FOUND

All 153 tests pass (verified: `php vendor/bin/phpunit --filter "OperatorControllerTest|AdminControllerTest|AnalyticsControllerTest|BallotsControllerTest|AuditControllerTest" --no-coverage`).
