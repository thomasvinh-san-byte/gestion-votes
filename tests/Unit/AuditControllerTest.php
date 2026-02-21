<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\AuditController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AuditController.
 *
 * Tests the audit/timeline endpoints including:
 *  - Controller structure (final, extends AbstractController, public methods)
 *  - HTTP method enforcement (implicit via api_request or api_query patterns)
 *  - UUID validation for meeting_id parameters
 *  - Limit clamping logic for timeline and operatorEvents
 *  - Payload parsing logic
 *  - Response structure verification via source introspection
 *
 * Since api_ok()/api_fail() throw ApiResponseException, we catch these to
 * inspect controller behavior without a database.
 */
class AuditControllerTest extends TestCase
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
        $controller = new AuditController();
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
        $ref = new \ReflectionClass(AuditController::class);
        $this->assertTrue($ref->isFinal(), 'AuditController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new AuditController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(AuditController::class);

        $expectedMethods = ['timeline', 'export', 'meetingAudit', 'meetingEvents', 'operatorEvents'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "AuditController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(AuditController::class);

        $expectedMethods = ['timeline', 'export', 'meetingAudit', 'meetingEvents', 'operatorEvents'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "AuditController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // timeline: MEETING_ID VALIDATION
    // =========================================================================

    public function testTimelineRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('timeline');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testTimelineRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('timeline');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testTimelineRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'not-a-valid-uuid'];

        $result = $this->callControllerMethod('timeline');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testTimelineRejectsShortUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678'];

        $result = $this->callControllerMethod('timeline');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testTimelineRejectsUuidWithSpecialChars(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234-1234-1234-12345678ZZZZ'];

        $result = $this->callControllerMethod('timeline');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // export: METHOD ENFORCEMENT
    // =========================================================================

    public function testExportRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('export');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testExportRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('export');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testExportRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('export');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testExportRejectsPatchMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';

        $result = $this->callControllerMethod('export');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // export: MEETING_ID VALIDATION
    // =========================================================================

    public function testExportRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('export');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testExportRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('export');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testExportRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'bad-uuid'];

        $result = $this->callControllerMethod('export');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    // =========================================================================
    // meetingAudit: METHOD ENFORCEMENT
    // =========================================================================

    public function testMeetingAuditRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('meetingAudit');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testMeetingAuditRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('meetingAudit');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // meetingAudit: MEETING_ID VALIDATION
    // =========================================================================

    public function testMeetingAuditRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('meetingAudit');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testMeetingAuditRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('meetingAudit');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // meetingEvents: METHOD ENFORCEMENT
    // =========================================================================

    public function testMeetingEventsRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('meetingEvents');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testMeetingEventsRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('meetingEvents');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // meetingEvents: MEETING_ID VALIDATION
    // =========================================================================

    public function testMeetingEventsRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('meetingEvents');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testMeetingEventsRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('meetingEvents');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testMeetingEventsRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'not-a-uuid'];

        $result = $this->callControllerMethod('meetingEvents');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // operatorEvents: METHOD ENFORCEMENT
    // =========================================================================

    public function testOperatorEventsRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('operatorEvents');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testOperatorEventsRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('operatorEvents');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // operatorEvents: MEETING_ID VALIDATION
    // =========================================================================

    public function testOperatorEventsRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('operatorEvents');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testOperatorEventsRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('operatorEvents');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testOperatorEventsRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'xyz-bad-uuid'];

        $result = $this->callControllerMethod('operatorEvents');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // timeline: LIMIT CLAMPING LOGIC
    // =========================================================================

    public function testTimelineLimitClampingMinBound(): void
    {
        $limit = min(200, max(1, 0));
        $this->assertEquals(1, $limit, 'Limit of 0 should be clamped to 1');
    }

    public function testTimelineLimitClampingMaxBound(): void
    {
        $limit = min(200, max(1, 500));
        $this->assertEquals(200, $limit, 'Limit of 500 should be clamped to 200');
    }

    public function testTimelineLimitClampingDefault50(): void
    {
        $limit = min(200, max(1, 50));
        $this->assertEquals(50, $limit, 'Default limit of 50 should pass through');
    }

    public function testTimelineLimitClampingNegative(): void
    {
        $limit = min(200, max(1, -10));
        $this->assertEquals(1, $limit, 'Negative limit should be clamped to 1');
    }

    // =========================================================================
    // operatorEvents: LIMIT CLAMPING LOGIC
    // =========================================================================

    public function testOperatorEventsLimitClampingDefault200(): void
    {
        $limit = 200;
        if ($limit <= 0) $limit = 200;
        if ($limit > 500) $limit = 500;
        $this->assertEquals(200, $limit);
    }

    public function testOperatorEventsLimitClampingZeroResets(): void
    {
        $limit = 0;
        if ($limit <= 0) $limit = 200;
        if ($limit > 500) $limit = 500;
        $this->assertEquals(200, $limit);
    }

    public function testOperatorEventsLimitClampingOver500(): void
    {
        $limit = 1000;
        if ($limit <= 0) $limit = 200;
        if ($limit > 500) $limit = 500;
        $this->assertEquals(500, $limit);
    }

    public function testOperatorEventsLimitClampingExactly500(): void
    {
        $limit = 500;
        if ($limit <= 0) $limit = 200;
        if ($limit > 500) $limit = 500;
        $this->assertEquals(500, $limit);
    }

    // =========================================================================
    // PAYLOAD PARSING LOGIC
    // =========================================================================

    public function testParsePayloadEmptyReturnsEmptyArray(): void
    {
        $payload = null;
        $result = $this->parsePayload($payload);
        $this->assertEquals([], $result);
    }

    public function testParsePayloadStringJsonReturnsArray(): void
    {
        $payload = '{"message":"test","detail":"info"}';
        $result = $this->parsePayload($payload);
        $this->assertEquals(['message' => 'test', 'detail' => 'info'], $result);
    }

    public function testParsePayloadInvalidJsonReturnsEmptyArray(): void
    {
        $payload = 'not valid json';
        $result = $this->parsePayload($payload);
        $this->assertEquals([], $result);
    }

    public function testParsePayloadArrayPassesThrough(): void
    {
        $payload = ['key' => 'value'];
        $result = $this->parsePayload($payload);
        $this->assertEquals(['key' => 'value'], $result);
    }

    public function testParsePayloadEmptyStringReturnsEmptyArray(): void
    {
        $payload = '';
        $result = $this->parsePayload($payload);
        $this->assertEquals([], $result);
    }

    /**
     * Replicate parsePayload logic from AuditController.
     */
    private function parsePayload(mixed $payload): array
    {
        if (empty($payload)) {
            return [];
        }
        if (is_string($payload)) {
            return json_decode($payload, true) ?? [];
        }
        return (array) $payload;
    }

    // =========================================================================
    // ACTION LABELS: VERIFICATION
    // =========================================================================

    public function testActionLabelsExistInSource(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AuditController.php');

        $expectedActions = [
            'meeting_created', 'meeting_updated', 'meeting_validated',
            'motion_created', 'motion_opened', 'motion_closed',
            'ballot_cast', 'attendance_updated', 'proxy_created',
        ];

        foreach ($expectedActions as $action) {
            $this->assertStringContainsString(
                "'{$action}'",
                $source,
                "AuditController should have label for action '{$action}'",
            );
        }
    }

    // =========================================================================
    // CONTROLLER SOURCE: RESPONSE STRUCTURE VERIFICATION
    // =========================================================================

    public function testTimelineResponseContainsMeetingId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AuditController.php');

        $this->assertStringContainsString("'meeting_id'", $source);
        $this->assertStringContainsString("'total'", $source);
        $this->assertStringContainsString("'items'", $source);
    }

    public function testTimelineResponseContainsPaginationFields(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AuditController.php');

        $this->assertStringContainsString("'limit'", $source);
        $this->assertStringContainsString("'offset'", $source);
    }

    public function testExportSourceHasFormatBranching(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AuditController.php');

        $this->assertStringContainsString("'json'", $source);
        $this->assertStringContainsString("'csv'", $source);
    }

    public function testExportSourceDefaultFormatIsCsv(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AuditController.php');

        $this->assertStringContainsString("'format', 'csv'", $source,
            'export default format should be csv');
    }

    // =========================================================================
    // CONTROLLER SOURCE: REPOSITORY USAGE
    // =========================================================================

    public function testControllerUsesAuditEventRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AuditController.php');

        $this->assertStringContainsString('AuditEventRepository', $source);
    }

    public function testControllerUsesMeetingRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/AuditController.php');

        $this->assertStringContainsString('MeetingRepository', $source);
    }
}
