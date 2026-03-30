<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\AbstractController;
use AgVote\Controller\ProjectorController;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MeetingStatsRepository;
use AgVote\Repository\MotionRepository;

/**
 * Unit tests for ProjectorController.
 *
 * Extends ControllerTestCase for repo injection and standard helpers.
 *
 * Tests:
 *  - Controller structure (final, extends AbstractController)
 *  - state(): GET only enforcement
 *  - No live meetings => 404
 *  - Multiple live meetings => choose prompt
 *  - Single live meeting with idle phase (no motions)
 *  - Single live meeting with active phase (open motion)
 *  - Single live meeting with closed phase (last closed motion)
 *  - Explicit meeting_id in query
 *  - Explicit meeting_id not found => 404
 *  - Archived meeting => 404
 */
class ProjectorControllerTest extends ControllerTestCase
{
    private const TENANT   = 'aaaaaaaa-0000-0000-0000-000000000001';
    private const MEETING  = 'bbbbbbbb-0000-0000-0000-000000000001';
    private const MEETING2 = 'bbbbbbbb-0000-0000-0000-000000000002';
    private const MOTION   = 'cccccccc-0000-0000-0000-000000000001';

    // =========================================================================
    // CONTROLLER STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(ProjectorController::class);
        $this->assertTrue($ref->isFinal(), 'ProjectorController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $this->assertInstanceOf(AbstractController::class, new ProjectorController());
    }

    public function testControllerHasStateMethod(): void
    {
        $ref = new \ReflectionClass(ProjectorController::class);
        $this->assertTrue($ref->hasMethod('state'));
        $this->assertTrue($ref->getMethod('state')->isPublic());
    }

    // =========================================================================
    // state(): METHOD ENFORCEMENT
    // =========================================================================

    public function testStateRejectsPostMethod(): void
    {
        $this->setHttpMethod('POST');

        $result = $this->callController(ProjectorController::class, 'state');

        $this->assertEquals(405, $result['status']);
    }

    public function testStateRejectsPutMethod(): void
    {
        $this->setHttpMethod('PUT');

        $result = $this->callController(ProjectorController::class, 'state');

        $this->assertEquals(405, $result['status']);
    }

    // =========================================================================
    // state(): NO LIVE MEETING
    // =========================================================================

    public function testStateReturnsNoLiveMeetingWhenNoneExist(): void
    {
        $this->setAuth('user-1', 'operator', self::TENANT);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('listLiveForTenant')->willReturn([]);

        $mockMotion = $this->createMock(MotionRepository::class);

        $this->injectRepos([
            MeetingRepository::class => $mockMeeting,
            MotionRepository::class  => $mockMotion,
        ]);

        $result = $this->callController(ProjectorController::class, 'state');

        $this->assertEquals(404, $result['status']);
        $this->assertEquals('no_live_meeting', $result['body']['error']);
    }

    // =========================================================================
    // state(): MULTIPLE LIVE MEETINGS (choose prompt)
    // =========================================================================

    public function testStateReturnsChoosePromptForMultipleLiveMeetings(): void
    {
        $this->setAuth('user-1', 'operator', self::TENANT);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('listLiveForTenant')->willReturn([
            ['id' => self::MEETING,  'title' => 'Meeting A', 'started_at' => null],
            ['id' => self::MEETING2, 'title' => 'Meeting B', 'started_at' => null],
        ]);

        $mockMotion = $this->createMock(MotionRepository::class);

        $this->injectRepos([
            MeetingRepository::class => $mockMeeting,
            MotionRepository::class  => $mockMotion,
        ]);

        $result = $this->callController(ProjectorController::class, 'state');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['data']['choose'] ?? false);
        $this->assertCount(2, $result['body']['data']['meetings']);
    }

    // =========================================================================
    // state(): SINGLE LIVE MEETING — IDLE PHASE
    // =========================================================================

    public function testStateReturnsIdlePhaseWhenNoMotions(): void
    {
        $this->setAuth('user-1', 'operator', self::TENANT);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('listLiveForTenant')->willReturn([
            ['id' => self::MEETING, 'title' => 'Test AG', 'status' => 'live'],
        ]);

        $mockMotion = $this->createMock(MotionRepository::class);
        $mockMotion->method('findOpenForProjector')->willReturn(null);
        $mockMotion->method('findLastClosedForProjector')->willReturn(null);
        $mockMotion->method('countForMeeting')->willReturn(5);

        $mockStats = $this->createMock(MeetingStatsRepository::class);
        $mockStats->method('countActiveMembers')->willReturn(42);

        $this->injectRepos([
            MeetingRepository::class      => $mockMeeting,
            MotionRepository::class       => $mockMotion,
            MeetingStatsRepository::class => $mockStats,
        ]);

        $result = $this->callController(ProjectorController::class, 'state');

        $this->assertEquals(200, $result['status']);
        $this->assertEquals('idle', $result['body']['data']['phase']);
        $this->assertNull($result['body']['data']['motion']);
        $this->assertEquals(42, $result['body']['data']['eligible_count']);
        $this->assertEquals(5, $result['body']['data']['total_motions']);
    }

    // =========================================================================
    // state(): SINGLE LIVE MEETING — ACTIVE PHASE
    // =========================================================================

    public function testStateReturnsActivePhaseWhenMotionOpen(): void
    {
        $this->setAuth('user-1', 'operator', self::TENANT);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('listLiveForTenant')->willReturn([
            ['id' => self::MEETING, 'title' => 'Test AG', 'status' => 'live'],
        ]);

        $mockMotion = $this->createMock(MotionRepository::class);
        $mockMotion->method('findOpenForProjector')->willReturn([
            'id'          => self::MOTION,
            'title'       => 'Motion 1',
            'description' => 'Description',
            'body'        => 'Body text',
            'secret'      => false,
            'position'    => 1,
        ]);
        $mockMotion->method('findLastClosedForProjector')->willReturn(null);
        $mockMotion->method('countForMeeting')->willReturn(3);

        $mockStats = $this->createMock(MeetingStatsRepository::class);
        $mockStats->method('countActiveMembers')->willReturn(10);

        $this->injectRepos([
            MeetingRepository::class      => $mockMeeting,
            MotionRepository::class       => $mockMotion,
            MeetingStatsRepository::class => $mockStats,
        ]);

        $result = $this->callController(ProjectorController::class, 'state');

        $this->assertEquals(200, $result['status']);
        $this->assertEquals('active', $result['body']['data']['phase']);
        $this->assertNotNull($result['body']['data']['motion']);
        $this->assertEquals(self::MOTION, $result['body']['data']['motion']['id']);
        $this->assertFalse($result['body']['data']['motion']['secret']);
    }

    // =========================================================================
    // state(): SINGLE LIVE MEETING — CLOSED PHASE
    // =========================================================================

    public function testStateReturnsClosedPhaseWhenLastMotionClosed(): void
    {
        $this->setAuth('user-1', 'operator', self::TENANT);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('listLiveForTenant')->willReturn([
            ['id' => self::MEETING, 'title' => 'Test AG', 'status' => 'live'],
        ]);

        $mockMotion = $this->createMock(MotionRepository::class);
        $mockMotion->method('findOpenForProjector')->willReturn(null);
        $mockMotion->method('findLastClosedForProjector')->willReturn([
            'id'          => self::MOTION,
            'title'       => 'Closed Motion',
            'description' => '',
            'body'        => '',
            'secret'      => true,
            'position'    => 2,
        ]);
        $mockMotion->method('countForMeeting')->willReturn(2);

        $mockStats = $this->createMock(MeetingStatsRepository::class);
        $mockStats->method('countActiveMembers')->willReturn(15);

        $this->injectRepos([
            MeetingRepository::class      => $mockMeeting,
            MotionRepository::class       => $mockMotion,
            MeetingStatsRepository::class => $mockStats,
        ]);

        $result = $this->callController(ProjectorController::class, 'state');

        $this->assertEquals(200, $result['status']);
        $this->assertEquals('closed', $result['body']['data']['phase']);
        $this->assertTrue($result['body']['data']['motion']['secret']);
    }

    // =========================================================================
    // state(): EXPLICIT meeting_id IN QUERY
    // =========================================================================

    public function testStateWithExplicitMeetingId(): void
    {
        $this->setAuth('user-1', 'operator', self::TENANT);
        $this->setQueryParams(['meeting_id' => self::MEETING]);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('findByIdForTenant')->willReturn([
            'id'     => self::MEETING,
            'title'  => 'Explicit Meeting',
            'status' => 'live',
        ]);

        $mockMotion = $this->createMock(MotionRepository::class);
        $mockMotion->method('findOpenForProjector')->willReturn(null);
        $mockMotion->method('findLastClosedForProjector')->willReturn(null);
        $mockMotion->method('countForMeeting')->willReturn(0);

        $mockStats = $this->createMock(MeetingStatsRepository::class);
        $mockStats->method('countActiveMembers')->willReturn(5);

        $this->injectRepos([
            MeetingRepository::class      => $mockMeeting,
            MotionRepository::class       => $mockMotion,
            MeetingStatsRepository::class => $mockStats,
        ]);

        $result = $this->callController(ProjectorController::class, 'state');

        $this->assertEquals(200, $result['status']);
        $this->assertEquals(self::MEETING, $result['body']['data']['meeting_id']);
        $this->assertEquals('Explicit Meeting', $result['body']['data']['meeting_title']);
    }

    public function testStateWithExplicitMeetingIdNotFound(): void
    {
        $this->setAuth('user-1', 'operator', self::TENANT);
        $this->setQueryParams(['meeting_id' => self::MEETING]);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('findByIdForTenant')->willReturn(null);

        $mockMotion = $this->createMock(MotionRepository::class);

        $this->injectRepos([
            MeetingRepository::class => $mockMeeting,
            MotionRepository::class  => $mockMotion,
        ]);

        $result = $this->callController(ProjectorController::class, 'state');

        $this->assertEquals(404, $result['status']);
        $this->assertEquals('meeting_not_found', $result['body']['error']);
    }

    public function testStateWithArchivedMeetingReturnsNotFound(): void
    {
        $this->setAuth('user-1', 'operator', self::TENANT);
        $this->setQueryParams(['meeting_id' => self::MEETING]);

        $mockMeeting = $this->createMock(MeetingRepository::class);
        $mockMeeting->method('findByIdForTenant')->willReturn([
            'id'     => self::MEETING,
            'title'  => 'Archived Meeting',
            'status' => 'archived',
        ]);

        $mockMotion = $this->createMock(MotionRepository::class);

        $this->injectRepos([
            MeetingRepository::class => $mockMeeting,
            MotionRepository::class  => $mockMotion,
        ]);

        $result = $this->callController(ProjectorController::class, 'state');

        $this->assertEquals(404, $result['status']);
        $this->assertEquals('meeting_not_found', $result['body']['error']);
    }
}
