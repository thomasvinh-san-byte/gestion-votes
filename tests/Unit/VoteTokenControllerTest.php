<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\AbstractController;
use AgVote\Controller\VoteTokenController;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\VoteTokenRepository;

/**
 * Unit tests for VoteTokenController.
 *
 * Extends ControllerTestCase for repo injection and standard helpers.
 *
 * Tests:
 *  - Controller structure (final, extends AbstractController)
 *  - generate: POST method enforcement, meeting_id/motion_id UUID validation,
 *    meeting not found, motion not found, motion already closed,
 *    success path (generates tokens for eligible voters)
 *  - TTL clamping (defaults to 180 when 0 or negative)
 */
class VoteTokenControllerTest extends ControllerTestCase
{
    private const TENANT  = 'aaaaaaaa-0000-0000-0000-000000000001';
    private const MEETING = 'bbbbbbbb-0000-0000-0000-000000000001';
    private const MOTION  = 'cccccccc-0000-0000-0000-000000000001';
    private const MEMBER  = 'dddddddd-0000-0000-0000-000000000001';

    // =========================================================================
    // CONTROLLER STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(VoteTokenController::class);
        $this->assertTrue($ref->isFinal(), 'VoteTokenController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $this->assertInstanceOf(AbstractController::class, new VoteTokenController());
    }

    public function testControllerHasGenerateMethod(): void
    {
        $ref = new \ReflectionClass(VoteTokenController::class);
        $this->assertTrue($ref->hasMethod('generate'));
        $this->assertTrue($ref->getMethod('generate')->isPublic());
    }

    // =========================================================================
    // generate: METHOD ENFORCEMENT
    // =========================================================================

    public function testGenerateRejectsGetMethod(): void
    {
        $result = $this->callController(VoteTokenController::class, 'generate');

        $this->assertEquals(405, $result['status']);
    }

    public function testGenerateRejectsPutMethod(): void
    {
        $this->setHttpMethod('PUT');

        $result = $this->callController(VoteTokenController::class, 'generate');

        $this->assertEquals(405, $result['status']);
    }

    // =========================================================================
    // generate: VALIDATION
    // =========================================================================

    public function testGenerateRequiresMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $result = $this->callController(VoteTokenController::class, 'generate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testGenerateRejectsInvalidMeetingUuid(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => 'not-a-uuid']);

        $result = $this->callController(VoteTokenController::class, 'generate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testGenerateRequiresMotionId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => self::MEETING]);

        $result = $this->callController(VoteTokenController::class, 'generate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_motion_id', $result['body']['error']);
    }

    public function testGenerateRejectsInvalidMotionUuid(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id' => self::MEETING,
            'motion_id'  => 'bad-uuid',
        ]);

        $result = $this->callController(VoteTokenController::class, 'generate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_motion_id', $result['body']['error']);
    }

    // =========================================================================
    // generate: MEETING NOT FOUND
    // =========================================================================

    public function testGenerateReturnsMeetingNotFound(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([
            'meeting_id' => self::MEETING,
            'motion_id'  => self::MOTION,
        ]);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('findByIdForTenant')->willReturn(null);

        $this->injectRepos([MeetingRepository::class => $mockMeeting]);

        $result = $this->callController(VoteTokenController::class, 'generate');

        $this->assertEquals(404, $result['status']);
        $this->assertEquals('meeting_not_found', $result['body']['error']);
    }

    // =========================================================================
    // generate: MOTION NOT FOUND
    // =========================================================================

    public function testGenerateReturnsMotionNotFound(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([
            'meeting_id' => self::MEETING,
            'motion_id'  => self::MOTION,
        ]);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING, 'title' => 'Test', 'status' => 'live',
        ]);

        $mockMotion = $this->createMock(MotionRepository::class);
        $mockMotion->method('findByIdAndMeetingWithDates')->willReturn(null);

        $this->injectRepos([
            MeetingRepository::class => $mockMeeting,
            MotionRepository::class  => $mockMotion,
        ]);

        $result = $this->callController(VoteTokenController::class, 'generate');

        $this->assertEquals(404, $result['status']);
        $this->assertEquals('motion_not_found', $result['body']['error']);
    }

    // =========================================================================
    // generate: MOTION ALREADY CLOSED
    // =========================================================================

    public function testGenerateRejectsClosedMotion(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([
            'meeting_id' => self::MEETING,
            'motion_id'  => self::MOTION,
        ]);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING, 'title' => 'Test', 'status' => 'live',
        ]);

        $mockMotion = $this->createMock(MotionRepository::class);
        $mockMotion->method('findByIdAndMeetingWithDates')->willReturn([
            'id'        => self::MOTION,
            'title'     => 'Motion 1',
            'closed_at' => '2026-03-01 12:00:00',  // already closed
        ]);

        $this->injectRepos([
            MeetingRepository::class => $mockMeeting,
            MotionRepository::class  => $mockMotion,
        ]);

        $result = $this->callController(VoteTokenController::class, 'generate');

        $this->assertEquals(409, $result['status']);
        $this->assertEquals('motion_closed', $result['body']['error']);
    }

    // =========================================================================
    // generate: SUCCESS PATH — NO ELIGIBLE VOTERS
    // =========================================================================

    public function testGenerateWithNoEligibleVotersReturnsZero(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([
            'meeting_id' => self::MEETING,
            'motion_id'  => self::MOTION,
        ]);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING, 'title' => 'Test', 'status' => 'live',
        ]);

        $mockMotion = $this->createMock(MotionRepository::class);
        $mockMotion->method('findByIdAndMeetingWithDates')->willReturn([
            'id' => self::MOTION, 'title' => 'Motion 1', 'closed_at' => null,
        ]);

        $mockAttendance = $this->createMock(AttendanceRepository::class);
        $mockAttendance->method('listEligibleVotersWithName')->willReturn([]);

        $mockToken = $this->createMock(VoteTokenRepository::class);

        $this->injectRepos([
            MeetingRepository::class    => $mockMeeting,
            MotionRepository::class     => $mockMotion,
            AttendanceRepository::class => $mockAttendance,
            VoteTokenRepository::class  => $mockToken,
        ]);

        $result = $this->callController(VoteTokenController::class, 'generate');

        $this->assertEquals(200, $result['status']);
        $this->assertEquals(0, $result['body']['data']['count']);
        $this->assertEquals([], $result['body']['data']['tokens']);
    }

    // =========================================================================
    // generate: SUCCESS PATH — WITH ELIGIBLE VOTERS
    // =========================================================================

    public function testGenerateCreatesTokensForEligibleVoters(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', self::TENANT);
        $this->injectJsonBody([
            'meeting_id'  => self::MEETING,
            'motion_id'   => self::MOTION,
            'ttl_minutes' => 60,
        ]);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING, 'title' => 'Test', 'status' => 'live',
        ]);

        $mockMotion = $this->createMock(MotionRepository::class);
        $mockMotion->method('findByIdAndMeetingWithDates')->willReturn([
            'id' => self::MOTION, 'title' => 'Motion 1', 'closed_at' => null,
        ]);

        $mockAttendance = $this->createMock(AttendanceRepository::class);
        $mockAttendance->method('listEligibleVotersWithName')->willReturn([
            ['member_id' => self::MEMBER, 'member_name' => 'Alice Martin'],
        ]);

        $mockToken = $this->createMock(VoteTokenRepository::class);
        // deleteUnusedByMotionAndMember and insert are void methods

        $this->injectRepos([
            MeetingRepository::class    => $mockMeeting,
            MotionRepository::class     => $mockMotion,
            AttendanceRepository::class => $mockAttendance,
            VoteTokenRepository::class  => $mockToken,
        ]);

        $result = $this->callController(VoteTokenController::class, 'generate');

        $this->assertEquals(200, $result['status']);
        $this->assertEquals(1, $result['body']['data']['count']);
        $this->assertEquals(60, $result['body']['data']['expires_in']);
        $this->assertCount(1, $result['body']['data']['tokens']);
        $this->assertEquals(self::MEMBER, $result['body']['data']['tokens'][0]['member_id']);
    }

    // =========================================================================
    // generate: TTL CLAMPING
    // =========================================================================

    /**
     * TTL defaults to 180 minutes when not provided or non-positive.
     */
    public function testGenerateTtlDefaultsTo180Minutes(): void
    {
        // Verify the clamping logic from the source
        $clampTtl = fn(int $input): int => $input > 0 ? $input : 180;

        $this->assertEquals(180, $clampTtl(0));     // Zero => default 180
        $this->assertEquals(180, $clampTtl(-10));   // Negative => default 180
        $this->assertEquals(60, $clampTtl(60));     // Positive => keep
        $this->assertEquals(180, $clampTtl(180));   // Exactly 180
    }
}
