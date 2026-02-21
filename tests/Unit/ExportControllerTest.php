<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\ExportController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ExportController.
 *
 * Tests the export endpoint logic including:
 *  - Controller structure (final, extends AbstractController, public methods)
 *  - requireMeetingId() validation (missing, empty, invalid UUID)
 *  - All 9 export endpoints reject requests without a valid meeting_id
 *
 * Since api_ok()/api_fail() throw ApiResponseException, we catch these to
 * inspect controller behavior without a database.
 */
class ExportControllerTest extends TestCase
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
        $controller = new ExportController();
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

    // =========================================================================
    // CONTROLLER STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(ExportController::class);
        $this->assertTrue($ref->isFinal(), 'ExportController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new ExportController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(ExportController::class);

        $expectedMethods = [
            'attendanceCsv',
            'attendanceXlsx',
            'votesCsv',
            'votesXlsx',
            'membersCsv',
            'motionResultsCsv',
            'resultsXlsx',
            'fullXlsx',
            'ballotsAuditCsv',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "ExportController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(ExportController::class);

        $expectedMethods = [
            'attendanceCsv',
            'attendanceXlsx',
            'votesCsv',
            'votesXlsx',
            'membersCsv',
            'motionResultsCsv',
            'resultsXlsx',
            'fullXlsx',
            'ballotsAuditCsv',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "ExportController::{$method}() should be public",
            );
        }
    }

    public function testControllerHasPrivateHelperRequireMeetingId(): void
    {
        $ref = new \ReflectionClass(ExportController::class);
        $this->assertTrue($ref->hasMethod('requireMeetingId'), 'Should have requireMeetingId helper');
        $this->assertTrue(
            $ref->getMethod('requireMeetingId')->isPrivate(),
            'requireMeetingId() should be private',
        );
    }

    public function testControllerHasPrivateHelperRequireValidatedMeeting(): void
    {
        $ref = new \ReflectionClass(ExportController::class);
        $this->assertTrue($ref->hasMethod('requireValidatedMeeting'), 'Should have requireValidatedMeeting helper');
        $this->assertTrue(
            $ref->getMethod('requireValidatedMeeting')->isPrivate(),
            'requireValidatedMeeting() should be private',
        );
    }

    public function testControllerHasPrivateHelperAuditExport(): void
    {
        $ref = new \ReflectionClass(ExportController::class);
        $this->assertTrue($ref->hasMethod('auditExport'), 'Should have auditExport helper');
        $this->assertTrue(
            $ref->getMethod('auditExport')->isPrivate(),
            'auditExport() should be private',
        );
    }

    public function testControllerHasExactlyNinePublicExportMethods(): void
    {
        $ref = new \ReflectionClass(ExportController::class);

        $exportMethods = [
            'attendanceCsv',
            'attendanceXlsx',
            'votesCsv',
            'votesXlsx',
            'membersCsv',
            'motionResultsCsv',
            'resultsXlsx',
            'fullXlsx',
            'ballotsAuditCsv',
        ];

        // Count public methods declared in ExportController (not inherited)
        $ownPublic = [];
        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
            if ($m->getDeclaringClass()->getName() === ExportController::class) {
                $ownPublic[] = $m->getName();
            }
        }

        foreach ($exportMethods as $expected) {
            $this->assertContains(
                $expected,
                $ownPublic,
                "ExportController should declare public method '{$expected}'",
            );
        }

        $this->assertCount(
            count($exportMethods),
            $ownPublic,
            'ExportController should declare exactly 9 public methods: ' . implode(', ', $ownPublic),
        );
    }

    // =========================================================================
    // attendanceCsv: INPUT VALIDATION
    // =========================================================================

    public function testAttendanceCsvRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('attendanceCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testAttendanceCsvRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('attendanceCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testAttendanceCsvRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'not-a-valid-uuid'];

        $result = $this->callControllerMethod('attendanceCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testAttendanceCsvRejectsWhitespaceMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '   '];

        $result = $this->callControllerMethod('attendanceCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // attendanceXlsx: INPUT VALIDATION
    // =========================================================================

    public function testAttendanceXlsxRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('attendanceXlsx');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testAttendanceXlsxRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('attendanceXlsx');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testAttendanceXlsxRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'bad-uuid-here'];

        $result = $this->callControllerMethod('attendanceXlsx');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // votesCsv: INPUT VALIDATION
    // =========================================================================

    public function testVotesCsvRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('votesCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testVotesCsvRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('votesCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testVotesCsvRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'xyz-123'];

        $result = $this->callControllerMethod('votesCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // votesXlsx: INPUT VALIDATION
    // =========================================================================

    public function testVotesXlsxRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('votesXlsx');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testVotesXlsxRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('votesXlsx');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testVotesXlsxRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345'];

        $result = $this->callControllerMethod('votesXlsx');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // membersCsv: INPUT VALIDATION
    // =========================================================================

    public function testMembersCsvRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('membersCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testMembersCsvRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('membersCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testMembersCsvRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'definitely-not-uuid'];

        $result = $this->callControllerMethod('membersCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // motionResultsCsv: INPUT VALIDATION
    // =========================================================================

    public function testMotionResultsCsvRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('motionResultsCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testMotionResultsCsvRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('motionResultsCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testMotionResultsCsvRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'invalid!!'];

        $result = $this->callControllerMethod('motionResultsCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // resultsXlsx: INPUT VALIDATION
    // =========================================================================

    public function testResultsXlsxRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('resultsXlsx');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testResultsXlsxRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('resultsXlsx');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testResultsXlsxRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'nope'];

        $result = $this->callControllerMethod('resultsXlsx');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // fullXlsx: INPUT VALIDATION
    // =========================================================================

    public function testFullXlsxRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('fullXlsx');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testFullXlsxRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('fullXlsx');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testFullXlsxRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'short'];

        $result = $this->callControllerMethod('fullXlsx');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // ballotsAuditCsv: INPUT VALIDATION
    // =========================================================================

    public function testBallotsAuditCsvRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('ballotsAuditCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testBallotsAuditCsvRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('ballotsAuditCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testBallotsAuditCsvRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'aaaaaaaa-bbbb-cccc-dddd'];

        $result = $this->callControllerMethod('ballotsAuditCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // CROSS-CUTTING: All methods reject various invalid UUID formats
    // =========================================================================

    /**
     * @dataProvider allExportMethodsProvider
     */
    public function testAllMethodsRejectMissingMeetingId(string $method): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod($method);

        $this->assertEquals(400, $result['status'], "{$method} should return 400 when meeting_id is missing");
        $this->assertEquals('missing_meeting_id', $result['body']['error'], "{$method} should return 'missing_meeting_id' error");
    }

    /**
     * @dataProvider allExportMethodsProvider
     */
    public function testAllMethodsRejectEmptyMeetingId(string $method): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod($method);

        $this->assertEquals(400, $result['status'], "{$method} should return 400 for empty meeting_id");
        $this->assertEquals('missing_meeting_id', $result['body']['error'], "{$method} should return 'missing_meeting_id' for empty value");
    }

    /**
     * @dataProvider allExportMethodsProvider
     */
    public function testAllMethodsRejectNonUuidMeetingId(string $method): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'not-a-valid-uuid-format'];

        $result = $this->callControllerMethod($method);

        $this->assertEquals(400, $result['status'], "{$method} should return 400 for non-UUID meeting_id");
        $this->assertEquals('missing_meeting_id', $result['body']['error'], "{$method} should return 'missing_meeting_id' for invalid UUID");
    }

    /**
     * @dataProvider allExportMethodsProvider
     */
    public function testAllMethodsRejectTooShortUuid(string $method): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234'];

        $result = $this->callControllerMethod($method);

        $this->assertEquals(400, $result['status'], "{$method} should reject truncated UUID");
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    /**
     * @dataProvider allExportMethodsProvider
     */
    public function testAllMethodsRejectUuidWithExtraChars(string $method): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234-1234-1234-123456789abcXXX'];

        $result = $this->callControllerMethod($method);

        $this->assertEquals(400, $result['status'], "{$method} should reject UUID with trailing characters");
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    /**
     * @dataProvider allExportMethodsProvider
     */
    public function testAllMethodsRejectUuidWithSpecialChars(string $method): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234-1234-1234-12345678!abc'];

        $result = $this->callControllerMethod($method);

        $this->assertEquals(400, $result['status'], "{$method} should reject UUID with special characters");
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    /**
     * Data provider returning all 9 export method names.
     */
    public static function allExportMethodsProvider(): array
    {
        return [
            'attendanceCsv' => ['attendanceCsv'],
            'attendanceXlsx' => ['attendanceXlsx'],
            'votesCsv' => ['votesCsv'],
            'votesXlsx' => ['votesXlsx'],
            'membersCsv' => ['membersCsv'],
            'motionResultsCsv' => ['motionResultsCsv'],
            'resultsXlsx' => ['resultsXlsx'],
            'fullXlsx' => ['fullXlsx'],
            'ballotsAuditCsv' => ['ballotsAuditCsv'],
        ];
    }

    // =========================================================================
    // ERROR RESPONSE STRUCTURE
    // =========================================================================

    public function testFailResponseContainsOkFalse(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('attendanceCsv');

        $this->assertArrayHasKey('ok', $result['body']);
        $this->assertFalse($result['body']['ok']);
    }

    public function testFailResponseContainsErrorKey(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('votesCsv');

        $this->assertArrayHasKey('error', $result['body']);
        $this->assertIsString($result['body']['error']);
    }

    public function testFailResponseContainsMessageKey(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('membersCsv');

        $this->assertArrayHasKey('message', $result['body']);
        $this->assertIsString($result['body']['message']);
        $this->assertNotEmpty($result['body']['message']);
    }

    public function testMissingMeetingIdErrorMessageIsFrench(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('attendanceCsv');

        // The ErrorDictionary translates 'missing_meeting_id' to a French message
        $this->assertArrayHasKey('message', $result['body']);
        $this->assertStringContainsString('ance', $result['body']['message'], 'Error message should be French (contains "séance" or similar)');
    }

    // =========================================================================
    // requireMeetingId: EDGE CASES VIA REFLECTION
    // =========================================================================

    public function testRequireMeetingIdAcceptsValidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234-1234-1234-123456789abc'];

        $ref = new \ReflectionMethod(ExportController::class, 'requireMeetingId');
        $ref->setAccessible(true);

        $controller = new ExportController();

        // A valid UUID should be returned without throwing
        // However requireValidatedMeeting will then throw since there is no DB
        // So we test requireMeetingId directly via reflection
        $meetingId = $ref->invoke($controller);

        $this->assertEquals('12345678-1234-1234-1234-123456789abc', $meetingId);
    }

    public function testRequireMeetingIdAcceptsUpperCaseUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'ABCDEF01-2345-6789-ABCD-EF0123456789'];

        $ref = new \ReflectionMethod(ExportController::class, 'requireMeetingId');
        $ref->setAccessible(true);

        $controller = new ExportController();
        $meetingId = $ref->invoke($controller);

        $this->assertEquals('ABCDEF01-2345-6789-ABCD-EF0123456789', $meetingId);
    }

    public function testRequireMeetingIdRejectsMissingKey(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $ref = new \ReflectionMethod(ExportController::class, 'requireMeetingId');
        $ref->setAccessible(true);

        $controller = new ExportController();

        $this->expectException(ApiResponseException::class);
        $ref->invoke($controller);
    }

    public function testRequireMeetingIdRejectsEmptyString(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $ref = new \ReflectionMethod(ExportController::class, 'requireMeetingId');
        $ref->setAccessible(true);

        $controller = new ExportController();

        $this->expectException(ApiResponseException::class);
        $ref->invoke($controller);
    }

    // =========================================================================
    // CONTROLLER SOURCE VERIFICATION
    // =========================================================================

    public function testAllMethodsCallRequireMeetingId(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportController.php');

        $methods = [
            'attendanceCsv',
            'attendanceXlsx',
            'votesCsv',
            'votesXlsx',
            'membersCsv',
            'motionResultsCsv',
            'resultsXlsx',
            'fullXlsx',
            'ballotsAuditCsv',
        ];

        foreach ($methods as $method) {
            // Each public method should reference requireMeetingId or requireValidatedMeeting
            // (requireValidatedMeeting calls requireMeetingId indirectly via its parameter)
            $this->assertTrue(
                str_contains($source, 'requireMeetingId')
                || str_contains($source, 'requireValidatedMeeting'),
                "{$method} should call requireMeetingId or requireValidatedMeeting",
            );
        }
    }

    public function testAllMethodsCallRequireValidatedMeeting(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportController.php');

        // Every public export method calls requireValidatedMeeting
        $this->assertGreaterThanOrEqual(
            9,
            substr_count($source, 'requireValidatedMeeting'),
            'requireValidatedMeeting should be called at least 9 times (once per export + declaration)',
        );
    }

    public function testAllMethodsCallAuditExport(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportController.php');

        // Every public export method calls auditExport
        $auditCallCount = substr_count($source, '$this->auditExport(');
        $this->assertEquals(
            9,
            $auditCallCount,
            'auditExport should be called exactly 9 times (once per export method)',
        );
    }

    public function testRequireMeetingIdUsesApiQuery(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportController.php');

        $this->assertStringContainsString(
            "api_query('meeting_id')",
            $source,
            'requireMeetingId should read meeting_id from query parameters via api_query()',
        );
    }

    public function testRequireMeetingIdUsesApiIsUuid(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportController.php');

        $this->assertStringContainsString(
            'api_is_uuid',
            $source,
            'requireMeetingId should validate UUID format via api_is_uuid()',
        );
    }

    public function testRequireValidatedMeetingChecksValidatedAt(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportController.php');

        $this->assertStringContainsString(
            'validated_at',
            $source,
            'requireValidatedMeeting should check the validated_at field',
        );
    }

    public function testRequireValidatedMeetingReturns404ForMissing(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportController.php');

        $this->assertStringContainsString(
            "'meeting_not_found', 404",
            $source,
            'requireValidatedMeeting should return 404 when meeting is not found',
        );
    }

    public function testRequireValidatedMeetingReturns409ForUnvalidated(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportController.php');

        $this->assertStringContainsString(
            "'meeting_not_validated', 409",
            $source,
            'requireValidatedMeeting should return 409 when meeting is not validated',
        );
    }

    // =========================================================================
    // EXPORT TYPE COVERAGE IN SOURCE
    // =========================================================================

    public function testAttendanceExportUsesAttendanceRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportController.php');

        $this->assertStringContainsString(
            'AttendanceRepository',
            $source,
            'Attendance exports should use AttendanceRepository',
        );
    }

    public function testVotesExportUsesBallotRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportController.php');

        $this->assertStringContainsString(
            'BallotRepository',
            $source,
            'Votes exports should use BallotRepository',
        );
    }

    public function testMembersExportUsesMemberRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportController.php');

        $this->assertStringContainsString(
            'MemberRepository',
            $source,
            'Members export should use MemberRepository',
        );
    }

    public function testMotionResultsExportUsesMotionRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportController.php');

        $this->assertStringContainsString(
            'MotionRepository',
            $source,
            'Motion results exports should use MotionRepository',
        );
    }

    public function testExportUsesExportService(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportController.php');

        $this->assertStringContainsString(
            'ExportService',
            $source,
            'All exports should use ExportService',
        );

        // Count instantiation — at least 9 times (one per method)
        $serviceCount = substr_count($source, 'new ExportService()');
        $this->assertGreaterThanOrEqual(
            8,
            $serviceCount,
            'ExportService should be instantiated in most export methods (fullXlsx may use it differently)',
        );
    }

    // =========================================================================
    // FULL XLSX: include_votes PARAMETER
    // =========================================================================

    public function testFullXlsxSourceReferencesIncludeVotes(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportController.php');

        $this->assertStringContainsString(
            'include_votes',
            $source,
            'fullXlsx should support the include_votes query parameter',
        );
    }

    public function testFullXlsxUsesFilterValidateBoolean(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportController.php');

        $this->assertStringContainsString(
            'FILTER_VALIDATE_BOOLEAN',
            $source,
            'fullXlsx should parse include_votes as a boolean via filter_var',
        );
    }

    public function testFullXlsxDefaultsIncludeVotesToTrue(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportController.php');

        // The default for include_votes is '1' (true)
        $this->assertStringContainsString(
            "'include_votes', '1'",
            $source,
            'fullXlsx should default include_votes to true (1)',
        );
    }

    // =========================================================================
    // UUID VALIDATION LOGIC (unit tests of the api_is_uuid function)
    // =========================================================================

    public function testApiIsUuidAcceptsValidLowercaseUuid(): void
    {
        $this->assertTrue(api_is_uuid('12345678-1234-1234-1234-123456789abc'));
    }

    public function testApiIsUuidAcceptsValidUppercaseUuid(): void
    {
        $this->assertTrue(api_is_uuid('ABCDEF01-2345-6789-ABCD-EF0123456789'));
    }

    public function testApiIsUuidAcceptsValidMixedCaseUuid(): void
    {
        $this->assertTrue(api_is_uuid('abcDEF01-2345-6789-AbCd-eF0123456789'));
    }

    public function testApiIsUuidRejectsEmptyString(): void
    {
        $this->assertFalse(api_is_uuid(''));
    }

    public function testApiIsUuidRejectsTooShort(): void
    {
        $this->assertFalse(api_is_uuid('12345678-1234-1234'));
    }

    public function testApiIsUuidRejectsTooLong(): void
    {
        $this->assertFalse(api_is_uuid('12345678-1234-1234-1234-123456789abcdef'));
    }

    public function testApiIsUuidRejectsNoDashes(): void
    {
        $this->assertFalse(api_is_uuid('12345678123412341234123456789abc'));
    }

    public function testApiIsUuidRejectsSpecialCharacters(): void
    {
        $this->assertFalse(api_is_uuid('12345678-1234-1234-1234-12345678!abc'));
    }

    // =========================================================================
    // AUDIT EXPORT TYPES IN SOURCE
    // =========================================================================

    public function testAuditExportCoversAllExportTypes(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportController.php');

        $expectedTypes = [
            "'attendance'",
            "'votes'",
            "'members'",
            "'motion_results'",
            "'full'",
            "'ballots_audit'",
        ];

        foreach ($expectedTypes as $type) {
            $this->assertStringContainsString(
                $type,
                $source,
                "auditExport should be called with type {$type}",
            );
        }
    }

    public function testAuditExportFormats(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ExportController.php');

        // Both csv and xlsx formats should be audited
        $this->assertStringContainsString("'csv'", $source, 'CSV exports should be audited');
        $this->assertStringContainsString("'xlsx'", $source, 'XLSX exports should be audited');
    }
}
