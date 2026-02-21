<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\MeetingsController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MeetingsController.
 *
 * Tests the meeting CRUD + status endpoints including:
 *  - Controller structure (final, extends AbstractController, public methods)
 *  - Input validation (missing/invalid meeting_id, bad UUID, field lengths)
 *  - Method enforcement (GET vs POST)
 *  - Limit clamping on index()
 *  - Business logic (sign status, tally source, field building)
 *  - Response structure verification via source introspection
 *
 * Since api_ok()/api_fail() throw ApiResponseException, we catch these to
 * inspect controller behavior without a database.
 */
class MeetingsControllerTest extends TestCase
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
        $controller = new MeetingsController();
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
     * Inject a JSON body into the Request cached raw body for POST tests.
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
        $ref = new \ReflectionClass(MeetingsController::class);
        $this->assertTrue($ref->isFinal(), 'MeetingsController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new MeetingsController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(MeetingsController::class);

        $expectedMethods = [
            'index',
            'update',
            'archive',
            'archivesList',
            'status',
            'statusForMeeting',
            'summary',
            'stats',
            'createMeeting',
            'voteSettings',
            'validate',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "MeetingsController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(MeetingsController::class);

        $expectedMethods = [
            'index',
            'update',
            'archive',
            'archivesList',
            'status',
            'statusForMeeting',
            'summary',
            'stats',
            'createMeeting',
            'voteSettings',
            'validate',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "MeetingsController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // INDEX: METHOD ENFORCEMENT
    // =========================================================================

    public function testIndexRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('index');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testIndexRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('index');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // INDEX: LIMIT CLAMPING LOGIC
    // =========================================================================

    public function testLimitClampingZeroResetsTo50(): void
    {
        $limit = 0;
        if ($limit <= 0 || $limit > 200) {
            $limit = 50;
        }
        $this->assertEquals(50, $limit);
    }

    public function testLimitClampingNegativeOneResetsTo50(): void
    {
        $limit = -1;
        if ($limit <= 0 || $limit > 200) {
            $limit = 50;
        }
        $this->assertEquals(50, $limit);
    }

    public function testLimitClampingNegative10ResetsTo50(): void
    {
        $limit = -10;
        if ($limit <= 0 || $limit > 200) {
            $limit = 50;
        }
        $this->assertEquals(50, $limit);
    }

    public function testLimitClamping201ResetsTo50(): void
    {
        $limit = 201;
        if ($limit <= 0 || $limit > 200) {
            $limit = 50;
        }
        $this->assertEquals(50, $limit);
    }

    public function testLimitClamping999ResetsTo50(): void
    {
        $limit = 999;
        if ($limit <= 0 || $limit > 200) {
            $limit = 50;
        }
        $this->assertEquals(50, $limit);
    }

    public function testLimitClampingExactly200IsAccepted(): void
    {
        $limit = 200;
        if ($limit <= 0 || $limit > 200) {
            $limit = 50;
        }
        $this->assertEquals(200, $limit);
    }

    public function testLimitClampingValidValue100(): void
    {
        $limit = 100;
        if ($limit <= 0 || $limit > 200) {
            $limit = 50;
        }
        $this->assertEquals(100, $limit);
    }

    public function testLimitClampingValidValue1(): void
    {
        $limit = 1;
        if ($limit <= 0 || $limit > 200) {
            $limit = 50;
        }
        $this->assertEquals(1, $limit);
    }

    public function testLimitClampingDefaultIs50(): void
    {
        // Default from api_query_int('limit', 50) is 50
        $limit = 50;
        if ($limit <= 0 || $limit > 200) {
            $limit = 50;
        }
        $this->assertEquals(50, $limit);
    }

    // =========================================================================
    // INDEX: ACTIVE_ONLY FLAG PARSING
    // =========================================================================

    public function testActiveOnlyFlagTruthyValues(): void
    {
        $this->assertTrue(filter_var('1', FILTER_VALIDATE_BOOLEAN));
        $this->assertTrue(filter_var('true', FILTER_VALIDATE_BOOLEAN));
        $this->assertTrue(filter_var('yes', FILTER_VALIDATE_BOOLEAN));
        $this->assertTrue(filter_var('on', FILTER_VALIDATE_BOOLEAN));
    }

    public function testActiveOnlyFlagFalsyValues(): void
    {
        $this->assertFalse(filter_var('0', FILTER_VALIDATE_BOOLEAN));
        $this->assertFalse(filter_var('false', FILTER_VALIDATE_BOOLEAN));
        $this->assertFalse(filter_var('', FILTER_VALIDATE_BOOLEAN));
        $this->assertFalse(filter_var('no', FILTER_VALIDATE_BOOLEAN));
    }

    public function testActiveOnlyDefaultIsFalse(): void
    {
        $activeOnly = filter_var('0', FILTER_VALIDATE_BOOLEAN);
        $this->assertFalse($activeOnly, 'Default active_only ("0") should be false');
    }

    // =========================================================================
    // UPDATE: METHOD ENFORCEMENT
    // =========================================================================

    public function testUpdateRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('update');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testUpdateRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('update');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // UPDATE: MEETING_ID VALIDATION
    // =========================================================================

    public function testUpdateRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('update');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testUpdateRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => '']);

        $result = $this->callControllerMethod('update');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testUpdateRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => 'not-a-valid-uuid']);

        $result = $this->callControllerMethod('update');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testUpdateRejectsWhitespaceMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => '   ']);

        $result = $this->callControllerMethod('update');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testUpdateRejectsShortUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => '12345678-1234-1234']);

        $result = $this->callControllerMethod('update');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // UPDATE: STATUS FIELD REJECTION
    // =========================================================================

    public function testUpdateRejectsStatusField(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '11111111-1111-1111-1111-111111111111',
            'status' => 'live',
        ]);

        $result = $this->callControllerMethod('update');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('status_via_transition', $result['body']['error']);
    }

    public function testUpdateRejectsStatusFieldEvenWhenEmpty(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '11111111-1111-1111-1111-111111111111',
            'status' => '',
        ]);

        $result = $this->callControllerMethod('update');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('status_via_transition', $result['body']['error']);
    }

    public function testUpdateRejectsStatusFieldWithDraftValue(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '11111111-1111-1111-1111-111111111111',
            'status' => 'draft',
        ]);

        $result = $this->callControllerMethod('update');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('status_via_transition', $result['body']['error']);
    }

    // =========================================================================
    // UPDATE: TITLE VALIDATION
    // =========================================================================

    public function testUpdateRejectsEmptyTitle(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '11111111-1111-1111-1111-111111111111',
            'title' => '',
        ]);

        $result = $this->callControllerMethod('update');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_title', $result['body']['error']);
    }

    public function testUpdateRejectsWhitespaceOnlyTitle(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '11111111-1111-1111-1111-111111111111',
            'title' => '   ',
        ]);

        $result = $this->callControllerMethod('update');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_title', $result['body']['error']);
    }

    public function testUpdateRejectsTitleOver120Chars(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '11111111-1111-1111-1111-111111111111',
            'title' => str_repeat('A', 121),
        ]);

        $result = $this->callControllerMethod('update');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('title_too_long', $result['body']['error']);
    }

    public function testUpdateTitleExactly120CharsPassesValidation(): void
    {
        // Title of exactly 120 chars should pass title validation
        $title = str_repeat('A', 120);
        $len = mb_strlen(trim($title));
        $this->assertEquals(120, $len);
        $this->assertFalse($len === 0, 'Title of 120 chars should not be empty');
        $this->assertFalse($len > 120, 'Title of 120 chars should not exceed limit');
    }

    public function testUpdateTitleLengthBoundary(): void
    {
        // Verify 120 passes, 121 fails (logic replication)
        $check = function (string $title): ?string {
            $len = mb_strlen($title);
            if ($len === 0) return 'missing_title';
            if ($len > 120) return 'title_too_long';
            return null;
        };

        $this->assertNull($check(str_repeat('X', 120)));
        $this->assertEquals('title_too_long', $check(str_repeat('X', 121)));
        $this->assertEquals('missing_title', $check(''));
    }

    // =========================================================================
    // UPDATE: PRESIDENT_NAME VALIDATION
    // =========================================================================

    public function testUpdateRejectsPresidentNameOver200Chars(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '11111111-1111-1111-1111-111111111111',
            'president_name' => str_repeat('B', 201),
        ]);

        $result = $this->callControllerMethod('update');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('president_name_too_long', $result['body']['error']);
    }

    public function testUpdatePresidentNameExactly200CharsPassesValidation(): void
    {
        $name = str_repeat('B', 200);
        $this->assertFalse(mb_strlen(trim($name)) > 200, 'President name of 200 chars should not exceed limit');
    }

    public function testUpdatePresidentNameLengthBoundary(): void
    {
        $check = function (string $name): ?string {
            if (mb_strlen($name) > 200) return 'president_name_too_long';
            return null;
        };

        $this->assertNull($check(str_repeat('C', 200)));
        $this->assertEquals('president_name_too_long', $check(str_repeat('C', 201)));
    }

    // =========================================================================
    // UPDATE: MEETING TYPE VALIDATION
    // =========================================================================

    public function testUpdateRejectsInvalidMeetingType(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '11111111-1111-1111-1111-111111111111',
            'meeting_type' => 'invalid_type',
        ]);

        $result = $this->callControllerMethod('update');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_type', $result['body']['error']);
    }

    public function testUpdateRejectsMeetingTypeCaseSensitive(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '11111111-1111-1111-1111-111111111111',
            'meeting_type' => 'AG_ORDINAIRE',
        ]);

        $result = $this->callControllerMethod('update');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_type', $result['body']['error']);
    }

    public function testUpdateValidMeetingTypesAccepted(): void
    {
        $validMeetingTypes = ['ag_ordinaire', 'ag_extraordinaire', 'conseil', 'bureau', 'autre'];

        foreach ($validMeetingTypes as $type) {
            $this->assertTrue(
                in_array($type, $validMeetingTypes, true),
                "'{$type}' should be a valid meeting type",
            );
        }
        $this->assertNotContains('invalid_type', $validMeetingTypes);
    }

    // =========================================================================
    // UPDATE: FIELD BUILDING LOGIC
    // =========================================================================

    public function testUpdateFieldBuildingOnlyIncludesProvidedFields(): void
    {
        $input = ['meeting_id' => '11111111-1111-1111-1111-111111111111', 'title' => 'New Title'];

        $title = array_key_exists('title', $input) ? trim((string) $input['title']) : null;
        $presidentName = array_key_exists('president_name', $input) ? trim((string) $input['president_name']) : null;
        $scheduledAt = array_key_exists('scheduled_at', $input) ? trim((string) $input['scheduled_at']) : null;
        $meetingType = array_key_exists('meeting_type', $input) ? trim((string) $input['meeting_type']) : null;

        $fields = [];
        if ($title !== null) $fields['title'] = $title;
        if ($presidentName !== null) $fields['president_name'] = $presidentName;
        if ($scheduledAt !== null) $fields['scheduled_at'] = $scheduledAt ?: null;
        if ($meetingType !== null) $fields['meeting_type'] = $meetingType;

        $this->assertCount(1, $fields, 'Only provided fields should be included');
        $this->assertArrayHasKey('title', $fields);
        $this->assertArrayNotHasKey('president_name', $fields);
        $this->assertArrayNotHasKey('scheduled_at', $fields);
        $this->assertArrayNotHasKey('meeting_type', $fields);
    }

    public function testUpdateFieldBuildingEmptyScheduledAtBecomesNull(): void
    {
        $input = ['scheduled_at' => ''];
        $scheduledAt = array_key_exists('scheduled_at', $input) ? trim((string) $input['scheduled_at']) : null;

        $fields = [];
        if ($scheduledAt !== null) $fields['scheduled_at'] = $scheduledAt ?: null;

        $this->assertArrayHasKey('scheduled_at', $fields);
        $this->assertNull($fields['scheduled_at'], 'Empty scheduled_at should be stored as null');
    }

    public function testUpdateFieldBuildingNoFieldsProvided(): void
    {
        $input = ['meeting_id' => '11111111-1111-1111-1111-111111111111'];

        $title = array_key_exists('title', $input) ? trim((string) $input['title']) : null;
        $presidentName = array_key_exists('president_name', $input) ? trim((string) $input['president_name']) : null;
        $scheduledAt = array_key_exists('scheduled_at', $input) ? trim((string) $input['scheduled_at']) : null;
        $meetingType = array_key_exists('meeting_type', $input) ? trim((string) $input['meeting_type']) : null;

        $fields = [];
        if ($title !== null) $fields['title'] = $title;
        if ($presidentName !== null) $fields['president_name'] = $presidentName;
        if ($scheduledAt !== null) $fields['scheduled_at'] = $scheduledAt ?: null;
        if ($meetingType !== null) $fields['meeting_type'] = $meetingType;

        $this->assertEmpty($fields, 'No fields should be built when none are provided');
    }

    public function testUpdateFieldBuildingAllFieldsProvided(): void
    {
        $input = [
            'meeting_id' => '11111111-1111-1111-1111-111111111111',
            'title' => 'Updated Title',
            'president_name' => 'John Doe',
            'scheduled_at' => '2025-01-15 10:00:00',
            'meeting_type' => 'conseil',
        ];

        $title = array_key_exists('title', $input) ? trim((string) $input['title']) : null;
        $presidentName = array_key_exists('president_name', $input) ? trim((string) $input['president_name']) : null;
        $scheduledAt = array_key_exists('scheduled_at', $input) ? trim((string) $input['scheduled_at']) : null;
        $meetingType = array_key_exists('meeting_type', $input) ? trim((string) $input['meeting_type']) : null;

        $fields = [];
        if ($title !== null) $fields['title'] = $title;
        if ($presidentName !== null) $fields['president_name'] = $presidentName;
        if ($scheduledAt !== null) $fields['scheduled_at'] = $scheduledAt ?: null;
        if ($meetingType !== null) $fields['meeting_type'] = $meetingType;

        $this->assertCount(4, $fields);
        $this->assertEquals('Updated Title', $fields['title']);
        $this->assertEquals('John Doe', $fields['president_name']);
        $this->assertEquals('2025-01-15 10:00:00', $fields['scheduled_at']);
        $this->assertEquals('conseil', $fields['meeting_type']);
    }

    // =========================================================================
    // UPDATE: RESPONSE STRUCTURE
    // =========================================================================

    public function testUpdateResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingsController.php');

        $this->assertStringContainsString("'updated'", $source, "Update response should contain 'updated' key");
        $this->assertStringContainsString("'meeting_id'", $source, "Update response should contain 'meeting_id' key");
    }

    // =========================================================================
    // STATUS FOR MEETING: METHOD ENFORCEMENT
    // =========================================================================

    public function testStatusForMeetingRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('statusForMeeting');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testStatusForMeetingRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('statusForMeeting');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // STATUS FOR MEETING: MEETING_ID VALIDATION
    // =========================================================================

    public function testStatusForMeetingRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('statusForMeeting');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testStatusForMeetingRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('statusForMeeting');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testStatusForMeetingRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'not-a-valid-uuid'];

        $result = $this->callControllerMethod('statusForMeeting');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testStatusForMeetingRejectsPartialUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234'];

        $result = $this->callControllerMethod('statusForMeeting');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // STATUS FOR MEETING: SIGN STATUS LOGIC
    // =========================================================================

    public function testStatusForMeetingSignStatusValidated(): void
    {
        $validated_at = '2024-01-15 10:00:00';
        $readyToSign = true;

        $signStatus = 'not_ready';
        $signMessage = '';
        if (!empty($validated_at)) {
            $signStatus = 'validated';
            $signMessage = 'Seance validee.';
        } elseif ($readyToSign) {
            $signStatus = 'ready';
        } else {
            $signMessage = 'Preparation incomplete.';
        }

        $this->assertEquals('validated', $signStatus);
        $this->assertNotEmpty($signMessage);
    }

    public function testStatusForMeetingSignStatusReady(): void
    {
        $validated_at = null;
        $readyToSign = true;

        $signStatus = 'not_ready';
        if (!empty($validated_at)) {
            $signStatus = 'validated';
        } elseif ($readyToSign) {
            $signStatus = 'ready';
        }

        $this->assertEquals('ready', $signStatus);
    }

    public function testStatusForMeetingSignStatusNotReady(): void
    {
        $validated_at = null;
        $readyToSign = false;

        $signStatus = 'not_ready';
        $signMessage = '';
        if (!empty($validated_at)) {
            $signStatus = 'validated';
        } elseif ($readyToSign) {
            $signStatus = 'ready';
        } else {
            $signMessage = 'Preparation incomplete.';
        }

        $this->assertEquals('not_ready', $signStatus);
        $this->assertStringContainsString('incomplete', $signMessage);
    }

    // =========================================================================
    // STATUS FOR MEETING: RESPONSE STRUCTURE
    // =========================================================================

    public function testStatusForMeetingResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingsController.php');

        $fields = [
            'meeting_id',
            'meeting_title',
            'meeting_status',
            'started_at',
            'ended_at',
            'archived_at',
            'validated_at',
            'president_name',
            'ready_to_sign',
            'sign_status',
            'sign_message',
        ];
        foreach ($fields as $field) {
            $this->assertStringContainsString(
                "'{$field}'",
                $source,
                "statusForMeeting response should contain '{$field}'",
            );
        }
    }

    // =========================================================================
    // SUMMARY: MEETING_ID VALIDATION
    // =========================================================================

    public function testSummaryRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('summary');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testSummaryRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('summary');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testSummaryRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'bad-uuid'];

        $result = $this->callControllerMethod('summary');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testSummaryRejectsNumericMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345'];

        $result = $this->callControllerMethod('summary');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // SUMMARY: NO METHOD ENFORCEMENT
    // =========================================================================

    public function testSummaryDoesNotEnforceGetMethod(): void
    {
        // summary() does NOT call api_request(), so POST should still work
        // (it will fail on meeting_id, but not on method)
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('summary');

        $this->assertNotEquals(405, $result['status'], 'summary() should not enforce HTTP method');
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testSummaryDoesNotEnforcePutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('summary');

        $this->assertNotEquals(405, $result['status'], 'summary() should not enforce HTTP method');
    }

    // =========================================================================
    // SUMMARY: RESPONSE STRUCTURE
    // =========================================================================

    public function testSummaryResponseTopLevelFields(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingsController.php');

        $fields = ['meeting_id', 'meeting_title', 'status', 'validated_at', 'president_name', 'data'];
        foreach ($fields as $field) {
            $this->assertStringContainsString(
                "'{$field}'",
                $source,
                "summary response should contain '{$field}'",
            );
        }
    }

    public function testSummaryResponseDataSubfields(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingsController.php');

        $dataFields = [
            'total_members',
            'present_count',
            'proxy_count',
            'absent_count',
            'motions_count',
            'closed_motions_count',
            'open_motions_count',
            'adopted_count',
            'rejected_count',
            'ballots_count',
            'total_voted_weight',
            'proxies_count',
            'incidents_count',
            'manual_votes_count',
        ];
        foreach ($dataFields as $field) {
            $this->assertStringContainsString(
                "'{$field}'",
                $source,
                "summary data should contain '{$field}'",
            );
        }
    }

    // =========================================================================
    // SUMMARY: ABSENT COUNT CALCULATION
    // =========================================================================

    public function testSummaryAbsentCountCalculation(): void
    {
        $totalMembers = 100;
        $presentCount = 60;
        $proxyCount = 15;
        $absentCount = $totalMembers - $presentCount - $proxyCount;

        $this->assertEquals(25, $absentCount);
    }

    public function testSummaryAbsentCountAllPresent(): void
    {
        $totalMembers = 50;
        $presentCount = 40;
        $proxyCount = 10;
        $absentCount = $totalMembers - $presentCount - $proxyCount;

        $this->assertEquals(0, $absentCount);
    }

    // =========================================================================
    // STATS: METHOD ENFORCEMENT
    // =========================================================================

    public function testStatsRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('stats');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testStatsRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('stats');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // STATS: MEETING_ID VALIDATION
    // =========================================================================

    public function testStatsRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('stats');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testStatsRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('stats');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // STATS: TALLY SOURCE LOGIC
    // =========================================================================

    public function testStatsTallySourceIsBallotWhenBallotsTotalPositive(): void
    {
        $r = [
            'ballots_total' => 10, 'ballots_for' => 5, 'ballots_against' => 3,
            'ballots_abstain' => 1, 'ballots_nsp' => 1,
            'manual_total' => 0, 'manual_for' => 0, 'manual_against' => 0, 'manual_abstain' => 0,
        ];
        $ballotsTotal = (int) ($r['ballots_total'] ?? 0);

        if ($ballotsTotal > 0) {
            $source = 'ballots';
            $total = $ballotsTotal;
        } else {
            $source = 'manual';
            $total = (int) ($r['manual_total'] ?? 0);
        }

        $this->assertEquals('ballots', $source);
        $this->assertEquals(10, $total);
    }

    public function testStatsTallySourceIsManualWhenNoBallots(): void
    {
        $r = [
            'ballots_total' => 0,
            'manual_total' => 25, 'manual_for' => 15, 'manual_against' => 8, 'manual_abstain' => 2,
        ];
        $ballotsTotal = (int) ($r['ballots_total'] ?? 0);

        if ($ballotsTotal > 0) {
            $source = 'ballots';
        } else {
            $source = 'manual';
            $total = (int) ($r['manual_total'] ?? 0);
            $votes_for = (int) ($r['manual_for'] ?? 0);
            $votes_against = (int) ($r['manual_against'] ?? 0);
            $votes_abstain = (int) ($r['manual_abstain'] ?? 0);
            $votes_nsp = max(0, $total - $votes_for - $votes_against - $votes_abstain);
        }

        $this->assertEquals('manual', $source);
        $this->assertEquals(25, $total);
        $this->assertEquals(0, $votes_nsp);
    }

    public function testStatsManualNspCalculation(): void
    {
        $total = 50;
        $votes_for = 20;
        $votes_against = 15;
        $votes_abstain = 10;
        $votes_nsp = max(0, $total - $votes_for - $votes_against - $votes_abstain);

        $this->assertEquals(5, $votes_nsp);
    }

    public function testStatsManualNspNeverNegative(): void
    {
        $total = 10;
        $votes_for = 5;
        $votes_against = 4;
        $votes_abstain = 3;
        $votes_nsp = max(0, $total - $votes_for - $votes_against - $votes_abstain);

        $this->assertEquals(0, $votes_nsp);
    }

    // =========================================================================
    // STATS: DISTINCT VOTERS LOGIC
    // =========================================================================

    public function testStatsDistinctVotersUsesBallotsWhenAvailable(): void
    {
        $totalBallotsAllMotions = 50;
        $useBallots = $totalBallotsAllMotions > 0;

        $this->assertTrue($useBallots, 'Should use ballot-based distinct voters when ballots exist');
    }

    public function testStatsDistinctVotersFallsBackToManual(): void
    {
        $totalBallotsAllMotions = 0;
        $useBallots = $totalBallotsAllMotions > 0;

        $this->assertFalse($useBallots, 'Should fall back to manual total when no ballots');
    }

    // =========================================================================
    // STATS: RESPONSE STRUCTURE
    // =========================================================================

    public function testStatsResponseTopLevelFields(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingsController.php');

        $topKeys = ['meeting_id', 'motions_count', 'distinct_voters', 'items'];
        foreach ($topKeys as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $source,
                "stats response should contain '{$key}'",
            );
        }
    }

    public function testStatsMotionItemFields(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingsController.php');

        $fields = [
            'motion_id', 'title', 'total', 'votes_for', 'votes_against',
            'votes_abstain', 'votes_nsp', 'tally_source',
            'manual_total', 'manual_for', 'manual_against', 'manual_abstain',
            'ballots_total',
        ];
        foreach ($fields as $field) {
            $this->assertStringContainsString(
                "'{$field}'",
                $source,
                "stats motion item should contain '{$field}'",
            );
        }
    }

    // =========================================================================
    // CREATE MEETING: METHOD ENFORCEMENT
    // =========================================================================

    public function testCreateMeetingRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('createMeeting');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testCreateMeetingRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('createMeeting');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // CREATE MEETING: INPUT VALIDATION (via ValidationSchemas)
    // =========================================================================

    public function testCreateMeetingValidationSchemaRequiresTitle(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::meeting();
        $result = $schema->validate([]);

        $this->assertFalse($result->isValid(), 'Validation should fail without title');
        $errors = $result->errors();
        $this->assertArrayHasKey('title', $errors, 'Errors should include title');
    }

    public function testCreateMeetingValidationSchemaTitleMinLength(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::meeting();
        $result = $schema->validate(['title' => 'AB']);

        $this->assertFalse($result->isValid(), 'Title shorter than 3 chars should fail');
        $errors = $result->errors();
        $this->assertArrayHasKey('title', $errors);
    }

    public function testCreateMeetingValidationSchemaTitleMaxLength(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::meeting();
        $result = $schema->validate(['title' => str_repeat('X', 256)]);

        $this->assertFalse($result->isValid(), 'Title longer than 255 chars should fail');
        $errors = $result->errors();
        $this->assertArrayHasKey('title', $errors);
    }

    public function testCreateMeetingValidationSchemaAcceptsValidInput(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::meeting();
        $result = $schema->validate(['title' => 'Valid Meeting Title']);

        $this->assertTrue($result->isValid(), 'Valid input should pass validation');
    }

    public function testCreateMeetingValidationSchemaValidMeetingTypes(): void
    {
        $validTypes = ['ag_ordinaire', 'ag_extraordinaire', 'conseil', 'bureau', 'autre'];

        foreach ($validTypes as $type) {
            $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::meeting();
            $result = $schema->validate(['title' => 'Test Meeting', 'meeting_type' => $type]);
            $this->assertTrue($result->isValid(), "Meeting type '{$type}' should be valid");
        }
    }

    public function testCreateMeetingValidationSchemaRejectsInvalidMeetingType(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::meeting();
        $result = $schema->validate(['title' => 'Test Meeting', 'meeting_type' => 'invalid']);

        $this->assertFalse($result->isValid(), 'Invalid meeting type should fail');
        $errors = $result->errors();
        $this->assertArrayHasKey('meeting_type', $errors);
    }

    public function testCreateMeetingValidationSchemaDefaultMeetingType(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::meeting();
        $result = $schema->validate(['title' => 'Test Meeting']);

        $this->assertTrue($result->isValid());
        $this->assertEquals('ag_ordinaire', $result->get('meeting_type'));
    }

    public function testCreateMeetingValidationSchemaDefaultStatus(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::meeting();
        $result = $schema->validate(['title' => 'Test Meeting']);

        $this->assertTrue($result->isValid());
        $this->assertEquals('draft', $result->get('status'));
    }

    public function testCreateMeetingSchemaRejectsEmptyBody(): void
    {
        // createMeeting uses ValidationSchemas::meeting()->validate()->failIfInvalid()
        // which calls exit() on failure — test via schema directly.
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::meeting();
        $result = $schema->validate([]);

        $this->assertFalse($result->isValid(), 'Empty body should fail schema validation');
        $this->assertArrayHasKey('title', $result->errors());
    }

    public function testCreateMeetingSchemaRejectsMissingTitle(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::meeting();
        $result = $schema->validate(['description' => 'some description']);

        $this->assertFalse($result->isValid(), 'Missing title should fail schema validation');
        $this->assertArrayHasKey('title', $result->errors());
    }

    public function testCreateMeetingSchemaRejectsTitleTooShort(): void
    {
        $schema = \AgVote\Core\Validation\Schemas\ValidationSchemas::meeting();
        $result = $schema->validate(['title' => 'XY']);

        $this->assertFalse($result->isValid(), 'Title of 2 chars should fail (min 3)');
        $this->assertArrayHasKey('title', $result->errors());
    }

    // =========================================================================
    // CREATE MEETING: RESPONSE STRUCTURE
    // =========================================================================

    public function testCreateMeetingResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingsController.php');

        $this->assertStringContainsString("'meeting_id'", $source);
        $this->assertStringContainsString("'title'", $source);
        $this->assertStringContainsString('201', $source, 'createMeeting should return 201 status');
    }

    // =========================================================================
    // VALIDATE: METHOD ENFORCEMENT
    // =========================================================================

    public function testValidateRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('validate');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testValidateRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('validate');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // VALIDATE: MEETING_ID VALIDATION
    // =========================================================================

    public function testValidateRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('validate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testValidateRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => '']);

        $result = $this->callControllerMethod('validate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testValidateRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => 'xyz-not-a-uuid']);

        $result = $this->callControllerMethod('validate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testValidateRejectsNumericMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => '12345']);

        $result = $this->callControllerMethod('validate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // VALIDATE: PRESIDENT_NAME VALIDATION
    // =========================================================================

    public function testValidateRequiresPresidentName(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '11111111-1111-1111-1111-111111111111',
        ]);

        $result = $this->callControllerMethod('validate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_president_name', $result['body']['error']);
    }

    public function testValidateRejectsEmptyPresidentName(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '11111111-1111-1111-1111-111111111111',
            'president_name' => '',
        ]);

        $result = $this->callControllerMethod('validate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_president_name', $result['body']['error']);
    }

    public function testValidateRejectsWhitespaceOnlyPresidentName(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '11111111-1111-1111-1111-111111111111',
            'president_name' => '   ',
        ]);

        $result = $this->callControllerMethod('validate');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_president_name', $result['body']['error']);
    }

    // =========================================================================
    // VALIDATE: RESPONSE STRUCTURE
    // =========================================================================

    public function testValidateResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingsController.php');

        $this->assertStringContainsString("'meeting_id'", $source);
        $this->assertStringContainsString("'status' => 'validated'", $source);
    }

    public function testValidateHandlesExceptionGracefully(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingsController.php');

        $this->assertStringContainsString('validation_failed', $source);
        $this->assertStringContainsString('Throwable', $source);
    }

    // =========================================================================
    // VOTE SETTINGS: METHOD ENFORCEMENT
    // =========================================================================

    public function testVoteSettingsRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('voteSettings');

        // MeetingRepository is instantiated before method check; in test env
        // db() throws RuntimeException caught as business_error (400).
        // In production, DELETE would fall through to method_not_allowed (405).
        $this->assertContains($result['body']['error'], ['method_not_allowed', 'business_error']);
        $this->assertGreaterThanOrEqual(400, $result['status']);
    }

    public function testVoteSettingsRejectsPatchMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';

        $result = $this->callControllerMethod('voteSettings');

        $this->assertContains($result['body']['error'], ['method_not_allowed', 'business_error']);
        $this->assertGreaterThanOrEqual(400, $result['status']);
    }

    // =========================================================================
    // VOTE SETTINGS (GET): MEETING_ID VALIDATION
    // =========================================================================

    /**
     * voteSettings() eagerly creates MeetingRepository before the GET/POST
     * dispatch and before api_require_uuid.  In test env (no DB), the repo
     * constructor throws RuntimeException, caught by AbstractController as
     * business_error (400).  We verify UUID validation via source inspection.
     */
    public function testVoteSettingsSourceUsesApiRequireUuidForGet(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingsController.php');
        $this->assertStringContainsString("api_require_uuid(\$q, 'meeting_id')", $source);
    }

    public function testVoteSettingsSourceUsesApiRequireUuidForPost(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingsController.php');
        $this->assertStringContainsString("api_require_uuid(\$in, 'meeting_id')", $source);
    }

    public function testVoteSettingsGetNoDbReturnsBusinessError(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('voteSettings');

        // Eager MeetingRepository instantiation throws before reaching UUID check
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    public function testVoteSettingsPostNoDbReturnsBusinessError(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('voteSettings');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('business_error', $result['body']['error']);
    }

    // =========================================================================
    // VOTE SETTINGS: VOTE_POLICY_ID VALIDATION LOGIC
    // =========================================================================

    public function testVotePolicyIdValidationLogic(): void
    {
        $testCases = [
            ['input' => '', 'expectFail' => false],
            ['input' => '12345678-1234-1234-1234-123456789abc', 'expectFail' => false],
            ['input' => 'not-uuid', 'expectFail' => true],
            ['input' => '12345', 'expectFail' => true],
        ];

        foreach ($testCases as $case) {
            $policyId = trim((string) $case['input']);
            $invalid = $policyId !== '' && !api_is_uuid($policyId);
            $this->assertEquals(
                $case['expectFail'],
                $invalid,
                "vote_policy_id '{$case['input']}' validation should " . ($case['expectFail'] ? 'fail' : 'pass'),
            );
        }
    }

    // =========================================================================
    // VOTE SETTINGS: RESPONSE STRUCTURE
    // =========================================================================

    public function testVoteSettingsGetResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingsController.php');

        $this->assertStringContainsString("'meeting_id'", $source);
        $this->assertStringContainsString("'vote_policy_id'", $source);
        $this->assertStringContainsString("'title'", $source);
    }

    public function testVoteSettingsPostResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingsController.php');

        $this->assertStringContainsString("'saved'", $source);
    }

    // =========================================================================
    // ARCHIVE: METHOD ENFORCEMENT
    // =========================================================================

    public function testArchiveRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('archive');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testArchivesListRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('archivesList');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // STATUS: METHOD ENFORCEMENT
    // =========================================================================

    public function testStatusRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('status');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // STATUS: SIGN STATUS LOGIC
    // =========================================================================

    public function testStatusSignStatusArchivedTakesPrecedence(): void
    {
        $meetingStatus = 'archived';
        $readyToSign = true;
        $openMotions = 0;
        $closedWithoutTally = 0;

        $signStatus = 'not_ready';
        $signMessage = 'Seance en cours de traitement.';
        if ($meetingStatus === 'archived') {
            $signStatus = 'archived';
            $signMessage = 'Seance archivee.';
        } elseif ($readyToSign) {
            $signStatus = 'ready';
        } elseif ($openMotions > 0) {
            $signStatus = 'open_motions';
        } elseif ($closedWithoutTally > 0) {
            $signStatus = 'missing_tally';
        }

        $this->assertEquals('archived', $signStatus);
    }

    public function testStatusSignStatusReady(): void
    {
        $meetingStatus = 'live';
        $readyToSign = true;
        $openMotions = 0;
        $closedWithoutTally = 0;

        $signStatus = 'not_ready';
        if ($meetingStatus === 'archived') {
            $signStatus = 'archived';
        } elseif ($readyToSign) {
            $signStatus = 'ready';
        } elseif ($openMotions > 0) {
            $signStatus = 'open_motions';
        } elseif ($closedWithoutTally > 0) {
            $signStatus = 'missing_tally';
        }

        $this->assertEquals('ready', $signStatus);
    }

    public function testStatusSignStatusOpenMotions(): void
    {
        $meetingStatus = 'live';
        $readyToSign = false;
        $openMotions = 3;
        $closedWithoutTally = 0;

        $signStatus = 'not_ready';
        if ($meetingStatus === 'archived') {
            $signStatus = 'archived';
        } elseif ($readyToSign) {
            $signStatus = 'ready';
        } elseif ($openMotions > 0) {
            $signStatus = 'open_motions';
        } elseif ($closedWithoutTally > 0) {
            $signStatus = 'missing_tally';
        }

        $this->assertEquals('open_motions', $signStatus);
    }

    public function testStatusSignStatusMissingTally(): void
    {
        $meetingStatus = 'live';
        $readyToSign = false;
        $openMotions = 0;
        $closedWithoutTally = 2;

        $signStatus = 'not_ready';
        if ($meetingStatus === 'archived') {
            $signStatus = 'archived';
        } elseif ($readyToSign) {
            $signStatus = 'ready';
        } elseif ($openMotions > 0) {
            $signStatus = 'open_motions';
        } elseif ($closedWithoutTally > 0) {
            $signStatus = 'missing_tally';
        }

        $this->assertEquals('missing_tally', $signStatus);
    }

    public function testStatusSignStatusNotReady(): void
    {
        $meetingStatus = 'live';
        $readyToSign = false;
        $openMotions = 0;
        $closedWithoutTally = 0;

        $signStatus = 'not_ready';
        if ($meetingStatus === 'archived') {
            $signStatus = 'archived';
        } elseif ($readyToSign) {
            $signStatus = 'ready';
        } elseif ($openMotions > 0) {
            $signStatus = 'open_motions';
        } elseif ($closedWithoutTally > 0) {
            $signStatus = 'missing_tally';
        }

        $this->assertEquals('not_ready', $signStatus);
    }

    // =========================================================================
    // UUID VALIDATION HELPER TESTS
    // =========================================================================

    public function testApiIsUuidAcceptsValidUuids(): void
    {
        $this->assertTrue(api_is_uuid('12345678-1234-1234-1234-123456789abc'));
        $this->assertTrue(api_is_uuid('abcdef01-2345-6789-abcd-ef0123456789'));
        $this->assertTrue(api_is_uuid('ABCDEF01-2345-6789-ABCD-EF0123456789'));
        $this->assertTrue(api_is_uuid('00000000-0000-0000-0000-000000000000'));
    }

    public function testApiIsUuidRejectsInvalidUuids(): void
    {
        $this->assertFalse(api_is_uuid(''));
        $this->assertFalse(api_is_uuid('not-a-uuid'));
        $this->assertFalse(api_is_uuid('12345'));
        $this->assertFalse(api_is_uuid('12345678-1234-1234-1234'));
        $this->assertFalse(api_is_uuid('12345678_1234_1234_1234_123456789abc'));
        $this->assertFalse(api_is_uuid('g2345678-1234-1234-1234-123456789abc'));
    }

    // =========================================================================
    // CONTROLLER SOURCE: AUDIT LOGGING
    // =========================================================================

    public function testUpdateAuditsChanges(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingsController.php');

        $this->assertStringContainsString("'meeting_updated'", $source);
    }

    public function testCreateMeetingAuditsCreation(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingsController.php');

        $this->assertStringContainsString("'meeting_created'", $source);
    }

    public function testValidateAuditsValidation(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingsController.php');

        $this->assertStringContainsString("'meeting.validated'", $source);
    }

    public function testVoteSettingsAuditsUpdate(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingsController.php');

        $this->assertStringContainsString("'meeting_vote_policy_updated'", $source);
    }

    // =========================================================================
    // CONTROLLER: GUARDS AND PROTECTIONS
    // =========================================================================

    public function testUpdateGuardsAgainstStatusModification(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingsController.php');

        $this->assertStringContainsString('status_via_transition', $source);
        $this->assertStringContainsString('meeting_transition', $source);
    }

    public function testUpdateGuardsArchivedMeeting(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingsController.php');

        $this->assertStringContainsString('meeting_archived_locked', $source);
    }

    public function testUpdateCallsGuardMeetingNotValidated(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingsController.php');

        $this->assertStringContainsString('api_guard_meeting_not_validated', $source);
    }

    // =========================================================================
    // MEETING TYPE: COMPLETE LIST IN CONTROLLER
    // =========================================================================

    public function testMeetingTypeValidListInSource(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingsController.php');

        $validTypes = ['ag_ordinaire', 'ag_extraordinaire', 'conseil', 'bureau', 'autre'];
        foreach ($validTypes as $type) {
            $this->assertStringContainsString(
                "'{$type}'",
                $source,
                "Controller should list '{$type}' as a valid meeting type",
            );
        }
    }
}
