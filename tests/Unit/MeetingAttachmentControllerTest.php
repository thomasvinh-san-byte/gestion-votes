<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\MeetingAttachmentController;
use AgVote\Repository\MeetingAttachmentRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\VoteTokenRepository;

/**
 * Unit tests for MeetingAttachmentController.
 *
 * Endpoints:
 *  - listForMeeting(): GET    — list attachments for a meeting
 *  - upload():         POST   — upload a PDF attachment (requires file)
 *  - delete():         DELETE — delete an attachment
 *
 * Note: upload() uses finfo, move_uploaded_file(), filesystem ops.
 * Only early validation paths (pre-file-access) can be tested without mocking
 * the filesystem. File-related paths require integration environment.
 *
 * Response structure:
 *   - api_ok($data)  => body['data'][...] (wrapped in 'data' key)
 *   - api_fail(...)  => body['error'] (direct)
 *
 * Pattern: extends ControllerTestCase, uses injectRepos() + callController().
 */
class MeetingAttachmentControllerTest extends ControllerTestCase
{
    private const TENANT     = 'tenant-uuid-001';
    private const MEETING_ID = 'aaaaaaaa-1111-2222-3333-000000000070';
    private const ATTACH_ID  = 'bbbbbbbb-1111-2222-3333-000000000070';
    private const USER_ID    = 'user-uuid-0070';

    // =========================================================================
    // CONTROLLER STRUCTURE
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(MeetingAttachmentController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testControllerHasRequiredMethods(): void
    {
        foreach (['listForMeeting', 'upload', 'delete', 'serve'] as $method) {
            $this->assertTrue(method_exists(MeetingAttachmentController::class, $method));
        }
    }

    // =========================================================================
    // listForMeeting() — GET
    // =========================================================================

    public function testListForMeetingMissingIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $repo = $this->createMock(MeetingAttachmentRepository::class);
        $this->injectRepos([MeetingAttachmentRepository::class => $repo]);

        $res = $this->callController(MeetingAttachmentController::class, 'listForMeeting');

        $this->assertSame(400, $res['status']);
        $this->assertSame('missing_meeting_id', $res['body']['error']);
    }

    public function testListForMeetingInvalidUuidReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => 'bad-uuid']);

        $repo = $this->createMock(MeetingAttachmentRepository::class);
        $this->injectRepos([MeetingAttachmentRepository::class => $repo]);

        $res = $this->callController(MeetingAttachmentController::class, 'listForMeeting');

        $this->assertSame(400, $res['status']);
        $this->assertSame('missing_meeting_id', $res['body']['error']);
    }

    public function testListForMeetingReturnsAttachments(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $repo = $this->createMock(MeetingAttachmentRepository::class);
        $repo->method('listForMeeting')->willReturn([
            ['id' => self::ATTACH_ID, 'original_name' => 'convocation.pdf'],
        ]);

        $this->injectRepos([MeetingAttachmentRepository::class => $repo]);

        $res = $this->callController(MeetingAttachmentController::class, 'listForMeeting');

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['body']['data']['attachments']);
        $this->assertSame(self::ATTACH_ID, $res['body']['data']['attachments'][0]['id']);
    }

    public function testListForMeetingReturnsEmptyArray(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $repo = $this->createMock(MeetingAttachmentRepository::class);
        $repo->method('listForMeeting')->willReturn([]);

        $this->injectRepos([MeetingAttachmentRepository::class => $repo]);

        $res = $this->callController(MeetingAttachmentController::class, 'listForMeeting');

        $this->assertSame(200, $res['status']);
        $this->assertSame([], $res['body']['data']['attachments']);
    }

    // =========================================================================
    // upload() — POST (early validation paths only)
    // File processing (finfo, move_uploaded_file) requires real environment.
    // =========================================================================

    public function testUploadRequiresPost(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');

        $repo = $this->createMock(MeetingAttachmentRepository::class);
        $this->injectRepos([MeetingAttachmentRepository::class => $repo]);

        $res = $this->callController(MeetingAttachmentController::class, 'upload');

        $this->assertSame(405, $res['status']);
    }

    public function testUploadMissingMeetingIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $repo = $this->createMock(MeetingAttachmentRepository::class);
        $this->injectRepos([MeetingAttachmentRepository::class => $repo]);

        $res = $this->callController(MeetingAttachmentController::class, 'upload');

        $this->assertSame(400, $res['status']);
        $this->assertSame('missing_meeting_id', $res['body']['error']);
    }

    public function testUploadMeetingNotFoundReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => self::MEETING_ID]);

        $attachRepo = $this->createMock(MeetingAttachmentRepository::class);
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('existsForTenant')->willReturn(false);

        $this->injectRepos([
            MeetingAttachmentRepository::class => $attachRepo,
            MeetingRepository::class           => $meetingRepo,
        ]);

        $res = $this->callController(MeetingAttachmentController::class, 'upload');

        $this->assertSame(404, $res['status']);
        $this->assertSame('meeting_not_found', $res['body']['error']);
    }

    public function testUploadNoFileReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => self::MEETING_ID]);

        // No file in $_FILES — api_file() returns null
        $_FILES = [];

        $attachRepo = $this->createMock(MeetingAttachmentRepository::class);
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('existsForTenant')->willReturn(true);

        $this->injectRepos([
            MeetingAttachmentRepository::class => $attachRepo,
            MeetingRepository::class           => $meetingRepo,
        ]);

        $res = $this->callController(MeetingAttachmentController::class, 'upload');

        $this->assertSame(400, $res['status']);
        $this->assertSame('upload_error', $res['body']['error']);
    }

    // =========================================================================
    // delete() — DELETE
    // =========================================================================

    public function testDeleteRequiresDelete(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');

        $repo = $this->createMock(MeetingAttachmentRepository::class);
        $this->injectRepos([MeetingAttachmentRepository::class => $repo]);

        $res = $this->callController(MeetingAttachmentController::class, 'delete');

        $this->assertSame(405, $res['status']);
    }

    public function testDeleteMissingIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('DELETE');
        $this->injectJsonBody([]);

        $repo = $this->createMock(MeetingAttachmentRepository::class);
        $this->injectRepos([MeetingAttachmentRepository::class => $repo]);

        $res = $this->callController(MeetingAttachmentController::class, 'delete');

        $this->assertSame(400, $res['status']);
        $this->assertSame('missing_id', $res['body']['error']);
    }

    public function testDeleteNotFoundReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('DELETE');
        $this->injectJsonBody(['id' => self::ATTACH_ID]);

        $repo = $this->createMock(MeetingAttachmentRepository::class);
        $repo->method('findById')->willReturn(null);

        $this->injectRepos([MeetingAttachmentRepository::class => $repo]);

        $res = $this->callController(MeetingAttachmentController::class, 'delete');

        $this->assertSame(404, $res['status']);
        $this->assertSame('not_found', $res['body']['error']);
    }

    public function testDeleteSucceeds(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('DELETE');
        $this->injectJsonBody(['id' => self::ATTACH_ID]);

        // Use a non-existent file path so unlink is skipped
        $att = [
            'id'            => self::ATTACH_ID,
            'meeting_id'    => self::MEETING_ID,
            'stored_name'   => 'nonexistent-file.pdf',
            'original_name' => 'convocation.pdf',
        ];

        $repo = $this->createMock(MeetingAttachmentRepository::class);
        $repo->method('findById')->willReturn($att);
        $repo->expects($this->once())->method('delete');

        $this->injectRepos([MeetingAttachmentRepository::class => $repo]);

        $res = $this->callController(MeetingAttachmentController::class, 'delete');

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['body']['data']['deleted']);
    }

    // =========================================================================
    // serve() — validation only (serve exits after readfile)
    // =========================================================================

    public function testServeMissingId(): void
    {
        $this->setQueryParams([]);
        $repo = $this->createMock(MeetingAttachmentRepository::class);
        $this->injectRepos([MeetingAttachmentRepository::class => $repo]);

        $result = $this->callController(MeetingAttachmentController::class, 'serve');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_id', $result['body']['error']);
    }

    public function testServeInvalidId(): void
    {
        $this->setQueryParams(['id' => 'bad']);
        $repo = $this->createMock(MeetingAttachmentRepository::class);
        $this->injectRepos([MeetingAttachmentRepository::class => $repo]);

        $result = $this->callController(MeetingAttachmentController::class, 'serve');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_id', $result['body']['error']);
    }

    public function testServeWithSessionUserDocNotFound(): void
    {
        $this->setQueryParams(['id' => self::ATTACH_ID]);
        $repo = $this->createMock(MeetingAttachmentRepository::class);
        $repo->method('findById')->willReturn(null);
        $this->injectRepos([MeetingAttachmentRepository::class => $repo]);

        $result = $this->callController(MeetingAttachmentController::class, 'serve');
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
        $this->setQueryParams(['id' => self::ATTACH_ID]);
        $repo = $this->createMock(MeetingAttachmentRepository::class);
        $this->injectRepos([MeetingAttachmentRepository::class => $repo]);

        try {
            $result = $this->callController(MeetingAttachmentController::class, 'serve');
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
        $this->setQueryParams(['id' => self::ATTACH_ID, 'token' => 'fake-token']);

        $repo = $this->createMock(MeetingAttachmentRepository::class);
        $voteTokenRepo = $this->createMock(VoteTokenRepository::class);
        $voteTokenRepo->method('findByHash')->willReturn(null);
        $this->injectRepos([
            MeetingAttachmentRepository::class => $repo,
            VoteTokenRepository::class         => $voteTokenRepo,
        ]);

        try {
            $result = $this->callController(MeetingAttachmentController::class, 'serve');
        } finally {
            putenv('APP_AUTH_ENABLED=0');
            \AgVote\Core\Security\AuthMiddleware::reset();
        }
        $this->assertEquals(401, $result['status']);
        $this->assertEquals('invalid_token', $result['body']['error']);
    }

    public function testServeWithValidTokenButAttachmentNotFound(): void
    {
        putenv('APP_AUTH_ENABLED=1');
        \AgVote\Core\Security\AuthMiddleware::reset();
        $this->setQueryParams(['id' => self::ATTACH_ID, 'token' => 'valid-vote-token']);

        $repo = $this->createMock(MeetingAttachmentRepository::class);
        $repo->method('findById')->willReturn(null);
        $voteTokenRepo = $this->createMock(VoteTokenRepository::class);
        $voteTokenRepo->method('findByHash')->willReturn([
            'tenant_id'  => self::TENANT,
            'meeting_id' => self::MEETING_ID,
        ]);
        $this->injectRepos([
            MeetingAttachmentRepository::class => $repo,
            VoteTokenRepository::class         => $voteTokenRepo,
        ]);

        try {
            $result = $this->callController(MeetingAttachmentController::class, 'serve');
        } finally {
            putenv('APP_AUTH_ENABLED=0');
            \AgVote\Core\Security\AuthMiddleware::reset();
        }
        $this->assertEquals(404, $result['status']);
        $this->assertEquals('not_found', $result['body']['error']);
    }

    public function testServeWithTokenForWrongMeeting(): void
    {
        putenv('APP_AUTH_ENABLED=1');
        \AgVote\Core\Security\AuthMiddleware::reset();
        $this->setQueryParams(['id' => self::ATTACH_ID, 'token' => 'valid-vote-token']);

        $repo = $this->createMock(MeetingAttachmentRepository::class);
        $repo->method('findById')->willReturn([
            'id'           => self::ATTACH_ID,
            'meeting_id'   => self::MEETING_ID,
            'stored_name'  => 'att.pdf',
            'original_name' => 'convocation.pdf',
            'file_size'    => 1024,
        ]);
        $voteTokenRepo = $this->createMock(VoteTokenRepository::class);
        $voteTokenRepo->method('findByHash')->willReturn([
            'tenant_id'  => self::TENANT,
            'meeting_id' => 'different-meeting-id', // token meeting != attachment meeting
        ]);
        $this->injectRepos([
            MeetingAttachmentRepository::class => $repo,
            VoteTokenRepository::class         => $voteTokenRepo,
        ]);

        try {
            $result = $this->callController(MeetingAttachmentController::class, 'serve');
        } finally {
            putenv('APP_AUTH_ENABLED=0');
            \AgVote\Core\Security\AuthMiddleware::reset();
        }
        $this->assertEquals(403, $result['status']);
        $this->assertEquals('access_denied', $result['body']['error']);
    }
}
