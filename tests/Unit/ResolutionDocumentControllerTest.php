<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\ResolutionDocumentController;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\ResolutionDocumentRepository;
use AgVote\Repository\VoteTokenRepository;

/**
 * Unit tests for ResolutionDocumentController.
 *
 * Endpoints:
 *  - listForMotion(): GET  — list documents for a motion
 *  - upload():        POST — upload PDF document
 *  - delete():        DELETE — delete document
 *  - serve():         GET  — serve PDF file (exits after sending)
 *
 * Uses ControllerTestCase with mocked repos via RepositoryFactory injection.
 * The serve() endpoint calls readfile() + exit so only validation paths are tested.
 */
class ResolutionDocumentControllerTest extends ControllerTestCase
{
    private const TENANT_ID  = 'ffffffff-0000-1111-2222-333333333333';
    private const MOTION_ID  = 'aa000001-0000-4000-a000-000000000001';
    private const MEETING_ID = 'aa000002-0000-4000-a000-000000000002';
    private const DOC_ID     = 'aa000003-0000-4000-a000-000000000003';
    private const USER_ID    = 'aa000004-0000-4000-a000-000000000004';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setAuth(self::USER_ID, 'admin', self::TENANT_ID);
        $_FILES = [];
    }

    protected function tearDown(): void
    {
        $_FILES = [];
        parent::tearDown();
    }

    // =========================================================================
    // STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(ResolutionDocumentController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testControllerExtendsAbstractController(): void
    {
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, new ResolutionDocumentController());
    }

    public function testControllerHasExpectedMethods(): void
    {
        $ref = new \ReflectionClass(ResolutionDocumentController::class);
        foreach (['listForMotion', 'upload', 'delete', 'serve'] as $m) {
            $this->assertTrue($ref->hasMethod($m), "Missing method: {$m}");
        }
    }

    // =========================================================================
    // listForMotion()
    // =========================================================================

    public function testListForMotionMissingMotionId(): void
    {
        $this->setQueryParams([]);
        $docRepo = $this->createMock(ResolutionDocumentRepository::class);
        $this->injectRepos([ResolutionDocumentRepository::class => $docRepo]);

        $result = $this->callController(ResolutionDocumentController::class, 'listForMotion');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_motion_id', $result['body']['error']);
    }

    public function testListForMotionInvalidMotionId(): void
    {
        $this->setQueryParams(['motion_id' => 'not-uuid']);
        $docRepo = $this->createMock(ResolutionDocumentRepository::class);
        $this->injectRepos([ResolutionDocumentRepository::class => $docRepo]);

        $result = $this->callController(ResolutionDocumentController::class, 'listForMotion');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_motion_id', $result['body']['error']);
    }

    public function testListForMotionSuccess(): void
    {
        $this->setQueryParams(['motion_id' => self::MOTION_ID]);
        $docRepo = $this->createMock(ResolutionDocumentRepository::class);
        $docRepo->method('listForMotion')->willReturn([
            ['id' => self::DOC_ID, 'original_name' => 'doc.pdf', 'file_size' => 1024],
        ]);
        $this->injectRepos([ResolutionDocumentRepository::class => $docRepo]);

        $result = $this->callController(ResolutionDocumentController::class, 'listForMotion');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertArrayHasKey('documents', $data);
        $this->assertCount(1, $data['documents']);
    }

    public function testListForMotionEmpty(): void
    {
        $this->setQueryParams(['motion_id' => self::MOTION_ID]);
        $docRepo = $this->createMock(ResolutionDocumentRepository::class);
        $docRepo->method('listForMotion')->willReturn([]);
        $this->injectRepos([ResolutionDocumentRepository::class => $docRepo]);

        $result = $this->callController(ResolutionDocumentController::class, 'listForMotion');
        $this->assertEquals(200, $result['status']);
        $this->assertCount(0, $result['body']['data']['documents']);
    }

    // =========================================================================
    // upload()
    // =========================================================================

    public function testUploadRejectsGetMethod(): void
    {
        $this->setHttpMethod('GET');
        $result = $this->callController(ResolutionDocumentController::class, 'upload');
        $this->assertEquals(405, $result['status']);
    }

    public function testUploadMissingMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $docRepo = $this->createMock(ResolutionDocumentRepository::class);
        $motionRepo = $this->createMock(MotionRepository::class);
        $this->injectRepos([
            ResolutionDocumentRepository::class => $docRepo,
            MotionRepository::class             => $motionRepo,
        ]);

        $this->injectJsonBody(['motion_id' => self::MOTION_ID]);
        $result = $this->callController(ResolutionDocumentController::class, 'upload');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testUploadMissingMotionId(): void
    {
        $this->setHttpMethod('POST');
        $docRepo = $this->createMock(ResolutionDocumentRepository::class);
        $motionRepo = $this->createMock(MotionRepository::class);
        $this->injectRepos([
            ResolutionDocumentRepository::class => $docRepo,
            MotionRepository::class             => $motionRepo,
        ]);

        $this->injectJsonBody(['meeting_id' => self::MEETING_ID]);
        $result = $this->callController(ResolutionDocumentController::class, 'upload');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_motion_id', $result['body']['error']);
    }

    public function testUploadMotionNotFound(): void
    {
        $this->setHttpMethod('POST');
        $docRepo = $this->createMock(ResolutionDocumentRepository::class);
        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findByIdForTenant')->willReturn(null);
        $this->injectRepos([
            ResolutionDocumentRepository::class => $docRepo,
            MotionRepository::class             => $motionRepo,
        ]);

        $this->injectJsonBody(['meeting_id' => self::MEETING_ID, 'motion_id' => self::MOTION_ID]);
        $result = $this->callController(ResolutionDocumentController::class, 'upload');
        $this->assertEquals(404, $result['status']);
        $this->assertEquals('motion_not_found', $result['body']['error']);
    }

    public function testUploadNoFileReturnsError(): void
    {
        $this->setHttpMethod('POST');
        $docRepo = $this->createMock(ResolutionDocumentRepository::class);
        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findByIdForTenant')->willReturn([
            'id' => self::MOTION_ID, 'tenant_id' => self::TENANT_ID,
        ]);
        $this->injectRepos([
            ResolutionDocumentRepository::class => $docRepo,
            MotionRepository::class             => $motionRepo,
        ]);

        $this->injectJsonBody(['meeting_id' => self::MEETING_ID, 'motion_id' => self::MOTION_ID]);
        $_FILES = []; // no file
        $result = $this->callController(ResolutionDocumentController::class, 'upload');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('upload_error', $result['body']['error']);
    }

    // =========================================================================
    // delete()
    // =========================================================================

    public function testDeleteMissingId(): void
    {
        $docRepo = $this->createMock(ResolutionDocumentRepository::class);
        $this->injectRepos([ResolutionDocumentRepository::class => $docRepo]);

        $this->setHttpMethod('DELETE');
        $this->injectJsonBody(['id' => '']);
        $result = $this->callController(ResolutionDocumentController::class, 'delete');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_id', $result['body']['error']);
    }

    public function testDeleteInvalidId(): void
    {
        $docRepo = $this->createMock(ResolutionDocumentRepository::class);
        $this->injectRepos([ResolutionDocumentRepository::class => $docRepo]);

        $this->setHttpMethod('DELETE');
        $this->injectJsonBody(['id' => 'not-uuid']);
        $result = $this->callController(ResolutionDocumentController::class, 'delete');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_id', $result['body']['error']);
    }

    public function testDeleteDocumentNotFound(): void
    {
        $docRepo = $this->createMock(ResolutionDocumentRepository::class);
        $docRepo->method('findById')->willReturn(null);
        $this->injectRepos([ResolutionDocumentRepository::class => $docRepo]);

        $this->setHttpMethod('DELETE');
        $this->injectJsonBody(['id' => self::DOC_ID]);
        $result = $this->callController(ResolutionDocumentController::class, 'delete');
        $this->assertEquals(404, $result['status']);
        $this->assertEquals('not_found', $result['body']['error']);
    }

    public function testDeleteSuccessNoPhysicalFile(): void
    {
        $docRepo = $this->createMock(ResolutionDocumentRepository::class);
        $docRepo->method('findById')->willReturn([
            'id'           => self::DOC_ID,
            'motion_id'    => self::MOTION_ID,
            'meeting_id'   => self::MEETING_ID,
            'stored_name'  => 'doc.pdf',
            'original_name' => 'my-doc.pdf',
            'tenant_id'    => self::TENANT_ID,
        ]);
        $docRepo->method('delete')->willReturn(1);
        $this->injectRepos([ResolutionDocumentRepository::class => $docRepo]);

        $this->setHttpMethod('DELETE');
        $this->injectJsonBody(['id' => self::DOC_ID]);
        $result = $this->callController(ResolutionDocumentController::class, 'delete');
        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['body']['data']['deleted']);
    }

    // =========================================================================
    // serve() — validation only (serve exits after readfile)
    // =========================================================================

    public function testServeMissingId(): void
    {
        $this->setQueryParams([]);
        $docRepo = $this->createMock(ResolutionDocumentRepository::class);
        $this->injectRepos([ResolutionDocumentRepository::class => $docRepo]);

        $result = $this->callController(ResolutionDocumentController::class, 'serve');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_id', $result['body']['error']);
    }

    public function testServeInvalidId(): void
    {
        $this->setQueryParams(['id' => 'bad']);
        $docRepo = $this->createMock(ResolutionDocumentRepository::class);
        $this->injectRepos([ResolutionDocumentRepository::class => $docRepo]);

        $result = $this->callController(ResolutionDocumentController::class, 'serve');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_id', $result['body']['error']);
    }

    public function testServeWithSessionUserDocNotFound(): void
    {
        $this->setQueryParams(['id' => self::DOC_ID]);
        $docRepo = $this->createMock(ResolutionDocumentRepository::class);
        $docRepo->method('findById')->willReturn(null);
        $this->injectRepos([ResolutionDocumentRepository::class => $docRepo]);

        $result = $this->callController(ResolutionDocumentController::class, 'serve');
        $this->assertEquals(404, $result['status']);
        $this->assertEquals('not_found', $result['body']['error']);
    }

    public function testServeWithNoAuthRequiresToken(): void
    {
        // Enable real auth so authenticate() does not auto-fill a dev user.
        // With auth enabled and no session/API-key, getCurrentUserId() returns null
        // and the controller falls through to the token-check branch.
        putenv('APP_AUTH_ENABLED=1');
        \AgVote\Core\Security\AuthMiddleware::reset();
        $this->setQueryParams(['id' => self::DOC_ID]);
        $docRepo = $this->createMock(ResolutionDocumentRepository::class);
        $this->injectRepos([ResolutionDocumentRepository::class => $docRepo]);

        try {
            $result = $this->callController(ResolutionDocumentController::class, 'serve');
        } finally {
            putenv('APP_AUTH_ENABLED=0');
            \AgVote\Core\Security\AuthMiddleware::reset();
        }
        $this->assertEquals(401, $result['status']);
        $this->assertEquals('authentication_required', $result['body']['error']);
    }

    public function testServeWithInvalidToken(): void
    {
        putenv('APP_AUTH_ENABLED=1');
        \AgVote\Core\Security\AuthMiddleware::reset();
        $this->setQueryParams(['id' => self::DOC_ID, 'token' => 'fake-token']);

        $docRepo = $this->createMock(ResolutionDocumentRepository::class);
        $voteTokenRepo = $this->createMock(VoteTokenRepository::class);
        $voteTokenRepo->method('findByHash')->willReturn(null);
        $this->injectRepos([
            ResolutionDocumentRepository::class => $docRepo,
            VoteTokenRepository::class          => $voteTokenRepo,
        ]);

        try {
            $result = $this->callController(ResolutionDocumentController::class, 'serve');
        } finally {
            putenv('APP_AUTH_ENABLED=0');
            \AgVote\Core\Security\AuthMiddleware::reset();
        }
        $this->assertEquals(401, $result['status']);
        $this->assertEquals('invalid_token', $result['body']['error']);
    }

    public function testServeWithValidTokenButDocNotFound(): void
    {
        putenv('APP_AUTH_ENABLED=1');
        \AgVote\Core\Security\AuthMiddleware::reset();
        $this->setQueryParams(['id' => self::DOC_ID, 'token' => 'valid-vote-token']);

        $docRepo = $this->createMock(ResolutionDocumentRepository::class);
        $docRepo->method('findById')->willReturn(null);
        $voteTokenRepo = $this->createMock(VoteTokenRepository::class);
        $voteTokenRepo->method('findByHash')->willReturn([
            'tenant_id'  => self::TENANT_ID,
            'meeting_id' => self::MEETING_ID,
        ]);
        $this->injectRepos([
            ResolutionDocumentRepository::class => $docRepo,
            VoteTokenRepository::class          => $voteTokenRepo,
        ]);

        try {
            $result = $this->callController(ResolutionDocumentController::class, 'serve');
        } finally {
            putenv('APP_AUTH_ENABLED=0');
            \AgVote\Core\Security\AuthMiddleware::reset();
        }
        $this->assertEquals(404, $result['status']);
        $this->assertEquals('not_found', $result['body']['error']);
    }

    public function testServeWithTokenWrongMeeting(): void
    {
        putenv('APP_AUTH_ENABLED=1');
        \AgVote\Core\Security\AuthMiddleware::reset();
        $this->setQueryParams(['id' => self::DOC_ID, 'token' => 'valid-vote-token']);

        $docRepo = $this->createMock(ResolutionDocumentRepository::class);
        $docRepo->method('findById')->willReturn([
            'id'          => self::DOC_ID,
            'motion_id'   => self::MOTION_ID,
            'meeting_id'  => 'bb000001-0000-4000-b000-000000000001', // different meeting
            'stored_name' => 'doc.pdf',
            'original_name' => 'doc.pdf',
            'file_size'   => 1024,
        ]);
        $voteTokenRepo = $this->createMock(VoteTokenRepository::class);
        $voteTokenRepo->method('findByHash')->willReturn([
            'tenant_id'  => self::TENANT_ID,
            'meeting_id' => self::MEETING_ID, // token meeting != doc meeting
        ]);
        $this->injectRepos([
            ResolutionDocumentRepository::class => $docRepo,
            VoteTokenRepository::class          => $voteTokenRepo,
        ]);

        try {
            $result = $this->callController(ResolutionDocumentController::class, 'serve');
        } finally {
            putenv('APP_AUTH_ENABLED=0');
            \AgVote\Core\Security\AuthMiddleware::reset();
        }
        $this->assertEquals(403, $result['status']);
        $this->assertEquals('access_denied', $result['body']['error']);
    }

    // =========================================================================
    // serve() — happy path (requires FileServedOkException support in controller)
    // =========================================================================

    public function testServeSuccessWithSessionUser(): void
    {
        $this->setAuth(self::USER_ID, 'admin', self::TENANT_ID);
        $this->setQueryParams(['id' => self::DOC_ID]);

        // Create a real temp file under the expected path structure:
        // AG_UPLOAD_DIR . '/resolutions/' . MOTION_ID . '/' . storedName
        $dir = AG_UPLOAD_DIR . '/resolutions/' . self::MOTION_ID;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $storedName = 'testdoc_75_01.pdf';
        $filePath = $dir . '/' . $storedName;
        file_put_contents($filePath, 'dummy pdf content');

        try {
            $doc = [
                'id'            => self::DOC_ID,
                'motion_id'     => self::MOTION_ID,
                'meeting_id'    => self::MEETING_ID,
                'stored_name'   => $storedName,
                'original_name' => 'resolution.pdf',
                'file_size'     => 17,
            ];

            $docRepo = $this->createMock(ResolutionDocumentRepository::class);
            $docRepo->method('findById')->willReturn($doc);
            $this->injectRepos([ResolutionDocumentRepository::class => $docRepo]);

            // Call serve() directly (not via handle()) to bypass RuntimeException catch in AbstractController
            $this->expectException(\AgVote\Controller\FileServedOkException::class);
            $controller = new ResolutionDocumentController();
            $controller->serve();
        } finally {
            @unlink($filePath);
            @rmdir($dir);
        }
    }
}
