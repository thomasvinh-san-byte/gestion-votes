<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\OperatorController;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MeetingStatsRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\NotificationRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Repository\ProxyRepository;
use AgVote\Repository\VoteTokenRepository;
use AgVote\Service\OperatorWorkflowService;
use ReflectionClass;

/**
 * Unit tests for OperatorController.
 *
 * Tests all three endpoints with mocked repositories:
 * - workflowState: quorum/attendance/proxy/consolidation state
 * - openVote: token generation, meeting/motion validation
 * - anomalies: duplicate detection, ineligible ballots, missing voters
 */
class OperatorControllerTest extends ControllerTestCase
{
    private const MEETING_ID = '11111111-1111-1111-1111-111111111111';
    private const MOTION_ID  = '22222222-2222-2222-2222-222222222222';
    private const TENANT_ID  = 'tenant-test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setAuth('user-01', 'operator', self::TENANT_ID);
    }

    // =========================================================================
    // CONTROLLER STRUCTURE
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new ReflectionClass(OperatorController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testControllerExtendsAbstractController(): void
    {
        $ref = new ReflectionClass(OperatorController::class);
        $this->assertSame('AgVote\\Controller\\AbstractController', $ref->getParentClass()->getName());
    }

    public function testHasExpectedPublicMethods(): void
    {
        $ref = new ReflectionClass(OperatorController::class);
        $methods = array_map(fn ($m) => $m->getName(), $ref->getMethods(\ReflectionMethod::IS_PUBLIC));
        $this->assertContains('workflowState', $methods);
        $this->assertContains('openVote', $methods);
        $this->assertContains('anomalies', $methods);
    }

    // =========================================================================
    // SERVICE STRUCTURE TESTS (pre-split — validates extraction targets)
    // =========================================================================

    /**
     * @group pending-service
     */
    public function testOperatorWorkflowServiceIsFinal(): void
    {
        $ref = new ReflectionClass(OperatorWorkflowService::class);
        $this->assertTrue($ref->isFinal(), 'OperatorWorkflowService should be final');
    }

    /**
     * @group pending-service
     */
    public function testOperatorWorkflowServiceHasExpectedMethods(): void
    {
        $ref = new ReflectionClass(OperatorWorkflowService::class);

        $expectedMethods = ['getWorkflowState', 'openVote', 'getAnomalies'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "OperatorWorkflowService should have a '{$method}' method",
            );
        }
    }

    /**
     * @group pending-service
     */
    public function testOperatorWorkflowServiceUsesNullableDI(): void
    {
        $ref = new ReflectionClass(OperatorWorkflowService::class);
        $constructor = $ref->getConstructor();
        $this->assertNotNull($constructor, 'OperatorWorkflowService should have a constructor');

        $params = $constructor->getParameters();
        $this->assertCount(1, $params, 'Constructor should have exactly 1 parameter');
        $this->assertTrue($params[0]->allowsNull(), 'Constructor parameter should be nullable');
        $this->assertSame(
            'AgVote\\Core\\Providers\\RepositoryFactory',
            $params[0]->getType()->getName(),
            'Constructor parameter should be RepositoryFactory',
        );
    }

    // =========================================================================
    // workflowState — validation errors
    // =========================================================================

    public function testWorkflowStateMissingMeetingId(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);
        $this->injectRepos([]);

        $resp = $this->callController(OperatorController::class, 'workflowState');
        $this->assertSame(400, $resp['status']);
        $this->assertSame('missing_meeting_id', $resp['body']['error'] ?? $resp['body']['code'] ?? '');
    }

    public function testWorkflowStateInvalidMeetingId(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => 'not-a-uuid']);
        $this->injectRepos([]);

        $resp = $this->callController(OperatorController::class, 'workflowState');
        $this->assertSame(400, $resp['status']);
    }

    public function testWorkflowStateMeetingNotFound(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(null);

        // All repos are fetched before the meeting-not-found check
        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(0);
        $memberRepo->method('listWithAttendanceForMeeting')->willReturn([]);

        $statsRepo  = $this->createMock(MeetingStatsRepository::class);
        $motionRepo = $this->createMock(MotionRepository::class);
        $ballotRepo = $this->createMock(BallotRepository::class);
        $proxyRepo  = $this->createMock(ProxyRepository::class);

        $this->injectRepos([
            MeetingRepository::class      => $meetingRepo,
            MemberRepository::class       => $memberRepo,
            MeetingStatsRepository::class => $statsRepo,
            MotionRepository::class       => $motionRepo,
            BallotRepository::class       => $ballotRepo,
            ProxyRepository::class        => $proxyRepo,
        ]);

        $resp = $this->callController(OperatorController::class, 'workflowState');
        $this->assertSame(404, $resp['status']);
    }

    public function testWorkflowStateHappyPath(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meeting = [
            'id' => self::MEETING_ID,
            'title' => 'Test Meeting',
            'status' => 'live',
            'president_name' => 'John President',
            'quorum_policy_id' => null,
        ];

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($meeting);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(10);
        $memberRepo->method('listWithAttendanceForMeeting')->willReturn([
            ['member_id' => 'mem-01', 'full_name' => 'Alice', 'attendance_mode' => 'present', 'voting_power' => '1'],
            ['member_id' => 'mem-02', 'full_name' => 'Bob',   'attendance_mode' => 'present', 'voting_power' => '1'],
        ]);

        $statsRepo = $this->createMock(MeetingStatsRepository::class);
        $statsRepo->method('countClosedMotions')->willReturn(2);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('countWorkflowSummary')->willReturn(['total' => 3, 'open' => 0]);
        $motionRepo->method('findCurrentOpen')->willReturn(null);
        $motionRepo->method('findNextNotOpened')->willReturn(['id' => self::MOTION_ID, 'title' => 'Next Motion']);
        $motionRepo->method('findLastClosedForProjector')->willReturn(null);
        $motionRepo->method('countConsolidatedMotions')->willReturn(0);

        $ballotRepo = $this->createMock(BallotRepository::class);

        $proxyRepo = $this->createMock(ProxyRepository::class);
        $proxyRepo->method('listDistinctGivers')->willReturn([]);
        $proxyRepo->method('countActive')->willReturn(0);

        $notifRepo = $this->createMock(NotificationRepository::class);
        $notifRepo->method('findValidationState')->willReturn(null);

        $this->injectRepos([
            MeetingRepository::class      => $meetingRepo,
            MemberRepository::class       => $memberRepo,
            MeetingStatsRepository::class => $statsRepo,
            MotionRepository::class       => $motionRepo,
            BallotRepository::class       => $ballotRepo,
            ProxyRepository::class        => $proxyRepo,
            NotificationRepository::class => $notifRepo,
        ]);

        $resp = $this->callController(OperatorController::class, 'workflowState');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertArrayHasKey('meeting', $data);
        $this->assertArrayHasKey('attendance', $data);
        $this->assertArrayHasKey('motion', $data);
        $this->assertArrayHasKey('consolidation', $data);
        $this->assertArrayHasKey('validation', $data);
    }

    public function testWorkflowStateWithOpenMotion(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meeting = [
            'id' => self::MEETING_ID,
            'title' => 'Test Meeting',
            'status' => 'live',
            'president_name' => 'Jane Pres',
            'quorum_policy_id' => null,
        ];

        $openMotion = [
            'id' => self::MOTION_ID,
            'title' => 'Open Motion',
            'opened_at' => date('Y-m-d H:i:s', time() - 1800), // 30 min ago
        ];

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($meeting);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(10);
        $memberRepo->method('listWithAttendanceForMeeting')->willReturn([
            ['member_id' => 'mem-01', 'full_name' => 'Alice', 'attendance_mode' => 'present', 'voting_power' => '1'],
        ]);

        $statsRepo = $this->createMock(MeetingStatsRepository::class);
        $statsRepo->method('countClosedMotions')->willReturn(1);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('countWorkflowSummary')->willReturn(['total' => 2, 'open' => 1]);
        $motionRepo->method('findCurrentOpen')->willReturn($openMotion);
        $motionRepo->method('findNextNotOpened')->willReturn(null);
        $motionRepo->method('findLastClosedForProjector')->willReturn(null);
        $motionRepo->method('countConsolidatedMotions')->willReturn(0);

        $ballotRepo = $this->createMock(BallotRepository::class);
        $ballotRepo->method('countByMotionId')->willReturn(8);

        $proxyRepo = $this->createMock(ProxyRepository::class);
        $proxyRepo->method('listDistinctGivers')->willReturn([]);
        $proxyRepo->method('countActive')->willReturn(0);

        $notifRepo = $this->createMock(NotificationRepository::class);
        $notifRepo->method('findValidationState')->willReturn(null);

        $this->injectRepos([
            MeetingRepository::class      => $meetingRepo,
            MemberRepository::class       => $memberRepo,
            MeetingStatsRepository::class => $statsRepo,
            MotionRepository::class       => $motionRepo,
            BallotRepository::class       => $ballotRepo,
            ProxyRepository::class        => $proxyRepo,
            NotificationRepository::class => $notifRepo,
        ]);

        $resp = $this->callController(OperatorController::class, 'workflowState');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertSame(self::MOTION_ID, $data['motion']['open_motion_id']);
        $this->assertSame(8, $data['motion']['open_ballots']);
    }

    public function testWorkflowStateWithQuorumPolicy(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $policyId = '99999999-9999-9999-9999-999999999999';
        $meeting = [
            'id' => self::MEETING_ID,
            'title' => 'Test Meeting',
            'status' => 'live',
            'president_name' => 'Jane Pres',
            'quorum_policy_id' => $policyId,
        ];

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($meeting);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(10);
        $memberRepo->method('listWithAttendanceForMeeting')->willReturn([
            ['member_id' => 'mem-01', 'full_name' => 'Alice', 'attendance_mode' => 'present', 'voting_power' => '1'],
            ['member_id' => 'mem-02', 'full_name' => 'Bob',   'attendance_mode' => 'present', 'voting_power' => '1'],
            ['member_id' => 'mem-03', 'full_name' => 'Carol', 'attendance_mode' => 'present', 'voting_power' => '1'],
            ['member_id' => 'mem-04', 'full_name' => 'Dave',  'attendance_mode' => 'present', 'voting_power' => '1'],
            ['member_id' => 'mem-05', 'full_name' => 'Eve',   'attendance_mode' => 'present', 'voting_power' => '1'],
            ['member_id' => 'mem-06', 'full_name' => 'Frank', 'attendance_mode' => 'absent',  'voting_power' => '1'],
        ]);

        $statsRepo = $this->createMock(MeetingStatsRepository::class);
        $statsRepo->method('countClosedMotions')->willReturn(0);

        $policyRepo = $this->createMock(PolicyRepository::class);
        $policyRepo->method('findQuorumPolicyForTenant')->willReturn(['threshold' => 0.4]);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('countWorkflowSummary')->willReturn(['total' => 0, 'open' => 0]);
        $motionRepo->method('findCurrentOpen')->willReturn(null);
        $motionRepo->method('findNextNotOpened')->willReturn(null);
        $motionRepo->method('findLastClosedForProjector')->willReturn(null);
        $motionRepo->method('countConsolidatedMotions')->willReturn(0);

        $ballotRepo = $this->createMock(BallotRepository::class);

        $proxyRepo = $this->createMock(ProxyRepository::class);
        $proxyRepo->method('listDistinctGivers')->willReturn([
            ['giver_member_id' => 'mem-06'],
        ]);
        $proxyRepo->method('countActive')->willReturn(1);

        $notifRepo = $this->createMock(NotificationRepository::class);
        $notifRepo->method('findValidationState')->willReturn(null);

        $this->injectRepos([
            MeetingRepository::class      => $meetingRepo,
            MemberRepository::class       => $memberRepo,
            MeetingStatsRepository::class => $statsRepo,
            MotionRepository::class       => $motionRepo,
            BallotRepository::class       => $ballotRepo,
            ProxyRepository::class        => $proxyRepo,
            PolicyRepository::class       => $policyRepo,
            NotificationRepository::class => $notifRepo,
        ]);

        $resp = $this->callController(OperatorController::class, 'workflowState');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertSame(0.4, $data['attendance']['quorum_threshold']);
        $this->assertTrue($data['attendance']['quorum_ok']);
    }

    // =========================================================================
    // openVote — validation errors
    // =========================================================================

    public function testOpenVoteWrongMethod(): void
    {
        $this->setHttpMethod('GET');
        $this->injectJsonBody([]);

        // api_request('POST') on GET will throw 405
        $this->injectRepos([]);
        $resp = $this->callController(OperatorController::class, 'openVote');
        $this->assertSame(405, $resp['status']);
    }

    public function testOpenVoteMissingMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['motion_id' => self::MOTION_ID]);
        $this->injectRepos([]);

        $resp = $this->callController(OperatorController::class, 'openVote');
        $this->assertSame(422, $resp['status']);
        $this->assertSame('invalid_meeting_id', $resp['body']['error'] ?? $resp['body']['code'] ?? '');
    }

    public function testOpenVoteInvalidMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => 'not-a-uuid']);
        $this->injectRepos([]);

        $resp = $this->callController(OperatorController::class, 'openVote');
        $this->assertSame(422, $resp['status']);
    }

    public function testOpenVoteInvalidMotionId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => self::MEETING_ID, 'motion_id' => 'not-a-uuid']);
        $this->injectRepos([]);

        $resp = $this->callController(OperatorController::class, 'openVote');
        $this->assertSame(422, $resp['status']);
        $this->assertSame('invalid_motion_id', $resp['body']['error'] ?? $resp['body']['code'] ?? '');
    }

    // =========================================================================
    // anomalies — validation errors
    // =========================================================================

    public function testAnomaliesWrongMethod(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);
        $this->injectRepos([]);

        $resp = $this->callController(OperatorController::class, 'anomalies');
        $this->assertSame(405, $resp['status']);
    }

    public function testAnomaliesMissingMeetingId(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);
        $this->injectRepos([]);

        $resp = $this->callController(OperatorController::class, 'anomalies');
        $this->assertSame(422, $resp['status']);
        $this->assertSame('invalid_meeting_id', $resp['body']['error'] ?? $resp['body']['code'] ?? '');
    }

    public function testAnomaliesMeetingNotFound(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(null);

        // All repos are fetched before the meeting check
        $motionRepo  = $this->createMock(MotionRepository::class);
        $memberRepo  = $this->createMock(MemberRepository::class);
        $ballotRepo  = $this->createMock(BallotRepository::class);
        $tokenRepo   = $this->createMock(VoteTokenRepository::class);
        $proxyRepo   = $this->createMock(ProxyRepository::class);

        $this->injectRepos([
            MeetingRepository::class   => $meetingRepo,
            MotionRepository::class    => $motionRepo,
            MemberRepository::class    => $memberRepo,
            BallotRepository::class    => $ballotRepo,
            VoteTokenRepository::class => $tokenRepo,
            ProxyRepository::class     => $proxyRepo,
        ]);

        $resp = $this->callController(OperatorController::class, 'anomalies');
        $this->assertSame(404, $resp['status']);
    }

    public function testAnomaliesHappyPathNoMotion(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meeting = [
            'id' => self::MEETING_ID,
            'status' => 'live',
            'validated_at' => null,
        ];

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($meeting);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findCurrentOpen')->willReturn(null);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('listEligibleForMeeting')->willReturn([
            ['member_id' => 'mem-01', 'full_name' => 'Alice'],
            ['member_id' => 'mem-02', 'full_name' => 'Bob'],
        ]);
        $memberRepo->method('listActiveFallbackByMeeting')->willReturn([]);

        $proxyRepo = $this->createMock(ProxyRepository::class);
        $proxyRepo->method('listCeilingViolations')->willReturn([]);

        $ballotRepo  = $this->createMock(BallotRepository::class);
        $tokenRepo   = $this->createMock(VoteTokenRepository::class);

        $this->injectRepos([
            MeetingRepository::class  => $meetingRepo,
            MotionRepository::class   => $motionRepo,
            MemberRepository::class   => $memberRepo,
            ProxyRepository::class    => $proxyRepo,
            BallotRepository::class   => $ballotRepo,
            VoteTokenRepository::class => $tokenRepo,
        ]);

        $resp = $this->callController(OperatorController::class, 'anomalies');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertArrayHasKey('stats', $data);
        $this->assertArrayHasKey('anomalies', $data);
        $this->assertNull($data['motion']);
    }

    public function testAnomaliesHappyPathWithMotion(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID, 'motion_id' => self::MOTION_ID]);

        $meeting = [
            'id' => self::MEETING_ID,
            'status' => 'live',
            'validated_at' => null,
        ];

        $motion = [
            'id' => self::MOTION_ID,
            'title' => 'Test Motion',
            'opened_at' => date('Y-m-d H:i:s', time() - 600),
            'closed_at' => null,
        ];

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($meeting);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findByMeetingWithDates')->willReturn($motion);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('listEligibleForMeeting')->willReturn([
            ['member_id' => 'mem-01', 'full_name' => 'Alice'],
            ['member_id' => 'mem-02', 'full_name' => 'Bob'],
        ]);
        $memberRepo->method('listActiveFallbackByMeeting')->willReturn([]);

        $proxyRepo = $this->createMock(ProxyRepository::class);
        $proxyRepo->method('listCeilingViolations')->willReturn([]);

        $tokenRepo = $this->createMock(VoteTokenRepository::class);
        $tokenRepo->method('countActiveUnused')->willReturn(1);
        $tokenRepo->method('countExpiredUnused')->willReturn(0);
        $tokenRepo->method('countUsed')->willReturn(1);

        $ballotRepo = $this->createMock(BallotRepository::class);
        $ballotRepo->method('listForMotionWithSource')->willReturn([
            ['member_id' => 'mem-01', 'value' => 'for', 'source' => 'token', 'cast_at' => date('c')],
        ]);

        $this->injectRepos([
            MeetingRepository::class   => $meetingRepo,
            MotionRepository::class    => $motionRepo,
            MemberRepository::class    => $memberRepo,
            ProxyRepository::class     => $proxyRepo,
            BallotRepository::class    => $ballotRepo,
            VoteTokenRepository::class => $tokenRepo,
        ]);

        $resp = $this->callController(OperatorController::class, 'anomalies');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertSame(self::MOTION_ID, $data['motion']['id']);
        $this->assertSame(1, $data['stats']['ballots_total']);
        $this->assertSame(1, $data['stats']['ballots_from_eligible']);
        $this->assertSame(1, $data['stats']['missing_ballots_from_eligible']);
    }

    public function testAnomaliesDetectsDuplicateBallots(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID, 'motion_id' => self::MOTION_ID]);

        $meeting = ['id' => self::MEETING_ID, 'status' => 'live', 'validated_at' => null];
        $motion  = ['id' => self::MOTION_ID, 'title' => 'Dup Motion', 'opened_at' => date('Y-m-d H:i:s'), 'closed_at' => null];

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($meeting);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findByMeetingWithDates')->willReturn($motion);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('listEligibleForMeeting')->willReturn([
            ['member_id' => 'mem-01', 'full_name' => 'Alice'],
        ]);
        $memberRepo->method('listActiveFallbackByMeeting')->willReturn([]);

        $proxyRepo = $this->createMock(ProxyRepository::class);
        $proxyRepo->method('listCeilingViolations')->willReturn([]);

        $tokenRepo = $this->createMock(VoteTokenRepository::class);
        $tokenRepo->method('countActiveUnused')->willReturn(0);
        $tokenRepo->method('countExpiredUnused')->willReturn(0);
        $tokenRepo->method('countUsed')->willReturn(2);

        // Two ballots for same member = duplicate
        $ballotRepo = $this->createMock(BallotRepository::class);
        $ballotRepo->method('listForMotionWithSource')->willReturn([
            ['member_id' => 'mem-01', 'value' => 'for',   'source' => 'token', 'cast_at' => date('c')],
            ['member_id' => 'mem-01', 'value' => 'against', 'source' => 'manual', 'cast_at' => date('c')],
        ]);

        $this->injectRepos([
            MeetingRepository::class   => $meetingRepo,
            MotionRepository::class    => $motionRepo,
            MemberRepository::class    => $memberRepo,
            ProxyRepository::class     => $proxyRepo,
            BallotRepository::class    => $ballotRepo,
            VoteTokenRepository::class => $tokenRepo,
        ]);

        $resp = $this->callController(OperatorController::class, 'anomalies');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertCount(1, $data['anomalies']['duplicates']);
        $this->assertSame('mem-01', $data['anomalies']['duplicates'][0]['member_id']);
    }

    public function testAnomaliesInvalidMotionId(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID, 'motion_id' => 'bad-uuid']);
        $this->injectRepos([]);

        $resp = $this->callController(OperatorController::class, 'anomalies');
        $this->assertSame(422, $resp['status']);
    }

    public function testAnomaliesMotionNotFound(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID, 'motion_id' => self::MOTION_ID]);

        $meeting = ['id' => self::MEETING_ID, 'status' => 'live', 'validated_at' => null];

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn($meeting);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findByMeetingWithDates')->willReturn(null);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('listEligibleForMeeting')->willReturn([]);
        $memberRepo->method('listActiveFallbackByMeeting')->willReturn([]);

        $proxyRepo  = $this->createMock(ProxyRepository::class);
        $ballotRepo = $this->createMock(BallotRepository::class);
        $tokenRepo  = $this->createMock(VoteTokenRepository::class);

        $this->injectRepos([
            MeetingRepository::class   => $meetingRepo,
            MotionRepository::class    => $motionRepo,
            MemberRepository::class    => $memberRepo,
            ProxyRepository::class     => $proxyRepo,
            BallotRepository::class    => $ballotRepo,
            VoteTokenRepository::class => $tokenRepo,
        ]);

        $resp = $this->callController(OperatorController::class, 'anomalies');
        $this->assertSame(404, $resp['status']);
    }
}
