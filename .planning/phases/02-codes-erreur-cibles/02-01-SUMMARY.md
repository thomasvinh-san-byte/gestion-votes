---
phase: 02-codes-erreur-cibles
plan: 01
subsystem: error-handling
tags: [error-codes, observability, abstract-controller, error-dictionary, runtime-exception]
requires: []
provides:
  - "AbstractController extracts snake_case codes from RuntimeException into api_fail() error_code"
  - "ErrorDictionary entries for archived_meeting_locked + validated_meeting_locked"
  - "Service-layer normalization of 3 French RuntimeException throws into snake_case codes"
affects:
  - "/admin/error-stats dashboard now sees specific codes instead of generic business_error"
  - "All ~80 service sites already throwing snake_case RuntimeException now bypass business_error"
tech-stack:
  added: []
  patterns:
    - "Snake_case extractor regex on catch (RuntimeException) — bounded length, strict pattern"
    - "Norman v2.3 ERR-02 next-step convention for new dictionary entries"
key-files:
  created:
    - tests/Unit/Controller/AbstractControllerBusinessErrorTest.php
    - tests/Unit/ErrorStatsRoutingTest.php
  modified:
    - app/Controller/AbstractController.php
    - app/Services/MeetingTransitionService.php
    - app/Services/MeetingLifecycleService.php
    - app/Services/ErrorDictionary.php
decisions:
  - "Detection at catch site (1 file) instead of rewriting 94 service throws (94 files)"
  - "ErrorStatsRoutingTest implemented with mock-PDO (not markTestSkipped) — exercises SQL routing without Postgres"
  - "Distinct request_ids (req-A, req-B) per test to escape Plan 02-02 idempotency guard"
metrics:
  duration: "~15 min"
  completed: "2026-05-05"
  tasks_completed: "2/2"
  tests_added: 13
  tests_assertions: 19
requirements_completed:
  - ERR-V26-01
  - ERR-V26-03
---

# Phase 02 Plan 01: Codes d'erreur cibles — extraction passive + dictionnaire Summary

## One-liner

AbstractController surface les codes snake_case portes par RuntimeException en
`api_fail()` error_code (regex bornee 3-40 chars), 3 throws francais normalises
en `archived_meeting_locked` / `validated_meeting_locked`, et 2 nouvelles
entrees ErrorDictionary avec next-step francais Norman v2.3 ERR-02.

## What Was Built

### Task 1 — AbstractController catch enhancement + service normalizations + dictionary entries

**Commit:** `0efe010`

- `app/Controller/AbstractController.php`
  - Added `private static function extractBusinessErrorCode(string $message): ?string` (regex `^[a-z][a-z0-9]*(_[a-z0-9]+)*$`, length 3-40, trimmed).
  - Modified `catch (RuntimeException $e)` (line 53) to call `api_fail($code, 400, ['detail' => ...])` with `$code = self::extractBusinessErrorCode(...) ?? 'business_error'`.
  - Other catch branches and `wrapApiCall()` untouched.
- `app/Services/MeetingTransitionService.php` line 56: `'Seance archivee : aucune transition autorisee.'` → `'archived_meeting_locked'`.
- `app/Services/MeetingTransitionService.php` line 251: `'Seance validee : reset interdit (seance figee).'` → `'validated_meeting_locked'`.
- `app/Services/MeetingLifecycleService.php` line 44: `'Seance archivee : modification interdite.'` → `'archived_meeting_locked'`.
- `app/Services/ErrorDictionary.php` lines 117-119 (new entries inserted after `'invalid_state'`):
  - `archived_meeting_locked` → "Séance archivée : aucune transition ni modification autorisée, créez une nouvelle séance pour repartir d'un état modifiable ou consultez l'archive en lecture seule."
  - `validated_meeting_locked` → "Séance validée : la réinitialisation est interdite pour préserver l'audit, créez une nouvelle séance si vous devez recommencer le processus de vote."

### Task 2 — PHPUnit coverage

**Commit:** `b3e46ff`

- `tests/Unit/Controller/AbstractControllerBusinessErrorTest.php` — 10 tests / 12 assertions, all passing. Uses `ReflectionMethod` against the private static `extractBusinessErrorCode` (avoids mocking the api_fail() global).
  - Positive: `archived_meeting_locked`, `validated_meeting_locked`, `meeting_not_found` (regression-safe for the 49 existing sites).
  - Fallback: French message, empty/whitespace, spaces, punctuation, oversized (>40 chars), double underscore, leading digit.
- `tests/Unit/ErrorStatsRoutingTest.php` — 3 tests / 7 assertions, all passing. Mock-PDO pure (no DB, no markTestSkipped).
  - `test_capture_emits_archived_meeting_locked_into_insert`: asserts `prepare()` receives `INSERT INTO error_events` and `execute()` receives `:code => 'archived_meeting_locked'`.
  - `test_capture_emits_validated_meeting_locked_into_insert`: same for the second new code.
  - `test_top_codes_since_executes_select`: asserts `prepare()` receives a SELECT with `FROM error_events`, mock returns the 2 new codes, repository returns them via `topCodesSince()`.

## Verification Results

- `php -l` clean on all 4 modified app files and 2 new test files.
- `timeout 60 php vendor/bin/phpunit tests/Unit/Controller/AbstractControllerBusinessErrorTest.php --no-coverage`: **OK (10 tests, 12 assertions)**.
- `timeout 60 php vendor/bin/phpunit tests/Unit/ErrorStatsRoutingTest.php --no-coverage`: **OK (3 tests, 7 assertions)**.
- 1 test execution per file (within CLAUDE.md max-3-runs budget).

### Audit grep before/after

| Audit                                                                  | Before plan | After plan |
| ---------------------------------------------------------------------- | ----------- | ---------- |
| `api_fail('business_error'` direct calls (literal string)              | 1           | 0          |
| `throw new RuntimeException('Séance (archivée\|validée) ...')` 3 cibles | 3           | 0          |
| ErrorDictionary entries `archived_meeting_locked`                      | 0           | 1          |
| ErrorDictionary entries `validated_meeting_locked`                     | 0           | 1          |
| `extractBusinessErrorCode` defined + called in AbstractController      | 0           | 2          |

The lone literal `api_fail('business_error'` in AbstractController is now a
dynamic fallback (`$code` variable) — not a literal direct call — so the audit
result is `0` literal hits, as designed.

## Deviations from Plan

None — plan executed exactly as written. The mock-PDO implementation for
ErrorStatsRoutingTest was provided complete in the plan and used verbatim
(no `markTestSkipped`).

## Deferred Issues

### Out-of-scope French throws (followups, not in this plan)

The plan deliberately scoped 3 specific sites (the only ones blocking the
v2.6 dashboard observability goal). Other French throws remain in services
and are tracked here as observation, not commitment:

- `app/Services/AttendancesService.php:121` — `'Séance introuvable'`
- `app/Services/AttendancesService.php:124` — `'Séance archivée : présence non modifiable'`
- `app/Services/QuorumEngine.php:119` — `'Séance introuvable'`
- `app/Services/SpeechService.php:65` — `'Séance introuvable'`
- `app/Services/SpeechService.php:229` — `'Seules les demandes en attente peuvent être annulées'`
- `app/Services/BallotsService.php:78` — `'Séance validée : vote interdit'`
- `app/Services/BallotsService.php:160` — `'Séance non disponible pour le vote'`
- `app/Services/VoteTokenService.php:69` — `'Séance introuvable'`

These continue to fall back through the new dynamic `business_error` path —
no regression vs. before. v2.6 closure rule prevents adding them to scope.
Capture in v2.7 backlog as `ERR-V27-XX-normalize-remaining-french-throws`.

### Pre-existing ErrorDictionaryTest failures (NOT introduced by this plan)

`tests/Unit/ErrorDictionaryTest.php` has 4 pre-existing failures at the base
commit `544a60a` (verified by checkout-test). The test expects shorter
legacy messages while the dictionary already contains Norman next-step
strings. Affected tests: `Unauthorized`, `MeetingNotFound`, `InvalidVoteChoice`,
`ServerError`. **Out of scope per CLAUDE.md scope boundary rule** — not
caused by my edits. Recommend a one-shot fix in v2.6 closure or v2.7.

## Cross-Plan Contract

ErrorStatsRoutingTest uses distinct `request_id`s (`req-A`, `req-B`) per
`capture()` call. This is intentional to escape the intra-request idempotency
guard introduced by Plan 02-02. The contract is documented in the test
file's class docblock so future readers see the linkage.

## Self-Check: PASSED

- [x] `app/Controller/AbstractController.php` — modified (commit `0efe010`).
- [x] `app/Services/MeetingTransitionService.php` — modified (commit `0efe010`).
- [x] `app/Services/MeetingLifecycleService.php` — modified (commit `0efe010`).
- [x] `app/Services/ErrorDictionary.php` — modified (commit `0efe010`).
- [x] `tests/Unit/Controller/AbstractControllerBusinessErrorTest.php` — created (commit `b3e46ff`).
- [x] `tests/Unit/ErrorStatsRoutingTest.php` — created (commit `b3e46ff`).
- [x] Commit `0efe010` verified in `git log --oneline`.
- [x] Commit `b3e46ff` verified in `git log --oneline`.
- [x] AbstractControllerBusinessErrorTest: 10/10 tests pass.
- [x] ErrorStatsRoutingTest: 3/3 tests pass.
- [x] No `markTestSkipped` in either new test file.
- [x] Audit greps confirm 0 `api_fail('business_error'` literals and 0 of the 3 target French throws.

