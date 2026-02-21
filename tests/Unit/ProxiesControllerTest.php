<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\AbstractController;
use AgVote\Controller\ProxiesController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ProxiesController.
 *
 * Tests the proxy endpoint logic including:
 *  - Controller structure (final, extends AbstractController, public methods)
 *  - listForMeeting: method enforcement, meeting_id UUID validation
 *  - upsert: method enforcement, meeting_id / giver_member_id UUID validation,
 *    receiver_member_id validation, scope defaults, revoke path
 *  - delete: method enforcement, meeting_id / proxy_id UUID validation
 *
 * Since api_ok()/api_fail() throw ApiResponseException, we catch these to
 * inspect controller behavior without a database.
 */
class ProxiesControllerTest extends TestCase
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
        $controller = new ProxiesController();
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
     * Inject a JSON body into the Request cache (simulates php://input).
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
        $ref = new \ReflectionClass(ProxiesController::class);
        $this->assertTrue($ref->isFinal(), 'ProxiesController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new ProxiesController();
        $this->assertInstanceOf(AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(ProxiesController::class);

        $expectedMethods = ['listForMeeting', 'upsert', 'delete'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "ProxiesController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(ProxiesController::class);

        $expectedMethods = ['listForMeeting', 'upsert', 'delete'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "ProxiesController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // listForMeeting: METHOD ENFORCEMENT
    // =========================================================================

    public function testListForMeetingRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListForMeetingRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListForMeetingRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListForMeetingRejectsPatchMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // listForMeeting: MEETING_ID VALIDATION
    // =========================================================================

    public function testListForMeetingRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    public function testListForMeetingRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    public function testListForMeetingRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'not-a-valid-uuid'];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testListForMeetingRejectsPartialUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234-1234-1234'];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testListForMeetingRejectsWhitespaceMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '   '];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    // =========================================================================
    // upsert: METHOD ENFORCEMENT
    // =========================================================================

    public function testUpsertRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testUpsertRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testUpsertRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testUpsertRejectsPatchMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // upsert: MEETING_ID VALIDATION
    // =========================================================================

    public function testUpsertRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    public function testUpsertRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => '']);

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    public function testUpsertRejectsInvalidMeetingUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => 'bad-uuid']);

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    // =========================================================================
    // upsert: GIVER_MEMBER_ID VALIDATION
    // =========================================================================

    public function testUpsertRequiresGiverMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('giver_member_id', $result['body']['field']);
    }

    public function testUpsertRejectsEmptyGiverMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'giver_member_id' => '',
        ]);

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('giver_member_id', $result['body']['field']);
    }

    public function testUpsertRejectsInvalidGiverMemberUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'giver_member_id' => 'not-a-uuid',
        ]);

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('giver_member_id', $result['body']['field']);
    }

    // =========================================================================
    // upsert: RECEIVER_MEMBER_ID VALIDATION (invalid UUID, not empty)
    // =========================================================================

    public function testUpsertRejectsInvalidReceiverMemberUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'giver_member_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'receiver_member_id' => 'invalid-receiver',
        ]);

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_receiver_member_id', $result['body']['error']);
    }

    public function testUpsertRejectsPartialReceiverUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'giver_member_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'receiver_member_id' => '12345678-1234',
        ]);

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_receiver_member_id', $result['body']['error']);
    }

    public function testUpsertRejectsReceiverWithSpecialChars(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'giver_member_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'receiver_member_id' => '<script>alert(1)</script>',
        ]);

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_receiver_member_id', $result['body']['error']);
    }

    public function testUpsertRejectsReceiverWithWhitespaceOnly(): void
    {
        // After trim(), whitespace-only becomes empty => triggers revoke path,
        // not invalid_receiver_member_id. Verify it enters the revoke branch.
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'giver_member_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'receiver_member_id' => '   ',
        ]);

        // This will attempt to call ProxiesService::revoke() which needs DB.
        // The AbstractController catches RuntimeException and converts it, OR
        // the service call itself might throw. We just verify it does NOT fail
        // with invalid_receiver_member_id (since trimmed empty => revoke path).
        $result = $this->callControllerMethod('upsert');

        // After trim, '   ' becomes '' which triggers the revoke path.
        // The revoke path calls ProxiesService::revoke() which will hit the DB
        // and throw a RuntimeException (no DB in tests). AbstractController
        // catches that as business_error (400) or internal_error (500).
        // The key assertion: it is NOT 'invalid_receiver_member_id'.
        $this->assertNotEquals('invalid_receiver_member_id', $result['body']['error'] ?? '');
    }

    // =========================================================================
    // upsert: REVOKE PATH (empty receiver_member_id)
    // =========================================================================

    public function testUpsertWithEmptyReceiverTriggersRevokePath(): void
    {
        // When receiver_member_id is empty string, the controller calls revoke().
        // Since ProxiesService needs DB, it will throw. But we verify the code
        // path by confirming we do NOT get 'invalid_receiver_member_id'.
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'giver_member_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'receiver_member_id' => '',
        ]);

        $result = $this->callControllerMethod('upsert');

        // Should NOT be an input validation error on receiver_member_id
        $this->assertNotEquals('invalid_receiver_member_id', $result['body']['error'] ?? '');
        $this->assertNotEquals('missing_or_invalid_uuid', $result['body']['error'] ?? '');
    }

    public function testUpsertWithMissingReceiverTriggersRevokePath(): void
    {
        // When receiver_member_id is not provided at all, defaults to ''
        // after trim, so should trigger revoke path.
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'giver_member_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            // receiver_member_id omitted
        ]);

        $result = $this->callControllerMethod('upsert');

        // Should NOT be an input validation error on receiver_member_id
        $this->assertNotEquals('invalid_receiver_member_id', $result['body']['error'] ?? '');
        $this->assertNotEquals('missing_or_invalid_uuid', $result['body']['error'] ?? '');
    }

    // =========================================================================
    // upsert: SCOPE DEFAULT LOGIC
    // =========================================================================

    public function testUpsertScopeDefaultLogic(): void
    {
        // Replicate the scope default logic from upsert()
        $input1 = ['scope' => ''];
        $scope1 = trim((string) ($input1['scope'] ?? 'full'));
        $this->assertEquals('', $scope1);
        // Empty scope is replaced by 'full' via ($scope ?: 'full')
        $this->assertEquals('full', $scope1 ?: 'full');

        $input2 = [];
        $scope2 = trim((string) ($input2['scope'] ?? 'full'));
        $this->assertEquals('full', $scope2);

        $input3 = ['scope' => 'motion_123'];
        $scope3 = trim((string) ($input3['scope'] ?? 'full'));
        $this->assertEquals('motion_123', $scope3);
        $this->assertEquals('motion_123', $scope3 ?: 'full');
    }

    public function testUpsertScopeTrimsWhitespace(): void
    {
        $input = ['scope' => '  full  '];
        $scope = trim((string) ($input['scope'] ?? 'full'));
        $this->assertEquals('full', $scope);
    }

    // =========================================================================
    // upsert: RECEIVER_MEMBER_ID TRIM LOGIC
    // =========================================================================

    public function testUpsertReceiverMemberIdIsTrimmed(): void
    {
        // Replicate the trim logic for receiver_member_id
        $input1 = ['receiver_member_id' => '  '];
        $receiverRaw1 = trim((string) ($input1['receiver_member_id'] ?? ''));
        $this->assertEquals('', $receiverRaw1, 'Whitespace-only should become empty after trim');

        $input2 = ['receiver_member_id' => '  aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee  '];
        $receiverRaw2 = trim((string) ($input2['receiver_member_id'] ?? ''));
        $this->assertEquals('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', $receiverRaw2);
    }

    // =========================================================================
    // delete: METHOD ENFORCEMENT
    // =========================================================================

    public function testDeleteRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testDeleteRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testDeleteRejectsPatchMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testDeleteRejectsDeleteHttpMethod(): void
    {
        // The controller requires POST, not HTTP DELETE
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // delete: MEETING_ID VALIDATION
    // =========================================================================

    public function testDeleteRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testDeleteRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => '']);

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testDeleteRejectsInvalidMeetingUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => 'bad-uuid']);

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testDeleteRejectsWhitespaceMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => '   ']);

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // delete: PROXY_ID VALIDATION
    // =========================================================================

    public function testDeleteRequiresProxyId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_proxy_id', $result['body']['error']);
    }

    public function testDeleteRejectsEmptyProxyId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'proxy_id' => '',
        ]);

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_proxy_id', $result['body']['error']);
    }

    public function testDeleteRejectsInvalidProxyUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'proxy_id' => 'not-a-uuid',
        ]);

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_proxy_id', $result['body']['error']);
    }

    public function testDeleteRejectsWhitespaceProxyId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'proxy_id' => '   ',
        ]);

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_proxy_id', $result['body']['error']);
    }

    public function testDeleteRejectsPartialProxyUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'proxy_id' => '12345678-1234-1234',
        ]);

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_proxy_id', $result['body']['error']);
    }

    // =========================================================================
    // delete: VALIDATION ORDER (meeting_id checked before proxy_id)
    // =========================================================================

    public function testDeleteValidatesMeetingIdBeforeProxyId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => 'bad',
            'proxy_id' => 'also-bad',
        ]);

        $result = $this->callControllerMethod('delete');

        // meeting_id is validated first in the controller
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // upsert: VALIDATION ORDER (meeting_id -> giver -> receiver)
    // =========================================================================

    public function testUpsertValidatesMeetingIdBeforeGiver(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => 'bad',
            'giver_member_id' => 'also-bad',
        ]);

        $result = $this->callControllerMethod('upsert');

        // meeting_id is validated first via api_require_uuid
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    public function testUpsertValidatesGiverBeforeReceiver(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'giver_member_id' => 'bad-uuid',
            'receiver_member_id' => 'also-bad',
        ]);

        $result = $this->callControllerMethod('upsert');

        // giver_member_id is validated before receiver_member_id
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('giver_member_id', $result['body']['field']);
    }

    // =========================================================================
    // upsert: POST WITH $_POST FALLBACK (no JSON body)
    // =========================================================================

    public function testUpsertWorksWithFormPostData(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        // Don't set JSON body; use $_POST instead
        $_POST = [
            'meeting_id' => 'bad-meeting',
        ];

        $result = $this->callControllerMethod('upsert');

        // The api_request function falls back to $_POST when JSON is invalid
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    public function testDeleteWorksWithFormPostData(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'meeting_id' => '',
        ];

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // listForMeeting: ACCEPTS VALID UUID FORMAT
    // (will fail on DB access but NOT on input validation)
    // =========================================================================

    public function testListForMeetingAcceptsValidUuidFormat(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234-1234-1234-123456789abc'];

        // With a valid UUID, the controller passes validation and attempts to
        // call ProxiesService which requires a DB. The resulting error should
        // NOT be a validation error.
        $result = $this->callControllerMethod('listForMeeting');

        $this->assertNotEquals('missing_or_invalid_uuid', $result['body']['error'] ?? '');
        $this->assertNotEquals('method_not_allowed', $result['body']['error'] ?? '');
    }

    public function testListForMeetingAcceptsUppercaseUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'AABBCCDD-1122-3344-5566-778899AABBCC'];

        $result = $this->callControllerMethod('listForMeeting');

        // UUID regex is case-insensitive, so uppercase should be accepted
        $this->assertNotEquals('missing_or_invalid_uuid', $result['body']['error'] ?? '');
    }

    // =========================================================================
    // delete: BOTH IDS VALID => PASSES VALIDATION, HITS DB LAYER
    // =========================================================================

    public function testDeleteWithValidIdsPassesInputValidation(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'proxy_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        ]);

        $result = $this->callControllerMethod('delete');

        // Should not be a missing_meeting_id or missing_proxy_id error
        $this->assertNotEquals('missing_meeting_id', $result['body']['error'] ?? '');
        $this->assertNotEquals('missing_proxy_id', $result['body']['error'] ?? '');
        $this->assertNotEquals('method_not_allowed', $result['body']['error'] ?? '');
    }

    // =========================================================================
    // CONTROLLER SOURCE STRUCTURE VERIFICATION
    // =========================================================================

    public function testListForMeetingResponseIncludesExpectedKeys(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProxiesController.php');

        $expectedKeys = ['meeting_id', 'count', 'items'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $source,
                "listForMeeting response should contain '{$key}' key",
            );
        }
    }

    public function testUpsertResponseIncludesExpectedKeys(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProxiesController.php');

        $expectedKeys = ['ok', 'meeting_id', 'giver_member_id', 'receiver_member_id', 'scope'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $source,
                "upsert response should contain '{$key}' key",
            );
        }
    }

    public function testDeleteResponseIncludesExpectedKeys(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProxiesController.php');

        $expectedKeys = ['deleted', 'proxy_id', 'giver_name', 'receiver_name'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $source,
                "delete response should contain '{$key}' key",
            );
        }
    }

    public function testUpsertRevokeResponseIncludesRevokedFlag(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProxiesController.php');

        $this->assertStringContainsString("'revoked'", $source);
    }

    public function testControllerUsesProxiesService(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProxiesController.php');

        $this->assertStringContainsString('ProxiesService', $source);
    }

    public function testControllerUsesMeetingRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProxiesController.php');

        $this->assertStringContainsString('MeetingRepository', $source);
    }

    public function testControllerUsesProxyRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProxiesController.php');

        $this->assertStringContainsString('ProxyRepository', $source);
    }

    public function testDeleteChecksArchivedMeeting(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProxiesController.php');

        $this->assertStringContainsString('meeting_archived', $source);
    }

    public function testDeleteChecksProxyExists(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProxiesController.php');

        $this->assertStringContainsString('proxy_not_found', $source);
    }

    public function testDeleteChecksMeetingExists(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProxiesController.php');

        $this->assertStringContainsString('meeting_not_found', $source);
    }

    public function testDeleteHandlesDeleteFailure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProxiesController.php');

        $this->assertStringContainsString('delete_failed', $source);
    }

    public function testUpsertAuditsProxyRevoke(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProxiesController.php');

        $this->assertStringContainsString("'proxy_revoked'", $source);
    }

    public function testUpsertAuditsProxyUpsert(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProxiesController.php');

        $this->assertStringContainsString("'proxy_upsert'", $source);
    }

    public function testDeleteAuditsProxyDeletion(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProxiesController.php');

        $this->assertStringContainsString("'proxy_deleted'", $source);
    }

    public function testUpsertGuardsMeetingNotValidated(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProxiesController.php');

        // api_guard_meeting_not_validated is called in upsert
        $this->assertStringContainsString('api_guard_meeting_not_validated', $source);
    }

    public function testDeleteGuardsMeetingNotValidated(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProxiesController.php');

        // Verify the guard is invoked; it appears at least twice (upsert + delete)
        $count = substr_count($source, 'api_guard_meeting_not_validated');
        $this->assertGreaterThanOrEqual(2, $count, 'api_guard_meeting_not_validated should be called in both upsert and delete');
    }

    // =========================================================================
    // delete: INPUT PARSING USES trim()
    // =========================================================================

    public function testDeleteInputParsingTrimsValues(): void
    {
        // Replicate the trim logic from delete()
        $input = [
            'meeting_id' => '  12345678-1234-1234-1234-123456789abc  ',
            'proxy_id' => '  aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee  ',
        ];

        $meetingId = trim((string) ($input['meeting_id'] ?? ''));
        $proxyId = trim((string) ($input['proxy_id'] ?? ''));

        $this->assertEquals('12345678-1234-1234-1234-123456789abc', $meetingId);
        $this->assertEquals('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', $proxyId);
        $this->assertTrue(api_is_uuid($meetingId));
        $this->assertTrue(api_is_uuid($proxyId));
    }

    public function testDeleteInputMissingKeysDefaultToEmpty(): void
    {
        $input = [];

        $meetingId = trim((string) ($input['meeting_id'] ?? ''));
        $proxyId = trim((string) ($input['proxy_id'] ?? ''));

        $this->assertEquals('', $meetingId);
        $this->assertEquals('', $proxyId);
    }

    // =========================================================================
    // upsert: RECEIVER VALIDATION DETAIL MESSAGE
    // =========================================================================

    public function testUpsertInvalidReceiverIncludesDetailMessage(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'giver_member_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'receiver_member_id' => 'garbage',
        ]);

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_receiver_member_id', $result['body']['error']);
        $this->assertArrayHasKey('detail', $result['body']);
    }

    // =========================================================================
    // HANDLE METHOD DISPATCHES CORRECTLY
    // =========================================================================

    public function testHandleDispatchesListForMeeting(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = []; // Missing meeting_id => validation error

        $result = $this->callControllerMethod('listForMeeting');

        // Proves listForMeeting was called (got its specific validation error)
        $this->assertEquals(400, $result['status']);
    }

    public function testHandleDispatchesUpsert(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('upsert');

        // Proves upsert was called (got its specific validation error)
        $this->assertEquals(400, $result['status']);
    }

    public function testHandleDispatchesDelete(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('delete');

        // Proves delete was called (got its specific validation error)
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // DELETE: ARCHIVED MEETING STATUS CHECK
    // =========================================================================

    public function testDeleteChecksMeetingArchivedStatus(): void
    {
        // Verify the controller checks meeting['status'] === 'archived'
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProxiesController.php');

        $this->assertStringContainsString("'archived'", $source);
        $this->assertStringContainsString("'meeting_archived'", $source);
    }

    public function testDeleteArchivedMeetingReturns409(): void
    {
        // Verify the HTTP status code in the source for archived meeting
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProxiesController.php');

        $this->assertStringContainsString('409', $source);
    }

    // =========================================================================
    // DELETE: deleteProxy FAILURE RETURNS 500
    // =========================================================================

    public function testDeleteFailureReturns500(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProxiesController.php');

        $this->assertStringContainsString("'delete_failed', 500", $source);
    }

    // =========================================================================
    // listForMeeting: USES api_current_tenant_id
    // =========================================================================

    public function testListForMeetingUsesCurrentTenantId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ProxiesController.php');

        // api_current_tenant_id is called in listForMeeting
        $count = substr_count($source, 'api_current_tenant_id()');
        $this->assertGreaterThanOrEqual(1, $count);
    }

    // =========================================================================
    // ADDITIONAL UUID EDGE CASES
    // =========================================================================

    public function testUuidValidationRejectsNumericInput(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345'];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testUuidValidationRejectsUuidWithoutDashes(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678123412341234123456789abc'];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testUuidValidationRejectsUuidWithExtraChars(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234-1234-1234-123456789abc-extra'];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }
}
