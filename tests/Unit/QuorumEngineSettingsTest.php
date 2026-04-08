<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Repository\SettingsRepository;
use AgVote\Service\QuorumEngine;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Proves that settQuorumThreshold in tenant_settings flows into QuorumEngine calculations.
 *
 * Each test changes the setting value and asserts the calculation changes accordingly.
 * DB → calc flow is verified without a real database connection.
 */
class QuorumEngineSettingsTest extends TestCase {
    private const TENANT_ID = 'tenant-1';
    private const MEETING_ID = 'meeting-1';

    private MotionRepository&MockObject $motionRepo;
    private PolicyRepository&MockObject $policyRepo;
    private AttendanceRepository&MockObject $attendanceRepo;
    private MemberRepository&MockObject $memberRepo;
    private MeetingRepository&MockObject $meetingRepo;
    private SettingsRepository&MockObject $settingsRepo;

    protected function setUp(): void {
        $this->motionRepo = $this->createMock(MotionRepository::class);
        $this->policyRepo = $this->createMock(PolicyRepository::class);
        $this->attendanceRepo = $this->createMock(AttendanceRepository::class);
        $this->memberRepo = $this->createMock(MemberRepository::class);
        $this->meetingRepo = $this->createMock(MeetingRepository::class);
        $this->settingsRepo = $this->createMock(SettingsRepository::class);

        // Attendance: 6 present members out of 10 eligible (60% by members, 60% by weight)
        $this->attendanceRepo->method('countPresentMembers')->willReturn(6);
        $this->attendanceRepo->method('sumPresentWeight')->willReturn(6.0);
        $this->memberRepo->method('countActive')->willReturn(10);
        $this->memberRepo->method('sumActiveWeight')->willReturn(10.0);

        // Default: no explicit quorum_policy_id on the meeting
        $this->meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING_ID,
            'tenant_id' => self::TENANT_ID,
            'quorum_policy_id' => null,
            'convocation_no' => 1,
        ]);

        // Default policyRepo returns null (no explicit policy)
        $this->policyRepo->method('findQuorumPolicy')->willReturn(null);
    }

    private function makeEngine(): QuorumEngine {
        return new QuorumEngine(
            $this->motionRepo,
            $this->policyRepo,
            $this->attendanceRepo,
            $this->memberRepo,
            $this->meetingRepo,
            $this->settingsRepo,
        );
    }

    /**
     * Test 1: settQuorumThreshold = '60' → threshold 0.60, quorum met (6/10 = 0.6 >= 0.6).
     */
    public function testSettQuorumThreshold60AppliedAndMet(): void {
        $this->settingsRepo->method('get')
            ->with(self::TENANT_ID, 'settQuorumThreshold')
            ->willReturn('60');

        $engine = $this->makeEngine();
        $result = $engine->computeForMeeting(self::MEETING_ID, self::TENANT_ID);

        $this->assertTrue($result['applied'], 'Quorum should be applied when settQuorumThreshold is set');
        $this->assertArrayHasKey('primary', $result['details']);
        $this->assertEqualsWithDelta(0.60, $result['details']['primary']['threshold'], 0.001, 'Threshold should be 0.60');
        $this->assertTrue($result['details']['primary']['met'], '6/10 = 0.60 should meet threshold 0.60');
    }

    /**
     * Test 2: settQuorumThreshold = '75' → threshold 0.75, quorum NOT met (6/10 = 0.6 < 0.75).
     */
    public function testSettQuorumThreshold75NotMet(): void {
        $this->settingsRepo->method('get')
            ->with(self::TENANT_ID, 'settQuorumThreshold')
            ->willReturn('75');

        $engine = $this->makeEngine();
        $result = $engine->computeForMeeting(self::MEETING_ID, self::TENANT_ID);

        $this->assertTrue($result['applied'], 'Quorum should be applied when settQuorumThreshold is set');
        $this->assertEqualsWithDelta(0.75, $result['details']['primary']['threshold'], 0.001, 'Threshold should be 0.75');
        $this->assertFalse($result['details']['primary']['met'], '6/10 = 0.60 should not meet threshold 0.75');
    }

    /**
     * Test 3: No setting AND no policy → legacy behavior (applied=false, noPolicy()).
     */
    public function testNoSettingNoPolicyReturnsNoPolicy(): void {
        $this->settingsRepo->method('get')
            ->with(self::TENANT_ID, 'settQuorumThreshold')
            ->willReturn(null);

        $engine = $this->makeEngine();
        $result = $engine->computeForMeeting(self::MEETING_ID, self::TENANT_ID);

        $this->assertFalse($result['applied'], 'No quorum should be applied when no setting and no policy');
        $this->assertNull($result['met']);
        $this->assertEmpty($result['details']);
    }

    /**
     * Test 4: Explicit meeting-level quorum_policy_id is set → policy wins, setting is IGNORED.
     */
    public function testExplicitPolicyWinsOverSetting(): void {
        // Meeting has an explicit quorum_policy_id
        $this->meetingRepo = $this->createMock(MeetingRepository::class);
        $this->meetingRepo->method('findByIdForTenant')->willReturn([
            'id' => self::MEETING_ID,
            'tenant_id' => self::TENANT_ID,
            'quorum_policy_id' => 'policy-explicit-001',
            'convocation_no' => 1,
        ]);

        // Explicit policy returns threshold of 0.33
        $this->policyRepo = $this->createMock(PolicyRepository::class);
        $this->policyRepo->method('findQuorumPolicy')->willReturn([
            'id' => 'policy-explicit-001',
            'name' => 'Explicit Policy',
            'mode' => 'single',
            'denominator' => 'eligible_members',
            'threshold' => 0.33,
            'threshold_call2' => null,
            'denominator2' => null,
            'threshold2' => null,
            'include_proxies' => true,
            'count_remote' => true,
        ]);

        // Setting says 75% — should be ignored
        $this->settingsRepo->method('get')
            ->with(self::TENANT_ID, 'settQuorumThreshold')
            ->willReturn('75');

        $engine = new QuorumEngine(
            $this->motionRepo,
            $this->policyRepo,
            $this->attendanceRepo,
            $this->memberRepo,
            $this->meetingRepo,
            $this->settingsRepo,
        );

        $result = $engine->computeForMeeting(self::MEETING_ID, self::TENANT_ID);

        $this->assertTrue($result['applied'], 'Quorum should be applied via explicit policy');
        // Threshold should come from the explicit policy (0.33), NOT from the setting (0.75)
        $this->assertEqualsWithDelta(0.33, $result['details']['primary']['threshold'], 0.001, 'Threshold should be from explicit policy, not setting');
        $this->assertArrayHasKey('policy', $result);
        $this->assertEquals('policy-explicit-001', $result['policy']['id']);
    }
}
