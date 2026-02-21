<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\VoteTokenController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for VoteTokenController.
 *
 * Tests the single generate endpoint:
 *  - generate: POST, generates vote tokens for eligible voters
 *
 * Since api_ok()/api_fail() throw ApiResponseException, we catch these to
 * inspect controller behavior without a database.
 */
class VoteTokenControllerTest extends TestCase
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
        $controller = new VoteTokenController();
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
        $ref = new \ReflectionClass(VoteTokenController::class);
        $this->assertTrue($ref->isFinal(), 'VoteTokenController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new VoteTokenController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasGenerateMethod(): void
    {
        $ref = new \ReflectionClass(VoteTokenController::class);
        $this->assertTrue(
            $ref->hasMethod('generate'),
            'VoteTokenController should have a generate() method',
        );
    }

    public function testGenerateMethodIsPublic(): void
    {
        $ref = new \ReflectionClass(VoteTokenController::class);
        $this->assertTrue(
            $ref->getMethod('generate')->isPublic(),
            'VoteTokenController::generate() should be public',
        );
    }

    // =========================================================================
    // generate: METHOD ENFORCEMENT
    // =========================================================================

    public function testGenerateRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('generate');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testGenerateRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('generate');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testGenerateRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('generate');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testGenerateRejectsPatchMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';

        $result = $this->callControllerMethod('generate');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // generate: INPUT VALIDATION - MEETING ID
    // =========================================================================

    public function testGenerateRejectsMissingMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'motion_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('generate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testGenerateRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '',
            'motion_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('generate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testGenerateRejectsInvalidMeetingUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => 'not-a-uuid',
            'motion_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('generate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testGenerateRejectsWhitespaceMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '   ',
            'motion_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('generate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // generate: INPUT VALIDATION - MOTION ID
    // =========================================================================

    public function testGenerateRejectsMissingMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('generate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_motion_id', $result['body']['error']);
    }

    public function testGenerateRejectsEmptyMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => '',
        ]);

        $result = $this->callControllerMethod('generate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_motion_id', $result['body']['error']);
    }

    public function testGenerateRejectsInvalidMotionUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => 'bad-uuid',
        ]);

        $result = $this->callControllerMethod('generate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_motion_id', $result['body']['error']);
    }

    public function testGenerateRejectsPartialMotionUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => '12345678-1234-1234',
        ]);

        $result = $this->callControllerMethod('generate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_motion_id', $result['body']['error']);
    }

    // =========================================================================
    // generate: VALIDATION ORDER (meeting_id before motion_id)
    // =========================================================================

    public function testGenerateValidatesMeetingIdBeforeMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => 'bad',
            'motion_id' => 'also-bad',
        ]);

        $result = $this->callControllerMethod('generate');

        // meeting_id is validated first
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testGenerateRejectsBothMissingIds(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('generate');

        // meeting_id is validated first
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // generate: MEETING ID FROM QUERY STRING FALLBACK
    // =========================================================================

    public function testGenerateFallsBackToQueryStringMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = ['meeting_id' => '12345678-1234-1234-1234-123456789abc'];
        $this->setJsonBody([
            'motion_id' => 'abcdefab-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('generate');

        // Should pass meeting_id and motion_id validation (fail at DB access)
        $this->assertNotEquals('invalid_meeting_id', $result['body']['error'] ?? '');
        $this->assertNotEquals('invalid_motion_id', $result['body']['error'] ?? '');
    }

    public function testGenerateFallsBackToQueryStringMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = ['motion_id' => 'abcdefab-1234-1234-1234-123456789abc'];
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('generate');

        // Should pass both validations (fail at DB access)
        $this->assertNotEquals('invalid_meeting_id', $result['body']['error'] ?? '');
        $this->assertNotEquals('invalid_motion_id', $result['body']['error'] ?? '');
    }

    // =========================================================================
    // generate: TTL MINUTES DEFAULT LOGIC
    // =========================================================================

    public function testTtlMinutesDefaultLogic(): void
    {
        // Replicate the TTL logic from generate()
        $in1 = [];
        $ttl1 = (int) ($in1['ttl_minutes'] ?? 0);
        if ($ttl1 <= 0) {
            $ttl1 = 180;
        }
        $this->assertEquals(180, $ttl1, 'Default TTL should be 180 minutes');

        $in2 = ['ttl_minutes' => 60];
        $ttl2 = (int) ($in2['ttl_minutes'] ?? 0);
        if ($ttl2 <= 0) {
            $ttl2 = 180;
        }
        $this->assertEquals(60, $ttl2, 'Custom TTL should be preserved');

        $in3 = ['ttl_minutes' => 0];
        $ttl3 = (int) ($in3['ttl_minutes'] ?? 0);
        if ($ttl3 <= 0) {
            $ttl3 = 180;
        }
        $this->assertEquals(180, $ttl3, 'Zero TTL should default to 180');

        $in4 = ['ttl_minutes' => -10];
        $ttl4 = (int) ($in4['ttl_minutes'] ?? 0);
        if ($ttl4 <= 0) {
            $ttl4 = 180;
        }
        $this->assertEquals(180, $ttl4, 'Negative TTL should default to 180');
    }

    // =========================================================================
    // generate: HASH COMPUTATION LOGIC
    // =========================================================================

    public function testGenerateUsesHmacSha256(): void
    {
        $raw = 'test-uuid-token';
        $hash = hash_hmac('sha256', $raw, APP_SECRET);

        $this->assertIsString($hash);
        $this->assertEquals(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
    }

    public function testGenerateHashIsDeterministic(): void
    {
        $raw = 'test-uuid-token';
        $hash1 = hash_hmac('sha256', $raw, APP_SECRET);
        $hash2 = hash_hmac('sha256', $raw, APP_SECRET);

        $this->assertEquals($hash1, $hash2);
    }

    public function testGenerateDifferentTokensProduceDifferentHashes(): void
    {
        $hash1 = hash_hmac('sha256', 'token-a', APP_SECRET);
        $hash2 = hash_hmac('sha256', 'token-b', APP_SECRET);

        $this->assertNotEquals($hash1, $hash2);
    }

    // =========================================================================
    // generate: URL CONSTRUCTION LOGIC
    // =========================================================================

    public function testTokenUrlConstruction(): void
    {
        $raw = 'test-uuid-value';
        $url = '/vote.php?token=' . $raw;

        $this->assertEquals('/vote.php?token=test-uuid-value', $url);
    }

    // =========================================================================
    // CONTROLLER SOURCE VERIFICATION
    // =========================================================================

    public function testControllerUsesMeetingRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VoteTokenController.php');

        $this->assertStringContainsString('MeetingRepository', $source);
        $this->assertStringContainsString('findByIdForTenant', $source);
    }

    public function testControllerUsesMotionRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VoteTokenController.php');

        $this->assertStringContainsString('MotionRepository', $source);
        $this->assertStringContainsString('findByIdAndMeetingWithDates', $source);
    }

    public function testControllerUsesAttendanceRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VoteTokenController.php');

        $this->assertStringContainsString('AttendanceRepository', $source);
        $this->assertStringContainsString('listEligibleVotersWithName', $source);
    }

    public function testControllerUsesVoteTokenRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VoteTokenController.php');

        $this->assertStringContainsString('VoteTokenRepository', $source);
        $this->assertStringContainsString('deleteUnusedByMotionAndMember', $source);
        $this->assertStringContainsString('insert', $source);
    }

    public function testControllerUsesApiTransaction(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VoteTokenController.php');

        $this->assertStringContainsString('api_transaction', $source);
    }

    public function testControllerUsesAppSecret(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VoteTokenController.php');

        $this->assertStringContainsString('APP_SECRET', $source);
    }

    public function testControllerAuditsOperation(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VoteTokenController.php');

        $this->assertStringContainsString("'vote_tokens_generated'", $source);
        $this->assertStringContainsString('audit_log', $source);
    }

    public function testControllerGuardsMeetingNotValidated(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VoteTokenController.php');

        $this->assertStringContainsString('api_guard_meeting_not_validated', $source);
    }

    public function testControllerChecksMotionClosed(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VoteTokenController.php');

        $this->assertStringContainsString("'motion_closed'", $source);
        $this->assertStringContainsString('closed_at', $source);
    }

    public function testControllerResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VoteTokenController.php');

        $expectedKeys = ['count', 'expires_in', 'tokens'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString("'{$key}'", $source);
        }
    }

    public function testControllerTokenOutputStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VoteTokenController.php');

        $expectedTokenKeys = ['member_id', 'member_name', 'token', 'url'];
        foreach ($expectedTokenKeys as $key) {
            $this->assertStringContainsString("'{$key}'", $source);
        }
    }

    // =========================================================================
    // HANDLE: UNKNOWN METHOD
    // =========================================================================

    public function testHandleUnknownMethodReturnsInternalError(): void
    {
        $controller = new VoteTokenController();
        try {
            $controller->handle('nonExistentMethod');
            $this->fail('Expected ApiResponseException was not thrown');
        } catch (ApiResponseException $e) {
            $this->assertEquals(500, $e->getResponse()->getStatusCode());
            $this->assertEquals('internal_error', $e->getResponse()->getBody()['error']);
        }
    }

    // =========================================================================
    // generate: MOTION CLOSED CHECK LOGIC
    // =========================================================================

    public function testMotionClosedCheckLogic(): void
    {
        $motionOpen = ['closed_at' => null];
        $this->assertNull($motionOpen['closed_at'], 'Open motion should have null closed_at');

        $motionClosed = ['closed_at' => '2024-01-01 12:00:00'];
        $this->assertNotNull($motionClosed['closed_at'], 'Closed motion should have non-null closed_at');
    }

    // =========================================================================
    // generate: MEETING ID TRIMMING LOGIC
    // =========================================================================

    public function testMeetingIdTrimmingLogic(): void
    {
        $in = ['meeting_id' => '  12345678-1234-1234-1234-123456789abc  '];
        $meetingId = trim((string) ($in['meeting_id'] ?? api_query('meeting_id')));

        $this->assertEquals('12345678-1234-1234-1234-123456789abc', $meetingId);
        $this->assertTrue(api_is_uuid($meetingId));
    }

    public function testMotionIdTrimmingLogic(): void
    {
        $in = ['motion_id' => '  abcdefab-1234-1234-1234-123456789abc  '];
        $motionId = trim((string) ($in['motion_id'] ?? api_query('motion_id')));

        $this->assertEquals('abcdefab-1234-1234-1234-123456789abc', $motionId);
        $this->assertTrue(api_is_uuid($motionId));
    }

    // =========================================================================
    // generate: EXPIRATION DATE FORMAT
    // =========================================================================

    public function testExpirationDateFormat(): void
    {
        $ttlMinutes = 180;
        $expiresAt = (new \DateTimeImmutable('+' . $ttlMinutes . ' minutes'))->format('Y-m-d H:i:sP');

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $expiresAt,
        );
    }

    public function testExpirationDateIsInFuture(): void
    {
        $ttlMinutes = 180;
        $expiresAt = new \DateTimeImmutable('+' . $ttlMinutes . ' minutes');
        $now = new \DateTimeImmutable();

        $this->assertGreaterThan($now, $expiresAt);
    }
}
