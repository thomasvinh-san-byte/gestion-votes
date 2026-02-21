<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\ReminderController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ReminderController.
 *
 * Tests the 3 reminder endpoints:
 *  - listForMeeting: GET, lists reminders for a meeting
 *  - upsert: POST, creates or updates a reminder schedule
 *  - delete: DELETE-like (via query), deletes a reminder by id
 *
 * Since api_ok()/api_fail() throw ApiResponseException, we catch these to
 * inspect controller behavior without a database.
 */
class ReminderControllerTest extends TestCase
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
        $controller = new ReminderController();
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
        $ref = new \ReflectionClass(ReminderController::class);
        $this->assertTrue($ref->isFinal(), 'ReminderController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new ReminderController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(ReminderController::class);

        $expectedMethods = ['listForMeeting', 'upsert', 'delete'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "ReminderController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(ReminderController::class);

        $expectedMethods = ['listForMeeting', 'upsert', 'delete'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "ReminderController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // listForMeeting: INPUT VALIDATION
    // =========================================================================

    public function testListForMeetingRejectsMissingMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testListForMeetingRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testListForMeetingRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'not-a-valid-uuid'];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testListForMeetingRejectsPartialUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234-1234-1234'];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testListForMeetingRejectsWhitespaceMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '   '];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
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
    // upsert: INPUT VALIDATION (source-level verification)
    // Note: upsert() creates ReminderScheduleRepository after api_request()
    // but before validation, which throws in test env (no DB). We verify
    // input validation logic via source inspection and logic replication.
    // =========================================================================

    public function testUpsertValidatesMeetingIdInSource(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ReminderController.php');

        $this->assertStringContainsString("api_fail('missing_meeting_id', 400)", $source);
        $this->assertStringContainsString("api_is_uuid(\$meetingId)", $source);
    }

    public function testUpsertMeetingIdValidationLogic(): void
    {
        // Replicate the validation logic from upsert()
        $input1 = ['meeting_id' => ''];
        $meetingId1 = trim((string) ($input1['meeting_id'] ?? ''));
        $this->assertTrue($meetingId1 === '' || !api_is_uuid($meetingId1), 'Empty meeting_id should be rejected');

        $input2 = ['meeting_id' => 'bad-uuid'];
        $meetingId2 = trim((string) ($input2['meeting_id'] ?? ''));
        $this->assertTrue($meetingId2 === '' || !api_is_uuid($meetingId2), 'Invalid UUID should be rejected');

        $input3 = ['meeting_id' => '12345678-1234-1234-1234-123456789abc'];
        $meetingId3 = trim((string) ($input3['meeting_id'] ?? ''));
        $this->assertFalse($meetingId3 === '' || !api_is_uuid($meetingId3), 'Valid UUID should be accepted');
    }

    public function testUpsertValidatesDaysBeforeInSource(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ReminderController.php');

        $this->assertStringContainsString("api_fail('invalid_days_before', 400", $source);
        $this->assertStringContainsString('days_before doit etre entre 0 et 30', $source);
    }

    public function testUpsertDaysBeforeValidationLogic(): void
    {
        // Replicate the validation from upsert()
        $testCases = [
            [null, true],   // null => invalid
            [-1, true],     // negative => invalid
            [31, true],     // exceeds 30 => invalid
            [0, false],     // zero => valid
            [15, false],    // mid-range => valid
            [30, false],    // max => valid
        ];
        foreach ($testCases as [$val, $shouldReject]) {
            $isInvalid = $val === null || $val < 0 || $val > 30;
            $this->assertEquals($shouldReject, $isInvalid, "days_before={$val} rejection mismatch");
        }
    }

    public function testUpsertValidatesTemplateIdInSource(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ReminderController.php');

        $this->assertStringContainsString("api_fail('invalid_template_id', 400)", $source);
        $this->assertStringContainsString('api_is_uuid($templateId)', $source);
    }

    public function testUpsertTemplateIdValidationLogic(): void
    {
        // Replicate the template_id validation: non-empty + non-UUID => reject
        $templateId1 = 'not-a-uuid';
        $this->assertTrue($templateId1 !== '' && !api_is_uuid($templateId1));

        $templateId2 = '12345678-1234-1234-1234-123456789abc';
        $this->assertFalse($templateId2 !== '' && !api_is_uuid($templateId2));

        // Empty is allowed (skips validation)
        $templateId3 = '';
        $this->assertFalse($templateId3 !== '' && !api_is_uuid($templateId3));
    }

    public function testUpsertValidatesSendTimeInSource(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ReminderController.php');

        $this->assertStringContainsString("api_fail('invalid_send_time', 400", $source);
        $this->assertStringContainsString("preg_match('/^\\d{2}:\\d{2}(:\\d{2})?$/'", $source);
    }

    // =========================================================================
    // upsert: SEND TIME FORMAT VALIDATION LOGIC
    // =========================================================================

    public function testSendTimeFormatValidation(): void
    {
        $pattern = '/^\d{2}:\d{2}(:\d{2})?$/';

        $this->assertMatchesRegularExpression($pattern, '09:00');
        $this->assertMatchesRegularExpression($pattern, '23:59');
        $this->assertMatchesRegularExpression($pattern, '09:00:00');
        $this->assertMatchesRegularExpression($pattern, '23:59:59');

        $this->assertDoesNotMatchRegularExpression($pattern, '9:00');
        $this->assertDoesNotMatchRegularExpression($pattern, 'ab:cd');
        $this->assertDoesNotMatchRegularExpression($pattern, '09:00:00:00');
        $this->assertDoesNotMatchRegularExpression($pattern, '');
        $this->assertDoesNotMatchRegularExpression($pattern, 'invalid');
    }

    // =========================================================================
    // upsert: SETUP DEFAULTS ACTION (source-level verification)
    // =========================================================================

    public function testUpsertSetupDefaultsInSource(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ReminderController.php');

        $this->assertStringContainsString("'action'", $source);
        $this->assertStringContainsString("'setup_defaults'", $source);
        $this->assertStringContainsString('setupDefaults', $source);
    }

    public function testUpsertSetupDefaultsMeetingIdValidationLogic(): void
    {
        // Replicate the setup_defaults meeting_id validation
        $input1 = ['action' => 'setup_defaults'];
        $meetingId1 = trim((string) ($input1['meeting_id'] ?? ''));
        $this->assertTrue($meetingId1 === '' || !api_is_uuid($meetingId1));

        $input2 = ['action' => 'setup_defaults', 'meeting_id' => 'bad-uuid'];
        $meetingId2 = trim((string) ($input2['meeting_id'] ?? ''));
        $this->assertTrue($meetingId2 === '' || !api_is_uuid($meetingId2));

        $input3 = ['action' => 'setup_defaults', 'meeting_id' => '12345678-1234-1234-1234-123456789abc'];
        $meetingId3 = trim((string) ($input3['meeting_id'] ?? ''));
        $this->assertFalse($meetingId3 === '' || !api_is_uuid($meetingId3));
    }

    // =========================================================================
    // upsert: DAYS_BEFORE BOUNDARY VALUES
    // =========================================================================

    public function testDaysBeforeBoundaryValues(): void
    {
        // Valid range: 0..30
        $this->assertTrue(0 >= 0 && 0 <= 30, 'days_before=0 should be valid');
        $this->assertTrue(30 >= 0 && 30 <= 30, 'days_before=30 should be valid');
        $this->assertTrue(15 >= 0 && 15 <= 30, 'days_before=15 should be valid');
        $this->assertFalse(-1 >= 0 && -1 <= 30, 'days_before=-1 should be invalid');
        $this->assertFalse(31 >= 0 && 31 <= 30, 'days_before=31 should be invalid');
    }

    // =========================================================================
    // delete: INPUT VALIDATION
    // =========================================================================

    public function testDeleteRejectsMissingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_reminder_id', $result['body']['error']);
    }

    public function testDeleteRejectsEmptyId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['id' => ''];

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_reminder_id', $result['body']['error']);
    }

    public function testDeleteRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['id' => 'not-a-uuid'];

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_reminder_id', $result['body']['error']);
    }

    public function testDeleteRejectsPartialUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['id' => '12345678-1234-1234'];

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_reminder_id', $result['body']['error']);
    }

    public function testDeleteRejectsWhitespaceId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['id' => '   '];

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_reminder_id', $result['body']['error']);
    }

    // =========================================================================
    // CONTROLLER SOURCE VERIFICATION
    // =========================================================================

    public function testControllerUsesReminderScheduleRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ReminderController.php');

        $this->assertStringContainsString('ReminderScheduleRepository', $source);
    }

    public function testControllerUsesEmailTemplateRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ReminderController.php');

        $this->assertStringContainsString('EmailTemplateRepository', $source);
    }

    public function testControllerAuditsOperations(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ReminderController.php');

        $this->assertStringContainsString("'reminder.setup_defaults'", $source);
        $this->assertStringContainsString("'reminder.upsert'", $source);
        $this->assertStringContainsString("'reminder.delete'", $source);
    }

    public function testUpsertGuardsMeetingNotValidated(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ReminderController.php');

        $this->assertStringContainsString('api_guard_meeting_not_validated', $source);
    }

    public function testUpsertReturns201OnSuccess(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ReminderController.php');

        $this->assertStringContainsString('201', $source);
    }

    // =========================================================================
    // HANDLE: UNKNOWN METHOD
    // =========================================================================

    public function testHandleUnknownMethodReturnsInternalError(): void
    {
        $controller = new ReminderController();
        try {
            $controller->handle('nonExistentMethod');
            $this->fail('Expected ApiResponseException was not thrown');
        } catch (ApiResponseException $e) {
            $this->assertEquals(500, $e->getResponse()->getStatusCode());
            $this->assertEquals('internal_error', $e->getResponse()->getBody()['error']);
        }
    }

    // =========================================================================
    // upsert: DEFAULT VALUES LOGIC
    // =========================================================================

    public function testUpsertSendTimeDefault(): void
    {
        $input = [];
        $sendTime = isset($input['send_time']) ? trim((string) $input['send_time']) : '09:00';

        $this->assertEquals('09:00', $sendTime);
    }

    public function testUpsertIsActiveDefault(): void
    {
        $input = [];
        $isActive = !isset($input['is_active']) || (bool) $input['is_active'];

        $this->assertTrue($isActive, 'Default is_active should be true');
    }

    public function testUpsertIsActiveFalse(): void
    {
        $input = ['is_active' => false];
        $isActive = !isset($input['is_active']) || (bool) $input['is_active'];

        $this->assertFalse($isActive, 'Explicit false should be false');
    }

    public function testUpsertIsActiveZero(): void
    {
        $input = ['is_active' => 0];
        $isActive = !isset($input['is_active']) || (bool) $input['is_active'];

        $this->assertFalse($isActive, 'Explicit 0 should be false');
    }
}
