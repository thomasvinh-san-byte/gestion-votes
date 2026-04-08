<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\InvitationRepository;
use AgVote\Repository\MeetingReportRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Repository\ProxyRepository;
use AgVote\Repository\SettingsRepository;
use AgVote\Service\MeetingReportsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Service-level unit tests for MeetingReportsService.
 *
 * Verifies: HTML assembly with motion titles, snapshot short-circuit,
 * regen bypass of snapshot, and PDF magic bytes output.
 *
 * All repository dependencies are mocked — no database, no HTTP context.
 */
class MeetingReportsServiceTest extends TestCase
{
    private const MEETING_ID = '12345678-0000-0000-0000-000000000001';
    private const TENANT_ID = 'tenant-0001';

    /** @var MeetingRepository&MockObject */
    private $meetingRepo;
    /** @var MotionRepository&MockObject */
    private $motionRepo;
    /** @var AttendanceRepository&MockObject */
    private $attendanceRepo;
    /** @var BallotRepository&MockObject */
    private $ballotRepo;
    /** @var PolicyRepository&MockObject */
    private $policyRepo;
    /** @var ProxyRepository&MockObject */
    private $proxyRepo;
    /** @var InvitationRepository&MockObject */
    private $invitationRepo;
    /** @var MeetingReportRepository&MockObject */
    private $meetingReportRepo;
    /** @var SettingsRepository&MockObject */
    private $settingsRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->meetingRepo = $this->createMock(MeetingRepository::class);
        $this->motionRepo = $this->createMock(MotionRepository::class);
        $this->attendanceRepo = $this->createMock(AttendanceRepository::class);
        $this->ballotRepo = $this->createMock(BallotRepository::class);
        $this->policyRepo = $this->createMock(PolicyRepository::class);
        $this->proxyRepo = $this->createMock(ProxyRepository::class);
        $this->invitationRepo = $this->createMock(InvitationRepository::class);
        $this->meetingReportRepo = $this->createMock(MeetingReportRepository::class);
        $this->settingsRepo = $this->createMock(SettingsRepository::class);
    }

    // =========================================================================
    // HELPER: build a service instance with all mocks injected
    // =========================================================================

    private function buildService(): MeetingReportsService
    {
        return new MeetingReportsService(
            $this->meetingRepo,
            $this->motionRepo,
            $this->attendanceRepo,
            $this->ballotRepo,
            $this->policyRepo,
            $this->proxyRepo,
            $this->invitationRepo,
            $this->meetingReportRepo,
            $this->settingsRepo,
        );
    }

    // =========================================================================
    // Test 1: buildReportHtml returns HTML containing both motion titles
    // =========================================================================

    public function testBuildReportHtmlReturnsHtmlWithMotionTitles(): void
    {
        // No cached snapshot
        $this->meetingReportRepo->method('findSnapshot')->willReturn(null);

        // Two motions with distinct titles
        $this->motionRepo->method('listForReport')->willReturn([
            $this->buildMotion(['id' => 'mo-001', 'title' => 'Motion A', 'decision' => 'adopted']),
            $this->buildMotion(['id' => 'mo-002', 'title' => 'Motion B', 'decision' => 'rejected']),
        ]);

        $this->attendanceRepo->method('listForReport')->willReturn([
            $this->buildAttendance(['full_name' => 'Alice', 'mode' => 'present']),
            $this->buildAttendance(['full_name' => 'Bob', 'mode' => 'remote']),
            $this->buildAttendance(['full_name' => 'Carol', 'mode' => 'proxy']),
        ]);

        $this->proxyRepo->method('listForReport')->willReturn([
            ['giver_name' => 'Dave', 'receiver_name' => 'Carol', 'revoked_at' => null],
        ]);

        $this->invitationRepo->method('listTokensForReport')->willReturn([]);

        $this->meetingRepo->method('findByIdForTenant')->willReturn(
            $this->buildMeeting(['title' => 'AG 2026'])
        );

        $service = $this->buildService();
        $html = $service->buildReportHtml(self::MEETING_ID, self::TENANT_ID, false, false);

        $this->assertIsString($html);
        $this->assertNotEmpty($html);
        $this->assertStringContainsString('Motion A', $html);
        $this->assertStringContainsString('Motion B', $html);
        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('<!DOCTYPE html', $html);
    }

    // =========================================================================
    // Test 2: cached snapshot short-circuits motion repo call
    // =========================================================================

    public function testBuildReportHtmlServesCachedSnapshotWhenAvailable(): void
    {
        // Snapshot exists and contains HTML
        $this->meetingReportRepo->method('findSnapshot')->willReturn([
            'html' => '<html>CACHED</html>',
        ]);

        // listForReport must NEVER be called — snapshot short-circuits
        $this->motionRepo->expects($this->never())
            ->method('listForReport');

        $service = $this->buildService();
        $html = $service->buildReportHtml(self::MEETING_ID, self::TENANT_ID, false, false);

        $this->assertStringContainsString('CACHED', $html);
    }

    // =========================================================================
    // Test 3: regen=true bypasses snapshot and calls listForReport
    // =========================================================================

    public function testBuildReportHtmlRegenBypassesSnapshot(): void
    {
        // Snapshot exists but regen=true should bypass it
        $this->meetingReportRepo->method('findSnapshot')->willReturn([
            'html' => '<html>CACHED</html>',
        ]);

        // listForReport MUST be called once (regen bypasses cache)
        $this->motionRepo->expects($this->once())
            ->method('listForReport')
            ->willReturn([
                $this->buildMotion(['title' => 'Fresh Motion']),
            ]);

        $this->attendanceRepo->method('listForReport')->willReturn([]);
        $this->proxyRepo->method('listForReport')->willReturn([]);
        $this->invitationRepo->method('listTokensForReport')->willReturn([]);

        $this->meetingRepo->method('findByIdForTenant')->willReturn(
            $this->buildMeeting(['title' => 'AG Regen 2026'])
        );

        $service = $this->buildService();
        $html = $service->buildReportHtml(self::MEETING_ID, self::TENANT_ID, false, true);

        // Returns fresh HTML, not cached
        $this->assertStringNotContainsString('CACHED', $html);
        $this->assertStringContainsString('<!DOCTYPE html', $html);
    }

    // =========================================================================
    // Test 4: buildPdfBytes returns binary starting with %PDF-
    // =========================================================================

    public function testBuildPdfBytesReturnsPdfMagicHeader(): void
    {
        $meeting = $this->buildMeeting([
            'id' => self::MEETING_ID,
            'title' => 'AG PDF 2026',
            'validated_at' => '2026-04-10 10:00:00',
            'scheduled_at' => '2026-04-10 10:00:00',
            'location' => 'Salle A',
        ]);

        $this->settingsRepo->method('get')->willReturn('Association Test');

        $this->attendanceRepo->method('listForReport')->willReturn([
            $this->buildAttendance(['full_name' => 'Alice', 'mode' => 'present']),
            $this->buildAttendance(['full_name' => 'Bob', 'mode' => 'present']),
        ]);

        $this->motionRepo->method('listForReport')->willReturn([
            $this->buildMotion(['title' => 'Résolution 1', 'secret' => false]),
        ]);

        $this->proxyRepo->method('listForReport')->willReturn([]);

        $this->meetingReportRepo->method('upsertFull')->willReturnSelf();

        $service = $this->buildService();
        $result = $service->buildPdfBytes(
            self::MEETING_ID,
            self::TENANT_ID,
            false,   // not preview
            false,   // not inline
            $meeting,
        );

        $this->assertArrayHasKey('pdf', $result);
        $this->assertArrayHasKey('hash', $result);
        $this->assertArrayHasKey('filename', $result);

        $pdf = $result['pdf'];
        $this->assertIsString($pdf);
        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf, 'PDF binary must start with %PDF- magic header');
    }

    // =========================================================================
    // HELPER: fixture builders
    // =========================================================================

    /** @return array<string,mixed> */
    private function buildMeeting(array $overrides = []): array
    {
        return array_merge([
            'id' => self::MEETING_ID,
            'tenant_id' => self::TENANT_ID,
            'title' => 'Test Meeting',
            'status' => 'validated',
            'president_name' => 'Marie Dupont',
            'validated_at' => '2026-04-10T10:00:00Z',
            'created_at' => '2026-01-01T00:00:00Z',
            'archived_at' => null,
            'scheduled_at' => '2026-04-10T10:00:00Z',
            'location' => 'Paris',
        ], $overrides);
    }

    /** @return array<string,mixed> */
    private function buildMotion(array $overrides = []): array
    {
        return array_merge([
            'id' => 'motion-001',
            'title' => 'Test Motion',
            'description' => '',
            'official_source' => 'manual',
            'official_total' => 100,
            'official_for' => 60,
            'official_against' => 30,
            'official_abstain' => 10,
            'decision' => 'adopted',
            'decision_reason' => '',
            'closed_at' => '2026-04-10T10:00:00Z',
            'vote_policy_id' => null,
            'quorum_policy_id' => null,
            'secret' => false,
        ], $overrides);
    }

    /** @return array<string,mixed> */
    private function buildAttendance(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Test Member',
            'mode' => 'present',
            'voting_power' => 1.0,
            'checked_in_at' => '2026-04-10T09:30:00Z',
            'checked_out_at' => null,
        ], $overrides);
    }
}
