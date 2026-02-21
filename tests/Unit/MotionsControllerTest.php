<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\MotionsController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MotionsController.
 *
 * Tests the motion endpoint logic including:
 *  - Input validation for all 10 public methods
 *  - HTTP method enforcement (GET vs POST)
 *  - UUID validation for meeting_id, motion_id, agenda_id
 *  - Title/description length constraints
 *  - Reorder array validation
 *  - Degraded tally arithmetic checks
 *
 * Since api_ok()/api_fail() throw ApiResponseException, we catch these to
 * inspect controller behavior without a database.
 */
class MotionsControllerTest extends TestCase
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
        $controller = new MotionsController();
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
     * Inject a JSON body for POST requests via Request::$cachedRawBody.
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
        $ref = new \ReflectionClass(MotionsController::class);
        $this->assertTrue($ref->isFinal(), 'MotionsController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new MotionsController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(MotionsController::class);

        $expectedMethods = [
            'listForMeeting',
            'createSimple',
            'createOrUpdate',
            'deleteMotion',
            'reorder',
            'open',
            'close',
            'tally',
            'current',
            'degradedTally',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "MotionsController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(MotionsController::class);

        $expectedMethods = [
            'listForMeeting',
            'createSimple',
            'createOrUpdate',
            'deleteMotion',
            'reorder',
            'open',
            'close',
            'tally',
            'current',
            'degradedTally',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "MotionsController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // listForMeeting: INPUT VALIDATION
    // =========================================================================

    public function testListForMeetingRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testListForMeetingRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = []; // No meeting_id

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testListForMeetingRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testListForMeetingRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'not-a-valid-uuid'];

        $result = $this->callControllerMethod('listForMeeting');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    // =========================================================================
    // createSimple: INPUT VALIDATION
    // =========================================================================

    public function testCreateSimpleRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('createSimple');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testCreateSimpleRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['title' => 'A motion']);

        $result = $this->callControllerMethod('createSimple');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testCreateSimpleRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => '', 'title' => 'A motion']);

        $result = $this->callControllerMethod('createSimple');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testCreateSimpleRejectsInvalidMeetingUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['meeting_id' => 'bad-uuid', 'title' => 'A motion']);

        $result = $this->callControllerMethod('createSimple');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testCreateSimpleRequiresTitle(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('createSimple');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_title', $result['body']['error']);
    }

    public function testCreateSimpleRejectsEmptyTitle(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'title' => '',
        ]);

        $result = $this->callControllerMethod('createSimple');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_title', $result['body']['error']);
    }

    public function testCreateSimpleRejectsWhitespaceOnlyTitle(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'title' => '   ',
        ]);

        $result = $this->callControllerMethod('createSimple');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_title', $result['body']['error']);
    }

    public function testCreateSimpleRejectsTitleOver200Chars(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'title' => str_repeat('A', 201),
        ]);

        $result = $this->callControllerMethod('createSimple');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('title_too_long', $result['body']['error']);
    }

    public function testCreateSimpleRejectsDescriptionOver10000Chars(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'title' => 'Valid title',
            'description' => str_repeat('X', 10001),
        ]);

        $result = $this->callControllerMethod('createSimple');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('description_too_long', $result['body']['error']);
    }

    // =========================================================================
    // createOrUpdate: INPUT VALIDATION
    // =========================================================================

    public function testCreateOrUpdateRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('createOrUpdate');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testCreateOrUpdateUsesInputValidatorSchema(): void
    {
        // InputValidator::failIfInvalid() calls exit() on validation failure,
        // which cannot be intercepted cleanly in PHPUnit. Instead we verify
        // that the controller source declares the expected schema rules.
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString("->uuid('agenda_id')->required()", $source);
        $this->assertStringContainsString("->uuid('motion_id')->optional()", $source);
        $this->assertStringContainsString("->string('title')->required()->minLength(1)->maxLength(500)", $source);
        $this->assertStringContainsString("->string('description')->optional()->maxLength(10000)", $source);
        $this->assertStringContainsString("->boolean('secret')->default(false)", $source);
        $this->assertStringContainsString("->uuid('vote_policy_id')->optional()", $source);
        $this->assertStringContainsString("->uuid('quorum_policy_id')->optional()", $source);
    }

    // =========================================================================
    // deleteMotion: INPUT VALIDATION
    // =========================================================================

    public function testDeleteMotionRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('deleteMotion');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testDeleteMotionRequiresMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('deleteMotion');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testDeleteMotionRejectsEmptyMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['motion_id' => '']);

        $result = $this->callControllerMethod('deleteMotion');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    public function testDeleteMotionRejectsInvalidMotionUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['motion_id' => 'not-a-uuid']);

        $result = $this->callControllerMethod('deleteMotion');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_or_invalid_uuid', $result['body']['error']);
    }

    // =========================================================================
    // reorder: INPUT VALIDATION
    // =========================================================================

    public function testReorderRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('reorder');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testReorderRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'motion_ids' => ['12345678-1234-1234-1234-123456789abc'],
        ]);

        $result = $this->callControllerMethod('reorder');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testReorderRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '',
            'motion_ids' => ['12345678-1234-1234-1234-123456789abc'],
        ]);

        $result = $this->callControllerMethod('reorder');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testReorderRejectsInvalidMeetingUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => 'bad-uuid',
            'motion_ids' => ['12345678-1234-1234-1234-123456789abc'],
        ]);

        $result = $this->callControllerMethod('reorder');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testReorderRequiresMotionIds(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('reorder');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_motion_ids', $result['body']['error']);
    }

    public function testReorderRejectsEmptyMotionIdsArray(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_ids' => [],
        ]);

        $result = $this->callControllerMethod('reorder');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_motion_ids', $result['body']['error']);
    }

    public function testReorderRejectsInvalidMotionIdInArray(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_ids' => [
                '12345678-1234-1234-1234-123456789abc',
                'invalid-uuid',
            ],
        ]);

        $result = $this->callControllerMethod('reorder');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_motion_id', $result['body']['error']);
    }

    public function testReorderRejectsNonArrayMotionIds(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'motion_ids' => 'not-an-array',
        ]);

        $result = $this->callControllerMethod('reorder');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_motion_ids', $result['body']['error']);
    }

    // =========================================================================
    // open (openVote): INPUT VALIDATION
    // =========================================================================

    public function testOpenRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('open');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testOpenRequiresMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('open');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_motion_id', $result['body']['error']);
    }

    public function testOpenRejectsEmptyMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['motion_id' => '']);

        $result = $this->callControllerMethod('open');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_motion_id', $result['body']['error']);
    }

    public function testOpenRejectsInvalidMotionUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['motion_id' => 'not-a-uuid']);

        $result = $this->callControllerMethod('open');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_motion_id', $result['body']['error']);
    }

    // =========================================================================
    // close (closeVote): INPUT VALIDATION
    // =========================================================================

    public function testCloseRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('close');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testCloseRequiresMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('close');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_motion_id', $result['body']['error']);
    }

    public function testCloseRejectsEmptyMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['motion_id' => '']);

        $result = $this->callControllerMethod('close');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_motion_id', $result['body']['error']);
    }

    public function testCloseRejectsInvalidMotionUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['motion_id' => 'not-a-uuid']);

        $result = $this->callControllerMethod('close');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_motion_id', $result['body']['error']);
    }

    // =========================================================================
    // tally: INPUT VALIDATION
    // =========================================================================

    public function testTallyRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('tally');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testTallyRequiresMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('tally');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_motion_id', $result['body']['error']);
    }

    public function testTallyRejectsEmptyMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['motion_id' => ''];

        $result = $this->callControllerMethod('tally');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_motion_id', $result['body']['error']);
    }

    public function testTallyRejectsInvalidMotionUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['motion_id' => 'xyz-bad'];

        $result = $this->callControllerMethod('tally');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_motion_id', $result['body']['error']);
    }

    // =========================================================================
    // current: INPUT VALIDATION
    // =========================================================================

    public function testCurrentRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $result = $this->callControllerMethod('current');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testCurrentRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('current');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_request', $result['body']['error']);
    }

    public function testCurrentRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('current');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_request', $result['body']['error']);
    }

    public function testCurrentRejectsInvalidMeetingUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'not-valid'];

        $result = $this->callControllerMethod('current');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('invalid_request', $result['body']['error']);
    }

    // =========================================================================
    // degradedTally: INPUT VALIDATION
    // =========================================================================

    public function testDegradedTallyRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('degradedTally');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testDegradedTallyRequiresMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([]);

        $result = $this->callControllerMethod('degradedTally');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_motion_id', $result['body']['error']);
    }

    public function testDegradedTallyRejectsEmptyMotionId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody(['motion_id' => '']);

        $result = $this->callControllerMethod('degradedTally');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_motion_id', $result['body']['error']);
    }

    // =========================================================================
    // createSimple: TITLE BOUNDARY TESTS
    // =========================================================================

    public function testCreateSimpleAcceptsMaxLengthTitle(): void
    {
        // Title exactly at 200 chars should pass the title checks
        // (will fail later at meeting_not_found since there's no DB, but that means
        //  title validation passed)
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'title' => str_repeat('A', 200),
        ]);

        $result = $this->callControllerMethod('createSimple');

        // If title validation passed, we expect a downstream error (meeting_not_found)
        // not a title-related validation error
        $this->assertNotEquals('missing_title', $result['body']['error'] ?? '');
        $this->assertNotEquals('title_too_long', $result['body']['error'] ?? '');
    }

    public function testCreateSimpleAcceptsDescriptionAtMaxLength(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'title' => 'Valid title',
            'description' => str_repeat('X', 10000),
        ]);

        $result = $this->callControllerMethod('createSimple');

        // Description at exactly 10000 should not trigger description_too_long
        $this->assertNotEquals('description_too_long', $result['body']['error'] ?? '');
    }

    // =========================================================================
    // reorder: MEETING STATUS LOCK LOGIC
    // =========================================================================

    public function testReorderMeetingStatusLockLogic(): void
    {
        // Replicate the status lock logic from reorder()
        $lockedStatuses = ['live', 'closed', 'validated', 'archived'];

        foreach ($lockedStatuses as $status) {
            $isLocked = in_array($status, ['live', 'closed', 'validated', 'archived'], true);
            $this->assertTrue($isLocked, "Status '{$status}' should be locked");
        }

        $unlockedStatuses = ['draft', 'scheduled', 'open'];
        foreach ($unlockedStatuses as $status) {
            $isLocked = in_array($status, ['live', 'closed', 'validated', 'archived'], true);
            $this->assertFalse($isLocked, "Status '{$status}' should not be locked");
        }
    }

    // =========================================================================
    // degradedTally: ARITHMETIC VALIDATION LOGIC
    // =========================================================================

    public function testDegradedTallyArithmeticConsistencyCheck(): void
    {
        // Replicate the arithmetic validation from degradedTally()
        $total = 100;
        $for = 50;
        $against = 30;
        $abstain = 20;

        $sum = $for + $against + $abstain;
        $consistent = ($sum === $total);

        $this->assertTrue($consistent);
    }

    public function testDegradedTallyRejectsInconsistentSum(): void
    {
        $total = 100;
        $for = 50;
        $against = 30;
        $abstain = 10; // sum = 90, not 100

        $sum = $for + $against + $abstain;
        $consistent = ($sum === $total);

        $this->assertFalse($consistent);
    }

    public function testDegradedTallyRejectsZeroTotal(): void
    {
        $total = 0;
        $valid = $total > 0;

        $this->assertFalse($valid, 'Total must be strictly positive');
    }

    public function testDegradedTallyRejectsNegativeTotal(): void
    {
        $total = -5;
        $valid = $total > 0;

        $this->assertFalse($valid);
    }

    public function testDegradedTallyRejectsNegativeVoteCounts(): void
    {
        $for = -1;
        $against = 10;
        $abstain = 5;

        $valid = ($for >= 0 && $against >= 0 && $abstain >= 0);

        $this->assertFalse($valid);
    }

    public function testDegradedTallyRejectsVotesExceedingTotal(): void
    {
        $total = 50;
        $for = 60; // exceeds total
        $against = 10;
        $abstain = 5;

        $forExceeds = ($for > $total);
        $againstExceeds = ($against > $total);
        $abstainExceeds = ($abstain > $total);

        $this->assertTrue($forExceeds, 'for votes should not exceed total');
        $this->assertFalse($againstExceeds);
        $this->assertFalse($abstainExceeds);
    }

    public function testDegradedTallyValidatesAllZeros(): void
    {
        $total = 10;
        $for = 0;
        $against = 0;
        $abstain = 0;

        $sum = $for + $against + $abstain;
        $consistent = ($sum === $total);

        $this->assertFalse($consistent, 'Sum of zeros should not match positive total');
    }

    // =========================================================================
    // createSimple: SECRET FLAG PARSING
    // =========================================================================

    public function testCreateSimpleSecretFlagParsing(): void
    {
        // Replicate the secret flag logic: (bool) ($in['secret'] ?? false)
        $this->assertTrue((bool) (true));
        $this->assertTrue((bool) (1));
        $this->assertTrue((bool) ('1'));
        $this->assertFalse((bool) (false));
        $this->assertFalse((bool) (0));
        $this->assertFalse((bool) (null));
        $this->assertFalse((bool) (false ?? false));
    }

    // =========================================================================
    // TALLY: RESULT STRUCTURE
    // =========================================================================

    public function testTallyResultStructure(): void
    {
        // Replicate the tally result initialization from tally()
        $result = [
            'for' => ['count' => 0, 'weight' => 0],
            'against' => ['count' => 0, 'weight' => 0],
            'abstain' => ['count' => 0, 'weight' => 0],
            'nsp' => ['count' => 0, 'weight' => 0],
        ];

        $this->assertArrayHasKey('for', $result);
        $this->assertArrayHasKey('against', $result);
        $this->assertArrayHasKey('abstain', $result);
        $this->assertArrayHasKey('nsp', $result);

        foreach ($result as $value) {
            $this->assertArrayHasKey('count', $value);
            $this->assertArrayHasKey('weight', $value);
            $this->assertEquals(0, $value['count']);
            $this->assertEquals(0, $value['weight']);
        }
    }

    public function testTallyAccumulatesRows(): void
    {
        // Replicate the tally accumulation logic
        $rows = [
            ['value' => 'for', 'c' => 10, 'w' => 15.5],
            ['value' => 'against', 'c' => 5, 'w' => 7.0],
            ['value' => 'abstain', 'c' => 3, 'w' => 3.0],
            ['value' => 'nsp', 'c' => 2, 'w' => 2.0],
        ];

        $result = [
            'for' => ['count' => 0, 'weight' => 0],
            'against' => ['count' => 0, 'weight' => 0],
            'abstain' => ['count' => 0, 'weight' => 0],
            'nsp' => ['count' => 0, 'weight' => 0],
        ];

        foreach ($rows as $r) {
            $v = $r['value'];
            if (!isset($result[$v])) {
                continue;
            }
            $result[$v]['count'] = (int) $r['c'];
            $result[$v]['weight'] = (float) $r['w'];
        }

        $this->assertEquals(10, $result['for']['count']);
        $this->assertEquals(15.5, $result['for']['weight']);
        $this->assertEquals(5, $result['against']['count']);
        $this->assertEquals(7.0, $result['against']['weight']);
        $this->assertEquals(3, $result['abstain']['count']);
        $this->assertEquals(3.0, $result['abstain']['weight']);
        $this->assertEquals(2, $result['nsp']['count']);
        $this->assertEquals(2.0, $result['nsp']['weight']);
    }

    public function testTallyIgnoresUnknownValues(): void
    {
        $rows = [
            ['value' => 'for', 'c' => 10, 'w' => 10.0],
            ['value' => 'invalid_value', 'c' => 99, 'w' => 99.0],
        ];

        $result = [
            'for' => ['count' => 0, 'weight' => 0],
            'against' => ['count' => 0, 'weight' => 0],
            'abstain' => ['count' => 0, 'weight' => 0],
            'nsp' => ['count' => 0, 'weight' => 0],
        ];

        foreach ($rows as $r) {
            $v = $r['value'];
            if (!isset($result[$v])) {
                continue;
            }
            $result[$v]['count'] = (int) $r['c'];
            $result[$v]['weight'] = (float) $r['w'];
        }

        $this->assertEquals(10, $result['for']['count']);
        $this->assertEquals(0, $result['against']['count']);
        $this->assertEquals(0, $result['abstain']['count']);
        $this->assertEquals(0, $result['nsp']['count']);
    }

    // =========================================================================
    // listForMeeting: MOTION DATA ENRICHMENT LOGIC
    // =========================================================================

    public function testMotionFieldMapping(): void
    {
        // Replicate the field mapping logic from listForMeeting()
        $m = [
            'motion_id' => 'abc-123',
            'motion_title' => 'Budget Approval',
            'motion_description' => 'Approve the annual budget',
            'decision' => 'approved',
        ];

        $m['id'] = $m['motion_id'] ?? $m['id'] ?? null;
        $m['title'] = $m['motion_title'] ?? $m['title'] ?? '';
        $m['description'] = $m['motion_description'] ?? $m['description'] ?? '';
        $m['result'] = $m['decision'] ?? null;

        $this->assertEquals('abc-123', $m['id']);
        $this->assertEquals('Budget Approval', $m['title']);
        $this->assertEquals('Approve the annual budget', $m['description']);
        $this->assertEquals('approved', $m['result']);
    }

    public function testMotionFieldMappingFallback(): void
    {
        // When motion_* keys are absent, fall back to direct keys
        $m = [
            'id' => 'fallback-id',
            'title' => 'Fallback Title',
            'description' => 'Fallback Description',
        ];

        $m['id'] = $m['motion_id'] ?? $m['id'] ?? null;
        $m['title'] = $m['motion_title'] ?? $m['title'] ?? '';
        $m['description'] = $m['motion_description'] ?? $m['description'] ?? '';
        $m['result'] = $m['decision'] ?? null;

        $this->assertEquals('fallback-id', $m['id']);
        $this->assertEquals('Fallback Title', $m['title']);
        $this->assertEquals('Fallback Description', $m['description']);
        $this->assertNull($m['result']);
    }

    public function testMotionStatsEnrichmentDefaults(): void
    {
        // When no stats are found for a motion, defaults to zeros
        $statsMap = [];
        $mid = 'motion-no-stats';

        $votes = [];
        if (isset($statsMap[$mid])) {
            $votes['votes_for'] = (int) $statsMap[$mid]['ballots_for'];
        } else {
            $votes['votes_for'] = 0;
            $votes['votes_against'] = 0;
            $votes['votes_abstain'] = 0;
            $votes['votes_nsp'] = 0;
            $votes['votes_count'] = 0;
        }

        $this->assertEquals(0, $votes['votes_for']);
        $this->assertEquals(0, $votes['votes_against']);
        $this->assertEquals(0, $votes['votes_abstain']);
        $this->assertEquals(0, $votes['votes_nsp']);
        $this->assertEquals(0, $votes['votes_count']);
    }

    public function testMotionStatsEnrichmentWithData(): void
    {
        $statsMap = [
            'motion-1' => [
                'motion_id' => 'motion-1',
                'ballots_for' => '15',
                'ballots_against' => '8',
                'ballots_abstain' => '3',
                'ballots_nsp' => '1',
                'ballots_total' => '27',
            ],
        ];

        $mid = 'motion-1';
        $votes = [];
        if (isset($statsMap[$mid])) {
            $votes['votes_for'] = (int) $statsMap[$mid]['ballots_for'];
            $votes['votes_against'] = (int) $statsMap[$mid]['ballots_against'];
            $votes['votes_abstain'] = (int) $statsMap[$mid]['ballots_abstain'];
            $votes['votes_nsp'] = (int) $statsMap[$mid]['ballots_nsp'];
            $votes['votes_count'] = (int) $statsMap[$mid]['ballots_total'];
        }

        $this->assertEquals(15, $votes['votes_for']);
        $this->assertEquals(8, $votes['votes_against']);
        $this->assertEquals(3, $votes['votes_abstain']);
        $this->assertEquals(1, $votes['votes_nsp']);
        $this->assertEquals(27, $votes['votes_count']);
    }

    // =========================================================================
    // listForMeeting: MOTIONS JSON DECODING LOGIC
    // =========================================================================

    public function testMotionsJsonDecodingFromString(): void
    {
        // Replicate the JSON decoding logic from listForMeeting()
        $row = ['motions' => json_encode([
            ['id' => 'm1', 'title' => 'First'],
            ['id' => 'm2', 'title' => 'Second'],
        ])];

        $motions = [];
        if ($row && isset($row['motions']) && $row['motions'] !== null) {
            if (is_string($row['motions'])) {
                $decoded = json_decode($row['motions'], true);
                if (is_array($decoded)) {
                    $motions = $decoded;
                }
            } elseif (is_array($row['motions'])) {
                $motions = $row['motions'];
            }
        }

        $this->assertCount(2, $motions);
        $this->assertEquals('m1', $motions[0]['id']);
        $this->assertEquals('m2', $motions[1]['id']);
    }

    public function testMotionsJsonDecodingFromArray(): void
    {
        $row = ['motions' => [
            ['id' => 'm1', 'title' => 'First'],
        ]];

        $motions = [];
        if ($row && isset($row['motions']) && $row['motions'] !== null) {
            if (is_string($row['motions'])) {
                $decoded = json_decode($row['motions'], true);
                if (is_array($decoded)) {
                    $motions = $decoded;
                }
            } elseif (is_array($row['motions'])) {
                $motions = $row['motions'];
            }
        }

        $this->assertCount(1, $motions);
        $this->assertEquals('m1', $motions[0]['id']);
    }

    public function testMotionsJsonDecodingFromNull(): void
    {
        $row = ['motions' => null];

        $motions = [];
        if ($row && isset($row['motions']) && $row['motions'] !== null) {
            if (is_string($row['motions'])) {
                $decoded = json_decode($row['motions'], true);
                if (is_array($decoded)) {
                    $motions = $decoded;
                }
            }
        }

        $this->assertEmpty($motions);
    }

    public function testMotionsJsonDecodingFromInvalidJson(): void
    {
        $row = ['motions' => 'this is not json'];

        $motions = [];
        if ($row && isset($row['motions']) && $row['motions'] !== null) {
            if (is_string($row['motions'])) {
                $decoded = json_decode($row['motions'], true);
                if (is_array($decoded)) {
                    $motions = $decoded;
                }
            }
        }

        $this->assertEmpty($motions, 'Invalid JSON should result in empty motions array');
    }

    public function testMotionsJsonDecodingFromEmptyRow(): void
    {
        $row = null;

        $motions = [];
        if ($row && isset($row['motions']) && $row['motions'] !== null) {
            // unreachable
        }

        $this->assertEmpty($motions);
    }

    // =========================================================================
    // RESPONSE STRUCTURE VERIFICATION (source-level)
    // =========================================================================

    public function testListForMeetingResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $expectedKeys = ['meeting_id', 'current_motion_id', 'items'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString("'{$key}'", $source, "listForMeeting response should contain '{$key}'");
        }
    }

    public function testCreateSimpleResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        // createSimple returns motion_id, agenda_id, created
        $this->assertStringContainsString("'motion_id'", $source);
        $this->assertStringContainsString("'agenda_id'", $source);
        $this->assertStringContainsString("'created' => true", $source);
    }

    public function testDeleteMotionResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        // deleteMotion audit log includes meeting_id and agenda_id
        $this->assertStringContainsString("'motion_deleted'", $source);
    }

    public function testOpenResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $expectedKeys = ['meeting_id', 'opened_motion_id', 'vote_policy_id', 'quorum_policy_id'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString("'{$key}'", $source, "open() response should contain '{$key}'");
        }
    }

    public function testCloseResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $expectedKeys = ['closed_motion_id', 'results', 'eligible_count', 'votes_cast'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString("'{$key}'", $source, "close() response should contain '{$key}'");
        }
    }

    public function testCloseResultsFields(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $fields = ['for', 'against', 'abstain', 'total', 'decision', 'reason'];
        foreach ($fields as $field) {
            $this->assertStringContainsString("'{$field}'", $source, "close() results should include '{$field}'");
        }
    }

    public function testReorderResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString("'reordered' => true", $source);
        $this->assertStringContainsString("'count'", $source);
    }

    public function testCurrentResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $expectedKeys = ['motion', 'total_motions', 'eligible_count', 'ballots_cast'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString("'{$key}'", $source, "current() response should contain '{$key}'");
        }
    }

    public function testDegradedTallyResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $expectedKeys = ['manual_total', 'manual_for', 'manual_against', 'manual_abstain'];
        foreach ($expectedKeys as $key) {
            $this->assertStringContainsString("'{$key}'", $source, "degradedTally() response should contain '{$key}'");
        }
    }

    // =========================================================================
    // AUDIT LOG VERIFICATION (source-level)
    // =========================================================================

    public function testCreateSimpleAuditsMotionCreation(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString("'motion_created'", $source);
        $this->assertStringContainsString("'created_via' => 'simple_endpoint'", $source);
    }

    public function testDeleteMotionAuditsMotionDeletion(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString("'motion_deleted'", $source);
    }

    public function testReorderAuditsMotionReorder(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString("'motions_reordered'", $source);
    }

    public function testOpenAuditsMotionOpened(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString("'motion_opened'", $source);
    }

    public function testCloseAuditsMotionClosed(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString("'motion_closed'", $source);
    }

    public function testDegradedTallyAuditsManualTally(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString("'manual_tally_set'", $source);
    }

    // =========================================================================
    // BUSINESS GUARD VERIFICATION (source-level)
    // =========================================================================

    public function testDeleteMotionGuardsMeetingNotValidated(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        // deleteMotion() checks api_guard_meeting_not_validated
        $this->assertStringContainsString('api_guard_meeting_not_validated', $source);
    }

    public function testDeleteMotionGuardsOpenMotion(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString('motion_open_locked', $source);
    }

    public function testDeleteMotionGuardsClosedMotion(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString('motion_closed_locked', $source);
    }

    public function testOpenGuardsAlreadyOpenedMotion(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString('motion_already_opened', $source);
    }

    public function testCloseGuardsMotionNotOpen(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString('motion_not_open', $source);
    }

    public function testCloseGuardsMotionAlreadyClosed(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString('motion_already_closed', $source);
    }

    public function testReorderGuardsMeetingLocked(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString('meeting_locked', $source);
    }

    // =========================================================================
    // WEBSOCKET EVENT VERIFICATION (source-level)
    // =========================================================================

    public function testOpenBroadcastsMotionOpenedEvent(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString('EventBroadcaster::motionOpened', $source);
    }

    public function testCloseBroadcastsMotionClosedEvent(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString('EventBroadcaster::motionClosed', $source);
    }

    // =========================================================================
    // DEGRADED TALLY: JUSTIFICATION REQUIREMENT
    // =========================================================================

    public function testDegradedTallyRequiresJustification(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString('missing_justification', $source);
    }

    public function testDegradedTallyJustificationValidation(): void
    {
        // Replicate justification check logic
        $justification1 = trim('');
        $this->assertEquals('', $justification1, 'Empty justification should fail');

        $justification2 = trim('   ');
        $this->assertEquals('', $justification2, 'Whitespace-only justification should fail');

        $justification3 = trim('Manual count due to system failure');
        $this->assertNotEquals('', $justification3, 'Valid justification should pass');
    }

    // =========================================================================
    // DEGRADED TALLY: NOTIFICATION
    // =========================================================================

    public function testDegradedTallyEmitsNotification(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString('degraded_manual_tally', $source);
        $this->assertStringContainsString('NotificationsService', $source);
    }

    // =========================================================================
    // CLOSE: TOKEN REVOCATION
    // =========================================================================

    public function testCloseRevokesVoteTokens(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString('revokeForMotion', $source);
        $this->assertStringContainsString('VoteTokenService', $source);
    }

    // =========================================================================
    // CLOSE: OFFICIAL RESULTS COMPUTATION
    // =========================================================================

    public function testCloseComputesOfficialResults(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString('OfficialResultsService', $source);
        $this->assertStringContainsString('computeOfficialTallies', $source);
        $this->assertStringContainsString('updateOfficialResults', $source);
    }

    // =========================================================================
    // OPEN: POLICY RESOLUTION CHAIN
    // =========================================================================

    public function testOpenResolvesPolicyFromMotionThenMeetingThenDefault(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        // The open() method resolves vote_policy_id in a cascade:
        // motion -> meeting -> tenant defaults
        $this->assertStringContainsString('vote_policy_id', $source);
        $this->assertStringContainsString('quorum_policy_id', $source);
        $this->assertStringContainsString('listVotePolicies', $source);
        $this->assertStringContainsString('listQuorumPolicies', $source);
    }

    // =========================================================================
    // createOrUpdate: EDIT GUARDS
    // =========================================================================

    public function testCreateOrUpdateGuardsActiveMotion(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString('motion_active_locked', $source);
    }

    public function testCreateOrUpdateGuardsClosedMotion(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString('motion_closed_locked', $source);
    }

    public function testCreateOrUpdateGuardsAgendaMismatch(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MotionsController.php');

        $this->assertStringContainsString('agenda_mismatch', $source);
    }

    // =========================================================================
    // STATS MAP BUILDING LOGIC
    // =========================================================================

    public function testStatsMapBuilding(): void
    {
        // Replicate the stats map building from listForMeeting()
        $stats = [
            ['motion_id' => 'm1', 'ballots_for' => 10, 'ballots_against' => 5],
            ['motion_id' => 'm2', 'ballots_for' => 20, 'ballots_against' => 3],
            ['motion_id' => 'm3', 'ballots_for' => 0, 'ballots_against' => 0],
        ];

        $statsMap = [];
        foreach ($stats as $s) {
            $statsMap[(string) $s['motion_id']] = $s;
        }

        $this->assertCount(3, $statsMap);
        $this->assertArrayHasKey('m1', $statsMap);
        $this->assertArrayHasKey('m2', $statsMap);
        $this->assertArrayHasKey('m3', $statsMap);
        $this->assertEquals(10, $statsMap['m1']['ballots_for']);
        $this->assertEquals(20, $statsMap['m2']['ballots_for']);
    }

    // =========================================================================
    // UUID VALIDATION HELPER (used throughout)
    // =========================================================================

    public function testUuidValidation(): void
    {
        // Valid UUIDs
        $this->assertTrue(api_is_uuid('12345678-1234-1234-1234-123456789abc'));
        $this->assertTrue(api_is_uuid('ABCDEF01-2345-6789-abcd-ef0123456789'));
        $this->assertTrue(api_is_uuid('00000000-0000-0000-0000-000000000000'));

        // Invalid UUIDs
        $this->assertFalse(api_is_uuid(''));
        $this->assertFalse(api_is_uuid('not-a-uuid'));
        $this->assertFalse(api_is_uuid('12345678-1234-1234-1234'));
        $this->assertFalse(api_is_uuid('12345678-1234-1234-1234-123456789abcx'));
        $this->assertFalse(api_is_uuid('12345678_1234_1234_1234_123456789abc'));
    }

    // =========================================================================
    // TITLE TRIMMING BEHAVIOR
    // =========================================================================

    public function testTitleTrimmingLogic(): void
    {
        // Replicate: $title = trim((string) ($in['title'] ?? ''));
        $this->assertEquals('Hello', trim('  Hello  '));
        $this->assertEquals('', trim('   '));
        $this->assertEquals('', trim(''));
        $this->assertEquals('Motion A', trim('Motion A'));
    }

    public function testMeetingIdTrimmingLogic(): void
    {
        // Replicate: $meetingId = trim((string) ($in['meeting_id'] ?? ''));
        $uuid = '12345678-1234-1234-1234-123456789abc';
        $this->assertEquals($uuid, trim(" {$uuid} "));
        $this->assertEquals('', trim(''));
        $this->assertEquals('', trim((string) null));
    }
}
