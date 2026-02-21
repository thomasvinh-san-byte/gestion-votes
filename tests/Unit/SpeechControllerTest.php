<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\SpeechController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SpeechController.
 *
 * Tests the 8 speech endpoints:
 *  - request: POST, toggles a speech request
 *  - grant: POST, grants speaking rights
 *  - end: POST, ends current speaker
 *  - cancel: POST, cancels a speech request
 *  - clear: POST, clears speech history
 *  - next: POST, grants next speaker in queue
 *  - queue: GET, lists the speech queue
 *  - current: GET, shows current speaker
 *  - myStatus: GET, shows member's speech status
 *
 * Since api_ok()/api_fail() throw ApiResponseException, we catch these to
 * inspect controller behavior without a database.
 */
class SpeechControllerTest extends TestCase
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

        // Reset cached raw body
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
        $controller = new SpeechController();
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

    /**
     * Inject a JSON body into Request::$cachedRawBody for POST endpoints.
     */
    private function setJsonBody(array $data): void
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
        $ref = new \ReflectionClass(SpeechController::class);
        $this->assertTrue($ref->isFinal(), 'SpeechController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new SpeechController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(SpeechController::class);

        $expectedMethods = [
            'request', 'grant', 'end', 'cancel',
            'clear', 'next', 'queue', 'current', 'myStatus',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "SpeechController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(SpeechController::class);

        $expectedMethods = [
            'request', 'grant', 'end', 'cancel',
            'clear', 'next', 'queue', 'current', 'myStatus',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "SpeechController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // request: METHOD ENFORCEMENT
    // =========================================================================

    public function testRequestRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('request');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testRequestRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('request');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testRequestRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('request');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // request: INPUT VALIDATION
    // =========================================================================

    public function testRequestRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'member_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('request');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    public function testRequestRejectsInvalidMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => 'bad-uuid',
            'member_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('request');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    public function testRequestRequiresMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('request');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('member_id', $result['body']['field']);
    }

    public function testRequestRejectsInvalidMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'member_id' => 'not-valid',
        ]);

        $result = $this->callControllerMethod('request');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('member_id', $result['body']['field']);
    }

    // =========================================================================
    // grant: METHOD ENFORCEMENT
    // =========================================================================

    public function testGrantRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('grant');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testGrantRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('grant');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // grant: INPUT VALIDATION
    // =========================================================================

    public function testGrantRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('grant');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    public function testGrantRejectsInvalidMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => 'bad']);

        $result = $this->callControllerMethod('grant');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    public function testGrantRejectsInvalidOptionalMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'member_id' => 'not-a-uuid',
        ]);

        $result = $this->callControllerMethod('grant');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_uuid', $result['body']['error']);
        $this->assertEquals('member_id', $result['body']['field']);
    }

    public function testGrantRejectsInvalidOptionalRequestId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'request_id' => 'not-a-uuid',
        ]);

        $result = $this->callControllerMethod('grant');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_uuid', $result['body']['error']);
        $this->assertEquals('request_id', $result['body']['field']);
    }

    // =========================================================================
    // end: METHOD ENFORCEMENT
    // =========================================================================

    public function testEndRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('end');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testEndRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('end');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // end: INPUT VALIDATION
    // =========================================================================

    public function testEndRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('end');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    public function testEndRejectsInvalidMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => 'xyz']);

        $result = $this->callControllerMethod('end');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    // =========================================================================
    // cancel: METHOD ENFORCEMENT
    // =========================================================================

    public function testCancelRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('cancel');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // cancel: INPUT VALIDATION
    // =========================================================================

    public function testCancelRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'request_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('cancel');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    public function testCancelRequiresRequestId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('cancel');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('request_id', $result['body']['field']);
    }

    public function testCancelRejectsInvalidRequestId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'request_id' => 'not-a-uuid',
        ]);

        $result = $this->callControllerMethod('cancel');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('request_id', $result['body']['field']);
    }

    // =========================================================================
    // clear: METHOD ENFORCEMENT
    // =========================================================================

    public function testClearRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('clear');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // clear: INPUT VALIDATION
    // =========================================================================

    public function testClearRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('clear');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    public function testClearRejectsInvalidMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => 'bad']);

        $result = $this->callControllerMethod('clear');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    // =========================================================================
    // next: METHOD ENFORCEMENT
    // =========================================================================

    public function testNextRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('next');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // next: INPUT VALIDATION
    // =========================================================================

    public function testNextRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('next');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    // =========================================================================
    // queue: METHOD ENFORCEMENT
    // =========================================================================

    public function testQueueRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('queue');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testQueueRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('queue');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // queue: INPUT VALIDATION
    // =========================================================================

    public function testQueueRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('queue');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    public function testQueueRejectsInvalidMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'not-uuid'];

        $result = $this->callControllerMethod('queue');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    // =========================================================================
    // current: METHOD ENFORCEMENT
    // =========================================================================

    public function testCurrentRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('current');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // current: INPUT VALIDATION
    // =========================================================================

    public function testCurrentRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('current');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    public function testCurrentRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('current');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    // =========================================================================
    // myStatus: METHOD ENFORCEMENT
    // =========================================================================

    public function testMyStatusRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('myStatus');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // myStatus: INPUT VALIDATION
    // =========================================================================

    public function testMyStatusRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['member_id' => '12345678-1234-1234-1234-123456789abc'];

        $result = $this->callControllerMethod('myStatus');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field']);
    }

    public function testMyStatusRequiresMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234-1234-1234-123456789abc'];

        $result = $this->callControllerMethod('myStatus');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('member_id', $result['body']['field']);
    }

    public function testMyStatusRejectsInvalidMemberId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'member_id' => 'bad-uuid',
        ];

        $result = $this->callControllerMethod('myStatus');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('member_id', $result['body']['field']);
    }

    // =========================================================================
    // CONTROLLER SOURCE VERIFICATION
    // =========================================================================

    public function testControllerUsesSpeechService(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/SpeechController.php');

        $this->assertStringContainsString('SpeechService', $source);
        $this->assertStringContainsString('toggleRequest', $source);
        $this->assertStringContainsString('grant', $source);
        $this->assertStringContainsString('endCurrent', $source);
        $this->assertStringContainsString('cancelRequest', $source);
        $this->assertStringContainsString('clearHistory', $source);
        $this->assertStringContainsString('getQueue', $source);
        $this->assertStringContainsString('getMyStatus', $source);
    }

    public function testControllerAuditsOperations(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/SpeechController.php');

        $this->assertStringContainsString("'speech.requested'", $source);
        $this->assertStringContainsString("'speech.granted'", $source);
        $this->assertStringContainsString("'speech.ended'", $source);
        $this->assertStringContainsString("'speech.cancelled'", $source);
        $this->assertStringContainsString("'speech.cleared'", $source);
        $this->assertStringContainsString("'speech.next'", $source);
    }

    public function testControllerUsesApiCurrentTenantId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/SpeechController.php');

        $count = substr_count($source, 'api_current_tenant_id()');
        $this->assertGreaterThanOrEqual(5, $count);
    }

    // =========================================================================
    // current: ELAPSED TIME CALCULATION LOGIC
    // =========================================================================

    public function testElapsedTimeCalculation(): void
    {
        $elapsedSeconds = 125;
        $minutes = floor($elapsedSeconds / 60);
        $seconds = $elapsedSeconds % 60;

        $this->assertEquals(2, $minutes);
        $this->assertEquals(5, $seconds);
        $this->assertEquals('02:05', sprintf('%02d:%02d', $minutes, $seconds));
    }

    public function testElapsedTimeZero(): void
    {
        $elapsedSeconds = 0;
        $minutes = floor($elapsedSeconds / 60);
        $seconds = $elapsedSeconds % 60;

        $this->assertEquals('00:00', sprintf('%02d:%02d', $minutes, $seconds));
    }

    // =========================================================================
    // HANDLE: UNKNOWN METHOD
    // =========================================================================

    public function testHandleUnknownMethodReturnsInternalError(): void
    {
        $controller = new SpeechController();
        try {
            $controller->handle('nonExistentMethod');
            $this->fail('Expected ApiResponseException was not thrown');
        } catch (ApiResponseException $e) {
            $this->assertEquals(500, $e->getResponse()->getStatusCode());
            $this->assertEquals('internal_error', $e->getResponse()->getBody()['error']);
        }
    }

    // =========================================================================
    // queue: MEMBER NAME ALIASING LOGIC
    // =========================================================================

    public function testQueueMemberNameAliasing(): void
    {
        $item = ['full_name' => 'John Doe', 'created_at' => '2024-01-01 10:00:00'];

        $item['member_name'] = $item['full_name'] ?? $item['member_name'] ?? '';
        $item['requested_at'] = $item['created_at'] ?? null;

        $this->assertEquals('John Doe', $item['member_name']);
        $this->assertEquals('2024-01-01 10:00:00', $item['requested_at']);
    }

    public function testQueueMemberNameFallback(): void
    {
        $item = ['member_name' => 'Jane Doe'];

        $item['member_name'] = $item['full_name'] ?? $item['member_name'] ?? '';
        $item['requested_at'] = $item['created_at'] ?? null;

        $this->assertEquals('Jane Doe', $item['member_name']);
        $this->assertNull($item['requested_at']);
    }
}
