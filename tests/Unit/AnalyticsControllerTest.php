<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\AnalyticsController;
use AgVote\Repository\AggregateReportRepository;
use AgVote\Repository\AnalyticsRepository;
use AgVote\Repository\MemberRepository;
use ReflectionClass;

/**
 * Unit tests for AnalyticsController.
 *
 * Tests both endpoints with mocked repositories:
 * - analytics():        GET — dashboard analytics by type (overview, participation, motions, etc.)
 * - reportsAggregate(): GET — aggregate reports in JSON, CSV, XLSX format
 */
class AnalyticsControllerTest extends ControllerTestCase
{
    private const TENANT_ID  = 'tenant-analytics-test';
    private const MEETING_ID = 'aaaa0000-1111-2222-3333-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setAuth('user-01', 'admin', self::TENANT_ID);
    }

    // =========================================================================
    // CONTROLLER STRUCTURE
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new ReflectionClass(AnalyticsController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testControllerExtendsAbstractController(): void
    {
        $ref = new ReflectionClass(AnalyticsController::class);
        $this->assertSame('AgVote\\Controller\\AbstractController', $ref->getParentClass()->getName());
    }

    public function testHasExpectedPublicMethods(): void
    {
        $this->assertTrue(method_exists(AnalyticsController::class, 'analytics'));
        $this->assertTrue(method_exists(AnalyticsController::class, 'reportsAggregate'));
    }

    // =========================================================================
    // analytics() — method enforcement
    // =========================================================================

    public function testAnalyticsWrongMethod(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $memberRepo    = $this->createMock(MemberRepository::class);
        $analyticsRepo = $this->createMock(AnalyticsRepository::class);

        $this->injectRepos([
            MemberRepository::class    => $memberRepo,
            AnalyticsRepository::class => $analyticsRepo,
        ]);

        $resp = $this->callController(AnalyticsController::class, 'analytics');
        $this->assertSame(405, $resp['status']);
    }

    // =========================================================================
    // analytics() — type=overview (default)
    // =========================================================================

    public function testAnalyticsOverviewDefault(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams([]);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(50);

        $analyticsRepo = $this->createMock(AnalyticsRepository::class);
        $analyticsRepo->method('countMeetings')->willReturn(10);
        $analyticsRepo->method('countMotions')->willReturn(30);
        $analyticsRepo->method('countBallots')->willReturn(150);
        $analyticsRepo->method('getMeetingsByStatus')->willReturn([
            ['status' => 'closed', 'count' => 8],
            ['status' => 'live', 'count' => 2],
        ]);
        $analyticsRepo->method('getMotionDecisions')->willReturn([
            ['decision' => 'adopted', 'count' => 25],
            ['decision' => 'rejected', 'count' => 5],
        ]);
        $analyticsRepo->method('getAverageParticipationRate')->willReturn(0.75);

        $this->injectRepos([
            MemberRepository::class    => $memberRepo,
            AnalyticsRepository::class => $analyticsRepo,
        ]);

        $resp = $this->callController(AnalyticsController::class, 'analytics');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertArrayHasKey('totals', $data);
        $this->assertArrayHasKey('meetings_by_status', $data);
        $this->assertArrayHasKey('motion_decisions', $data);
        $this->assertSame(10, $data['totals']['meetings']);
        $this->assertSame(50, $data['totals']['members']);
    }

    // =========================================================================
    // analytics() — type=participation
    // =========================================================================

    public function testAnalyticsParticipation(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['type' => 'participation', 'period' => 'month']);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(20);

        $analyticsRepo = $this->createMock(AnalyticsRepository::class);
        $analyticsRepo->method('getParticipationByMeeting')->willReturn([
            [
                'id' => self::MEETING_ID, 'title' => 'AG 2026',
                'started_at' => '2026-01-15',
                'present_count' => 15, 'proxy_count' => 3, 'total_attendees' => 18,
            ],
        ]);

        $this->injectRepos([
            MemberRepository::class    => $memberRepo,
            AnalyticsRepository::class => $analyticsRepo,
        ]);

        $resp = $this->callController(AnalyticsController::class, 'analytics');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertArrayHasKey('eligible_count', $data);
        $this->assertArrayHasKey('meetings', $data);
        $this->assertSame(20, $data['eligible_count']);
    }

    // =========================================================================
    // analytics() — type=motions
    // =========================================================================

    public function testAnalyticsMotions(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['type' => 'motions', 'period' => 'year']);

        $memberRepo    = $this->createMock(MemberRepository::class);
        $analyticsRepo = $this->createMock(AnalyticsRepository::class);
        $analyticsRepo->method('getMotionsStatsByMeeting')->willReturn([]);
        $analyticsRepo->method('getMotionsTotals')->willReturn([
            'total' => 10, 'adopted' => 7, 'rejected' => 3,
        ]);

        $this->injectRepos([
            MemberRepository::class    => $memberRepo,
            AnalyticsRepository::class => $analyticsRepo,
        ]);

        $resp = $this->callController(AnalyticsController::class, 'analytics');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertArrayHasKey('summary', $data);
        $this->assertSame(10, $data['summary']['total']);
        $this->assertSame(70.0, $data['summary']['adoption_rate']);
    }

    // =========================================================================
    // analytics() — type=vote_duration
    // =========================================================================

    public function testAnalyticsVoteDuration(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['type' => 'vote_duration', 'period' => 'quarter']);

        $memberRepo    = $this->createMock(MemberRepository::class);
        $analyticsRepo = $this->createMock(AnalyticsRepository::class);
        $analyticsRepo->method('getVoteDurations')->willReturn([
            [
                'id' => 'm-01', 'title' => 'Motion 1', 'meeting_title' => 'AG',
                'opened_at' => '2026-01-01 10:00:00', 'closed_at' => '2026-01-01 10:05:00',
                'duration_seconds' => '300',
            ],
        ]);

        $this->injectRepos([
            MemberRepository::class    => $memberRepo,
            AnalyticsRepository::class => $analyticsRepo,
        ]);

        $resp = $this->callController(AnalyticsController::class, 'analytics');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('distribution', $data);
        $this->assertSame(1, $data['count']);
    }

    // =========================================================================
    // analytics() — type=proxies
    // =========================================================================

    public function testAnalyticsProxies(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['type' => 'proxies']);

        $memberRepo    = $this->createMock(MemberRepository::class);
        $analyticsRepo = $this->createMock(AnalyticsRepository::class);
        $analyticsRepo->method('getProxiesStatsByMeeting')->willReturn([]);
        $analyticsRepo->method('countProxies')->willReturn(5);

        $this->injectRepos([
            MemberRepository::class    => $memberRepo,
            AnalyticsRepository::class => $analyticsRepo,
        ]);

        $resp = $this->callController(AnalyticsController::class, 'analytics');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertArrayHasKey('total_proxies', $data);
        $this->assertSame(5, $data['total_proxies']);
    }

    // =========================================================================
    // analytics() — type=anomalies
    // =========================================================================

    public function testAnalyticsAnomalies(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['type' => 'anomalies']);

        $memberRepo    = $this->createMock(MemberRepository::class);
        $analyticsRepo = $this->createMock(AnalyticsRepository::class);
        $analyticsRepo->method('countLowParticipationMeetings')->willReturn(2);
        $analyticsRepo->method('countQuorumIssues')->willReturn(0);
        $analyticsRepo->method('countIncompleteVotes')->willReturn(1);
        $analyticsRepo->method('countHighProxyConcentration')->willReturn(0);
        $analyticsRepo->method('getAbstentionRate')->willReturn(0.05);
        $analyticsRepo->method('countVeryShortVotes')->willReturn(3);
        $analyticsRepo->method('getFlaggedMeetings')->willReturn([
            [
                'id' => self::MEETING_ID, 'title' => 'Flagged AG', 'date' => '2026-01-01',
                'eligible' => '20', 'attended' => '8', 'quorum_issues' => '0', 'incomplete' => '1',
            ],
        ]);

        $this->injectRepos([
            MemberRepository::class    => $memberRepo,
            AnalyticsRepository::class => $analyticsRepo,
        ]);

        $resp = $this->callController(AnalyticsController::class, 'analytics');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertArrayHasKey('indicators', $data);
        $this->assertArrayHasKey('flagged_meetings', $data);
        $this->assertSame(2, $data['indicators']['low_participation_count']);
    }

    // =========================================================================
    // analytics() — type=vote_timing
    // =========================================================================

    public function testAnalyticsVoteTiming(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['type' => 'vote_timing']);

        $memberRepo    = $this->createMock(MemberRepository::class);
        $analyticsRepo = $this->createMock(AnalyticsRepository::class);
        $analyticsRepo->method('getVoteTimingDistribution')->willReturn([
            ['response_seconds' => '5.0'],
            ['response_seconds' => '25.0'],
            ['response_seconds' => '90.0'],
        ]);

        $this->injectRepos([
            MemberRepository::class    => $memberRepo,
            AnalyticsRepository::class => $analyticsRepo,
        ]);

        $resp = $this->callController(AnalyticsController::class, 'analytics');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('distribution', $data);
        $this->assertSame(3, $data['count']);
        $this->assertSame(1, $data['distribution']['0-10s']);
        $this->assertSame(1, $data['distribution']['10-30s']);
        $this->assertSame(1, $data['distribution']['1-2m']);
    }

    // =========================================================================
    // analytics() — invalid type
    // =========================================================================

    public function testAnalyticsInvalidType(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['type' => 'nonexistent_type']);

        $memberRepo    = $this->createMock(MemberRepository::class);
        $analyticsRepo = $this->createMock(AnalyticsRepository::class);

        $this->injectRepos([
            MemberRepository::class    => $memberRepo,
            AnalyticsRepository::class => $analyticsRepo,
        ]);

        $resp = $this->callController(AnalyticsController::class, 'analytics');
        $this->assertSame(400, $resp['status']);
        $this->assertSame('invalid_type', $resp['body']['error']);
    }

    // =========================================================================
    // analytics() — period variants
    // =========================================================================

    public function testAnalyticsAllPeriods(): void
    {
        $periods = ['month', 'quarter', 'year', 'all', 'unknown'];

        foreach ($periods as $period) {
            $this->setHttpMethod('GET');
            $this->setQueryParams(['type' => 'overview', 'period' => $period]);

            $memberRepo = $this->createMock(MemberRepository::class);
            $memberRepo->method('countActive')->willReturn(0);

            $analyticsRepo = $this->createMock(AnalyticsRepository::class);
            $analyticsRepo->method('countMeetings')->willReturn(0);
            $analyticsRepo->method('countMotions')->willReturn(0);
            $analyticsRepo->method('countBallots')->willReturn(0);
            $analyticsRepo->method('getMeetingsByStatus')->willReturn([]);
            $analyticsRepo->method('getMotionDecisions')->willReturn([]);
            $analyticsRepo->method('getAverageParticipationRate')->willReturn(0.0);

            $this->injectRepos([
                MemberRepository::class    => $memberRepo,
                AnalyticsRepository::class => $analyticsRepo,
            ]);

            $resp = $this->callController(AnalyticsController::class, 'analytics');
            $this->assertSame(200, $resp['status'], "Failed for period: {$period}");
        }
    }

    // =========================================================================
    // reportsAggregate() — method enforcement
    // =========================================================================

    public function testReportsAggregateWrongMethod(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $aggRepo = $this->createMock(AggregateReportRepository::class);
        $this->injectRepos([AggregateReportRepository::class => $aggRepo]);

        $resp = $this->callController(AnalyticsController::class, 'reportsAggregate');
        $this->assertSame(405, $resp['status']);
    }

    // =========================================================================
    // reportsAggregate() — list_meetings
    // =========================================================================

    public function testReportsAggregateListMeetings(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['list_meetings' => '1']);

        $aggRepo = $this->createMock(AggregateReportRepository::class);
        $aggRepo->method('listAvailableMeetings')->willReturn([
            ['id' => self::MEETING_ID, 'title' => 'AG 2026', 'scheduled_at' => '2026-01-01'],
        ]);

        $this->injectRepos([AggregateReportRepository::class => $aggRepo]);

        $resp = $this->callController(AnalyticsController::class, 'reportsAggregate');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(1, $data['items']);
    }

    // =========================================================================
    // reportsAggregate() — invalid report_type
    // =========================================================================

    public function testReportsAggregateInvalidReportType(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['report_type' => 'nonexistent']);

        $aggRepo = $this->createMock(AggregateReportRepository::class);
        $this->injectRepos([AggregateReportRepository::class => $aggRepo]);

        $resp = $this->callController(AnalyticsController::class, 'reportsAggregate');
        $this->assertSame(400, $resp['status']);
        $this->assertSame('invalid_report_type', $resp['body']['error']);
    }

    // =========================================================================
    // reportsAggregate() — invalid format
    // =========================================================================

    public function testReportsAggregateInvalidFormat(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['report_type' => 'summary', 'format' => 'pdf']);

        $aggRepo = $this->createMock(AggregateReportRepository::class);
        $this->injectRepos([AggregateReportRepository::class => $aggRepo]);

        $resp = $this->callController(AnalyticsController::class, 'reportsAggregate');
        $this->assertSame(400, $resp['status']);
        $this->assertSame('invalid_format', $resp['body']['error']);
    }

    // =========================================================================
    // reportsAggregate() — JSON format (all report types)
    // =========================================================================

    public function testReportsAggregateJsonSummary(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['report_type' => 'summary', 'format' => 'json']);

        $aggRepo = $this->createMock(AggregateReportRepository::class);
        $aggRepo->method('getSummary')->willReturn([
            'total_meetings' => 5,
            'total_motions' => 15,
            'adopted_count' => 12,
            'rejected_count' => 3,
            'other_count' => 0,
            'avg_attendance' => 0.8,
            'first_meeting' => '2025-01-01',
            'last_meeting' => '2026-03-01',
        ]);

        $this->injectRepos([AggregateReportRepository::class => $aggRepo]);

        $resp = $this->callController(AnalyticsController::class, 'reportsAggregate');
        $this->assertSame(200, $resp['status']);
        $data = $resp['body']['data'];
        $this->assertSame('summary', $data['report_type']);
        $this->assertArrayHasKey('data', $data);
    }

    public function testReportsAggregateJsonParticipation(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['report_type' => 'participation', 'format' => 'json']);

        $aggRepo = $this->createMock(AggregateReportRepository::class);
        $aggRepo->method('getParticipationReport')->willReturn([]);

        $this->injectRepos([AggregateReportRepository::class => $aggRepo]);

        $resp = $this->callController(AnalyticsController::class, 'reportsAggregate');
        $this->assertSame(200, $resp['status']);
        $this->assertSame('participation', $resp['body']['data']['report_type']);
    }

    public function testReportsAggregateJsonDecisions(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['report_type' => 'decisions', 'format' => 'json']);

        $aggRepo = $this->createMock(AggregateReportRepository::class);
        $aggRepo->method('getDecisionsReport')->willReturn([]);

        $this->injectRepos([AggregateReportRepository::class => $aggRepo]);

        $resp = $this->callController(AnalyticsController::class, 'reportsAggregate');
        $this->assertSame(200, $resp['status']);
    }

    public function testReportsAggregateJsonVotingPower(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['report_type' => 'voting_power', 'format' => 'json']);

        $aggRepo = $this->createMock(AggregateReportRepository::class);
        $aggRepo->method('getVotingPowerReport')->willReturn([]);

        $this->injectRepos([AggregateReportRepository::class => $aggRepo]);

        $resp = $this->callController(AnalyticsController::class, 'reportsAggregate');
        $this->assertSame(200, $resp['status']);
    }

    public function testReportsAggregateJsonProxies(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['report_type' => 'proxies', 'format' => 'json']);

        $aggRepo = $this->createMock(AggregateReportRepository::class);
        $aggRepo->method('getProxiesReport')->willReturn([]);

        $this->injectRepos([AggregateReportRepository::class => $aggRepo]);

        $resp = $this->callController(AnalyticsController::class, 'reportsAggregate');
        $this->assertSame(200, $resp['status']);
    }

    public function testReportsAggregateJsonQuorum(): void
    {
        $this->setHttpMethod('GET');
        $this->setQueryParams(['report_type' => 'quorum', 'format' => 'json']);

        $aggRepo = $this->createMock(AggregateReportRepository::class);
        $aggRepo->method('getQuorumReport')->willReturn([]);

        $this->injectRepos([AggregateReportRepository::class => $aggRepo]);

        $resp = $this->callController(AnalyticsController::class, 'reportsAggregate');
        $this->assertSame(200, $resp['status']);
    }

    // =========================================================================
    // reportsAggregate() — with meeting_ids filter
    // =========================================================================

    public function testReportsAggregateWithMeetingIdsFilter(): void
    {
        $this->setHttpMethod('GET');
        // meeting_ids must be in $_GET as an array
        $_GET = [
            'report_type' => 'summary',
            'format' => 'json',
            'meeting_ids' => [self::MEETING_ID],
        ];

        $aggRepo = $this->createMock(AggregateReportRepository::class);
        $aggRepo->method('getSummary')->with(
            self::TENANT_ID,
            [self::MEETING_ID],
            null,
            null,
        )->willReturn([]);

        $this->injectRepos([AggregateReportRepository::class => $aggRepo]);

        $resp = $this->callController(AnalyticsController::class, 'reportsAggregate');
        $this->assertSame(200, $resp['status']);
    }

    public function testReportsAggregateWithInvalidMeetingIds(): void
    {
        $this->setHttpMethod('GET');
        $_GET = [
            'report_type' => 'summary',
            'format' => 'json',
            'meeting_ids' => ['not-a-uuid', 'also-invalid'],
        ];

        $aggRepo = $this->createMock(AggregateReportRepository::class);
        // Invalid UUIDs filtered out → meetingIds = null
        $aggRepo->method('getSummary')->with(self::TENANT_ID, null, null, null)->willReturn([]);

        $this->injectRepos([AggregateReportRepository::class => $aggRepo]);

        $resp = $this->callController(AnalyticsController::class, 'reportsAggregate');
        $this->assertSame(200, $resp['status']);
    }
}
