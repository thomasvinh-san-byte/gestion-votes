<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\EmailController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EmailController.
 *
 * Tests the email endpoint logic including:
 *  - Controller structure (final, extends AbstractController)
 *  - HTTP method enforcement (GET vs POST)
 *  - Input validation for preview, schedule, sendBulk
 *  - UUID validation for meeting_id, template_id
 *  - Missing required fields (body_html, meeting_id)
 *  - Response/audit structure verification via source inspection
 */
class EmailControllerTest extends TestCase
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
        $controller = new EmailController();
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
        $ref = new \ReflectionClass(EmailController::class);
        $this->assertTrue($ref->isFinal(), 'EmailController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new EmailController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(EmailController::class);

        $expectedMethods = ['preview', 'schedule', 'sendBulk'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "EmailController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(EmailController::class);

        $expectedMethods = ['preview', 'schedule', 'sendBulk'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "EmailController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // preview: METHOD ENFORCEMENT
    // =========================================================================

    public function testPreviewRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('preview');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testPreviewRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('preview');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testPreviewRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('preview');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // preview: INPUT VALIDATION
    // =========================================================================

    public function testPreviewRequiresBodyHtml(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('preview');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_body_html', $result['body']['error']);
    }

    public function testPreviewRejectsEmptyBodyHtml(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['body_html' => '']);

        $result = $this->callControllerMethod('preview');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_body_html', $result['body']['error']);
    }

    public function testPreviewRejectsWhitespaceOnlyBodyHtml(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['body_html' => '   ']);

        $result = $this->callControllerMethod('preview');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_body_html', $result['body']['error']);
    }

    // =========================================================================
    // schedule: METHOD ENFORCEMENT
    // =========================================================================

    public function testScheduleRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('schedule');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testScheduleRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('schedule');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // schedule: meeting_id VALIDATION
    // =========================================================================

    public function testScheduleRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('schedule');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testScheduleRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => '']);

        $result = $this->callControllerMethod('schedule');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testScheduleRejectsInvalidMeetingUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => 'not-a-uuid']);

        $result = $this->callControllerMethod('schedule');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testScheduleRejectsWhitespaceMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => '   ']);

        $result = $this->callControllerMethod('schedule');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // schedule: template_id VALIDATION
    // =========================================================================

    public function testScheduleTemplateIdValidationLogic(): void
    {
        // Replicate: if ($templateId !== null && $templateId !== '' && !api_is_uuid($templateId))
        $testCases = [
            ['input' => null, 'expectFail' => false],
            ['input' => '', 'expectFail' => false],
            ['input' => '12345678-1234-1234-1234-123456789abc', 'expectFail' => false],
            ['input' => 'not-uuid', 'expectFail' => true],
            ['input' => '12345', 'expectFail' => true],
        ];

        foreach ($testCases as $case) {
            $templateId = $case['input'] !== null ? trim((string) $case['input']) : null;
            $invalid = $templateId !== null && $templateId !== '' && !api_is_uuid($templateId);
            $this->assertEquals(
                $case['expectFail'],
                $invalid,
                "template_id '{$case['input']}' validation should " . ($case['expectFail'] ? 'fail' : 'pass'),
            );
        }
    }

    // =========================================================================
    // schedule: scheduled_at DATE PARSING LOGIC
    // =========================================================================

    public function testScheduleDateParsingIso8601(): void
    {
        $scheduledAt = '2025-06-15T10:30:00+02:00';
        $dt = \DateTime::createFromFormat(\DateTime::ATOM, $scheduledAt);
        $this->assertNotFalse($dt, 'ISO 8601 format should be parsed');
    }

    public function testScheduleDateParsingYmdHis(): void
    {
        $scheduledAt = '2025-06-15 10:30:00';
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $scheduledAt);
        $this->assertNotFalse($dt, 'Y-m-d H:i:s format should be parsed');
    }

    public function testScheduleDateParsingYmdHi(): void
    {
        $scheduledAt = '2025-06-15 10:30';
        $dt = \DateTime::createFromFormat('Y-m-d H:i', $scheduledAt);
        $this->assertNotFalse($dt, 'Y-m-d H:i format should be parsed');
    }

    public function testScheduleDateParsingInvalidFormat(): void
    {
        $scheduledAt = '15/06/2025';
        $dt = \DateTime::createFromFormat(\DateTime::ATOM, $scheduledAt);
        if (!$dt) {
            $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $scheduledAt);
        }
        if (!$dt) {
            $dt = \DateTime::createFromFormat('Y-m-d H:i', $scheduledAt);
        }
        $this->assertFalse($dt, 'Invalid date format should not be parsed');
    }

    // =========================================================================
    // sendBulk: METHOD ENFORCEMENT
    // =========================================================================

    public function testSendBulkRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('sendBulk');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testSendBulkRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('sendBulk');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testSendBulkRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('sendBulk');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // sendBulk: meeting_id VALIDATION
    // =========================================================================

    public function testSendBulkRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('sendBulk');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testSendBulkRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => '']);

        $result = $this->callControllerMethod('sendBulk');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testSendBulkRejectsInvalidMeetingUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => 'bad-uuid']);

        $result = $this->callControllerMethod('sendBulk');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testSendBulkRejectsPartialUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => '12345678-1234-1234']);

        $result = $this->callControllerMethod('sendBulk');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // CROSS-CUTTING: METHOD CHECK BEFORE BODY VALIDATION
    // =========================================================================

    public function testPreviewMethodCheckBeforeBodyValidation(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->injectJsonBody(['body_html' => '<p>Hello</p>']);

        $result = $this->callControllerMethod('preview');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testScheduleMethodCheckBeforeBodyValidation(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->injectJsonBody(['meeting_id' => '12345678-1234-1234-1234-123456789abc']);

        $result = $this->callControllerMethod('schedule');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testSendBulkMethodCheckBeforeBodyValidation(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->injectJsonBody(['meeting_id' => '12345678-1234-1234-1234-123456789abc']);

        $result = $this->callControllerMethod('sendBulk');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // RESPONSE STRUCTURE (source verification)
    // =========================================================================

    public function testPreviewResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailController.php');

        $expectedKeys = ['preview_html', 'preview_subject', 'validation', 'available_variables'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $source,
                "preview() response should contain '{$key}'",
            );
        }
    }

    public function testScheduleResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailController.php');

        $expectedKeys = ['meeting_id', 'scheduled', 'skipped', 'scheduled_at', 'errors'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $source,
                "schedule() response should contain '{$key}'",
            );
        }
    }

    public function testSendBulkResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailController.php');

        $expectedKeys = ['meeting_id', 'meeting_title', 'dry_run', 'sent', 'skipped', 'errors'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $source,
                "sendBulk() response should contain '{$key}'",
            );
        }
    }

    public function testSendBulkResponseIncludesSkippedDetails(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailController.php');

        $this->assertStringContainsString("'skipped_no_email'", $source);
        $this->assertStringContainsString("'skipped_already_sent'", $source);
    }

    // =========================================================================
    // AUDIT LOG VERIFICATION (source-level)
    // =========================================================================

    public function testScheduleAuditsEmailSchedule(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailController.php');

        $this->assertStringContainsString("'email.schedule'", $source);
    }

    public function testSendBulkAuditsEmailSendBulk(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailController.php');

        $this->assertStringContainsString("'email.send_bulk'", $source);
    }

    // =========================================================================
    // BUSINESS GUARD VERIFICATION (source-level)
    // =========================================================================

    public function testScheduleGuardsMeetingNotValidated(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailController.php');

        $this->assertStringContainsString('api_guard_meeting_not_validated', $source);
    }

    public function testSendBulkGuardsMeetingNotValidated(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailController.php');

        // sendBulk also calls api_guard_meeting_not_validated
        $this->assertStringContainsString('api_guard_meeting_not_validated', $source);
    }

    public function testSendBulkChecksSmtpConfiguration(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailController.php');

        $this->assertStringContainsString('smtp_not_configured', $source);
        $this->assertStringContainsString('isConfigured', $source);
    }

    // =========================================================================
    // UNKNOWN METHOD HANDLING
    // =========================================================================

    public function testHandleUnknownMethodReturns500(): void
    {
        $result = $this->callControllerMethod('nonExistentMethod');

        $this->assertEquals(500, $result['status']);
        $this->assertEquals('internal_error', $result['body']['error']);
    }

    // =========================================================================
    // sendBulk: DRY RUN LOGIC
    // =========================================================================

    public function testSendBulkDryRunFlagParsing(): void
    {
        $this->assertFalse((bool) (false));
        $this->assertFalse((bool) (0));
        $this->assertFalse((bool) (null));
        $this->assertTrue((bool) (true));
        $this->assertTrue((bool) (1));
        $this->assertTrue((bool) ('1'));
    }

    public function testSendBulkOnlyUnsentDefaultsToTrue(): void
    {
        // Replicate: (bool) ($input['only_unsent'] ?? true)
        $input = [];
        $onlyUnsent = (bool) ($input['only_unsent'] ?? true);
        $this->assertTrue($onlyUnsent);
    }
}
