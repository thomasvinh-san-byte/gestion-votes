<?php
declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Providers\RepositoryFactory;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MeetingStatsRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\NotificationRepository;
use AgVote\Repository\ProxyRepository;
use AgVote\Repository\VoteTokenRepository;
use AgVote\Service\OperatorWorkflowService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Unit tests for OperatorWorkflowService.
 *
 * All repository dependencies are mocked via RepositoryFactory; no database connection needed.
 */
class OperatorWorkflowServiceTest extends TestCase {
    private const TENANT = 'aaaaaaaa-1111-2222-3333-444444444444';
    private const MEETING = 'bbbbbbbb-1111-2222-3333-444444444444';

    private MeetingRepository&MockObject $meetingRepo;
    private MotionRepository&MockObject $motionRepo;
    private MemberRepository&MockObject $memberRepo;
    private MeetingStatsRepository&MockObject $statsRepo;
    private BallotRepository&MockObject $ballotRepo;
    private ProxyRepository&MockObject $proxyRepo;
    private OperatorWorkflowService $service;

    protected function setUp(): void {
        $factory = new RepositoryFactory(null);
        $ref = new ReflectionClass(RepositoryFactory::class);
        $cacheProp = $ref->getProperty('cache');
        $cacheProp->setAccessible(true);

        $this->meetingRepo = $this->createMock(MeetingRepository::class);
        $this->motionRepo = $this->createMock(MotionRepository::class);
        $this->memberRepo = $this->createMock(MemberRepository::class);
        $this->statsRepo = $this->createMock(MeetingStatsRepository::class);
        $this->ballotRepo = $this->createMock(BallotRepository::class);
        $this->proxyRepo = $this->createMock(ProxyRepository::class);
        $notifRepo = $this->createMock(NotificationRepository::class);
        $notifRepo->method('findValidationState')->willReturn(null);
        $tokenRepo = $this->createMock(VoteTokenRepository::class);

        $cacheProp->setValue($factory, [
            MeetingRepository::class => $this->meetingRepo,
            MotionRepository::class => $this->motionRepo,
            MemberRepository::class => $this->memberRepo,
            MeetingStatsRepository::class => $this->statsRepo,
            BallotRepository::class => $this->ballotRepo,
            ProxyRepository::class => $this->proxyRepo,
            NotificationRepository::class => $notifRepo,
            VoteTokenRepository::class => $tokenRepo,
        ]);

        // Set singleton so MeetingValidator/NotificationsService pick up the same factory
        $instanceProp = $ref->getProperty('instance');
        $instanceProp->setAccessible(true);
        $instanceProp->setValue(null, $factory);

        $this->service = new OperatorWorkflowService($factory);
    }

    protected function tearDown(): void {
        RepositoryFactory::reset();
    }

    // =========================================================================
    // Structural tests
    // =========================================================================

    public function testServiceIsFinal(): void {
        $ref = new ReflectionClass(OperatorWorkflowService::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testConstructorAcceptsNullableRepoFactory(): void {
        $ref = new ReflectionClass(OperatorWorkflowService::class);
        $constructor = $ref->getConstructor();
        $this->assertNotNull($constructor);
        $params = $constructor->getParameters();
        $this->assertCount(1, $params);
        $this->assertTrue($params[0]->allowsNull());
    }

    public function testHasExpectedMethods(): void {
        $ref = new ReflectionClass(OperatorWorkflowService::class);
        foreach (['getWorkflowState', 'openVote', 'getAnomalies'] as $method) {
            $this->assertTrue($ref->hasMethod($method), "Missing method: {$method}");
        }
    }

    // =========================================================================
    // getWorkflowState
    // =========================================================================

    public function testGetWorkflowStateThrowsOnMissingMeeting(): void {
        $this->meetingRepo->method('findByIdForTenant')->willReturn(null);
        $this->memberRepo->method('countActive')->willReturn(0);
        $this->memberRepo->method('listWithAttendanceForMeeting')->willReturn([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('meeting_not_found');
        $this->service->getWorkflowState(self::MEETING, self::TENANT);
    }

    public function testGetWorkflowStateReturnsExpectedKeys(): void {
        $this->meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING, 'title' => 'Test', 'status' => 'live',
            'president_name' => 'Pres', 'quorum_policy_id' => null,
        ]);
        $this->memberRepo->method('countActive')->willReturn(10);
        $this->memberRepo->method('listWithAttendanceForMeeting')->willReturn([
            ['member_id' => 'mem-01', 'full_name' => 'Alice', 'attendance_mode' => 'present', 'voting_power' => '1'],
        ]);
        $this->statsRepo->method('countClosedMotions')->willReturn(0);
        $this->motionRepo->method('countWorkflowSummary')->willReturn(['total' => 0, 'open' => 0]);
        $this->motionRepo->method('findCurrentOpen')->willReturn(null);
        $this->motionRepo->method('findNextNotOpened')->willReturn(null);
        $this->motionRepo->method('findLastClosedForProjector')->willReturn(null);
        $this->motionRepo->method('countConsolidatedMotions')->willReturn(0);
        $this->proxyRepo->method('listDistinctGivers')->willReturn([]);
        $this->proxyRepo->method('countActive')->willReturn(0);

        $result = $this->service->getWorkflowState(self::MEETING, self::TENANT);
        $this->assertArrayHasKey('meeting', $result);
        $this->assertArrayHasKey('attendance', $result);
        $this->assertArrayHasKey('motion', $result);
        $this->assertArrayHasKey('consolidation', $result);
        $this->assertArrayHasKey('validation', $result);
    }

    // =========================================================================
    // getAnomalies
    // =========================================================================

    public function testGetAnomaliesThrowsOnMissingMeeting(): void {
        $this->meetingRepo->method('findByIdForTenant')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('meeting_not_found');
        $this->service->getAnomalies(self::MEETING, self::TENANT);
    }

    public function testGetAnomaliesReturnsExpectedKeysNoMotion(): void {
        $this->meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING, 'status' => 'live', 'validated_at' => null,
        ]);
        $this->motionRepo->method('findCurrentOpen')->willReturn(null);
        $this->memberRepo->method('listEligibleForMeeting')->willReturn([
            ['member_id' => 'mem-01', 'full_name' => 'Alice'],
        ]);
        $this->proxyRepo->method('listCeilingViolations')->willReturn([]);

        $result = $this->service->getAnomalies(self::MEETING, self::TENANT);
        $this->assertArrayHasKey('stats', $result);
        $this->assertArrayHasKey('anomalies', $result);
        $this->assertNull($result['motion']);
    }
}
