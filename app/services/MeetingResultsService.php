<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\MotionRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Repository\MemberRepository;

/**
 * Consolidation "officielle" d'un résultat de motion.
 *
 * Règle MVP :
 * - Si un comptage manuel est saisi (manual_total > 0), il fait foi.
 * - Sinon, on calcule via e-vote (ballots) avec VoteEngine.
 */
final class MeetingResultsService
{
    /**
     * @return array<string,mixed> Format compatible (subset) de VoteEngine::computeMotionResult()
     */
    public static function officialMotionResult(string $motionId): array
    {
        $motionId = trim($motionId);
        if ($motionId === '') {
            throw new InvalidArgumentException('motion_id obligatoire');
        }

        $motionRepo = new MotionRepository();
        $motion = $motionRepo->findWithOfficialContext($motionId);

        if (!$motion) {
            throw new RuntimeException('Motion introuvable');
        }

        $hasManual = ((int)($motion['manual_total'] ?? 0)) > 0;
        if (!$hasManual) {
            // e-vote (ballots)
            $res = VoteEngine::computeMotionResult($motionId);
            $res['official_source'] = 'evote';
            return $res;
        }

        $tenantId = (string)$motion['tenant_id'];

        // Comptage manuel (on assimile à un "poids")
        $manualFor     = (int)($motion['manual_for'] ?? 0);
        $manualAgainst = (int)($motion['manual_against'] ?? 0);
        $manualAbstain = (int)($motion['manual_abstain'] ?? 0);
        $manualTotal   = (int)($motion['manual_total'] ?? 0);

        // Par sécurité, on reconstruit le total exprimé depuis les champs si incohérent.
        $expressedWeight = $manualFor + $manualAgainst + $manualAbstain;
        if ($manualTotal > 0 && $expressedWeight <= 0) {
            $expressedWeight = $manualTotal;
        }

        $memberRepo = new MemberRepository();
        $eligibleMembers = $memberRepo->countActive($tenantId);
        $eligibleWeight  = $memberRepo->sumActiveWeight($tenantId);

        // Policies
        $policyRepo = new PolicyRepository();

        $quorumPolicy = null;
        if (!empty($motion['quorum_policy_id'])) {
            $quorumPolicy = $policyRepo->findQuorumPolicy((string)$motion['quorum_policy_id']);
        }

        // Résoudre la politique de vote: motion-level > meeting-level
        $appliedVotePolicyId = !empty($motion['vote_policy_id'])
            ? (string)$motion['vote_policy_id']
            : (!empty($motion['meeting_vote_policy_id']) ? (string)$motion['meeting_vote_policy_id'] : '');

        $votePolicy = null;
        if ($appliedVotePolicyId !== '') {
            $votePolicy = $policyRepo->findVotePolicy($appliedVotePolicyId);
        }

        // Quorum
        $quorumMet         = null;
        $quorumRatio       = null;
        $quorumThreshold   = null;
        $quorumBasis       = null;
        $quorumDenominator = null;

        $expressedMembers = $manualTotal > 0 ? $manualTotal : ($manualFor + $manualAgainst + $manualAbstain);
        $expressedWeightF = (float)$expressedWeight;

        if ($quorumPolicy) {
            $quorumBasis     = (string)$quorumPolicy['denominator'];
            $quorumThreshold = (float)$quorumPolicy['threshold'];

            if ($quorumBasis === 'eligible_members') {
                $denominator = max(1, $eligibleMembers);
                $numerator   = $expressedMembers;
            } else {
                $denominator = $eligibleWeight > 0 ? $eligibleWeight : 0.0001;
                $numerator   = $expressedWeightF;
            }

            $ratio             = $denominator > 0 ? $numerator / $denominator : 0.0;
            $quorumMet         = $ratio >= $quorumThreshold;
            $quorumRatio       = $ratio;
            $quorumDenominator = $denominator;
        }

        // Majorité
        $adopted           = null;
        $majorityRatio     = null;
        $majorityThreshold = null;
        $majorityBase      = null;
        $abstAsAgainst     = null;

        if ($votePolicy) {
            $majorityBase      = (string)$votePolicy['base'];
            $majorityThreshold = (float)$votePolicy['threshold'];
            $abstAsAgainst     = (bool)$votePolicy['abstention_as_against'];

            if ($majorityBase === 'eligible') {
                $baseTotal = $eligibleWeight;
            } else {
                // expressed/present → on se base sur l'exprimé manuel
                $baseTotal = $expressedWeightF;
            }

            $forW     = (float)$manualFor;
            $againstW = (float)$manualAgainst;
            $abstW    = (float)$manualAbstain;

            $den = $baseTotal > 0 ? $baseTotal : 0.0001;
            $majorityRatio = $forW / $den;

            if ($baseTotal <= 0.0 || $expressedWeightF <= 0.0) {
                $adopted = false;
            } else {
                $adopted = $majorityRatio >= $majorityThreshold;
                if ($quorumMet === false) {
                    $adopted = false;
                }
            }
        }

        $decisionStatus = 'no_votes';
        $decisionReason = null;

        $hasVotes = ($manualTotal > 0) || ($expressedWeightF > 0);
        if (!$hasVotes) {
            $decisionStatus = 'no_votes';
            $decisionReason = 'Aucun comptage manuel.';
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
            'official_source' => 'manual',
            'motion' => [
                'id'               => (string)$motion['id'],
                'title'            => (string)$motion['title'],
                'meeting_id'       => (string)$motion['meeting_id'],
                'tenant_id'        => $tenantId,
                'vote_policy_id'   => $appliedVotePolicyId,
                'quorum_policy_id' => $motion['quorum_policy_id'],
                'secret'           => (bool)$motion['secret'],
            ],
            'tallies' => [
                'for'     => ['count' => $manualFor,     'weight' => (float)$manualFor],
                'against' => ['count' => $manualAgainst, 'weight' => (float)$manualAgainst],
                'abstain' => ['count' => $manualAbstain, 'weight' => (float)$manualAbstain],
                'nsp'     => ['count' => 0,              'weight' => 0.0],
            ],
            'eligible' => [
                'members' => $eligibleMembers,
                'weight'  => $eligibleWeight,
            ],
            'expressed' => [
                'members' => $expressedMembers,
                'weight'  => $expressedWeightF,
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
