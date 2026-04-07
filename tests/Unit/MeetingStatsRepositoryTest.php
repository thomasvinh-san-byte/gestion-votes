<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Repository\MeetingStatsRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MeetingStatsRepository::getDashboardStats().
 *
 * PDO is mocked to verify:
 * 1. getDashboardStats() executes exactly one SQL query (one prepare() call)
 * 2. The returned array contains all 12 expected keys
 */
class MeetingStatsRepositoryTest extends TestCase
{
    private const MEETING_ID = 'aaaaaaaa-1111-2222-3333-000000000001';
    private const TENANT_ID  = 'aaaaaaaa-1111-2222-3333-000000000002';

    /** @var PDO&MockObject */
    private PDO $mockPdo;

    /** @var PDOStatement&MockObject */
    private PDOStatement $mockStmt;

    private MeetingStatsRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockStmt = $this->createMock(PDOStatement::class);
        $this->mockPdo  = $this->createMock(PDO::class);

        $this->repo = new MeetingStatsRepository($this->mockPdo);
    }

    // =========================================================================
    // Test 5 — getDashboardStats() returns array with all expected keys
    // =========================================================================

    public function testGetDashboardStatsReturnsAllExpectedKeys(): void
    {
        $fakeRow = [
            'present_count'      => 5,
            'proxy_count'        => 2,
            'total_motions'      => 10,
            'closed_motions'     => 7,
            'open_motions'       => 3,
            'adopted_motions'    => 4,
            'rejected_motions'   => 3,
            'ballots_count'      => 50,
            'ballot_weight'      => 50.0,
            'proxies_count'      => 2,
            'incidents_count'    => 1,
            'manual_votes_count' => 5,
        ];

        $this->mockStmt->method('execute')->willReturn(true);
        $this->mockStmt->method('fetch')->willReturn($fakeRow);

        $this->mockPdo
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $result = $this->repo->getDashboardStats(self::MEETING_ID, self::TENANT_ID);

        $expectedKeys = [
            'present_count', 'proxy_count', 'total_motions', 'closed_motions',
            'open_motions', 'adopted_motions', 'rejected_motions', 'ballots_count',
            'ballot_weight', 'proxies_count', 'incidents_count', 'manual_votes_count',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey(
                $key,
                $result,
                "getDashboardStats() must return key '{$key}'"
            );
        }
    }

    // =========================================================================
    // Test 6 — getDashboardStats() executes exactly one SQL query
    // =========================================================================

    public function testGetDashboardStatsExecutesExactlyOneQuery(): void
    {
        $fakeRow = [
            'present_count'      => 0,
            'proxy_count'        => 0,
            'total_motions'      => 0,
            'closed_motions'     => 0,
            'open_motions'       => 0,
            'adopted_motions'    => 0,
            'rejected_motions'   => 0,
            'ballots_count'      => 0,
            'ballot_weight'      => 0.0,
            'proxies_count'      => 0,
            'incidents_count'    => 0,
            'manual_votes_count' => 0,
        ];

        $this->mockStmt->method('execute')->willReturn(true);
        $this->mockStmt->method('fetch')->willReturn($fakeRow);

        // prepare() must be called exactly once — one round-trip
        $this->mockPdo
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->repo->getDashboardStats(self::MEETING_ID, self::TENANT_ID);
    }

    // =========================================================================
    // Additional — returns empty array (not null) when row is null
    // =========================================================================

    public function testGetDashboardStatsReturnsEmptyArrayWhenRowIsNull(): void
    {
        $this->mockStmt->method('execute')->willReturn(true);
        $this->mockStmt->method('fetch')->willReturn(false);

        $this->mockPdo
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $result = $this->repo->getDashboardStats(self::MEETING_ID, self::TENANT_ID);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
