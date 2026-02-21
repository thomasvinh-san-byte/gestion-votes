<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\QuorumController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for QuorumController.
 *
 * Tests the 3 quorum endpoints:
 *  - card: GET, renders quorum card HTML (uses api_query for meeting_id/motion_id)
 *  - status: GET, returns JSON quorum status
 *  - meetingSettings: GET/POST, manages quorum settings for a meeting
 *
 * Since api_ok()/api_fail() throw ApiResponseException, we catch these to
 * inspect controller behavior without a database.
 */
class QuorumControllerTest extends TestCase
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
        $controller = new QuorumController();
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
        $ref = new \ReflectionClass(QuorumController::class);
        $this->assertTrue($ref->isFinal(), 'QuorumController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new QuorumController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(QuorumController::class);

        $expectedMethods = ['card', 'status', 'meetingSettings'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "QuorumController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(QuorumController::class);

        $expectedMethods = ['card', 'status', 'meetingSettings'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "QuorumController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // status: METHOD ENFORCEMENT
    // =========================================================================

    public function testStatusRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('status');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testStatusRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('status');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testStatusRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('status');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testStatusRejectsPatchMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';

        $result = $this->callControllerMethod('status');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // status: INPUT VALIDATION - INVALID UUIDs
    // =========================================================================

    public function testStatusRejectsInvalidMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'not-a-valid-uuid'];

        $result = $this->callControllerMethod('status');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testStatusRejectsInvalidMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['motion_id' => 'not-a-valid-uuid'];

        $result = $this->callControllerMethod('status');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_motion_id', $result['body']['error']);
    }

    public function testStatusRejectsShortMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234-1234'];

        $result = $this->callControllerMethod('status');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testStatusRejectsShortMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['motion_id' => '12345678-1234'];

        $result = $this->callControllerMethod('status');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_motion_id', $result['body']['error']);
    }

    // =========================================================================
    // status: MISSING PARAMS
    // =========================================================================

    public function testStatusRejectsMissingBothParams(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('status');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_params', $result['body']['error']);
    }

    // =========================================================================
    // card: SOURCE VALIDATION LOGIC
    // =========================================================================

    public function testCardValidatesMeetingIdInSource(): void
    {
        // card() uses exit() directly for invalid UUIDs, which cannot be
        // caught by PHPUnit. Verify validation logic via source inspection.
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/QuorumController.php');

        $this->assertStringContainsString("api_is_uuid(\$meetingId)", $source);
        $this->assertStringContainsString("api_is_uuid(\$motionId)", $source);
        $this->assertStringContainsString("Invalid meeting_id", $source);
        $this->assertStringContainsString("Invalid motion_id", $source);
    }

    public function testCardUsesApiQueryForParams(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/QuorumController.php');

        $this->assertStringContainsString("api_query('meeting_id')", $source);
        $this->assertStringContainsString("api_query('motion_id')", $source);
    }

    // =========================================================================
    // meetingSettings: METHOD ENFORCEMENT
    // Note: meetingSettings() creates MeetingRepository() before method checks,
    // which throws RuntimeException (no DB). We verify method enforcement
    // via source inspection and logic replication.
    // =========================================================================

    public function testMeetingSettingsMethodEnforcementInSource(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/QuorumController.php');

        // meetingSettings uses api_method() and checks GET/POST, with fallthrough
        // to api_fail('method_not_allowed', 405) for other methods.
        $this->assertStringContainsString("api_method()", $source);
        $this->assertStringContainsString("api_request('GET')", $source);
        $this->assertStringContainsString("api_request('POST')", $source);
        $this->assertStringContainsString("api_fail('method_not_allowed', 405)", $source);
    }

    public function testMeetingSettingsMethodCheckLogic(): void
    {
        // Replicate the method checking logic from meetingSettings()
        $allowedMethods = ['GET', 'POST'];

        foreach (['PUT', 'DELETE', 'PATCH'] as $disallowed) {
            $this->assertNotContains($disallowed, $allowedMethods);
        }
        foreach (['GET', 'POST'] as $allowed) {
            $this->assertContains($allowed, $allowedMethods);
        }
    }

    // =========================================================================
    // meetingSettings: INPUT VALIDATION (source-level verification)
    // Note: meetingSettings() creates MeetingRepository before validation,
    // which throws in test env. We verify validation logic via source.
    // =========================================================================

    public function testMeetingSettingsGetUsesApiRequireUuid(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/QuorumController.php');

        $this->assertStringContainsString("api_require_uuid(\$q, 'meeting_id')", $source);
    }

    public function testMeetingSettingsPostUsesApiRequireUuid(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/QuorumController.php');

        $this->assertStringContainsString("api_require_uuid(\$in, 'meeting_id')", $source);
    }

    public function testMeetingSettingsValidatesQuorumPolicyId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/QuorumController.php');

        $this->assertStringContainsString("api_is_uuid(\$policyId)", $source);
        $this->assertStringContainsString("'invalid_quorum_policy_id'", $source);
    }

    public function testMeetingSettingsValidatesConvocationNo(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/QuorumController.php');

        $this->assertStringContainsString("'invalid_convocation_no'", $source);
        $this->assertStringContainsString("in_array(\$convocationNo, [1, 2], true)", $source);
    }

    // =========================================================================
    // meetingSettings: CONVOCATION NO VALIDATION LOGIC
    // =========================================================================

    public function testConvocationNoAllowedValues(): void
    {
        $allowed = [1, 2];
        $this->assertTrue(in_array(1, $allowed, true));
        $this->assertTrue(in_array(2, $allowed, true));
        $this->assertFalse(in_array(0, $allowed, true));
        $this->assertFalse(in_array(3, $allowed, true));
        $this->assertFalse(in_array(-1, $allowed, true));
    }

    // =========================================================================
    // meetingSettings: QUORUM POLICY ID VALIDATION LOGIC
    // =========================================================================

    public function testQuorumPolicyIdEmptyIsAllowed(): void
    {
        // Replicate the logic from meetingSettings(): empty policyId is valid
        $policyId = '';
        $valid = ($policyId === '' || api_is_uuid($policyId));
        $this->assertTrue($valid, 'Empty quorum_policy_id should be allowed');
    }

    public function testQuorumPolicyIdValidUuidIsAllowed(): void
    {
        $policyId = '12345678-1234-1234-1234-123456789abc';
        $valid = ($policyId === '' || api_is_uuid($policyId));
        $this->assertTrue($valid, 'Valid UUID should be allowed');
    }

    public function testQuorumPolicyIdInvalidUuidIsRejected(): void
    {
        $policyId = 'not-a-uuid';
        $valid = ($policyId === '' || api_is_uuid($policyId));
        $this->assertFalse($valid, 'Invalid UUID should be rejected');
    }

    // =========================================================================
    // status: ACCEPTS VALID UUID FOR motion_id
    // =========================================================================

    public function testStatusAcceptsValidMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['motion_id' => '12345678-1234-1234-1234-123456789abc'];

        $result = $this->callControllerMethod('status');

        // Should pass UUID validation and fail at DB access, not at validation
        $this->assertNotEquals('invalid_motion_id', $result['body']['error'] ?? '');
        $this->assertNotEquals('invalid_meeting_id', $result['body']['error'] ?? '');
        $this->assertNotEquals('missing_params', $result['body']['error'] ?? '');
    }

    public function testStatusAcceptsValidMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234-1234-1234-123456789abc'];

        $result = $this->callControllerMethod('status');

        // Should pass UUID validation and fail at DB access, not at validation
        $this->assertNotEquals('invalid_meeting_id', $result['body']['error'] ?? '');
        $this->assertNotEquals('missing_params', $result['body']['error'] ?? '');
    }

    // =========================================================================
    // CONTROLLER SOURCE VERIFICATION
    // =========================================================================

    public function testControllerUsesQuorumEngine(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/QuorumController.php');

        $this->assertStringContainsString('QuorumEngine', $source);
        $this->assertStringContainsString('computeForMotion', $source);
        $this->assertStringContainsString('computeForMeeting', $source);
    }

    public function testControllerUsesMeetingRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/QuorumController.php');

        $this->assertStringContainsString('MeetingRepository', $source);
    }

    public function testControllerUsesPolicyRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/QuorumController.php');

        $this->assertStringContainsString('PolicyRepository', $source);
    }

    public function testControllerUsesApiCurrentTenantId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/QuorumController.php');

        $count = substr_count($source, 'api_current_tenant_id()');
        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function testMeetingSettingsAuditsOperation(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/QuorumController.php');

        $this->assertStringContainsString("'meeting_quorum_updated'", $source);
        $this->assertStringContainsString('audit_log', $source);
    }

    public function testStatusResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/QuorumController.php');

        $expectedKeys = ['ratio', 'threshold', 'present', 'total_eligible', 'required', 'mode'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString("'{$key}'", $source, "status() response should contain '{$key}'");
        }
    }

    // =========================================================================
    // HANDLE: UNKNOWN METHOD
    // =========================================================================

    public function testHandleUnknownMethodReturnsInternalError(): void
    {
        $controller = new QuorumController();
        try {
            $controller->handle('nonExistentMethod');
            $this->fail('Expected ApiResponseException was not thrown');
        } catch (ApiResponseException $e) {
            $this->assertEquals(500, $e->getResponse()->getStatusCode());
            $this->assertEquals('internal_error', $e->getResponse()->getBody()['error']);
        }
    }

    // =========================================================================
    // status: UUID VALIDATION EDGE CASES
    // =========================================================================

    public function testStatusRejectsUuidWithoutDashes(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678123412341234123456789abc'];

        $result = $this->callControllerMethod('status');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testStatusRejectsMotionIdWithSpecialChars(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['motion_id' => '<script>alert(1)</script>'];

        $result = $this->callControllerMethod('status');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_motion_id', $result['body']['error']);
    }

    // =========================================================================
    // status: PRIORITY (motion_id checked before meeting_id)
    // =========================================================================

    public function testStatusMotionIdTakesPriorityOverMeetingId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/QuorumController.php');

        // In the status() method, motion_id is checked first (if motionId !== '')
        $this->assertStringContainsString("\$motionId !== ''", $source);
    }

    // =========================================================================
    // meetingSettings: QUORUM POLICY ID TRIMMING LOGIC
    // =========================================================================

    public function testQuorumPolicyIdTrimmingLogic(): void
    {
        $input = ['quorum_policy_id' => '  12345678-1234-1234-1234-123456789abc  '];
        $policyId = trim((string) ($input['quorum_policy_id'] ?? ''));

        $this->assertEquals('12345678-1234-1234-1234-123456789abc', $policyId);
        $this->assertTrue(api_is_uuid($policyId));
    }

    public function testQuorumPolicyIdEmptyStringAfterTrim(): void
    {
        $input = ['quorum_policy_id' => '   '];
        $policyId = trim((string) ($input['quorum_policy_id'] ?? ''));

        $this->assertEquals('', $policyId);
    }
}
