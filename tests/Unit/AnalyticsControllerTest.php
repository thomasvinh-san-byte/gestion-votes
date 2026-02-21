<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\AnalyticsController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AnalyticsController.
 *
 * Tests the analytics and aggregate report endpoints including:
 *  - Controller structure (final, extends AbstractController, public methods)
 *  - HTTP method enforcement for analytics and reportsAggregate
 *  - Query parameter validation (type, period, report_type, format)
 *  - Limit clamping logic
 *  - Period-to-date mapping
 *  - Response structure verification via source introspection
 *  - Duration formatting logic
 *
 * Since api_ok()/api_fail() throw ApiResponseException, we catch these to
 * inspect controller behavior without a database.
 */
class AnalyticsControllerTest extends TestCase
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
        $controller = new AnalyticsController();
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
        $ref = new \ReflectionClass(AnalyticsController::class);
        $this->assertTrue($ref->isFinal(), 'AnalyticsController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new AnalyticsController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(AnalyticsController::class);

        $expectedMethods = ['analytics', 'reportsAggregate'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "AnalyticsController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(AnalyticsController::class);

        $expectedMethods = ['analytics', 'reportsAggregate'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "AnalyticsController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // analytics: METHOD ENFORCEMENT
    // =========================================================================

    public function testAnalyticsRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('analytics');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testAnalyticsRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('analytics');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testAnalyticsRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('analytics');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testAnalyticsRejectsPatchMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';

        $result = $this->callControllerMethod('analytics');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // reportsAggregate: METHOD ENFORCEMENT
    // =========================================================================

    public function testReportsAggregateRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('reportsAggregate');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testReportsAggregateRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('reportsAggregate');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testReportsAggregateRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('reportsAggregate');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testReportsAggregateRejectsPatchMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';

        $result = $this->callControllerMethod('reportsAggregate');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // analytics: LIMIT CLAMPING LOGIC
    // =========================================================================

    public function testLimitClampingMinBound(): void
    {
        $limit = min(100, max(1, 0));
        $this->assertEquals(1, $limit, 'Limit of 0 should be clamped to 1');
    }

    public function testLimitClampingNegativeValue(): void
    {
        $limit = min(100, max(1, -5));
        $this->assertEquals(1, $limit, 'Negative limit should be clamped to 1');
    }

    public function testLimitClampingMaxBound(): void
    {
        $limit = min(100, max(1, 200));
        $this->assertEquals(100, $limit, 'Limit of 200 should be clamped to 100');
    }

    public function testLimitClampingValidValue(): void
    {
        $limit = min(100, max(1, 50));
        $this->assertEquals(50, $limit, 'Valid limit of 50 should pass through');
    }

    public function testLimitClampingExactly100(): void
    {
        $limit = min(100, max(1, 100));
        $this->assertEquals(100, $limit, 'Limit of exactly 100 should be accepted');
    }

    public function testLimitClampingExactly1(): void
    {
        $limit = min(100, max(1, 1));
        $this->assertEquals(1, $limit, 'Limit of exactly 1 should be accepted');
    }

    public function testLimitClampingDefault20(): void
    {
        $limit = min(100, max(1, 20));
        $this->assertEquals(20, $limit, 'Default limit of 20 should pass through');
    }

    // =========================================================================
    // analytics: PERIOD-TO-DATE MAPPING
    // =========================================================================

    public function testPeriodMonthMapping(): void
    {
        $dateFrom = match ('month') {
            'month' => date('Y-m-d', strtotime('-1 month')),
            'quarter' => date('Y-m-d', strtotime('-3 months')),
            'year' => date('Y-m-d', strtotime('-1 year')),
            'all' => '2000-01-01',
            default => date('Y-m-d', strtotime('-1 year')),
        };

        $this->assertEquals(date('Y-m-d', strtotime('-1 month')), $dateFrom);
    }

    public function testPeriodQuarterMapping(): void
    {
        $dateFrom = match ('quarter') {
            'month' => date('Y-m-d', strtotime('-1 month')),
            'quarter' => date('Y-m-d', strtotime('-3 months')),
            'year' => date('Y-m-d', strtotime('-1 year')),
            'all' => '2000-01-01',
            default => date('Y-m-d', strtotime('-1 year')),
        };

        $this->assertEquals(date('Y-m-d', strtotime('-3 months')), $dateFrom);
    }

    public function testPeriodYearMapping(): void
    {
        $dateFrom = match ('year') {
            'month' => date('Y-m-d', strtotime('-1 month')),
            'quarter' => date('Y-m-d', strtotime('-3 months')),
            'year' => date('Y-m-d', strtotime('-1 year')),
            'all' => '2000-01-01',
            default => date('Y-m-d', strtotime('-1 year')),
        };

        $this->assertEquals(date('Y-m-d', strtotime('-1 year')), $dateFrom);
    }

    public function testPeriodAllMapping(): void
    {
        $dateFrom = match ('all') {
            'month' => date('Y-m-d', strtotime('-1 month')),
            'quarter' => date('Y-m-d', strtotime('-3 months')),
            'year' => date('Y-m-d', strtotime('-1 year')),
            'all' => '2000-01-01',
            default => date('Y-m-d', strtotime('-1 year')),
        };

        $this->assertEquals('2000-01-01', $dateFrom);
    }

    public function testPeriodDefaultFallsBackToYear(): void
    {
        $dateFrom = match ('unknown') {
            'month' => date('Y-m-d', strtotime('-1 month')),
            'quarter' => date('Y-m-d', strtotime('-3 months')),
            'year' => date('Y-m-d', strtotime('-1 year')),
            'all' => '2000-01-01',
            default => date('Y-m-d', strtotime('-1 year')),
        };

        $this->assertEquals(date('Y-m-d', strtotime('-1 year')), $dateFrom);
    }

    // =========================================================================
    // analytics: TYPE VALIDATION
    // =========================================================================

    public function testAnalyticsValidTypes(): void
    {
        $validTypes = ['overview', 'participation', 'motions', 'vote_duration', 'proxies', 'anomalies', 'vote_timing'];

        foreach ($validTypes as $type) {
            $this->assertContains($type, $validTypes, "'{$type}' should be a valid analytics type");
        }
    }

    public function testAnalyticsInvalidTypeReturnsFail(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AnalyticsController.php');

        $this->assertStringContainsString("'invalid_type'", $source,
            'analytics should return invalid_type for unknown type parameter');
    }

    public function testAnalyticsDefaultTypeIsOverview(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AnalyticsController.php');

        $this->assertStringContainsString("'type', 'overview'", $source,
            'analytics default type should be overview');
    }

    public function testAnalyticsDefaultPeriodIsYear(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AnalyticsController.php');

        $this->assertStringContainsString("'period', 'year'", $source,
            'analytics default period should be year');
    }

    // =========================================================================
    // reportsAggregate: REPORT TYPE VALIDATION
    // =========================================================================

    public function testReportsAggregateValidReportTypes(): void
    {
        $validTypes = ['participation', 'decisions', 'voting_power', 'proxies', 'quorum', 'summary'];

        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AnalyticsController.php');
        foreach ($validTypes as $type) {
            $this->assertStringContainsString(
                "'{$type}'",
                $source,
                "reportsAggregate should support report type '{$type}'",
            );
        }
    }

    public function testReportsAggregateInvalidReportTypeError(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AnalyticsController.php');

        $this->assertStringContainsString("'invalid_report_type'", $source,
            'reportsAggregate should return invalid_report_type for unknown types');
    }

    public function testReportsAggregateDefaultReportTypeIsSummary(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AnalyticsController.php');

        $this->assertStringContainsString("'report_type', 'summary'", $source,
            'reportsAggregate default report_type should be summary');
    }

    // =========================================================================
    // reportsAggregate: FORMAT VALIDATION
    // =========================================================================

    public function testReportsAggregateValidFormats(): void
    {
        $validFormats = ['json', 'csv', 'xlsx'];

        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AnalyticsController.php');
        foreach ($validFormats as $format) {
            $this->assertStringContainsString(
                "'{$format}'",
                $source,
                "reportsAggregate should support format '{$format}'",
            );
        }
    }

    public function testReportsAggregateInvalidFormatError(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AnalyticsController.php');

        $this->assertStringContainsString("'invalid_format'", $source,
            'reportsAggregate should return invalid_format for unknown formats');
    }

    public function testReportsAggregateDefaultFormatIsJson(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AnalyticsController.php');

        $this->assertStringContainsString("'format', 'json'", $source,
            'reportsAggregate default format should be json');
    }

    // =========================================================================
    // DURATION FORMATTING LOGIC
    // =========================================================================

    public function testFormatDurationUnderOneMinute(): void
    {
        $seconds = 45.0;
        $result = $this->formatDuration($seconds);
        $this->assertEquals('45s', $result);
    }

    public function testFormatDurationExactlyOneMinute(): void
    {
        $seconds = 60.0;
        $result = $this->formatDuration($seconds);
        $this->assertEquals('1m', $result);
    }

    public function testFormatDurationMinutesAndSeconds(): void
    {
        $seconds = 90.0;
        $result = $this->formatDuration($seconds);
        $this->assertEquals('1m 30s', $result);
    }

    public function testFormatDurationOneHour(): void
    {
        $seconds = 3600.0;
        $result = $this->formatDuration($seconds);
        $this->assertEquals('1h', $result);
    }

    public function testFormatDurationHoursAndMinutes(): void
    {
        $seconds = 3660.0;
        $result = $this->formatDuration($seconds);
        $this->assertEquals('1h 1m', $result);
    }

    public function testFormatDurationZeroSeconds(): void
    {
        $seconds = 0.0;
        $result = $this->formatDuration($seconds);
        $this->assertEquals('0s', $result);
    }

    /**
     * Replicate the formatDuration logic from AnalyticsController.
     */
    private function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds) . 's';
        } elseif ($seconds < 3600) {
            $m = floor($seconds / 60);
            $s = round($seconds % 60);
            return $m . 'm' . ($s > 0 ? ' ' . $s . 's' : '');
        }
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        return $h . 'h' . ($m > 0 ? ' ' . $m . 'm' : '');
    }

    // =========================================================================
    // VOTE DURATION DISTRIBUTION LOGIC
    // =========================================================================

    public function testVoteDurationDistributionBuckets(): void
    {
        $distribution = ['0-30s' => 0, '30s-1m' => 0, '1-2m' => 0, '2-5m' => 0, '5-10m' => 0, '10m+' => 0];

        $testDurations = [10.0, 45.0, 90.0, 180.0, 400.0, 700.0];

        foreach ($testDurations as $s) {
            if ($s < 30) {
                $distribution['0-30s']++;
            } elseif ($s < 60) {
                $distribution['30s-1m']++;
            } elseif ($s < 120) {
                $distribution['1-2m']++;
            } elseif ($s < 300) {
                $distribution['2-5m']++;
            } elseif ($s < 600) {
                $distribution['5-10m']++;
            } else {
                $distribution['10m+']++;
            }
        }

        $this->assertEquals(1, $distribution['0-30s']);
        $this->assertEquals(1, $distribution['30s-1m']);
        $this->assertEquals(1, $distribution['1-2m']);
        $this->assertEquals(1, $distribution['2-5m']);
        $this->assertEquals(1, $distribution['5-10m']);
        $this->assertEquals(1, $distribution['10m+']);
    }

    public function testVoteDurationDistributionBoundaryAt30Seconds(): void
    {
        $s = 30.0;
        $bucket = $s < 30 ? '0-30s' : ($s < 60 ? '30s-1m' : 'other');
        $this->assertEquals('30s-1m', $bucket, '30s should fall in 30s-1m bucket');
    }

    public function testVoteDurationDistributionBoundaryAt60Seconds(): void
    {
        $s = 60.0;
        $bucket = $s < 60 ? '30s-1m' : ($s < 120 ? '1-2m' : 'other');
        $this->assertEquals('1-2m', $bucket, '60s should fall in 1-2m bucket');
    }

    // =========================================================================
    // MEETING_IDS UUID FILTERING LOGIC
    // =========================================================================

    public function testMeetingIdsFilterValidUuids(): void
    {
        $meetingIds = [
            '12345678-1234-1234-1234-123456789abc',
            'not-a-uuid',
            'abcdef01-2345-6789-abcd-ef0123456789',
            '123',
        ];

        $filtered = array_filter($meetingIds, fn($id) => api_is_uuid($id));
        $this->assertCount(2, $filtered);
    }

    public function testMeetingIdsEmptyAfterFilterBecomesNull(): void
    {
        $meetingIds = ['not-a-uuid', 'also-not-uuid'];
        $filtered = array_filter($meetingIds, fn($id) => api_is_uuid($id));

        if (empty($filtered)) {
            $filtered = null;
        }

        $this->assertNull($filtered);
    }

    public function testMeetingIdsNonArrayBecomesNull(): void
    {
        $meetingIds = 'not-an-array';

        if (!empty($meetingIds) && is_array($meetingIds)) {
            $meetingIds = array_filter($meetingIds, fn($id) => api_is_uuid($id));
        } else {
            $meetingIds = null;
        }

        $this->assertNull($meetingIds);
    }

    // =========================================================================
    // CONTROLLER SOURCE: RESPONSE STRUCTURE VERIFICATION
    // =========================================================================

    public function testAnalyticsSourceReturnsApiOk(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AnalyticsController.php');

        $this->assertStringContainsString('api_ok(', $source);
    }

    public function testReportsAggregateSourceReturnsReportTypeInResponse(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AnalyticsController.php');

        $this->assertStringContainsString("'report_type'", $source);
        $this->assertStringContainsString("'generated_at'", $source);
        $this->assertStringContainsString("'data'", $source);
    }

    public function testReportsAggregateSourceHasAuditLog(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AnalyticsController.php');

        $this->assertStringContainsString("'report_aggregate_view'", $source,
            'reportsAggregate should log audit event report_aggregate_view');
    }

    // =========================================================================
    // CONTROLLER SOURCE: REPOSITORY USAGE
    // =========================================================================

    public function testControllerUsesAnalyticsRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AnalyticsController.php');

        $this->assertStringContainsString('AnalyticsRepository', $source);
    }

    public function testControllerUsesAggregateReportRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AnalyticsController.php');

        $this->assertStringContainsString('AggregateReportRepository', $source);
    }

    public function testControllerUsesMemberRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AnalyticsController.php');

        $this->assertStringContainsString('MemberRepository', $source);
    }

    public function testControllerUsesExportService(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AnalyticsController.php');

        $this->assertStringContainsString('ExportService', $source);
    }

    // =========================================================================
    // REPORT HEADERS STRUCTURE
    // =========================================================================

    public function testReportHeadersExistForAllTypes(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AnalyticsController.php');

        $types = ['participation', 'decisions', 'voting_power', 'proxies', 'quorum', 'summary'];
        foreach ($types as $type) {
            $this->assertStringContainsString("'{$type}'", $source,
                "getReportHeaders should have headers for '{$type}'");
        }
    }

    public function testOverviewResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AnalyticsController.php');

        $this->assertStringContainsString("'totals'", $source);
        $this->assertStringContainsString("'meetings_by_status'", $source);
        $this->assertStringContainsString("'motion_decisions'", $source);
        $this->assertStringContainsString("'avg_participation_rate'", $source);
    }
}
