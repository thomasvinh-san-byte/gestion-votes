---
phase: 77-rgpd-compliance
plan: "02"
subsystem: rgpd
tags: [rgpd, data-retention, right-to-erasure, cli-command, repository, admin-controller]
dependency_graph:
  requires: []
  provides:
    - DataRetentionCommand (rgpd:purge-retention CLI)
    - MemberRepository::findExpiredForTenant
    - MemberRepository::hardDeleteById
    - AdminController erase_member action
  affects:
    - app/Repository/MemberRepository.php
    - app/Command/DataRetentionCommand.php
    - app/Controller/AdminController.php
    - bin/console
tech_stack:
  added: []
  patterns:
    - Console command via Symfony Console (AsCommand attribute)
    - PostgreSQL INTERVAL string interpolation (not bind param) for retention query
    - DB CASCADE hard delete via FK ON DELETE CASCADE
    - requireConfirmation 2-step pattern for destructive admin actions
key_files:
  created:
    - app/Command/DataRetentionCommand.php
    - tests/Unit/DataRetentionCommandTest.php
  modified:
    - app/Repository/MemberRepository.php
    - app/Controller/AdminController.php
    - bin/console
decisions:
  - "PostgreSQL INTERVAL does not support bind params inside the literal — month count embedded via (int) cast interpolation"
  - "hardDeleteById relies on DB-level ON DELETE CASCADE for referential integrity (ballots, attendances, proxies, member_group_assignments, invitations, speech_requests)"
  - "erase_member action reuses requireConfirmation same as delete action — admin must confirm with password"
  - "findByIdForTenant used pre-delete to return 404 if member not found (prevents silent no-op)"
metrics:
  duration_minutes: 8
  completed_date: "2026-04-02"
  tasks_completed: 2
  files_changed: 5
---

# Phase 77 Plan 02: RGPD Data Retention and Right-to-Erasure Summary

**One-liner:** Admin-configurable data retention purge command (`rgpd:purge-retention`) and right-to-erasure action (`erase_member`) with hard-delete cascade across all related tables.

## What Was Built

### Task 1: MemberRepository methods + DataRetentionCommand (TDD)

**MemberRepository** — two new methods:
- `findExpiredForTenant(string $tenantId, int $monthsRetention): array` — returns members with `updated_at < NOW() - INTERVAL 'N months'`. Returns `[]` when `months <= 0` (disabled). Uses integer interpolation for the INTERVAL literal (PostgreSQL limitation with bind params).
- `hardDeleteById(string $id, string $tenantId): int` — `DELETE FROM members WHERE id=:id AND tenant_id=:tid`. Returns affected row count. All cascade FK (ballots, attendances, proxies, member_group_assignments, invitations, speech_requests) handled by DB.

**DataRetentionCommand** (`rgpd:purge-retention`):
- `--tenant-id` (`-t`): required tenant UUID
- `--dry-run`: list without deleting
- Reads `data_retention_months` from `tenant_settings` via `SettingsRepository::get()`; exits with info message when `0` (not configured)
- Iterates expired members, calls `hardDeleteById`, reports count

**bin/console**: `DataRetentionCommand` registered with updated docblock.

**14 unit tests** cover:
- Command configuration (name, options, shortcuts, description)
- `findExpiredForTenant` with `months=0` / negative (returns `[]`)
- `findExpiredForTenant` with `months=12` (returns rows, scopes to tenant_id, uses INTERVAL)
- `hardDeleteById` (correct SQL, correct params, returns row count)

### Task 2: erase_member action in AdminController

New `elseif ($action === 'erase_member')` block in `users()`:
1. `requireConfirmation` — admin must provide `confirm_password`
2. `api_require_uuid($in, 'member_id')` — validates UUID format
3. `findByIdForTenant` — 404 if not found or wrong tenant
4. `hardDeleteById` — hard delete with cascade
5. `audit_log('admin.member.erased', ...)` — records `full_name`, `email`, `rgpd=true`
6. Returns `{'erased': true, 'member_id': ..., 'rows_deleted': N}`

## Deviations from Plan

None — plan executed exactly as written.

## Verification

All checks passed:
- `timeout 60 php vendor/bin/phpunit tests/Unit/DataRetentionCommandTest.php --no-coverage` — 14 tests, 14 pass, 28 assertions
- `php -l app/Controller/AdminController.php` — no syntax errors
- `php -l app/Command/DataRetentionCommand.php` — no syntax errors
- `grep -q "erase_member" app/Controller/AdminController.php` — PASS
- `grep -q "rgpd:purge-retention" bin/console` — PASS
- `grep -q "findExpiredForTenant" app/Repository/MemberRepository.php` — PASS
- `grep -q "hardDeleteById" app/Repository/MemberRepository.php` — PASS

## Self-Check: PASSED
