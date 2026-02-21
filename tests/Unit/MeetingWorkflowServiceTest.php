<?php

declare(strict_types=1);

namespace AgVote\Tests\Unit;

use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MeetingStatsRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\UserRepository;
use AgVote\Service\MeetingWorkflowService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MeetingWorkflowService.
 *
 * All repository dependencies are mocked; no database connection is needed.
 */
class MeetingWorkflowServiceTest extends TestCase {
    private const TENANT = 'aaaaaaaa-1111-2222-3333-444444444444';
    private const MEETING = 'bbbbbbbb-1111-2222-3333-444444444444';

    private MeetingRepository&MockObject $meetingRepo;
    private MotionRepository&MockObject $motionRepo;
    private AttendanceRepository&MockObject $attendanceRepo;
    private UserRepository&MockObject $userRepo;
    private MeetingStatsRepository&MockObject $statsRepo;
    private MeetingWorkflowService $service;

    protected function setUp(): void {
        $this->meetingRepo = $this->createMock(MeetingRepository::class);
        $this->motionRepo = $this->createMock(MotionRepository::class);
        $this->attendanceRepo = $this->createMock(AttendanceRepository::class);
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->statsRepo = $this->createMock(MeetingStatsRepository::class);

        $this->service = new MeetingWorkflowService(
            $this->meetingRepo,
            $this->motionRepo,
            $this->attendanceRepo,
            $this->userRepo,
            $this->statsRepo,
        );
    }

    /**
     * Returns a meeting array with the given status.
     */
    private function meetingWithStatus(string $status): array {
        return [
            'id' => self::MEETING,
            'tenant_id' => self::TENANT,
            'status' => $status,
        ];
    }

    // =========================================================================
    // issuesBeforeTransition() -- meeting not found
    // =========================================================================

    public function testIssuesBeforeTransitionReturnsErrorWhenMeetingNotFound(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn(null);

        $result = $this->service->issuesBeforeTransition(self::MEETING, self::TENANT, 'scheduled');

        $this->assertFalse($result['can_proceed']);
        $this->assertCount(1, $result['issues']);
        $this->assertSame('meeting_not_found', $result['issues'][0]['code']);
    }

    // =========================================================================
    // issuesBeforeTransition() -- draft -> scheduled
    // =========================================================================

    public function testDraftToScheduledNoMotionsReturnsIssue(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('draft'));

        $this->motionRepo->method('countForMeeting')
            ->with(self::MEETING)
            ->willReturn(0);

        $result = $this->service->issuesBeforeTransition(self::MEETING, self::TENANT, 'scheduled');

        $this->assertFalse($result['can_proceed']);
        $this->assertCount(1, $result['issues']);
        $this->assertSame('no_motions', $result['issues'][0]['code']);
    }

    public function testDraftToScheduledWithMotionsCanProceed(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('draft'));

        $this->motionRepo->method('countForMeeting')
            ->with(self::MEETING)
            ->willReturn(3);

        $result = $this->service->issuesBeforeTransition(self::MEETING, self::TENANT, 'scheduled');

        $this->assertTrue($result['can_proceed']);
        $this->assertEmpty($result['issues']);
    }

    // =========================================================================
    // issuesBeforeTransition() -- scheduled -> frozen
    // =========================================================================

    public function testScheduledToFrozenNoAttendanceReturnsIssue(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('scheduled'));

        $this->attendanceRepo->method('countPresentOrRemote')
            ->with(self::MEETING, self::TENANT)
            ->willReturn(0);

        // President check also runs in this transition
        $this->userRepo->method('findExistingPresident')
            ->willReturn(null);

        $result = $this->service->issuesBeforeTransition(self::MEETING, self::TENANT, 'frozen');

        $this->assertFalse($result['can_proceed']);
        $this->assertCount(1, $result['issues']);
        $this->assertSame('no_attendance', $result['issues'][0]['code']);
    }

    public function testScheduledToFrozenWithAttendanceCanProceed(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('scheduled'));

        $this->attendanceRepo->method('countPresentOrRemote')
            ->willReturn(5);

        $this->userRepo->method('findExistingPresident')
            ->willReturn('user-president-id');

        $result = $this->service->issuesBeforeTransition(self::MEETING, self::TENANT, 'frozen');

        $this->assertTrue($result['can_proceed']);
        $this->assertEmpty($result['issues']);
        $this->assertEmpty($result['warnings']);
    }

    public function testScheduledToFrozenWithAttendanceButNoPresidentWarns(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('scheduled'));

        $this->attendanceRepo->method('countPresentOrRemote')
            ->willReturn(5);

        $this->userRepo->method('findExistingPresident')
            ->willReturn(null);

        $result = $this->service->issuesBeforeTransition(self::MEETING, self::TENANT, 'frozen');

        $this->assertTrue($result['can_proceed']);
        $this->assertEmpty($result['issues']);
        $this->assertCount(1, $result['warnings']);
        $this->assertSame('no_president', $result['warnings'][0]['code']);
    }

    // =========================================================================
    // issuesBeforeTransition() -- live -> closed
    // =========================================================================

    public function testLiveToClosedOpenMotionsReturnsIssue(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('live'));

        $this->statsRepo->method('countOpenMotions')
            ->with(self::MEETING)
            ->willReturn(2);

        $result = $this->service->issuesBeforeTransition(self::MEETING, self::TENANT, 'closed');

        $this->assertFalse($result['can_proceed']);
        $this->assertCount(1, $result['issues']);
        $this->assertSame('motion_open', $result['issues'][0]['code']);
        $this->assertStringContainsString('2', $result['issues'][0]['msg']);
    }

    public function testLiveToClosedAllClosedCanProceed(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('live'));

        $this->statsRepo->method('countOpenMotions')
            ->willReturn(0);

        $result = $this->service->issuesBeforeTransition(self::MEETING, self::TENANT, 'closed');

        $this->assertTrue($result['can_proceed']);
        $this->assertEmpty($result['issues']);
    }

    // =========================================================================
    // issuesBeforeTransition() -- paused -> closed
    // =========================================================================

    public function testPausedToClosedOpenMotionsReturnsIssue(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('paused'));

        $this->statsRepo->method('countOpenMotions')
            ->willReturn(1);

        $result = $this->service->issuesBeforeTransition(self::MEETING, self::TENANT, 'closed');

        $this->assertFalse($result['can_proceed']);
        $this->assertSame('motion_open', $result['issues'][0]['code']);
    }

    public function testPausedToClosedAllClosedCanProceed(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('paused'));

        $this->statsRepo->method('countOpenMotions')
            ->willReturn(0);

        $result = $this->service->issuesBeforeTransition(self::MEETING, self::TENANT, 'closed');

        $this->assertTrue($result['can_proceed']);
        $this->assertEmpty($result['issues']);
    }

    // =========================================================================
    // issuesBeforeTransition() -- live -> paused
    // =========================================================================

    public function testLiveToPausedOpenMotionReturnsIssue(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('live'));

        $this->statsRepo->method('countOpenMotions')
            ->willReturn(1);

        $result = $this->service->issuesBeforeTransition(self::MEETING, self::TENANT, 'paused');

        $this->assertFalse($result['can_proceed']);
        $this->assertSame('motion_open', $result['issues'][0]['code']);
        $this->assertStringContainsString('pause', $result['issues'][0]['msg']);
    }

    public function testLiveToPausedNoOpenMotionsCanProceed(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('live'));

        $this->statsRepo->method('countOpenMotions')
            ->willReturn(0);

        $result = $this->service->issuesBeforeTransition(self::MEETING, self::TENANT, 'paused');

        $this->assertTrue($result['can_proceed']);
        $this->assertEmpty($result['issues']);
    }

    // =========================================================================
    // issuesBeforeTransition() -- closed -> validated
    // =========================================================================

    public function testClosedToValidatedBadResultsReturnsIssue(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('closed'));

        $this->statsRepo->method('countClosedMotions')
            ->willReturn(5);

        $this->motionRepo->method('countBadClosedMotions')
            ->with(self::MEETING)
            ->willReturn(2);

        $this->motionRepo->method('countConsolidatedMotions')
            ->willReturn(5);

        $result = $this->service->issuesBeforeTransition(self::MEETING, self::TENANT, 'validated');

        $this->assertFalse($result['can_proceed']);
        $this->assertSame('bad_results', $result['issues'][0]['code']);
        $this->assertStringContainsString('2', $result['issues'][0]['msg']);
    }

    public function testClosedToValidatedAllGoodCanProceed(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('closed'));

        $this->statsRepo->method('countClosedMotions')
            ->willReturn(5);

        $this->motionRepo->method('countBadClosedMotions')
            ->willReturn(0);

        $this->motionRepo->method('countConsolidatedMotions')
            ->willReturn(5);

        $result = $this->service->issuesBeforeTransition(self::MEETING, self::TENANT, 'validated');

        $this->assertTrue($result['can_proceed']);
        $this->assertEmpty($result['issues']);
        $this->assertEmpty($result['warnings']);
    }

    public function testClosedToValidatedNotConsolidatedWarns(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('closed'));

        $this->statsRepo->method('countClosedMotions')
            ->willReturn(5);

        $this->motionRepo->method('countBadClosedMotions')
            ->willReturn(0);

        $this->motionRepo->method('countConsolidatedMotions')
            ->willReturn(3); // 3 out of 5 consolidated

        $result = $this->service->issuesBeforeTransition(self::MEETING, self::TENANT, 'validated');

        $this->assertTrue($result['can_proceed']);
        $this->assertEmpty($result['issues']);
        $this->assertCount(1, $result['warnings']);
        $this->assertSame('not_consolidated', $result['warnings'][0]['code']);
    }

    // =========================================================================
    // issuesBeforeTransition() -- archived -> any (always blocked)
    // =========================================================================

    public function testArchivedAlwaysBlocks(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('archived'));

        $result = $this->service->issuesBeforeTransition(self::MEETING, self::TENANT, 'live');

        $this->assertFalse($result['can_proceed']);
        $this->assertSame('archived_immutable', $result['issues'][0]['code']);
    }

    public function testArchivedBlocksTransitionToDraft(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('archived'));

        $result = $this->service->issuesBeforeTransition(self::MEETING, self::TENANT, 'draft');

        $this->assertFalse($result['can_proceed']);
        $this->assertSame('archived_immutable', $result['issues'][0]['code']);
    }

    public function testArchivedBlocksTransitionToScheduled(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('archived'));

        $result = $this->service->issuesBeforeTransition(self::MEETING, self::TENANT, 'scheduled');

        $this->assertFalse($result['can_proceed']);
        $this->assertSame('archived_immutable', $result['issues'][0]['code']);
    }

    // =========================================================================
    // issuesBeforeTransition() -- fromStatusOverride
    // =========================================================================

    public function testFromStatusOverrideIsRespected(): void {
        // Meeting is 'live' in DB, but we override from status to 'draft'
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('live'));

        $this->motionRepo->method('countForMeeting')
            ->willReturn(0);

        $result = $this->service->issuesBeforeTransition(
            self::MEETING,
            self::TENANT,
            'scheduled',
            'draft',
        );

        // Should check draft->scheduled rules, not live->scheduled
        $this->assertFalse($result['can_proceed']);
        $this->assertSame('no_motions', $result['issues'][0]['code']);
    }

    // =========================================================================
    // issuesBeforeTransition() -- unmatched transitions (no specific rules)
    // =========================================================================

    public function testUnmatchedTransitionHasNoIssues(): void {
        // A transition not specifically checked (e.g., validated -> archived)
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('validated'));

        $result = $this->service->issuesBeforeTransition(self::MEETING, self::TENANT, 'archived');

        $this->assertTrue($result['can_proceed']);
        $this->assertEmpty($result['issues']);
    }

    // =========================================================================
    // hasMotions()
    // =========================================================================

    public function testHasMotionsReturnsTrueWhenMotionsExist(): void {
        $this->motionRepo->method('countForMeeting')
            ->with(self::MEETING)
            ->willReturn(3);

        $this->assertTrue($this->service->hasMotions(self::MEETING));
    }

    public function testHasMotionsReturnsFalseWhenNoMotions(): void {
        $this->motionRepo->method('countForMeeting')
            ->with(self::MEETING)
            ->willReturn(0);

        $this->assertFalse($this->service->hasMotions(self::MEETING));
    }

    // =========================================================================
    // hasAttendance()
    // =========================================================================

    public function testHasAttendanceReturnsTrueWhenPresentOrRemote(): void {
        $this->attendanceRepo->method('countPresentOrRemote')
            ->with(self::MEETING, self::TENANT)
            ->willReturn(5);

        $this->assertTrue($this->service->hasAttendance(self::MEETING, self::TENANT));
    }

    public function testHasAttendanceReturnsFalseWhenNone(): void {
        $this->attendanceRepo->method('countPresentOrRemote')
            ->with(self::MEETING, self::TENANT)
            ->willReturn(0);

        $this->assertFalse($this->service->hasAttendance(self::MEETING, self::TENANT));
    }

    // =========================================================================
    // hasPresident()
    // =========================================================================

    public function testHasPresidentReturnsTrueWhenPresidentExists(): void {
        $this->userRepo->method('findExistingPresident')
            ->with(self::TENANT, self::MEETING)
            ->willReturn('user-president-id');

        $this->assertTrue($this->service->hasPresident(self::MEETING, self::TENANT));
    }

    public function testHasPresidentReturnsFalseWhenNoPresident(): void {
        $this->userRepo->method('findExistingPresident')
            ->with(self::TENANT, self::MEETING)
            ->willReturn(null);

        $this->assertFalse($this->service->hasPresident(self::MEETING, self::TENANT));
    }

    public function testHasPresidentReturnsFalseWhenTenantIdIsEmpty(): void {
        $this->assertFalse($this->service->hasPresident(self::MEETING, ''));
    }

    // =========================================================================
    // countOpenMotions()
    // =========================================================================

    public function testCountOpenMotionsReturnsCountFromStatsRepo(): void {
        $this->statsRepo->method('countOpenMotions')
            ->with(self::MEETING)
            ->willReturn(3);

        $this->assertSame(3, $this->service->countOpenMotions(self::MEETING));
    }

    public function testCountOpenMotionsReturnsZeroWhenNone(): void {
        $this->statsRepo->method('countOpenMotions')
            ->willReturn(0);

        $this->assertSame(0, $this->service->countOpenMotions(self::MEETING));
    }

    // =========================================================================
    // allMotionsClosed()
    // =========================================================================

    public function testAllMotionsClosedReturnsTrueWhenZeroOpen(): void {
        $this->statsRepo->method('countOpenMotions')
            ->willReturn(0);

        $this->assertTrue($this->service->allMotionsClosed(self::MEETING));
    }

    public function testAllMotionsClosedReturnsFalseWhenSomeOpen(): void {
        $this->statsRepo->method('countOpenMotions')
            ->willReturn(1);

        $this->assertFalse($this->service->allMotionsClosed(self::MEETING));
    }

    // =========================================================================
    // getTransitionReadiness()
    // =========================================================================

    public function testGetTransitionReadinessReturnsErrorWhenMeetingNotFound(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn(null);

        $result = $this->service->getTransitionReadiness(self::MEETING, self::TENANT);

        $this->assertSame('meeting_not_found', $result['error']);
    }

    public function testGetTransitionReadinessForDraftMeeting(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('draft'));

        // For draft->scheduled check: has motions
        $this->motionRepo->method('countForMeeting')
            ->willReturn(2);

        $result = $this->service->getTransitionReadiness(self::MEETING, self::TENANT);

        $this->assertSame('draft', $result['current_status']);
        $this->assertArrayHasKey('scheduled', $result['transitions']);
        $this->assertTrue($result['transitions']['scheduled']['can_proceed']);
    }

    public function testGetTransitionReadinessForDraftMeetingWithoutMotions(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('draft'));

        $this->motionRepo->method('countForMeeting')
            ->willReturn(0);

        $result = $this->service->getTransitionReadiness(self::MEETING, self::TENANT);

        $this->assertSame('draft', $result['current_status']);
        $this->assertArrayHasKey('scheduled', $result['transitions']);
        $this->assertFalse($result['transitions']['scheduled']['can_proceed']);
    }

    public function testGetTransitionReadinessForLiveMeeting(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('live'));

        $this->statsRepo->method('countOpenMotions')
            ->willReturn(0);

        $result = $this->service->getTransitionReadiness(self::MEETING, self::TENANT);

        $this->assertSame('live', $result['current_status']);
        // Live has two possible transitions: paused, closed
        $this->assertArrayHasKey('paused', $result['transitions']);
        $this->assertArrayHasKey('closed', $result['transitions']);
    }

    public function testGetTransitionReadinessForArchivedMeetingHasNoTransitions(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('archived'));

        $result = $this->service->getTransitionReadiness(self::MEETING, self::TENANT);

        $this->assertSame('archived', $result['current_status']);
        $this->assertEmpty($result['transitions']);
    }

    public function testGetTransitionReadinessForScheduledMeeting(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('scheduled'));

        // For scheduled->frozen check: has attendance + president
        $this->attendanceRepo->method('countPresentOrRemote')
            ->willReturn(10);
        $this->userRepo->method('findExistingPresident')
            ->willReturn('user-id');

        // For scheduled->draft check: no specific rules
        $this->motionRepo->method('countForMeeting')
            ->willReturn(2);

        $result = $this->service->getTransitionReadiness(self::MEETING, self::TENANT);

        $this->assertSame('scheduled', $result['current_status']);
        $this->assertArrayHasKey('frozen', $result['transitions']);
        $this->assertArrayHasKey('draft', $result['transitions']);
        $this->assertTrue($result['transitions']['frozen']['can_proceed']);
    }

    public function testGetTransitionReadinessForClosedMeeting(): void {
        $this->meetingRepo->method('findByIdForTenant')
            ->willReturn($this->meetingWithStatus('closed'));

        $this->statsRepo->method('countClosedMotions')
            ->willReturn(3);
        $this->motionRepo->method('countBadClosedMotions')
            ->willReturn(0);
        $this->motionRepo->method('countConsolidatedMotions')
            ->willReturn(3);

        $result = $this->service->getTransitionReadiness(self::MEETING, self::TENANT);

        $this->assertSame('closed', $result['current_status']);
        $this->assertArrayHasKey('validated', $result['transitions']);
        $this->assertTrue($result['transitions']['validated']['can_proceed']);
    }
}
