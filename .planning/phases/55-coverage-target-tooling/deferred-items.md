# Deferred Items — Phase 55

## Pre-existing test failures (out of scope for 55-06)

These failures exist in the working tree from prior plan executions (55-04/55-05) and are unrelated to 55-06 changes. They should be addressed in a dedicated plan.

### SpeechControllerTest (7 errors)
- `testRequestWithValidDataCallsService` — mocking `findActiveRequest` which does not exist
- `testGrantLooksUpMemberByRequestId` — mocking `getActiveSpeaker` which does not exist
- `testQueueReturnsSpeakerAndQueue` — mocking `getActiveSpeaker` which does not exist
- `testQueueWithItemsTransformsFields` — same
- `testCurrentNoSpeakerReturnsNull` — same
- `testMyStatusReturnsStatus` — mocking `findMyStatus` which does not exist

**Root cause:** SpeechControllerTest mocks methods that don't exist on the SpeechRepository.
The repository methods were renamed or the tests reference an outdated API.

### DashboardControllerTest (1 failure)
- `testIndexPicksLiveMeetingAsSuggested` — returns 404 instead of 200

**Root cause:** DashboardControllerTest uses a mocked MeetingRepository that doesn't
return the right data for the `findSuggestedMeeting` path.
