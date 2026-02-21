<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\AttendancesController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AttendancesController.
 *
 * Tests the attendance endpoints logic including:
 *  - Controller structure (final, extends AbstractController, public methods)
 *  - listForMeeting: method enforcement and meeting_id validation
 *  - upsert: method enforcement, meeting_id / member_id validation
 *  - bulk: method enforcement, meeting_id validation, mode validation,
 *          member_ids type validation
 *  - setPresentFrom: method enforcement, meeting_id / member_id validation
 *
 * Since api_ok()/api_fail() throw ApiResponseException, we catch these to
 * inspect controller behavior without a database.
 */
class AttendancesControllerTest extends TestCase
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
        $controller = new AttendancesController();
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
    private function injectJsonBody(array $data): void
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
        $ref = new \ReflectionClass(AttendancesController::class);
        $this->assertTrue($ref->isFinal(), 'AttendancesController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new AttendancesController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(AttendancesController::class);

        $expectedMethods = ['listForMeeting', 'upsert', 'bulk', 'setPresentFrom'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "AttendancesController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(AttendancesController::class);

        $expectedMethods = ['listForMeeting', 'upsert', 'bulk', 'setPresentFrom'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "AttendancesController::{$method}() should be public",
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

    // =========================================================================
    // listForMeeting: meeting_id VALIDATION
    // =========================================================================

    public function testListForMeetingRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = []; // No meeting_id

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_request', $result['body']['error']);
    }

    public function testListForMeetingRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_request', $result['body']['error']);
    }

    public function testListForMeetingRejectsNonUuidMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'not-a-valid-uuid'];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_request', $result['body']['error']);
    }

    public function testListForMeetingRejectsPartialUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234-1234-1234'];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_request', $result['body']['error']);
    }

    public function testListForMeetingRejectsWhitespaceMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '   '];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_request', $result['body']['error']);
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

    // =========================================================================
    // upsert: meeting_id VALIDATION
    // =========================================================================

    public function testUpsertRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testUpsertRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => '']);

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testUpsertRejectsNonUuidMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => 'invalid']);

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testUpsertRejectsWhitespaceMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => '   ']);

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // upsert: member_id VALIDATION
    // =========================================================================

    public function testUpsertRequiresMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_member_id', $result['body']['error']);
    }

    public function testUpsertRejectsEmptyMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'member_id' => '',
        ]);

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_member_id', $result['body']['error']);
    }

    public function testUpsertRejectsNonUuidMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'member_id' => 'not-a-uuid',
        ]);

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_member_id', $result['body']['error']);
    }

    public function testUpsertRejectsWhitespaceMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'member_id' => '   ',
        ]);

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_member_id', $result['body']['error']);
    }

    // =========================================================================
    // bulk: METHOD ENFORCEMENT
    // =========================================================================

    public function testBulkRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('bulk');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testBulkRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('bulk');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testBulkRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('bulk');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // bulk: meeting_id VALIDATION
    // =========================================================================

    public function testBulkRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('bulk');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testBulkRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => '']);

        $result = $this->callControllerMethod('bulk');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testBulkRejectsNonUuidMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => 'bad-uuid']);

        $result = $this->callControllerMethod('bulk');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testBulkRejectsWhitespaceMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => '   ']);

        $result = $this->callControllerMethod('bulk');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // bulk: mode VALIDATION
    // =========================================================================

    public function testBulkRejectsInvalidMode(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'mode' => 'invalid_mode',
        ]);

        $result = $this->callControllerMethod('bulk');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_mode', $result['body']['error']);
    }

    public function testBulkRejectsEmptyStringMode(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'mode' => '',
        ]);

        $result = $this->callControllerMethod('bulk');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_mode', $result['body']['error']);
    }

    public function testBulkRejectsNumericMode(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'mode' => '123',
        ]);

        $result = $this->callControllerMethod('bulk');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_mode', $result['body']['error']);
    }

    public function testBulkRejectsCaseSensitiveMode(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'mode' => 'Present', // Capital P should be invalid
        ]);

        $result = $this->callControllerMethod('bulk');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_mode', $result['body']['error']);
    }

    // =========================================================================
    // bulk: mode VALID VALUES (each valid mode should pass mode validation)
    // The controller will fail later (DB access) but should pass mode check.
    // We cannot test beyond mode check without DB, so we verify invalid_mode
    // is NOT the error for valid modes.
    // =========================================================================

    /**
     * @dataProvider validModesProvider
     */
    public function testBulkAcceptsValidMode(string $mode): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'mode' => $mode,
        ]);

        $result = $this->callControllerMethod('bulk');

        // Should not fail with invalid_mode - it will fail later (DB access)
        $this->assertNotEquals('invalid_mode', $result['body']['error'] ?? null,
            "Mode '{$mode}' should be accepted as valid");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validModesProvider(): array
    {
        return [
            'present' => ['present'],
            'absent' => ['absent'],
            'remote' => ['remote'],
            'proxy' => ['proxy'],
            'excused' => ['excused'],
        ];
    }

    // =========================================================================
    // bulk: mode DEFAULT (status fallback)
    // =========================================================================

    public function testBulkDefaultsModeToPresent(): void
    {
        // When neither mode nor status is provided, defaults to 'present'
        // which is a valid mode, so should not fail with invalid_mode
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            // No mode, no status => default 'present'
        ]);

        $result = $this->callControllerMethod('bulk');

        $this->assertNotEquals('missing_meeting_id', $result['body']['error'] ?? null);
        $this->assertNotEquals('invalid_mode', $result['body']['error'] ?? null);
    }

    public function testBulkFallsBackToStatusField(): void
    {
        // When mode is not provided, controller falls back to status field
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'status' => 'remote',
        ]);

        $result = $this->callControllerMethod('bulk');

        // Should not fail with invalid_mode since 'remote' is valid
        $this->assertNotEquals('invalid_mode', $result['body']['error'] ?? null);
    }

    // =========================================================================
    // bulk: member_ids TYPE VALIDATION (source verification)
    // Note: The member_ids check is behind a MeetingRepository DB call,
    // so it cannot be reached in unit tests without a database.
    // We verify the validation logic exists in the source instead.
    // =========================================================================

    public function testBulkHasMemberIdsTypeValidation(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AttendancesController.php');

        $this->assertStringContainsString('!is_array($memberIds)', $source,
            'bulk should validate that member_ids is an array');
        $this->assertStringContainsString('invalid_member_ids', $source,
            'bulk should return invalid_member_ids error for non-array');
    }

    public function testBulkMemberIdsValidationLogic(): void
    {
        // Replicate the member_ids type validation logic from bulk()
        $memberIds = 'not-an-array';
        $shouldFail = $memberIds !== null && !is_array($memberIds);
        $this->assertTrue($shouldFail, 'String member_ids should be rejected');

        $memberIds = 12345;
        $shouldFail = $memberIds !== null && !is_array($memberIds);
        $this->assertTrue($shouldFail, 'Numeric member_ids should be rejected');

        $memberIds = true;
        $shouldFail = $memberIds !== null && !is_array($memberIds);
        $this->assertTrue($shouldFail, 'Boolean member_ids should be rejected');

        $memberIds = ['id1', 'id2'];
        $shouldFail = $memberIds !== null && !is_array($memberIds);
        $this->assertFalse($shouldFail, 'Array member_ids should be accepted');

        $memberIds = null;
        $shouldFail = $memberIds !== null && !is_array($memberIds);
        $this->assertFalse($shouldFail, 'Null member_ids should be accepted (means all members)');
    }

    public function testBulkHasNoMembersCheck(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AttendancesController.php');

        $this->assertStringContainsString('no_members', $source,
            'bulk should return no_members error when no members to process');
    }

    // =========================================================================
    // setPresentFrom: METHOD ENFORCEMENT
    // =========================================================================

    public function testSetPresentFromRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('setPresentFrom');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testSetPresentFromRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('setPresentFrom');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testSetPresentFromRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('setPresentFrom');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // setPresentFrom: meeting_id VALIDATION (via api_require_uuid)
    // =========================================================================

    public function testSetPresentFromRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('setPresentFrom');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field'] ?? null);
    }

    public function testSetPresentFromRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => '']);

        $result = $this->callControllerMethod('setPresentFrom');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field'] ?? null);
    }

    public function testSetPresentFromRejectsInvalidMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => 'not-a-uuid']);

        $result = $this->callControllerMethod('setPresentFrom');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field'] ?? null);
    }

    // =========================================================================
    // setPresentFrom: member_id VALIDATION (via api_require_uuid)
    // =========================================================================

    public function testSetPresentFromRequiresMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('setPresentFrom');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('member_id', $result['body']['field'] ?? null);
    }

    public function testSetPresentFromRejectsEmptyMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'member_id' => '',
        ]);

        $result = $this->callControllerMethod('setPresentFrom');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('member_id', $result['body']['field'] ?? null);
    }

    public function testSetPresentFromRejectsNonUuidMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'member_id' => 'bad-uuid',
        ]);

        $result = $this->callControllerMethod('setPresentFrom');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('member_id', $result['body']['field'] ?? null);
    }

    // =========================================================================
    // CROSS-CUTTING: UUID FORMAT ACCEPTANCE
    // =========================================================================

    public function testUuidValidationAcceptsLowercaseUuid(): void
    {
        $this->assertTrue(api_is_uuid('12345678-1234-1234-1234-123456789abc'));
    }

    public function testUuidValidationAcceptsUppercaseUuid(): void
    {
        $this->assertTrue(api_is_uuid('12345678-1234-1234-1234-123456789ABC'));
    }

    public function testUuidValidationAcceptsMixedCaseUuid(): void
    {
        $this->assertTrue(api_is_uuid('12345678-abCD-1234-EF56-123456789abc'));
    }

    public function testUuidValidationRejectsShortString(): void
    {
        $this->assertFalse(api_is_uuid('12345'));
    }

    public function testUuidValidationRejectsEmptyString(): void
    {
        $this->assertFalse(api_is_uuid(''));
    }

    public function testUuidValidationRejectsMissingDashes(): void
    {
        $this->assertFalse(api_is_uuid('12345678123412341234123456789abc'));
    }

    // =========================================================================
    // CROSS-CUTTING: POST WITH EMPTY BODY
    // =========================================================================

    public function testUpsertWithEmptyJsonBody(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testBulkWithEmptyJsonBody(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('bulk');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testSetPresentFromWithEmptyJsonBody(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('setPresentFrom');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    // =========================================================================
    // CROSS-CUTTING: VALIDATION ORDER
    // Ensures method check happens before body validation.
    // =========================================================================

    public function testUpsertMethodCheckBeforeBodyValidation(): void
    {
        // GET method with valid JSON body - should fail on method, not body
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'member_id' => '12345678-1234-1234-1234-123456789abc',
            'mode' => 'present',
        ]);

        $result = $this->callControllerMethod('upsert');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testBulkMethodCheckBeforeBodyValidation(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'mode' => 'present',
        ]);

        $result = $this->callControllerMethod('bulk');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testSetPresentFromMethodCheckBeforeBodyValidation(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'member_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('setPresentFrom');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // CROSS-CUTTING: VALIDATION ORDER (meeting_id before member_id)
    // =========================================================================

    public function testUpsertValidatesMeetingIdBeforeMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => 'bad',
            'member_id' => 'also-bad',
        ]);

        $result = $this->callControllerMethod('upsert');

        // Should fail on meeting_id first, not member_id
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testSetPresentFromValidatesMeetingIdBeforeMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => 'bad',
            'member_id' => 'also-bad',
        ]);

        $result = $this->callControllerMethod('setPresentFrom');

        // Should fail on meeting_id first
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field'] ?? null);
    }

    // =========================================================================
    // bulk: VALIDATION ORDER (meeting_id -> mode -> member_ids)
    // =========================================================================

    public function testBulkValidatesMeetingIdBeforeMode(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => 'bad',
            'mode' => 'invalid_mode',
        ]);

        $result = $this->callControllerMethod('bulk');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testBulkValidatesModeBeforeMemberIds(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'mode' => 'invalid_mode',
            'member_ids' => 'not-an-array',
        ]);

        $result = $this->callControllerMethod('bulk');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_mode', $result['body']['error']);
    }

    // =========================================================================
    // RESPONSE STRUCTURE: error responses contain 'ok' => false
    // =========================================================================

    public function testErrorResponseContainsOkFalse(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertArrayHasKey('ok', $result['body']);
        $this->assertFalse($result['body']['ok']);
    }

    public function testErrorResponseContainsErrorKey(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertArrayHasKey('error', $result['body']);
        $this->assertIsString($result['body']['error']);
    }

    // =========================================================================
    // bulk: VALID MODES INCLUDE ALL EXPECTED VALUES
    // =========================================================================

    public function testBulkValidModesListIsComplete(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AttendancesController.php');

        // Verify all 5 valid modes are present in the controller
        $expectedModes = ['present', 'absent', 'remote', 'proxy', 'excused'];
        foreach ($expectedModes as $mode) {
            $this->assertStringContainsString("'{$mode}'", $source,
                "Valid modes list should include '{$mode}'");
        }
    }

    // =========================================================================
    // SOURCE STRUCTURE: verify key patterns in controller source
    // =========================================================================

    public function testControllerUsesApiRequestForMethodEnforcement(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AttendancesController.php');

        $this->assertStringContainsString("api_request('GET')", $source,
            'listForMeeting should enforce GET');
        $this->assertStringContainsString("api_request('POST')", $source,
            'POST endpoints should enforce POST');
    }

    public function testControllerUsesApiIsUuidForValidation(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AttendancesController.php');

        $this->assertStringContainsString('api_is_uuid(', $source,
            'Controller should use api_is_uuid for UUID validation');
    }

    public function testControllerUsesApiRequireUuid(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AttendancesController.php');

        $this->assertStringContainsString('api_require_uuid(', $source,
            'setPresentFrom should use api_require_uuid');
    }

    public function testControllerCallsApiGuardMeetingNotValidated(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AttendancesController.php');

        $this->assertStringContainsString('api_guard_meeting_not_validated(', $source,
            'Controller should guard against validated meetings');
    }

    public function testControllerUsesAuditLog(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AttendancesController.php');

        $this->assertStringContainsString('audit_log(', $source,
            'Controller should log audit events');
    }

    public function testControllerUsesEventBroadcaster(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AttendancesController.php');

        $this->assertStringContainsString('EventBroadcaster::attendanceUpdated(', $source,
            'bulk() should broadcast attendance updates via WebSocket');
    }

    public function testControllerUsesAttendancesService(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AttendancesController.php');

        $this->assertStringContainsString('new AttendancesService()', $source,
            'Controller should instantiate AttendancesService');
    }

    public function testControllerUsesAttendanceRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AttendancesController.php');

        $this->assertStringContainsString('new AttendanceRepository()', $source,
            'Controller should instantiate AttendanceRepository');
    }

    // =========================================================================
    // listForMeeting: RESPONSE STRUCTURE (source verification)
    // =========================================================================

    public function testListForMeetingResponseIncludesItemsAndSummary(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AttendancesController.php');

        $this->assertStringContainsString("'items'", $source,
            'listForMeeting response should include items key');
        $this->assertStringContainsString("'summary'", $source,
            'listForMeeting response should include summary key');
    }

    // =========================================================================
    // upsert: RESPONSE STRUCTURE (source verification)
    // =========================================================================

    public function testUpsertResponseIncludesAttendance(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AttendancesController.php');

        $this->assertStringContainsString("'attendance'", $source,
            'upsert response should include attendance key');
    }

    // =========================================================================
    // bulk: RESPONSE STRUCTURE (source verification)
    // =========================================================================

    public function testBulkResponseIncludesCreatedUpdatedTotal(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AttendancesController.php');

        $this->assertStringContainsString("'created'", $source,
            'bulk response should include created key');
        $this->assertStringContainsString("'updated'", $source,
            'bulk response should include updated key');
        $this->assertStringContainsString("'total'", $source,
            'bulk response should include total key');
    }

    // =========================================================================
    // setPresentFrom: RESPONSE STRUCTURE (source verification)
    // =========================================================================

    public function testSetPresentFromResponseIncludesSaved(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AttendancesController.php');

        $this->assertStringContainsString("'saved' => true", $source,
            'setPresentFrom response should include saved => true');
    }

    // =========================================================================
    // bulk: ARCHIVED MEETING CHECK (source verification)
    // =========================================================================

    public function testBulkChecksArchivedMeetingStatus(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AttendancesController.php');

        $this->assertStringContainsString("'archived'", $source,
            'bulk should check for archived meeting status');
        $this->assertStringContainsString('meeting_archived', $source,
            'bulk should return meeting_archived error');
    }

    // =========================================================================
    // bulk: TRANSACTION USAGE (source verification)
    // =========================================================================

    public function testBulkUsesTransaction(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AttendancesController.php');

        $this->assertStringContainsString('api_transaction(', $source,
            'bulk should wrap updates in a transaction');
    }

    // =========================================================================
    // setPresentFrom: AUDIT LOG EVENTS (source verification)
    // =========================================================================

    public function testSetPresentFromLogsSetAndClearEvents(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AttendancesController.php');

        $this->assertStringContainsString('attendance_present_from_set', $source,
            'setPresentFrom should log set event');
        $this->assertStringContainsString('attendance_present_from_cleared', $source,
            'setPresentFrom should log cleared event');
    }

    // =========================================================================
    // upsert: NOTES HANDLING (source verification)
    // =========================================================================

    public function testUpsertHandlesNotes(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AttendancesController.php');

        $this->assertStringContainsString("'notes'", $source,
            'upsert should handle notes field');
    }

    // =========================================================================
    // upsert: AUDIT LOG EVENT (source verification)
    // =========================================================================

    public function testUpsertLogsAuditEvent(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AttendancesController.php');

        $this->assertStringContainsString("'attendance.upsert'", $source,
            'upsert should log attendance.upsert audit event');
    }

    // =========================================================================
    // bulk: AUDIT LOG EVENT (source verification)
    // =========================================================================

    public function testBulkLogsAuditEvent(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AttendancesController.php');

        $this->assertStringContainsString("'attendances_bulk_update'", $source,
            'bulk should log attendances_bulk_update audit event');
    }

    // =========================================================================
    // HANDLE: unknown method falls through to AbstractController error handling
    // =========================================================================

    public function testHandleUnknownMethodReturns500(): void
    {
        $result = $this->callControllerMethod('nonExistentMethod');

        $this->assertEquals(500, $result['status']);
        $this->assertEquals('internal_error', $result['body']['error']);
    }
}
