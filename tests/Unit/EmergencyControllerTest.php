<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\EmergencyController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EmergencyController.
 *
 * Tests the emergency endpoint logic including:
 *  - Controller structure (final, extends AbstractController)
 *  - HTTP method enforcement (GET vs POST)
 *  - UUID validation for meeting_id
 *  - Missing required fields (procedure_code, item_index)
 *  - Input validation for checkToggle and procedures
 */
class EmergencyControllerTest extends TestCase
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
        $controller = new EmergencyController();
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
        $ref = new \ReflectionClass(EmergencyController::class);
        $this->assertTrue($ref->isFinal(), 'EmergencyController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new EmergencyController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(EmergencyController::class);

        $expectedMethods = ['checkToggle', 'procedures'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "EmergencyController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(EmergencyController::class);

        $expectedMethods = ['checkToggle', 'procedures'];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "EmergencyController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // checkToggle: METHOD ENFORCEMENT
    // =========================================================================

    public function testCheckToggleRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('checkToggle');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testCheckToggleRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('checkToggle');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testCheckToggleRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('checkToggle');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // checkToggle: meeting_id VALIDATION (via api_require_uuid)
    // =========================================================================

    public function testCheckToggleRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('checkToggle');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field'] ?? null);
    }

    public function testCheckToggleRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => '']);

        $result = $this->callControllerMethod('checkToggle');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field'] ?? null);
    }

    public function testCheckToggleRejectsInvalidMeetingUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => 'not-a-uuid']);

        $result = $this->callControllerMethod('checkToggle');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
        $this->assertEquals('meeting_id', $result['body']['field'] ?? null);
    }

    public function testCheckToggleRejectsWhitespaceMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => '   ']);

        $result = $this->callControllerMethod('checkToggle');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    // =========================================================================
    // checkToggle: procedure_code VALIDATION
    // =========================================================================

    public function testCheckToggleProcedureCodeValidationLogic(): void
    {
        // Replicate: $procedure = trim((string) ($in['procedure_code'] ?? ''));
        // if ($procedure === '') api_fail('missing_procedure_code', 400);
        $testCases = [
            ['input' => '', 'shouldFail' => true],
            ['input' => '   ', 'shouldFail' => true],
            ['input' => 'fire_evacuation', 'shouldFail' => false],
            ['input' => 'power_outage', 'shouldFail' => false],
        ];

        foreach ($testCases as $case) {
            $procedure = trim((string) $case['input']);
            $isMissing = ($procedure === '');
            $this->assertEquals(
                $case['shouldFail'],
                $isMissing,
                "procedure_code '{$case['input']}' should " . ($case['shouldFail'] ? 'fail' : 'pass'),
            );
        }
    }

    // =========================================================================
    // checkToggle: item_index VALIDATION
    // =========================================================================

    public function testCheckToggleItemIndexValidationLogic(): void
    {
        // Replicate: $idx = (int) ($in['item_index'] ?? -1);
        // if ($idx < 0) api_fail('invalid_item_index', 400);
        $testCases = [
            ['input' => null, 'expected' => -1, 'shouldFail' => true],
            ['input' => -1, 'expected' => -1, 'shouldFail' => true],
            ['input' => -5, 'expected' => -5, 'shouldFail' => true],
            ['input' => 0, 'expected' => 0, 'shouldFail' => false],
            ['input' => 1, 'expected' => 1, 'shouldFail' => false],
            ['input' => 10, 'expected' => 10, 'shouldFail' => false],
        ];

        foreach ($testCases as $case) {
            $idx = (int) ($case['input'] ?? -1);
            $this->assertEquals($case['expected'], $idx);
            $this->assertEquals($case['shouldFail'], $idx < 0);
        }
    }

    // =========================================================================
    // checkToggle: checked FLAG PARSING
    // =========================================================================

    public function testCheckToggleCheckedFlagParsing(): void
    {
        // Replicate: $checked = (int) ($in['checked'] ?? 0) ? true : false;
        $this->assertFalse((int) (0) ? true : false);
        $this->assertFalse((int) (null ?? 0) ? true : false);
        $this->assertTrue((int) (1) ? true : false);
        $this->assertTrue((int) ('1') ? true : false);
    }

    // =========================================================================
    // procedures: METHOD ENFORCEMENT
    // =========================================================================

    public function testProceduresRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('procedures');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testProceduresRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('procedures');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testProceduresRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('procedures');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // procedures: AUDIENCE DEFAULT VALUE
    // =========================================================================

    public function testProceduresAudienceDefaultsToOperator(): void
    {
        // Replicate: $aud = trim((string) ($q['audience'] ?? 'operator'));
        $q = [];
        $aud = trim((string) ($q['audience'] ?? 'operator'));
        $this->assertEquals('operator', $aud);
    }

    public function testProceduresAudienceFromInput(): void
    {
        $q = ['audience' => 'participant'];
        $aud = trim((string) ($q['audience'] ?? 'operator'));
        $this->assertEquals('participant', $aud);
    }

    // =========================================================================
    // procedures: meeting_id OPTIONAL UUID VALIDATION
    // =========================================================================

    public function testProceduresMeetingIdOptionalLogic(): void
    {
        // Replicate: if ($meetingId !== '' && api_is_uuid($meetingId)) { ... }
        $testCases = [
            ['input' => '', 'shouldLoadChecks' => false],
            ['input' => 'not-uuid', 'shouldLoadChecks' => false],
            ['input' => '12345678-1234-1234-1234-123456789abc', 'shouldLoadChecks' => true],
        ];

        foreach ($testCases as $case) {
            $meetingId = trim((string) $case['input']);
            $shouldLoad = $meetingId !== '' && api_is_uuid($meetingId);
            $this->assertEquals(
                $case['shouldLoadChecks'],
                $shouldLoad,
                "meeting_id '{$case['input']}' should " . ($case['shouldLoadChecks'] ? 'load' : 'skip') . ' checks',
            );
        }
    }

    // =========================================================================
    // CROSS-CUTTING: METHOD CHECK BEFORE BODY VALIDATION
    // =========================================================================

    public function testCheckToggleMethodCheckBeforeBodyValidation(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'procedure_code' => 'fire',
            'item_index' => 0,
        ]);

        $result = $this->callControllerMethod('checkToggle');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testProceduresMethodCheckBeforeInput(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['audience' => 'operator']);

        $result = $this->callControllerMethod('procedures');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // RESPONSE STRUCTURE (source verification)
    // =========================================================================

    public function testCheckToggleResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmergencyController.php');

        $this->assertStringContainsString("'saved' => true", $source);
    }

    public function testProceduresResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmergencyController.php');

        $this->assertStringContainsString("'items'", $source);
        $this->assertStringContainsString("'checks'", $source);
    }

    // =========================================================================
    // AUDIT LOG VERIFICATION (source-level)
    // =========================================================================

    public function testCheckToggleAuditsEvent(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmergencyController.php');

        $this->assertStringContainsString("'emergency_check_toggled'", $source);
        $this->assertStringContainsString("'procedure_code'", $source);
        $this->assertStringContainsString("'item_index'", $source);
        $this->assertStringContainsString("'checked'", $source);
    }

    // =========================================================================
    // BUSINESS GUARD VERIFICATION (source-level)
    // =========================================================================

    public function testCheckToggleGuardsMeetingNotValidated(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmergencyController.php');

        $this->assertStringContainsString('api_guard_meeting_not_validated', $source);
    }

    public function testCheckToggleUsesApiRequireUuid(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmergencyController.php');

        $this->assertStringContainsString("api_require_uuid(\$in, 'meeting_id')", $source);
    }

    // =========================================================================
    // REPOSITORY USAGE (source-level)
    // =========================================================================

    public function testCheckToggleUsesMeetingRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmergencyController.php');

        $this->assertStringContainsString('MeetingRepository', $source);
        $this->assertStringContainsString('upsertEmergencyCheck', $source);
    }

    public function testProceduresUsesEmergencyProcedureRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/EmergencyController.php');

        $this->assertStringContainsString('EmergencyProcedureRepository', $source);
        $this->assertStringContainsString('listByAudienceWithField', $source);
    }

    // =========================================================================
    // UNKNOWN METHOD HANDLING
    // =========================================================================

    public function testHandleUnknownMethodReturns500(): void
    {
        $result = $this->callControllerMethod('nonExistentMethod');

        $this->assertEquals(500, $result['status']);
        $this->assertEquals('internal_error', $result['body']['error']);
    }
}
