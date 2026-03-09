<?php

declare(strict_types=1);

namespace Tests\Integration;

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\MotionRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for repository classes.
 *
 * These tests verify that repository SQL queries are syntactically correct
 * and produce expected results against a real PostgreSQL database.
 *
 * Tests are skipped when no database connection is available (CI without PG).
 * To run locally: set DATABASE_URL or PG_DSN environment variable.
 *
 * Example:
 *   DATABASE_URL="pgsql:host=localhost;dbname=agvote_test;user=agvote;password=agvote" \
 *     vendor/bin/phpunit tests/Integration/RepositoryTest.php
 */
class RepositoryTest extends TestCase
{
    private static ?PDO $pdo = null;
    private static bool $schemaLoaded = false;

    private const TENANT_ID = 'aaaaaaaa-1111-2222-3333-444444444444';
    private const MEETING_ID = 'bbbbbbbb-1111-2222-3333-444444444444';
    private const MOTION_ID = 'cccccccc-1111-2222-3333-444444444444';
    private const MEMBER_ID = 'dddddddd-1111-2222-3333-444444444444';

    // =========================================================================
    // SETUP
    // =========================================================================

    public static function setUpBeforeClass(): void
    {
        $dsn = getenv('DATABASE_URL') ?: getenv('PG_DSN') ?: '';
        if ($dsn === '') {
            return; // Tests will be skipped
        }

        try {
            self::$pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // Load schema if not already done
            if (!self::$schemaLoaded) {
                self::loadSchema();
                self::$schemaLoaded = true;
            }
        } catch (\Throwable $e) {
            self::$pdo = null;
        }
    }

    protected function setUp(): void
    {
        if (self::$pdo === null) {
            $this->markTestSkipped('No PostgreSQL connection available (set DATABASE_URL)');
        }

        // Clean up test data before each test
        self::$pdo->prepare("DELETE FROM ballots WHERE tenant_id = ?")->execute([self::TENANT_ID]);
        self::$pdo->prepare("DELETE FROM motions WHERE tenant_id = ?")->execute([self::TENANT_ID]);
        self::$pdo->prepare("DELETE FROM meetings WHERE tenant_id = ?")->execute([self::TENANT_ID]);
        self::$pdo->prepare("DELETE FROM tenants WHERE id = ?")->execute([self::TENANT_ID]);

        // Insert test tenant
        self::$pdo->prepare(
            "INSERT INTO tenants (id, name, slug) VALUES (?, 'Test Tenant', 'test-tenant')
             ON CONFLICT (id) DO NOTHING"
        )->execute([self::TENANT_ID]);
    }

    private static function loadSchema(): void
    {
        $schemaFile = PROJECT_ROOT . '/database/schema.sql';
        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            self::$pdo->exec($sql);
        }
    }

    // =========================================================================
    // MeetingRepository TESTS
    // =========================================================================

    public function testMeetingFindByIdForTenantReturnsNullWhenNotFound(): void
    {
        $repo = new MeetingRepository(self::$pdo);

        $result = $repo->findByIdForTenant('00000000-0000-0000-0000-000000000000', self::TENANT_ID);

        $this->assertNull($result);
    }

    public function testMeetingExistsForTenantReturnsFalseWhenNotFound(): void
    {
        $repo = new MeetingRepository(self::$pdo);

        $result = $repo->existsForTenant('00000000-0000-0000-0000-000000000000', self::TENANT_ID);

        $this->assertFalse($result);
    }

    public function testMeetingCreateAndFind(): void
    {
        $repo = new MeetingRepository(self::$pdo);

        // Insert meeting directly
        self::$pdo->prepare(
            "INSERT INTO meetings (id, tenant_id, title, status, created_at, updated_at)
             VALUES (:id, :tid, :title, 'draft', NOW(), NOW())"
        )->execute([
            ':id' => self::MEETING_ID,
            ':tid' => self::TENANT_ID,
            ':title' => 'Test Meeting',
        ]);

        // Find it
        $meeting = $repo->findByIdForTenant(self::MEETING_ID, self::TENANT_ID);

        $this->assertNotNull($meeting);
        $this->assertEquals(self::MEETING_ID, $meeting['id']);
        $this->assertEquals('Test Meeting', $meeting['title']);
        $this->assertEquals('draft', $meeting['status']);
    }

    public function testMeetingExistsForTenantReturnsTrue(): void
    {
        $repo = new MeetingRepository(self::$pdo);

        self::$pdo->prepare(
            "INSERT INTO meetings (id, tenant_id, title, status, created_at, updated_at)
             VALUES (:id, :tid, 'Test', 'draft', NOW(), NOW())"
        )->execute([':id' => self::MEETING_ID, ':tid' => self::TENANT_ID]);

        $this->assertTrue($repo->existsForTenant(self::MEETING_ID, self::TENANT_ID));
    }

    public function testMeetingTenantIsolation(): void
    {
        $repo = new MeetingRepository(self::$pdo);
        $otherTenant = 'ffffffff-1111-2222-3333-444444444444';

        self::$pdo->prepare(
            "INSERT INTO meetings (id, tenant_id, title, status, created_at, updated_at)
             VALUES (:id, :tid, 'Test', 'draft', NOW(), NOW())"
        )->execute([':id' => self::MEETING_ID, ':tid' => self::TENANT_ID]);

        // Same meeting ID with different tenant should return null
        $result = $repo->findByIdForTenant(self::MEETING_ID, $otherTenant);
        $this->assertNull($result, 'Meeting should not be visible to another tenant');
    }

    public function testMeetingListByTenant(): void
    {
        $repo = new MeetingRepository(self::$pdo);

        // Insert 2 meetings
        for ($i = 1; $i <= 2; $i++) {
            $id = str_replace('4444', str_pad((string) $i, 4, '0', STR_PAD_LEFT), self::MEETING_ID);
            self::$pdo->prepare(
                "INSERT INTO meetings (id, tenant_id, title, status, created_at, updated_at)
                 VALUES (:id, :tid, :title, 'draft', NOW(), NOW())"
            )->execute([':id' => $id, ':tid' => self::TENANT_ID, ':title' => "Meeting {$i}"]);
        }

        $meetings = $repo->listByTenant(self::TENANT_ID);
        $this->assertGreaterThanOrEqual(2, count($meetings));
    }

    public function testMeetingCountForTenant(): void
    {
        $repo = new MeetingRepository(self::$pdo);

        $initialCount = $repo->countForTenant(self::TENANT_ID);

        self::$pdo->prepare(
            "INSERT INTO meetings (id, tenant_id, title, status, created_at, updated_at)
             VALUES (:id, :tid, 'Test', 'draft', NOW(), NOW())"
        )->execute([':id' => self::MEETING_ID, ':tid' => self::TENANT_ID]);

        $newCount = $repo->countForTenant(self::TENANT_ID);
        $this->assertEquals($initialCount + 1, $newCount);
    }

    public function testMeetingIsValidatedReturnsFalseForDraft(): void
    {
        $repo = new MeetingRepository(self::$pdo);

        self::$pdo->prepare(
            "INSERT INTO meetings (id, tenant_id, title, status, created_at, updated_at)
             VALUES (:id, :tid, 'Test', 'draft', NOW(), NOW())"
        )->execute([':id' => self::MEETING_ID, ':tid' => self::TENANT_ID]);

        $this->assertFalse($repo->isValidated(self::MEETING_ID, self::TENANT_ID));
    }

    // =========================================================================
    // BallotRepository TESTS
    // =========================================================================

    public function testBallotTallyReturnsZerosForEmptyMotion(): void
    {
        $repo = new BallotRepository(self::$pdo);

        $tally = $repo->tally('00000000-0000-0000-0000-000000000000', self::TENANT_ID);

        $this->assertEquals(0, $tally['total_ballots']);
        $this->assertEquals(0, $tally['count_for']);
        $this->assertEquals(0, $tally['count_against']);
    }

    public function testBallotCountForMotionReturnsZero(): void
    {
        $repo = new BallotRepository(self::$pdo);

        $count = $repo->countForMotion(self::TENANT_ID, self::MEETING_ID, self::MOTION_ID);

        $this->assertEquals(0, $count);
    }

    public function testBallotListForMotionReturnsEmpty(): void
    {
        $repo = new BallotRepository(self::$pdo);

        $ballots = $repo->listForMotion('00000000-0000-0000-0000-000000000000', self::TENANT_ID);

        $this->assertIsArray($ballots);
        $this->assertEmpty($ballots);
    }

    // =========================================================================
    // MotionRepository TESTS
    // =========================================================================

    public function testMotionListForMeetingReturnsEmpty(): void
    {
        $repo = new MotionRepository(self::$pdo);

        // Create meeting first
        self::$pdo->prepare(
            "INSERT INTO meetings (id, tenant_id, title, status, created_at, updated_at)
             VALUES (:id, :tid, 'Test', 'draft', NOW(), NOW())"
        )->execute([':id' => self::MEETING_ID, ':tid' => self::TENANT_ID]);

        $motions = $repo->listForMeeting(self::MEETING_ID, self::TENANT_ID);

        $this->assertIsArray($motions);
        $this->assertEmpty($motions);
    }

    // =========================================================================
    // AbstractRepository TESTS
    // =========================================================================

    public function testGenerateUuidReturnsValidFormat(): void
    {
        $repo = new MeetingRepository(self::$pdo);

        $uuid = $repo->generateUuid();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $uuid
        );
    }

    public function testGenerateUuidReturnsUniqueValues(): void
    {
        $repo = new MeetingRepository(self::$pdo);

        $uuids = [];
        for ($i = 0; $i < 10; $i++) {
            $uuids[] = $repo->generateUuid();
        }

        $this->assertCount(10, array_unique($uuids), 'All generated UUIDs should be unique');
    }
}
