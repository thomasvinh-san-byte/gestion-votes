---
phase: 2
phase_name: Vote intégrité & cross-tenant
milestone: v2.1
verdict: implemented_and_tested
date: 2026-04-29
plans_executed: inline (orchestrator took over after planner agent timeouts)
---

# Phase 2 SUMMARY — Vote intégrité & cross-tenant

Closes 5 hardening findings around the core voting integrity surface and
cross-tenant isolation. Two findings (F06, F08) revealed that some of the
original "vulnerabilities" flagged in the audit were already mitigated in
production code — those were converted into regression tests so the
existing protection cannot silently regress.

## Requirements covered

| Req         | Verdict | Approach                                                       | Tests                                 |
|-------------|---------|----------------------------------------------------------------|---------------------------------------|
| HARDEN-F06  | ✓       | Already atomic via `consumeIfValid` (UPDATE…RETURNING). Doc + regression test added. | `tests/Unit/VoteTokenServiceTest.php` (+1) |
| HARDEN-F07  | ✓       | Migration to NULL legacy plaintext tokens + CHECK constraint forbidding plaintext when hash exists. Repository fallback now logs when used. | n/a (DB migration; covered indirectly by InvitationRepository tests) |
| HARDEN-F08  | ✓       | 1 dead unsafe method removed, 1 made tenant-required, 2 docs clarified. Reflection-based contract test prevents regression. | `tests/Unit/MotionRepositoryTenantIsolationTest.php` (+2) |
| HARDEN-F09  | ✓       | 4 hardening layers on resetDemo: meeting_id required, prod+admin gate, typed RESET-<prefix> token, status whitelist {draft, scheduled}. | `tests/Unit/MeetingWorkflowControllerTest.php` (+5 cases, 17/17) |
| HARDEN-F10  | ✓       | Opt-in HMAC-scoped CSRF tokens (`tokenFor(method, path)`, `fieldFor(method, path)`). Legacy session-wide token still works — gradual migration. | `tests/Unit/CsrfMiddlewareTest.php` (+9 cases, 25/25) |

## Verification

- `php -l` clean on all PHP files modified or added.
- Migration syntax check: `bash scripts/validate-migrations.sh --syntax-only` → PASS (26 files).
- Targeted PHPUnit (5 files):
  - VoteTokenServiceTest: 19/19 ✓
  - MotionRepositoryTenantIsolationTest: 2/2 ✓
  - MeetingWorkflowControllerTest filter ResetDemo: 17/17 ✓
  - CsrfMiddlewareTest: 25/25 ✓

## Commits

| SHA       | Title                                                                              |
|-----------|------------------------------------------------------------------------------------|
| 8f8e4f4f  | feat(F06): regression test for atomic vote-token consume                          |
| 5ca4a14e  | feat(F07): NULL legacy plaintext invitation tokens + CHECK constraint              |
| 93e521ac  | feat(F08): close cross-tenant IDOR primitives in MotionRepository                  |
| 6fe9d00f  | feat(F09): lock down resetDemo — production gate + status whitelist + typed confirm |
| ce329e8b  | feat(F10): action-scoped CSRF tokens via HMAC + opt-in API                         |

## Discoveries vs the original audit

- **F06** offensive analysis claimed a TOCTOU window between an early
  `findValidByHash` and a later `consumeIfValid`. After re-reading, the
  consume IS atomic via UPDATE…RETURNING and is wrapped in a transaction
  with FOR UPDATE on the motion. False positive — replaced fix work
  with a regression test that locks the behavior.

- **F07** column `token` was already being NULLed on every upsert; the
  hardening here is the migration that wipes legacy rows, plus a CHECK
  constraint preventing future writes from reintroducing the leak.

- **F08** systematic audit of 155 public DB methods across 5 repositories
  found ONLY 4 outliers, of which 2 were the tenant-ownership oracle
  (`isOwnedByUser`) which is correct as-is. The 4th was dead code (no
  callers — deleted). The single real fix was on `findByIdAndMeetingWithDates`.

## Out of scope (deferred to v2.2+)

- 8 MotionRepository methods accept `string $tenantId = ''` as optional
  with conditional WHERE. These are footguns — every caller appears to
  pass a non-empty tenantId today, but the default is risky. Auditing
  every caller is its own task.
- Migrating existing templates from `field()` to `fieldFor(method, path)`
  is gradual. Every form continues to work with the legacy token.
- Switching invitation token hashing from plain SHA-256 to HMAC-SHA256
  would invalidate every pending invitation — defer to a synchronized
  re-issue.

## Next

Phase 3 (Périmètre & SSRF) — F11 URL whitelist, F12 rate limits, F13 lockout progressif.
