# Deferred items — Phase 04

Items discovered out-of-scope during plan execution. NOT fixed; logged for later triage.

## Discovered during 04-02 execution (2026-05-05)

### Pre-existing failures in MeetingsControllerTest (unrelated to 04-02 scope)

6 failing tests in `tests/Unit/MeetingsControllerTest.php`, all in `update()` and `delete()` methods. Plan 04-02 only modified `archivesList()` (added `HttpCache::sendOk` call). Verified failures pre-exist by running `git stash && phpunit` — failures reproduce without 04-02 changes.

Failing tests :
- `testUpdateArchivedMeetingReturns409`
- `testDeleteMeetingNotFoundReturns404`
- `testDeleteMeetingNonDraftReturns409`
- `testDeleteLiveMeetingReturns409WithHint`
- `testDeleteClosedMeetingStillRejects`
- `testDeleteMeetingHappyPathReturnsDeleted`

Symptom : test expects 409 / 200 / 404 but gets 400. Likely a previously undetected schema/behavior drift in `MeetingLifecycleService` or `update`/`delete` validation paths.

Action : triage in a future bugfix plan. Not blocking 04-02 (PERF-V27-03).
