<?php

declare(strict_types=1);

namespace AgVote\Tests\Unit;

use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\ProxyRepository;
use AgVote\Service\AttendancesService;
use AgVote\Service\BallotsService;
use AgVote\Service\ProxiesService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for DATA-01 and DATA-02 data integrity lock fixes.
 *
 * DATA-01: BallotsService::castBallot() re-checks proxy validity using
 *          hasActiveProxyForUpdate (FOR UPDATE) inside the transaction.
 *
 * DATA-02: VotePublicController::doVote() locks the motion row inside the
 *          transaction via findByIdForTenantForUpdate before inserting the ballot.
 *          (Tested indirectly via source-level assertion; controller uses exit().)
 */
class DataIntegrityLocksTest extends TestCase {
    private const TENANT = 'aaaaaaaa-1111-2222-3333-444444444444';
    private const MEETING = 'bbbbbbbb-1111-2222-3333-444444444444';
    private const MOTION = 'cccccccc-1111-2222-3333-444444444444';
    private const MEMBER = 'dddddddd-1111-2222-3333-444444444444';
    private const PROXY_VOTER = 'eeeeeeee-1111-4222-a333-444444444444';

    private ProxyRepository&MockObject $proxyRepo;
    private BallotRepository&MockObject $ballotRepo;
    private MotionRepository&MockObject $motionRepo;
    private MemberRepository&MockObject $memberRepo;
    private MeetingRepository&MockObject $meetingRepo;
    private AttendanceRepository&MockObject $attendanceRepo;
    private BallotsService $ballotsService;

    protected function setUp(): void {
        $this->proxyRepo = $this->createMock(ProxyRepository::class);
        $this->ballotRepo = $this->createMock(BallotRepository::class);
        $this->motionRepo = $this->createMock(MotionRepository::class);
        $this->memberRepo = $this->createMock(MemberRepository::class);
        $this->meetingRepo = $this->createMock(MeetingRepository::class);
        $this->attendanceRepo = $this->createMock(AttendanceRepository::class);

        $attendancesService = new AttendancesService(
            $this->attendanceRepo,
            $this->createMock(MeetingRepository::class),
            $this->createMock(MemberRepository::class),
        );

        $proxiesService = new ProxiesService($this->proxyRepo);

        $this->ballotsService = new BallotsService(
            $this->ballotRepo,
            $this->motionRepo,
            $this->memberRepo,
            $this->meetingRepo,
            $attendancesService,
            $proxiesService,
        );
    }

    // =========================================================================
    // ProxyRepository::hasActiveProxyForUpdate
    // =========================================================================

    /**
     * @test DATA-01: hasActiveProxyForUpdate returns true when a matching proxy row exists.
     */
    public function testHasActiveProxyForUpdateReturnsTrueWhenRowExists(): void {
        $repo = $this->getMockBuilder(ProxyRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['selectAll'])
            ->getMock();

        $repo->expects($this->once())
            ->method('selectAll')
            ->willReturn([['id' => 'proxy-1']]);

        $result = $repo->hasActiveProxyForUpdate(
            self::MEETING,
            self::MEMBER,
            self::PROXY_VOTER,
            self::TENANT,
        );

        $this->assertTrue($result);
    }

    /**
     * @test DATA-01: hasActiveProxyForUpdate returns false when no matching rows exist.
     */
    public function testHasActiveProxyForUpdateReturnsFalseWhenNoRows(): void {
        $repo = $this->getMockBuilder(ProxyRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['selectAll'])
            ->getMock();

        $repo->expects($this->once())
            ->method('selectAll')
            ->willReturn([]);

        $result = $repo->hasActiveProxyForUpdate(
            self::MEETING,
            self::MEMBER,
            self::PROXY_VOTER,
            self::TENANT,
        );

        $this->assertFalse($result);
    }

    // =========================================================================
    // BallotsService: in-transaction proxy re-check uses hasActiveProxyForUpdate
    // =========================================================================

    /**
     * @test DATA-01: BallotsService::castBallot() completes successfully when
     *               hasActiveProxyForUpdate returns true inside the transaction.
     */
    public function testCastBallotSucceedsWhenHasActiveProxyForUpdateReturnsTrue(): void {
        $this->setupProxyVoteContext();

        // Initial pre-check (hasActiveProxy) passes
        $this->proxyRepo->method('hasActiveProxy')
            ->willReturn(true);

        // In-transaction re-check (hasActiveProxyForUpdate) also passes
        $this->proxyRepo->method('hasActiveProxyForUpdate')
            ->willReturn(true);

        $this->meetingRepo->method('lockForUpdate')
            ->willReturn(['id' => self::MEETING, 'status' => 'live']);

        $this->ballotRepo->method('findByMotionAndMember')
            ->willReturn([
                'motion_id' => self::MOTION,
                'member_id' => self::MEMBER,
                'value' => 'for',
                'weight' => 1.0,
            ]);

        // Should complete without throwing
        $result = $this->ballotsService->castBallot($this->proxyVoteData());

        $this->assertSame('for', $result['value']);
    }

    /**
     * @test DATA-01: BallotsService::castBallot() throws when hasActiveProxyForUpdate
     *               returns false inside the transaction (proxy revoked between checks).
     */
    public function testCastBallotThrowsWhenHasActiveProxyForUpdateReturnsFalse(): void {
        $this->setupProxyVoteContext();

        // Initial pre-check passes (proxy was active before transaction)
        $this->proxyRepo->method('hasActiveProxy')
            ->willReturn(true);

        // In-transaction re-check: proxy was revoked concurrently
        $this->proxyRepo->method('hasActiveProxyForUpdate')
            ->willReturn(false);

        $this->meetingRepo->method('lockForUpdate')
            ->willReturn(['id' => self::MEETING, 'status' => 'live']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Procuration révoquée avant le vote');

        $this->ballotsService->castBallot($this->proxyVoteData());
    }

    // =========================================================================
    // DATA-02: VotePublicController source-level assertion
    // =========================================================================

    /**
     * @test DATA-02: VotePublicController source contains findByIdForTenantForUpdate
     *               inside the api_transaction closure (source-level assertion).
     */
    public function testVotePublicControllerSourceContainsFindByIdForTenantForUpdate(): void {
        $controllerPath = __DIR__ . '/../../app/Controller/VotePublicController.php';
        $source = file_get_contents($controllerPath);

        $this->assertNotFalse($source, 'Could not read VotePublicController.php');
        $this->assertStringContainsString(
            'findByIdForTenantForUpdate',
            $source,
            'VotePublicController must call findByIdForTenantForUpdate inside the transaction',
        );
        $this->assertStringContainsString(
            'motion_closed_concurrent',
            $source,
            'VotePublicController must handle motion_closed_concurrent exception',
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function setupProxyVoteContext(): void {
        $context = [
            'motion_id' => self::MOTION,
            'motion_opened_at' => '2026-01-01 10:00:00',
            'motion_closed_at' => null,
            'meeting_id' => self::MEETING,
            'meeting_status' => 'live',
            'meeting_validated_at' => null,
            'tenant_id' => self::TENANT,
        ];

        $this->motionRepo->method('findWithBallotContext')
            ->willReturn($context);

        $this->memberRepo->method('findByIdForTenant')
            ->willReturn([
                'id' => self::MEMBER,
                'voting_power' => 1.0,
                'is_active' => true,
                'tenant_id' => self::TENANT,
            ]);

        $this->attendanceRepo->method('isPresentDirect')
            ->willReturn(true);
    }

    private function proxyVoteData(): array {
        return [
            'motion_id' => self::MOTION,
            'member_id' => self::MEMBER,
            'value' => 'for',
            '_tenant_id' => self::TENANT,
            'is_proxy_vote' => true,
            'proxy_source_member_id' => self::PROXY_VOTER,
        ];
    }
}
