<?php
declare(strict_types=1);
namespace AgVote\Tests\Unit;

use AgVote\Service\VoteEngine;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for weighted vote tally correctness.
 * Ensures voting_power weighting produces correct POUR/CONTRE tallies
 * regardless of vocabulary changes (CPR-05).
 */
class WeightedVoteRegressionTest extends TestCase
{
    // =========================================================================
    // WEIGHTED VOTE TALLY TESTS
    // =========================================================================

    public function testWeightedVoteTallyPourContre(): void
    {
        // Member A: voting_power=3, votes POUR
        // Member B: voting_power=1, votes CONTRE
        $result = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: ['base' => 'expressed', 'threshold' => 0.5],
            forWeight: 3.0,
            againstWeight: 1.0,
            abstainWeight: 0.0,
            expressedWeight: 4.0,
            expressedMembers: 2,
            eligibleMembers: 2,
            eligibleWeight: 4.0,
        );

        $this->assertTrue($result['majority']['met'], 'POUR:3 vs CONTRE:1 should adopt');
        $this->assertEqualsWithDelta(3.0 / 4.0, $result['majority']['ratio'], 0.0001);
    }

    public function testWeightedVoteTallyUnanimousPour(): void
    {
        // Both members vote POUR: weight 3 + weight 1 = 4
        $result = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: ['base' => 'expressed', 'threshold' => 0.5],
            forWeight: 4.0,
            againstWeight: 0.0,
            abstainWeight: 0.0,
            expressedWeight: 4.0,
            expressedMembers: 2,
            eligibleMembers: 2,
            eligibleWeight: 4.0,
        );

        $this->assertTrue($result['majority']['met']);
        $this->assertEqualsWithDelta(1.0, $result['majority']['ratio'], 0.0001);
    }

    public function testEqualWeightTieNotAdopted(): void
    {
        // Two members, equal weight 1 each, one POUR one CONTRE
        // threshold 0.501 = strict majority (>50%); ratio 0.5 < 0.501 => NOT adopted
        $result = VoteEngine::computeDecision(
            quorumPolicy: null,
            votePolicy: ['base' => 'expressed', 'threshold' => 0.501],
            forWeight: 1.0,
            againstWeight: 1.0,
            abstainWeight: 0.0,
            expressedWeight: 2.0,
            expressedMembers: 2,
            eligibleMembers: 2,
            eligibleWeight: 2.0,
        );

        $this->assertFalse($result['majority']['met'], 'Tie (50%) should NOT meet strict >50% threshold');
        $this->assertEqualsWithDelta(0.5, $result['majority']['ratio'], 0.0001);
    }
}
