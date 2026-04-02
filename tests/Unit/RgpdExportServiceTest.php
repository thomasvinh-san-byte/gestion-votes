<?php

declare(strict_types=1);

namespace AgVote\Tests\Unit;

use AgVote\Service\RgpdExportService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RgpdExportService.
 *
 * All database interactions are mocked via PDO injection — no database required.
 *
 * Test coverage:
 *  1. exportForUser returns array with keys: profile, votes, attendances, exported_at
 *  2. profile sub-array contains expected fields and excludes password_hash
 *  3. votes sub-array items contain motion_title, meeting_title, meeting_date, value, weight, cast_at
 *  4. attendances sub-array items contain meeting_title, meeting_date, mode, checked_in_at
 *  5. user with no linked member record returns profile=null, votes=[], attendances=[]
 */
class RgpdExportServiceTest extends TestCase
{
    private const USER_ID   = 'aaaaaaaa-1111-2222-3333-444444444444';
    private const TENANT_ID = 'bbbbbbbb-1111-2222-3333-444444444444';
    private const MEMBER_ID = 'cccccccc-1111-2222-3333-444444444444';

    /** @var \PDO&MockObject */
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(\PDO::class);
    }

    // =========================================================================
    // Test 1: exportForUser returns array with required top-level keys
    // =========================================================================

    public function testExportForUserReturnsArrayWithRequiredKeys(): void
    {
        [$memberStmt, $votesStmt, $attendancesStmt] = $this->buildMockStatementsWithMember();

        $this->pdo->expects($this->exactly(3))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($memberStmt, $votesStmt, $attendancesStmt);

        $service = new RgpdExportService($this->pdo);
        $result  = $service->exportForUser(self::USER_ID, self::TENANT_ID);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('profile', $result);
        $this->assertArrayHasKey('votes', $result);
        $this->assertArrayHasKey('attendances', $result);
        $this->assertArrayHasKey('exported_at', $result);
    }

    // =========================================================================
    // Test 2: profile sub-array contains required fields, excludes password_hash
    // =========================================================================

    public function testProfileContainsRequiredFieldsAndExcludesPasswordHash(): void
    {
        [$memberStmt, $votesStmt, $attendancesStmt] = $this->buildMockStatementsWithMember();

        $this->pdo->expects($this->exactly(3))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($memberStmt, $votesStmt, $attendancesStmt);

        $service = new RgpdExportService($this->pdo);
        $result  = $service->exportForUser(self::USER_ID, self::TENANT_ID);

        $profile = $result['profile'];
        $this->assertIsArray($profile);
        $this->assertArrayHasKey('id', $profile);
        $this->assertArrayHasKey('full_name', $profile);
        $this->assertArrayHasKey('email', $profile);
        $this->assertArrayHasKey('role', $profile);
        $this->assertArrayHasKey('voting_power', $profile);
        $this->assertArrayHasKey('created_at', $profile);
        $this->assertArrayNotHasKey('password_hash', $profile);
    }

    // =========================================================================
    // Test 3: votes sub-array items contain required fields
    // =========================================================================

    public function testVotesSubArrayItemsContainRequiredFields(): void
    {
        [$memberStmt, $votesStmt, $attendancesStmt] = $this->buildMockStatementsWithMember(
            votes: [
                [
                    'motion_title'  => 'Approbation du budget',
                    'meeting_title' => 'AG 2025',
                    'meeting_date'  => '2025-06-01T09:00:00+00:00',
                    'value'         => 'pour',
                    'weight'        => '1',
                    'cast_at'       => '2025-06-01T10:15:00+00:00',
                ],
            ]
        );

        $this->pdo->expects($this->exactly(3))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($memberStmt, $votesStmt, $attendancesStmt);

        $service = new RgpdExportService($this->pdo);
        $result  = $service->exportForUser(self::USER_ID, self::TENANT_ID);

        $this->assertCount(1, $result['votes']);
        $vote = $result['votes'][0];
        $this->assertArrayHasKey('motion_title', $vote);
        $this->assertArrayHasKey('meeting_title', $vote);
        $this->assertArrayHasKey('meeting_date', $vote);
        $this->assertArrayHasKey('value', $vote);
        $this->assertArrayHasKey('weight', $vote);
        $this->assertArrayHasKey('cast_at', $vote);
        $this->assertSame('Approbation du budget', $vote['motion_title']);
        $this->assertSame('pour', $vote['value']);
    }

    // =========================================================================
    // Test 4: attendances sub-array items contain required fields
    // =========================================================================

    public function testAttendancesSubArrayItemsContainRequiredFields(): void
    {
        [$memberStmt, $votesStmt, $attendancesStmt] = $this->buildMockStatementsWithMember(
            attendances: [
                [
                    'meeting_title' => 'AG 2025',
                    'meeting_date'  => '2025-06-01T09:00:00+00:00',
                    'mode'          => 'physical',
                    'checked_in_at' => '2025-06-01T09:05:00+00:00',
                ],
            ]
        );

        $this->pdo->expects($this->exactly(3))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($memberStmt, $votesStmt, $attendancesStmt);

        $service = new RgpdExportService($this->pdo);
        $result  = $service->exportForUser(self::USER_ID, self::TENANT_ID);

        $this->assertCount(1, $result['attendances']);
        $attendance = $result['attendances'][0];
        $this->assertArrayHasKey('meeting_title', $attendance);
        $this->assertArrayHasKey('meeting_date', $attendance);
        $this->assertArrayHasKey('mode', $attendance);
        $this->assertArrayHasKey('checked_in_at', $attendance);
        $this->assertSame('AG 2025', $attendance['meeting_title']);
        $this->assertSame('physical', $attendance['mode']);
    }

    // =========================================================================
    // Test 5: user with no linked member record returns empty export
    // =========================================================================

    public function testUserWithNoMemberRecordReturnsEmptyExport(): void
    {
        $memberStmt = $this->createMock(\PDOStatement::class);
        $memberStmt->method('execute')->willReturn(true);
        $memberStmt->method('fetch')->willReturn(false); // no member found

        $this->pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($memberStmt);

        $service = new RgpdExportService($this->pdo);
        $result  = $service->exportForUser(self::USER_ID, self::TENANT_ID);

        $this->assertNull($result['profile']);
        $this->assertSame([], $result['votes']);
        $this->assertSame([], $result['attendances']);
        $this->assertArrayHasKey('exported_at', $result);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Build three mock PDOStatement objects simulating:
     * 1. members lookup → returns a member row
     * 2. votes lookup    → returns $votes rows
     * 3. attendances lookup → returns $attendances rows
     *
     * @return array{\PDOStatement&MockObject, \PDOStatement&MockObject, \PDOStatement&MockObject}
     */
    private function buildMockStatementsWithMember(
        array $votes = [],
        array $attendances = [],
    ): array {
        $memberRow = [
            'id'           => self::MEMBER_ID,
            'full_name'    => 'Alice Dupont',
            'email'        => 'alice@example.com',
            'role'         => 'member',
            'voting_power' => 1,
            'created_at'   => '2024-01-15T08:00:00+00:00',
        ];

        $memberStmt = $this->createMock(\PDOStatement::class);
        $memberStmt->method('execute')->willReturn(true);
        $memberStmt->method('fetch')->willReturn($memberRow);

        $votesStmt = $this->createMock(\PDOStatement::class);
        $votesStmt->method('execute')->willReturn(true);
        $votesStmt->method('fetchAll')->willReturn($votes);

        $attendancesStmt = $this->createMock(\PDOStatement::class);
        $attendancesStmt->method('execute')->willReturn(true);
        $attendancesStmt->method('fetchAll')->willReturn($attendances);

        return [$memberStmt, $votesStmt, $attendancesStmt];
    }
}
