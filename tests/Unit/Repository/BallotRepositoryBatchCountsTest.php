<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use AgVote\Repository\BallotRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * PERF-V27-02 — proves BallotRepository::countByMotionIds() executes a single SQL
 * query regardless of the number of motion IDs (replaces N foreach calls in
 * DashboardController::index()).
 *
 * Mocks PDO::prepare() to count round-trips (Nyquist gate: N -> 1).
 */
final class BallotRepositoryBatchCountsTest extends TestCase
{
    private const TENANT_ID  = 'aaaaaaaa-1111-2222-3333-000000000001';
    private const MEETING_ID = 'bbbbbbbb-1111-2222-3333-000000000001';

    /** @var PDO&MockObject */
    private PDO $mockPdo;

    /** @var PDOStatement&MockObject */
    private PDOStatement $mockStmt;

    private BallotRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockStmt = $this->createMock(PDOStatement::class);
        $this->mockPdo  = $this->createMock(PDO::class);
        $this->repo     = new BallotRepository($this->mockPdo);
    }

    public function testCountByMotionIdsReturnsMapForExistingMotions(): void
    {
        $id1 = 'cccccccc-1111-2222-3333-000000000001';
        $id2 = 'cccccccc-1111-2222-3333-000000000002';
        $id3 = 'cccccccc-1111-2222-3333-000000000003';

        // DB returns only motions that have ballots; id2 has zero, so absent from rows.
        $rows = [
            ['motion_id' => $id1, 'cnt' => 5],
            ['motion_id' => $id3, 'cnt' => 12],
        ];

        $this->mockStmt->method('execute')->willReturn(true);
        $this->mockStmt->method('fetchAll')->willReturn($rows);
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);

        $result = $this->repo->countByMotionIds([$id1, $id2, $id3], self::TENANT_ID, self::MEETING_ID);

        $this->assertSame(5, $result[$id1]);
        $this->assertSame(0, $result[$id2], 'Missing motion id must default to 0');
        $this->assertSame(12, $result[$id3]);
    }

    /**
     * Nyquist gate: 10 motion IDs -> exactly 1 prepare() call (not 10).
     */
    public function testCountByMotionIdsExecutesExactlyOneQuery(): void
    {
        $ids = [];
        for ($i = 0; $i < 10; $i++) {
            $ids[] = sprintf('cccccccc-1111-2222-3333-%012d', $i);
        }

        $this->mockStmt->method('execute')->willReturn(true);
        $this->mockStmt->method('fetchAll')->willReturn([]);

        // CRITICAL ASSERTION: prepare() called exactly once -> single round-trip.
        $this->mockPdo
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->repo->countByMotionIds($ids, self::TENANT_ID, self::MEETING_ID);
    }

    public function testCountByMotionIdsEmptyArrayReturnsEmptyMapWithoutHittingDb(): void
    {
        // prepare() must NEVER be called for an empty input.
        $this->mockPdo
            ->expects($this->never())
            ->method('prepare');

        $result = $this->repo->countByMotionIds([], self::TENANT_ID, self::MEETING_ID);

        $this->assertSame([], $result);
    }

    public function testCountByMotionIdsZeroFillsMissingIds(): void
    {
        $id1 = 'cccccccc-1111-2222-3333-000000000010';
        $id2 = 'cccccccc-1111-2222-3333-000000000011';

        // DB returns no rows at all (none of the motions have ballots).
        $this->mockStmt->method('execute')->willReturn(true);
        $this->mockStmt->method('fetchAll')->willReturn([]);
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);

        $result = $this->repo->countByMotionIds([$id1, $id2], self::TENANT_ID, self::MEETING_ID);

        $this->assertSame([$id1 => 0, $id2 => 0], $result);
    }
}
