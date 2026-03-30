<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Service\VoteEngine;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for VoteEngine service.
 *
 * Tests both the static computeDecision() method (pure calculation, no I/O)
 * and the result structure / decision-status logic.
 * Integration tests with real database are in tests/Integration/.
 */
class VoteEngineTest extends TestCase {
    // =========================================================================
    // RESULT STRUCTURE TESTS
    // =========================================================================

    /**
     * Validates the expected structure of computeMotionResult output.
     */
    public function testResultStructureKeys(): void {
        $expectedKeys = [
            'motion',
            'tallies',
            'eligible',
            'expressed',
            'quorum',
            'majority',
            'decision',
        ];

        // Simulate a result structure
        $result = $this->createMockResult();

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Result should contain '{$key}' key");
        }
    }

    public function testMotionStructure(): void {
        $result = $this->createMockResult();

        $this->assertArrayHasKey('id', $result['motion']);
        $this->assertArrayHasKey('title', $result['motion']);
        $this->assertArrayHasKey('meeting_id', $result['motion']);
        $this->assertArrayHasKey('tenant_id', $result['motion']);
        $this->assertArrayHasKey('secret', $result['motion']);
    }

    public function testTalliesStructure(): void {
        $result = $this->createMockResult();

        $expectedVoteValues = ['for', 'against', 'abstain', 'nsp'];

        foreach ($expectedVoteValues as $value) {
            $this->assertArrayHasKey($value, $result['tallies']);
            $this->assertArrayHasKey('count', $result['tallies'][$value]);
            $this->assertArrayHasKey('weight', $result['tallies'][$value]);
        }
    }

    public function testQuorumStructure(): void {
        $result = $this->createMockResult();

        $this->assertArrayHasKey('applied', $result['quorum']);
        $this->assertArrayHasKey('met', $result['quorum']);
        $this->assertArrayHasKey('basis', $result['quorum']);
        $this->assertArrayHasKey('ratio', $result['quorum']);
        $this->assertArrayHasKey('threshold', $result['quorum']);
    }

    public function testMajorityStructure(): void {
        $result = $this->createMockResult();

        $this->assertArrayHasKey('applied', $result['majority']);
        $this->assertArrayHasKey('met', $result['majority']);
        $this->assertArrayHasKey('base', $result['majority']);
        $this->assertArrayHasKey('ratio', $result['majority']);
        $this->assertArrayHasKey('threshold', $result['majority']);
        $this->assertArrayHasKey('abstention_as_against', $result['majority']);
    }

    public function testDecisionStructure(): void {
        $result = $this->createMockResult();

        $this->assertArrayHasKey('status', $result['decision']);
        $this->assertArrayHasKey('reason', $result['decision']);
    }

    // =========================================================================
    // DECISION STATUS CALCULATION TESTS
    // =========================================================================

    /**
     * Test decision status computation logic.
     */
    public function testDecisionStatusNoVotes(): void {
        $status = $this->computeDecisionStatus(
            expressedMembers: 0,
            nspCount: 0,
            quorumMet: null,
            hasVotePolicy: true,
            adopted: null,
        );

        $this->assertEquals('no_votes', $status);
    }

    public function testDecisionStatusNoQuorum(): void {
        $status = $this->computeDecisionStatus(
            expressedMembers: 10,
            nspCount: 0,
            quorumMet: false,
            hasVotePolicy: true,
            adopted: false,
        );

        $this->assertEquals('no_quorum', $status);
    }

    public function testDecisionStatusAdopted(): void {
        $status = $this->computeDecisionStatus(
            expressedMembers: 10,
            nspCount: 0,
            quorumMet: true,
            hasVotePolicy: true,
            adopted: true,
        );

        $this->assertEquals('adopted', $status);
    }

    public function testDecisionStatusRejected(): void {
        $status = $this->computeDecisionStatus(
            expressedMembers: 10,
            nspCount: 0,
            quorumMet: true,
            hasVotePolicy: true,
            adopted: false,
        );

        $this->assertEquals('rejected', $status);
    }

    public function testDecisionStatusNoPolicy(): void {
        $status = $this->computeDecisionStatus(
            expressedMembers: 10,
            nspCount: 0,
            quorumMet: null,
            hasVotePolicy: false,
            adopted: null,
        );

        $this->assertEquals('no_policy', $status);
    }

    public function testDecisionStatusWithNspOnly(): void {
        // Even with only NSP votes (no expressed), there are votes
        $status = $this->computeDecisionStatus(
            expressedMembers: 0,
            nspCount: 5,
            quorumMet: true,
            hasVotePolicy: true,
            adopted: false,
        );

        $this->assertEquals('rejected', $status);
    }

    // =========================================================================
    // MAJORITY CALCULATION TESTS
    // =========================================================================

    public function testMajorityCalculationExpressedBase(): void {
        $tallies = [
            'for' => ['weight' => 60.0],
            'against' => ['weight' => 30.0],
            'abstain' => ['weight' => 10.0],
        ];

        $expressedWeight = 100.0;
        $threshold = 0.5;

        $ratio = $tallies['for']['weight'] / $expressedWeight;
        $adopted = $ratio >= $threshold;

        $this->assertEquals(0.6, $ratio);
        $this->assertTrue($adopted);
    }

    public function testMajorityCalculationWithAbstentionAsAgainst(): void {
        $tallies = [
            'for' => ['weight' => 45.0],
            'against' => ['weight' => 35.0],
            'abstain' => ['weight' => 20.0],
        ];

        $expressedWeight = 100.0;
        $threshold = 0.5;
        $abstAsAgainst = true;

        // Without abstention as against: 45% for >= 50%? No
        $ratio = $tallies['for']['weight'] / $expressedWeight;
        $this->assertFalse($ratio >= $threshold);

        // With abstention as against: effective against = 35 + 20 = 55
        // This doesn't change the for ratio, but affects interpretation
        $effectiveAgainst = $tallies['against']['weight'] + ($abstAsAgainst ? $tallies['abstain']['weight'] : 0);
        $this->assertEquals(55.0, $effectiveAgainst);
    }

    public function testMajorityCalculationEligibleBase(): void {
        $tallies = [
            'for' => ['weight' => 60.0],
            'against' => ['weight' => 20.0],
            'abstain' => ['weight' => 10.0],
        ];

        $eligibleWeight = 200.0; // More than expressed
        $threshold = 0.5;

        // Using eligible as base: 60/200 = 0.3 < 0.5
        $ratio = $tallies['for']['weight'] / $eligibleWeight;
        $adopted = $ratio >= $threshold;

        $this->assertEquals(0.3, $ratio);
        $this->assertFalse($adopted);
    }

    // =========================================================================
    // TALLY AGGREGATION TESTS
    // =========================================================================

    public function testExpressedCalculation(): void {
        $tallies = [
            'for' => ['count' => 10, 'weight' => 100.0],
            'against' => ['count' => 5, 'weight' => 50.0],
            'abstain' => ['count' => 3, 'weight' => 30.0],
            'nsp' => ['count' => 2, 'weight' => 20.0],
        ];

        $expressedMembers = $tallies['for']['count']
            + $tallies['against']['count']
            + $tallies['abstain']['count'];

        $expressedWeight = $tallies['for']['weight']
            + $tallies['against']['weight']
            + $tallies['abstain']['weight'];

        $this->assertEquals(18, $expressedMembers);
        $this->assertEquals(180.0, $expressedWeight);

        // NSP not included
        $this->assertNotEquals(20, $expressedMembers);
        $this->assertNotEquals(200.0, $expressedWeight);
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function testZeroExpressedWeight(): void {
        $expressedWeight = 0.0;
        $forWeight = 0.0;

        // Should handle division by zero gracefully
        $ratio = $expressedWeight > 0 ? $forWeight / $expressedWeight : 0.0;

        $this->assertEquals(0.0, $ratio);
    }

    public function testQuorumBlocksAdoption(): void {
        // Even with 100% for votes, if quorum not met, motion fails
        $ratio = 1.0; // 100% for
        $threshold = 0.5;
        $quorumMet = false;

        $adopted = $quorumMet !== false && $ratio >= $threshold;

        $this->assertFalse($adopted);
    }

    public function testNoQuorumPolicyAllowsVote(): void {
        // Without quorum policy, quorumMet is null
        $ratio = 0.6;
        $threshold = 0.5;
        $quorumMet = null; // No quorum policy

        // null quorum doesn't block
        $adopted = $quorumMet !== false && $ratio >= $threshold;

        $this->assertTrue($adopted);
    }

    // =========================================================================
    // VoteEngine::computeDecision() — DIRECT STATIC METHOD TESTS
    // =========================================================================

    public function testComputeDecisionResultStructure(): void {
        $result = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: ['base' => 'expressed', 'threshold' => 0.5],
            forWeight: 60.0,
            againstWeight: 30.0,
            abstainWeight: 10.0,
            expressedWeight: 100.0,
            expressedMembers: 10,
            eligibleMembers: 20,
            eligibleWeight: 200.0,
        );

        $this->assertArrayHasKey('quorum', $result);
        $this->assertArrayHasKey('majority', $result);
        $this->assertIsBool($result['quorum']['applied']);
        $this->assertIsBool($result['majority']['applied']);
    }

    public function testComputeDecisionSimpleMajorityAdopted(): void {
        $result = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: ['base' => 'expressed', 'threshold' => 0.5],
            forWeight: 60.0,
            againstWeight: 30.0,
            abstainWeight: 10.0,
            expressedWeight: 100.0,
            expressedMembers: 10,
            eligibleMembers: 20,
            eligibleWeight: 200.0,
        );

        $this->assertTrue($result['majority']['met']);
        // 'expressed' base = for + against (excl. abstain): 60/90
        $this->assertEqualsWithDelta(60.0 / 90.0, $result['majority']['ratio'], 0.0001);
        $this->assertEquals('expressed', $result['majority']['base']);
        $this->assertFalse($result['quorum']['applied']);
    }

    public function testComputeDecisionSimpleMajorityRejected(): void {
        $result = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: ['base' => 'expressed', 'threshold' => 0.5],
            forWeight: 30.0,
            againstWeight: 60.0,
            abstainWeight: 10.0,
            expressedWeight: 100.0,
            expressedMembers: 10,
            eligibleMembers: 20,
            eligibleWeight: 200.0,
        );

        $this->assertFalse($result['majority']['met']);
        // 30 / (30+60) = 30/90
        $this->assertEqualsWithDelta(30.0 / 90.0, $result['majority']['ratio'], 0.0001);
    }

    public function testComputeDecisionTwoThirdsMajority(): void {
        // 59 for / (59+31) = 59/90 ≈ 0.6556 < 2/3 → rejected
        $result = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: ['base' => 'expressed', 'threshold' => 2 / 3],
            forWeight: 59.0,
            againstWeight: 31.0,
            abstainWeight: 10.0,
            expressedWeight: 100.0,
            expressedMembers: 10,
            eligibleMembers: 20,
            eligibleWeight: 200.0,
        );

        $this->assertFalse($result['majority']['met']);

        // 70 for / (70+20) = 70/90 ≈ 0.778 > 2/3 → adopted
        $result2 = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: ['base' => 'expressed', 'threshold' => 2 / 3],
            forWeight: 70.0,
            againstWeight: 20.0,
            abstainWeight: 10.0,
            expressedWeight: 100.0,
            expressedMembers: 10,
            eligibleMembers: 20,
            eligibleWeight: 200.0,
        );

        $this->assertTrue($result2['majority']['met']);
    }

    public function testComputeDecisionAbstentionAsAgainst(): void {
        // With abstention_as_against=true, base includes abstentions:
        // 60 / (60+30+10) = 60/100 = 0.60 >= 0.5 → adopted
        $result = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: ['base' => 'expressed', 'threshold' => 0.5, 'abstention_as_against' => true],
            forWeight: 60.0,
            againstWeight: 30.0,
            abstainWeight: 10.0,
            expressedWeight: 100.0,
            expressedMembers: 10,
            eligibleMembers: 20,
            eligibleWeight: 200.0,
        );

        $this->assertTrue($result['majority']['met']);
        $this->assertEqualsWithDelta(0.6, $result['majority']['ratio'], 0.0001);

        // Without the flag, same values: 60/(60+30)=0.667 → also adopted
        // but the ratio differs (0.6 vs 0.667), demonstrating the flag has effect.

        // Edge case: abstentions tip the balance.
        // for=45, against=35, abstain=20 → without flag: 45/80 = 0.5625 >= 0.5 → adopted
        // with flag: 45/100 = 0.45 < 0.5 → REJECTED
        $result2 = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: ['base' => 'expressed', 'threshold' => 0.5, 'abstention_as_against' => true],
            forWeight: 45.0,
            againstWeight: 35.0,
            abstainWeight: 20.0,
            expressedWeight: 100.0,
            expressedMembers: 10,
            eligibleMembers: 20,
            eligibleWeight: 200.0,
        );

        $this->assertFalse($result2['majority']['met']);
        $this->assertEqualsWithDelta(0.45, $result2['majority']['ratio'], 0.0001);

        // Without flag: same data → 45/(45+35) = 45/80 = 0.5625 → adopted
        $result3 = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: ['base' => 'expressed', 'threshold' => 0.5, 'abstention_as_against' => false],
            forWeight: 45.0,
            againstWeight: 35.0,
            abstainWeight: 20.0,
            expressedWeight: 100.0,
            expressedMembers: 10,
            eligibleMembers: 20,
            eligibleWeight: 200.0,
        );

        $this->assertTrue($result3['majority']['met']);
        $this->assertEqualsWithDelta(45.0 / 80.0, $result3['majority']['ratio'], 0.0001);
    }

    public function testComputeDecisionEligibleBase(): void {
        // 60 for out of 200 eligible = 30% < 50%
        $result = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: ['base' => 'eligible', 'threshold' => 0.5],
            forWeight: 60.0,
            againstWeight: 20.0,
            abstainWeight: 10.0,
            expressedWeight: 90.0,
            expressedMembers: 9,
            eligibleMembers: 20,
            eligibleWeight: 200.0,
        );

        $this->assertFalse($result['majority']['met']);
        $this->assertEquals(0.3, $result['majority']['ratio']);
        $this->assertEquals('eligible', $result['majority']['base']);
    }

    public function testComputeDecisionPresentBase(): void {
        $result = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: ['base' => 'present', 'threshold' => 0.5],
            forWeight: 60.0,
            againstWeight: 20.0,
            abstainWeight: 10.0,
            expressedWeight: 90.0,
            expressedMembers: 9,
            eligibleMembers: 20,
            eligibleWeight: 200.0,
            presentWeight: 150.0,
        );

        // 60/150 = 0.4 < 0.5
        $this->assertFalse($result['majority']['met']);
        $this->assertEquals(0.4, $result['majority']['ratio']);
        $this->assertEquals('present', $result['majority']['base']);
    }

    public function testComputeDecisionWithQuorumMet(): void {
        $result = VoteEngine::computeDecision(
            quorumPolicy: ['denominator' => 'eligible_members', 'threshold' => 0.5],
            votePolicy: ['base' => 'expressed', 'threshold' => 0.5],
            forWeight: 60.0,
            againstWeight: 30.0,
            abstainWeight: 10.0,
            expressedWeight: 100.0,
            expressedMembers: 15,
            eligibleMembers: 20,
            eligibleWeight: 200.0,
        );

        $this->assertTrue($result['quorum']['applied']);
        $this->assertTrue($result['quorum']['met']);
        $this->assertEquals(0.75, $result['quorum']['ratio']); // 15/20
        $this->assertTrue($result['majority']['met']);
    }

    public function testComputeDecisionWithQuorumNotMet(): void {
        $result = VoteEngine::computeDecision(
            quorumPolicy: ['denominator' => 'eligible_members', 'threshold' => 0.5],
            votePolicy: ['base' => 'expressed', 'threshold' => 0.5],
            forWeight: 60.0,
            againstWeight: 30.0,
            abstainWeight: 10.0,
            expressedWeight: 100.0,
            expressedMembers: 8,
            eligibleMembers: 20,
            eligibleWeight: 200.0,
        );

        $this->assertTrue($result['quorum']['applied']);
        $this->assertFalse($result['quorum']['met']);
        // Quorum blocks adoption even though for > threshold
        $this->assertFalse($result['majority']['met']);
    }

    public function testComputeDecisionQuorumByWeight(): void {
        $result = VoteEngine::computeDecision(
            quorumPolicy: ['denominator' => 'eligible_weight', 'threshold' => 0.5],
            votePolicy: ['base' => 'expressed', 'threshold' => 0.5],
            forWeight: 60.0,
            againstWeight: 30.0,
            abstainWeight: 10.0,
            expressedWeight: 100.0,
            expressedMembers: 10,
            eligibleMembers: 20,
            eligibleWeight: 200.0,
        );

        $this->assertTrue($result['quorum']['applied']);
        $this->assertTrue($result['quorum']['met']); // 100/200 = 0.5 >= 0.5
        $this->assertEquals('eligible_weight', $result['quorum']['basis']);
    }

    public function testComputeDecisionNoPolicies(): void {
        $result = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: null,
            forWeight: 60.0,
            againstWeight: 30.0,
            abstainWeight: 10.0,
            expressedWeight: 100.0,
            expressedMembers: 10,
            eligibleMembers: 20,
            eligibleWeight: 200.0,
        );

        $this->assertFalse($result['quorum']['applied']);
        $this->assertNull($result['quorum']['met']);
        $this->assertFalse($result['majority']['applied']);
        $this->assertNull($result['majority']['met']);
    }

    public function testComputeDecisionZeroVotes(): void {
        $result = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: ['base' => 'expressed', 'threshold' => 0.5],
            forWeight: 0.0,
            againstWeight: 0.0,
            abstainWeight: 0.0,
            expressedWeight: 0.0,
            expressedMembers: 0,
            eligibleMembers: 20,
            eligibleWeight: 200.0,
        );

        $this->assertEquals(0.0, $result['majority']['ratio']);
        $this->assertFalse($result['majority']['met']);
    }

    public function testComputeDecisionZeroEligible(): void {
        $result = VoteEngine::computeDecision(
            quorumPolicy: ['denominator' => 'eligible_weight', 'threshold' => 0.5],
            votePolicy: ['base' => 'expressed', 'threshold' => 0.5],
            forWeight: 0.0,
            againstWeight: 0.0,
            abstainWeight: 0.0,
            expressedWeight: 0.0,
            expressedMembers: 0,
            eligibleMembers: 0,
            eligibleWeight: 0.0,
        );

        $this->assertFalse($result['quorum']['met']);
        $this->assertEquals(0.0, $result['quorum']['ratio']);
    }

    public function testComputeDecisionExactThreshold(): void {
        // Exactly at threshold: 45/(45+45) = 45/90 = 0.5 >= 0.5
        $result = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: ['base' => 'expressed', 'threshold' => 0.5],
            forWeight: 45.0,
            againstWeight: 45.0,
            abstainWeight: 10.0,
            expressedWeight: 100.0,
            expressedMembers: 10,
            eligibleMembers: 20,
            eligibleWeight: 200.0,
        );

        $this->assertTrue($result['majority']['met']);
        $this->assertEquals(0.5, $result['majority']['ratio']);
    }

    public function testComputeDecisionJustBelowThreshold(): void {
        // 44.9/(44.9+45.1) = 44.9/90 ≈ 0.4989 < 0.5
        $result = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: ['base' => 'expressed', 'threshold' => 0.5],
            forWeight: 44.9,
            againstWeight: 45.1,
            abstainWeight: 10.0,
            expressedWeight: 100.0,
            expressedMembers: 10,
            eligibleMembers: 20,
            eligibleWeight: 200.0,
        );

        $this->assertFalse($result['majority']['met']);
    }

    public function testComputeDecisionUnanimity(): void {
        // 100% threshold (unanimity)
        $result = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: ['base' => 'expressed', 'threshold' => 1.0],
            forWeight: 100.0,
            againstWeight: 0.0,
            abstainWeight: 0.0,
            expressedWeight: 100.0,
            expressedMembers: 10,
            eligibleMembers: 10,
            eligibleWeight: 100.0,
        );

        $this->assertTrue($result['majority']['met']);
        $this->assertEquals(1.0, $result['majority']['ratio']);

        // One against breaks unanimity
        $result2 = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: ['base' => 'expressed', 'threshold' => 1.0],
            forWeight: 99.0,
            againstWeight: 1.0,
            abstainWeight: 0.0,
            expressedWeight: 100.0,
            expressedMembers: 10,
            eligibleMembers: 10,
            eligibleWeight: 100.0,
        );

        $this->assertFalse($result2['majority']['met']);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Create a mock result structure for testing.
     */
    private function createMockResult(): array {
        return [
            'motion' => [
                'id' => 'test-motion-id',
                'title' => 'Test Motion',
                'meeting_id' => 'test-meeting-id',
                'tenant_id' => 'test-tenant-id',
                'vote_policy_id' => 'test-policy-id',
                'quorum_policy_id' => null,
                'secret' => false,
            ],
            'tallies' => [
                'for' => ['count' => 10, 'weight' => 100.0],
                'against' => ['count' => 5, 'weight' => 50.0],
                'abstain' => ['count' => 2, 'weight' => 20.0],
                'nsp' => ['count' => 1, 'weight' => 10.0],
            ],
            'eligible' => [
                'members' => 20,
                'weight' => 200.0,
            ],
            'expressed' => [
                'members' => 17,
                'weight' => 170.0,
            ],
            'quorum' => [
                'applied' => false,
                'met' => null,
                'basis' => null,
                'ratio' => null,
                'threshold' => null,
                'denominator' => null,
            ],
            'majority' => [
                'applied' => true,
                'met' => true,
                'base' => 'expressed',
                'ratio' => 0.588,
                'threshold' => 0.5,
                'abstention_as_against' => false,
            ],
            'decision' => [
                'status' => 'adopted',
                'reason' => 'Threshold reached.',
            ],
        ];
    }

    /**
     * Compute decision status based on vote parameters.
     * Mirrors VoteEngine logic for testing.
     */
    private function computeDecisionStatus(
        int $expressedMembers,
        int $nspCount,
        ?bool $quorumMet,
        bool $hasVotePolicy,
        ?bool $adopted,
    ): string {
        $hasVotes = ($expressedMembers + $nspCount) > 0;

        if (!$hasVotes) {
            return 'no_votes';
        }

        if ($quorumMet === false) {
            return 'no_quorum';
        }

        if ($hasVotePolicy && $adopted === true) {
            return 'adopted';
        }

        if ($hasVotePolicy && $adopted === false) {
            return 'rejected';
        }

        return 'no_policy';
    }

    // =========================================================================
    // VoteEngine::computeMotionResult() — MOCKED REPO TESTS
    // =========================================================================

    /**
     * Build a VoteEngine with all repos mocked.
     */
    private function buildVoteEngine(array $overrides = []): VoteEngine {
        $motionRepo     = $overrides['motionRepo']     ?? $this->createMock(MotionRepository::class);
        $ballotRepo     = $overrides['ballotRepo']     ?? $this->createMock(BallotRepository::class);
        $memberRepo     = $overrides['memberRepo']     ?? $this->createMock(MemberRepository::class);
        $policyRepo     = $overrides['policyRepo']     ?? $this->createMock(PolicyRepository::class);
        $attendanceRepo = $overrides['attendanceRepo'] ?? $this->createMock(AttendanceRepository::class);

        return new VoteEngine($motionRepo, $ballotRepo, $memberRepo, $policyRepo, $attendanceRepo);
    }

    /**
     * Helper: motion row with vote context.
     */
    private function makeMotionRow(array $overrides = []): array {
        return array_merge([
            'motion_id'                  => 'mot-1',
            'motion_title'               => 'Test',
            'meeting_id'                 => 'm-1',
            'tenant_id'                  => 't-1',
            'vote_policy_id'             => 'vp-1',
            'quorum_policy_id'           => null,
            'meeting_vote_policy_id'     => null,
            'meeting_quorum_policy_id'   => null,
            'secret'                     => false,
        ], $overrides);
    }

    /**
     * Helper: tally row with real votes.
     */
    private function makeTallyRow(array $overrides = []): array {
        return array_merge([
            'count_for'      => 10,
            'weight_for'     => 60.0,
            'count_against'  => 5,
            'weight_against' => 30.0,
            'count_abstain'  => 2,
            'weight_abstain' => 10.0,
            'count_nsp'      => 1,
            'weight_nsp'     => 0.0,
        ], $overrides);
    }

    /**
     * Helper: standard expressed vote policy.
     */
    private function makeVotePolicy(array $overrides = []): array {
        return array_merge([
            'base'                 => 'expressed',
            'threshold'            => 0.5,
            'abstention_as_against' => false,
        ], $overrides);
    }

    // --- Input validation ---

    public function testComputeMotionResultEmptyIdThrows(): void {
        $engine = $this->buildVoteEngine();
        $this->expectException(\InvalidArgumentException::class);
        $engine->computeMotionResult('');
    }

    public function testComputeMotionResultMotionNotFoundThrows(): void {
        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findWithVoteContext')->willReturn(null);

        $engine = $this->buildVoteEngine(['motionRepo' => $motionRepo]);
        $this->expectException(\RuntimeException::class);
        $engine->computeMotionResult('mot-nonexistent', 't-1');
    }

    // --- Adopted scenario ---

    public function testComputeMotionResultAdopted(): void {
        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findWithVoteContext')->willReturn($this->makeMotionRow());

        $ballotRepo = $this->createMock(BallotRepository::class);
        $ballotRepo->method('tally')->willReturn($this->makeTallyRow([
            'count_for'      => 12,
            'weight_for'     => 60.0,
            'count_against'  => 6,
            'weight_against' => 30.0,
            'count_abstain'  => 2,
            'weight_abstain' => 10.0,
            'count_nsp'      => 0,
            'weight_nsp'     => 0.0,
        ]));

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(20);
        $memberRepo->method('sumActiveWeight')->willReturn(200.0);

        $policyRepo = $this->createMock(PolicyRepository::class);
        $policyRepo->method('findVotePolicy')->willReturn($this->makeVotePolicy([
            'base'      => 'expressed',
            'threshold' => 0.5,
        ]));

        $engine = $this->buildVoteEngine([
            'motionRepo' => $motionRepo,
            'ballotRepo' => $ballotRepo,
            'memberRepo' => $memberRepo,
            'policyRepo' => $policyRepo,
        ]);

        $result = $engine->computeMotionResult('mot-1', 't-1');

        $this->assertEquals('adopted', $result['decision']['status']);
        $this->assertTrue($result['majority']['met']);
        $this->assertEquals(60.0, $result['tallies']['for']['weight']);
        $this->assertEquals(30.0, $result['tallies']['against']['weight']);
        $this->assertEquals(10.0, $result['tallies']['abstain']['weight']);
    }

    // --- Rejected scenario ---

    public function testComputeMotionResultRejected(): void {
        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findWithVoteContext')->willReturn($this->makeMotionRow());

        $ballotRepo = $this->createMock(BallotRepository::class);
        $ballotRepo->method('tally')->willReturn($this->makeTallyRow([
            'count_for'      => 6,
            'weight_for'     => 30.0,
            'count_against'  => 12,
            'weight_against' => 60.0,
            'count_abstain'  => 2,
            'weight_abstain' => 10.0,
            'count_nsp'      => 0,
            'weight_nsp'     => 0.0,
        ]));

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(20);
        $memberRepo->method('sumActiveWeight')->willReturn(200.0);

        $policyRepo = $this->createMock(PolicyRepository::class);
        $policyRepo->method('findVotePolicy')->willReturn($this->makeVotePolicy());

        $engine = $this->buildVoteEngine([
            'motionRepo' => $motionRepo,
            'ballotRepo' => $ballotRepo,
            'memberRepo' => $memberRepo,
            'policyRepo' => $policyRepo,
        ]);

        $result = $engine->computeMotionResult('mot-1', 't-1');

        $this->assertEquals('rejected', $result['decision']['status']);
        $this->assertFalse($result['majority']['met']);
    }

    // --- No quorum blocks adoption ---

    public function testComputeMotionResultNoQuorum(): void {
        // Motion has a quorum policy, only 5 members expressed vs 20 eligible (needs 50% = 10)
        $motionRow = $this->makeMotionRow(['quorum_policy_id' => 'qp-1']);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findWithVoteContext')->willReturn($motionRow);

        $ballotRepo = $this->createMock(BallotRepository::class);
        $ballotRepo->method('tally')->willReturn($this->makeTallyRow([
            'count_for'      => 3,
            'weight_for'     => 30.0,
            'count_against'  => 1,
            'weight_against' => 10.0,
            'count_abstain'  => 1,
            'weight_abstain' => 5.0,
            'count_nsp'      => 0,
            'weight_nsp'     => 0.0,
        ]));

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(20);
        $memberRepo->method('sumActiveWeight')->willReturn(200.0);

        $policyRepo = $this->createMock(PolicyRepository::class);
        $policyRepo->method('findQuorumPolicy')->willReturn([
            'denominator' => 'eligible_members',
            'threshold'   => 0.5,
        ]);
        $policyRepo->method('findVotePolicy')->willReturn($this->makeVotePolicy());

        $engine = $this->buildVoteEngine([
            'motionRepo' => $motionRepo,
            'ballotRepo' => $ballotRepo,
            'memberRepo' => $memberRepo,
            'policyRepo' => $policyRepo,
        ]);

        $result = $engine->computeMotionResult('mot-1', 't-1');

        // quorum not met → decision must be no_quorum
        $this->assertEquals('no_quorum', $result['decision']['status']);
        $this->assertFalse($result['quorum']['met']);
    }

    // --- No votes ---

    public function testComputeMotionResultNoVotes(): void {
        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findWithVoteContext')->willReturn($this->makeMotionRow());

        $ballotRepo = $this->createMock(BallotRepository::class);
        $ballotRepo->method('tally')->willReturn([
            'count_for'      => 0,
            'weight_for'     => 0.0,
            'count_against'  => 0,
            'weight_against' => 0.0,
            'count_abstain'  => 0,
            'weight_abstain' => 0.0,
            'count_nsp'      => 0,
            'weight_nsp'     => 0.0,
        ]);

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(20);
        $memberRepo->method('sumActiveWeight')->willReturn(200.0);

        $policyRepo = $this->createMock(PolicyRepository::class);
        $policyRepo->method('findVotePolicy')->willReturn($this->makeVotePolicy());

        $engine = $this->buildVoteEngine([
            'motionRepo' => $motionRepo,
            'ballotRepo' => $ballotRepo,
            'memberRepo' => $memberRepo,
            'policyRepo' => $policyRepo,
        ]);

        $result = $engine->computeMotionResult('mot-1', 't-1');

        $this->assertEquals('no_votes', $result['decision']['status']);
    }

    // --- No policy ---

    public function testComputeMotionResultNoPolicy(): void {
        // Motion has neither vote_policy_id nor meeting_vote_policy_id
        $motionRow = $this->makeMotionRow([
            'vote_policy_id'         => null,
            'meeting_vote_policy_id' => null,
        ]);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findWithVoteContext')->willReturn($motionRow);

        $ballotRepo = $this->createMock(BallotRepository::class);
        $ballotRepo->method('tally')->willReturn($this->makeTallyRow());

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(20);
        $memberRepo->method('sumActiveWeight')->willReturn(200.0);

        $engine = $this->buildVoteEngine([
            'motionRepo' => $motionRepo,
            'ballotRepo' => $ballotRepo,
            'memberRepo' => $memberRepo,
        ]);

        $result = $engine->computeMotionResult('mot-1', 't-1');

        $this->assertEquals('no_policy', $result['decision']['status']);
    }

    // --- Policy fallback to meeting level ---

    public function testComputeMotionResultFallsBackToMeetingVotePolicy(): void {
        // Motion has no direct vote_policy_id, but meeting has one
        $motionRow = $this->makeMotionRow([
            'vote_policy_id'         => null,
            'meeting_vote_policy_id' => 'vp-meeting',
        ]);

        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findWithVoteContext')->willReturn($motionRow);

        $ballotRepo = $this->createMock(BallotRepository::class);
        $ballotRepo->method('tally')->willReturn($this->makeTallyRow());

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(20);
        $memberRepo->method('sumActiveWeight')->willReturn(200.0);

        $policyRepo = $this->createMock(PolicyRepository::class);
        // When called with 'vp-meeting' it returns the policy
        $policyRepo->method('findVotePolicy')
            ->with('vp-meeting')
            ->willReturn($this->makeVotePolicy());

        $engine = $this->buildVoteEngine([
            'motionRepo' => $motionRepo,
            'ballotRepo' => $ballotRepo,
            'memberRepo' => $memberRepo,
            'policyRepo' => $policyRepo,
        ]);

        $result = $engine->computeMotionResult('mot-1', 't-1');

        // The meeting-level policy was used — result should not be no_policy
        $this->assertNotEquals('no_policy', $result['decision']['status']);
        $this->assertEquals('vp-meeting', $result['motion']['vote_policy_id']);
    }

    // --- Present base uses attendanceRepo.sumPresentWeight ---

    public function testComputeMotionResultPresentBaseUsesAttendanceWeight(): void {
        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findWithVoteContext')->willReturn($this->makeMotionRow());

        $ballotRepo = $this->createMock(BallotRepository::class);
        $ballotRepo->method('tally')->willReturn($this->makeTallyRow([
            'count_for'      => 10,
            'weight_for'     => 60.0,
            'count_against'  => 4,
            'weight_against' => 20.0,
            'count_abstain'  => 0,
            'weight_abstain' => 0.0,
            'count_nsp'      => 0,
            'weight_nsp'     => 0.0,
        ]));

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(20);
        $memberRepo->method('sumActiveWeight')->willReturn(200.0);

        // Vote policy with base='present'
        $policyRepo = $this->createMock(PolicyRepository::class);
        $policyRepo->method('findVotePolicy')->willReturn($this->makeVotePolicy([
            'base'      => 'present',
            'threshold' => 0.5,
        ]));

        // attendanceRepo should be called to get present weight
        $attendanceRepo = $this->createMock(AttendanceRepository::class);
        $attendanceRepo->expects($this->once())
            ->method('sumPresentWeight')
            ->willReturn(150.0);

        $engine = $this->buildVoteEngine([
            'motionRepo'     => $motionRepo,
            'ballotRepo'     => $ballotRepo,
            'memberRepo'     => $memberRepo,
            'policyRepo'     => $policyRepo,
            'attendanceRepo' => $attendanceRepo,
        ]);

        $result = $engine->computeMotionResult('mot-1', 't-1');

        // 60 / 150 = 0.4 < 0.5 → rejected
        $this->assertEquals('rejected', $result['decision']['status']);
        $this->assertEqualsWithDelta(0.4, $result['majority']['ratio'], 0.001);
    }

    // --- Full result structure check ---

    public function testComputeMotionResultStructure(): void {
        $motionRepo = $this->createMock(MotionRepository::class);
        $motionRepo->method('findWithVoteContext')->willReturn($this->makeMotionRow());

        $ballotRepo = $this->createMock(BallotRepository::class);
        $ballotRepo->method('tally')->willReturn($this->makeTallyRow());

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('countActive')->willReturn(20);
        $memberRepo->method('sumActiveWeight')->willReturn(200.0);

        $policyRepo = $this->createMock(PolicyRepository::class);
        $policyRepo->method('findVotePolicy')->willReturn($this->makeVotePolicy());

        $engine = $this->buildVoteEngine([
            'motionRepo' => $motionRepo,
            'ballotRepo' => $ballotRepo,
            'memberRepo' => $memberRepo,
            'policyRepo' => $policyRepo,
        ]);

        $result = $engine->computeMotionResult('mot-1', 't-1');

        // All top-level keys must be present
        foreach (['motion', 'tallies', 'eligible', 'expressed', 'quorum', 'majority', 'decision'] as $key) {
            $this->assertArrayHasKey($key, $result, "Missing top-level key: {$key}");
        }

        // Nested keys
        $this->assertArrayHasKey('id', $result['motion']);
        $this->assertArrayHasKey('title', $result['motion']);
        $this->assertArrayHasKey('meeting_id', $result['motion']);
        $this->assertArrayHasKey('tenant_id', $result['motion']);
        $this->assertArrayHasKey('secret', $result['motion']);

        $this->assertArrayHasKey('for', $result['tallies']);
        $this->assertArrayHasKey('against', $result['tallies']);
        $this->assertArrayHasKey('abstain', $result['tallies']);
        $this->assertArrayHasKey('nsp', $result['tallies']);

        $this->assertArrayHasKey('status', $result['decision']);
        $this->assertArrayHasKey('reason', $result['decision']);
    }
}
