---
phase: 16-data-foundation
plan: 01
status: complete
started: "2026-03-16"
completed: "2026-03-16"
---

# Plan 16-01 Summary: Atomic Member + Motion Persistence

## What Was Built

Extended `MeetingsController::createMeeting()` to process the full wizard payload (members + resolutions) inside a single `api_transaction()`, replacing the previous meeting-only creation.

## Key Changes

### app/Controller/MeetingsController.php
- **Field mapping:** Wizard fields (`type`, `date`+`time`, `place`, `quorum`, `defaultMaj`) mapped to backend format before processing
- **Atomic transaction:** Meeting creation, member upsert, attendance linking, and motion creation all wrapped in `api_transaction()`
- **Member upsert:** `findByEmail()` checks for existing members; creates new ones only if not found; all linked via `upsertMode('present')`
- **Motion creation:** Each resolution becomes a motion row with generated UUID
- **Validation:** Per-item 422 errors with `index`, `field`, `message` for invalid members/resolutions
- **Expanded response:** `{ meeting_id, title, members_created, members_linked, motions_created }`
- **Removed unused `ValidationSchemas` import** (wizard payload uses manual validation due to field name mismatch)

### tests/Unit/MeetingsControllerTest.php
- 5 new source-inspection tests covering: count fields in response, api_transaction usage, wizard field mapping, findByEmail upsert, empty arrays backward compatibility

## Verification

- `./vendor/bin/phpunit tests/Unit/MeetingsControllerTest.php --no-coverage` → 133 tests, 320 assertions, all green
- `./vendor/bin/phpunit --no-coverage` → 2843 tests, 5974 assertions, all green (14 skipped)

## Commits

1. `ba7dd0e` — feat(16-01): extend createMeeting() with atomic member + motion persistence
2. `d30a2dc` — chore(16-01): remove unused ValidationSchemas import

## Key Files

### Created
- `.planning/phases/16-data-foundation/16-01-SUMMARY.md`

### Modified
- `app/Controller/MeetingsController.php` — atomic createMeeting with members + motions
- `tests/Unit/MeetingsControllerTest.php` — 5 new tests for expanded response

## Deviations

None. Implementation follows plan exactly.
