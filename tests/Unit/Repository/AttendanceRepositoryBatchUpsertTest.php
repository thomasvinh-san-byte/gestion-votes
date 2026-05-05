<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use AgVote\Repository\AttendanceRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * PERF-V27-02 — proves AttendanceRepository::upsertModeBulk() runs a single
 * batched INSERT … ON CONFLICT RETURNING (replaces N upsertMode() calls in
 * AttendancesController::bulkUpdate()).
 */
final class AttendanceRepositoryBatchUpsertTest extends TestCase
{
    private const TENANT_ID  = 'aaaaaaaa-1111-2222-3333-000000000001';
    private const MEETING_ID = 'bbbbbbbb-1111-2222-3333-000000000001';

    /** @var PDO&MockObject */
    private PDO $mockPdo;

    /** @var PDOStatement&MockObject */
    private PDOStatement $mockStmt;

    private AttendanceRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockStmt = $this->createMock(PDOStatement::class);
        $this->mockPdo  = $this->createMock(PDO::class);
        $this->repo     = new AttendanceRepository($this->mockPdo);
    }

    public function testUpsertModeBulkReturnsCreatedAndUpdatedCounts(): void
    {
        // Two newly inserted, one already existed.
        $this->mockStmt->method('execute')->willReturn(true);
        $this->mockStmt->method('fetchAll')->willReturn([
            ['inserted' => true],
            ['inserted' => false],
            ['inserted' => true],
        ]);
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);

        $result = $this->repo->upsertModeBulk(
            self::MEETING_ID,
            ['id-1', 'id-2', 'id-3'],
            'present',
            self::TENANT_ID,
        );

        $this->assertSame(2, $result['created']);
        $this->assertSame(1, $result['updated']);
    }

    /**
     * Nyquist gate: N=15 member IDs -> exactly 1 prepare() call.
     */
    public function testUpsertModeBulkExecutesExactlyOneQuery(): void
    {
        $ids = [];
        for ($i = 0; $i < 15; $i++) {
            $ids[] = sprintf('cccccccc-1111-2222-3333-%012d', $i);
        }

        $this->mockStmt->method('execute')->willReturn(true);
        $this->mockStmt->method('fetchAll')->willReturn([]);

        $this->mockPdo
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->repo->upsertModeBulk(self::MEETING_ID, $ids, 'present', self::TENANT_ID);
    }

    public function testUpsertModeBulkEmptyArrayReturnsZeroWithoutHittingDb(): void
    {
        $this->mockPdo->expects($this->never())->method('prepare');

        $result = $this->repo->upsertModeBulk(self::MEETING_ID, [], 'present', self::TENANT_ID);

        $this->assertSame(['created' => 0, 'updated' => 0], $result);
    }
}
