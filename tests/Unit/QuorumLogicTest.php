<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour la logique de calcul du quorum
 * Valide les algorithmes de quorum sans base de donnees
 */
class QuorumLogicTest extends TestCase
{
    // =========================================================================
    // RATIO CALCULATION
    // =========================================================================

    /**
     * Calcule le ratio de quorum
     */
    private function computeQuorumRatio(
        string $basis,
        int $presentMembers,
        float $presentWeight,
        int $eligibleMembers,
        float $eligibleWeight
    ): float {
        if ($basis === 'eligible_members') {
            $denominator = max(1, $eligibleMembers);
            $numerator = (float)$presentMembers;
        } else {
            $denominator = $eligibleWeight > 0 ? $eligibleWeight : 0.0001;
            $numerator = $presentWeight;
        }

        return $denominator > 0 ? $numerator / $denominator : 0.0;
    }

    /**
     * Determine si le quorum est atteint
     */
    private function isQuorumMet(float $ratio, float $threshold): bool
    {
        return $ratio >= $threshold;
    }

    // =========================================================================
    // BASIC QUORUM TESTS
    // =========================================================================

    public function testQuorumMetByMemberCount(): void
    {
        // 60 presents sur 100 eligibles = 60% >= 50%
        $ratio = $this->computeQuorumRatio('eligible_members', 60, 0, 100, 0);

        $this->assertEquals(0.6, $ratio);
        $this->assertTrue($this->isQuorumMet($ratio, 0.5));
    }

    public function testQuorumNotMetByMemberCount(): void
    {
        // 40 presents sur 100 eligibles = 40% < 50%
        $ratio = $this->computeQuorumRatio('eligible_members', 40, 0, 100, 0);

        $this->assertEquals(0.4, $ratio);
        $this->assertFalse($this->isQuorumMet($ratio, 0.5));
    }

    public function testQuorumExactlyAtThreshold(): void
    {
        // 50 presents sur 100 eligibles = 50% >= 50%
        $ratio = $this->computeQuorumRatio('eligible_members', 50, 0, 100, 0);

        $this->assertEquals(0.5, $ratio);
        $this->assertTrue($this->isQuorumMet($ratio, 0.5));
    }

    // =========================================================================
    // WEIGHTED QUORUM TESTS
    // =========================================================================

    public function testQuorumMetByWeight(): void
    {
        // 600 voix presentes sur 1000 eligibles = 60% >= 50%
        $ratio = $this->computeQuorumRatio('eligible_weight', 0, 600.0, 0, 1000.0);

        $this->assertEquals(0.6, $ratio);
        $this->assertTrue($this->isQuorumMet($ratio, 0.5));
    }

    public function testQuorumNotMetByWeight(): void
    {
        // 400 voix presentes sur 1000 eligibles = 40% < 50%
        $ratio = $this->computeQuorumRatio('eligible_weight', 0, 400.0, 0, 1000.0);

        $this->assertEquals(0.4, $ratio);
        $this->assertFalse($this->isQuorumMet($ratio, 0.5));
    }

    public function testWeightedQuorumWithUnequalShares(): void
    {
        // Grands actionnaires presents
        // 3 membres presents avec 300 voix chacun = 900
        // Total eligibles = 1500 voix (mais 100 membres)
        $ratio = $this->computeQuorumRatio('eligible_weight', 3, 900.0, 100, 1500.0);

        $this->assertEquals(0.6, $ratio); // 60% des voix
        $this->assertTrue($this->isQuorumMet($ratio, 0.5));
    }

    // =========================================================================
    // THRESHOLD VARIATIONS
    // =========================================================================

    public function testThresholdOneThird(): void
    {
        $threshold = 1/3;

        // 35 sur 100 = 35% >= 33.33%
        $ratio = $this->computeQuorumRatio('eligible_members', 35, 0, 100, 0);
        $this->assertTrue($this->isQuorumMet($ratio, $threshold));

        // 30 sur 100 = 30% < 33.33%
        $ratio2 = $this->computeQuorumRatio('eligible_members', 30, 0, 100, 0);
        $this->assertFalse($this->isQuorumMet($ratio2, $threshold));
    }

    public function testThresholdTwoThirds(): void
    {
        $threshold = 2/3;

        // 70 sur 100 = 70% >= 66.67%
        $ratio = $this->computeQuorumRatio('eligible_members', 70, 0, 100, 0);
        $this->assertTrue($this->isQuorumMet($ratio, $threshold));

        // 65 sur 100 = 65% < 66.67%
        $ratio2 = $this->computeQuorumRatio('eligible_members', 65, 0, 100, 0);
        $this->assertFalse($this->isQuorumMet($ratio2, $threshold));
    }

    public function testThresholdMajority(): void
    {
        $threshold = 0.5;

        // Exactly half
        $ratio = $this->computeQuorumRatio('eligible_members', 50, 0, 100, 0);
        $this->assertTrue($this->isQuorumMet($ratio, $threshold));

        // One less than half
        $ratio2 = $this->computeQuorumRatio('eligible_members', 49, 0, 100, 0);
        $this->assertFalse($this->isQuorumMet($ratio2, $threshold));
    }

    // =========================================================================
    // DOUBLE QUORUM TESTS
    // =========================================================================

    /**
     * Verifie le double quorum
     */
    private function isDoubleQuorumMet(bool $primaryMet, bool $secondaryMet): bool
    {
        return $primaryMet && $secondaryMet;
    }

    public function testDoubleQuorumBothMet(): void
    {
        // Quorum 1: 60 membres sur 100 >= 50%
        $ratio1 = $this->computeQuorumRatio('eligible_members', 60, 0, 100, 0);
        $met1 = $this->isQuorumMet($ratio1, 0.5);

        // Quorum 2: 600 voix sur 1000 >= 50%
        $ratio2 = $this->computeQuorumRatio('eligible_weight', 0, 600.0, 0, 1000.0);
        $met2 = $this->isQuorumMet($ratio2, 0.5);

        $this->assertTrue($this->isDoubleQuorumMet($met1, $met2));
    }

    public function testDoubleQuorumFirstNotMet(): void
    {
        // Quorum 1: 40 membres sur 100 < 50%
        $ratio1 = $this->computeQuorumRatio('eligible_members', 40, 0, 100, 0);
        $met1 = $this->isQuorumMet($ratio1, 0.5);

        // Quorum 2: 600 voix sur 1000 >= 50%
        $ratio2 = $this->computeQuorumRatio('eligible_weight', 0, 600.0, 0, 1000.0);
        $met2 = $this->isQuorumMet($ratio2, 0.5);

        $this->assertFalse($this->isDoubleQuorumMet($met1, $met2));
    }

    public function testDoubleQuorumSecondNotMet(): void
    {
        // Quorum 1: 60 membres sur 100 >= 50%
        $ratio1 = $this->computeQuorumRatio('eligible_members', 60, 0, 100, 0);
        $met1 = $this->isQuorumMet($ratio1, 0.5);

        // Quorum 2: 400 voix sur 1000 < 50%
        $ratio2 = $this->computeQuorumRatio('eligible_weight', 0, 400.0, 0, 1000.0);
        $met2 = $this->isQuorumMet($ratio2, 0.5);

        $this->assertFalse($this->isDoubleQuorumMet($met1, $met2));
    }

    // =========================================================================
    // EVOLVING QUORUM TESTS
    // =========================================================================

    /**
     * Retourne le seuil selon le numero de convocation
     */
    private function getEvolvingThreshold(
        int $convocationNo,
        float $threshold1,
        ?float $threshold2
    ): float {
        if ($convocationNo === 2 && $threshold2 !== null) {
            return $threshold2;
        }
        return $threshold1;
    }

    public function testEvolvingQuorumFirstCall(): void
    {
        // Premiere convocation: seuil 50%
        $threshold = $this->getEvolvingThreshold(1, 0.5, 0.25);
        $this->assertEquals(0.5, $threshold);

        // 40 presents < 50%
        $ratio = $this->computeQuorumRatio('eligible_members', 40, 0, 100, 0);
        $this->assertFalse($this->isQuorumMet($ratio, $threshold));
    }

    public function testEvolvingQuorumSecondCall(): void
    {
        // Deuxieme convocation: seuil reduit a 25%
        $threshold = $this->getEvolvingThreshold(2, 0.5, 0.25);
        $this->assertEquals(0.25, $threshold);

        // 40 presents >= 25%
        $ratio = $this->computeQuorumRatio('eligible_members', 40, 0, 100, 0);
        $this->assertTrue($this->isQuorumMet($ratio, $threshold));
    }

    public function testEvolvingQuorumNoSecondThreshold(): void
    {
        // Si pas de seuil 2 configure, utiliser le seuil 1
        $threshold = $this->getEvolvingThreshold(2, 0.5, null);
        $this->assertEquals(0.5, $threshold);
    }

    // =========================================================================
    // ATTENDANCE MODE TESTS
    // =========================================================================

    /**
     * Compte les presents selon les modes autorises
     */
    private function countPresentByModes(array $attendance, array $allowedModes): int
    {
        return array_reduce($attendance, function($count, $a) use ($allowedModes) {
            return $count + (in_array($a['mode'], $allowedModes, true) ? 1 : 0);
        }, 0);
    }

    public function testCountOnlyPhysicallyPresent(): void
    {
        $attendance = [
            ['member_id' => 1, 'mode' => 'present'],
            ['member_id' => 2, 'mode' => 'present'],
            ['member_id' => 3, 'mode' => 'remote'],
            ['member_id' => 4, 'mode' => 'proxy'],
            ['member_id' => 5, 'mode' => 'absent'],
        ];

        $count = $this->countPresentByModes($attendance, ['present']);
        $this->assertEquals(2, $count);
    }

    public function testCountPresentAndRemote(): void
    {
        $attendance = [
            ['member_id' => 1, 'mode' => 'present'],
            ['member_id' => 2, 'mode' => 'present'],
            ['member_id' => 3, 'mode' => 'remote'],
            ['member_id' => 4, 'mode' => 'proxy'],
            ['member_id' => 5, 'mode' => 'absent'],
        ];

        $count = $this->countPresentByModes($attendance, ['present', 'remote']);
        $this->assertEquals(3, $count);
    }

    public function testCountAllParticipationModes(): void
    {
        $attendance = [
            ['member_id' => 1, 'mode' => 'present'],
            ['member_id' => 2, 'mode' => 'present'],
            ['member_id' => 3, 'mode' => 'remote'],
            ['member_id' => 4, 'mode' => 'proxy'],
            ['member_id' => 5, 'mode' => 'absent'],
        ];

        $count = $this->countPresentByModes($attendance, ['present', 'remote', 'proxy']);
        $this->assertEquals(4, $count);
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function testZeroEligibleMembers(): void
    {
        // Devrait eviter division par zero
        $ratio = $this->computeQuorumRatio('eligible_members', 0, 0, 0, 0);
        $this->assertEquals(0.0, $ratio);
    }

    public function testZeroEligibleWeight(): void
    {
        // Devrait utiliser un petit denominateur pour eviter division par zero
        $ratio = $this->computeQuorumRatio('eligible_weight', 0, 100.0, 0, 0.0);
        $this->assertGreaterThan(0, $ratio);
    }

    public function testMorePresentThanEligible(): void
    {
        // Cas anormal mais code doit etre robuste
        $ratio = $this->computeQuorumRatio('eligible_members', 150, 0, 100, 0);
        $this->assertEquals(1.5, $ratio); // 150%
        $this->assertTrue($this->isQuorumMet($ratio, 0.5));
    }

    public function testVeryLowThreshold(): void
    {
        // Seuil tres bas (0.01 = 1%)
        $ratio = $this->computeQuorumRatio('eligible_members', 2, 0, 100, 0);
        $this->assertTrue($this->isQuorumMet($ratio, 0.01));
    }

    public function testVeryHighThreshold(): void
    {
        // Seuil tres haut (0.99 = 99%)
        $ratio = $this->computeQuorumRatio('eligible_members', 98, 0, 100, 0);
        $this->assertFalse($this->isQuorumMet($ratio, 0.99));

        $ratio2 = $this->computeQuorumRatio('eligible_members', 99, 0, 100, 0);
        $this->assertTrue($this->isQuorumMet($ratio2, 0.99));
    }
}
