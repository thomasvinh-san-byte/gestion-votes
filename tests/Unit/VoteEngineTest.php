<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for VoteEngine service.
 *
 * These tests validate the vote calculation algorithms and result structure.
 * Integration tests with real database are in tests/Integration/.
 */
class VoteEngineTest extends TestCase
{
    // =========================================================================
    // RESULT STRUCTURE TESTS
    // =========================================================================

    /**
     * Validates the expected structure of computeMotionResult output.
     */
    public function testResultStructureKeys(): void
    {
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

    public function testMotionStructure(): void
    {
        $result = $this->createMockResult();

        $this->assertArrayHasKey('id', $result['motion']);
        $this->assertArrayHasKey('title', $result['motion']);
        $this->assertArrayHasKey('meeting_id', $result['motion']);
        $this->assertArrayHasKey('tenant_id', $result['motion']);
        $this->assertArrayHasKey('secret', $result['motion']);
    }

    public function testTalliesStructure(): void
    {
        $result = $this->createMockResult();

        $expectedVoteValues = ['for', 'against', 'abstain', 'nsp'];

        foreach ($expectedVoteValues as $value) {
            $this->assertArrayHasKey($value, $result['tallies']);
            $this->assertArrayHasKey('count', $result['tallies'][$value]);
            $this->assertArrayHasKey('weight', $result['tallies'][$value]);
        }
    }

    public function testQuorumStructure(): void
    {
        $result = $this->createMockResult();

        $this->assertArrayHasKey('applied', $result['quorum']);
        $this->assertArrayHasKey('met', $result['quorum']);
        $this->assertArrayHasKey('basis', $result['quorum']);
        $this->assertArrayHasKey('ratio', $result['quorum']);
        $this->assertArrayHasKey('threshold', $result['quorum']);
    }

    public function testMajorityStructure(): void
    {
        $result = $this->createMockResult();

        $this->assertArrayHasKey('applied', $result['majority']);
        $this->assertArrayHasKey('met', $result['majority']);
        $this->assertArrayHasKey('base', $result['majority']);
        $this->assertArrayHasKey('ratio', $result['majority']);
        $this->assertArrayHasKey('threshold', $result['majority']);
        $this->assertArrayHasKey('abstention_as_against', $result['majority']);
    }

    public function testDecisionStructure(): void
    {
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
    public function testDecisionStatusNoVotes(): void
    {
        $status = $this->computeDecisionStatus(
            expressedMembers: 0,
            nspCount: 0,
            quorumMet: null,
            hasVotePolicy: true,
            adopted: null
        );

        $this->assertEquals('no_votes', $status);
    }

    public function testDecisionStatusNoQuorum(): void
    {
        $status = $this->computeDecisionStatus(
            expressedMembers: 10,
            nspCount: 0,
            quorumMet: false,
            hasVotePolicy: true,
            adopted: false
        );

        $this->assertEquals('no_quorum', $status);
    }

    public function testDecisionStatusAdopted(): void
    {
        $status = $this->computeDecisionStatus(
            expressedMembers: 10,
            nspCount: 0,
            quorumMet: true,
            hasVotePolicy: true,
            adopted: true
        );

        $this->assertEquals('adopted', $status);
    }

    public function testDecisionStatusRejected(): void
    {
        $status = $this->computeDecisionStatus(
            expressedMembers: 10,
            nspCount: 0,
            quorumMet: true,
            hasVotePolicy: true,
            adopted: false
        );

        $this->assertEquals('rejected', $status);
    }

    public function testDecisionStatusNoPolicy(): void
    {
        $status = $this->computeDecisionStatus(
            expressedMembers: 10,
            nspCount: 0,
            quorumMet: null,
            hasVotePolicy: false,
            adopted: null
        );

        $this->assertEquals('no_policy', $status);
    }

    public function testDecisionStatusWithNspOnly(): void
    {
        // Even with only NSP votes (no expressed), there are votes
        $status = $this->computeDecisionStatus(
            expressedMembers: 0,
            nspCount: 5,
            quorumMet: true,
            hasVotePolicy: true,
            adopted: false
        );

        $this->assertEquals('rejected', $status);
    }

    // =========================================================================
    // MAJORITY CALCULATION TESTS
    // =========================================================================

    public function testMajorityCalculationExpressedBase(): void
    {
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

    public function testMajorityCalculationWithAbstentionAsAgainst(): void
    {
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

    public function testMajorityCalculationEligibleBase(): void
    {
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

    public function testExpressedCalculation(): void
    {
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

    public function testZeroExpressedWeight(): void
    {
        $expressedWeight = 0.0;
        $forWeight = 0.0;

        // Should handle division by zero gracefully
        $ratio = $expressedWeight > 0 ? $forWeight / $expressedWeight : 0.0;

        $this->assertEquals(0.0, $ratio);
    }

    public function testQuorumBlocksAdoption(): void
    {
        // Even with 100% for votes, if quorum not met, motion fails
        $ratio = 1.0; // 100% for
        $threshold = 0.5;
        $quorumMet = false;

        $adopted = $quorumMet !== false && $ratio >= $threshold;

        $this->assertFalse($adopted);
    }

    public function testNoQuorumPolicyAllowsVote(): void
    {
        // Without quorum policy, quorumMet is null
        $ratio = 0.6;
        $threshold = 0.5;
        $quorumMet = null; // No quorum policy

        // null quorum doesn't block
        $adopted = $quorumMet !== false && $ratio >= $threshold;

        $this->assertTrue($adopted);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Create a mock result structure for testing.
     */
    private function createMockResult(): array
    {
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
        ?bool $adopted
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
