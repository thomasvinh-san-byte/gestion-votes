<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Repository\AgendaRepository;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\ManualActionRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\NotificationRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Repository\VoteTokenRepository;
use AgVote\Service\MotionsService;
use AgVote\Service\NotificationsService;
use AgVote\Service\OfficialResultsService;
use AgVote\Service\VoteTokenService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for MotionsService.
 *
 * Repositories injected via constructor; no database connection needed.
 * Since OfficialResultsService, VoteTokenService, and NotificationsService are
 * final classes, they cannot be mocked directly — real instances with mocked repos
 * are used instead (same pattern as BallotsServiceTest).
 *
 * api_transaction() and audit_log() are no-ops in the test bootstrap.
 */
class MotionsServiceTest extends TestCase
{
    private const TENANT_ID  = 'aaaaaaaa-1111-2222-3333-444444444444';
    private const MOTION_ID  = 'cc000001-0000-4000-c000-000000000001';
    private const MEETING_ID = 'dd000002-0000-4000-d000-000000000002';

    private MotionRepository&MockObject $motionRepo;
    private MeetingRepository&MockObject $meetingRepo;
    private PolicyRepository&MockObject $policyRepo;
    private AgendaRepository&MockObject $agendaRepo;
    private ManualActionRepository&MockObject $manualActionRepo;
    private AttendanceRepository&MockObject $attendanceRepo;

    private OfficialResultsService $resultsService;
    private VoteTokenService $voteTokenService;
    private NotificationsService $notificationsService;

    private MotionsService $service;

    protected function setUp(): void
    {
        $this->motionRepo = $this->createMock(MotionRepository::class);
        $this->meetingRepo = $this->createMock(MeetingRepository::class);
        $this->policyRepo = $this->createMock(PolicyRepository::class);
        $this->agendaRepo = $this->createMock(AgendaRepository::class);
        $this->manualActionRepo = $this->createMock(ManualActionRepository::class);
        $this->attendanceRepo = $this->createMock(AttendanceRepository::class);

        // Final classes — instantiate with their own mocked repositories
        $this->resultsService = new OfficialResultsService(
            $this->motionRepo,
            $this->createMock(BallotRepository::class),
            $this->createMock(MemberRepository::class),
            $this->policyRepo,
            $this->attendanceRepo,
        );

        $this->voteTokenService = new VoteTokenService(
            $this->meetingRepo,
            $this->createMock(VoteTokenRepository::class),
        );

        $this->notificationsService = new NotificationsService(
            $this->meetingRepo,
            $this->createMock(NotificationRepository::class),
        );

        $this->service = new MotionsService(
            $this->motionRepo,
            $this->meetingRepo,
            $this->policyRepo,
            $this->agendaRepo,
            $this->manualActionRepo,
            $this->attendanceRepo,
            $this->resultsService,
            $this->voteTokenService,
            $this->notificationsService,
        );
    }

    // =========================================================================
    // overrideDecision() — happy path
    // =========================================================================

    /**
     * Happy path: closed motion → overrideDecision() called once,
     * returns meeting_id and motion_title.
     */
    public function testOverrideDecisionUpdatesMotionAndReturnsContext(): void
    {
        $this->motionRepo
            ->expects($this->once())
            ->method('findWithMeetingTenant')
            ->with(self::MOTION_ID, self::TENANT_ID)
            ->willReturn([
                'id' => self::MOTION_ID,
                'meeting_id' => self::MEETING_ID,
                'motion_title' => 'Résolution 1',
                'closed_at' => '2026-04-07 14:00:00',
            ]);

        $this->motionRepo
            ->expects($this->once())
            ->method('overrideDecision')
            ->with(self::MOTION_ID, 'adopted', 'Erreur de comptage', self::TENANT_ID);

        $result = $this->service->overrideDecision(
            self::MOTION_ID,
            'adopted',
            'Erreur de comptage',
            self::TENANT_ID,
        );

        $this->assertSame(self::MEETING_ID, $result['meeting_id']);
        $this->assertSame('Résolution 1', $result['motion_title']);
    }

    // =========================================================================
    // overrideDecision() — guard: motion must be closed
    // =========================================================================

    /**
     * Open motion (closed_at null) → throws RuntimeException('motion_not_closed').
     */
    public function testOverrideDecisionThrowsWhenMotionNotClosed(): void
    {
        $this->motionRepo
            ->expects($this->once())
            ->method('findWithMeetingTenant')
            ->willReturn([
                'id' => self::MOTION_ID,
                'meeting_id' => self::MEETING_ID,
                'motion_title' => 'Résolution 1',
                'closed_at' => null, // still open
            ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('motion_not_closed');

        $this->service->overrideDecision(
            self::MOTION_ID,
            'adopted',
            'Erreur',
            self::TENANT_ID,
        );
    }

    // =========================================================================
    // tally() — happy path
    // =========================================================================

    /**
     * tally(): returns structured vote counts per value.
     */
    public function testTallyReturnsCategorisedCounts(): void
    {
        $this->motionRepo
            ->expects($this->once())
            ->method('findByIdForTenant')
            ->with(self::MOTION_ID, self::TENANT_ID)
            ->willReturn([
                'id' => self::MOTION_ID,
                'meeting_id' => self::MEETING_ID,
                'closed_at' => '2026-04-07 14:00:00',
            ]);

        $this->motionRepo
            ->expects($this->once())
            ->method('getTally')
            ->with(self::MOTION_ID, self::TENANT_ID)
            ->willReturn([
                ['value' => 'for', 'c' => 5, 'w' => 5.0],
                ['value' => 'against', 'c' => 2, 'w' => 2.0],
                ['value' => 'abstain', 'c' => 1, 'w' => 1.0],
            ]);

        $result = $this->service->tally(self::MOTION_ID, self::TENANT_ID);

        $this->assertSame(self::MOTION_ID, $result['motion_id']);
        $this->assertTrue($result['closed']);
        $this->assertSame(5, $result['tally']['for']['count']);
        $this->assertSame(2, $result['tally']['against']['count']);
        $this->assertSame(1, $result['tally']['abstain']['count']);
    }

    // =========================================================================
    // degradedTally() — happy path
    // =========================================================================

    /**
     * Persists manual tally and returns totals matching input.
     */
    public function testDegradedTallyPersistsManualCountsAndReturnsTotals(): void
    {
        $row = [
            'id' => self::MOTION_ID,
            'meeting_id' => self::MEETING_ID,
            'motion_title' => 'Résolution DT',
            'manual_total' => null, // F03: no prior tally — idempotence baseline
            'manual_for' => null,
            'manual_against' => null,
            'manual_abstain' => null,
        ];

        $this->motionRepo
            ->expects($this->once())
            ->method('findWithMeetingTenant')
            ->willReturn($row);

        $this->motionRepo
            ->expects($this->once())
            ->method('updateManualTally')
            ->with(self::MOTION_ID, 10, 6, 3, 1, self::TENANT_ID);

        $this->manualActionRepo
            ->expects($this->once())
            ->method('createManualTally');

        // NotificationsService will try meetingRepo and notifRepo; both are mocked
        $this->meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING_ID,
            'status' => 'live',
        ]);

        $result = $this->service->degradedTally([
            'motion_id' => self::MOTION_ID,
            'manual_total' => 10,
            'manual_for' => 6,
            'manual_against' => 3,
            'manual_abstain' => 1,
            'justification' => 'Problème technique majeur sur le scanner', // ≥ 20 chars (F03 gate)
        ], self::TENANT_ID);

        $this->assertSame(10, $result['manual_total']);
        $this->assertSame(6, $result['manual_for']);
        $this->assertSame(3, $result['manual_against']);
        $this->assertSame(1, $result['manual_abstain']);
    }

    // =========================================================================
    // degradedTally() — F03 justification & idempotence gates
    // =========================================================================

    /**
     * F03: justification < 20 chars → RuntimeException('justification_too_short').
     */
    public function testDegradedTallyRefusesShortJustification(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('justification_too_short');

        // Repo MUST NOT be hit — gate fires before any DB read.
        $this->motionRepo->expects($this->never())->method('findWithMeetingTenant');
        $this->motionRepo->expects($this->never())->method('updateManualTally');

        $this->service->degradedTally([
            'motion_id' => self::MOTION_ID,
            'manual_total' => 10,
            'manual_for' => 6,
            'manual_against' => 3,
            'manual_abstain' => 1,
            'justification' => 'Trop court', // 10 chars
        ], self::TENANT_ID);
    }

    /**
     * F03: a manual tally already exists on the motion → throw 'manual_tally_already_set'.
     * Prevents silent overwrite of degraded-mode figures.
     */
    public function testDegradedTallyRefusesIfManualTallyAlreadySet(): void
    {
        $row = [
            'id' => self::MOTION_ID,
            'meeting_id' => self::MEETING_ID,
            'motion_title' => 'Résolution DT',
            'manual_total' => 50,    // already set
            'manual_for' => 30,
            'manual_against' => 15,
            'manual_abstain' => 5,
        ];

        $this->motionRepo
            ->expects($this->once())
            ->method('findWithMeetingTenant')
            ->willReturn($row);

        // No write must occur on the second attempt.
        $this->motionRepo->expects($this->never())->method('updateManualTally');
        $this->manualActionRepo->expects($this->never())->method('createManualTally');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('manual_tally_already_set');

        $this->service->degradedTally([
            'motion_id' => self::MOTION_ID,
            'manual_total' => 10,
            'manual_for' => 6,
            'manual_against' => 3,
            'manual_abstain' => 1,
            'justification' => 'Tentative de réécrasement du tally manuel précédent',
        ], self::TENANT_ID);
    }

    // =========================================================================
    // degradedTally() — arithmetic guard
    // =========================================================================

    /**
     * For + Against + Abstain != total → throws RuntimeException('inconsistent_tally').
     */
    public function testDegradedTallyThrowsOnInconsistentTotal(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('inconsistent_tally');

        $this->service->degradedTally([
            'motion_id' => self::MOTION_ID,
            'manual_total' => 10,
            'manual_for' => 5,
            'manual_against' => 3,
            'manual_abstain' => 1, // 5+3+1=9 != 10
            'justification' => 'test',
        ], self::TENANT_ID);
    }

    // =========================================================================
    // open() — happy path policy cascade
    // =========================================================================

    /**
     * Policy cascade: motion has no policy → falls back to meeting policy.
     */
    public function testOpenReturnsMeetingPolicyWhenMotionHasNone(): void
    {
        $motionRow = [
            'id' => self::MOTION_ID,
            'meeting_id' => self::MEETING_ID,
            'vote_policy_id' => null,
            'quorum_policy_id' => null,
            'opened_at' => null,
            'title' => 'Résolution test',
            'secret' => false,
        ];

        $this->motionRepo
            ->expects($this->once())
            ->method('findByIdForTenantForUpdate')
            ->willReturn($motionRow);

        $this->meetingRepo
            ->expects($this->atLeastOnce())
            ->method('findByIdForTenant')
            ->willReturn([
                'id' => self::MEETING_ID,
                'vote_policy_id' => 'policy-vote-uuid-1234-1234-123456789abc',
                'quorum_policy_id' => 'policy-qrm-uuid-1234-1234-123456789abc',
            ]);

        $this->motionRepo->expects($this->once())->method('markOpened');
        $this->meetingRepo->expects($this->once())->method('updateCurrentMotion');

        $result = $this->service->open(self::MOTION_ID, self::TENANT_ID);

        $this->assertSame(self::MEETING_ID, $result['meetingId']);
        $this->assertSame('policy-vote-uuid-1234-1234-123456789abc', $result['votePolicyId']);
    }

    // =========================================================================
    // delete() — guard: open motion cannot be deleted
    // =========================================================================

    /**
     * Open motion (opened_at set, closed_at null) → throws motion_open_locked.
     */
    public function testDeleteThrowsWhenMotionIsOpen(): void
    {
        $this->motionRepo
            ->expects($this->once())
            ->method('findByIdForTenant')
            ->willReturn([
                'id' => self::MOTION_ID,
                'meeting_id' => self::MEETING_ID,
                'opened_at' => '2026-04-07 12:00:00',
                'closed_at' => null,
                'agenda_id' => 'agenda-uuid',
            ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('motion_open_locked');

        $this->service->delete(self::MOTION_ID, self::TENANT_ID);
    }

    // =========================================================================
    // overrideDecision() — returns adopted/rejected
    // =========================================================================

    /**
     * overrideDecision with 'rejected' → result contains motion_title.
     */
    public function testOverrideDecisionRejectedReturnsMotionTitle(): void
    {
        $this->motionRepo
            ->expects($this->once())
            ->method('findWithMeetingTenant')
            ->willReturn([
                'id' => self::MOTION_ID,
                'meeting_id' => self::MEETING_ID,
                'motion_title' => 'Résolution 2',
                'closed_at' => '2026-04-07 15:00:00',
            ]);

        $this->motionRepo
            ->expects($this->once())
            ->method('overrideDecision')
            ->with(self::MOTION_ID, 'rejected', 'Recompte confirme rejet', self::TENANT_ID);

        $result = $this->service->overrideDecision(
            self::MOTION_ID,
            'rejected',
            'Recompte confirme rejet',
            self::TENANT_ID,
        );

        $this->assertSame(self::MEETING_ID, $result['meeting_id']);
        $this->assertSame('Résolution 2', $result['motion_title']);
    }
}
