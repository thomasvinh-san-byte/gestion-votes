<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Core\Providers\RepositoryFactory;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Repository\SettingsRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * VoteEngine - Computes motion voting results.
 *
 * Single source of truth for quorum/majority calculation.
 */
final class VoteEngine {
    private MotionRepository $motionRepo;
    private BallotRepository $ballotRepo;
    private MemberRepository $memberRepo;
    private PolicyRepository $policyRepo;
    private AttendanceRepository $attendanceRepo;
    private SettingsRepository $settingsRepo;

    public function __construct(
        ?MotionRepository $motionRepo = null,
        ?BallotRepository $ballotRepo = null,
        ?MemberRepository $memberRepo = null,
        ?PolicyRepository $policyRepo = null,
        ?AttendanceRepository $attendanceRepo = null,
        ?SettingsRepository $settingsRepo = null,
    ) {
        $this->motionRepo = $motionRepo ?? RepositoryFactory::getInstance()->motion();
        $this->ballotRepo = $ballotRepo ?? RepositoryFactory::getInstance()->ballot();
        $this->memberRepo = $memberRepo ?? RepositoryFactory::getInstance()->member();
        $this->policyRepo = $policyRepo ?? RepositoryFactory::getInstance()->policy();
        $this->attendanceRepo = $attendanceRepo ?? RepositoryFactory::getInstance()->attendance();
        $this->settingsRepo = $settingsRepo ?? RepositoryFactory::getInstance()->settings();
    }

    /**
     * Synthesize a minimal vote policy from settMajority tenant setting.
     *
     * Returns null when the setting is absent or unrecognized, so the caller
     * can fall back to the no-policy behavior.
     *
     * @param string $tenantId Tenant UUID
     *
     * @return array|null Synthetic vote policy or null if no valid setting exists
     */
    private function resolveFallbackVotePolicy(string $tenantId): ?array {
        $rawMajority = $this->settingsRepo->get($tenantId, 'settMajority');
        if ($rawMajority === null || $rawMajority === '') {
            return null;
        }
        $threshold = match ($rawMajority) {
            'simple', 'absolute' => 0.5,
            'two_thirds'         => 2.0 / 3.0,
            'three_quarters'     => 0.75,
            default              => null,
        };
        if ($threshold === null) {
            return null;
        }
        return [
            'name'                  => 'Réglages tenant',
            'base'                  => 'expressed',
            'threshold'             => $threshold,
            'abstention_as_against' => false,
        ];
    }

    /**
     * Pure quorum/majority calculation. No I/O — takes all inputs as parameters.
     * Used by both computeMotionResult() and OfficialResultsService::decideWithPolicies().
     *
     * @return array{quorum: array, majority: array}
     */
    public static function computeDecision(
        ?array $quorumPolicy,
        ?array $votePolicy,
        float $forWeight,
        float $againstWeight,
        float $abstainWeight,
        float $expressedWeight,
        int $expressedMembers,
        int $eligibleMembers,
        float $eligibleWeight,
        ?float $presentWeight = null,
    ): array {
        // Quorum calculation
        $quorumMet = null;
        $quorumRatio = null;
        $quorumThreshold = null;
        $quorumDenominator = null;
        $quorumBasis = null;

        if ($quorumPolicy) {
            $quorumBasis = (string) $quorumPolicy['denominator'];
            $quorumThreshold = (float) $quorumPolicy['threshold'];

            if ($quorumBasis === 'eligible_members') {
                $denominator = $eligibleMembers;
                $numerator = $expressedMembers;
            } else {
                $denominator = $eligibleWeight;
                $numerator = $expressedWeight;
            }

            if ($denominator <= 0) {
                $quorumMet = false;
                $quorumRatio = 0.0;
            } else {
                $quorumRatio = $numerator / $denominator;
                $quorumMet = $quorumRatio >= $quorumThreshold;
            }
            $quorumDenominator = $denominator;
        }

        // Majority calculation
        $adopted = null;
        $majorityRatio = null;
        $majorityThreshold = null;
        $majorityBase = null;
        $abstAsAgainst = null;

        if ($votePolicy) {
            $majorityBase = (string) $votePolicy['base'];
            $majorityThreshold = (float) $votePolicy['threshold'];
            $abstAsAgainst = (bool) ($votePolicy['abstention_as_against'] ?? false);

            if ($majorityBase === 'expressed') {
                // "Suffrages exprimés" (French law): for + against only.
                // When abstention_as_against is true, abstentions count as
                // "against" so they re-enter the denominator.
                $baseTotal = $abstAsAgainst
                    ? ($forWeight + $againstWeight + $abstainWeight)
                    : ($forWeight + $againstWeight);
            } elseif ($majorityBase === 'eligible') {
                $baseTotal = $eligibleWeight;
            } elseif ($majorityBase === 'present') {
                $baseTotal = $presentWeight ?? $expressedWeight;
            } else {
                $baseTotal = $expressedWeight;
            }

            if ($baseTotal <= 0.0 || $expressedWeight <= 0.0) {
                $majorityRatio = 0.0;
                $adopted = false;
            } else {
                $majorityRatio = $forWeight / $baseTotal;
                $adopted = $majorityRatio >= $majorityThreshold;
                if ($quorumMet === false) {
                    $adopted = false;
                }
            }
        }

        // Tie detection: ratio equals threshold within floating-point epsilon and at least one vote was cast
        $tie = (
            $majorityThreshold !== null
            && $majorityThreshold > 0
            && $majorityRatio !== null
            && abs($majorityRatio - $majorityThreshold) < 0.0001
            && $forWeight > 0
        );

        return [
            'quorum' => [
                'applied' => (bool) $quorumPolicy,
                'met' => $quorumMet,
                'basis' => $quorumBasis,
                'ratio' => $quorumRatio,
                'threshold' => $quorumThreshold,
                'denominator' => $quorumDenominator,
            ],
            'majority' => [
                'applied' => (bool) $votePolicy,
                'met' => $adopted,
                'base' => $majorityBase,
                'ratio' => $majorityRatio,
                'threshold' => $majorityThreshold,
                'abstention_as_against' => $abstAsAgainst,
                'tie' => $tie,
            ],
        ];
    }

    /**
     * Computes motion results from ballots and policies.
     *
     * @param string $motionId
     *
     * @return array<string,mixed>
     */
    public function computeMotionResult(string $motionId, string $tenantId = ''): array {
        $motionId = trim($motionId);
        if ($motionId === '') {
            throw new InvalidArgumentException('motion_id obligatoire');
        }

        $motion = $this->motionRepo->findWithVoteContext($motionId, $tenantId);

        if (!$motion) {
            throw new RuntimeException('Motion introuvable');
        }

        $tenantId = (string) $motion['tenant_id'];
        $meetingId = (string) $motion['meeting_id'];

        // Aggregate ballots by value
        $t = $this->ballotRepo->tally($motionId, $tenantId);

        $tallies = [
            'for' => ['count' => (int) $t['count_for'],     'weight' => (float) $t['weight_for']],
            'against' => ['count' => (int) $t['count_against'],  'weight' => (float) $t['weight_against']],
            'abstain' => ['count' => (int) $t['count_abstain'],  'weight' => (float) $t['weight_abstain']],
            'nsp' => ['count' => (int) $t['count_nsp'],      'weight' => 0.0],
        ];

        $expressedMembers = $tallies['for']['count']
            + $tallies['against']['count']
            + $tallies['abstain']['count'];

        $expressedWeight = $tallies['for']['weight']
            + $tallies['against']['weight']
            + $tallies['abstain']['weight'];

        // Eligible voters (by tenant)
        $eligibleMembers = $this->memberRepo->countActive($tenantId);
        $eligibleWeight = $this->memberRepo->sumActiveWeight($tenantId);

        // Resolve quorum policy: motion-level > meeting-level
        // (same fallback logic as OfficialResultsService)
        $appliedQuorumPolicyId = !empty($motion['quorum_policy_id'])
            ? (string) $motion['quorum_policy_id']
            : (!empty($motion['meeting_quorum_policy_id']) ? (string) $motion['meeting_quorum_policy_id'] : '');

        $quorumPolicy = null;
        if ($appliedQuorumPolicyId !== '') {
            $quorumPolicy = $this->policyRepo->findQuorumPolicy($appliedQuorumPolicyId, $tenantId);
        }

        // Resolve vote policy: motion-level > meeting-level
        $appliedVotePolicyId = !empty($motion['vote_policy_id'])
            ? (string) $motion['vote_policy_id']
            : (!empty($motion['meeting_vote_policy_id']) ? (string) $motion['meeting_vote_policy_id'] : '');

        $votePolicy = null;
        if ($appliedVotePolicyId !== '') {
            $votePolicy = $this->policyRepo->findVotePolicy($appliedVotePolicyId, $tenantId);
        }

        // Fallback: when no explicit vote policy, synthesize one from tenant settings
        if ($votePolicy === null) {
            $votePolicy = $this->resolveFallbackVotePolicy($tenantId);
        }

        // Override motion secrecy from settVoteMode tenant setting
        $motionSecret = (bool) ($motion['secret'] ?? false);
        $settVoteMode = $this->settingsRepo->get($tenantId, 'settVoteMode');
        if ($settVoteMode === 'secret') {
            $motionSecret = true;
        } elseif ($settVoteMode === 'public') {
            $motionSecret = false;
        }

        // Resolve present weight for 'present' majority base
        $presentWeight = null;
        if ($votePolicy && ($votePolicy['base'] ?? '') === 'present') {
            $presentWeight = $this->attendanceRepo->sumPresentWeight($meetingId, $tenantId, ['present', 'remote']);
        }

        // Compute decision using the shared engine
        $calc = self::computeDecision(
            $quorumPolicy,
            $votePolicy,
            $tallies['for']['weight'],
            $tallies['against']['weight'],
            $tallies['abstain']['weight'],
            $expressedWeight,
            $expressedMembers,
            $eligibleMembers,
            $eligibleWeight,
            $presentWeight,
        );

        $quorumMet = $calc['quorum']['met'];
        $adopted = $calc['majority']['met'];

        // Human-readable global status
        $decisionStatus = 'no_votes';
        $decisionReason = null;

        $hasVotes = ($expressedMembers + $tallies['nsp']['count']) > 0;

        if (!$hasVotes) {
            $decisionStatus = 'no_votes';
            $decisionReason = 'Aucun bulletin enregistré pour cette motion.';
        } elseif ($quorumPolicy && $quorumMet === false) {
            $decisionStatus = 'no_quorum';
            $decisionReason = 'Quorum non atteint.';
        } elseif ($votePolicy && $adopted === true) {
            $decisionStatus = 'adopted';
            $decisionReason = 'Seuil de majorité atteint.';
        } elseif ($votePolicy && $adopted === false) {
            $decisionStatus = 'rejected';
            $decisionReason = 'Seuil de majorité non atteint.';
        } else {
            $decisionStatus = 'no_policy';
            $decisionReason = 'Aucune politique de vote définie pour cette motion.';
        }

        return [
            'motion' => [
                'id' => $motion['motion_id'],
                'title' => $motion['motion_title'],
                'meeting_id' => $meetingId,
                'tenant_id' => $tenantId,
                'vote_policy_id' => $appliedVotePolicyId,
                'quorum_policy_id' => $motion['quorum_policy_id'],
                'secret' => $motionSecret,
            ],
            'tallies' => $tallies,
            'eligible' => [
                'members' => $eligibleMembers,
                'weight' => $eligibleWeight,
            ],
            'expressed' => [
                'members' => $expressedMembers,
                'weight' => $expressedWeight,
            ],
            'quorum' => $calc['quorum'],
            'majority' => $calc['majority'],
            'decision' => [
                'status' => $decisionStatus,
                'reason' => $decisionReason,
            ],
        ];
    }
}
