---
phase: 260402-d2y
plan: 01
subsystem: members-api
tags: [routing, performance, data-integrity, api-surface]
key-decisions:
  - "Proxy cleanup is inline in softDelete() — no new dependency injection needed"
  - "Batch groups uses buildInClause with prefix 'mbid' (not column alias) for valid param names"
  - "TENANT/USER_ID constants updated to valid UUIDs in tests to satisfy api_is_uuid validation"
key-files:
  created: []
  modified:
    - app/routes.php
    - app/Repository/MemberRepository.php
    - app/Repository/MemberGroupRepository.php
    - app/Controller/MembersController.php
    - tests/Unit/MembersControllerTest.php
metrics:
  duration: ~12 minutes
  completed: 2026-04-01
  tasks_completed: 3
  files_modified: 5
---

# Quick Task 260402-d2y: Fix 5 Post-v9.0 Audit Findings — Summary

**One-liner:** Routing, N+1 batch fix, search filter, soft-delete proxy cascade, and bulk assign/voting-power endpoint all resolved across 5 files.

## Tasks Completed

| Task | Description | Commit |
|------|-------------|--------|
| 1 | Register RgpdExportController at GET /api/v1/rgpd_export | 828c273f |
| 2 (RED) | Write failing tests for all 4 implementation findings | 1c58b7b5 |
| 2 (GREEN) | Implement proxy cascade, batch groups, search filter, bulk endpoint | 7f7e4482 |
| 3 | Register POST /api/v1/members_bulk route | 987c3082 |

## Findings Resolved

**Finding 1 — AUDIT-01: RgpdExportController not routed**
- Added `use AgVote\Controller\RgpdExportController` to routes.php
- Registered `GET /api/v1/rgpd_export` with rate limit 5/hour
- All roles with data access can export

**Finding 2 — AUDIT-02: Soft-delete does not cascade to proxies**
- `MemberRepository::softDelete()` now runs a second `execute()` to `DELETE FROM proxies WHERE tenant_id = :tid AND (giver_member_id = :id OR receiver_member_id = :id)`
- No new dependency injection — uses existing `execute()` from AbstractRepository

**Finding 3 — AUDIT-03: N+1 queries for include_groups**
- Added `MemberGroupRepository::listGroupsForMembers(array $memberIds, string $tenantId): array`
- Single IN() query joining `member_group_assignments` + `member_groups`
- Returns keyed array by member_id
- `MembersController::index()` now calls batch method, maps results onto rows

**Finding 4 — AUDIT-04: No search/filter param on members list**
- Added `MemberRepository::listPaginatedFiltered()` with `ILIKE` on full_name/email
- Added `MemberRepository::countFiltered()` for pagination total
- `MembersController::index()` branches on `$_GET['search']` — uses filtered path when non-empty

**Finding 5 — AUDIT-05: No bulk endpoint for group assignment / voting power**
- Added `MemberRepository::bulkUpdateVotingPower()` using `filterExistingIds()` + IN() UPDATE
- Added `MembersController::bulk()` accepting `assign_group` and `update_voting_power`
- Full validation: operation required, member_ids 1-200 UUIDs, group_id for assign, voting_power 0.01-100 for update
- Audit log for each operation
- Registered at `POST /api/v1/members_bulk` (operator|admin, 20/min rate limit)

## Deviations from Plan

**1. [Rule 1 - Bug] Updated test UUID constants to valid UUIDs**
- Found during: Task 2 GREEN phase
- Issue: `TENANT = 'tenant-uuid-001'` and `USER_ID = 'user-uuid-0080'` are not valid UUIDs. The `api_is_uuid()` validator in `bulk()` was rejecting `group_id` validation (but auth was not affected because `setAuth()` doesn't validate UUID format). The `GROUP_ID` constant `'group-uuid-001'` was directly invalid in the bulk test.
- Fix: Changed `TENANT`, `USER_ID` to proper UUID format, added `GROUP_ID` constant as valid UUID. Updated the existing `testIndexWithIncludeGroupsFetchesGroups` test to use the new batch `listGroupsForMembers()` method.
- Files modified: tests/Unit/MembersControllerTest.php
- Commit: 7f7e4482

## Verification

```
OK (26 tests, 67 assertions)
No syntax errors detected in app/routes.php
No syntax errors detected in app/Repository/MemberRepository.php
No syntax errors detected in app/Repository/MemberGroupRepository.php
No syntax errors detected in app/Controller/MembersController.php
```

Both new routes confirmed in routes.php:
- `GET /api/v1/rgpd_export` — line 198
- `POST /api/v1/members_bulk` — line 285

## Self-Check: PASSED

All committed files exist and all 4 commit hashes are reachable in git log.
