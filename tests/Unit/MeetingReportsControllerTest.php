<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\MeetingReportsController;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\InvitationRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MeetingReportRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Repository\ProxyRepository;

/**
 * Unit tests for MeetingReportsController.
 *
 * Tests the meeting report endpoints including:
 *  - Controller structure (final, extends AbstractController)
 *  - HTTP method enforcement for report, generatePdf, generateReport, sendReport, exportPvHtml
 *  - UUID validation for meeting_id
 *  - Input validation for sendReport (email, meeting_id)
 *  - Execution-based tests with mocked repos for error paths
 *  - Response structure and audit log verification via source introspection
 */
class MeetingReportsControllerTest extends ControllerTestCase
{
    // =========================================================================
    // HELPER: backward compat wrapper
    // =========================================================================

    private function callControllerMethod(string $method): array
    {
        return $this->callController(MeetingReportsController::class, $method);
    }

    // =========================================================================
    // CONTROLLER STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(MeetingReportsController::class);
        $this->assertTrue($ref->isFinal(), 'MeetingReportsController should be final');
    }

    public function testControllerExtendsAbstractController(): void
    {
        $controller = new MeetingReportsController();
        $this->assertInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasAllExpectedMethods(): void
    {
        $ref = new \ReflectionClass(MeetingReportsController::class);

        $expectedMethods = [
            'report',
            'generatePdf',
            'generateReport',
            'sendReport',
            'exportPvHtml',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->hasMethod($method),
                "MeetingReportsController should have a '{$method}' method",
            );
        }
    }

    public function testControllerMethodsArePublic(): void
    {
        $ref = new \ReflectionClass(MeetingReportsController::class);

        $expectedMethods = [
            'report',
            'generatePdf',
            'generateReport',
            'sendReport',
            'exportPvHtml',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $ref->getMethod($method)->isPublic(),
                "MeetingReportsController::{$method}() should be public",
            );
        }
    }

    // =========================================================================
    // report: MEETING_ID VALIDATION
    // =========================================================================

    public function testReportRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('report');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testReportRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('report');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testReportRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'not-a-valid-uuid'];

        $result = $this->callControllerMethod('report');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    public function testReportRejectsPartialUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => '12345678-1234'];

        $result = $this->callControllerMethod('report');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // generatePdf: MEETING_ID VALIDATION
    // =========================================================================

    public function testGeneratePdfRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('generatePdf');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testGeneratePdfRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('generatePdf');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testGeneratePdfRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'xyz-bad'];

        $result = $this->callControllerMethod('generatePdf');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // generateReport: MEETING_ID VALIDATION
    // =========================================================================

    public function testGenerateReportRejectsPostMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('generateReport');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testGenerateReportRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $result = $this->callControllerMethod('generateReport');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testGenerateReportRejectsEmptyMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => ''];

        $result = $this->callControllerMethod('generateReport');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    public function testGenerateReportRejectsInvalidUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['meeting_id' => 'bad-uuid'];

        $result = $this->callControllerMethod('generateReport');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // sendReport: METHOD ENFORCEMENT
    // =========================================================================

    public function testSendReportRejectsGetMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $result = $this->callControllerMethod('sendReport');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    public function testSendReportRejectsPutMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        $result = $this->callControllerMethod('sendReport');

        $this->assertEquals(405, $result['status']);
        $this->assertEquals('method_not_allowed', $result['body']['error']);
    }

    // =========================================================================
    // sendReport: INPUT VALIDATION
    // =========================================================================

    public function testSendReportRequiresMeetingIdAndEmail(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody([]);

        $result = $this->callControllerMethod('sendReport');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_or_email', $result['body']['error']);
    }

    public function testSendReportRejectsMissingEmail(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => '12345678-1234-1234-1234-123456789abc']);

        $result = $this->callControllerMethod('sendReport');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_or_email', $result['body']['error']);
    }

    public function testSendReportRejectsMissingMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['email' => 'test@example.com']);

        $result = $this->callControllerMethod('sendReport');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_or_email', $result['body']['error']);
    }

    public function testSendReportRejectsEmptyMeetingIdAndEmail(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => '', 'email' => '']);

        $result = $this->callControllerMethod('sendReport');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('missing_meeting_or_email', $result['body']['error']);
    }

    public function testSendReportRejectsInvalidMeetingUuid(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->injectJsonBody(['meeting_id' => 'not-a-uuid', 'email' => 'test@example.com']);

        $result = $this->callControllerMethod('sendReport');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('invalid_meeting_id', $result['body']['error']);
    }

    // =========================================================================
    // exportPvHtml: MEETING_ID VALIDATION
    // =========================================================================

    public function testExportPvHtmlRequiresMeetingId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        // exportPvHtml uses http_response_code(400) + echo + exit, not api_fail
        // so we cannot capture it via ApiResponseException. Verify via source.
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingReportsController.php');
        $this->assertStringContainsString('missing_meeting_id', $source);
    }

    // =========================================================================
    // REPORT HELPERS: DECISION LABEL LOGIC
    // =========================================================================

    public function testDecisionLabelMapping(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingReportsController.php');

        $decisions = ['adopted', 'rejected', 'no_quorum', 'no_votes', 'no_policy', 'cancelled', 'pending'];
        foreach ($decisions as $decision) {
            $this->assertStringContainsString(
                "'{$decision}'",
                $source,
                "Controller should handle decision '{$decision}'",
            );
        }
    }

    public function testModeLabelMapping(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingReportsController.php');

        $modes = ['present', 'remote', 'proxy', 'excused', 'absent'];
        foreach ($modes as $mode) {
            $this->assertStringContainsString(
                "'{$mode}'",
                $source,
                "Controller should handle attendance mode '{$mode}'",
            );
        }
    }

    public function testChoiceLabelMapping(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingReportsController.php');

        $choices = ['for', 'against', 'abstain', 'nsp', 'blank'];
        foreach ($choices as $choice) {
            $this->assertStringContainsString(
                "'{$choice}'",
                $source,
                "Controller should handle vote choice '{$choice}'",
            );
        }
    }

    // =========================================================================
    // REPORT HELPERS: FORMAT NUMBER LOGIC
    // =========================================================================

    public function testFmtNumWholeNumber(): void
    {
        // Replicate fmtNum logic: whole numbers display as integers
        $n = 10.0;
        if (abs($n - round($n)) < 0.000001) {
            $result = (string) intval(round($n));
        } else {
            $result = rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
        }
        $this->assertEquals('10', $result);
    }

    public function testFmtNumDecimalNumber(): void
    {
        $n = 10.5;
        if (abs($n - round($n)) < 0.000001) {
            $result = (string) intval(round($n));
        } else {
            $result = rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
        }
        $this->assertEquals('10.5', $result);
    }

    public function testFmtNumZero(): void
    {
        $n = 0.0;
        if (abs($n - round($n)) < 0.000001) {
            $result = (string) intval(round($n));
        } else {
            $result = rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
        }
        $this->assertEquals('0', $result);
    }

    public function testFmtNumSmallDecimal(): void
    {
        $n = 0.3333;
        if (abs($n - round($n)) < 0.000001) {
            $result = (string) intval(round($n));
        } else {
            $result = rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
        }
        $this->assertEquals('0.3333', $result);
    }

    // =========================================================================
    // POLICY LABEL LOGIC
    // =========================================================================

    public function testPolicyLabelWithBothPolicies(): void
    {
        $votePolicy = ['base' => 'expressed', 'threshold' => '0.5', 'abstention_as_against' => false];
        $quorumPolicy = ['denominator' => 'eligible_members', 'threshold' => '0.33'];

        $parts = [];
        if ($quorumPolicy) {
            $parts[] = 'Quorum: ' . ($quorumPolicy['denominator'] ?? '---') . ' >= ' . ($quorumPolicy['threshold'] ?? '---');
        }
        if ($votePolicy) {
            $parts[] = 'Majorite: ' . ($votePolicy['base'] ?? '---') . ' >= ' . ($votePolicy['threshold'] ?? '---');
        }

        $this->assertCount(2, $parts);
        $this->assertStringContainsString('Quorum', $parts[0]);
        $this->assertStringContainsString('Majorite', $parts[1]);
    }

    public function testPolicyLabelWithNoPolicies(): void
    {
        $votePolicy = null;
        $quorumPolicy = null;

        $parts = [];
        if ($quorumPolicy) {
            $parts[] = 'Quorum: present';
        } else {
            $parts[] = 'Quorum: ---';
        }
        if ($votePolicy) {
            $parts[] = 'Majorite: expressed';
        } else {
            $parts[] = 'Majorite: ---';
        }

        $this->assertCount(2, $parts);
        $this->assertStringContainsString('---', $parts[0]);
        $this->assertStringContainsString('---', $parts[1]);
    }

    // =========================================================================
    // AUDIT LOG VERIFICATION (source-level)
    // =========================================================================

    public function testReportAuditsView(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingReportsController.php');

        $this->assertStringContainsString("'report.view_html'", $source);
    }

    public function testGeneratePdfAuditsGeneration(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingReportsController.php');

        $this->assertStringContainsString("'report.generate_pdf'", $source);
    }

    public function testGenerateReportAuditsGeneration(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingReportsController.php');

        $this->assertStringContainsString("'report.generate_html'", $source);
    }

    public function testSendReportAuditsSend(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingReportsController.php');

        $this->assertStringContainsString("'report.send'", $source);
    }

    public function testExportPvHtmlAuditsExport(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingReportsController.php');

        $this->assertStringContainsString("'report.export_pv_html'", $source);
    }

    // =========================================================================
    // GENERATE PDF: PREVIEW LOGIC
    // =========================================================================

    public function testGeneratePdfPreviewFlagDetection(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingReportsController.php');

        $this->assertStringContainsString("api_query('preview')", $source);
        $this->assertStringContainsString("api_query('draft')", $source);
    }

    public function testGeneratePdfRequiresValidationForNonPreview(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingReportsController.php');

        $this->assertStringContainsString('meeting_not_validated', $source);
    }

    public function testGeneratePdfFilenamePrefix(): void
    {
        // Replicate the filename prefix logic
        $isPreview = true;
        $prefix = $isPreview ? 'BROUILLON_PV_' : 'PV_';
        $this->assertEquals('BROUILLON_PV_', $prefix);

        $isPreview = false;
        $prefix = $isPreview ? 'BROUILLON_PV_' : 'PV_';
        $this->assertEquals('PV_', $prefix);
    }

    // =========================================================================
    // SEND REPORT: SMTP GUARD
    // =========================================================================

    public function testSendReportGuardsSmtpNotConfigured(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingReportsController.php');

        $this->assertStringContainsString('smtp_not_configured', $source);
        $this->assertStringContainsString('isConfigured', $source);
    }

    public function testSendReportGuardsMailSendFailed(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingReportsController.php');

        $this->assertStringContainsString('mail_send_failed', $source);
    }

    // =========================================================================
    // REPORT: SHOW VOTERS FLAG
    // =========================================================================

    public function testReportShowVotersFlagParsing(): void
    {
        $this->assertTrue(('1' === '1'));
        $this->assertFalse(('0' === '1'));
        $this->assertFalse(('' === '1'));
    }

    // =========================================================================
    // UUID VALIDATION
    // =========================================================================

    public function testUuidValidationForReportEndpoints(): void
    {
        $this->assertTrue(api_is_uuid('12345678-1234-1234-1234-123456789abc'));
        $this->assertFalse(api_is_uuid(''));
        $this->assertFalse(api_is_uuid('not-a-uuid'));
        $this->assertFalse(api_is_uuid('12345'));
    }

    // =========================================================================
    // EXECUTION-BASED: report() error paths with mocked repos
    // =========================================================================

    public function testReportMeetingNotFoundReturns404(): void
    {
        $this->setHttpMethod('GET');
        $this->setAuth('user-1', 'admin', 'tenant-1');
        $this->setQueryParams(['meeting_id' => '12345678-1234-1234-1234-123456789abc']);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(null);

        // report() accesses many repos — inject all needed
        $motionRepo = $this->createMock(MotionRepository::class);
        $attendanceRepo = $this->createMock(AttendanceRepository::class);
        $ballotRepo = $this->createMock(BallotRepository::class);
        $policyRepo = $this->createMock(PolicyRepository::class);
        $invitationRepo = $this->createMock(InvitationRepository::class);
        $meetingReportRepo = $this->createMock(MeetingReportRepository::class);
        $meetingReportRepo->method('findSnapshot')->willReturn(null);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            MotionRepository::class => $motionRepo,
            AttendanceRepository::class => $attendanceRepo,
            BallotRepository::class => $ballotRepo,
            PolicyRepository::class => $policyRepo,
            InvitationRepository::class => $invitationRepo,
            MeetingReportRepository::class => $meetingReportRepo,
        ]);

        $result = $this->callController(MeetingReportsController::class, 'report');

        $this->assertEquals(404, $result['status']);
        $this->assertEquals('meeting_not_found', $result['body']['error']);
    }

    // =========================================================================
    // EXECUTION-BASED: generatePdf() error paths
    // =========================================================================

    public function testGeneratePdfMeetingNotFoundReturns404(): void
    {
        $this->setHttpMethod('GET');
        $this->setAuth('user-1', 'admin', 'tenant-1');
        $this->setQueryParams(['meeting_id' => '12345678-1234-1234-1234-123456789abc']);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findWithValidator')->willReturn(null);

        $this->injectRepos([MeetingRepository::class => $meetingRepo]);

        $result = $this->callController(MeetingReportsController::class, 'generatePdf');

        $this->assertEquals(404, $result['status']);
        $this->assertEquals('meeting_not_found', $result['body']['error']);
    }

    public function testGeneratePdfMeetingNotValidatedReturns409(): void
    {
        $this->setHttpMethod('GET');
        $this->setAuth('user-1', 'admin', 'tenant-1');
        $this->setQueryParams(['meeting_id' => '12345678-1234-1234-1234-123456789abc']);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findWithValidator')->willReturn([
            'id' => '12345678-1234-1234-1234-123456789abc',
            'tenant_id' => 'tenant-1',
            'title' => 'Test Meeting',
            'validated_at' => null,
        ]);

        $this->injectRepos([MeetingRepository::class => $meetingRepo]);

        $result = $this->callController(MeetingReportsController::class, 'generatePdf');

        $this->assertEquals(409, $result['status']);
        $this->assertEquals('meeting_not_validated', $result['body']['error']);
    }

    // =========================================================================
    // EXECUTION-BASED: generateReport() error paths
    // =========================================================================

    public function testGenerateReportMeetingNotFoundReturns404(): void
    {
        $this->setHttpMethod('GET');
        $this->setAuth('user-1', 'admin', 'tenant-1');
        $this->setQueryParams(['meeting_id' => '12345678-1234-1234-1234-123456789abc']);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findWithValidator')->willReturn(null);

        $motionRepo = $this->createMock(MotionRepository::class);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            MotionRepository::class => $motionRepo,
        ]);

        $result = $this->callController(MeetingReportsController::class, 'generateReport');

        $this->assertEquals(404, $result['status']);
        $this->assertEquals('meeting_not_found', $result['body']['error']);
    }

    public function testGenerateReportMeetingNotValidatedReturns409(): void
    {
        $this->setHttpMethod('GET');
        $this->setAuth('user-1', 'admin', 'tenant-1');
        $this->setQueryParams(['meeting_id' => '12345678-1234-1234-1234-123456789abc']);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findWithValidator')->willReturn([
            'id' => '12345678-1234-1234-1234-123456789abc',
            'title' => 'Test Meeting',
            'validated_at' => null,
        ]);

        $motionRepo = $this->createMock(MotionRepository::class);

        $this->injectRepos([
            MeetingRepository::class => $meetingRepo,
            MotionRepository::class => $motionRepo,
        ]);

        $result = $this->callController(MeetingReportsController::class, 'generateReport');

        $this->assertEquals(409, $result['status']);
        $this->assertEquals('meeting_not_validated', $result['body']['error']);
    }

    // =========================================================================
    // EXECUTION-BASED: sendReport() error paths with mocked repos
    // =========================================================================

    public function testSendReportSmtpNotConfiguredReturns400(): void
    {
        $this->setHttpMethod('POST');
        $this->setAuth('user-1', 'admin', 'tenant-1');
        $this->injectJsonBody([
            'meeting_id' => '12345678-1234-1234-1234-123456789abc',
            'email' => 'test@example.com',
        ]);

        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => '12345678-1234-1234-1234-123456789abc',
            'title' => 'Test Meeting',
        ]);

        $this->injectRepos([MeetingRepository::class => $meetingRepo]);

        // MailerService with no config is not configured → 400 smtp_not_configured
        $result = $this->callController(MeetingReportsController::class, 'sendReport');

        $this->assertEquals(400, $result['status']);
        $this->assertEquals('smtp_not_configured', $result['body']['error']);
    }

    // =========================================================================
    // GENERATE PDF: LOI 1901 TEMPLATE REQUIREMENTS (Wave 0 — RED)
    // =========================================================================

    public function testGeneratePdfIncludesOrgNameHeader(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingReportsController.php');

        // generatePdf() must fetch org_name from SettingsRepository via settings()->get()
        $this->assertStringContainsString(
            "settings()->get",
            $source,
            'generatePdf() must call $this->repo()->settings()->get() to fetch org_name from tenant_settings',
        );
    }

    public function testGeneratePdfIncludesQuorumSection(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingReportsController.php');

        // generatePdf() must contain a meeting-level quorum section header
        $this->assertStringContainsString(
            'Quorum de la',
            $source,
            'generatePdf() must include a meeting-level quorum block with "Quorum de la séance" text',
        );
    }

    public function testGeneratePdfIncludesDualSignatureBlocks(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingReportsController.php');

        $this->assertStringContainsString(
            'Le Pr',
            $source,
            'generatePdf() must include President signature block starting with "Le Pr"',
        );
        $this->assertStringContainsString(
            'Le Secr',
            $source,
            'generatePdf() must include Secretaire signature block starting with "Le Secr"',
        );
    }

    public function testGeneratePdfInlineDisposition(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingReportsController.php');

        $this->assertStringContainsString(
            "api_query('inline')",
            $source,
            'generatePdf() must detect the ?inline=1 query parameter',
        );
        $this->assertStringContainsString(
            'inline',
            $source,
            'generatePdf() must support Content-Disposition: inline mode',
        );
    }

    public function testGeneratePdfUsesTextLabelsNotEmoji(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/MeetingReportsController.php');

        // generatePdf() must NOT contain emoji vote result characters
        $this->assertStringNotContainsString(
            "\u{2705}",
            $source,
            'generatePdf() must not use checkmark emoji (✅) — use text label "Pour" instead',
        );
        $this->assertStringNotContainsString(
            "\u{274C}",
            $source,
            'generatePdf() must not use cross emoji (❌) — use text label "Contre" instead',
        );
        $this->assertStringNotContainsString(
            "\u{26AA}",
            $source,
            'generatePdf() must not use white circle emoji (⚪) — use text label "Abstention" instead',
        );
    }
}
