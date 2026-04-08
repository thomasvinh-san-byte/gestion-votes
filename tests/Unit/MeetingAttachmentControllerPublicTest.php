<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\FileServedOkException;
use AgVote\Controller\MeetingAttachmentController;
use AgVote\Repository\MeetingAttachmentRepository;
use AgVote\Repository\VoteTokenRepository;

/**
 * Execution-level integration tests for MeetingAttachmentController::listPublic() and serve().
 *
 * Covers the dual-auth contract:
 *   - Session user (operator/admin) authenticated via AuthMiddleware
 *   - Vote token holder authenticated via ?token= query param
 *
 * Also verifies the security invariant: stored_name MUST NEVER appear in
 * the listPublic response payload (filesystem path leak prevention).
 *
 * serve() is tested via FileServedOkException which the controller throws
 * instead of readfile()+exit() when PHPUNIT_RUNNING === true.
 */
class MeetingAttachmentControllerPublicTest extends ControllerTestCase
{
    private const TENANT     = 'tenant-uuid-public-001';
    private const MEETING_ID = 'cccccccc-1111-2222-3333-000000000011';
    private const ATTACH_ID  = 'dddddddd-1111-2222-3333-000000000011';
    private const USER_ID    = 'user-uuid-public-001';

    /** Temp file created for serve happy-path tests. */
    private ?string $tempFilePath = null;
    /** Temp directory created for serve happy-path tests. */
    private ?string $tempDir = null;

    protected function tearDown(): void
    {
        if ($this->tempFilePath !== null && file_exists($this->tempFilePath)) {
            @unlink($this->tempFilePath);
        }
        if ($this->tempDir !== null && is_dir($this->tempDir)) {
            @rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    // =========================================================================
    // listPublic() — session auth path
    // =========================================================================

    /**
     * Session-authenticated operator gets the attachment list.
     * Two rows are returned; stored_name must not appear in the payload.
     */
    public function testListPublicSessionUserReturnsAttachmentList(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $repo = $this->createMock(MeetingAttachmentRepository::class);
        $repo->method('listForMeeting')->willReturn([
            [
                'id'            => self::ATTACH_ID,
                'original_name' => 'reglement.pdf',
                'file_size'     => 12345,
                'created_at'    => '2026-04-07T10:00:00Z',
                'stored_name'   => 'abc.pdf',
                'meeting_id'    => self::MEETING_ID,
            ],
            [
                'id'            => 'eeeeeeee-1111-2222-3333-000000000022',
                'original_name' => 'pv_precedent.pdf',
                'file_size'     => 67890,
                'created_at'    => '2026-04-07T11:00:00Z',
                'stored_name'   => 'def.pdf',
                'meeting_id'    => self::MEETING_ID,
            ],
        ]);
        $this->injectRepos([MeetingAttachmentRepository::class => $repo]);

        $result = $this->callController(MeetingAttachmentController::class, 'listPublic');

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['body']['ok'] ?? ($result['body']['data'] !== null));
        $attachments = $result['body']['data']['attachments'];
        $this->assertCount(2, $attachments);
        $this->assertSame('reglement.pdf', $attachments[0]['original_name']);
        $this->assertSame(12345, $attachments[0]['file_size']);
        $this->assertArrayNotHasKey(
            'stored_name',
            $attachments[0],
            'stored_name must NEVER leak to clients (filesystem path leak)',
        );
        $this->assertSame('pv_precedent.pdf', $attachments[1]['original_name']);
    }

    // =========================================================================
    // listPublic() — vote token auth path
    // =========================================================================

    /**
     * Vote token holder (no session) gets the attachment list.
     * Token's meeting_id must match the requested meeting_id.
     */
    public function testListPublicTokenUserReturnsAttachmentList(): void
    {
        putenv('APP_AUTH_ENABLED=1');
        \AgVote\Core\Security\AuthMiddleware::reset();

        $rawToken   = 'plain-token-xyz';
        $tokenHash  = hash_hmac('sha256', $rawToken, APP_SECRET);
        $tokenTenant = 'tenant-from-token';

        $this->setQueryParams(['meeting_id' => self::MEETING_ID, 'token' => $rawToken]);

        $attachRepo = $this->createMock(MeetingAttachmentRepository::class);
        $attachRepo->method('listForMeeting')->willReturn([
            [
                'id'            => self::ATTACH_ID,
                'original_name' => 'reglement.pdf',
                'file_size'     => 12345,
                'created_at'    => '2026-04-07T10:00:00Z',
                'stored_name'   => 'abc.pdf',
                'meeting_id'    => self::MEETING_ID,
            ],
        ]);

        $voteTokenRepo = $this->createMock(VoteTokenRepository::class);
        $voteTokenRepo
            ->method('findByHash')
            ->with($tokenHash)
            ->willReturn([
                'meeting_id' => self::MEETING_ID,
                'tenant_id'  => $tokenTenant,
            ]);

        $this->injectRepos([
            MeetingAttachmentRepository::class => $attachRepo,
            VoteTokenRepository::class         => $voteTokenRepo,
        ]);

        try {
            $result = $this->callController(MeetingAttachmentController::class, 'listPublic');
        } finally {
            putenv('APP_AUTH_ENABLED=0');
            \AgVote\Core\Security\AuthMiddleware::reset();
        }

        $this->assertSame(200, $result['status']);
        $attachments = $result['body']['data']['attachments'];
        $this->assertCount(1, $attachments);
        $this->assertSame('reglement.pdf', $attachments[0]['original_name']);
        $this->assertArrayNotHasKey(
            'stored_name',
            $attachments[0],
            'stored_name must NEVER leak to clients (filesystem path leak)',
        );
    }

    // =========================================================================
    // listPublic() — access_denied when token meeting_id != query meeting_id
    // =========================================================================

    /**
     * Vote token's meeting_id does not match the requested meeting_id → 403.
     */
    public function testListPublicTokenWrongMeetingReturns403(): void
    {
        putenv('APP_AUTH_ENABLED=1');
        \AgVote\Core\Security\AuthMiddleware::reset();

        $this->setQueryParams(['meeting_id' => self::MEETING_ID, 'token' => 'some-valid-token']);

        $attachRepo = $this->createMock(MeetingAttachmentRepository::class);
        $voteTokenRepo = $this->createMock(VoteTokenRepository::class);
        $voteTokenRepo->method('findByHash')->willReturn([
            'meeting_id' => 'ffffffff-9999-9999-9999-999999999999', // different meeting
            'tenant_id'  => self::TENANT,
        ]);

        $this->injectRepos([
            MeetingAttachmentRepository::class => $attachRepo,
            VoteTokenRepository::class         => $voteTokenRepo,
        ]);

        try {
            $result = $this->callController(MeetingAttachmentController::class, 'listPublic');
        } finally {
            putenv('APP_AUTH_ENABLED=0');
            \AgVote\Core\Security\AuthMiddleware::reset();
        }

        $this->assertSame(403, $result['status']);
        $this->assertSame('access_denied', $result['body']['error']);
    }

    // =========================================================================
    // listPublic() — no auth at all → 401
    // =========================================================================

    /**
     * No session and no token → authentication_required 401.
     */
    public function testListPublicNoAuthReturns401(): void
    {
        putenv('APP_AUTH_ENABLED=1');
        \AgVote\Core\Security\AuthMiddleware::reset();

        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $attachRepo = $this->createMock(MeetingAttachmentRepository::class);
        $this->injectRepos([MeetingAttachmentRepository::class => $attachRepo]);

        try {
            $result = $this->callController(MeetingAttachmentController::class, 'listPublic');
        } finally {
            putenv('APP_AUTH_ENABLED=0');
            \AgVote\Core\Security\AuthMiddleware::reset();
        }

        $this->assertSame(401, $result['status']);
        $this->assertSame('authentication_required', $result['body']['error']);
    }

    // =========================================================================
    // serve() — happy path: FileServedOkException with correct properties
    // =========================================================================

    /**
     * Session user + valid attachment + physical file present → FileServedOkException
     * containing the expected filename, mime-type, and size.
     */
    public function testServeSessionUserThrowsFileServedOk(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setQueryParams(['id' => self::ATTACH_ID]);

        // Build the real temp file the controller will resolve
        $uploadDir = AG_UPLOAD_DIR;
        $this->tempDir  = $uploadDir . '/meetings/' . self::MEETING_ID;
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
        $storedName = 'xyz.pdf';
        $this->tempFilePath = $this->tempDir . '/' . $storedName;
        // Write 9999 bytes of dummy PDF data (magic bytes + padding)
        file_put_contents(
            $this->tempFilePath,
            '%PDF-1.4' . str_repeat('x', 9991),
        );

        $repo = $this->createMock(MeetingAttachmentRepository::class);
        $repo->method('findById')->willReturn([
            'id'            => self::ATTACH_ID,
            'meeting_id'    => self::MEETING_ID,
            'stored_name'   => $storedName,
            'original_name' => 'reglement.pdf',
            'file_size'     => 9999,
        ]);
        $this->injectRepos([MeetingAttachmentRepository::class => $repo]);

        // serve() calls readfile()+exit() in production; in test mode it throws
        // FileServedOkException — call the method directly (not via handle())
        // so the exception propagates to the test.
        $caughtEx = null;
        try {
            $controller = new MeetingAttachmentController();
            $controller->serve();
            $this->fail('Expected FileServedOkException was not thrown');
        } catch (FileServedOkException $ex) {
            $caughtEx = $ex;
        }

        $this->assertNotNull($caughtEx);
        $this->assertSame('reglement.pdf', $caughtEx->getFilename());
        $this->assertSame(9999, $caughtEx->getFileSize());
        $this->assertSame('application/pdf', $caughtEx->getContentType());
    }

    // =========================================================================
    // serve() — file missing on disk → 404
    // =========================================================================

    /**
     * Repo returns a valid attachment row but the physical file does not exist → 404.
     */
    public function testServeFileMissingReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setQueryParams(['id' => self::ATTACH_ID]);

        $repo = $this->createMock(MeetingAttachmentRepository::class);
        $repo->method('findById')->willReturn([
            'id'            => self::ATTACH_ID,
            'meeting_id'    => self::MEETING_ID,
            'stored_name'   => 'does-not-exist.pdf',
            'original_name' => 'missing.pdf',
            'file_size'     => 0,
        ]);
        $this->injectRepos([MeetingAttachmentRepository::class => $repo]);

        // The physical file does NOT exist — controller should return file_not_found 404
        $result = $this->callController(MeetingAttachmentController::class, 'serve');

        $this->assertSame(404, $result['status']);
        $this->assertSame('file_not_found', $result['body']['error']);
    }
}
