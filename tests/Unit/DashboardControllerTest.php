<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\DashboardController;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MeetingStatsRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\ProxyRepository;
use AgVote\Repository\WizardRepository;

/**
 * Unit tests for DashboardController.
 *
 * Endpoints:
 *  - index():        GET — returns meetings list + optional meeting detail
 *  - wizardStatus(): GET — returns meeting setup progress
 *
 * Response structure:
 *   - api_ok($data)  => body['data'][...] (wrapped in 'data' key)
 *   - api_fail(...)  => body['error'] (direct)
 *
 * Pattern: extends ControllerTestCase, uses injectRepos() + callController().
 */
class DashboardControllerTest extends ControllerTestCase
{
    private const TENANT     = 'tenant-uuid-001';
    private const MEETING_ID = 'aaaaaaaa-1111-2222-3333-000000000001';
    private const MOTION_ID  = 'bbbbbbbb-1111-2222-3333-000000000001';
    private const POLICY_ID  = 'cccccccc-1111-2222-3333-000000000001';
    private const USER_ID    = 'user-uuid-0001';

    // =========================================================================
    // CONTROLLER STRUCTURE
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(DashboardController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testControllerExtendsAbstractController(): void
    {
        $ref = new \ReflectionClass(DashboardController::class);
        $this->assertSame(
            \AgVote\Controller\AbstractController::class,
            $ref->getParentClass()->getName()
        );
    }

    public function testControllerHasIndexMethod(): void
    {
        $this->assertTrue(method_exists(DashboardController::class, 'index'));
    }

    public function testControllerHasWizardStatusMethod(): void
    {
        $this->assertTrue(method_exists(DashboardController::class, 'wizardStatus'));
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Inject all 6 repos needed by DashboardController::index().
     */
    private function injectIndexRepos(
        \AgVote\Repository\MeetingRepository $meetingRepo,
        ?\AgVote\Repository\MeetingStatsRepository $statsRepo = null,
        ?\AgVote\Repository\MemberRepository $memberRepo = null,
        ?\AgVote\Repository\AttendanceRepository $attRepo = null,
        ?\AgVote\Repository\MotionRepository $motionRepo = null,
        ?\AgVote\Repository\BallotRepository $ballotRepo = null,
        ?\AgVote\Repository\ProxyRepository $proxyRepo = null,
    ): void {
        $this->injectRepos([
            MeetingRepository::class      => $meetingRepo,
            MeetingStatsRepository::class => $statsRepo ?? $this->createMock(MeetingStatsRepository::class),
            MemberRepository::class       => $memberRepo ?? $this->createMock(MemberRepository::class),
            AttendanceRepository::class   => $attRepo ?? $this->createMock(AttendanceRepository::class),
            MotionRepository::class       => $motionRepo ?? $this->createMock(MotionRepository::class),
            BallotRepository::class       => $ballotRepo ?? $this->createMock(BallotRepository::class),
            ProxyRepository::class        => $proxyRepo ?? $this->createMock(ProxyRepository::class),
        ]);
    }

    // =========================================================================
    // index() — no meeting_id supplied
    // =========================================================================

    public function testIndexNoMeetingIdReturnsEmptyDashboard(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('listForDashboard')->willReturn([]);

        $this->injectIndexRepos($meetingRepo);

        $res = $this->callController(DashboardController::class, 'index');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        $this->assertArrayHasKey('meetings', $data);
        $this->assertSame([], $data['meetings']);
        $this->assertNull($data['meeting']);
        $this->assertNull($data['suggested_meeting_id']);
    }

    public function testIndexPicksLiveMeetingAsSuggested(): void
    {
        // When no meeting_id query param, controller falls back to suggested as meetingId.
        // So findByIdForTenant IS called. We mock it to return the live meeting.
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $meetingIdA = 'aaaaaaaa-0001-0001-0001-000000000001';
        $meetingIdB = 'bbbbbbbb-0001-0001-0001-000000000001';

        $meetings = [
            ['id' => $meetingIdA, 'status' => 'draft'],
            ['id' => $meetingIdB, 'status' => 'live'],
        ];

        $meetingData = [
            'id'                => $meetingIdB,
            'status'            => 'live',
            'current_motion_id' => '',
            'president_name'    => 'Alice',
        ];

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('listForDashboard')->willReturn($meetings);
        $meetingRepo->method('findByIdForTenant')->willReturn($meetingData);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countNotDeleted')->willReturn(10);
        $memberRepo->method('sumNotDeletedVoteWeight')->willReturn(10.0);

        $attRepo = $this->createMock(AttendanceRepository::class);
        $attRepo->method('dashboardSummary')->willReturn([
            'present_count' => 5, 'present_weight' => 5.0,
        ]);

        $proxyRepo = $this->createMock(ProxyRepository::class);
        $proxyRepo->method('countActive')->willReturn(0);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findCurrentOpen')->willReturn(null);
        $motionRepo->method('listOpenable')->willReturn([]);
        $motionRepo->method('listClosedWithManualTally')->willReturn([]);

        $statsRepo = $this->createMock(MeetingStatsRepository::class);
        $statsRepo->method('countOpenMotions')->willReturn(0);

        $ballotRepo = $this->createMock(BallotRepository::class);
        $ballotRepo->method('tally')->willReturn([
            'total_ballots' => 0, 'weight_for' => 0, 'weight_against' => 0, 'weight_abstain' => 0,
        ]);

        $this->injectIndexRepos($meetingRepo, $statsRepo, $memberRepo, $attRepo, $motionRepo, $ballotRepo, $proxyRepo);

        $res = $this->callController(DashboardController::class, 'index');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        // suggested_meeting_id = meetingIdB (live)
        $this->assertSame($meetingIdB, $data['suggested_meeting_id']);
        // meeting detail is also loaded since meetingId falls back to suggested
        $this->assertSame($meetingIdB, $data['meeting']['id']);
    }

    public function testIndexWithMeetingIdReturnsFullDetail(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingData = [
            'id'               => self::MEETING_ID,
            'status'           => 'live',
            'current_motion_id' => '',
            'president_name'   => 'Alice',
        ];

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('listForDashboard')->willReturn([
            ['id' => self::MEETING_ID, 'status' => 'live'],
        ]);
        $meetingRepo->method('findByIdForTenant')->willReturn($meetingData);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countNotDeleted')->willReturn(10);
        $memberRepo->method('sumNotDeletedVoteWeight')->willReturn(10.0);

        $attRepo = $this->createMock(AttendanceRepository::class);
        $attRepo->method('dashboardSummary')->willReturn([
            'present_count' => 6,
            'present_weight' => 6.0,
        ]);

        $proxyRepo = $this->createMock(ProxyRepository::class);
        $proxyRepo->method('countActive')->willReturn(0);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findCurrentOpen')->willReturn(null);
        $motionRepo->method('listOpenable')->willReturn([]);
        $motionRepo->method('listClosedWithManualTally')->willReturn([]);

        $statsRepo = $this->createMock(MeetingStatsRepository::class);
        $statsRepo->method('countOpenMotions')->willReturn(0);

        $ballotRepo = $this->createMock(BallotRepository::class);
        $ballotRepo->method('tally')->willReturn([
            'total_ballots' => 0, 'weight_for' => 0, 'weight_against' => 0, 'weight_abstain' => 0,
        ]);

        $this->injectIndexRepos($meetingRepo, $statsRepo, $memberRepo, $attRepo, $motionRepo, $ballotRepo, $proxyRepo);

        $res = $this->callController(DashboardController::class, 'index');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        $this->assertSame(self::MEETING_ID, $data['meeting']['id']);
        $this->assertSame(6, $data['attendance']['present_count']);
        $this->assertSame(10, $data['attendance']['eligible_count']);
        $this->assertTrue($data['ready_to_sign']['can']);
    }

    public function testIndexMeetingNotFoundReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('listForDashboard')->willReturn([
            ['id' => self::MEETING_ID, 'status' => 'live'],
        ]);
        $meetingRepo->method('findByIdForTenant')->willReturn(null);

        $this->injectIndexRepos($meetingRepo);

        $res = $this->callController(DashboardController::class, 'index');

        $this->assertSame(404, $res['status']);
        $this->assertSame('meeting_not_found', $res['body']['error']);
    }

    public function testIndexReadyToSignFalseWhenOpenMotionExists(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingData = [
            'id'               => self::MEETING_ID,
            'status'           => 'live',
            'current_motion_id' => '',
            'president_name'   => 'Alice',
        ];

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('listForDashboard')->willReturn([
            ['id' => self::MEETING_ID, 'status' => 'live'],
        ]);
        $meetingRepo->method('findByIdForTenant')->willReturn($meetingData);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countNotDeleted')->willReturn(5);
        $memberRepo->method('sumNotDeletedVoteWeight')->willReturn(5.0);

        $attRepo = $this->createMock(AttendanceRepository::class);
        $attRepo->method('dashboardSummary')->willReturn([
            'present_count' => 3, 'present_weight' => 3.0,
        ]);

        $proxyRepo = $this->createMock(ProxyRepository::class);
        $proxyRepo->method('countActive')->willReturn(0);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findCurrentOpen')->willReturn(null);
        $motionRepo->method('listOpenable')->willReturn([]);
        $motionRepo->method('listClosedWithManualTally')->willReturn([]);

        $statsRepo = $this->createMock(MeetingStatsRepository::class);
        $statsRepo->method('countOpenMotions')->willReturn(1); // blocks sign

        $ballotRepo = $this->createMock(BallotRepository::class);

        $this->injectIndexRepos($meetingRepo, $statsRepo, $memberRepo, $attRepo, $motionRepo, $ballotRepo, $proxyRepo);

        $res = $this->callController(DashboardController::class, 'index');

        $this->assertSame(200, $res['status']);
        $this->assertFalse($res['body']['data']['ready_to_sign']['can']);
        $this->assertNotEmpty($res['body']['data']['ready_to_sign']['reasons']);
    }

    public function testIndexReadyToSignFalseWhenPresidentMissing(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingData = [
            'id'               => self::MEETING_ID,
            'status'           => 'live',
            'current_motion_id' => '',
            'president_name'   => '',  // empty => blocks sign
        ];

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('listForDashboard')->willReturn([
            ['id' => self::MEETING_ID, 'status' => 'live'],
        ]);
        $meetingRepo->method('findByIdForTenant')->willReturn($meetingData);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countNotDeleted')->willReturn(4);
        $memberRepo->method('sumNotDeletedVoteWeight')->willReturn(4.0);

        $attRepo = $this->createMock(AttendanceRepository::class);
        $attRepo->method('dashboardSummary')->willReturn([
            'present_count' => 2, 'present_weight' => 2.0,
        ]);

        $proxyRepo = $this->createMock(ProxyRepository::class);
        $proxyRepo->method('countActive')->willReturn(0);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findCurrentOpen')->willReturn(null);
        $motionRepo->method('listOpenable')->willReturn([]);
        $motionRepo->method('listClosedWithManualTally')->willReturn([]);

        $statsRepo = $this->createMock(MeetingStatsRepository::class);
        $statsRepo->method('countOpenMotions')->willReturn(0);

        $ballotRepo = $this->createMock(BallotRepository::class);

        $this->injectIndexRepos($meetingRepo, $statsRepo, $memberRepo, $attRepo, $motionRepo, $ballotRepo, $proxyRepo);

        $res = $this->callController(DashboardController::class, 'index');

        $this->assertSame(200, $res['status']);
        $this->assertFalse($res['body']['data']['ready_to_sign']['can']);
    }

    public function testIndexWithCurrentMotionId(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingData = [
            'id'               => self::MEETING_ID,
            'status'           => 'live',
            'current_motion_id' => self::MOTION_ID,
            'president_name'   => 'Bob',
        ];

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('listForDashboard')->willReturn([
            ['id' => self::MEETING_ID, 'status' => 'live'],
        ]);
        $meetingRepo->method('findByIdForTenant')->willReturn($meetingData);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countNotDeleted')->willReturn(5);
        $memberRepo->method('sumNotDeletedVoteWeight')->willReturn(5.0);

        $attRepo = $this->createMock(AttendanceRepository::class);
        $attRepo->method('dashboardSummary')->willReturn([
            'present_count' => 3, 'present_weight' => 3.0,
        ]);

        $proxyRepo = $this->createMock(ProxyRepository::class);
        $proxyRepo->method('countActive')->willReturn(0);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findByIdForTenant')->willReturn([
            'id' => self::MOTION_ID, 'title' => 'Motion 1',
        ]);
        $motionRepo->method('listOpenable')->willReturn([]);
        $motionRepo->method('listClosedWithManualTally')->willReturn([]);

        $statsRepo = $this->createMock(MeetingStatsRepository::class);
        $statsRepo->method('countOpenMotions')->willReturn(0);

        $ballotRepo = $this->createMock(BallotRepository::class);
        $ballotRepo->method('tally')->willReturn([
            'total_ballots' => 3, 'weight_for' => 2.0, 'weight_against' => 1.0, 'weight_abstain' => 0.0,
        ]);

        $this->injectIndexRepos($meetingRepo, $statsRepo, $memberRepo, $attRepo, $motionRepo, $ballotRepo, $proxyRepo);

        $res = $this->callController(DashboardController::class, 'index');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        $this->assertSame(self::MOTION_ID, $data['current_motion']['id']);
        $this->assertSame(3, $data['current_motion_votes']['ballots_count']);
    }

    // =========================================================================
    // wizardStatus() — GET
    // =========================================================================

    public function testWizardStatusMissingMeetingIdReturns422(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $res = $this->callController(DashboardController::class, 'wizardStatus');

        $this->assertSame(422, $res['status']);
        $this->assertSame('missing_meeting_id', $res['body']['error']);
    }

    public function testWizardStatusInvalidUuidReturns422(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => 'not-a-uuid']);

        $res = $this->callController(DashboardController::class, 'wizardStatus');

        $this->assertSame(422, $res['status']);
    }

    public function testWizardStatusMeetingNotFoundReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $wizardRepo = $this->createMock(WizardRepository::class);
        $wizardRepo->method('getMeetingBasics')->willReturn(null);

        $this->injectRepos([WizardRepository::class => $wizardRepo]);

        $res = $this->callController(DashboardController::class, 'wizardStatus');

        $this->assertSame(404, $res['status']);
        $this->assertSame('meeting_not_found', $res['body']['error']);
    }

    public function testWizardStatusReturnsFullData(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingBasics = [
            'id'               => self::MEETING_ID,
            'title'            => 'AG 2025',
            'status'           => 'live',
            'current_motion_id' => null,
            'quorum_policy_id' => null,
            'vote_policy_id'   => self::POLICY_ID,
            'scheduled_at'     => '2025-06-01T10:00:00Z',
            'location'         => 'Paris',
            'meeting_type'     => 'ag_ordinaire',
        ];

        $wizardRepo = $this->createMock(WizardRepository::class);
        $wizardRepo->method('getMeetingBasics')->willReturn($meetingBasics);
        $wizardRepo->method('countAttendances')->willReturn(5);
        $wizardRepo->method('countPresentAttendances')->willReturn(3);
        $wizardRepo->method('getMotionsCounts')->willReturn(['total' => 2, 'closed' => 1]);
        $wizardRepo->method('hasPresident')->willReturn(true);

        $this->injectRepos([WizardRepository::class => $wizardRepo]);

        $res = $this->callController(DashboardController::class, 'wizardStatus');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        $this->assertSame(self::MEETING_ID, $data['meeting_id']);
        $this->assertSame('AG 2025', $data['meeting_title']);
        $this->assertSame(5, $data['members_count']);
        $this->assertSame(3, $data['present_count']);
        $this->assertTrue($data['has_president']);
        $this->assertTrue($data['quorum_met']); // ratio = 3/5 > 0, no policy threshold
    }

    public function testWizardStatusFallsBackToActiveMembersWhenNoAttendances(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingBasics = [
            'id'               => self::MEETING_ID,
            'title'            => 'AG 2025',
            'status'           => 'draft',
            'current_motion_id' => null,
            'quorum_policy_id' => null,
            'vote_policy_id'   => null,
            'scheduled_at'     => null,
            'location'         => null,
            'meeting_type'     => 'ag_ordinaire',
        ];

        $wizardRepo = $this->createMock(WizardRepository::class);
        $wizardRepo->method('getMeetingBasics')->willReturn($meetingBasics);
        $wizardRepo->method('countAttendances')->willReturn(0);  // fallback trigger
        $wizardRepo->method('countActiveMembers')->willReturn(12);
        $wizardRepo->method('countPresentAttendances')->willReturn(0);
        $wizardRepo->method('getMotionsCounts')->willReturn(['total' => 0, 'closed' => 0]);
        $wizardRepo->method('hasPresident')->willReturn(false);

        $this->injectRepos([WizardRepository::class => $wizardRepo]);

        $res = $this->callController(DashboardController::class, 'wizardStatus');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        $this->assertSame(12, $data['members_count']);
        $this->assertFalse($data['quorum_met']); // 0/12 = 0, not > 0
    }

    public function testWizardStatusWithQuorumPolicyBelowThreshold(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingBasics = [
            'id'               => self::MEETING_ID,
            'title'            => 'AG 2025',
            'status'           => 'live',
            'current_motion_id' => null,
            'quorum_policy_id' => self::POLICY_ID,
            'vote_policy_id'   => self::POLICY_ID,
            'scheduled_at'     => '2025-06-01',
            'location'         => 'Lyon',
            'meeting_type'     => 'ag_extraordinaire',
        ];

        $wizardRepo = $this->createMock(WizardRepository::class);
        $wizardRepo->method('getMeetingBasics')->willReturn($meetingBasics);
        $wizardRepo->method('countAttendances')->willReturn(10);
        $wizardRepo->method('countPresentAttendances')->willReturn(4); // 40%
        $wizardRepo->method('getMotionsCounts')->willReturn(['total' => 1, 'closed' => 0]);
        $wizardRepo->method('hasPresident')->willReturn(true);
        $wizardRepo->method('getQuorumThreshold')->willReturn(0.5); // need >= 50%

        $this->injectRepos([WizardRepository::class => $wizardRepo]);

        $res = $this->callController(DashboardController::class, 'wizardStatus');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        // 4/10 = 40%, threshold 50% => not met
        $this->assertFalse($data['quorum_met']);
        $this->assertTrue($data['policies_assigned']);
    }
}
