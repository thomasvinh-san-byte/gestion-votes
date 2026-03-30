<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\EmailController;
use AgVote\Repository\InvitationRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;

/**
 * Unit tests for EmailController.
 *
 * Endpoints:
 *  - preview():  POST — render template preview (uses EmailTemplateService)
 *  - schedule(): POST — queue invitations (uses EmailQueueService)
 *  - sendBulk(): POST — send bulk email (uses MailerService + repos)
 *
 * Uses ControllerTestCase with mocked repos via RepositoryFactory injection.
 * sendBulk() validation tests mock repos to avoid DB calls. Happy paths
 * require SMTP configuration so only dry_run=true paths are exercised.
 */
class EmailControllerTest extends ControllerTestCase
{
    private const TENANT_ID  = 'ffffffff-0000-1111-2222-333333333333';
    private const MEETING_ID = 'aa000001-0000-4000-a000-000000000001';
    private const USER_ID    = 'aa000002-0000-4000-a000-000000000002';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setAuth(self::USER_ID, 'admin', self::TENANT_ID);
    }

    // =========================================================================
    // STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(EmailController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testControllerExtendsAbstractController(): void
    {
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, new EmailController());
    }

    public function testControllerHasExpectedMethods(): void
    {
        $ref = new \ReflectionClass(EmailController::class);
        foreach (['preview', 'schedule', 'sendBulk'] as $m) {
            $this->assertTrue($ref->hasMethod($m), "Missing method: {$m}");
        }
    }

    // =========================================================================
    // preview()
    // =========================================================================

    public function testPreviewRejectsGetMethod(): void
    {
        $this->setHttpMethod('GET');
        $result = $this->callController(EmailController::class, 'preview');
        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testPreviewRejectsPutMethod(): void
    {
        $this->setHttpMethod('PUT');
        $result = $this->callController(EmailController::class, 'preview');
        $this->assertEquals(405, $result['status']);
    }

    public function testPreviewRequiresBodyHtml(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);
        $result = $this->callController(EmailController::class, 'preview');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_body_html', $result['body']['error']);
    }

    public function testPreviewRejectsEmptyBodyHtml(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['body_html' => '']);
        $result = $this->callController(EmailController::class, 'preview');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_body_html', $result['body']['error']);
    }

    public function testPreviewRejectsWhitespaceBodyHtml(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['body_html' => '   ']);
        $result = $this->callController(EmailController::class, 'preview');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_body_html', $result['body']['error']);
    }

    public function testPreviewResponseStructure(): void
    {
        // preview() creates an EmailTemplateService inline (not injectable via repos),
        // so the happy path requires a real DB. Verify response keys via source inspection.
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailController.php');
        foreach (['preview_html', 'preview_subject', 'validation', 'available_variables'] as $key) {
            $this->assertStringContainsString("'{$key}'", $source);
        }
    }

    // =========================================================================
    // schedule()
    // =========================================================================

    public function testScheduleRejectsGetMethod(): void
    {
        $this->setHttpMethod('GET');
        $result = $this->callController(EmailController::class, 'schedule');
        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testScheduleRequiresMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);
        $result = $this->callController(EmailController::class, 'schedule');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testScheduleRejectsInvalidMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => 'not-a-uuid']);
        $result = $this->callController(EmailController::class, 'schedule');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testScheduleRejectsInvalidTemplateId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id'  => self::MEETING_ID,
            'template_id' => 'bad-template-id',
        ]);
        $result = $this->callController(EmailController::class, 'schedule');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_template_id', $result['body']['error']);
    }

    public function testScheduleRejectsInvalidScheduledAt(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id'   => self::MEETING_ID,
            'scheduled_at' => '15/06/2025', // invalid format
        ]);
        $result = $this->callController(EmailController::class, 'schedule');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_scheduled_at', $result['body']['error']);
    }

    // =========================================================================
    // sendBulk()
    // =========================================================================

    public function testSendBulkRejectsGetMethod(): void
    {
        $this->setHttpMethod('GET');
        $result = $this->callController(EmailController::class, 'sendBulk');
        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testSendBulkRequiresMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);
        $result = $this->callController(EmailController::class, 'sendBulk');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testSendBulkRejectsInvalidMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => 'bad-uuid']);
        $result = $this->callController(EmailController::class, 'sendBulk');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testSendBulkDryRunWithEmptyMemberList(): void
    {
        $this->setHttpMethod('POST');

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findTitle')->willReturn('Test Meeting');

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('listActiveWithEmail')->willReturn([]);

        $invitationRepo = $this->createMock(InvitationRepository::class);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            MemberRepository::class     => $memberRepo,
            InvitationRepository::class => $invitationRepo,
        ]);

        $this->injectJsonBody([
            'meeting_id' => self::MEETING_ID,
            'dry_run'    => true,
        ]);

        $result = $this->callController(EmailController::class, 'sendBulk');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertEquals(self::MEETING_ID, $data['meeting_id']);
        $this->assertTrue($data['dry_run']);
        $this->assertEquals(0, $data['sent']);
        $this->assertEquals(0, $data['skipped']);
    }

    public function testSendBulkDryRunSkipsMembersWithoutEmail(): void
    {
        $this->setHttpMethod('POST');

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findTitle')->willReturn('My Meeting');

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('listActiveWithEmail')->willReturn([
            ['id' => 'aa000010-0000-4000-a000-000000000010', 'full_name' => 'No Email', 'email' => ''],
        ]);

        $invitationRepo = $this->createMock(InvitationRepository::class);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            MemberRepository::class     => $memberRepo,
            InvitationRepository::class => $invitationRepo,
        ]);

        $this->injectJsonBody([
            'meeting_id'  => self::MEETING_ID,
            'dry_run'     => true,
            'only_unsent' => false,
        ]);

        $result = $this->callController(EmailController::class, 'sendBulk');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertEquals(1, $data['skipped']);
        $this->assertContains('No Email', $data['skipped_no_email']);
    }

    public function testSendBulkDryRunCountsSentMembers(): void
    {
        $this->setHttpMethod('POST');

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findTitle')->willReturn('AG 2025');

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('listActiveWithEmail')->willReturn([
            ['id' => 'aa000011-0000-4000-a000-000000000011', 'full_name' => 'Alice', 'email' => 'alice@example.com'],
            ['id' => 'aa000012-0000-4000-a000-000000000012', 'full_name' => 'Bob', 'email' => 'bob@example.com'],
        ]);

        $invitationRepo = $this->createMock(InvitationRepository::class);
        $invitationRepo->method('upsertBulk');

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            MemberRepository::class     => $memberRepo,
            InvitationRepository::class => $invitationRepo,
        ]);

        $this->injectJsonBody([
            'meeting_id'  => self::MEETING_ID,
            'dry_run'     => true,
            'only_unsent' => false,
        ]);

        $result = $this->callController(EmailController::class, 'sendBulk');
        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertEquals(2, $data['sent']);
        $this->assertEquals(0, $data['skipped']);
        $this->assertEquals('AG 2025', $data['meeting_title']);
    }

    // =========================================================================
    // Source-level security/structure verification
    // =========================================================================

    public function testScheduleAuditsEmailScheduleEvent(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailController.php');
        $this->assertStringContainsString("'email.schedule'", $source);
    }

    public function testSendBulkAuditsEmailSendBulkEvent(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailController.php');
        $this->assertStringContainsString("'email.send_bulk'", $source);
    }

    public function testSendBulkChecksSmtpConfiguration(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailController.php');
        $this->assertStringContainsString('smtp_not_configured', $source);
        $this->assertStringContainsString('isConfigured', $source);
    }

    public function testScheduleGuardsMeetingNotValidated(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmailController.php');
        $this->assertStringContainsString('api_guard_meeting_not_validated', $source);
    }
}
