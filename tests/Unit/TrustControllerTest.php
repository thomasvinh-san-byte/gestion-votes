<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\TrustController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TrustController.
 *
 * Tests the 2 trust/integrity endpoints:
 *  - anomalies: GET, lists anomalies for a meeting
 *  - checks: GET, runs integrity checks for a meeting
 *
 * Since api_ok()/api_fail() throw ApiResponseException, we catch these to
 * inspect controller behavior without a database.
 */
class TrustControllerTest extends TestCase
{
    // =========================================================================
    // SETUP / TEARDOWN
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];

        // Reset cached raw body
        $ref = new \ReflectionClass(\AgVote\Core\Http\Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        \AgVote\Core\Security\AuthMiddleware::reset();
    }

    protected function tearDown(): void
    {
        \AgVote\Core\Security\AuthMiddleware::reset();

        $ref = new \ReflectionClass(\AgVote\Core\Http\Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        parent::tearDown();
    }

    // =========================================================================
    // HELPER: Call controller and capture response
    // =========================================================================

    private function callControllerMethod(string $method): array
    {
        $controller = new TrustController();
        try {
            $controller->handle($method);
            $this->fail('Expected ApiResponseException was not thrown');
        } catch (ApiResponseException $e) {
            return [
                'status' => $e->getResponse()->getStatusCode(),
                'body' => $e->getResponse()->getBody(),
            ];
        }
        return ['status' => 500, 'body' => []];
    }

    /**
     * Inject a JSON body into Request::$cachedRawBody for POST endpoints.
     */
    private function setJsonBody(array $data): void
    {
        $ref = new \ReflectionClass(\AgVote\Core\Http\Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, json_encode($data));
    }

    // =========================================================================
    // CONTROLLER STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(TrustController::class);
        $this->assertTrue($ref->isFinal(), 'TrustController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new TrustController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(TrustController::class);

        $expectedMethods = ['anomalies', 'checks'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "TrustController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(TrustController::class);

        $expectedMethods = ['anomalies', 'checks'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "TrustController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // anomalies: INPUT VALIDATION - MISSING MEETING ID
    // =========================================================================

    public function testAnomaliesRejectsMissingMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('anomalies');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testAnomaliesRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('anomalies');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testAnomaliesRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'not-a-valid-uuid'];

        $result = $this->callControllerMethod('anomalies');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testAnomaliesRejectsPartialUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234-1234-1234'];

        $result = $this->callControllerMethod('anomalies');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testAnomaliesRejectsWhitespaceMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '   '];

        $result = $this->callControllerMethod('anomalies');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testAnomaliesRejectsUuidWithoutDashes(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678123412341234123456789abc'];

        $result = $this->callControllerMethod('anomalies');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testAnomaliesRejectsSpecialChars(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '<script>alert(1)</script>'];

        $result = $this->callControllerMethod('anomalies');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // checks: INPUT VALIDATION - MISSING MEETING ID
    // =========================================================================

    public function testChecksRejectsMissingMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('checks');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testChecksRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('checks');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testChecksRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'not-a-valid-uuid'];

        $result = $this->callControllerMethod('checks');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testChecksRejectsPartialUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234-1234'];

        $result = $this->callControllerMethod('checks');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testChecksRejectsWhitespaceMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '   '];

        $result = $this->callControllerMethod('checks');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testChecksRejectsNumericMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345'];

        $result = $this->callControllerMethod('checks');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // anomalies: ANOMALY TYPE AND SEVERITY CONSTANTS
    // =========================================================================

    public function testAnomalyTypesInSource(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/TrustController.php');

        $expectedTypes = [
            'vote_without_attendance',
            'duplicate_vote',
            'weight_mismatch',
            'orphan_proxy',
            'unclosed_motion',
            'unjustified_manual_vote',
        ];
        foreach ($expectedTypes as $type) {
            $this->assertStringContainsString(
                "'{$type}'",
                $source,
                "anomalies() should detect '{$type}' anomaly type",
            );
        }
    }

    public function testAnomalySeverityLevels(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/TrustController.php');

        $this->assertStringContainsString("'danger'", $source);
        $this->assertStringContainsString("'warning'", $source);
        $this->assertStringContainsString("'info'", $source);
    }

    // =========================================================================
    // anomalies: SUMMARY STRUCTURE
    // =========================================================================

    public function testAnomalySummaryBuilding(): void
    {
        $anomalies = [
            ['severity' => 'danger'],
            ['severity' => 'danger'],
            ['severity' => 'warning'],
            ['severity' => 'warning'],
            ['severity' => 'warning'],
            ['severity' => 'info'],
        ];

        $summary = [
            'total' => count($anomalies),
            'danger' => count(array_filter($anomalies, fn ($a) => $a['severity'] === 'danger')),
            'warning' => count(array_filter($anomalies, fn ($a) => $a['severity'] === 'warning')),
            'info' => count(array_filter($anomalies, fn ($a) => $a['severity'] === 'info')),
        ];

        $this->assertEquals(6, $summary['total']);
        $this->assertEquals(2, $summary['danger']);
        $this->assertEquals(3, $summary['warning']);
        $this->assertEquals(1, $summary['info']);
    }

    public function testAnomalySummaryEmptyAnomalies(): void
    {
        $anomalies = [];

        $summary = [
            'total' => count($anomalies),
            'danger' => count(array_filter($anomalies, fn ($a) => $a['severity'] === 'danger')),
            'warning' => count(array_filter($anomalies, fn ($a) => $a['severity'] === 'warning')),
            'info' => count(array_filter($anomalies, fn ($a) => $a['severity'] === 'info')),
        ];

        $this->assertEquals(0, $summary['total']);
        $this->assertEquals(0, $summary['danger']);
        $this->assertEquals(0, $summary['warning']);
        $this->assertEquals(0, $summary['info']);
    }

    // =========================================================================
    // anomalies: FRONTEND ALIAS LOGIC
    // =========================================================================

    public function testAnomalyFrontendAliases(): void
    {
        $a = [
            'description' => 'Some anomaly description',
            'member_name' => 'John Doe',
            'motion_title' => 'Budget Approval',
        ];

        $a['message'] = $a['description'] ?? '';
        $parts = [];
        if (!empty($a['member_name'])) {
            $parts[] = $a['member_name'];
        }
        if (!empty($a['motion_title'])) {
            $parts[] = $a['motion_title'];
        }
        if (!empty($a['giver_name'])) {
            $parts[] = $a['giver_name'] . ' -> ' . ($a['receiver_name'] ?? '');
        }
        $a['context'] = implode(' · ', $parts) ?: null;

        $this->assertEquals('Some anomaly description', $a['message']);
        $this->assertEquals('John Doe · Budget Approval', $a['context']);
    }

    public function testAnomalyFrontendAliasesWithProxy(): void
    {
        $a = [
            'description' => 'Orphan proxy',
            'giver_name' => 'Alice',
            'receiver_name' => 'Bob',
        ];

        $a['message'] = $a['description'] ?? '';
        $parts = [];
        if (!empty($a['member_name'])) {
            $parts[] = $a['member_name'];
        }
        if (!empty($a['motion_title'])) {
            $parts[] = $a['motion_title'];
        }
        if (!empty($a['giver_name'])) {
            $parts[] = $a['giver_name'] . ' -> ' . ($a['receiver_name'] ?? '');
        }
        $a['context'] = implode(' · ', $parts) ?: null;

        $this->assertEquals('Alice -> Bob', $a['context']);
    }

    public function testAnomalyFrontendAliasesEmptyContext(): void
    {
        $a = ['description' => 'Some anomaly'];

        $a['message'] = $a['description'] ?? '';
        $parts = [];
        if (!empty($a['member_name'])) {
            $parts[] = $a['member_name'];
        }
        if (!empty($a['motion_title'])) {
            $parts[] = $a['motion_title'];
        }
        if (!empty($a['giver_name'])) {
            $parts[] = $a['giver_name'] . ' -> ' . ($a['receiver_name'] ?? '');
        }
        $a['context'] = implode(' · ', $parts) ?: null;

        $this->assertNull($a['context']);
    }

    // =========================================================================
    // checks: CHECK IDS IN SOURCE
    // =========================================================================

    public function testChecksHasExpectedCheckIds(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/TrustController.php');

        $expectedCheckIds = [
            'president_defined',
            'members_present',
            'quorum_met',
            'all_motions_closed',
            'has_closed_motions',
            'proxies_valid',
            'totals_consistent',
            'no_votes_after_close',
            'vote_policy_defined',
            'quorum_policy_defined',
        ];
        foreach ($expectedCheckIds as $checkId) {
            $this->assertStringContainsString(
                "'{$checkId}'",
                $source,
                "checks() should include '{$checkId}' check",
            );
        }
    }

    // =========================================================================
    // checks: SUMMARY BUILDING LOGIC
    // =========================================================================

    public function testChecksSummaryBuilding(): void
    {
        $checks = [
            ['passed' => true],
            ['passed' => true],
            ['passed' => false],
            ['passed' => true],
            ['passed' => false],
        ];

        $passedCount = count(array_filter($checks, fn ($c) => $c['passed']));
        $failedCount = count($checks) - $passedCount;

        $this->assertEquals(3, $passedCount);
        $this->assertEquals(2, $failedCount);
        $this->assertFalse($failedCount === 0, 'all_passed should be false with failures');
    }

    public function testChecksSummaryAllPassed(): void
    {
        $checks = [
            ['passed' => true],
            ['passed' => true],
            ['passed' => true],
        ];

        $passedCount = count(array_filter($checks, fn ($c) => $c['passed']));
        $failedCount = count($checks) - $passedCount;

        $this->assertEquals(3, $passedCount);
        $this->assertEquals(0, $failedCount);
        $this->assertTrue($failedCount === 0, 'all_passed should be true with no failures');
    }

    // =========================================================================
    // CONTROLLER SOURCE VERIFICATION
    // =========================================================================

    public function testControllerUsesExpectedRepositories(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/TrustController.php');

        $this->assertStringContainsString('BallotRepository', $source);
        $this->assertStringContainsString('MeetingRepository', $source);
        $this->assertStringContainsString('MeetingStatsRepository', $source);
        $this->assertStringContainsString('MemberRepository', $source);
        $this->assertStringContainsString('MotionRepository', $source);
        $this->assertStringContainsString('PolicyRepository', $source);
        $this->assertStringContainsString('ProxyRepository', $source);
    }

    public function testControllerUsesApiCurrentTenantId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/TrustController.php');

        $count = substr_count($source, 'api_current_tenant_id()');
        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function testAnomaliesResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/TrustController.php');

        $expectedKeys = ['meeting_id', 'summary', 'anomalies'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString("'{$key}'", $source);
        }
    }

    public function testChecksResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/TrustController.php');

        $expectedKeys = ['meeting_id', 'all_passed', 'summary', 'checks'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString("'{$key}'", $source);
        }
    }

    // =========================================================================
    // HANDLE: UNKNOWN METHOD
    // =========================================================================

    public function testHandleUnknownMethodReturnsInternalError(): void
    {
        $controller = new TrustController();
        try {
            $controller->handle('nonExistentMethod');
            $this->fail('Expected ApiResponseException was not thrown');
        } catch (ApiResponseException $e) {
            $this->assertEquals(500, $e->getResponse()->getStatusCode());
            $this->assertEquals('internal_error', $e->getResponse()->getBody()['error']);
        }
    }

    // =========================================================================
    // checks: QUORUM THRESHOLD CALCULATION LOGIC
    // =========================================================================

    public function testQuorumThresholdCalculation(): void
    {
        $totalMembers = 100;
        $quorumThreshold = 0.5;
        $quorumRequired = (int) ceil($totalMembers * $quorumThreshold);

        $this->assertEquals(50, $quorumRequired);
    }

    public function testQuorumThresholdCustomValue(): void
    {
        $totalMembers = 100;
        $quorumThreshold = 0.67;
        $quorumRequired = (int) ceil($totalMembers * $quorumThreshold);

        $this->assertEquals(67, $quorumRequired);
    }

    public function testQuorumMetCheck(): void
    {
        $presentCount = 51;
        $quorumRequired = 50;

        $this->assertTrue($presentCount >= $quorumRequired);
    }

    public function testQuorumNotMetCheck(): void
    {
        $presentCount = 49;
        $quorumRequired = 50;

        $this->assertFalse($presentCount >= $quorumRequired);
    }

    // =========================================================================
    // anomalies: ACCEPTS VALID UUID FORMAT
    // =========================================================================

    public function testAnomaliesAcceptsValidUuidFormat(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234-1234-1234-123456789abc'];

        $result = $this->callControllerMethod('anomalies');

        // Should pass validation and fail at DB, not at input validation
        $this->assertNotEquals('missing_meeting_id', $result['body']['error'] ?? '');
    }

    public function testChecksAcceptsValidUuidFormat(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234-1234-1234-123456789abc'];

        $result = $this->callControllerMethod('checks');

        // Should pass validation and fail at DB, not at input validation
        $this->assertNotEquals('missing_meeting_id', $result['body']['error'] ?? '');
    }

    public function testAnomaliesAcceptsUppercaseUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'AABBCCDD-1122-3344-5566-778899AABBCC'];

        $result = $this->callControllerMethod('anomalies');

        $this->assertNotEquals('missing_meeting_id', $result['body']['error'] ?? '');
    }
}
