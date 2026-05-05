<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use AgVote\Repository\MemberGroupRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * PERF-V27-02 — proves MemberGroupRepository::findManyByIds() batches existence
 * lookup into a single SQL query (replaces N foreach calls in
 * MemberGroupsController::assignMemberToGroups()).
 */
final class MemberGroupRepositoryBatchFindTest extends TestCase
{
    private const TENANT_ID = 'aaaaaaaa-1111-2222-3333-000000000001';

    /** @var PDO&MockObject */
    private PDO $mockPdo;

    /** @var PDOStatement&MockObject */
    private PDOStatement $mockStmt;

    private MemberGroupRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockStmt = $this->createMock(PDOStatement::class);
        $this->mockPdo  = $this->createMock(PDO::class);
        $this->repo     = new MemberGroupRepository($this->mockPdo);
    }

    public function testFindManyByIdsReturnsMapIndexedById(): void
    {
        $id1 = 'cccccccc-1111-2222-3333-000000000001';
        $id2 = 'cccccccc-1111-2222-3333-000000000002';

        $this->mockStmt->method('execute')->willReturn(true);
        $this->mockStmt->method('fetchAll')->willReturn([
            ['id' => $id1, 'name' => 'Conseil', 'tenant_id' => self::TENANT_ID,
             'description' => null, 'color' => null, 'sort_order' => 1,
             'is_active' => true, 'created_at' => null, 'updated_at' => null],
            ['id' => $id2, 'name' => 'Bureau', 'tenant_id' => self::TENANT_ID,
             'description' => null, 'color' => null, 'sort_order' => 2,
             'is_active' => true, 'created_at' => null, 'updated_at' => null],
        ]);
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);

        $result = $this->repo->findManyByIds([$id1, $id2], self::TENANT_ID);

        $this->assertSame('Conseil', $result[$id1]['name']);
        $this->assertSame('Bureau', $result[$id2]['name']);
    }

    /**
     * Nyquist gate: 8 group IDs -> exactly 1 prepare() call.
     */
    public function testFindManyByIdsExecutesExactlyOneQuery(): void
    {
        $ids = [];
        for ($i = 0; $i < 8; $i++) {
            $ids[] = sprintf('cccccccc-1111-2222-3333-%012d', $i);
        }

        $this->mockStmt->method('execute')->willReturn(true);
        $this->mockStmt->method('fetchAll')->willReturn([]);

        $this->mockPdo
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->repo->findManyByIds($ids, self::TENANT_ID);
    }

    public function testFindManyByIdsEmptyArrayReturnsEmptyMap(): void
    {
        $this->mockPdo->expects($this->never())->method('prepare');

        $result = $this->repo->findManyByIds([], self::TENANT_ID);

        $this->assertSame([], $result);
    }

    public function testFindManyByIdsMissingIdsAreAbsentFromMap(): void
    {
        $present = 'cccccccc-1111-2222-3333-000000000001';
        $missing = 'cccccccc-1111-2222-3333-000000000099';

        $this->mockStmt->method('execute')->willReturn(true);
        $this->mockStmt->method('fetchAll')->willReturn([
            ['id' => $present, 'name' => 'Existant', 'tenant_id' => self::TENANT_ID,
             'description' => null, 'color' => null, 'sort_order' => 1,
             'is_active' => true, 'created_at' => null, 'updated_at' => null],
        ]);
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);

        $result = $this->repo->findManyByIds([$present, $missing], self::TENANT_ID);

        $this->assertArrayHasKey($present, $result);
        $this->assertArrayNotHasKey($missing, $result);
    }
}
