<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\ManualActionRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Service\MeetingReportService;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for MeetingReportService.
 *
 * Tests HTML report generation using mocked repositories.
 * No database, no HTTP context needed.
 */
class MeetingReportServiceTest extends TestCase
{
    private const MEETING_ID = 'meeting-0001';
    private const TENANT_ID = 'tenant-0001';

    /** @var MeetingRepository&MockObject */
    private $meetingRepo;
    /** @var MotionRepository&MockObject */
    private $motionRepo;
    /** @var AttendanceRepository&MockObject */
    private $attendanceRepo;
    /** @var ManualActionRepository&MockObject */
    private $manualActionRepo;
    /** @var PolicyRepository&MockObject */
    private $policyRepo;

    private MeetingReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->meetingRepo = $this->createMock(MeetingRepository::class);
        $this->motionRepo = $this->createMock(MotionRepository::class);
        $this->attendanceRepo = $this->createMock(AttendanceRepository::class);
        $this->manualActionRepo = $this->createMock(ManualActionRepository::class);
        $this->policyRepo = $this->createMock(PolicyRepository::class);

        $this->service = new MeetingReportService(
            $this->meetingRepo,
            $this->motionRepo,
            $this->attendanceRepo,
            $this->manualActionRepo,
            $this->policyRepo,
        );
    }

    // =========================================================================
    // renderHtml() — validation errors
    // =========================================================================

    public function testRenderHtmlThrowsOnEmptyMeetingId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('meeting_id');

        $this->service->renderHtml('', false, self::TENANT_ID);
    }

    public function testRenderHtmlThrowsOnWhitespaceOnlyMeetingId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->renderHtml('   ', false, self::TENANT_ID);
    }

    public function testRenderHtmlThrowsWhenMeetingNotFound(): void
    {
        $this->meetingRepo->method('findByIdForTenant')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('meeting_not_found');

        $this->service->renderHtml(self::MEETING_ID, false, self::TENANT_ID);
    }

    // =========================================================================
    // renderHtml() — basic HTML output
    // =========================================================================

    public function testRenderHtmlReturnsHtmlDocument(): void
    {
        $this->meetingRepo->method('findByIdForTenant')->willReturn($this->buildMeeting());
        $this->motionRepo->method('listForReport')->willReturn([]);
        $this->attendanceRepo->method('listForReport')->willReturn([]);
        $this->manualActionRepo->method('listForMeeting')->willReturn([]);

        $html = $this->service->renderHtml(self::MEETING_ID, false, self::TENANT_ID);

        $this->assertStringContainsString('<!doctype html>', $html);
        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('</html>', $html);
    }

    public function testRenderHtmlContainsMeetingTitle(): void
    {
        $meeting = $this->buildMeeting(['title' => 'AG Extraordinaire 2024']);
        $this->meetingRepo->method('findByIdForTenant')->willReturn($meeting);
        $this->motionRepo->method('listForReport')->willReturn([]);
        $this->attendanceRepo->method('listForReport')->willReturn([]);
        $this->manualActionRepo->method('listForMeeting')->willReturn([]);

        $html = $this->service->renderHtml(self::MEETING_ID, false, self::TENANT_ID);

        $this->assertStringContainsString('AG Extraordinaire 2024', $html);
    }

    public function testRenderHtmlEscapesHtmlEntities(): void
    {
        $meeting = $this->buildMeeting(['title' => '<script>alert("xss")</script>']);
        $this->meetingRepo->method('findByIdForTenant')->willReturn($meeting);
        $this->motionRepo->method('listForReport')->willReturn([]);
        $this->attendanceRepo->method('listForReport')->willReturn([]);
        $this->manualActionRepo->method('listForMeeting')->willReturn([]);

        $html = $this->service->renderHtml(self::MEETING_ID, false, self::TENANT_ID);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRenderHtmlIncludesPresidentName(): void
    {
        $meeting = $this->buildMeeting(['president_name' => 'Jean Dupont']);
        $this->meetingRepo->method('findByIdForTenant')->willReturn($meeting);
        $this->motionRepo->method('listForReport')->willReturn([]);
        $this->attendanceRepo->method('listForReport')->willReturn([]);
        $this->manualActionRepo->method('listForMeeting')->willReturn([]);

        $html = $this->service->renderHtml(self::MEETING_ID, false, self::TENANT_ID);

        $this->assertStringContainsString('Jean Dupont', $html);
    }

    // =========================================================================
    // renderHtml() — attendance counts
    // =========================================================================

    public function testRenderHtmlCountsAttendanceModes(): void
    {
        $this->meetingRepo->method('findByIdForTenant')->willReturn($this->buildMeeting());
        $this->motionRepo->method('listForReport')->willReturn([]);
        $this->attendanceRepo->method('listForReport')->willReturn([
            ['mode' => 'present', 'checked_out_at' => null, 'full_name' => 'Alice', 'voting_power' => 1],
            ['mode' => 'remote', 'checked_out_at' => null, 'full_name' => 'Bob', 'voting_power' => 1],
            ['mode' => 'proxy', 'checked_out_at' => null, 'full_name' => 'Carol', 'voting_power' => 1],
            ['mode' => 'present', 'checked_out_at' => '2024-01-15', 'full_name' => 'Dave', 'voting_power' => 1], // checked out = absent
        ]);
        $this->manualActionRepo->method('listForMeeting')->willReturn([]);

        $html = $this->service->renderHtml(self::MEETING_ID, false, self::TENANT_ID);

        // The HTML contains attendance counts
        $this->assertStringContainsString('Présents', $html);
        $this->assertStringContainsString('Distants', $html);
        $this->assertStringContainsString('Représentés', $html);
    }

    // =========================================================================
    // renderHtml() — motions section
    // =========================================================================

    public function testRenderHtmlIncludesMotionWithOfficialData(): void
    {
        $this->meetingRepo->method('findByIdForTenant')->willReturn($this->buildMeeting());
        $this->motionRepo->method('listForReport')->willReturn([
            [
                'id' => 'motion-001',
                'title' => 'Budget 2024',
                'official_source' => 'manual',
                'official_total' => 100,
                'official_for' => 60,
                'official_against' => 30,
                'official_abstain' => 10,
                'decision' => 'adopted',
                'decision_reason' => 'Majorité simple',
                'closed_at' => '2024-01-15',
                'vote_policy_id' => null,
                'quorum_policy_id' => null,
            ],
        ]);
        $this->attendanceRepo->method('listForReport')->willReturn([]);
        $this->manualActionRepo->method('listForMeeting')->willReturn([]);

        $html = $this->service->renderHtml(self::MEETING_ID, false, self::TENANT_ID);

        $this->assertStringContainsString('Budget 2024', $html);
        $this->assertStringContainsString('Adoptée', $html);
    }

    public function testRenderHtmlIncludesMotionWithNoOfficialSource(): void
    {
        $this->meetingRepo->method('findByIdForTenant')->willReturn($this->buildMeeting());
        $this->motionRepo->method('listForReport')->willReturn([
            [
                'id' => 'motion-001',
                'title' => 'Résolution ouverte',
                'official_source' => '',
                'official_total' => null,
                'official_for' => 0,
                'official_against' => 0,
                'official_abstain' => 0,
                'decision' => 'pending',
                'decision_reason' => '',
                'closed_at' => null, // Not closed yet — skip OfficialResultsService call
                'vote_policy_id' => null,
                'quorum_policy_id' => null,
            ],
        ]);
        $this->attendanceRepo->method('listForReport')->willReturn([]);
        $this->manualActionRepo->method('listForMeeting')->willReturn([]);

        $html = $this->service->renderHtml(self::MEETING_ID, false, self::TENANT_ID);

        $this->assertStringContainsString('Résolution ouverte', $html);
    }

    // =========================================================================
    // renderHtml() — showVoters section
    // =========================================================================

    public function testRenderHtmlShowsVotersWhenEnabled(): void
    {
        $this->meetingRepo->method('findByIdForTenant')->willReturn($this->buildMeeting());
        $this->motionRepo->method('listForReport')->willReturn([]);
        $this->attendanceRepo->method('listForReport')->willReturn([
            ['mode' => 'present', 'checked_out_at' => null, 'full_name' => 'Alice Voter', 'voting_power' => 2],
        ]);
        $this->manualActionRepo->method('listForMeeting')->willReturn([]);

        $html = $this->service->renderHtml(self::MEETING_ID, true, self::TENANT_ID);

        $this->assertStringContainsString('Alice Voter', $html);
        $this->assertStringContainsString('Présences', $html);
    }

    public function testRenderHtmlHidesVotersSectionWhenDisabled(): void
    {
        $this->meetingRepo->method('findByIdForTenant')->willReturn($this->buildMeeting());
        $this->motionRepo->method('listForReport')->willReturn([]);
        $this->attendanceRepo->method('listForReport')->willReturn([
            ['mode' => 'present', 'checked_out_at' => null, 'full_name' => 'Alice Voter', 'voting_power' => 2],
        ]);
        $this->manualActionRepo->method('listForMeeting')->willReturn([]);

        $html = $this->service->renderHtml(self::MEETING_ID, false, self::TENANT_ID);

        // showVoters=false: no voters annex, but Alice's name should not appear in voter list
        // The main report may still not show voter names
        $this->assertStringNotContainsString('Annexe – Présences', $html);
    }

    // =========================================================================
    // renderHtml() — manual actions annex
    // =========================================================================

    public function testRenderHtmlIncludesManualActionsAnnex(): void
    {
        $this->meetingRepo->method('findByIdForTenant')->willReturn($this->buildMeeting());
        $this->motionRepo->method('listForReport')->willReturn([]);
        $this->attendanceRepo->method('listForReport')->willReturn([]);
        $this->manualActionRepo->method('listForMeeting')->willReturn([
            [
                'created_at' => '2024-01-15 10:00:00',
                'action_type' => 'manual_result',
                'value' => 'for:60',
                'justification' => 'Vote à mains levées',
            ],
        ]);

        $html = $this->service->renderHtml(self::MEETING_ID, false, self::TENANT_ID);

        $this->assertStringContainsString('Actions manuelles', $html);
        $this->assertStringContainsString('Vote à mains levées', $html);
    }

    public function testRenderHtmlOmitsManualActionsAnnexWhenEmpty(): void
    {
        $this->meetingRepo->method('findByIdForTenant')->willReturn($this->buildMeeting());
        $this->motionRepo->method('listForReport')->willReturn([]);
        $this->attendanceRepo->method('listForReport')->willReturn([]);
        $this->manualActionRepo->method('listForMeeting')->willReturn([]);

        $html = $this->service->renderHtml(self::MEETING_ID, false, self::TENANT_ID);

        $this->assertStringNotContainsString('Actions manuelles', $html);
    }

    public function testRenderHtmlHandlesManualActionsRepoException(): void
    {
        $this->meetingRepo->method('findByIdForTenant')->willReturn($this->buildMeeting());
        $this->motionRepo->method('listForReport')->willReturn([]);
        $this->attendanceRepo->method('listForReport')->willReturn([]);
        $this->manualActionRepo->method('listForMeeting')
            ->willThrowException(new \RuntimeException('repo error'));

        // Should not throw — gracefully returns empty manual actions
        $html = $this->service->renderHtml(self::MEETING_ID, false, self::TENANT_ID);

        $this->assertStringContainsString('<!doctype html>', $html);
        $this->assertStringNotContainsString('Actions manuelles', $html);
    }

    // =========================================================================
    // renderHtml() — status labels
    // =========================================================================

    /**
     * @dataProvider provideMeetingStatuses
     */
    public function testRenderHtmlTranslatesAllStatusLabels(string $status, string $expected): void
    {
        $meeting = $this->buildMeeting(['status' => $status]);
        $this->meetingRepo->method('findByIdForTenant')->willReturn($meeting);
        $this->motionRepo->method('listForReport')->willReturn([]);
        $this->attendanceRepo->method('listForReport')->willReturn([]);
        $this->manualActionRepo->method('listForMeeting')->willReturn([]);

        $html = $this->service->renderHtml(self::MEETING_ID, false, self::TENANT_ID);

        $this->assertStringContainsString($expected, $html);
    }

    public static function provideMeetingStatuses(): array
    {
        return [
            ['draft', 'Brouillon'],
            ['scheduled', 'Programmée'],
            ['frozen', 'Figée'],
            ['live', 'En cours'],
            ['closed', 'Clôturée'],
            ['validated', 'Validée'],
            ['archived', 'Archivée'],
        ];
    }

    // =========================================================================
    // renderHtml() — decision labels
    // =========================================================================

    /**
     * @dataProvider provideDecisions
     */
    public function testRenderHtmlTranslatesAllDecisionLabels(string $decision, string $expected): void
    {
        $this->meetingRepo->method('findByIdForTenant')->willReturn($this->buildMeeting());
        $this->motionRepo->method('listForReport')->willReturn([
            $this->buildMotion(['decision' => $decision, 'official_source' => 'manual', 'official_total' => 10]),
        ]);
        $this->attendanceRepo->method('listForReport')->willReturn([]);
        $this->manualActionRepo->method('listForMeeting')->willReturn([]);

        $html = $this->service->renderHtml(self::MEETING_ID, false, self::TENANT_ID);

        $this->assertStringContainsString($expected, $html);
    }

    public static function provideDecisions(): array
    {
        return [
            ['adopted', 'Adoptée'],
            ['rejected', 'Rejetée'],
            ['cancelled', 'Annulée'],
            ['pending', 'En attente'],
        ];
    }

    // =========================================================================
    // renderHtml() — mode labels (attendance)
    // =========================================================================

    public function testRenderHtmlTranslatesAllModeLabelsinVotersSection(): void
    {
        $this->meetingRepo->method('findByIdForTenant')->willReturn($this->buildMeeting());
        $this->motionRepo->method('listForReport')->willReturn([]);
        $this->attendanceRepo->method('listForReport')->willReturn([
            ['mode' => 'present', 'checked_out_at' => null, 'full_name' => 'Alice', 'voting_power' => 1],
            ['mode' => 'remote', 'checked_out_at' => null, 'full_name' => 'Bob', 'voting_power' => 1],
            ['mode' => 'proxy', 'checked_out_at' => null, 'full_name' => 'Carol', 'voting_power' => 1],
            ['mode' => 'excused', 'checked_out_at' => null, 'full_name' => 'Dave', 'voting_power' => 1],
            ['mode' => '', 'checked_out_at' => null, 'full_name' => 'Eve', 'voting_power' => 1],
        ]);
        $this->manualActionRepo->method('listForMeeting')->willReturn([]);

        $html = $this->service->renderHtml(self::MEETING_ID, true, self::TENANT_ID);

        $this->assertStringContainsString('Présent', $html);
        $this->assertStringContainsString('À distance', $html);
        $this->assertStringContainsString('Représenté', $html);
        $this->assertStringContainsString('Excusé', $html);
        $this->assertStringContainsString('Absent', $html);
    }

    // =========================================================================
    // renderHtml() — motion with vote policy lookup (lines 171, 175)
    // =========================================================================

    public function testRenderHtmlFetchesVotePolicyForMotion(): void
    {
        $this->meetingRepo->method('findByIdForTenant')->willReturn($this->buildMeeting());
        $this->motionRepo->method('listForReport')->willReturn([
            $this->buildMotion([
                'vote_policy_id' => 'vp-001',  // Non-null → triggers policyRepo->findVotePolicy
                'quorum_policy_id' => null,
                'official_source' => 'manual',
                'official_total' => 100,
            ]),
        ]);
        $this->attendanceRepo->method('listForReport')->willReturn([]);
        $this->manualActionRepo->method('listForMeeting')->willReturn([]);

        // findVotePolicy called once for the motion
        $this->policyRepo->expects($this->once())
            ->method('findVotePolicy')
            ->with('vp-001', self::TENANT_ID)
            ->willReturn([
                'base' => 'expressed',
                'threshold' => 0.5,
                'abstention_as_against' => false,
            ]);

        $html = $this->service->renderHtml(self::MEETING_ID, false, self::TENANT_ID);

        $this->assertStringContainsString('Test Motion', $html);
        $this->assertStringContainsString('Majorité:', $html);
    }

    public function testRenderHtmlFetchesQuorumPolicyForMotion(): void
    {
        $this->meetingRepo->method('findByIdForTenant')->willReturn($this->buildMeeting());
        $this->motionRepo->method('listForReport')->willReturn([
            $this->buildMotion([
                'vote_policy_id' => null,
                'quorum_policy_id' => 'qp-001', // Non-null → triggers policyRepo->findQuorumPolicy
                'official_source' => 'manual',
                'official_total' => 100,
            ]),
        ]);
        $this->attendanceRepo->method('listForReport')->willReturn([]);
        $this->manualActionRepo->method('listForMeeting')->willReturn([]);

        $this->policyRepo->expects($this->once())
            ->method('findQuorumPolicy')
            ->with('qp-001', self::TENANT_ID)
            ->willReturn([
                'denominator' => 'eligible_members',
                'threshold' => 0.5,
            ]);

        $html = $this->service->renderHtml(self::MEETING_ID, false, self::TENANT_ID);

        $this->assertStringContainsString('Test Motion', $html);
        $this->assertStringContainsString('Quorum:', $html);
    }

    public function testRenderHtmlPolicyLineWithVotePolicyAbstentionAsAgainst(): void
    {
        $this->meetingRepo->method('findByIdForTenant')->willReturn($this->buildMeeting());
        $this->motionRepo->method('listForReport')->willReturn([
            $this->buildMotion([
                'vote_policy_id' => 'vp-001',
                'official_source' => 'manual',
                'official_total' => 100,
            ]),
        ]);
        $this->attendanceRepo->method('listForReport')->willReturn([]);
        $this->manualActionRepo->method('listForMeeting')->willReturn([]);

        $this->policyRepo->method('findVotePolicy')->willReturn([
            'base' => 'expressed',
            'threshold' => 0.5,
            'abstention_as_against' => true, // triggers '(abst→contre)' label
        ]);

        $html = $this->service->renderHtml(self::MEETING_ID, false, self::TENANT_ID);

        $this->assertStringContainsString('abst', $html);
    }

    // =========================================================================
    // renderHtml() — additional decision labels (no_quorum, no_votes, no_policy)
    // =========================================================================

    public function testRenderHtmlTranslatesNoQuorumDecision(): void
    {
        $this->meetingRepo->method('findByIdForTenant')->willReturn($this->buildMeeting());
        $this->motionRepo->method('listForReport')->willReturn([
            $this->buildMotion(['decision' => 'no_quorum', 'official_source' => 'manual', 'official_total' => 10]),
        ]);
        $this->attendanceRepo->method('listForReport')->willReturn([]);
        $this->manualActionRepo->method('listForMeeting')->willReturn([]);

        $html = $this->service->renderHtml(self::MEETING_ID, false, self::TENANT_ID);

        $this->assertStringContainsString('Sans quorum', $html);
    }

    public function testRenderHtmlTranslatesNoVotesDecision(): void
    {
        $this->meetingRepo->method('findByIdForTenant')->willReturn($this->buildMeeting());
        $this->motionRepo->method('listForReport')->willReturn([
            $this->buildMotion(['decision' => 'no_votes', 'official_source' => 'manual', 'official_total' => 10]),
        ]);
        $this->attendanceRepo->method('listForReport')->willReturn([]);
        $this->manualActionRepo->method('listForMeeting')->willReturn([]);

        $html = $this->service->renderHtml(self::MEETING_ID, false, self::TENANT_ID);

        $this->assertStringContainsString('Sans votes', $html);
    }

    public function testRenderHtmlTranslatesNoPolicyDecision(): void
    {
        $this->meetingRepo->method('findByIdForTenant')->willReturn($this->buildMeeting());
        $this->motionRepo->method('listForReport')->willReturn([
            $this->buildMotion(['decision' => 'no_policy', 'official_source' => 'manual', 'official_total' => 10]),
        ]);
        $this->attendanceRepo->method('listForReport')->willReturn([]);
        $this->manualActionRepo->method('listForMeeting')->willReturn([]);

        $html = $this->service->renderHtml(self::MEETING_ID, false, self::TENANT_ID);

        $this->assertStringContainsString('Sans règle', $html);
    }

    // =========================================================================
    // policyLine() and majorityLine() — tested via Reflection
    // =========================================================================

    public function testPolicyLineWithNoQuorumNoVotePolicy(): void
    {
        $ref = new \ReflectionClass(MeetingReportService::class);
        $method = $ref->getMethod('policyLine');
        $method->setAccessible(true);

        $result = $method->invoke(null, null, null);

        $this->assertStringContainsString('Quorum: —', $result);
        $this->assertStringContainsString('Majorité: —', $result);
    }

    public function testPolicyLineWithBothPolicies(): void
    {
        $ref = new \ReflectionClass(MeetingReportService::class);
        $method = $ref->getMethod('policyLine');
        $method->setAccessible(true);

        $votePolicy = ['base' => 'expressed', 'threshold' => 0.5, 'abstention_as_against' => false];
        $quorumPolicy = ['denominator' => 'eligible_members', 'threshold' => 0.3];

        $result = $method->invoke(null, $votePolicy, $quorumPolicy);

        $this->assertStringContainsString('Quorum:', $result);
        $this->assertStringContainsString('Majorité:', $result);
        $this->assertStringContainsString('eligible_members', $result);
        $this->assertStringContainsString('expressed', $result);
    }

    public function testMajorityLineWithAllData(): void
    {
        $ref = new \ReflectionClass(MeetingReportService::class);
        $method = $ref->getMethod('majorityLine');
        $method->setAccessible(true);

        $maj = ['base' => 'expressed', 'ratio' => 0.6, 'threshold' => 0.5];

        $result = $method->invoke(null, $maj);

        $this->assertNotNull($result);
        $this->assertStringContainsString('expressed', $result);
        $this->assertStringContainsString('0', $result); // ratio formatted
    }

    public function testMajorityLineWithMissingData(): void
    {
        $ref = new \ReflectionClass(MeetingReportService::class);
        $method = $ref->getMethod('majorityLine');
        $method->setAccessible(true);

        // Missing threshold → returns null
        $maj = ['base' => 'expressed', 'ratio' => 0.6];
        $result = $method->invoke(null, $maj);

        $this->assertNull($result);
    }

    public function testFmtDecimalValues(): void
    {
        $ref = new \ReflectionClass(MeetingReportService::class);
        $method = $ref->getMethod('fmt');
        $method->setAccessible(true);

        // Integer (rounds to no decimal)
        $this->assertSame('5', $method->invoke(null, 5.0));
        // Decimal (with trailing zero stripping)
        $decimal = $method->invoke(null, 5.5);
        $this->assertStringContainsString('5', $decimal);
        $this->assertStringContainsString('.', $decimal);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function buildMeeting(array $overrides = []): array
    {
        return array_merge([
            'id' => self::MEETING_ID,
            'tenant_id' => self::TENANT_ID,
            'title' => 'Test Meeting',
            'status' => 'validated',
            'president_name' => 'Marie Dupont',
            'validated_at' => '2024-01-15T10:00:00Z',
        ], $overrides);
    }

    private function buildMotion(array $overrides = []): array
    {
        return array_merge([
            'id' => 'motion-001',
            'title' => 'Test Motion',
            'official_source' => 'manual',
            'official_total' => 100,
            'official_for' => 60,
            'official_against' => 30,
            'official_abstain' => 10,
            'decision' => 'adopted',
            'decision_reason' => '',
            'closed_at' => '2024-01-15T10:00:00Z',
            'vote_policy_id' => null,
            'quorum_policy_id' => null,
        ], $overrides);
    }
}
