<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\InvitationsController;
use AgVote\Repository\EmailEventRepository;
use AgVote\Repository\EmailQueueRepository;
use AgVote\Repository\InvitationRepository;
use AgVote\Repository\MeetingRepository;

/**
 * Unit tests for InvitationsController.
 *
 * Endpoints:
 *  - create():        POST — create/upsert an invitation for a member
 *  - listForMeeting(): GET — list invitations for a meeting
 *  - redeem():        GET  — redeem an invitation token
 *  - stats():         GET  — get invitation stats for a meeting
 *
 * Response structure:
 *   - api_ok($data)  => body['data'][...] (wrapped in 'data' key)
 *   - api_fail(...)  => body['error'] (direct)
 *
 * Pattern: extends ControllerTestCase, uses injectRepos() + callController().
 */
class InvitationsControllerTest extends ControllerTestCase
{
    private const TENANT     = 'tenant-uuid-001';
    private const MEETING_ID = 'aaaaaaaa-1111-2222-3333-000000000050';
    private const MEMBER_ID  = 'bbbbbbbb-1111-2222-3333-000000000050';
    private const INV_ID     = 'cccccccc-1111-2222-3333-000000000050';
    private const TOKEN      = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2';
    private const USER_ID    = 'user-uuid-0050';

    // =========================================================================
    // CONTROLLER STRUCTURE
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(InvitationsController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testControllerHasRequiredMethods(): void
    {
        foreach (['create', 'listForMeeting', 'redeem', 'stats'] as $method) {
            $this->assertTrue(method_exists(InvitationsController::class, $method));
        }
    }

    // =========================================================================
    // create() — POST
    // =========================================================================

    public function testCreateRequiresPost(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');

        $res = $this->callController(InvitationsController::class, 'create');

        $this->assertSame(405, $res['status']);
    }

    public function testCreateMissingMeetingAndMemberReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $repo = $this->createMock(InvitationRepository::class);
        $this->injectRepos([InvitationRepository::class => $repo]);

        $res = $this->callController(InvitationsController::class, 'create');

        $this->assertSame(400, $res['status']);
        $this->assertSame('missing_meeting_or_member', $res['body']['error']);
    }

    public function testCreateInvalidMeetingIdReturns422(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id' => 'not-a-uuid',
            'member_id'  => self::MEMBER_ID,
        ]);

        $repo = $this->createMock(InvitationRepository::class);
        $this->injectRepos([InvitationRepository::class => $repo]);

        $res = $this->callController(InvitationsController::class, 'create');

        $this->assertSame(422, $res['status']);
        $this->assertSame('invalid_meeting_id', $res['body']['error']);
    }

    public function testCreateInvalidMemberIdReturns422(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id' => self::MEETING_ID,
            'member_id'  => 'not-a-uuid',
        ]);

        $repo = $this->createMock(InvitationRepository::class);
        $this->injectRepos([InvitationRepository::class => $repo]);

        $res = $this->callController(InvitationsController::class, 'create');

        $this->assertSame(422, $res['status']);
        $this->assertSame('invalid_member_id', $res['body']['error']);
    }

    public function testCreateInvalidEmailReturns422(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id' => self::MEETING_ID,
            'member_id'  => self::MEMBER_ID,
            'email'      => 'not-an-email',
        ]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('isValidated')->willReturn(false);

        $repo = $this->createMock(InvitationRepository::class);
        $this->injectRepos([
            InvitationRepository::class => $repo,
            MeetingRepository::class    => $meetingRepo,
        ]);

        $res = $this->callController(InvitationsController::class, 'create');

        $this->assertSame(422, $res['status']);
        $this->assertSame('invalid_email', $res['body']['error']);
    }

    public function testCreateSucceeds(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('POST');
        $this->injectJsonBody([
            'meeting_id' => self::MEETING_ID,
            'member_id'  => self::MEMBER_ID,
            'email'      => 'alice@example.com',
        ]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('isValidated')->willReturn(false);

        $repo = $this->createMock(InvitationRepository::class);
        $repo->expects($this->once())->method('upsertSent');

        $this->injectRepos([
            InvitationRepository::class => $repo,
            MeetingRepository::class    => $meetingRepo,
        ]);

        $res = $this->callController(InvitationsController::class, 'create');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        $this->assertSame(self::MEETING_ID, $data['meeting_id']);
        $this->assertSame(self::MEMBER_ID, $data['member_id']);
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('vote_url', $data);
    }

    // =========================================================================
    // listForMeeting() — GET
    // =========================================================================

    public function testListForMeetingMissingMeetingIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $repo = $this->createMock(InvitationRepository::class);
        $this->injectRepos([InvitationRepository::class => $repo]);

        $res = $this->callController(InvitationsController::class, 'listForMeeting');

        $this->assertSame(400, $res['status']);
        $this->assertSame('missing_meeting_id', $res['body']['error']);
    }

    public function testListForMeetingInvalidUuidReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => 'not-a-uuid']);

        $repo = $this->createMock(InvitationRepository::class);
        $this->injectRepos([InvitationRepository::class => $repo]);

        $res = $this->callController(InvitationsController::class, 'listForMeeting');

        $this->assertSame(400, $res['status']);
        $this->assertSame('missing_meeting_id', $res['body']['error']);
    }

    public function testListForMeetingSucceeds(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $repo = $this->createMock(InvitationRepository::class);
        $repo->method('listForMeeting')->willReturn([
            ['id' => self::INV_ID, 'member_id' => self::MEMBER_ID, 'status' => 'sent'],
        ]);

        $this->injectRepos([InvitationRepository::class => $repo]);

        $res = $this->callController(InvitationsController::class, 'listForMeeting');

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['body']['data']['items']);
    }

    // =========================================================================
    // redeem() — GET
    // =========================================================================

    public function testRedeemMissingTokenReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $repo = $this->createMock(InvitationRepository::class);
        $this->injectRepos([InvitationRepository::class => $repo]);

        $res = $this->callController(InvitationsController::class, 'redeem');

        $this->assertSame(400, $res['status']);
        $this->assertSame('missing_token', $res['body']['error']);
    }

    public function testRedeemInvalidTokenReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['token' => 'some-token']);

        $repo = $this->createMock(InvitationRepository::class);
        $repo->method('findByToken')->willReturn(null);

        $this->injectRepos([InvitationRepository::class => $repo]);

        $res = $this->callController(InvitationsController::class, 'redeem');

        $this->assertSame(404, $res['status']);
        $this->assertSame('invalid_token', $res['body']['error']);
    }

    public function testRedeemWrongTenantReturns404(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['token' => self::TOKEN]);

        $repo = $this->createMock(InvitationRepository::class);
        $repo->method('findByToken')->willReturn([
            'id'         => self::INV_ID,
            'tenant_id'  => 'other-tenant',
            'meeting_id' => self::MEETING_ID,
            'member_id'  => self::MEMBER_ID,
            'status'     => 'sent',
        ]);

        $this->injectRepos([InvitationRepository::class => $repo]);

        $res = $this->callController(InvitationsController::class, 'redeem');

        $this->assertSame(404, $res['status']);
        $this->assertSame('invalid_token', $res['body']['error']);
    }

    public function testRedeemDeclinedTokenReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['token' => self::TOKEN]);

        $repo = $this->createMock(InvitationRepository::class);
        $repo->method('findByToken')->willReturn([
            'id'         => self::INV_ID,
            'tenant_id'  => self::TENANT,
            'meeting_id' => self::MEETING_ID,
            'member_id'  => self::MEMBER_ID,
            'status'     => 'declined',
        ]);

        $this->injectRepos([InvitationRepository::class => $repo]);

        $res = $this->callController(InvitationsController::class, 'redeem');

        $this->assertSame(400, $res['status']);
        $this->assertSame('token_not_usable', $res['body']['error']);
    }

    public function testRedeemBouncedTokenReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['token' => self::TOKEN]);

        $repo = $this->createMock(InvitationRepository::class);
        $repo->method('findByToken')->willReturn([
            'id'         => self::INV_ID,
            'tenant_id'  => self::TENANT,
            'meeting_id' => self::MEETING_ID,
            'member_id'  => self::MEMBER_ID,
            'status'     => 'bounced',
        ]);

        $this->injectRepos([InvitationRepository::class => $repo]);

        $res = $this->callController(InvitationsController::class, 'redeem');

        $this->assertSame(400, $res['status']);
        $this->assertSame('token_not_usable', $res['body']['error']);
    }

    public function testRedeemPendingTokenMarksOpenedAndAccepted(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['token' => self::TOKEN]);

        $repo = $this->createMock(InvitationRepository::class);
        $repo->method('findByToken')->willReturn([
            'id'         => self::INV_ID,
            'tenant_id'  => self::TENANT,
            'meeting_id' => self::MEETING_ID,
            'member_id'  => self::MEMBER_ID,
            'status'     => 'pending',
        ]);
        $repo->expects($this->once())->method('markOpened');
        $repo->expects($this->once())->method('markAccepted');

        $this->injectRepos([InvitationRepository::class => $repo]);

        $res = $this->callController(InvitationsController::class, 'redeem');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        $this->assertSame(self::MEETING_ID, $data['meeting_id']);
        $this->assertSame(self::MEMBER_ID, $data['member_id']);
        $this->assertSame('accepted', $data['status']);
    }

    public function testRedeemAlreadyAcceptedTokenJustMarksAccepted(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['token' => self::TOKEN]);

        $repo = $this->createMock(InvitationRepository::class);
        $repo->method('findByToken')->willReturn([
            'id'         => self::INV_ID,
            'tenant_id'  => self::TENANT,
            'meeting_id' => self::MEETING_ID,
            'member_id'  => self::MEMBER_ID,
            'status'     => 'accepted',
        ]);
        // markOpened is not called for already-accepted status
        $repo->expects($this->never())->method('markOpened');
        $repo->expects($this->once())->method('markAccepted');

        $this->injectRepos([InvitationRepository::class => $repo]);

        $res = $this->callController(InvitationsController::class, 'redeem');

        $this->assertSame(200, $res['status']);
        $this->assertSame('accepted', $res['body']['data']['status']);
    }

    // =========================================================================
    // stats() — GET
    // =========================================================================

    public function testStatsMissingMeetingIdReturns400(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $repo = $this->createMock(InvitationRepository::class);
        $this->injectRepos([InvitationRepository::class => $repo]);

        $res = $this->callController(InvitationsController::class, 'stats');

        $this->assertSame(400, $res['status']);
        $this->assertSame('missing_meeting_id', $res['body']['error']);
    }

    public function testStatsReturnsAggregates(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $invRepo = $this->createMock(InvitationRepository::class);
        $invRepo->method('getStatsForMeeting')->willReturn([
            'total'         => 10,
            'pending'       => 2,
            'sent'          => 5,
            'opened'        => 2,
            'accepted'      => 1,
            'declined'      => 0,
            'bounced'       => 0,
            'total_opens'   => 4,
            'total_clicks'  => 2,
        ]);

        $queueRepo = $this->createMock(EmailQueueRepository::class);
        $queueRepo->method('countByStatusForMeeting')->willReturn([]);

        $eventRepo = $this->createMock(EmailEventRepository::class);
        $eventRepo->method('countByTypeForMeeting')->willReturn([]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('existsForTenant')->willReturn(true);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING_ID, 'status' => 'live',
        ]);

        $this->injectRepos([
            InvitationRepository::class => $invRepo,
            EmailQueueRepository::class => $queueRepo,
            EmailEventRepository::class => $eventRepo,
            MeetingRepository::class    => $meetingRepo,
        ]);

        $res = $this->callController(InvitationsController::class, 'stats');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        $this->assertSame(self::MEETING_ID, $data['meeting_id']);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('engagement', $data);
        $this->assertSame(10, $data['items']['total']);
    }

    public function testStatsCalculatesRates(): void
    {
        $this->setAuth(self::USER_ID, 'operator', self::TENANT);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $invRepo = $this->createMock(InvitationRepository::class);
        $invRepo->method('getStatsForMeeting')->willReturn([
            'total'         => 10,
            'pending'       => 0,
            'sent'          => 8,
            'opened'        => 4,
            'accepted'      => 2,
            'declined'      => 0,
            'bounced'       => 2,
            'total_opens'   => 6,
            'total_clicks'  => 3,
        ]);

        $queueRepo = $this->createMock(EmailQueueRepository::class);
        $queueRepo->method('countByStatusForMeeting')->willReturn([
            ['status' => 'queued', 'count' => 3],
        ]);

        $eventRepo = $this->createMock(EmailEventRepository::class);
        $eventRepo->method('countByTypeForMeeting')->willReturn([
            ['event_type' => 'opened', 'count' => 4],
        ]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('existsForTenant')->willReturn(true);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING_ID, 'status' => 'live',
        ]);

        $this->injectRepos([
            InvitationRepository::class => $invRepo,
            EmailQueueRepository::class => $queueRepo,
            EmailEventRepository::class => $eventRepo,
            MeetingRepository::class    => $meetingRepo,
        ]);

        $res = $this->callController(InvitationsController::class, 'stats');

        $this->assertSame(200, $res['status']);
        $data = $res['body']['data'];
        $this->assertSame(3, $data['queue']['queued']);
        $this->assertSame(4, $data['events']['opened']);
        $this->assertGreaterThan(0, $data['engagement']['open_rate']);
    }
}
