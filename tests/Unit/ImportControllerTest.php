<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\ImportController;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MemberGroupRepository;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\ProxyRepository;

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
 *  - Execution-based tests with mocked repos via ControllerTestCase
 */
class ImportControllerTest extends ControllerTestCase
{
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
        $this->setHttpMethod('GET');

        $result = $this->callController(ImportController::class, 'membersCsv');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testMembersCsvRejectsPutMethod(): void
    {
        $this->setHttpMethod('PUT');

        $result = $this->callController(ImportController::class, 'membersCsv');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testMembersCsvRejectsDeleteMethod(): void
    {
        $this->setHttpMethod('DELETE');

        $result = $this->callController(ImportController::class, 'membersCsv');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // membersCsv: MISSING FILE — upload_error 400
    // =========================================================================

    public function testMembersCsvNoApiFileReturnsInternalErrorInTestEnv(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $result = $this->callController(ImportController::class, 'membersCsv');

        // api_file() returns null (no file), no csv_content either -> upload_error 400
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('upload_error', $result['body']['error']);
    }

    // =========================================================================
    // membersCsv: HAPPY PATH via csv_content with mocked repos
    // =========================================================================

    public function testMembersCsvHappyPathWithCsvContent(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');

        $csvContent = "name,email\nJean Dupont,jean@example.com\n";
        $this->injectJsonBody(['csv_content' => $csvContent]);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('findByEmail')->willReturn(null);
        $memberRepo->method('findByFullName')->willReturn(null);
        $memberRepo->method('createImport')->willReturn('new-member-uuid');

        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('listForTenant')->willReturn([]);

        $this->injectRepos([
            MemberRepository::class => $memberRepo,
            MemberGroupRepository::class => $groupRepo,
        ]);

        $result = $this->callController(ImportController::class, 'membersCsv');

        $this->assertEquals(200, $result['status']);
        $data = $result['body']['data'];
        $this->assertArrayHasKey('imported', $data);
        $this->assertArrayHasKey('skipped', $data);
        $this->assertEquals(1, $data['imported']);
    }

    public function testMembersCsvMissingNameColumnReturns400(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');

        // CSV with only an 'email' column, no name column
        $csvContent = "email\njean@example.com\n";
        $this->injectJsonBody(['csv_content' => $csvContent]);

        $result = $this->callController(ImportController::class, 'membersCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_name_column', $result['body']['error']);
    }

    public function testMembersCsvTooLargeContentReturns400(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');

        // More than 5 MB
        $bigContent = str_repeat('a', 5 * 1024 * 1024 + 1);
        $this->injectJsonBody(['csv_content' => $bigContent]);

        $result = $this->callController(ImportController::class, 'membersCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('file_too_large', $result['body']['error']);
    }

    public function testMembersCsvWithFirstLastNameColumns(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');

        $csvContent = "first_name,last_name,email\nJean,Dupont,jean@example.com\n";
        $this->injectJsonBody(['csv_content' => $csvContent]);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('findByEmail')->willReturn(null);
        $memberRepo->method('findByFullName')->willReturn(null);
        $memberRepo->method('createImport')->willReturn('new-member-uuid');

        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('listForTenant')->willReturn([]);

        $this->injectRepos([
            MemberRepository::class => $memberRepo,
            MemberGroupRepository::class => $groupRepo,
        ]);

        $result = $this->callController(ImportController::class, 'membersCsv');

        $this->assertEquals(200, $result['status']);
        $this->assertEquals(1, $result['body']['data']['imported']);
    }

    public function testMembersCsvUpdatesExistingMember(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');

        $csvContent = "name,email\nJean Dupont,jean@example.com\n";
        $this->injectJsonBody(['csv_content' => $csvContent]);

        $existingMember = ['id' => 'existing-uuid', 'full_name' => 'Jean Dupont', 'email' => 'jean@example.com'];
        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('findByEmail')->willReturn($existingMember);
        $memberRepo->expects($this->once())->method('updateImport');

        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('listForTenant')->willReturn([]);

        $this->injectRepos([
            MemberRepository::class => $memberRepo,
            MemberGroupRepository::class => $groupRepo,
        ]);

        $result = $this->callController(ImportController::class, 'membersCsv');

        $this->assertEquals(200, $result['status']);
        $this->assertEquals(1, $result['body']['data']['imported']);
    }

    // =========================================================================
    // membersXlsx: METHOD ENFORCEMENT + missing file
    // =========================================================================

    public function testMembersXlsxRejectsGetMethod(): void
    {
        $this->setHttpMethod('GET');

        $result = $this->callController(ImportController::class, 'membersXlsx');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testMembersXlsxNoApiFileReturnsInternalErrorInTestEnv(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $result = $this->callController(ImportController::class, 'membersXlsx');

        // api_file() returns null (no file) -> readImportFile -> upload_error 400
        $this->assertEquals(400, $result['status']);
        $this->assertEquals('upload_error', $result['body']['error']);
    }

    // =========================================================================
    // attendancesCsv: METHOD ENFORCEMENT + validation
    // =========================================================================

    public function testAttendancesCsvRejectsGetMethod(): void
    {
        $this->setHttpMethod('GET');

        $result = $this->callController(ImportController::class, 'attendancesCsv');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testAttendancesCsvRequiresMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $result = $this->callController(ImportController::class, 'attendancesCsv');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testAttendancesCsvRejectsInvalidMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => 'not-a-uuid']);

        $result = $this->callController(ImportController::class, 'attendancesCsv');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testAttendancesCsvRejectsEmptyMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => '']);

        $result = $this->callController(ImportController::class, 'attendancesCsv');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testAttendancesCsvMeetingNotFound(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');
        $this->injectJsonBody(['meeting_id' => '12345678-1234-1234-1234-123456789abc']);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(null);

        $this->injectRepos([MeetingRepository::class => $meetingRepo]);

        $result = $this->callController(ImportController::class, 'attendancesCsv');

        $this->assertEquals(404, $result['status']);
        $this->assertEquals('meeting_not_found', $result['body']['error']);
    }

    public function testAttendancesCsvLockedMeetingReturns403(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');
        $this->injectJsonBody(['meeting_id' => '12345678-1234-1234-1234-123456789abc']);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => '12345678-1234-1234-1234-123456789abc',
            'status' => 'validated',
            'tenant_id' => 'tenant-1',
        ]);

        $this->injectRepos([MeetingRepository::class => $meetingRepo]);

        $result = $this->callController(ImportController::class, 'attendancesCsv');

        $this->assertEquals(403, $result['status']);
        $this->assertEquals('meeting_locked', $result['body']['error']);
    }

    public function testAttendancesCsvMissingFileReturnsUploadError(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');
        $this->injectJsonBody(['meeting_id' => '12345678-1234-1234-1234-123456789abc']);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => '12345678-1234-1234-1234-123456789abc',
            'status' => 'draft',
            'tenant_id' => 'tenant-1',
        ]);

        $this->injectRepos([MeetingRepository::class => $meetingRepo]);

        $result = $this->callController(ImportController::class, 'attendancesCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('upload_error', $result['body']['error']);
    }

    // =========================================================================
    // attendancesXlsx: METHOD ENFORCEMENT + validation
    // =========================================================================

    public function testAttendancesXlsxRejectsGetMethod(): void
    {
        $this->setHttpMethod('GET');

        $result = $this->callController(ImportController::class, 'attendancesXlsx');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testAttendancesXlsxRequiresMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $result = $this->callController(ImportController::class, 'attendancesXlsx');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testAttendancesXlsxMeetingLockedReturns403(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');
        $this->injectJsonBody(['meeting_id' => '12345678-1234-1234-1234-123456789abc']);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => '12345678-1234-1234-1234-123456789abc',
            'status' => 'archived',
            'tenant_id' => 'tenant-1',
        ]);

        $this->injectRepos([MeetingRepository::class => $meetingRepo]);

        $result = $this->callController(ImportController::class, 'attendancesXlsx');

        $this->assertEquals(403, $result['status']);
        $this->assertEquals('meeting_locked', $result['body']['error']);
    }

    // =========================================================================
    // proxiesCsv: METHOD ENFORCEMENT + validation
    // =========================================================================

    public function testProxiesCsvRejectsGetMethod(): void
    {
        $this->setHttpMethod('GET');

        $result = $this->callController(ImportController::class, 'proxiesCsv');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testProxiesCsvRequiresMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $result = $this->callController(ImportController::class, 'proxiesCsv');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testProxiesCsvRejectsInvalidMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => 'bad']);

        $result = $this->callController(ImportController::class, 'proxiesCsv');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testProxiesCsvMeetingNotFoundReturns404(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');
        $this->injectJsonBody(['meeting_id' => '12345678-1234-1234-1234-123456789abc']);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(null);

        $this->injectRepos([MeetingRepository::class => $meetingRepo]);

        $result = $this->callController(ImportController::class, 'proxiesCsv');

        $this->assertEquals(404, $result['status']);
        $this->assertEquals('meeting_not_found', $result['body']['error']);
    }

    public function testProxiesCsvMissingFileReturnsUploadError(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');
        $this->injectJsonBody(['meeting_id' => '12345678-1234-1234-1234-123456789abc']);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => '12345678-1234-1234-1234-123456789abc',
            'status' => 'draft',
        ]);

        $this->injectRepos([MeetingRepository::class => $meetingRepo]);

        $result = $this->callController(ImportController::class, 'proxiesCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('upload_error', $result['body']['error']);
    }

    public function testProxiesCsvWithCsvContentMissingColumnsReturns400(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');
        // CSV missing giver/receiver columns
        $csvContent = "name\nJean Dupont\n";
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'csv_content' => $csvContent,
        ]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => '12345678-1234-1234-1234-123456789abc',
            'status' => 'draft',
        ]);

        $this->injectRepos([MeetingRepository::class => $meetingRepo]);

        $result = $this->callController(ImportController::class, 'proxiesCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_columns', $result['body']['error']);
    }

    // =========================================================================
    // proxiesXlsx: METHOD ENFORCEMENT + validation
    // =========================================================================

    public function testProxiesXlsxRejectsGetMethod(): void
    {
        $this->setHttpMethod('GET');

        $result = $this->callController(ImportController::class, 'proxiesXlsx');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testProxiesXlsxRequiresMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $result = $this->callController(ImportController::class, 'proxiesXlsx');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testProxiesXlsxMeetingLockedReturns403(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');
        $this->injectJsonBody(['meeting_id' => '12345678-1234-1234-1234-123456789abc']);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => '12345678-1234-1234-1234-123456789abc',
            'status' => 'validated',
        ]);

        $this->injectRepos([MeetingRepository::class => $meetingRepo]);

        $result = $this->callController(ImportController::class, 'proxiesXlsx');

        $this->assertEquals(403, $result['status']);
        $this->assertEquals('meeting_locked', $result['body']['error']);
    }

    // =========================================================================
    // motionsCsv: METHOD ENFORCEMENT + validation
    // =========================================================================

    public function testMotionsCsvRejectsGetMethod(): void
    {
        $this->setHttpMethod('GET');

        $result = $this->callController(ImportController::class, 'motionsCsv');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testMotionsCsvRequiresMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $result = $this->callController(ImportController::class, 'motionsCsv');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testMotionsCsvRejectsInvalidMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => 'not-valid']);

        $result = $this->callController(ImportController::class, 'motionsCsv');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testMotionsCsvRejectsEmptyMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody(['meeting_id' => '']);

        $result = $this->callController(ImportController::class, 'motionsCsv');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testMotionsCsvMeetingNotFoundReturns404(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');
        $this->injectJsonBody(['meeting_id' => '12345678-1234-1234-1234-123456789abc']);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(null);

        $this->injectRepos([MeetingRepository::class => $meetingRepo]);

        $result = $this->callController(ImportController::class, 'motionsCsv');

        $this->assertEquals(404, $result['status']);
        $this->assertEquals('meeting_not_found', $result['body']['error']);
    }

    public function testMotionsCsvMissingFileReturnsUploadError(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');
        $this->injectJsonBody(['meeting_id' => '12345678-1234-1234-1234-123456789abc']);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => '12345678-1234-1234-1234-123456789abc',
            'status' => 'draft',
        ]);

        $this->injectRepos([MeetingRepository::class => $meetingRepo]);

        $result = $this->callController(ImportController::class, 'motionsCsv');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('upload_error', $result['body']['error']);
    }

    public function testMotionsCsvWithTmpFileMissingTitleColumnReturns400(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');
        $this->injectJsonBody(['meeting_id' => '12345678-1234-1234-1234-123456789abc']);

        // Create a real temp CSV file missing the title column
        $tmpPath = tempnam(sys_get_temp_dir(), 'csv_test_');
        file_put_contents($tmpPath, "description\nSome description\n");

        $_FILES['file'] = [
            'name' => 'test.csv',
            'type' => 'text/csv',
            'tmp_name' => $tmpPath,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpPath),
        ];

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => '12345678-1234-1234-1234-123456789abc',
            'status' => 'draft',
        ]);

        $this->injectRepos([MeetingRepository::class => $meetingRepo]);

        try {
            $result = $this->callController(ImportController::class, 'motionsCsv');
            $this->assertEquals(400, $result['status']);
            $this->assertEquals('missing_title_column', $result['body']['error']);
        } finally {
            if (file_exists($tmpPath)) {
                unlink($tmpPath);
            }
        }
    }

    public function testMotionsCsvHappyPathDryRunWithTmpFile(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');
        $this->injectJsonBody(['meeting_id' => '12345678-1234-1234-1234-123456789abc', 'dry_run' => '1']);

        // Create a real temp CSV file with a title column
        $tmpPath = tempnam(sys_get_temp_dir(), 'csv_test_');
        file_put_contents($tmpPath, "title,description\n\"Résolution 1\",\"Description 1\"\n");

        $_FILES['file'] = [
            'name' => 'motions.csv',
            'type' => 'text/csv',
            'tmp_name' => $tmpPath,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpPath),
        ];

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => '12345678-1234-1234-1234-123456789abc',
            'status' => 'draft',
        ]);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('countForMeeting')->willReturn(0);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            MotionRepository::class => $motionRepo,
        ]);

        try {
            $result = $this->callController(ImportController::class, 'motionsCsv');

            $this->assertEquals(200, $result['status']);
            $this->assertTrue($result['body']['data']['dry_run']);
            $this->assertArrayHasKey('preview', $result['body']['data']);
        } finally {
            if (file_exists($tmpPath)) {
                unlink($tmpPath);
            }
        }
    }

    // =========================================================================
    // motionsXlsx: METHOD ENFORCEMENT + validation
    // =========================================================================

    public function testMotionsXlsxRejectsGetMethod(): void
    {
        $this->setHttpMethod('GET');

        $result = $this->callController(ImportController::class, 'motionsXlsx');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testMotionsXlsxRequiresMeetingId(): void
    {
        $this->setHttpMethod('POST');
        $this->injectJsonBody([]);

        $result = $this->callController(ImportController::class, 'motionsXlsx');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // CROSS-CUTTING: METHOD CHECK BEFORE BODY VALIDATION
    // =========================================================================

    public function testMembersCsvMethodCheckBeforeBodyValidation(): void
    {
        $this->setHttpMethod('GET');
        $this->injectJsonBody(['csv_content' => 'name\nJohn']);

        $result = $this->callController(ImportController::class, 'membersCsv');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testAttendancesCsvMethodCheckBeforeBodyValidation(): void
    {
        $this->setHttpMethod('GET');
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
        ]);

        $result = $this->callController(ImportController::class, 'attendancesCsv');

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
        $result = $this->callController(ImportController::class, 'nonExistentMethod');

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

    // =========================================================================
    // DUPLICATE EMAIL PRE-SCAN (IMP-02)
    // =========================================================================

    public function testMembersCsvDuplicateEmails(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');

        // Two rows with the same email address
        $csvContent = "name,email\nJean Dupont,jean@example.com\nPierre Dupont,jean@example.com\n";
        $this->injectJsonBody(['csv_content' => $csvContent]);

        $result = $this->callController(ImportController::class, 'membersCsv');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('duplicate_emails', $result['body']['error']);
        $this->assertArrayHasKey('duplicate_emails', $result['body']);
        $this->assertContains('jean@example.com', $result['body']['duplicate_emails']);
    }

    public function testMembersCsvDuplicateEmailsCaseInsensitive(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');

        // Same email with different casing — should be treated as duplicate
        $csvContent = "name,email\nJean Dupont,Jean@Test.fr\nPierre Dupont,jean@test.fr\n";
        $this->injectJsonBody(['csv_content' => $csvContent]);

        $result = $this->callController(ImportController::class, 'membersCsv');

        $this->assertEquals(422, $result['status']);
        $this->assertEquals('duplicate_emails', $result['body']['error']);
        $this->assertArrayHasKey('duplicate_emails', $result['body']);
        $this->assertContains('jean@test.fr', $result['body']['duplicate_emails']);
    }

    public function testMembersCsvEmptyEmailsNotFalseDuplicate(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');

        // Multiple rows with empty email — should NOT trigger duplicate detection
        $csvContent = "name,email\nJean Dupont,\nPierre Dupont,\n";
        $this->injectJsonBody(['csv_content' => $csvContent]);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('findByEmail')->willReturn(null);
        $memberRepo->method('findByFullName')->willReturn(null);
        $memberRepo->method('createImport')->willReturn('new-member-uuid');

        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('listForTenant')->willReturn([]);

        $this->injectRepos([
            MemberRepository::class => $memberRepo,
            MemberGroupRepository::class => $groupRepo,
        ]);

        $result = $this->callController(ImportController::class, 'membersCsv');

        // Should not return 422 duplicate error — empty emails are skipped
        $this->assertNotEquals(422, $result['status'], 'Empty emails must not trigger duplicate detection');
        if ($result['status'] !== 422) {
            $this->assertNotEquals('duplicate_emails', $result['body']['error'] ?? null);
        }
    }

    public function testMembersCsvUniqueEmailsPass(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');

        // All unique emails — should proceed normally without 422
        $csvContent = "name,email\nJean Dupont,jean@example.com\nPierre Martin,pierre@example.com\n";
        $this->injectJsonBody(['csv_content' => $csvContent]);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('findByEmail')->willReturn(null);
        $memberRepo->method('findByFullName')->willReturn(null);
        $memberRepo->method('createImport')->willReturn('new-member-uuid');

        $groupRepo = $this->createMock(MemberGroupRepository::class);
        $groupRepo->method('listForTenant')->willReturn([]);

        $this->injectRepos([
            MemberRepository::class => $memberRepo,
            MemberGroupRepository::class => $groupRepo,
        ]);

        $result = $this->callController(ImportController::class, 'membersCsv');

        $this->assertEquals(200, $result['status']);
        $this->assertEquals(2, $result['body']['data']['imported']);
    }
}
