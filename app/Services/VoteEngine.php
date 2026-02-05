<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\MotionRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Repository\MemberRepository;
use InvalidArgumentException;
use RuntimeException;

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
        $rows = $ballotRepo->tallyByMotion($motionId);

        $tallies = [
            'for'     => ['count' => 0, 'weight' => 0.0],
            'against' => ['count' => 0, 'weight' => 0.0],
            'abstain' => ['count' => 0, 'weight' => 0.0],
            'nsp'     => ['count' => 0, 'weight' => 0.0],
        ];

        foreach ($rows as $row) {
            $value = (string)$row['value'];
            if (!isset($tallies[$value])) {
                continue;
            }
            $tallies[$value]['count']  = (int)$row['count'];
            $tallies[$value]['weight'] = (float)$row['weight'];
        }

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
                $denominator = $eligibleWeight > 0 ? $eligibleWeight : 0.0001;
                $numerator   = $expressedWeight;
            }

            $ratio        = $denominator > 0 ? $numerator / $denominator : 0.0;
            $quorumMet    = $ratio >= $quorumThreshold;
            $quorumRatio  = $ratio;
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

            $effectiveAgainst = $againstWeight + ($abstAsAgainst ? $abstainWeight : 0.0);

            $denominator = $baseTotal > 0 ? $baseTotal : 0.0001;
            $ratio       = $forWeight / $denominator;

            $majorityRatio = $ratio;

            if ($baseTotal <= 0.0 || $expressedWeight <= 0.0) {
                $adopted = false;
            } else {
                $adopted = $ratio >= $majorityThreshold;
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
