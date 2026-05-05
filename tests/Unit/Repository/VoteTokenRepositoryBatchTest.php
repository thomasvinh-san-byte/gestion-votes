<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use AgVote\Repository\VoteTokenRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * PERF-V27-02 — proves VoteTokenRepository batch methods reduce N round-trips
 * to 1 (one for delete, one for insert) regardless of voter count. Replaces the
 * 2*N foreach pattern in VoteTokenController::generate().
 */
final class VoteTokenRepositoryBatchTest extends TestCase
{
    private const TENANT_ID  = 'aaaaaaaa-1111-2222-3333-000000000001';
    private const MEETING_ID = 'bbbbbbbb-1111-2222-3333-000000000001';
    private const MOTION_ID  = 'cccccccc-1111-2222-3333-000000000001';

    /** @var PDO&MockObject */
    private PDO $mockPdo;

    /** @var PDOStatement&MockObject */
    private PDOStatement $mockStmt;

    private VoteTokenRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockStmt = $this->createMock(PDOStatement::class);
        $this->mockPdo  = $this->createMock(PDO::class);
        $this->repo     = new VoteTokenRepository($this->mockPdo);
    }

    /**
     * Nyquist gate: deleting tokens for 12 members -> exactly 1 prepare() call.
     */
    public function testDeleteUnusedByMotionAndMembersExecutesExactlyOneQuery(): void
    {
        $ids = [];
        for ($i = 0; $i < 12; $i++) {
            $ids[] = sprintf('dddddddd-1111-2222-3333-%012d', $i);
        }

        $this->mockStmt->method('execute')->willReturn(true);
        $this->mockPdo
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->repo->deleteUnusedByMotionAndMembers(
            self::MEETING_ID,
            self::MOTION_ID,
            $ids,
            self::TENANT_ID,
        );
    }

    public function testDeleteUnusedByMotionAndMembersEmptyIsNoOp(): void
    {
        $this->mockPdo->expects($this->never())->method('prepare');

        $this->repo->deleteUnusedByMotionAndMembers(
            self::MEETING_ID,
            self::MOTION_ID,
            [],
            self::TENANT_ID,
        );
    }

    /**
     * Nyquist gate: inserting tokens for 12 members -> exactly 1 prepare() call.
     */
    public function testInsertManyExecutesExactlyOneQuery(): void
    {
        $rows = [];
        for ($i = 0; $i < 12; $i++) {
            $rows[] = [
                'token_hash' => "hash-{$i}",
                'tenant_id'  => self::TENANT_ID,
                'meeting_id' => self::MEETING_ID,
                'member_id'  => sprintf('dddddddd-1111-2222-3333-%012d', $i),
                'motion_id'  => self::MOTION_ID,
                'expires_at' => '2030-01-01 00:00:00+00',
            ];
        }

        $this->mockStmt->method('execute')->willReturn(true);
        $this->mockPdo
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->repo->insertMany($rows);
    }

    public function testInsertManyEmptyIsNoOp(): void
    {
        $this->mockPdo->expects($this->never())->method('prepare');
        $this->repo->insertMany([]);
    }
}
