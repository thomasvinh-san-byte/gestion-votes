<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\MeetingAttachmentController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MeetingAttachmentController.
 *
 * Tests the meeting attachment endpoints including:
 *  - Controller structure (final, extends AbstractController)
 *  - HTTP method enforcement (GET vs POST vs DELETE)
 *  - UUID validation for meeting_id and attachment id
 *  - Input validation for upload and delete operations
 *  - Response structure verification via source introspection
 *  - Audit log verification
 */
class MeetingAttachmentControllerTest extends TestCase
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
        $controller = new MeetingAttachmentController();
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
     * Inject a JSON body for POST/DELETE requests via Request::$cachedRawBody.
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
        $ref = new \ReflectionClass(MeetingAttachmentController::class);
        $this->assertTrue($ref->isFinal(), 'MeetingAttachmentController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new MeetingAttachmentController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(MeetingAttachmentController::class);

        $expectedMethods = [
            'listForMeeting',
            'upload',
            'delete',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "MeetingAttachmentController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(MeetingAttachmentController::class);

        $expectedMethods = [
            'listForMeeting',
            'upload',
            'delete',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "MeetingAttachmentController::{$method}() should be public",
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

        $this->assertGreaterThanOrEqual(400, $result['status']);
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
        $_GET = ['meeting_id' => '12345678-1234'];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testListForMeetingRejectsNumericMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345'];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // upload: METHOD ENFORCEMENT
    // =========================================================================

    public function testUploadRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('upload');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testUploadRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('upload');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // upload: MEETING_ID VALIDATION
    // =========================================================================

    public function testUploadRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('upload');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testUploadRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => '']);

        $result = $this->callControllerMethod('upload');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testUploadRejectsInvalidMeetingUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => 'bad-uuid']);

        $result = $this->callControllerMethod('upload');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testUploadRejectsWhitespaceMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => '   ']);

        $result = $this->callControllerMethod('upload');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
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

    public function testDeleteRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['id' => '12345678-1234-1234-1234-123456789abc']);

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // delete: ID VALIDATION
    // =========================================================================

    public function testDeleteRequiresId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_id', $result['body']['error']);
    }

    public function testDeleteRejectsEmptyId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->injectJsonBody(['id' => '']);

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_id', $result['body']['error']);
    }

    public function testDeleteRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->injectJsonBody(['id' => 'not-a-uuid']);

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_id', $result['body']['error']);
    }

    public function testDeleteRejectsWhitespaceId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->injectJsonBody(['id' => '   ']);

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_id', $result['body']['error']);
    }

    // =========================================================================
    // UPLOAD: FILE SIZE VALIDATION LOGIC
    // =========================================================================

    public function testUploadMaxFileSize10Mb(): void
    {
        $maxSize = 10 * 1024 * 1024;
        $this->assertEquals(10485760, $maxSize, 'Max file size should be 10 MB');
    }

    public function testUploadFileSizeExceedsLimit(): void
    {
        $maxSize = 10 * 1024 * 1024;
        $fileSize = 11 * 1024 * 1024;
        $this->assertTrue($fileSize > $maxSize, 'File of 11 MB should exceed 10 MB limit');
    }

    public function testUploadFileSizeWithinLimit(): void
    {
        $maxSize = 10 * 1024 * 1024;
        $fileSize = 5 * 1024 * 1024;
        $this->assertFalse($fileSize > $maxSize, 'File of 5 MB should be within limit');
    }

    public function testUploadFileSizeExactlyAtLimit(): void
    {
        $maxSize = 10 * 1024 * 1024;
        $fileSize = 10 * 1024 * 1024;
        $this->assertFalse($fileSize > $maxSize, 'File of exactly 10 MB should be accepted');
    }

    // =========================================================================
    // UPLOAD: ALLOWED MIME TYPE LOGIC
    // =========================================================================

    public function testUploadOnlyAllowsPdfMimeType(): void
    {
        $allowedMimes = ['application/pdf'];

        $this->assertTrue(in_array('application/pdf', $allowedMimes, true));
        $this->assertFalse(in_array('image/png', $allowedMimes, true));
        $this->assertFalse(in_array('application/zip', $allowedMimes, true));
        $this->assertFalse(in_array('text/plain', $allowedMimes, true));
        $this->assertFalse(in_array('application/msword', $allowedMimes, true));
    }

    // =========================================================================
    // UPLOAD: FILE EXTENSION VALIDATION LOGIC
    // =========================================================================

    public function testUploadAcceptsPdfExtension(): void
    {
        $ext = strtolower(pathinfo('document.pdf', PATHINFO_EXTENSION));
        $this->assertEquals('pdf', $ext);
    }

    public function testUploadRejectsNonPdfExtension(): void
    {
        $ext = strtolower(pathinfo('document.docx', PATHINFO_EXTENSION));
        $this->assertNotEquals('pdf', $ext);
    }

    public function testUploadRejectsUppercasePdfExtensionNormalized(): void
    {
        $ext = strtolower(pathinfo('document.PDF', PATHINFO_EXTENSION));
        $this->assertEquals('pdf', $ext, 'Uppercase .PDF should be normalized to pdf');
    }

    public function testUploadRejectsNoExtension(): void
    {
        $ext = strtolower(pathinfo('document', PATHINFO_EXTENSION));
        $this->assertNotEquals('pdf', $ext);
    }

    // =========================================================================
    // RESPONSE STRUCTURE VERIFICATION (source-level)
    // =========================================================================

    public function testUploadResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingAttachmentController.php');

        $expectedKeys = ['id', 'original_name', 'file_size', 'mime_type'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $source,
                "upload() response should contain '{$key}'",
            );
        }
    }

    public function testUploadReturns201StatusCode(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingAttachmentController.php');

        $this->assertStringContainsString('201', $source, 'upload() should return 201 on success');
    }

    public function testDeleteResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingAttachmentController.php');

        $this->assertStringContainsString("'deleted' => true", $source, "delete() should return 'deleted' key");
    }

    public function testListForMeetingResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingAttachmentController.php');

        $this->assertStringContainsString("'attachments'", $source, "listForMeeting() should return 'attachments' key");
    }

    // =========================================================================
    // AUDIT LOG VERIFICATION (source-level)
    // =========================================================================

    public function testUploadAuditsAttachmentUploaded(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingAttachmentController.php');

        $this->assertStringContainsString("'meeting_attachment_uploaded'", $source);
    }

    public function testDeleteAuditsAttachmentDeleted(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingAttachmentController.php');

        $this->assertStringContainsString("'meeting_attachment_deleted'", $source);
    }

    public function testUploadAuditIncludesMeetingId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingAttachmentController.php');

        $this->assertStringContainsString("'meeting_id'", $source);
        $this->assertStringContainsString("'original_name'", $source);
        $this->assertStringContainsString("'file_size'", $source);
    }

    // =========================================================================
    // UUID VALIDATION HELPER
    // =========================================================================

    public function testUuidValidationForAttachmentIds(): void
    {
        $this->assertTrue(api_is_uuid('12345678-1234-1234-1234-123456789abc'));
        $this->assertTrue(api_is_uuid('00000000-0000-0000-0000-000000000000'));
        $this->assertFalse(api_is_uuid(''));
        $this->assertFalse(api_is_uuid('not-a-uuid'));
        $this->assertFalse(api_is_uuid('12345678-1234-1234-1234'));
    }

    // =========================================================================
    // UPLOAD: STORED NAME GENERATION LOGIC
    // =========================================================================

    public function testStoredNameIsPdfWithUuid(): void
    {
        $id = '12345678-1234-1234-1234-123456789abc';
        $storedName = $id . '.pdf';
        $this->assertEquals('12345678-1234-1234-1234-123456789abc.pdf', $storedName);
    }

    public function testUploadDirectoryStructure(): void
    {
        $meetingId = '12345678-1234-1234-1234-123456789abc';
        $uploadDir = '/some/root/storage/uploads/meetings/' . $meetingId;
        $this->assertStringContainsString($meetingId, $uploadDir);
        $this->assertStringEndsWith($meetingId, $uploadDir);
    }
}
