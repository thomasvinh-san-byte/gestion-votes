---
phase: 61-dead-code-cleanup
plan: "01"
subsystem: codebase-hygiene
tags: [dead-code, vocabulary, phpunit, cli-tools, documentation]
dependency_graph:
  requires: [60-session-import-auth]
  provides: [CLEAN-01, CLEAN-02, CLEAN-03]
  affects: [phpunit.xml, SETUP.md, docs/directive-projet.md, app/Command]
tech_stack:
  added: []
  patterns: [retention-comments, vocabulary-alignment]
key_files:
  created: []
  modified:
    - SETUP.md
    - docs/directive-projet.md
    - phpunit.xml
    - app/Command/EmailProcessQueueCommand.php
    - app/Command/MonitoringCheckCommand.php
    - app/Command/RateLimitCleanupCommand.php
    - app/Command/RedisHealthCommand.php
decisions:
  - "app/Command CLI tools are intentionally retained with no unit tests — documented via inline comments"
  - "phpunit.xml app/WebSocket path was a Phase 58 rename artifact — corrected to app/SSE"
  - "CLEAN-01 was already satisfied pre-execution — 41 controller files, zero stubs"
metrics:
  duration_seconds: 86
  completed_date: "2026-03-31"
  tasks_completed: 3
  files_modified: 7
---

# Phase 61 Plan 01: Dead Code Cleanup Summary

Dead code cleanup closing v5.1 Operational Hardening milestone: syndic/copropriete vocabulary purged from two doc files, stale app/WebSocket phpunit.xml path corrected to app/SSE, and four app/Command CLI tools documented as intentionally retained — with full test suite green (2331 tests, 0 failures).

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Purge copropriete/syndic vocabulary and fix phpunit.xml stale path | a956273c | SETUP.md, docs/directive-projet.md, phpunit.xml |
| 2 | Verify CLEAN-01 (zero controller stubs) and run full test suite | (no commit — verification only) | app/Controller/ (41 files, read-only) |
| 3 | Document intentional retention of app/Command CLI tools | 83c61bde | 4 x app/Command/*.php |

## Decisions Made

- **CLEAN-01 pre-satisfied:** Zero stubs in 41 controller files. No changes required. Grep audit documented as proof.
- **Retention comments:** `// CLI tool — intentionally retained, no unit test required` inserted after namespace declaration in each of the 4 Command files.
- **Vocabulary replacements:** "Changement de syndic" → "Renouvellement du bureau" (SETUP.md); "Élection syndic" → "Élection du président"; "conseil syndical" enum → "conseil d'administration" (docs/directive-projet.md).
- **phpunit.xml path:** `app/WebSocket` → `app/SSE` — leftover from Phase 58 WebSocket-to-SSE rename.

## Requirement Traceability

| Requirement | Status | Evidence |
|-------------|--------|----------|
| CLEAN-01 | DONE | `grep -rn "not.implemented\|stub\|TODO\|FIXME" app/Controller/` returns zero matches across 41 files |
| CLEAN-02 | DONE | `grep -rni "syndic\|copropri" SETUP.md docs/directive-projet.md` returns zero matches |
| CLEAN-03 | DONE | phpunit.xml updated to app/SSE; 4 CLI tools have retention comments |

## Test Results

- **Full suite:** `vendor/bin/phpunit --no-coverage` — 2331 tests, 5192 assertions, 0 failures, 15 skipped
- **CLEAN-01 grep:** zero matches in app/Controller/ (41 files)

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

- FOUND: SETUP.md
- FOUND: docs/directive-projet.md
- FOUND: phpunit.xml
- FOUND: 4 x app/Command/*.php files
- FOUND: commit a956273c (Task 1)
- FOUND: commit 83c61bde (Task 3)
