<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour la logique de calcul des votes
 * Valide les algorithmes de comptage sans base de donnees
 */
class VoteLogicTest extends TestCase {
    // =========================================================================
    // MAJORITY CALCULATION TESTS
    // =========================================================================

    /**
     * Calcule le ratio de majorite
     */
    private function computeMajorityRatio(float $forWeight, float $baseTotal): float {
        if ($baseTotal <= 0) {
            return 0.0;
        }
        return $forWeight / $baseTotal;
    }

    /**
     * Determine si la resolution est adoptee
     */
    private function isAdopted(float $ratio, float $threshold, ?bool $quorumMet): bool {
        if ($quorumMet === false) {
            return false;
        }
        return $ratio >= $threshold;
    }

    public function testSimpleMajorityAdopted(): void {
        // 60 pour, 40 contre = 60% > 50%
        $forWeight = 60.0;
        $baseTotal = 100.0;
        $threshold = 0.5;

        $ratio = $this->computeMajorityRatio($forWeight, $baseTotal);
        $adopted = $this->isAdopted($ratio, $threshold, true);

        $this->assertEquals(0.6, $ratio);
        $this->assertTrue($adopted);
    }

    public function testSimpleMajorityRejected(): void {
        // 40 pour, 60 contre = 40% < 50%
        $forWeight = 40.0;
        $baseTotal = 100.0;
        $threshold = 0.5;

        $ratio = $this->computeMajorityRatio($forWeight, $baseTotal);
        $adopted = $this->isAdopted($ratio, $threshold, true);

        $this->assertEquals(0.4, $ratio);
        $this->assertFalse($adopted);
    }

    public function testExactlyMajority(): void {
        // 50 pour, 50 contre = 50% >= 50%
        $forWeight = 50.0;
        $baseTotal = 100.0;
        $threshold = 0.5;

        $ratio = $this->computeMajorityRatio($forWeight, $baseTotal);
        $adopted = $this->isAdopted($ratio, $threshold, true);

        $this->assertEquals(0.5, $ratio);
        $this->assertTrue($adopted); // >= threshold
    }

    public function testTwoThirdsMajorityAdopted(): void {
        // 70 pour = 70% >= 66.67%
        $forWeight = 70.0;
        $baseTotal = 100.0;
        $threshold = 2 / 3;

        $ratio = $this->computeMajorityRatio($forWeight, $baseTotal);
        $adopted = $this->isAdopted($ratio, $threshold, true);

        $this->assertGreaterThanOrEqual($threshold, $ratio);
        $this->assertTrue($adopted);
    }

    public function testTwoThirdsMajorityRejected(): void {
        // 65 pour = 65% < 66.67%
        $forWeight = 65.0;
        $baseTotal = 100.0;
        $threshold = 2 / 3;

        $ratio = $this->computeMajorityRatio($forWeight, $baseTotal);
        $adopted = $this->isAdopted($ratio, $threshold, true);

        $this->assertLessThan($threshold, $ratio);
        $this->assertFalse($adopted);
    }

    public function testUnanimityRequired(): void {
        // Unanimite = 100%
        $threshold = 1.0;

        // 100% pour
        $ratio1 = $this->computeMajorityRatio(100.0, 100.0);
        $this->assertTrue($this->isAdopted($ratio1, $threshold, true));

        // 99% pour
        $ratio2 = $this->computeMajorityRatio(99.0, 100.0);
        $this->assertFalse($this->isAdopted($ratio2, $threshold, true));
    }

    // =========================================================================
    // QUORUM EFFECT TESTS
    // =========================================================================

    public function testAdoptedWithQuorumMet(): void {
        $ratio = 0.6;
        $threshold = 0.5;

        // Quorum atteint = vote valide
        $this->assertTrue($this->isAdopted($ratio, $threshold, true));
    }

    public function testRejectedWhenQuorumNotMet(): void {
        $ratio = 0.9; // Meme avec 90% de pour
        $threshold = 0.5;

        // Quorum non atteint = automatiquement rejete
        $this->assertFalse($this->isAdopted($ratio, $threshold, false));
    }

    public function testAdoptedWhenNoQuorumPolicy(): void {
        $ratio = 0.6;
        $threshold = 0.5;

        // Pas de politique de quorum = quorum_met est null
        $this->assertTrue($this->isAdopted($ratio, $threshold, null));
    }

    // =========================================================================
    // ABSTENTION HANDLING TESTS
    // =========================================================================

    /**
     * Calcule le poids effectif contre avec abstentions
     */
    private function computeEffectiveAgainst(
        float $againstWeight,
        float $abstainWeight,
        bool $abstAsAgainst,
    ): float {
        return $againstWeight + ($abstAsAgainst ? $abstainWeight : 0.0);
    }

    public function testAbstentionNotCountedAsAgainst(): void {
        $against = 30.0;
        $abstain = 20.0;

        $effective = $this->computeEffectiveAgainst($against, $abstain, false);

        $this->assertEquals(30.0, $effective);
    }

    public function testAbstentionCountedAsAgainst(): void {
        $against = 30.0;
        $abstain = 20.0;

        $effective = $this->computeEffectiveAgainst($against, $abstain, true);

        $this->assertEquals(50.0, $effective);
    }

    // =========================================================================
    // TALLY AGGREGATION TESTS
    // =========================================================================

    public function testTallyAggregation(): void {
        $tallies = [
            'for' => ['count' => 10, 'weight' => 50.0],
            'against' => ['count' => 5, 'weight' => 30.0],
            'abstain' => ['count' => 3, 'weight' => 15.0],
            'nsp' => ['count' => 2, 'weight' => 5.0],
        ];

        $expressedCount = $tallies['for']['count']
            + $tallies['against']['count']
            + $tallies['abstain']['count'];

        $expressedWeight = $tallies['for']['weight']
            + $tallies['against']['weight']
            + $tallies['abstain']['weight'];

        $this->assertEquals(18, $expressedCount);
        $this->assertEquals(95.0, $expressedWeight);
    }

    public function testNspNotCountedInExpressed(): void {
        $tallies = [
            'for' => ['count' => 10, 'weight' => 50.0],
            'against' => ['count' => 5, 'weight' => 30.0],
            'abstain' => ['count' => 3, 'weight' => 15.0],
            'nsp' => ['count' => 100, 'weight' => 500.0], // Ne compte pas
        ];

        // NSP (Ne Se Prononce pas) n'est pas compte comme exprime
        $expressedCount = $tallies['for']['count']
            + $tallies['against']['count']
            + $tallies['abstain']['count'];

        $this->assertEquals(18, $expressedCount);
        $this->assertNotEquals(118, $expressedCount);
    }

    // =========================================================================
    // DECISION STATUS TESTS
    // =========================================================================

    /**
     * Determine le statut de decision
     */
    private function computeDecisionStatus(
        int $expressedMembers,
        int $nspCount,
        ?bool $quorumMet,
        ?bool $hasVotePolicy,
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

    public function testDecisionStatusNoVotes(): void {
        $status = $this->computeDecisionStatus(0, 0, true, true, null);
        $this->assertEquals('no_votes', $status);
    }

    public function testDecisionStatusNoQuorum(): void {
        $status = $this->computeDecisionStatus(10, 0, false, true, false);
        $this->assertEquals('no_quorum', $status);
    }

    public function testDecisionStatusAdopted(): void {
        $status = $this->computeDecisionStatus(10, 0, true, true, true);
        $this->assertEquals('adopted', $status);
    }

    public function testDecisionStatusRejected(): void {
        $status = $this->computeDecisionStatus(10, 0, true, true, false);
        $this->assertEquals('rejected', $status);
    }

    public function testDecisionStatusNoPolicy(): void {
        $status = $this->computeDecisionStatus(10, 0, null, false, null);
        $this->assertEquals('no_policy', $status);
    }

    // =========================================================================
    // WEIGHTED VOTING TESTS
    // =========================================================================

    public function testWeightedVotingWithDifferentWeights(): void {
        // Membre A: 10 voix, vote pour
        // Membre B: 5 voix, vote contre
        // Membre C: 5 voix, vote pour
        // Total pour: 15, Total contre: 5, Total: 20

        $forWeight = 10.0 + 5.0; // 15
        $againstWeight = 5.0;
        $totalWeight = 20.0;

        $ratio = $this->computeMajorityRatio($forWeight, $totalWeight);

        $this->assertEquals(0.75, $ratio); // 75% pour
        $this->assertTrue($this->isAdopted($ratio, 0.5, true));
    }

    public function testWeightedVotingMinorityWithMajorWeight(): void {
        // 2 membres votent pour avec 10 voix chacun = 20
        // 8 membres votent contre avec 1 voix chacun = 8
        // Total: 28 voix

        $forWeight = 20.0;
        $againstWeight = 8.0;
        $totalWeight = 28.0;

        $ratio = $this->computeMajorityRatio($forWeight, $totalWeight);

        $this->assertGreaterThan(0.7, $ratio); // >70% pour
        $this->assertTrue($this->isAdopted($ratio, 0.5, true));
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function testZeroBaseTotal(): void {
        $ratio = $this->computeMajorityRatio(10.0, 0.0);
        $this->assertEquals(0.0, $ratio);
    }

    public function testNegativeWeight(): void {
        // Les poids negatifs ne devraient pas exister mais le code doit etre robuste
        $ratio = $this->computeMajorityRatio(-5.0, 100.0);
        $this->assertLessThan(0, $ratio);
    }

    public function testVerySmallThreshold(): void {
        $forWeight = 0.001;
        $baseTotal = 100.0;
        $threshold = 0.00001;

        $ratio = $this->computeMajorityRatio($forWeight, $baseTotal);
        $adopted = $this->isAdopted($ratio, $threshold, true);

        $this->assertTrue($adopted);
    }
}
