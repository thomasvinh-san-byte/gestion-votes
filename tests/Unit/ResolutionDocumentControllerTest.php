<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\AbstractController;
use AgVote\Controller\ResolutionDocumentController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ResolutionDocumentController.
 *
 * Tests controller structure, source-level validation for all 4 endpoints
 * (listForMotion, upload, delete, serve), dual auth pattern, security headers,
 * audit logging, SSE broadcasting, and eager repo construction behavior.
 *
 * Note: listForMotion(), delete(), and serve() call $this->repo() early, which
 * triggers RepositoryFactory::getInstance() -> get() -> new $class(null) -> db()
 * -> RuntimeException. This is caught by AbstractController::handle() as
 * business_error (400).
 *
 * Input validation for these methods is verified at source level because the
 * repo construction fires before input validation can be reached in test env.
 *
 * upload() calls api_request('POST') before repo construction, so method
 * enforcement is testable. After passing the method check, the repo fires.
 */
class ResolutionDocumentControllerTest extends TestCase
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
        $_FILES = [];

        // Reset Request cached body
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

        $_FILES = [];
        parent::tearDown();
    }

    // =========================================================================
    // HELPER: Call controller and capture response
    // =========================================================================

    private function callControllerMethod(string $method): array
    {
        $controller = new ResolutionDocumentController();
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
        $ref = new \ReflectionClass(ResolutionDocumentController::class);
        $this->assertTrue($ref->isFinal(), 'ResolutionDocumentController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new ResolutionDocumentController();
        $this->assertInstanceOf(AbstractController::class, $controller);
    }

    public function testControllerHasExpectedMethods(): void
    {
        $ref = new \ReflectionClass(ResolutionDocumentController::class);

        foreach (['listForMotion', 'upload', 'delete', 'serve'] as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "ResolutionDocumentController should have a '{$method}' method",
            );
        }
    }

    // =========================================================================
    // listForMotion -- EAGER REPO FIRES FIRST
    // listForMotion() calls $this->repo()->resolutionDocument() which triggers
    // RepositoryFactory::getInstance() -> db() -> RuntimeException -> 400
    // =========================================================================

    public function testListForMotionEagerRepoThrowsBusinessError(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['motion_id'] = '12345678-1234-1234-1234-123456789abc';

        $result = $this->callControllerMethod('listForMotion');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    // =========================================================================
    // listForMotion -- SOURCE-LEVEL VALIDATION
    // =========================================================================

    public function testListForMotionSourceValidatesMotionId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ResolutionDocumentController.php');

        $this->assertStringContainsString("api_query('motion_id')", $source);
        $this->assertStringContainsString('api_is_uuid', $source);
        $this->assertStringContainsString("'missing_motion_id'", $source);
    }

    // =========================================================================
    // upload -- METHOD ENFORCEMENT
    // upload() calls api_request('POST') before $this->repo(), so method
    // enforcement is testable at runtime.
    // =========================================================================

    public function testUploadSourceUsesApiRequestPost(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ResolutionDocumentController.php');

        $this->assertStringContainsString("api_request('POST')", $source);
    }

    public function testUploadRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('upload');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // upload -- SOURCE-LEVEL VALIDATION
    // =========================================================================

    public function testUploadSourceValidatesMeetingAndMotionIds(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ResolutionDocumentController.php');

        $this->assertStringContainsString("'missing_meeting_id'", $source);
        $this->assertStringContainsString("'missing_motion_id'", $source);
    }

    public function testUploadSourceChecksMimeType(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ResolutionDocumentController.php');

        $this->assertStringContainsString("'invalid_mime_type'", $source);
        $this->assertStringContainsString('application/pdf', $source);
    }

    public function testUploadSourceChecksFileSize(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ResolutionDocumentController.php');

        $this->assertStringContainsString("'file_too_large'", $source);
        $this->assertStringContainsString('10 * 1024 * 1024', $source);
    }

    public function testUploadSourceChecksExtension(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ResolutionDocumentController.php');

        $this->assertStringContainsString("'invalid_file_type'", $source);
        $this->assertStringContainsString('.pdf', $source);
    }

    // =========================================================================
    // upload -- EAGER REPO CONSTRUCTION IN TEST ENV
    // After passing method check, repo construction fires before validation.
    // =========================================================================

    public function testUploadEagerRepoThrowsBusinessError(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_id' => '87654321-4321-4321-4321-cba987654321',
        ]);

        $result = $this->callControllerMethod('upload');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    // =========================================================================
    // delete -- SOURCE-LEVEL VALIDATION
    // =========================================================================

    public function testDeleteSourceValidatesId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ResolutionDocumentController.php');

        $this->assertStringContainsString("api_request('DELETE')", $source);
        $this->assertStringContainsString("'missing_id'", $source);
        $this->assertStringContainsString('api_is_uuid', $source);
    }

    public function testDeleteSourceRemovesFileBeforeDbDelete(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ResolutionDocumentController.php');

        // file_exists and unlink should appear before $repo->delete
        $fileExistsPos = strpos($source, 'file_exists');
        $unlinkPos = strpos($source, 'unlink');
        $deletePos = strpos($source, '$repo->delete');

        $this->assertNotFalse($fileExistsPos, 'Source should check file_exists before deleting');
        $this->assertNotFalse($unlinkPos, 'Source should call unlink to remove file');
        $this->assertNotFalse($deletePos, 'Source should call $repo->delete');
        $this->assertLessThan($deletePos, $unlinkPos, 'unlink should occur before $repo->delete');
    }

    // =========================================================================
    // delete -- EAGER REPO CONSTRUCTION IN TEST ENV
    // =========================================================================

    public function testDeleteEagerRepoThrowsBusinessError(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_GET['id'] = '12345678-1234-1234-1234-123456789abc';
        $this->injectJsonBody(['id' => '12345678-1234-1234-1234-123456789abc']);

        $result = $this->callControllerMethod('delete');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    // =========================================================================
    // serve -- DUAL AUTH SOURCE VERIFICATION
    // =========================================================================

    public function testServeSourceSupportsDualAuth(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ResolutionDocumentController.php');

        $this->assertStringContainsString('api_current_user_id', $source);
        $this->assertStringContainsString("api_query('token')", $source);
        $this->assertStringContainsString('hash_hmac', $source);
        $this->assertStringContainsString('findByHash', $source);
    }

    public function testServeSourceValidatesId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ResolutionDocumentController.php');

        $this->assertStringContainsString("api_query('id')", $source);
        $this->assertStringContainsString('api_is_uuid', $source);
        $this->assertStringContainsString("'missing_id'", $source);
    }

    public function testServeSourceSetsSecurityHeaders(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ResolutionDocumentController.php');

        $this->assertStringContainsString('X-Content-Type-Options', $source);
        $this->assertStringContainsString('X-Frame-Options', $source);
        $this->assertStringContainsString('Cache-Control', $source);
    }

    public function testServeSourceChecksMeetingMatch(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ResolutionDocumentController.php');

        // Vote token auth verifies document belongs to token's meeting
        $this->assertStringContainsString("'access_denied'", $source);
    }

    // =========================================================================
    // AUDIT LOG VERIFICATION (source-level)
    // =========================================================================

    public function testUploadAuditsDocumentUpload(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ResolutionDocumentController.php');

        $this->assertStringContainsString("'resolution_document_uploaded'", $source);
    }

    public function testDeleteAuditsDocumentDeletion(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ResolutionDocumentController.php');

        $this->assertStringContainsString("'resolution_document_deleted'", $source);
    }

    // =========================================================================
    // SSE BROADCAST VERIFICATION (source-level)
    // =========================================================================

    public function testUploadBroadcastsDocumentAdded(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ResolutionDocumentController.php');

        $this->assertStringContainsString('EventBroadcaster::documentAdded', $source);
    }

    public function testDeleteBroadcastsDocumentRemoved(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ResolutionDocumentController.php');

        $this->assertStringContainsString('EventBroadcaster::documentRemoved', $source);
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
}
