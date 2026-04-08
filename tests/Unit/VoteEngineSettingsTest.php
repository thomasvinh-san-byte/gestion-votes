<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Repository\SettingsRepository;
use AgVote\Service\VoteEngine;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Proves that settMajority and settVoteMode in tenant_settings
 * flow into VoteEngine calculations when no explicit policy exists.
 *
 * Each test changes the setting value and asserts the calculation changes.
 * DB → calc flow is verified without a real database connection.
 */
class VoteEngineSettingsTest extends TestCase {
    private const TENANT_ID = 'tenant-1';
    private const MOTION_ID = 'motion-1';
    private const MEETING_ID = 'meeting-1';

    private MotionRepository&MockObject $motionRepo;
    private BallotRepository&MockObject $ballotRepo;
    private MemberRepository&MockObject $memberRepo;
    private PolicyRepository&MockObject $policyRepo;
    private AttendanceRepository&MockObject $attendanceRepo;
    private SettingsRepository&MockObject $settingsRepo;

    protected function setUp(): void {
        $this->motionRepo = $this->createMock(MotionRepository::class);
        $this->ballotRepo = $this->createMock(BallotRepository::class);
        $this->memberRepo = $this->createMock(MemberRepository::class);
        $this->policyRepo = $this->createMock(PolicyRepository::class);
        $this->attendanceRepo = $this->createMock(AttendanceRepository::class);
        $this->settingsRepo = $this->createMock(SettingsRepository::class);

        // Motion fixture: no explicit policy IDs, secret=false by DB value
        $this->motionRepo->method('findWithVoteContext')->willReturn([
            'motion_id' => self::MOTION_ID,
            'motion_title' => 'Test Motion',
            'meeting_id' => self::MEETING_ID,
            'tenant_id' => self::TENANT_ID,
            'vote_policy_id' => null,
            'meeting_vote_policy_id' => null,
            'quorum_policy_id' => null,
            'meeting_quorum_policy_id' => null,
            'secret' => false,
        ]);

        // Ballot tally: 4 for, 2 against, 0 abstain (ratio 4/6 ≈ 0.6667)
        $this->ballotRepo->method('tally')->willReturn([
            'count_for' => 4, 'weight_for' => 4.0,
            'count_against' => 2, 'weight_against' => 2.0,
            'count_abstain' => 0, 'weight_abstain' => 0.0,
            'count_nsp' => 0,
        ]);

        // Members: 10 eligible
        $this->memberRepo->method('countActive')->willReturn(10);
        $this->memberRepo->method('sumActiveWeight')->willReturn(10.0);

        // Default: no explicit vote or quorum policies
        $this->policyRepo->method('findVotePolicy')->willReturn(null);
        $this->policyRepo->method('findQuorumPolicy')->willReturn(null);
    }

    private function makeEngine(): VoteEngine {
        return new VoteEngine(
            $this->motionRepo,
            $this->ballotRepo,
            $this->memberRepo,
            $this->policyRepo,
            $this->attendanceRepo,
            $this->settingsRepo,
        );
    }

    /**
     * Test 1: settMajority = 'two_thirds' → threshold ≈ 0.6667, adopted=true (4/6 ≈ 0.6667 >= 0.6667).
     */
    public function testSettMajorityTwoThirdsAdopted(): void {
        $this->settingsRepo->method('get')->willReturnMap([
            [self::TENANT_ID, 'settMajority', 'two_thirds'],
            [self::TENANT_ID, 'settVoteMode', null],
        ]);

        $engine = $this->makeEngine();
        $result = $engine->computeMotionResult(self::MOTION_ID, self::TENANT_ID);

        $this->assertTrue($result['majority']['applied'], 'Majority should be applied via settings fallback');
        $this->assertEqualsWithDelta(2.0 / 3.0, $result['majority']['threshold'], 0.001, 'Threshold should be ~0.6667');
        $this->assertTrue($result['majority']['met'], '4/6 = 0.6667 should meet two_thirds threshold');
        $this->assertEquals('adopted', $result['decision']['status']);
    }

    /**
     * Test 2: settMajority = 'three_quarters' → threshold 0.75, adopted=false (4/6 ≈ 0.6667 < 0.75).
     */
    public function testSettMajorityThreeQuartersNotAdopted(): void {
        $this->settingsRepo->method('get')->willReturnMap([
            [self::TENANT_ID, 'settMajority', 'three_quarters'],
            [self::TENANT_ID, 'settVoteMode', null],
        ]);

        $engine = $this->makeEngine();
        $result = $engine->computeMotionResult(self::MOTION_ID, self::TENANT_ID);

        $this->assertTrue($result['majority']['applied']);
        $this->assertEqualsWithDelta(0.75, $result['majority']['threshold'], 0.001, 'Threshold should be 0.75');
        $this->assertFalse($result['majority']['met'], '4/6 = 0.6667 should not meet three_quarters threshold');
        $this->assertEquals('rejected', $result['decision']['status']);
    }

    /**
     * Test 3: settMajority = 'simple' → threshold = 0.5.
     */
    public function testSettMajoritySimpleThreshold(): void {
        $this->settingsRepo->method('get')->willReturnMap([
            [self::TENANT_ID, 'settMajority', 'simple'],
            [self::TENANT_ID, 'settVoteMode', null],
        ]);

        $engine = $this->makeEngine();
        $result = $engine->computeMotionResult(self::MOTION_ID, self::TENANT_ID);

        $this->assertTrue($result['majority']['applied']);
        $this->assertEqualsWithDelta(0.5, $result['majority']['threshold'], 0.001, 'Threshold should be 0.5 for simple majority');
        $this->assertTrue($result['majority']['met'], '4/6 > 0.5 should be adopted');
    }

    /**
     * Test 4a: settVoteMode = 'secret' + no explicit policy → motion.secret === true.
     * Test 4b: settVoteMode = 'public' + no explicit policy → motion.secret === false.
     */
    public function testSettVoteModeSecretOverridesMotionDbValue(): void {
        $this->settingsRepo->method('get')->willReturnMap([
            [self::TENANT_ID, 'settMajority', 'simple'],
            [self::TENANT_ID, 'settVoteMode', 'secret'],
        ]);

        $engine = $this->makeEngine();
        $result = $engine->computeMotionResult(self::MOTION_ID, self::TENANT_ID);

        $this->assertTrue($result['motion']['secret'], 'settVoteMode=secret should set motion.secret=true');
    }

    public function testSettVoteModePublicOverridesMotionDbValue(): void {
        // Motion DB value is false; setting says public → should remain false
        $this->settingsRepo->method('get')->willReturnMap([
            [self::TENANT_ID, 'settMajority', 'simple'],
            [self::TENANT_ID, 'settVoteMode', 'public'],
        ]);

        $engine = $this->makeEngine();
        $result = $engine->computeMotionResult(self::MOTION_ID, self::TENANT_ID);

        $this->assertFalse($result['motion']['secret'], 'settVoteMode=public should set motion.secret=false');
    }

    /**
     * Test 5 (regression guard): Explicit motion-level vote_policy_id is set → policy wins, settings ignored.
     */
    public function testExplicitPolicyWinsOverSettings(): void {
        // Motion has explicit vote_policy_id
        $this->motionRepo = $this->createMock(MotionRepository::class);
        $this->motionRepo->method('findWithVoteContext')->willReturn([
            'motion_id' => self::MOTION_ID,
            'motion_title' => 'Test Motion',
            'meeting_id' => self::MEETING_ID,
            'tenant_id' => self::TENANT_ID,
            'vote_policy_id' => 'policy-explicit-001',
            'meeting_vote_policy_id' => null,
            'quorum_policy_id' => null,
            'meeting_quorum_policy_id' => null,
            'secret' => false,
        ]);

        // Explicit policy returns threshold of 0.5 (simple majority)
        $this->policyRepo = $this->createMock(PolicyRepository::class);
        $this->policyRepo->method('findVotePolicy')->willReturn([
            'id' => 'policy-explicit-001',
            'name' => 'Explicit Policy',
            'base' => 'expressed',
            'threshold' => 0.5,
            'abstention_as_against' => false,
        ]);
        $this->policyRepo->method('findQuorumPolicy')->willReturn(null);

        // Setting says three_quarters — should be IGNORED
        $this->settingsRepo->method('get')->willReturnMap([
            [self::TENANT_ID, 'settMajority', 'three_quarters'],
            [self::TENANT_ID, 'settVoteMode', 'secret'],
        ]);

        $engine = new VoteEngine(
            $this->motionRepo,
            $this->ballotRepo,
            $this->memberRepo,
            $this->policyRepo,
            $this->attendanceRepo,
            $this->settingsRepo,
        );

        $result = $engine->computeMotionResult(self::MOTION_ID, self::TENANT_ID);

        // Threshold should come from explicit policy (0.5), NOT from setting (0.75)
        $this->assertEqualsWithDelta(0.5, $result['majority']['threshold'], 0.001, 'Threshold should be from explicit policy, not setting');
        // settVoteMode=secret should NOT override when explicit policy is set
        // (the secret override is independent of policy — it's from the motion row + settings)
        // Actually per the plan, settVoteMode overrides regardless of policy. But regression guard
        // here is specifically about the majority threshold coming from policy, not settings.
        $this->assertTrue($result['majority']['met'], '4/6 > 0.5 should be adopted with explicit 0.5 threshold');
    }
}
