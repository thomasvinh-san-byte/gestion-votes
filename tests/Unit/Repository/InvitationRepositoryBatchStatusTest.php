<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use AgVote\Repository\InvitationRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * PERF-V27-02 — proves InvitationRepository::findStatusesByMeetingAndMembers()
 * uses a single SQL round-trip (replaces N findStatusByMeetingAndMember() calls
 * in EmailController::sendBulkInvitations() when only_unsent=true).
 */
final class InvitationRepositoryBatchStatusTest extends TestCase
{
    private const TENANT_ID  = 'aaaaaaaa-1111-2222-3333-000000000001';
    private const MEETING_ID = 'bbbbbbbb-1111-2222-3333-000000000001';

    /** @var PDO&MockObject */
    private PDO $mockPdo;

    /** @var PDOStatement&MockObject */
    private PDOStatement $mockStmt;

    private InvitationRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockStmt = $this->createMock(PDOStatement::class);
        $this->mockPdo  = $this->createMock(PDO::class);
        $this->repo     = new InvitationRepository($this->mockPdo);
    }

    public function testFindStatusesByMeetingAndMembersReturnsMap(): void
    {
        $id1 = 'cccccccc-1111-2222-3333-000000000001';
        $id2 = 'cccccccc-1111-2222-3333-000000000002';

        $this->mockStmt->method('execute')->willReturn(true);
        $this->mockStmt->method('fetchAll')->willReturn([
            ['member_id' => $id1, 'status' => 'sent'],
            ['member_id' => $id2, 'status' => 'pending'],
        ]);
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);

        $result = $this->repo->findStatusesByMeetingAndMembers(
            self::MEETING_ID,
            [$id1, $id2],
            self::TENANT_ID,
        );

        $this->assertSame('sent', $result[$id1]);
        $this->assertSame('pending', $result[$id2]);
    }

    /**
     * Nyquist gate: N=20 member IDs -> exactly 1 prepare() call.
     */
    public function testFindStatusesByMeetingAndMembersExecutesExactlyOneQuery(): void
    {
        $ids = [];
        for ($i = 0; $i < 20; $i++) {
            $ids[] = sprintf('cccccccc-1111-2222-3333-%012d', $i);
        }

        $this->mockStmt->method('execute')->willReturn(true);
        $this->mockStmt->method('fetchAll')->willReturn([]);

        $this->mockPdo
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->repo->findStatusesByMeetingAndMembers(self::MEETING_ID, $ids, self::TENANT_ID);
    }

    public function testFindStatusesByMeetingAndMembersEmptyArrayReturnsEmptyMap(): void
    {
        $this->mockPdo->expects($this->never())->method('prepare');

        $result = $this->repo->findStatusesByMeetingAndMembers(self::MEETING_ID, [], self::TENANT_ID);

        $this->assertSame([], $result);
    }
}
