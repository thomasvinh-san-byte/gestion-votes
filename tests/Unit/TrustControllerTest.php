<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\TrustController;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MeetingStatsRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Repository\ProxyRepository;

/**
 * Unit tests for TrustController.
 *
 * Tests the 2 trust/integrity endpoints:
 *  - anomalies: GET, lists anomalies for a meeting
 *  - checks: GET, runs integrity checks for a meeting
 *
 * Extends ControllerTestCase for RepositoryFactory injection.
 * Body structure: api_ok → ['ok' => true, 'data' => [...]]
 *                 api_fail → ['ok' => false, 'error' => '...']
 */
class TrustControllerTest extends ControllerTestCase
{
    private const MEETING_ID = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    private const TENANT_ID  = 'ffffffff-0000-1111-2222-333333333333';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setAuth('user-1', 'admin', self::TENANT_ID);
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);
    }

    // =========================================================================
    // STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(TrustController::class);
        $this->assertTrue($ref->isFinal(), 'TrustController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, new TrustController());
    }

    public function testControllerHasExpectedMethods(): void
    {
        $ref = new \ReflectionClass(TrustController::class);
        $this->assertTrue($ref->hasMethod('anomalies'));
        $this->assertTrue($ref->hasMethod('checks'));
    }

    // =========================================================================
    // anomalies() – validation
    // =========================================================================

    public function testAnomaliesMissingMeetingId(): void
    {
        $this->setQueryParams([]);
        $result = $this->callController(TrustController::class, 'anomalies');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testAnomaliesInvalidMeetingId(): void
    {
        $this->setQueryParams(['meeting_id' => 'not-a-uuid']);
        $result = $this->callController(TrustController::class, 'anomalies');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testAnomaliesMeetingNotFound(): void
    {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(null);
        $this->injectRepos([MeetingRepository::class => $meetingRepo]);

        $result = $this->callController(TrustController::class, 'anomalies');
        $this->assertEquals(404, $result['status']);
        $this->assertEquals('meeting_not_found', $result['body']['error']);
    }

    public function testAnomaliesHappyPathNoAnomalies(): void
    {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING_ID,
            'tenant_id' => self::TENANT_ID,
            'title' => 'Test Meeting',
        ]);

        $ballotRepo = $this->createMock(BallotRepository::class);
        $ballotRepo->method('listVotesWithoutAttendance')->willReturn([]);
        $ballotRepo->method('listDuplicateVotes')->willReturn([]);
        $ballotRepo->method('listWeightMismatches')->willReturn([]);
        $ballotRepo->method('listUnjustifiedManualVotes')->willReturn([]);

        $proxyRepo = $this->createMock(ProxyRepository::class);
        $proxyRepo->method('listOrphans')->willReturn([]);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('listUnclosed')->willReturn([]);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            BallotRepository::class  => $ballotRepo,
            ProxyRepository::class   => $proxyRepo,
            MotionRepository::class  => $motionRepo,
        ]);

        $result = $this->callController(TrustController::class, 'anomalies');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertArrayHasKey('anomalies', $data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertCount(0, $data['anomalies']);
        $this->assertEquals(0, $data['summary']['total']);
    }

    public function testAnomaliesHappyPathWithAllAnomalyTypes(): void
    {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING_ID,
            'tenant_id' => self::TENANT_ID,
            'title' => 'Test Meeting',
        ]);

        $ballotRepo = $this->createMock(BallotRepository::class);
        $ballotRepo->method('listVotesWithoutAttendance')->willReturn([
            ['member_id' => 'mem-1', 'full_name' => 'Alice', 'motion_title' => 'Res 1'],
        ]);
        $ballotRepo->method('listDuplicateVotes')->willReturn([
            ['member_id' => 'mem-2', 'full_name' => 'Bob', 'motion_title' => 'Res 2', 'vote_count' => 2],
        ]);
        $ballotRepo->method('listWeightMismatches')->willReturn([
            ['member_id' => 'mem-3', 'full_name' => 'Charlie', 'motion_title' => 'Res 3', 'actual_weight' => 1.5, 'expected_weight' => 1.0],
        ]);
        $ballotRepo->method('listUnjustifiedManualVotes')->willReturn([
            ['id' => 'b-1', 'full_name' => 'Dave', 'motion_title' => 'Res 4'],
        ]);

        $proxyRepo = $this->createMock(ProxyRepository::class);
        $proxyRepo->method('listOrphans')->willReturn([
            ['id' => 'prx-1', 'giver_name' => 'Eve', 'receiver_name' => 'Frank'],
        ]);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('listUnclosed')->willReturn([
            ['id' => 'mot-1', 'title' => 'Pending Motion', 'opened_at' => '2024-01-01 10:00:00'],
        ]);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            BallotRepository::class  => $ballotRepo,
            ProxyRepository::class   => $proxyRepo,
            MotionRepository::class  => $motionRepo,
        ]);

        $result = $this->callController(TrustController::class, 'anomalies');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertEquals(6, $data['summary']['total']);
        $this->assertCount(6, $data['anomalies']);
        $this->assertEquals(1, $data['summary']['danger']);
        $this->assertEquals(4, $data['summary']['warning']); // vote_no_attendance + weight_mismatch + orphan + unjustified
        $this->assertEquals(1, $data['summary']['info']);    // unclosed_motion

        // Each anomaly must have required fields (including message/context aliases)
        foreach ($data['anomalies'] as $anomaly) {
            $this->assertArrayHasKey('id', $anomaly);
            $this->assertArrayHasKey('type', $anomaly);
            $this->assertArrayHasKey('severity', $anomaly);
            $this->assertArrayHasKey('message', $anomaly);
            $this->assertArrayHasKey('context', $anomaly);
        }
    }

    // =========================================================================
    // checks() – validation
    // =========================================================================

    public function testChecksMissingMeetingId(): void
    {
        $this->setQueryParams([]);
        $result = $this->callController(TrustController::class, 'checks');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testChecksInvalidMeetingId(): void
    {
        $this->setQueryParams(['meeting_id' => 'bad']);
        $result = $this->callController(TrustController::class, 'checks');
        $this->assertEquals(400, $result['status']);
    }

    public function testChecksMeetingNotFound(): void
    {
        // checks() eagerly instantiates all repos before checking meeting existence,
        // so ALL repos must be in the cache to avoid PDO exceptions.
        $this->buildChecksRepos(meetingData: null);
        // Override the meeting repo to return null
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(null);

        $statsRepo = $this->createMock(MeetingStatsRepository::class);
        $memberRepo = $this->createMock(MemberRepository::class);
        $motionRepo = $this->createMock(MotionRepository::class);
        $ballotRepo = $this->createMock(BallotRepository::class);
        $policyRepo = $this->createMock(PolicyRepository::class);
        $proxyRepo  = $this->createMock(ProxyRepository::class);

        $this->injectRepos([
            MeetingRepository::class      => $meetingRepo,
            MeetingStatsRepository::class => $statsRepo,
            MemberRepository::class       => $memberRepo,
            MotionRepository::class       => $motionRepo,
            BallotRepository::class       => $ballotRepo,
            PolicyRepository::class       => $policyRepo,
            ProxyRepository::class        => $proxyRepo,
        ]);

        $result = $this->callController(TrustController::class, 'checks');
        $this->assertEquals(404, $result['status']);
        $this->assertEquals('meeting_not_found', $result['body']['error']);
    }

    private function buildChecksRepos(
        ?array $meetingData = null,
        int $presentCount = 10,
        int $totalMembers = 15,
        int $closedMotions = 3,
        int $openMotions = 0,
        int $totalMotions = 3,
        array $closedWithoutVotes = [],
        array $votesAfterClose = [],
        ?array $quorumPolicy = null,
        array $proxyCycles = []
    ): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($meetingData ?? [
            'id'               => self::MEETING_ID,
            'tenant_id'        => self::TENANT_ID,
            'title'            => 'Test',
            'president_name'   => 'M. President',
            'quorum_policy_id' => null,
            'vote_policy_id'   => 'vp-uuid-0000-1111-2222-333333333333',
        ]);

        $statsRepo = $this->createMock(MeetingStatsRepository::class);
        $statsRepo->method('countPresent')->willReturn($presentCount);
        $statsRepo->method('countMotions')->willReturn($totalMotions);
        $statsRepo->method('countClosedMotions')->willReturn($closedMotions);
        $statsRepo->method('countOpenMotions')->willReturn($openMotions);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn($totalMembers);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('listClosedWithoutVotes')->willReturn($closedWithoutVotes);

        $ballotRepo = $this->createMock(BallotRepository::class);
        $ballotRepo->method('listVotesAfterClose')->willReturn($votesAfterClose);

        $policyRepo = $this->createMock(PolicyRepository::class);
        $policyRepo->method('findQuorumPolicy')->willReturn($quorumPolicy);

        $proxyRepo = $this->createMock(ProxyRepository::class);
        $proxyRepo->method('findCycles')->willReturn($proxyCycles);

        $this->injectRepos([
            MeetingRepository::class      => $meetingRepo,
            MeetingStatsRepository::class => $statsRepo,
            MemberRepository::class       => $memberRepo,
            MotionRepository::class       => $motionRepo,
            BallotRepository::class       => $ballotRepo,
            PolicyRepository::class       => $policyRepo,
            ProxyRepository::class        => $proxyRepo,
        ]);
    }

    public function testChecksHappyPathAllPassing(): void
    {
        $this->buildChecksRepos();

        $result = $this->callController(TrustController::class, 'checks');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertArrayHasKey('checks', $data);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('all_passed', $data);
        $this->assertCount(10, $data['checks']);

        foreach ($data['checks'] as $check) {
            $this->assertArrayHasKey('id', $check);
            $this->assertArrayHasKey('label', $check);
            $this->assertArrayHasKey('passed', $check);
            $this->assertArrayHasKey('detail', $check);
        }
    }

    public function testChecksWithCustomQuorumPolicyQuorumMet(): void
    {
        $this->buildChecksRepos(
            meetingData: [
                'id'               => self::MEETING_ID,
                'tenant_id'        => self::TENANT_ID,
                'title'            => 'Test',
                'president_name'   => 'Pres',
                'quorum_policy_id' => 'qp-uuid-0000-1111-2222-333333333333',
                'vote_policy_id'   => null,
            ],
            presentCount: 5,
            totalMembers: 20,
            quorumPolicy: ['threshold' => 0.25]
        );

        $result = $this->callController(TrustController::class, 'checks');
        $this->assertEquals(200, $result['status']);
        // Quorum at 25% of 20 = 5, present = 5, quorum_met passes
        $quorumCheck = current(array_filter(
            $result['body']['data']['checks'],
            fn ($c) => $c['id'] === 'quorum_met'
        ));
        $this->assertTrue($quorumCheck['passed']);
    }

    public function testChecksWithProxyCyclesFailsCheck(): void
    {
        $this->buildChecksRepos(
            proxyCycles: [['cycle' => ['A', 'B', 'A']]]
        );

        $result = $this->callController(TrustController::class, 'checks');
        $this->assertEquals(200, $result['status']);
        $proxyCheck = current(array_filter(
            $result['body']['data']['checks'],
            fn ($c) => $c['id'] === 'proxies_valid'
        ));
        $this->assertFalse($proxyCheck['passed']);
    }

    public function testChecksNoPresidentFails(): void
    {
        $this->buildChecksRepos(
            meetingData: [
                'id'              => self::MEETING_ID,
                'tenant_id'       => self::TENANT_ID,
                'title'           => 'Test',
                'president_name'  => null,
                'quorum_policy_id' => null,
                'vote_policy_id'  => null,
            ]
        );

        $result = $this->callController(TrustController::class, 'checks');
        $this->assertEquals(200, $result['status']);
        $presCheck = current(array_filter(
            $result['body']['data']['checks'],
            fn ($c) => $c['id'] === 'president_defined'
        ));
        $this->assertFalse($presCheck['passed']);
    }

    public function testChecksOpenMotionsFailsAllMotionsClosed(): void
    {
        $this->buildChecksRepos(closedMotions: 2, openMotions: 1, totalMotions: 3);

        $result = $this->callController(TrustController::class, 'checks');
        $this->assertEquals(200, $result['status']);
        $motionCheck = current(array_filter(
            $result['body']['data']['checks'],
            fn ($c) => $c['id'] === 'all_motions_closed'
        ));
        $this->assertFalse($motionCheck['passed']);
    }

    public function testChecksSummaryCountsPassedFailed(): void
    {
        $this->buildChecksRepos();

        $result = $this->callController(TrustController::class, 'checks');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $summary = $data['summary'];
        $this->assertEquals(10, $summary['total']);
        $this->assertEquals($summary['passed'] + $summary['failed'], $summary['total']);
        $this->assertEquals($data['all_passed'], $summary['failed'] === 0);
    }

    public function testChecksVotesAfterCloseDetected(): void
    {
        $this->buildChecksRepos(
            votesAfterClose: [['ballot_id' => 'b1', 'motion_id' => 'mot-1']]
        );

        $result = $this->callController(TrustController::class, 'checks');
        $this->assertEquals(200, $result['status']);
        $noVotesCheck = current(array_filter(
            $result['body']['data']['checks'],
            fn ($c) => $c['id'] === 'no_votes_after_close'
        ));
        $this->assertFalse($noVotesCheck['passed']);
    }
}
