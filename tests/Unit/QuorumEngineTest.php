<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Service\QuorumEngine;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for QuorumEngine service.
 *
 * Tests both the mirrored ratio/threshold logic AND the real QuorumEngine
 * methods via mocked repositories (computeForMotion, computeForMeeting).
 * Integration tests with real database are in tests/Integration/.
 */
class QuorumEngineTest extends TestCase {
    // =========================================================================
    // RESULT STRUCTURE TESTS
    // =========================================================================

    /**
     * Validates the expected structure of computeForMeeting output.
     */
    public function testResultStructureKeys(): void {
        $expectedKeys = [
            'applied',
            'met',
            'details',
            'justification',
            'meeting',
        ];

        $result = $this->createMockResult();

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Result should contain '{$key}' key");
        }
    }

    public function testResultWithPolicyIncludesPolicy(): void {
        $result = $this->createMockResultWithPolicy();

        $this->assertArrayHasKey('policy', $result);
        $this->assertArrayHasKey('id', $result['policy']);
        $this->assertArrayHasKey('name', $result['policy']);
        $this->assertArrayHasKey('mode', $result['policy']);
    }

    public function testNoPolicyResult(): void {
        $result = $this->createNoPolicyResult();

        $this->assertFalse($result['applied']);
        $this->assertNull($result['met']);
        $this->assertEmpty($result['details']);
    }

    // =========================================================================
    // QUORUM MODE TESTS
    // =========================================================================

    public function testSingleModeQuorumMet(): void {
        $mode = 'single';
        $primary = $this->computeRatioBlock(
            basis: 'eligible_members',
            threshold: 0.5,
            numMembers: 60,
            numWeight: 0,
            eligibleMembers: 100,
            eligibleWeight: 0,
        );

        $this->assertTrue($primary['met']);
        $this->assertEquals(0.6, $primary['ratio']);
    }

    public function testSingleModeQuorumNotMet(): void {
        $primary = $this->computeRatioBlock(
            basis: 'eligible_members',
            threshold: 0.5,
            numMembers: 40,
            numWeight: 0,
            eligibleMembers: 100,
            eligibleWeight: 0,
        );

        $this->assertFalse($primary['met']);
        $this->assertEquals(0.4, $primary['ratio']);
    }

    public function testDoubleModeQuorumBothMet(): void {
        $primary = $this->computeRatioBlock(
            basis: 'eligible_members',
            threshold: 0.5,
            numMembers: 60,
            numWeight: 600,
            eligibleMembers: 100,
            eligibleWeight: 1000,
        );

        $secondary = $this->computeRatioBlock(
            basis: 'eligible_weight',
            threshold: 0.5,
            numMembers: 60,
            numWeight: 600,
            eligibleMembers: 100,
            eligibleWeight: 1000,
        );

        $doubleQuorumMet = $primary['met'] && $secondary['met'];

        $this->assertTrue($doubleQuorumMet);
    }

    public function testDoubleModeQuorumPrimaryNotMet(): void {
        $primary = $this->computeRatioBlock(
            basis: 'eligible_members',
            threshold: 0.5,
            numMembers: 40,
            numWeight: 600,
            eligibleMembers: 100,
            eligibleWeight: 1000,
        );

        $secondary = $this->computeRatioBlock(
            basis: 'eligible_weight',
            threshold: 0.5,
            numMembers: 40,
            numWeight: 600,
            eligibleMembers: 100,
            eligibleWeight: 1000,
        );

        $doubleQuorumMet = $primary['met'] && $secondary['met'];

        $this->assertFalse($doubleQuorumMet);
        $this->assertFalse($primary['met']);
        $this->assertTrue($secondary['met']);
    }

    public function testDoubleModeQuorumSecondaryNotMet(): void {
        $primary = $this->computeRatioBlock(
            basis: 'eligible_members',
            threshold: 0.5,
            numMembers: 60,
            numWeight: 400,
            eligibleMembers: 100,
            eligibleWeight: 1000,
        );

        $secondary = $this->computeRatioBlock(
            basis: 'eligible_weight',
            threshold: 0.5,
            numMembers: 60,
            numWeight: 400,
            eligibleMembers: 100,
            eligibleWeight: 1000,
        );

        $doubleQuorumMet = $primary['met'] && $secondary['met'];

        $this->assertFalse($doubleQuorumMet);
        $this->assertTrue($primary['met']);
        $this->assertFalse($secondary['met']);
    }

    // =========================================================================
    // EVOLVING THRESHOLD TESTS
    // =========================================================================

    public function testEvolvingThresholdFirstConvocation(): void {
        $threshold = $this->getEvolvingThreshold(
            convocationNo: 1,
            threshold1: 0.5,
            threshold2: 0.25,
        );

        $this->assertEquals(0.5, $threshold);
    }

    public function testEvolvingThresholdSecondConvocation(): void {
        $threshold = $this->getEvolvingThreshold(
            convocationNo: 2,
            threshold1: 0.5,
            threshold2: 0.25,
        );

        $this->assertEquals(0.25, $threshold);
    }

    public function testEvolvingThresholdNoSecondThreshold(): void {
        // Falls back to first threshold if second is null
        $threshold = $this->getEvolvingThreshold(
            convocationNo: 2,
            threshold1: 0.5,
            threshold2: null,
        );

        $this->assertEquals(0.5, $threshold);
    }

    public function testEvolvingThresholdAllowsQuorumOnSecondCall(): void {
        // First call: 40% attendance, 50% threshold = not met
        $threshold1 = $this->getEvolvingThreshold(1, 0.5, 0.25);
        $ratio1 = 0.4;
        $met1 = $ratio1 >= $threshold1;
        $this->assertFalse($met1);

        // Second call: same 40% attendance, 25% threshold = met
        $threshold2 = $this->getEvolvingThreshold(2, 0.5, 0.25);
        $ratio2 = 0.4;
        $met2 = $ratio2 >= $threshold2;
        $this->assertTrue($met2);
    }

    // =========================================================================
    // ATTENDANCE MODE TESTS
    // =========================================================================

    public function testAttendanceModesPresent(): void {
        $modes = ['present'];

        $attendance = [
            ['mode' => 'present'],
            ['mode' => 'present'],
            ['mode' => 'remote'],
            ['mode' => 'proxy'],
        ];

        $count = $this->countByModes($attendance, $modes);
        $this->assertEquals(2, $count);
    }

    public function testAttendanceModesPresentAndRemote(): void {
        $modes = ['present', 'remote'];

        $attendance = [
            ['mode' => 'present'],
            ['mode' => 'present'],
            ['mode' => 'remote'],
            ['mode' => 'proxy'],
        ];

        $count = $this->countByModes($attendance, $modes);
        $this->assertEquals(3, $count);
    }

    public function testAttendanceModesAll(): void {
        $modes = ['present', 'remote', 'proxy'];

        $attendance = [
            ['mode' => 'present'],
            ['mode' => 'present'],
            ['mode' => 'remote'],
            ['mode' => 'proxy'],
        ];

        $count = $this->countByModes($attendance, $modes);
        $this->assertEquals(4, $count);
    }

    public function testAttendanceModesFromPolicy(): void {
        $policy = [
            'include_proxies' => true,
            'count_remote' => true,
        ];

        $modes = $this->getAllowedModes($policy);

        $this->assertContains('present', $modes);
        $this->assertContains('remote', $modes);
        $this->assertContains('proxy', $modes);
    }

    public function testAttendanceModesNoProxies(): void {
        $policy = [
            'include_proxies' => false,
            'count_remote' => true,
        ];

        $modes = $this->getAllowedModes($policy);

        $this->assertContains('present', $modes);
        $this->assertContains('remote', $modes);
        $this->assertNotContains('proxy', $modes);
    }

    public function testAttendanceModesNoRemote(): void {
        $policy = [
            'include_proxies' => true,
            'count_remote' => false,
        ];

        $modes = $this->getAllowedModes($policy);

        $this->assertContains('present', $modes);
        $this->assertNotContains('remote', $modes);
        $this->assertContains('proxy', $modes);
    }

    // =========================================================================
    // BASIS CALCULATION TESTS
    // =========================================================================

    public function testBasisEligibleMembers(): void {
        $result = $this->computeRatioBlock(
            basis: 'eligible_members',
            threshold: 0.5,
            numMembers: 60,
            numWeight: 6000,
            eligibleMembers: 100,
            eligibleWeight: 10000,
        );

        // Should use member count, not weight
        $this->assertEquals(0.6, $result['ratio']);
        $this->assertEquals(60.0, $result['numerator']);
        $this->assertEquals(100, $result['denominator']);
    }

    public function testBasisEligibleWeight(): void {
        $result = $this->computeRatioBlock(
            basis: 'eligible_weight',
            threshold: 0.5,
            numMembers: 60,
            numWeight: 6000,
            eligibleMembers: 100,
            eligibleWeight: 10000,
        );

        // Should use weight, not count
        $this->assertEquals(0.6, $result['ratio']);
        $this->assertEquals(6000.0, $result['numerator']);
        $this->assertEquals(10000.0, $result['denominator']);
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function testZeroEligibleMembers(): void {
        $result = $this->computeRatioBlock(
            basis: 'eligible_members',
            threshold: 0.5,
            numMembers: 0,
            numWeight: 0,
            eligibleMembers: 0,
            eligibleWeight: 0,
        );

        // Zero eligible → denominator 0, quorum fails (guard prevents div-by-zero)
        $this->assertEquals(0.0, $result['ratio']);
        $this->assertEquals(0, $result['denominator']);
        $this->assertFalse($result['met']);
    }

    public function testZeroEligibleWeight(): void {
        $result = $this->computeRatioBlock(
            basis: 'eligible_weight',
            threshold: 0.5,
            numMembers: 0,
            numWeight: 100,
            eligibleMembers: 0,
            eligibleWeight: 0,
        );

        // Should use small positive denominator to avoid division by zero
        $this->assertGreaterThan(0, $result['ratio']);
    }

    public function testExactThreshold(): void {
        $result = $this->computeRatioBlock(
            basis: 'eligible_members',
            threshold: 0.5,
            numMembers: 50,
            numWeight: 0,
            eligibleMembers: 100,
            eligibleWeight: 0,
        );

        // Exactly at threshold should pass (>=)
        $this->assertEquals(0.5, $result['ratio']);
        $this->assertTrue($result['met']);
    }

    public function testJustBelowThreshold(): void {
        $result = $this->computeRatioBlock(
            basis: 'eligible_members',
            threshold: 0.5,
            numMembers: 49,
            numWeight: 0,
            eligibleMembers: 100,
            eligibleWeight: 0,
        );

        $this->assertEquals(0.49, $result['ratio']);
        $this->assertFalse($result['met']);
    }

    // =========================================================================
    // JUSTIFICATION TESTS
    // =========================================================================

    public function testJustificationFormat(): void {
        $justification = $this->generateJustification(
            name: 'Standard Quorum',
            mode: 'single',
            convocationNo: 1,
            modes: ['present', 'remote'],
            ratio: 0.6,
            threshold: 0.5,
            met: true,
        );

        $this->assertStringContainsString('Standard Quorum', $justification);
        $this->assertStringContainsString('convocation 1', $justification);
        $this->assertStringContainsString('0.6', $justification);
        $this->assertStringContainsString('0.5', $justification);
        $this->assertStringContainsString('atteint', $justification);
    }

    public function testJustificationNotMet(): void {
        $justification = $this->generateJustification(
            name: 'Standard Quorum',
            mode: 'single',
            convocationNo: 1,
            modes: ['present'],
            ratio: 0.4,
            threshold: 0.5,
            met: false,
        );

        $this->assertStringContainsString('non atteint', $justification);
    }

    // =========================================================================
    // QuorumEngine::computeForMeeting() — DIRECT SERVICE TESTS (mocked repos)
    // =========================================================================

    /**
     * Build a QuorumEngine with all repos mocked.
     */
    private function buildEngine(array $overrides = []): QuorumEngine {
        $motionRepo     = $overrides['motionRepo']     ?? $this->createMock(MotionRepository::class);
        $policyRepo     = $overrides['policyRepo']     ?? $this->createMock(PolicyRepository::class);
        $attendanceRepo = $overrides['attendanceRepo'] ?? $this->createMock(AttendanceRepository::class);
        $memberRepo     = $overrides['memberRepo']     ?? $this->createMock(MemberRepository::class);
        $meetingRepo    = $overrides['meetingRepo']     ?? $this->createMock(MeetingRepository::class);

        return new QuorumEngine($motionRepo, $policyRepo, $attendanceRepo, $memberRepo, $meetingRepo);
    }

    public function testComputeForMeetingNoPolicyApplied(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'meeting_id' => 'm-1',
            'tenant_id' => 't-1',
            'quorum_policy_id' => null,
            'convocation_no' => 1,
        ]);

        $engine = $this->buildEngine(['meetingRepo' => $meetingRepo]);
        $result = $engine->computeForMeeting('m-1', 't-1');

        $this->assertFalse($result['applied']);
        $this->assertNull($result['met']);
        $this->assertStringContainsString('Aucune politique', $result['justification']);
    }

    public function testComputeForMeetingSingleQuorumMet(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'meeting_id' => 'm-1',
            'tenant_id' => 't-1',
            'quorum_policy_id' => 'qp-1',
            'convocation_no' => 1,
        ]);

        $policyRepo = $this->createMock(PolicyRepository::class);
        $policyRepo->method('findQuorumPolicy')->willReturn([
            'id' => 'qp-1',
            'name' => 'Standard',
            'mode' => 'single',
            'denominator' => 'eligible_members',
            'threshold' => 0.5,
            'threshold_call2' => null,
            'include_proxies' => true,
            'count_remote' => true,
        ]);

        $attendanceRepo = $this->createMock(AttendanceRepository::class);
        $attendanceRepo->method('countPresentMembers')->willReturn(60);
        $attendanceRepo->method('sumPresentWeight')->willReturn(600.0);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(100);
        $memberRepo->method('sumActiveWeight')->willReturn(1000.0);

        $engine = $this->buildEngine([
            'meetingRepo' => $meetingRepo,
            'policyRepo' => $policyRepo,
            'attendanceRepo' => $attendanceRepo,
            'memberRepo' => $memberRepo,
        ]);

        $result = $engine->computeForMeeting('m-1', 't-1');

        $this->assertTrue($result['applied']);
        $this->assertTrue($result['met']);
        $this->assertEquals('qp-1', $result['policy']['id']);
        $this->assertEquals(0.6, $result['details']['primary']['ratio']);
    }

    public function testComputeForMeetingSingleQuorumNotMet(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'meeting_id' => 'm-1',
            'tenant_id' => 't-1',
            'quorum_policy_id' => 'qp-1',
            'convocation_no' => 1,
        ]);

        $policyRepo = $this->createMock(PolicyRepository::class);
        $policyRepo->method('findQuorumPolicy')->willReturn([
            'id' => 'qp-1',
            'name' => 'Standard',
            'mode' => 'single',
            'denominator' => 'eligible_members',
            'threshold' => 0.5,
            'threshold_call2' => null,
            'include_proxies' => true,
            'count_remote' => true,
        ]);

        $attendanceRepo = $this->createMock(AttendanceRepository::class);
        $attendanceRepo->method('countPresentMembers')->willReturn(40);
        $attendanceRepo->method('sumPresentWeight')->willReturn(400.0);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(100);
        $memberRepo->method('sumActiveWeight')->willReturn(1000.0);

        $engine = $this->buildEngine([
            'meetingRepo' => $meetingRepo,
            'policyRepo' => $policyRepo,
            'attendanceRepo' => $attendanceRepo,
            'memberRepo' => $memberRepo,
        ]);

        $result = $engine->computeForMeeting('m-1', 't-1');

        $this->assertTrue($result['applied']);
        $this->assertFalse($result['met']);
        $this->assertStringContainsString('non atteint', $result['justification']);
    }

    public function testComputeForMeetingDoubleQuorum(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'meeting_id' => 'm-1',
            'tenant_id' => 't-1',
            'quorum_policy_id' => 'qp-2',
            'convocation_no' => 1,
        ]);

        $policyRepo = $this->createMock(PolicyRepository::class);
        $policyRepo->method('findQuorumPolicy')->willReturn([
            'id' => 'qp-2',
            'name' => 'Double Quorum',
            'mode' => 'double',
            'denominator' => 'eligible_members',
            'threshold' => 0.5,
            'denominator2' => 'eligible_weight',
            'threshold2' => 0.5,
            'threshold_call2' => null,
            'include_proxies' => true,
            'count_remote' => true,
        ]);

        // 60 members present (60/100 = 0.6 >= 0.5) but only 400 weight (400/1000 = 0.4 < 0.5)
        $attendanceRepo = $this->createMock(AttendanceRepository::class);
        $attendanceRepo->method('countPresentMembers')->willReturn(60);
        $attendanceRepo->method('sumPresentWeight')->willReturn(400.0);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(100);
        $memberRepo->method('sumActiveWeight')->willReturn(1000.0);

        $engine = $this->buildEngine([
            'meetingRepo' => $meetingRepo,
            'policyRepo' => $policyRepo,
            'attendanceRepo' => $attendanceRepo,
            'memberRepo' => $memberRepo,
        ]);

        $result = $engine->computeForMeeting('m-1', 't-1');

        $this->assertTrue($result['applied']);
        // Primary met but secondary not → overall not met
        $this->assertFalse($result['met']);
        $this->assertTrue($result['details']['primary']['met']);
        $this->assertFalse($result['details']['secondary']['met']);
    }

    public function testComputeForMeetingEvolvingSecondConvocation(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn([
            'meeting_id' => 'm-1',
            'tenant_id' => 't-1',
            'quorum_policy_id' => 'qp-3',
            'convocation_no' => 2,
        ]);

        $policyRepo = $this->createMock(PolicyRepository::class);
        $policyRepo->method('findQuorumPolicy')->willReturn([
            'id' => 'qp-3',
            'name' => 'Evolving',
            'mode' => 'evolving',
            'denominator' => 'eligible_members',
            'threshold' => 0.5,
            'threshold_call2' => 0.25,
            'include_proxies' => true,
            'count_remote' => true,
        ]);

        // 30% attendance — fails 1st call (50%) but passes 2nd call (25%)
        $attendanceRepo = $this->createMock(AttendanceRepository::class);
        $attendanceRepo->method('countPresentMembers')->willReturn(30);
        $attendanceRepo->method('sumPresentWeight')->willReturn(300.0);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(100);
        $memberRepo->method('sumActiveWeight')->willReturn(1000.0);

        $engine = $this->buildEngine([
            'meetingRepo' => $meetingRepo,
            'policyRepo' => $policyRepo,
            'attendanceRepo' => $attendanceRepo,
            'memberRepo' => $memberRepo,
        ]);

        $result = $engine->computeForMeeting('m-1', 't-1');

        $this->assertTrue($result['applied']);
        $this->assertTrue($result['met']);
        // Threshold should be the 2nd convocation value
        $this->assertEquals(0.25, $result['details']['primary']['threshold']);
    }

    public function testComputeForMeetingEmptyIdThrows(): void {
        $engine = $this->buildEngine();
        $this->expectException(\InvalidArgumentException::class);
        $engine->computeForMeeting('', 't-1');
    }

    public function testComputeForMeetingNotFoundThrows(): void {
        $meetingRepo = $this->createMock(MeetingRepository::class);
        $meetingRepo->method('findByIdForTenant')->willReturn(null);

        $engine = $this->buildEngine(['meetingRepo' => $meetingRepo]);
        $this->expectException(\RuntimeException::class);
        $engine->computeForMeeting('m-nonexistent', 't-1');
    }

    // =========================================================================
    // QuorumEngine::computeForMotion() — DIRECT SERVICE TESTS (mocked repos)
    // =========================================================================

    public function testComputeForMotionNoPolicy(): void {
        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findWithQuorumContext')->willReturn([
            'motion_id' => 'mot-1',
            'motion_title' => 'Test Motion',
            'meeting_id' => 'm-1',
            'tenant_id' => 't-1',
            'convocation_no' => 1,
            'motion_quorum_policy_id' => null,
            'meeting_quorum_policy_id' => null,
            'motion_opened_at' => null,
        ]);

        $engine = $this->buildEngine(['motionRepo' => $motionRepo]);
        $result = $engine->computeForMotion('mot-1');

        $this->assertFalse($result['applied']);
        $this->assertNull($result['met']);
    }

    public function testComputeForMotionWithPolicy(): void {
        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findWithQuorumContext')->willReturn([
            'motion_id' => 'mot-1',
            'motion_title' => 'Test Motion',
            'meeting_id' => 'm-1',
            'tenant_id' => 't-1',
            'convocation_no' => 1,
            'motion_quorum_policy_id' => 'qp-1',
            'meeting_quorum_policy_id' => null,
            'motion_opened_at' => null,
        ]);

        $policyRepo = $this->createMock(PolicyRepository::class);
        $policyRepo->method('findQuorumPolicy')->willReturn([
            'id' => 'qp-1',
            'name' => 'Motion Quorum',
            'mode' => 'single',
            'denominator' => 'eligible_members',
            'threshold' => 0.5,
            'threshold_call2' => null,
            'include_proxies' => true,
            'count_remote' => true,
        ]);

        $attendanceRepo = $this->createMock(AttendanceRepository::class);
        $attendanceRepo->method('countPresentMembers')->willReturn(60);
        $attendanceRepo->method('sumPresentWeight')->willReturn(600.0);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(100);
        $memberRepo->method('sumActiveWeight')->willReturn(1000.0);

        $engine = $this->buildEngine([
            'motionRepo' => $motionRepo,
            'policyRepo' => $policyRepo,
            'attendanceRepo' => $attendanceRepo,
            'memberRepo' => $memberRepo,
        ]);

        $result = $engine->computeForMotion('mot-1');

        $this->assertTrue($result['applied']);
        $this->assertTrue($result['met']);
        $this->assertEquals('qp-1', $result['policy']['id']);
        $this->assertArrayHasKey('applies_to', $result);
        $this->assertEquals('mot-1', $result['applies_to']['motion_id']);
    }

    public function testComputeForMotionEmptyIdThrows(): void {
        $engine = $this->buildEngine();
        $this->expectException(\InvalidArgumentException::class);
        $engine->computeForMotion('');
    }

    public function testComputeForMotionFallsBackToMeetingPolicy(): void {
        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findWithQuorumContext')->willReturn([
            'motion_id' => 'mot-1',
            'motion_title' => 'Test Motion',
            'meeting_id' => 'm-1',
            'tenant_id' => 't-1',
            'convocation_no' => 1,
            'motion_quorum_policy_id' => null, // No motion-level policy
            'meeting_quorum_policy_id' => 'qp-meeting', // Falls back to meeting
            'motion_opened_at' => null,
        ]);

        $policyRepo = $this->createMock(PolicyRepository::class);
        $policyRepo->method('findQuorumPolicy')->with('qp-meeting')->willReturn([
            'id' => 'qp-meeting',
            'name' => 'Meeting Quorum',
            'mode' => 'single',
            'denominator' => 'eligible_members',
            'threshold' => 0.5,
            'threshold_call2' => null,
            'include_proxies' => true,
            'count_remote' => true,
        ]);

        $attendanceRepo = $this->createMock(AttendanceRepository::class);
        $attendanceRepo->method('countPresentMembers')->willReturn(60);
        $attendanceRepo->method('sumPresentWeight')->willReturn(600.0);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(100);
        $memberRepo->method('sumActiveWeight')->willReturn(1000.0);

        $engine = $this->buildEngine([
            'motionRepo' => $motionRepo,
            'policyRepo' => $policyRepo,
            'attendanceRepo' => $attendanceRepo,
            'memberRepo' => $memberRepo,
        ]);

        $result = $engine->computeForMotion('mot-1');

        $this->assertTrue($result['applied']);
        $this->assertEquals('qp-meeting', $result['policy']['id']);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function createMockResult(): array {
        return [
            'applied' => true,
            'met' => true,
            'details' => [
                'primary' => [
                    'configured' => true,
                    'met' => true,
                    'ratio' => 0.6,
                    'threshold' => 0.5,
                ],
            ],
            'justification' => 'Standard Quorum (convocation 1) : quorum met.',
            'meeting' => [
                'id' => 'test-meeting-id',
                'tenant_id' => 'test-tenant-id',
                'convocation_no' => 1,
            ],
        ];
    }

    private function createMockResultWithPolicy(): array {
        return array_merge($this->createMockResult(), [
            'policy' => [
                'id' => 'test-policy-id',
                'name' => 'Standard Quorum',
                'mode' => 'single',
            ],
        ]);
    }

    private function createNoPolicyResult(): array {
        return [
            'applied' => false,
            'met' => null,
            'details' => [],
            'justification' => 'No quorum policy applied.',
            'meeting' => [
                'id' => 'test-meeting-id',
                'tenant_id' => 'test-tenant-id',
            ],
        ];
    }

    /**
     * Compute a single quorum ratio block.
     * Mirrors QuorumEngine::ratioBlock logic.
     */
    private function computeRatioBlock(
        string $basis,
        float $threshold,
        int $numMembers,
        float $numWeight,
        int $eligibleMembers,
        float $eligibleWeight,
    ): array {
        if ($basis === 'eligible_members') {
            $den = $eligibleMembers;
            $num = (float) $numMembers;
        } else {
            $den = $eligibleWeight > 0 ? $eligibleWeight : 0.0001;
            $num = $numWeight;
        }

        $ratio = $den > 0 ? $num / $den : 0.0;

        return [
            'configured' => true,
            'met' => $ratio >= $threshold,
            'ratio' => $ratio,
            'threshold' => $threshold,
            'numerator' => $num,
            'denominator' => $den,
            'basis' => $basis,
        ];
    }

    /**
     * Get threshold based on convocation number (evolving mode).
     */
    private function getEvolvingThreshold(int $convocationNo, float $threshold1, ?float $threshold2): float {
        if ($convocationNo === 2 && $threshold2 !== null) {
            return $threshold2;
        }

        return $threshold1;
    }

    /**
     * Count attendance by allowed modes.
     */
    private function countByModes(array $attendance, array $allowedModes): int {
        return array_reduce($attendance, function ($count, $a) use ($allowedModes) {
            return $count + (in_array($a['mode'], $allowedModes, true) ? 1 : 0);
        }, 0);
    }

    /**
     * Get allowed attendance modes from policy.
     */
    private function getAllowedModes(array $policy): array {
        $modes = ['present'];

        if ($policy['count_remote'] ?? true) {
            $modes[] = 'remote';
        }

        if ($policy['include_proxies'] ?? true) {
            $modes[] = 'proxy';
        }

        return $modes;
    }

    /**
     * Generate justification text.
     */
    private function generateJustification(
        string $name,
        string $mode,
        int $convocationNo,
        array $modes,
        float $ratio,
        float $threshold,
        ?bool $met,
    ): string {
        $status = ($met === null) ? 'non applicable' : ($met ? 'atteint' : 'non atteint');
        $modesLabel = implode(', ', $modes);
        $ratioStr = number_format($ratio, 4, '.', '');
        $thrStr = number_format($threshold, 4, '.', '');

        return sprintf(
            '%s (convocation %d) : base eligible_members (ratio %s / seuil %s). Comptés: %s. Résultat: %s.',
            $name,
            $convocationNo,
            $ratioStr,
            $thrStr,
            $modesLabel,
            $status,
        );
    }
}
