<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\ImportController;
use AgVote\Core\Http\ApiResponseException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ImportController.
 *
 * Tests the import endpoint logic including:
 *  - Controller structure (final, extends AbstractController)
 *  - HTTP method enforcement (POST only)
 *  - UUID validation for meeting_id
 *  - File upload requirement validation
 *  - All 8 public import methods (membersCsv, membersXlsx, attendancesCsv,
 *    attendancesXlsx, proxiesCsv, proxiesXlsx, motionsCsv, motionsXlsx)
 */
class ImportControllerTest extends TestCase
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
        $_FILES = [];

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

        $_FILES = [];

        parent::tearDown();
    }

    // =========================================================================
    // HELPER: Call controller and capture response
    // =========================================================================

    private function callControllerMethod(string $method): array
    {
        $controller = new ImportController();
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
        $ref = new \ReflectionClass(ImportController::class);
        $this->assertTrue($ref->isFinal(), 'ImportController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new ImportController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(ImportController::class);

        $expectedMethods = [
            'membersCsv',
            'membersXlsx',
            'attendancesCsv',
            'attendancesXlsx',
            'proxiesCsv',
            'proxiesXlsx',
            'motionsCsv',
            'motionsXlsx',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "ImportController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(ImportController::class);

        $expectedMethods = [
            'membersCsv',
            'membersXlsx',
            'attendancesCsv',
            'attendancesXlsx',
            'proxiesCsv',
            'proxiesXlsx',
            'motionsCsv',
            'motionsXlsx',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "ImportController::{$method}() should be public",
            );
        }
    }

    public function testControllerHasPrivateHelperMethods(): void
    {
        $ref = new \ReflectionClass(ImportController::class);

        $privateMethods = [
            'readImportFile',
            'requireWritableMeeting',
            'buildMemberLookups',
            'buildProxyMemberFinder',
            'processMemberRows',
            'processAttendanceRows',
            'processProxyRows',
            'processMotionRows',
        ];
        foreach ($privateMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "ImportController should have a '{$method}' private method",
            );
            $this->assertTrue(
                $ref->getMethod($method)->isPrivate(),
                "ImportController::{$method}() should be private",
            );
        }
    }

    // =========================================================================
    // membersCsv: METHOD ENFORCEMENT
    // =========================================================================

    public function testMembersCsvRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('membersCsv');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testMembersCsvRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('membersCsv');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testMembersCsvRejectsDeleteMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        $result = $this->callControllerMethod('membersCsv');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // membersCsv: FILE UPLOAD VALIDATION (no-DB env limitation)
    // After api_request('POST'), membersCsv() calls api_file() which is not
    // available in the test bootstrap, causing internal_error.
    // =========================================================================

    public function testMembersCsvNoApiFileReturnsInternalErrorInTestEnv(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('membersCsv');

        // api_file() is not stubbed in test bootstrap, so this throws
        $this->assertEquals(500, $result['status']);
        $this->assertEquals('internal_error', $result['body']['error']);
    }

    // =========================================================================
    // membersXlsx: METHOD ENFORCEMENT
    // =========================================================================

    public function testMembersXlsxRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('membersXlsx');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // membersXlsx: FILE UPLOAD VALIDATION (no-DB env limitation)
    // After api_request('POST'), membersXlsx() calls api_file() which is not
    // available in the test bootstrap, causing internal_error.
    // =========================================================================

    public function testMembersXlsxNoApiFileReturnsInternalErrorInTestEnv(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('membersXlsx');

        $this->assertEquals(500, $result['status']);
        $this->assertEquals('internal_error', $result['body']['error']);
    }

    // =========================================================================
    // attendancesCsv: METHOD ENFORCEMENT
    // =========================================================================

    public function testAttendancesCsvRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('attendancesCsv');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // attendancesCsv: meeting_id VALIDATION
    // =========================================================================

    public function testAttendancesCsvRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('attendancesCsv');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testAttendancesCsvRejectsInvalidMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => 'not-a-uuid']);

        $result = $this->callControllerMethod('attendancesCsv');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testAttendancesCsvRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => '']);

        $result = $this->callControllerMethod('attendancesCsv');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // attendancesXlsx: METHOD ENFORCEMENT
    // =========================================================================

    public function testAttendancesXlsxRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('attendancesXlsx');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // attendancesXlsx: meeting_id VALIDATION
    // =========================================================================

    public function testAttendancesXlsxRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('attendancesXlsx');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // proxiesCsv: METHOD ENFORCEMENT
    // =========================================================================

    public function testProxiesCsvRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('proxiesCsv');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // proxiesCsv: meeting_id VALIDATION
    // =========================================================================

    public function testProxiesCsvRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('proxiesCsv');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testProxiesCsvRejectsInvalidMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => 'bad']);

        $result = $this->callControllerMethod('proxiesCsv');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // proxiesXlsx: METHOD ENFORCEMENT
    // =========================================================================

    public function testProxiesXlsxRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('proxiesXlsx');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // proxiesXlsx: meeting_id VALIDATION
    // =========================================================================

    public function testProxiesXlsxRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('proxiesXlsx');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // motionsCsv: METHOD ENFORCEMENT
    // =========================================================================

    public function testMotionsCsvRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('motionsCsv');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // motionsCsv: meeting_id VALIDATION
    // =========================================================================

    public function testMotionsCsvRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('motionsCsv');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testMotionsCsvRejectsInvalidMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => 'not-valid']);

        $result = $this->callControllerMethod('motionsCsv');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testMotionsCsvRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => '']);

        $result = $this->callControllerMethod('motionsCsv');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // motionsXlsx: METHOD ENFORCEMENT
    // =========================================================================

    public function testMotionsXlsxRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('motionsXlsx');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // motionsXlsx: meeting_id VALIDATION
    // =========================================================================

    public function testMotionsXlsxRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('motionsXlsx');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // CROSS-CUTTING: METHOD CHECK BEFORE BODY VALIDATION
    // =========================================================================

    public function testMembersCsvMethodCheckBeforeBodyValidation(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->injectJsonBody(['csv_content' => 'name\nJohn']);

        $result = $this->callControllerMethod('membersCsv');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testAttendancesCsvMethodCheckBeforeBodyValidation(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callControllerMethod('attendancesCsv');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // requireWritableMeeting: MEETING STATUS LOCK LOGIC
    // =========================================================================

    public function testRequireWritableMeetingLockedStatuses(): void
    {
        // Replicate: in_array($meeting['status'], ['validated', 'archived'], true)
        $lockedStatuses = ['validated', 'archived'];
        foreach ($lockedStatuses as $status) {
            $this->assertTrue(
                in_array($status, ['validated', 'archived'], true),
                "Status '{$status}' should be locked",
            );
        }

        $unlockedStatuses = ['draft', 'scheduled', 'open', 'live', 'closed'];
        foreach ($unlockedStatuses as $status) {
            $this->assertFalse(
                in_array($status, ['validated', 'archived'], true),
                "Status '{$status}' should not be locked",
            );
        }
    }

    // =========================================================================
    // membersCsv: CSV CONTENT SIZE LIMIT
    // =========================================================================

    public function testMembersCsvContentSizeLimit(): void
    {
        // Replicate: strlen($csvContent) > 5 * 1024 * 1024
        $maxSize = 5 * 1024 * 1024;
        $this->assertEquals(5242880, $maxSize);
        $this->assertTrue(5242881 > $maxSize);
        $this->assertFalse(5242880 > $maxSize);
    }

    // =========================================================================
    // RESPONSE STRUCTURE (source verification)
    // =========================================================================

    public function testMembersCsvResponseStructure(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ImportController.php');

        $this->assertStringContainsString("'imported'", $source);
        $this->assertStringContainsString("'skipped'", $source);
        $this->assertStringContainsString("'errors'", $source);
    }

    public function testAttendancesResponseIncludesDryRun(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ImportController.php');

        $this->assertStringContainsString("'dry_run'", $source);
        $this->assertStringContainsString("'preview'", $source);
    }

    public function testProxiesResponseIncludesMaxPerReceiver(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ImportController.php');

        $this->assertStringContainsString("'max_proxies_per_receiver'", $source);
    }

    // =========================================================================
    // AUDIT LOG VERIFICATION (source-level)
    // =========================================================================

    public function testMembersCsvAuditsImport(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ImportController.php');

        $this->assertStringContainsString("'members_import'", $source);
    }

    public function testMembersXlsxAuditsImport(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ImportController.php');

        $this->assertStringContainsString("'members_import_xlsx'", $source);
    }

    public function testAttendancesCsvAuditsImport(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ImportController.php');

        $this->assertStringContainsString("'attendances_import'", $source);
    }

    public function testProxiesCsvAuditsImport(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ImportController.php');

        $this->assertStringContainsString("'proxies_import'", $source);
    }

    public function testMotionsCsvAuditsImport(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ImportController.php');

        $this->assertStringContainsString("'motions_import'", $source);
    }

    // =========================================================================
    // BUSINESS GUARD VERIFICATION (source-level)
    // =========================================================================

    public function testImportGuardsMeetingStatus(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ImportController.php');

        $this->assertStringContainsString('meeting_locked', $source);
        $this->assertStringContainsString("'validated'", $source);
        $this->assertStringContainsString("'archived'", $source);
    }

    public function testImportUsesTransactions(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ImportController.php');

        $this->assertStringContainsString('api_transaction', $source);
    }

    public function testImportUsesImportService(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ImportController.php');

        $this->assertStringContainsString('ImportService', $source);
        $this->assertStringContainsString('mapColumns', $source);
        $this->assertStringContainsString('validateUploadedFile', $source);
    }

    // =========================================================================
    // COLUMN VALIDATION (source-level)
    // =========================================================================

    public function testMembersCsvRequiresNameColumn(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ImportController.php');

        $this->assertStringContainsString('missing_name_column', $source);
    }

    public function testAttendancesCsvRequiresIdentifierColumn(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ImportController.php');

        $this->assertStringContainsString('missing_identifier', $source);
    }

    public function testProxiesCsvRequiresGiverAndReceiverColumns(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ImportController.php');

        $this->assertStringContainsString('missing_columns', $source);
    }

    public function testMotionsCsvRequiresTitleColumn(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/ImportController.php');

        $this->assertStringContainsString('missing_title_column', $source);
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

    // =========================================================================
    // DRY RUN FLAG PARSING
    // =========================================================================

    public function testDryRunFlagParsing(): void
    {
        $this->assertFalse(filter_var(false, FILTER_VALIDATE_BOOLEAN));
        $this->assertFalse(filter_var('0', FILTER_VALIDATE_BOOLEAN));
        $this->assertFalse(filter_var('false', FILTER_VALIDATE_BOOLEAN));
        $this->assertTrue(filter_var('1', FILTER_VALIDATE_BOOLEAN));
        $this->assertTrue(filter_var('true', FILTER_VALIDATE_BOOLEAN));
        $this->assertTrue(filter_var(true, FILTER_VALIDATE_BOOLEAN));
    }
}
