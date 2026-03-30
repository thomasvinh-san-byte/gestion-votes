<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\AuditController;
use AgVote\Repository\AuditEventRepository;
use AgVote\Repository\MeetingRepository;
use ReflectionClass;

/**
 * Unit tests for AuditController.
 *
 * Tests all testable endpoints with mocked repositories:
 * - timeline():        GET — paginated audit event log for a meeting
 * - meetingAudit():    GET — all audit events for a meeting (admin)
 * - meetingEvents():   GET — formatted events for operator console
 * - verifyChain():     GET — lightweight chain integrity check
 * - operatorEvents():  GET — filtered events for operator
 *
 * export() is excluded: it sends raw CSV/JSON headers + echo (no api_ok/api_fail),
 * so it cannot be captured via callController().
 */
class AuditControllerTest extends ControllerTestCase
{
    private const TENANT_ID  = 'tenant-audit-test';
    private const MEETING_ID = '44440000-1111-2222-3333-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setAuth('operator-01', 'operator', self::TENANT_ID);
    }

    // =========================================================================
    // CONTROLLER STRUCTURE
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new ReflectionClass(AuditController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testControllerExtendsAbstractController(): void
    {
        $ref = new ReflectionClass(AuditController::class);
        $this->assertSame('AgVote\\Controller\\AbstractController', $ref->getParentClass()->getName());
    }

    public function testHasExpectedPublicMethods(): void
    {
        $ref = new ReflectionClass(AuditController::class);
        $methods = array_map(fn ($m) => $m->getName(), $ref->getMethods(\ReflectionMethod::IS_PUBLIC));
        foreach (['timeline', 'export', 'meetingAudit', 'meetingEvents', 'verifyChain', 'operatorEvents'] as $m) {
            $this->assertContains($m, $methods);
        }
    }

    // =========================================================================
    // timeline() — validation
    // =========================================================================

    public function testTimelineMissingMeetingId(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $auditRepo   = $this->createMock(AuditEventRepository::class);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'timeline');
        $this->assertSame(400, $resp['status']);
        $this->assertSame('missing_meeting_id', $resp['body']['error']);
    }

    public function testTimelineInvalidMeetingId(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => 'not-a-uuid']);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $auditRepo   = $this->createMock(AuditEventRepository::class);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'timeline');
        $this->assertSame(400, $resp['status']);
        $this->assertSame('missing_meeting_id', $resp['body']['error']);
    }

    public function testTimelineMeetingNotFound(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(null);

        $auditRepo = $this->createMock(AuditEventRepository::class);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'timeline');
        $this->assertSame(404, $resp['status']);
        $this->assertSame('meeting_not_found', $resp['body']['error']);
    }

    public function testTimelineHappyPath(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID, 'limit' => '10', 'offset' => '0']);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING_ID,
            'title' => 'Test Meeting',
        ]);

        $auditRepo = $this->createMock(AuditEventRepository::class);
        $auditRepo->method('listForMeetingLog')->willReturn([
            [
                'id' => 'evt-01',
                'action' => 'meeting_created',
                'resource_type' => 'meeting',
                'resource_id' => self::MEETING_ID,
                'actor_role' => 'admin',
                'created_at' => '2026-01-01 09:00:00',
                'ip_address' => '127.0.0.1',
                'payload' => '{"message":"created"}',
            ],
        ]);
        $auditRepo->method('countForMeetingLog')->willReturn(1);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'timeline');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertArrayHasKey('items', $data);
        $this->assertSame(self::MEETING_ID, $data['meeting_id']);
        $this->assertSame(1, $data['total']);
        $this->assertCount(1, $data['items']);
    }

    public function testTimelineLimitClamped(): void
    {
        $this->setHttpMethod('GET');
        // limit=500 -> clamped to 200
        $this->setQueryParams(['meeting_id' => self::MEETING_ID, 'limit' => '500']);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(['id' => self::MEETING_ID]);

        $auditRepo = $this->createMock(AuditEventRepository::class);
        $auditRepo->method('listForMeetingLog')->willReturn([]);
        $auditRepo->method('countForMeetingLog')->willReturn(0);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'timeline');
        $this->assertSame(200, $resp['status']);
        $this->assertSame(200, $resp['body']['data']['limit']);
    }

    public function testTimelineFormatsUnknownAction(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(['id' => self::MEETING_ID]);

        $auditRepo = $this->createMock(AuditEventRepository::class);
        $auditRepo->method('listForMeetingLog')->willReturn([
            [
                'id' => 'evt-02',
                'action' => 'custom_action_xyz',
                'resource_type' => 'meeting',
                'resource_id' => self::MEETING_ID,
                'actor_role' => 'system',
                'created_at' => '2026-01-01 09:00:00',
                'ip_address' => null,
                'payload' => null,
            ],
        ]);
        $auditRepo->method('countForMeetingLog')->willReturn(1);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'timeline');
        $this->assertSame(200, $resp['status']);
        $items = $resp['body']['data']['items'];
        $this->assertSame('Custom action xyz', $items[0]['action_label']);
    }

    // =========================================================================
    // meetingAudit() — validation
    // =========================================================================

    public function testMeetingAuditWrongMethod(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $auditRepo   = $this->createMock(AuditEventRepository::class);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'meetingAudit');
        $this->assertSame(405, $resp['status']);
    }

    public function testMeetingAuditMissingMeetingId(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $auditRepo   = $this->createMock(AuditEventRepository::class);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'meetingAudit');
        $this->assertSame(422, $resp['status']);
        $this->assertSame('missing_meeting_id', $resp['body']['error']);
    }

    public function testMeetingAuditMeetingNotFound(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('existsForTenant')->willReturn(false);

        $auditRepo = $this->createMock(AuditEventRepository::class);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'meetingAudit');
        $this->assertSame(404, $resp['status']);
        $this->assertSame('meeting_not_found', $resp['body']['error']);
    }

    public function testMeetingAuditHappyPath(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('existsForTenant')->willReturn(true);

        $auditRepo = $this->createMock(AuditEventRepository::class);
        $auditRepo->method('listForMeeting')->willReturn([
            ['id' => 'e-01', 'action' => 'meeting_created'],
            ['id' => 'e-02', 'action' => 'motion_opened'],
        ]);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'meetingAudit');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertSame(self::MEETING_ID, $data['meeting_id']);
        $this->assertCount(2, $data['items']);
    }

    // =========================================================================
    // meetingEvents() — validation
    // =========================================================================

    public function testMeetingEventsWrongMethod(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $auditRepo   = $this->createMock(AuditEventRepository::class);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'meetingEvents');
        $this->assertSame(405, $resp['status']);
    }

    public function testMeetingEventsMissingMeetingId(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $auditRepo   = $this->createMock(AuditEventRepository::class);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'meetingEvents');
        $this->assertSame(422, $resp['status']);
        $this->assertSame('missing_meeting_id', $resp['body']['error']);
    }

    public function testMeetingEventsMeetingNotFound(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('existsForTenant')->willReturn(false);

        $auditRepo = $this->createMock(AuditEventRepository::class);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'meetingEvents');
        $this->assertSame(404, $resp['status']);
        $this->assertSame('meeting_not_found', $resp['body']['error']);
    }

    public function testMeetingEventsHappyPath(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('existsForTenant')->willReturn(true);

        $auditRepo = $this->createMock(AuditEventRepository::class);
        $auditRepo->method('listForMeeting')->willReturn([
            [
                'id' => 'e-01',
                'action' => 'ballot_cast',
                'resource_type' => 'ballot',
                'resource_id' => 'b-01',
                'created_at' => '2026-01-01 10:00:00',
                'payload' => '{"message":"vote cast"}',
            ],
        ]);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'meetingEvents');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(1, $data['items']);
        $this->assertSame('ballot_cast', $data['items'][0]['action']);
        $this->assertSame('vote cast', $data['items'][0]['message']);
    }

    // =========================================================================
    // verifyChain() — validation
    // =========================================================================

    public function testVerifyChainWrongMethod(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $auditRepo   = $this->createMock(AuditEventRepository::class);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'verifyChain');
        $this->assertSame(405, $resp['status']);
    }

    public function testVerifyChainMissingMeetingId(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $auditRepo   = $this->createMock(AuditEventRepository::class);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'verifyChain');
        $this->assertSame(400, $resp['status']);
    }

    public function testVerifyChainMeetingNotFound(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(null);

        $auditRepo = $this->createMock(AuditEventRepository::class);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'verifyChain');
        $this->assertSame(404, $resp['status']);
        $this->assertSame('meeting_not_found', $resp['body']['error']);
    }

    public function testVerifyChainValidChain(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(['id' => self::MEETING_ID]);

        $hash1 = 'abc123';
        $hash2 = 'def456';

        $auditRepo = $this->createMock(AuditEventRepository::class);
        $auditRepo->method('listForMeetingExport')->willReturn([
            ['this_hash' => $hash1, 'prev_hash' => null, 'created_at' => '2026-01-01 09:00:00'],
            ['this_hash' => $hash2, 'prev_hash' => $hash1, 'created_at' => '2026-01-01 09:01:00'],
        ]);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'verifyChain');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertTrue($data['chain_valid']);
        $this->assertSame(0, $data['error_count']);
        $this->assertSame(2, $data['total_events']);
    }

    public function testVerifyChainBrokenChain(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(['id' => self::MEETING_ID]);

        $auditRepo = $this->createMock(AuditEventRepository::class);
        $auditRepo->method('listForMeetingExport')->willReturn([
            ['this_hash' => 'aaa', 'prev_hash' => null, 'created_at' => '2026-01-01 09:00:00'],
            ['this_hash' => 'bbb', 'prev_hash' => 'WRONG_HASH', 'resource_id' => 'e-02', 'created_at' => '2026-01-01 09:01:00'],
        ]);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'verifyChain');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertFalse($data['chain_valid']);
        $this->assertSame(1, $data['error_count']);
    }

    public function testVerifyChainEmptyEvents(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(['id' => self::MEETING_ID]);

        $auditRepo = $this->createMock(AuditEventRepository::class);
        $auditRepo->method('listForMeetingExport')->willReturn([]);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'verifyChain');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertTrue($data['chain_valid']);
        $this->assertSame(0, $data['total_events']);
    }

    // =========================================================================
    // operatorEvents() — validation
    // =========================================================================

    public function testOperatorEventsWrongMethod(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $auditRepo   = $this->createMock(AuditEventRepository::class);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'operatorEvents');
        $this->assertSame(405, $resp['status']);
    }

    public function testOperatorEventsMissingMeetingId(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $auditRepo   = $this->createMock(AuditEventRepository::class);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'operatorEvents');
        $this->assertSame(422, $resp['status']);
        $this->assertSame('missing_meeting_id', $resp['body']['error']);
    }

    public function testOperatorEventsMeetingNotFound(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('existsForTenant')->willReturn(false);

        $auditRepo = $this->createMock(AuditEventRepository::class);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'operatorEvents');
        $this->assertSame(404, $resp['status']);
        $this->assertSame('meeting_not_found', $resp['body']['error']);
    }

    public function testOperatorEventsHappyPath(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('existsForTenant')->willReturn(true);

        $auditRepo = $this->createMock(AuditEventRepository::class);
        $auditRepo->method('listForMeetingFiltered')->willReturn([
            [
                'id' => 'e-10',
                'action' => 'manual_vote',
                'resource_type' => 'ballot',
                'resource_id' => 'b-10',
                'created_at' => '2026-01-01 11:00:00',
                'payload' => null,
            ],
        ]);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'operatorEvents');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(1, $data['items']);
        $this->assertSame('manual_vote', $data['items'][0]['action']);
    }

    public function testOperatorEventsLimitClamped(): void
    {
        $this->setHttpMethod('GET');
        // limit=1000 -> clamped to 500
        $this->setQueryParams(['meeting_id' => self::MEETING_ID, 'limit' => '1000']);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('existsForTenant')->willReturn(true);

        $auditRepo = $this->createMock(AuditEventRepository::class);
        $auditRepo->method('listForMeetingFiltered')->willReturn([]);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'operatorEvents');
        $this->assertSame(200, $resp['status']);
    }

    public function testOperatorEventsWithFilters(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams([
            'meeting_id' => self::MEETING_ID,
            'resource_type' => 'ballot',
            'action' => 'ballot_cast',
            'q' => 'member',
        ]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('existsForTenant')->willReturn(true);

        $auditRepo = $this->createMock(AuditEventRepository::class);
        $auditRepo->expects($this->once())
            ->method('listForMeetingFiltered')
            ->with(
                self::TENANT_ID,
                self::MEETING_ID,
                200,        // default limit
                'ballot',
                'ballot_cast',
                'member',
            )
            ->willReturn([]);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);

        $resp = $this->callController(AuditController::class, 'operatorEvents');
        $this->assertSame(200, $resp['status']);
    }
}
