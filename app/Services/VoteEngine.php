<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\BallotRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\PolicyRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * VoteEngine - Computes motion voting results.
 *
 * This service handles the calculation of vote results for motions,
 * including tallying ballots, applying quorum policies, and determining
 * majority outcomes.
 *
 * Features:
 * - Ballot aggregation by vote value (for, against, abstain, nsp)
 * - Weight-based voting support
 * - Quorum verification against configured policies
 * - Majority calculation with configurable thresholds
 * - Abstention-as-against option support
 *
 * @package AgVote\Service
 */
final class VoteEngine
{
    /**
     * Computes motion results from ballots and policies.
     *
     * @param string $motionId
     * @return array<string,mixed>
     */
    public static function computeMotionResult(string $motionId): array
    {
        $motionId = trim($motionId);
        if ($motionId === '') {
            throw new InvalidArgumentException('motion_id obligatoire');
        }

        $motionRepo = new MotionRepository();
        $motion = $motionRepo->findWithVoteContext($motionId);

        if (!$motion) {
            throw new RuntimeException('Motion introuvable');
        }

        $tenantId   = (string)$motion['tenant_id'];
        $meetingId  = (string)$motion['meeting_id'];

        // Aggregate ballots by value
        $ballotRepo = new BallotRepository();
        $t = $ballotRepo->tally($motionId);

        $tallies = [
            'for'     => ['count' => (int)$t['count_for'],     'weight' => (float)$t['weight_for']],
            'against' => ['count' => (int)$t['count_against'],  'weight' => (float)$t['weight_against']],
            'abstain' => ['count' => (int)$t['count_abstain'],  'weight' => (float)$t['weight_abstain']],
            'nsp'     => ['count' => (int)$t['count_nsp'],      'weight' => 0.0],
        ];

        $expressedMembers = $tallies['for']['count']
            + $tallies['against']['count']
            + $tallies['abstain']['count'];

        $expressedWeight = $tallies['for']['weight']
            + $tallies['against']['weight']
            + $tallies['abstain']['weight'];

        // Eligible voters (by tenant)
        $memberRepo = new MemberRepository();
        $eligibleMembers = $memberRepo->countActive($tenantId);
        $eligibleWeight  = $memberRepo->sumActiveWeight($tenantId);

        // Load policies if any
        $policyRepo = new PolicyRepository();

        $quorumPolicy = null;
        if (!empty($motion['quorum_policy_id'])) {
            $quorumPolicy = $policyRepo->findQuorumPolicy((string)$motion['quorum_policy_id']);
        }

        // Resolve vote policy: motion-level > meeting-level
        $appliedVotePolicyId = !empty($motion['vote_policy_id'])
            ? (string)$motion['vote_policy_id']
            : (!empty($motion['meeting_vote_policy_id']) ? (string)$motion['meeting_vote_policy_id'] : '');

        $votePolicy = null;
        if ($appliedVotePolicyId !== '') {
            $votePolicy = $policyRepo->findVotePolicy($appliedVotePolicyId);
        }

        // Quorum calculation
        $quorumMet         = null;
        $quorumRatio       = null;
        $quorumThreshold   = null;
        $quorumDenominator = null;
        $quorumBasis       = null;

        if ($quorumPolicy) {
            $quorumBasis     = (string)$quorumPolicy['denominator'];
            $quorumThreshold = (float)$quorumPolicy['threshold'];

            if ($quorumBasis === 'eligible_members') {
                $denominator = max(1, $eligibleMembers);
                $numerator   = $expressedMembers;
            } else {
                $denominator = $eligibleWeight;
                $numerator   = $expressedWeight;
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
        $adopted           = null;
        $majorityRatio     = null;
        $majorityThreshold = null;
        $majorityBase      = null;
        $abstAsAgainst     = null;

        if ($votePolicy) {
            $majorityBase      = (string)$votePolicy['base'];
            $majorityThreshold = (float)$votePolicy['threshold'];
            $abstAsAgainst     = (bool)$votePolicy['abstention_as_against'];

            if ($majorityBase === 'expressed') {
                $baseTotal = $expressedWeight;
            } elseif ($majorityBase === 'eligible') {
                $baseTotal = $eligibleWeight;
            } elseif ($majorityBase === 'present') {
                $baseTotal = $expressedWeight;
            } else {
                $baseTotal = $expressedWeight;
            }

            $forWeight     = $tallies['for']['weight'];
            $againstWeight = $tallies['against']['weight'];
            $abstainWeight = $tallies['abstain']['weight'];

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
                'id'               => $motion['motion_id'],
                'title'            => $motion['motion_title'],
                'meeting_id'       => $meetingId,
                'tenant_id'        => $tenantId,
                'vote_policy_id'   => $appliedVotePolicyId,
                'quorum_policy_id' => $motion['quorum_policy_id'],
                'secret'           => (bool)$motion['secret'],
            ],
            'tallies' => $tallies,
            'eligible' => [
                'members' => $eligibleMembers,
                'weight'  => $eligibleWeight,
            ],
            'expressed' => [
                'members' => $expressedMembers,
                'weight'  => $expressedWeight,
            ],
            'quorum' => [
                'applied'     => (bool)$quorumPolicy,
                'met'         => $quorumMet,
                'basis'       => $quorumBasis,
                'ratio'       => $quorumRatio,
                'threshold'   => $quorumThreshold,
                'denominator' => $quorumDenominator,
            ],
            'majority' => [
                'applied'               => (bool)$votePolicy,
                'met'                   => $adopted,
                'base'                  => $majorityBase,
                'ratio'                 => $majorityRatio,
                'threshold'             => $majorityThreshold,
                'abstention_as_against' => $abstAsAgainst,
            ],
            'decision' => [
                'status' => $decisionStatus,
                'reason' => $decisionReason,
            ],
        ];
    }
}
