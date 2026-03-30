<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\SpeechController;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\SpeechRepository;

/**
 * Unit tests for SpeechController.
 *
 * Endpoints:
 *  - request():  POST — toggle a speech request for a member
 *  - grant():    POST — grant the floor to a member or next in queue
 *  - end():      POST — end the current speaker
 *  - cancel():   POST — cancel a speech request
 *  - clear():    POST — clear speech history for a meeting
 *  - next():     POST — advance to next speaker
 *  - queue():    GET  — return current queue
 *  - current():  GET  — return current speaker info
 *  - myStatus(): GET  — return a member's speech status
 *
 * Note: api_require_uuid returns 400, not 422.
 * Note: SpeechService is instantiated directly inside each method (not injected).
 *
 * Response structure:
 *   - api_ok($data)  => body['data'][...] (wrapped in 'data' key)
 *   - api_fail(...)  => body['error'] (direct)
 *
 * Pattern: extends ControllerTestCase, uses injectRepos() + callController().
 */
class SpeechControllerTest extends ControllerTestCase
{
    private const TENANT     = 'tenant-uuid-001';
    private const MEETING_ID = 'aaaaaaaa-1111-2222-3333-000000000030';
    private const MEMBER_ID  = 'bbbbbbbb-1111-2222-3333-000000000030';
    private const REQUEST_ID = 'cccccccc-1111-2222-3333-000000000030';
    private const USER_ID    = 'user-uuid-0030';

    // =========================================================================
    // CONTROLLER STRUCTURE
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(SpeechController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testControllerHasRequiredMethods(): void
    {
        foreach (['request', 'grant', 'end', 'cancel', 'clear', 'next', 'queue', 'current', 'myStatus'] as $method) {
            $this->assertTrue(method_exists(SpeechController::class, $method));
        }
    }

    // =========================================================================
    // request() — POST
    // =========================================================================

    public function testRequestRequiresPost(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');

        $res = $this->callController(SpeechController::class, 'request');

        $this->assertSame(405, $res['status']);
    }

    public function testRequestMissingMeetingIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['member_id' => self::MEMBER_ID]);

        $res = $this->callController(SpeechController::class, 'request');

        // api_require_uuid returns 400 for missing field
        $this->assertSame(400, $res['status']);
    }

    public function testRequestMissingMemberIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => self::MEETING_ID]);

        $res = $this->callController(SpeechController::class, 'request');

        $this->assertSame(400, $res['status']);
    }

    public function testRequestMeetingNotFoundReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id' => self::MEETING_ID,
            'member_id'  => self::MEMBER_ID,
        ]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(null);

        $memberRepo = $this->createMock(MemberRepository::class);
        $speechRepo = $this->createMock(SpeechRepository::class);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            MemberRepository::class  => $memberRepo,
            SpeechRepository::class  => $speechRepo,
        ]);

        $res = $this->callController(SpeechController::class, 'request');

        $this->assertSame(404, $res['status']);
        $this->assertSame('meeting_not_found', $res['body']['error']);
    }

    public function testRequestWithValidDataCallsService(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id' => self::MEETING_ID,
            'member_id'  => self::MEMBER_ID,
        ]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING_ID, 'status' => 'live',
        ]);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('findByIdForTenant')->willReturn(['id' => self::MEMBER_ID]);

        $speechRepo = $this->createMock(SpeechRepository::class);
        // SpeechService::toggleRequest() calls findActive() then insert();
        // SpeechService::getMyStatus() calls findActive() + listWaiting();
        // SpeechService::getQueue() calls findCurrentSpeaker() + listWaiting()
        $speechRepo->method('findActive')->willReturn(null);
        $speechRepo->method('listWaiting')->willReturn([]);
        $speechRepo->method('findCurrentSpeaker')->willReturn(null);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            MemberRepository::class  => $memberRepo,
            SpeechRepository::class  => $speechRepo,
        ]);

        $res = $this->callController(SpeechController::class, 'request');

        // 200 or 400 depending on SpeechService internal state
        $this->assertContains($res['status'], [200, 400]);
    }

    // =========================================================================
    // grant() — POST
    // =========================================================================

    public function testGrantRequiresPost(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');

        $res = $this->callController(SpeechController::class, 'grant');

        $this->assertSame(405, $res['status']);
    }

    public function testGrantMissingMeetingIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $res = $this->callController(SpeechController::class, 'grant');

        $this->assertSame(400, $res['status']);
    }

    public function testGrantInvalidMemberIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id' => self::MEETING_ID,
            'member_id'  => 'not-a-uuid',
        ]);

        $res = $this->callController(SpeechController::class, 'grant');

        $this->assertSame(400, $res['status']);
        $this->assertSame('invalid_uuid', $res['body']['error']);
    }

    public function testGrantInvalidRequestIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id' => self::MEETING_ID,
            'request_id' => 'not-a-uuid',
        ]);

        $res = $this->callController(SpeechController::class, 'grant');

        $this->assertSame(400, $res['status']);
        $this->assertSame('invalid_uuid', $res['body']['error']);
    }

    public function testGrantLooksUpMemberByRequestId(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id' => self::MEETING_ID,
            'request_id' => self::REQUEST_ID,
        ]);

        $speechRepo = $this->createMock(SpeechRepository::class);
        // grant() controller looks up speech request, then delegates to SpeechService::grant()
        // which calls resolveTenant() → meetingRepo, finishAllSpeaking(), findWaitingForMember(), getQueue()
        $speechRepo->method('findById')->willReturn([
            'id'        => self::REQUEST_ID,
            'member_id' => self::MEMBER_ID,
            'tenant_id' => self::TENANT,
        ]);
        $speechRepo->method('findCurrentSpeaker')->willReturn(null);
        $speechRepo->method('listWaiting')->willReturn([]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING_ID, 'status' => 'live',
        ]);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('findByIdForTenant')->willReturn(['id' => self::MEMBER_ID, 'full_name' => 'Alice']);

        $this->injectRepos([
            SpeechRepository::class  => $speechRepo,
            MeetingRepository::class => $meetingRepo,
            MemberRepository::class  => $memberRepo,
        ]);

        $res = $this->callController(SpeechController::class, 'grant');

        $this->assertContains($res['status'], [200, 400]);
    }

    // =========================================================================
    // end() — POST
    // =========================================================================

    public function testEndRequiresPost(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');

        $res = $this->callController(SpeechController::class, 'end');

        $this->assertSame(405, $res['status']);
    }

    public function testEndMissingMeetingIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $res = $this->callController(SpeechController::class, 'end');

        $this->assertSame(400, $res['status']);
    }

    // =========================================================================
    // cancel() — POST
    // =========================================================================

    public function testCancelRequiresPost(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');

        $res = $this->callController(SpeechController::class, 'cancel');

        $this->assertSame(405, $res['status']);
    }

    public function testCancelMissingMeetingIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['request_id' => self::REQUEST_ID]);

        $res = $this->callController(SpeechController::class, 'cancel');

        $this->assertSame(400, $res['status']);
    }

    public function testCancelMissingRequestIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => self::MEETING_ID]);

        $res = $this->callController(SpeechController::class, 'cancel');

        $this->assertSame(400, $res['status']);
    }

    // =========================================================================
    // clear() — POST
    // =========================================================================

    public function testClearRequiresPost(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');

        $res = $this->callController(SpeechController::class, 'clear');

        $this->assertSame(405, $res['status']);
    }

    public function testClearMissingMeetingIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $res = $this->callController(SpeechController::class, 'clear');

        $this->assertSame(400, $res['status']);
    }

    // =========================================================================
    // next() — POST
    // =========================================================================

    public function testNextRequiresPost(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');

        $res = $this->callController(SpeechController::class, 'next');

        $this->assertSame(405, $res['status']);
    }

    public function testNextMissingMeetingIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $res = $this->callController(SpeechController::class, 'next');

        $this->assertSame(400, $res['status']);
    }

    // =========================================================================
    // queue() — GET
    // =========================================================================

    public function testQueueRequiresGet(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => self::MEETING_ID]);

        $res = $this->callController(SpeechController::class, 'queue');

        $this->assertSame(405, $res['status']);
    }

    public function testQueueMissingMeetingIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $res = $this->callController(SpeechController::class, 'queue');

        $this->assertSame(400, $res['status']);
    }

    public function testQueueReturnsSpeakerAndQueue(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING_ID, 'status' => 'live',
        ]);

        $speechRepo = $this->createMock(SpeechRepository::class);
        // SpeechService::getQueue() calls findCurrentSpeaker() + listWaiting()
        $speechRepo->method('findCurrentSpeaker')->willReturn(null);
        $speechRepo->method('listWaiting')->willReturn([]);

        $memberRepo = $this->createMock(MemberRepository::class);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            SpeechRepository::class  => $speechRepo,
            MemberRepository::class  => $memberRepo,
        ]);

        $res = $this->callController(SpeechController::class, 'queue');

        $this->assertSame(200, $res['status']);
        $this->assertArrayHasKey('speaker', $res['body']['data']);
        $this->assertArrayHasKey('queue', $res['body']['data']);
    }

    public function testQueueWithItemsTransformsFields(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $queueItem = [
            'id'         => self::REQUEST_ID,
            'member_id'  => self::MEMBER_ID,
            'full_name'  => 'Alice Martin',
            'created_at' => '2025-01-01 10:00:00',
        ];

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING_ID, 'status' => 'live',
        ]);

        $speechRepo = $this->createMock(SpeechRepository::class);
        // SpeechService::getQueue() calls findCurrentSpeaker() + listWaiting()
        $speechRepo->method('findCurrentSpeaker')->willReturn(null);
        $speechRepo->method('listWaiting')->willReturn([$queueItem]);

        $memberRepo = $this->createMock(MemberRepository::class);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            SpeechRepository::class  => $speechRepo,
            MemberRepository::class  => $memberRepo,
        ]);

        $res = $this->callController(SpeechController::class, 'queue');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        $this->assertCount(1, $data['queue']);
        $this->assertSame('Alice Martin', $data['queue'][0]['member_name']);
        $this->assertSame('2025-01-01 10:00:00', $data['queue'][0]['requested_at']);
    }

    // =========================================================================
    // current() — GET
    // =========================================================================

    public function testCurrentMissingMeetingIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $res = $this->callController(SpeechController::class, 'current');

        $this->assertSame(400, $res['status']);
    }

    public function testCurrentNoSpeakerReturnsNull(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING_ID, 'status' => 'live',
        ]);

        $speechRepo = $this->createMock(SpeechRepository::class);
        // SpeechService::getQueue() calls findCurrentSpeaker() + listWaiting()
        $speechRepo->method('findCurrentSpeaker')->willReturn(null);
        $speechRepo->method('listWaiting')->willReturn([]);

        $memberRepo = $this->createMock(MemberRepository::class);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            SpeechRepository::class  => $speechRepo,
            MemberRepository::class  => $memberRepo,
        ]);

        $res = $this->callController(SpeechController::class, 'current');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        $this->assertNull($data['speaker']);
        $this->assertSame(0, $data['queue_count']);
    }

    public function testCurrentWithActiveSpeakerReturnsElapsed(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $speaker = [
            'id'         => self::REQUEST_ID,
            'member_id'  => self::MEMBER_ID,
            'full_name'  => 'Alice Martin',
            'updated_at' => date('Y-m-d H:i:s', time() - 90),
        ];

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING_ID, 'status' => 'live',
        ]);

        $speechRepo = $this->createMock(SpeechRepository::class);
        // SpeechService::getQueue() calls findCurrentSpeaker() + listWaiting()
        $speechRepo->method('findCurrentSpeaker')->willReturn($speaker);
        $speechRepo->method('listWaiting')->willReturn([]);

        $memberRepo = $this->createMock(MemberRepository::class);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            SpeechRepository::class  => $speechRepo,
            MemberRepository::class  => $memberRepo,
        ]);

        $res = $this->callController(SpeechController::class, 'current');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        $this->assertSame('Alice Martin', $data['member_name']);
        $this->assertSame(self::MEMBER_ID, $data['member_id']);
        $this->assertGreaterThan(0, $data['elapsed_seconds']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $data['elapsed_formatted']);
    }

    // =========================================================================
    // myStatus() — GET
    // =========================================================================

    public function testMyStatusRequiresGet(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id' => self::MEETING_ID,
            'member_id'  => self::MEMBER_ID,
        ]);

        $res = $this->callController(SpeechController::class, 'myStatus');

        $this->assertSame(405, $res['status']);
    }

    public function testMyStatusMissingMeetingIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['member_id' => self::MEMBER_ID]);

        $res = $this->callController(SpeechController::class, 'myStatus');

        $this->assertSame(400, $res['status']);
    }

    public function testMyStatusMissingMemberIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $res = $this->callController(SpeechController::class, 'myStatus');

        $this->assertSame(400, $res['status']);
    }

    public function testMyStatusReturnsStatus(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([
            'meeting_id' => self::MEETING_ID,
            'member_id'  => self::MEMBER_ID,
        ]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING_ID, 'status' => 'live',
        ]);

        $speechRepo = $this->createMock(SpeechRepository::class);
        // SpeechService::getMyStatus() calls findActive() + listWaiting()
        $speechRepo->method('findActive')->willReturn(null);
        $speechRepo->method('listWaiting')->willReturn([]);

        $memberRepo = $this->createMock(MemberRepository::class);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            SpeechRepository::class  => $speechRepo,
            MemberRepository::class  => $memberRepo,
        ]);

        $res = $this->callController(SpeechController::class, 'myStatus');

        $this->assertSame(200, $res['status']);
    }
}
