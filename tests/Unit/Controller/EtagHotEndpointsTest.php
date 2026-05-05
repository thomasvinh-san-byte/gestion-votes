<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use AgVote\Controller\AuditController;
use AgVote\Controller\DashboardController;
use AgVote\Controller\MeetingsController;
use AgVote\Core\Http\ApiResponseException;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\AuditEventRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MeetingStatsRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\ProxyRepository;
use Tests\Unit\ControllerTestCase;

/**
 * Integration tests for HttpCache wiring on 3 GET HTMX hot endpoints :
 *  - DashboardController::index    -> GET /api/v1/dashboard
 *  - AuditController::timeline     -> GET /api/v1/audit_log
 *  - MeetingsController::archivesList -> GET /api/v1/meeting_archive_list
 *
 * Each endpoint :
 *   A. Returns 200 + ETag header on first call.
 *   B. Returns 304 + empty body when If-None-Match matches the prior ETag.
 *   C. Returns 200 + a NEW ETag when the underlying payload changes.
 */
class EtagHotEndpointsTest extends ControllerTestCase
{
    private const TENANT_ID  = 'tenant-etag-test';
    private const MEETING_ID = 'eeee0000-1111-2222-3333-000000000001';
    private const MOTION_ID  = 'eeee0000-1111-2222-3333-000000000002';
    private const USER_ID    = 'user-etag-001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setAuth(self::USER_ID, 'operator', self::TENANT_ID);
        unset($_SERVER['HTTP_IF_NONE_MATCH']);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_IF_NONE_MATCH']);
        parent::tearDown();
    }

    /**
     * Invoke a controller method and return ['status', 'body', 'headers'].
     * Mirrors callController() but also captures response headers.
     *
     * @return array{status:int, body:array, headers:array<string,string>}
     */
    private function callWithHeaders(string $controllerClass, string $method): array
    {
        $controller = new $controllerClass();
        try {
            $controller->handle($method);
            $this->fail("Expected ApiResponseException from {$controllerClass}::{$method}()");
        } catch (ApiResponseException $e) {
            $resp = $e->getResponse();
            return [
                'status' => $resp->getStatusCode(),
                'body' => $resp->getBody(),
                'headers' => $resp->getHeaders(),
            ];
        }
        return ['status' => 500, 'body' => [], 'headers' => []];
    }

    // =========================================================================
    // Dashboard repo helpers
    // =========================================================================

    /**
     * Build a fully-mocked dashboard repo set with caller-controlled meetings list.
     * The meetings list is the simplest payload knob to mutate between calls.
     *
     * @param array<int, array<string, mixed>> $meetings
     */
    private function injectDashboardRepos(array $meetings): void
    {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('listForDashboard')->willReturn($meetings);
        // No specific meeting_id so findByIdForTenant won't matter unless set.
        $meetingRepo->method('findByIdForTenant')->willReturn(null);

        $this->injectRepos([
            MeetingRepository::class      => $meetingRepo,
            MeetingStatsRepository::class => $this->createMock(MeetingStatsRepository::class),
            MemberRepository::class       => $this->createMock(MemberRepository::class),
            AttendanceRepository::class   => $this->createMock(AttendanceRepository::class),
            MotionRepository::class       => $this->createMock(MotionRepository::class),
            BallotRepository::class       => $this->createMock(BallotRepository::class),
            ProxyRepository::class        => $this->createMock(ProxyRepository::class),
        ]);
    }

    // =========================================================================
    // Dashboard tests
    // =========================================================================

    public function testDashboardIndexReturns200WithEtagHeaderOnFirstCall(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);
        // Empty meetings list -> $suggested = null -> $meetingId stays empty.
        $this->injectDashboardRepos([]);

        $resp = $this->callWithHeaders(DashboardController::class, 'index');

        $this->assertSame(200, $resp['status']);
        $this->assertArrayHasKey('ETag', $resp['headers']);
        $this->assertMatchesRegularExpression('/^"[0-9a-f]{32}"$/', $resp['headers']['ETag']);
        $this->assertArrayHasKey('Cache-Control', $resp['headers']);
        $this->assertSame('private, must-revalidate', $resp['headers']['Cache-Control']);
        // Response shape unchanged.
        $this->assertTrue($resp['body']['ok']);
        $this->assertArrayHasKey('data', $resp['body']);
    }

    public function testDashboardIndexReturns304OnIfNoneMatchSameEtag(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);
        $this->injectDashboardRepos([]);

        // First call : capture ETag.
        $first = $this->callWithHeaders(DashboardController::class, 'index');
        $etag = $first['headers']['ETag'];

        // Reset repo factory + re-inject same fixture for the second call.
        $this->injectDashboardRepos([]);
        $_SERVER['HTTP_IF_NONE_MATCH'] = $etag;
        $second = $this->callWithHeaders(DashboardController::class, 'index');

        $this->assertSame(304, $second['status']);
        $this->assertSame($etag, $second['headers']['ETag']);
        $this->assertSame('private, must-revalidate', $second['headers']['Cache-Control']);
    }

    public function testDashboardIndexReturns200WhenPayloadChanges(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        // First call : empty meetings list.
        $this->injectDashboardRepos([]);
        $first = $this->callWithHeaders(DashboardController::class, 'index');
        $oldEtag = $first['headers']['ETag'];

        // Mutate fixture : add a meeting.
        $this->injectDashboardRepos([[
            'id' => self::MEETING_ID,
            'status' => 'draft',
            'title' => 'New Meeting',
        ]]);
        $_SERVER['HTTP_IF_NONE_MATCH'] = $oldEtag;
        $second = $this->callWithHeaders(DashboardController::class, 'index');

        $this->assertSame(200, $second['status']);
        $this->assertNotSame($oldEtag, $second['headers']['ETag']);
    }

    // =========================================================================
    // Audit timeline helpers + tests
    // =========================================================================

    /**
     * @param array<int, array<string, mixed>> $events
     */
    private function injectAuditRepos(array $events, int $total): void
    {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING_ID,
            'title' => 'Test Meeting',
        ]);

        $auditRepo = $this->createMock(AuditEventRepository::class);
        $auditRepo->method('listForMeetingLog')->willReturn($events);
        $auditRepo->method('countForMeetingLog')->willReturn($total);

        $this->injectRepos([
            MeetingRepository::class    => $meetingRepo,
            AuditEventRepository::class => $auditRepo,
        ]);
    }

    public function testAuditTimelineReturns200WithEtagHeaderOnFirstCall(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID, 'limit' => '10', 'offset' => '0']);
        $this->injectAuditRepos([], 0);

        $resp = $this->callWithHeaders(AuditController::class, 'timeline');

        $this->assertSame(200, $resp['status']);
        $this->assertArrayHasKey('ETag', $resp['headers']);
        $this->assertMatchesRegularExpression('/^"[0-9a-f]{32}"$/', $resp['headers']['ETag']);
        $this->assertSame('private, must-revalidate', $resp['headers']['Cache-Control']);
        // Body shape preserved (data.items, data.meeting_id, etc.).
        $this->assertTrue($resp['body']['ok']);
        $this->assertArrayHasKey('items', $resp['body']['data']);
    }

    public function testAuditTimelineReturns304OnIfNoneMatchSameEtag(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID, 'limit' => '10', 'offset' => '0']);
        $this->injectAuditRepos([], 0);

        $first = $this->callWithHeaders(AuditController::class, 'timeline');
        $etag = $first['headers']['ETag'];

        $this->injectAuditRepos([], 0);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID, 'limit' => '10', 'offset' => '0']);
        $this->setAuth(self::USER_ID, 'operator', self::TENANT_ID);
        $_SERVER['HTTP_IF_NONE_MATCH'] = $etag;

        $second = $this->callWithHeaders(AuditController::class, 'timeline');

        $this->assertSame(304, $second['status']);
        $this->assertSame($etag, $second['headers']['ETag']);
    }

    public function testAuditTimelineReturns200WhenPayloadChanges(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID, 'limit' => '10', 'offset' => '0']);
        $this->injectAuditRepos([], 0);

        $first = $this->callWithHeaders(AuditController::class, 'timeline');
        $oldEtag = $first['headers']['ETag'];

        // Append a new audit event -> total changes -> ETag changes.
        $this->injectAuditRepos([[
            'id' => 'evt-99',
            'action' => 'meeting_created',
            'resource_type' => 'meeting',
            'resource_id' => self::MEETING_ID,
            'actor_role' => 'admin',
            'created_at' => '2026-01-02 09:00:00',
            'ip_address' => '127.0.0.1',
            'payload' => '{"message":"freshly created"}',
        ]], 1);
        $this->setHttpMethod('GET');
        $this->setQueryParams(['meeting_id' => self::MEETING_ID, 'limit' => '10', 'offset' => '0']);
        $this->setAuth(self::USER_ID, 'operator', self::TENANT_ID);
        $_SERVER['HTTP_IF_NONE_MATCH'] = $oldEtag;

        $second = $this->callWithHeaders(AuditController::class, 'timeline');

        $this->assertSame(200, $second['status']);
        $this->assertNotSame($oldEtag, $second['headers']['ETag']);
    }

    // =========================================================================
    // Meetings archivesList tests
    // =========================================================================

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function injectArchivesRepos(array $rows): void
    {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('listArchivedWithReports')->willReturn($rows);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
        ]);
    }

    public function testArchivesListReturns200WithEtagHeaderOnFirstCall(): void
    {
        $this->setHttpMethod('GET');
        $this->injectArchivesRepos([]);

        $resp = $this->callWithHeaders(MeetingsController::class, 'archivesList');

        $this->assertSame(200, $resp['status']);
        $this->assertArrayHasKey('ETag', $resp['headers']);
        $this->assertMatchesRegularExpression('/^"[0-9a-f]{32}"$/', $resp['headers']['ETag']);
        $this->assertSame('private, must-revalidate', $resp['headers']['Cache-Control']);
        $this->assertTrue($resp['body']['ok']);
        $this->assertArrayHasKey('items', $resp['body']['data']);
    }

    public function testArchivesListReturns304OnIfNoneMatchSameEtag(): void
    {
        $this->setHttpMethod('GET');
        $this->injectArchivesRepos([]);

        $first = $this->callWithHeaders(MeetingsController::class, 'archivesList');
        $etag = $first['headers']['ETag'];

        $this->injectArchivesRepos([]);
        $this->setHttpMethod('GET');
        $this->setAuth(self::USER_ID, 'operator', self::TENANT_ID);
        $_SERVER['HTTP_IF_NONE_MATCH'] = $etag;

        $second = $this->callWithHeaders(MeetingsController::class, 'archivesList');

        $this->assertSame(304, $second['status']);
        $this->assertSame($etag, $second['headers']['ETag']);
    }

    public function testArchivesListReturns200WhenPayloadChanges(): void
    {
        $this->setHttpMethod('GET');
        $this->injectArchivesRepos([]);

        $first = $this->callWithHeaders(MeetingsController::class, 'archivesList');
        $oldEtag = $first['headers']['ETag'];

        // Add an archived meeting -> ETag changes.
        $this->injectArchivesRepos([[
            'id' => self::MEETING_ID,
            'title' => 'Archived AGM',
            'archived_at' => '2026-01-01',
        ]]);
        $this->setHttpMethod('GET');
        $this->setAuth(self::USER_ID, 'operator', self::TENANT_ID);
        $_SERVER['HTTP_IF_NONE_MATCH'] = $oldEtag;

        $second = $this->callWithHeaders(MeetingsController::class, 'archivesList');

        $this->assertSame(200, $second['status']);
        $this->assertNotSame($oldEtag, $second['headers']['ETag']);
    }
}
