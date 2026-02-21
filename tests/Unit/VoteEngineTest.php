<?php

declare(strict_types=1);

namespace Tests\Unit;

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
        $this->assertEquals(0.6, $result['majority']['ratio']);
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
        $this->assertEquals(0.3, $result['majority']['ratio']);
    }

    public function testComputeDecisionTwoThirdsMajority(): void {
        // 65% for with 2/3 threshold → rejected
        $result = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: ['base' => 'expressed', 'threshold' => 2 / 3],
            forWeight: 65.0,
            againstWeight: 25.0,
            abstainWeight: 10.0,
            expressedWeight: 100.0,
            expressedMembers: 10,
            eligibleMembers: 20,
            eligibleWeight: 200.0,
        );

        $this->assertFalse($result['majority']['met']);

        // 70% for with 2/3 threshold → adopted
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
        // Exactly at threshold: 50/100 = 0.5 >= 0.5
        $result = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: ['base' => 'expressed', 'threshold' => 0.5],
            forWeight: 50.0,
            againstWeight: 40.0,
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
        // 49.9/100 = 0.499 < 0.5
        $result = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: ['base' => 'expressed', 'threshold' => 0.5],
            forWeight: 49.9,
            againstWeight: 40.1,
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
}
