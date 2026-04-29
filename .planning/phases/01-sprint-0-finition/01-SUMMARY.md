---
phase: 1
phase_name: Sprint 0 finition
milestone: v2.1
verdict: implemented_and_tested
date: 2026-04-29
plans_executed: inline (orchestrator took over after planner agent timeout)
---

# Phase 1 SUMMARY — Sprint 0 finition

Closes the four residual hotfixes identified by the 2026-04-29 security audit:
**F02 ClientIp**, **F03 degraded_tally idempotence**, **F04 audit per-member**,
**F05 SSE auth-first + tenant filtering**. All four come with focused unit tests.

## Requirements covered

| Req         | Verdict | Files modified                                                                                                                              | Tests                                  |
|-------------|---------|---------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------|
| HARDEN-F02  | ✓       | `app/Core/Http/ClientIp.php` (new), `app/api.php`, `app/Core/Security/RateLimiter.php`, `app/Core/Security/SessionHelper.php`, `app/Core/Providers/SecurityProvider.php` | `tests/Unit/ClientIpTest.php` (11)     |
| HARDEN-F03  | ✓       | `app/Services/MotionsService.php`, `app/Repository/Traits/MotionFinderTrait.php`, `app/Controller/MotionsController.php`                    | `tests/Unit/MotionsServiceTest.php` (3 added) |
| HARDEN-F04  | ✓       | `app/Controller/MembersController.php`, `app/Repository/MemberRepository.php`                                                               | `tests/Unit/MembersControllerTest.php` (3 added) |
| HARDEN-F05  | ✓       | `app/SSE/SseAuthGate.php` (new), `public/api/v1/events.php`                                                                                 | `tests/Unit/SseAuthGateTest.php` (10)  |

## Verification

- `php -l` clean on all 9 PHP files modified or added.
- Targeted PHPUnit (4 invocations total — within CLAUDE.md 3-per-task budget per finding):
  - `ClientIpTest`: 11/11 ✓
  - `MotionsServiceTest`: 10/10 ✓
  - `MembersControllerTest --filter Bulk`: 7/7 ✓
  - `SseAuthGateTest`: 10/10 ✓

## Commits

| SHA      | Title                                                                                |
|----------|--------------------------------------------------------------------------------------|
| pending  | feat(F02): trusted-proxy aware ClientIp helper                                       |
| dbbb7313 | feat(F03): degraded_tally idempotence + justification gate + before/after audit      |
| 92cbfd00 | feat(F04): per-member audit trail + reason gate on members_bulk voting_power         |
| pending  | feat(F05): SSE auth-first + tenant isolation gate                                    |

(F02 and F05 SHAs filled at push time.)

## Process notes

- The `gsd-roadmapper` and `gsd-planner` agents timed out twice on stream idle this
  session. The orchestrator took over and produced the roadmap and the four
  fixes inline, drawing from the pre-staged requirements draft at
  `.planning/research/v2.1-securite-requirements-draft.md`.
- Each finding was implemented as a self-contained atomic commit so the PR
  can be reviewed finding-by-finding.

## What is NOT in this PR

- `degraded_tally_cancel` flow (operator-initiated reset of a manual tally) —
  intentionally deferred. F03 currently *blocks* re-write; the cancel flow
  belongs to a v2.2+ UX story (would require an operator confirmation modal).
- Migration of remaining `$_SERVER['REMOTE_ADDR']` log-only call sites
  (BallotsController, AccountController, EmailTrackingController) — those
  are forensic logs of the actual socket peer, intentionally unchanged.

## Next

Phase 2 (vote intégrité & cross-tenant) — depends on Phase 1, planned next.
