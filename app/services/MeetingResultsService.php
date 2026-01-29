<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/VoteEngine.php';

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

        $motion = db_select_one(
            "
            SELECT
              m.id,
              m.title,
              m.meeting_id,
              mt.tenant_id,
              mt.quorum_policy_id,
              mt.vote_policy_id AS meeting_vote_policy_id,
              m.vote_policy_id,
              m.quorum_policy_id,
              m.secret,
              m.closed_at,
              m.manual_total,
              m.manual_for,
              m.manual_against,
              m.manual_abstain
            FROM motions m
            JOIN meetings mt ON mt.id = m.meeting_id
            WHERE m.id = :id
            ",
            [':id' => $motionId]
        );

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

        $eligibleMembers = (int)(db_scalar(
            "SELECT COUNT(*) FROM members WHERE tenant_id = :tenant_id AND is_active = true",
            [':tenant_id' => $tenantId]
        ) ?? 0);

        $eligibleWeight = (float)(db_scalar(
            "SELECT COALESCE(SUM(voting_power), 0) FROM members WHERE tenant_id = :tenant_id AND is_active = true",
            [':tenant_id' => $tenantId]
        ) ?? 0.0);

        // Policies
        $quorumPolicy = null;
        if (!empty($motion['quorum_policy_id'])) {
            $quorumPolicy = db_select_one(
                "SELECT * FROM quorum_policies WHERE id = :id",
                [':id' => $motion['quorum_policy_id']]
            );
        }

        // Résoudre la politique de vote: motion-level > meeting-level
        $appliedVotePolicyId = !empty($motion['vote_policy_id'])
            ? (string)$motion['vote_policy_id']
            : (!empty($motion['meeting_vote_policy_id']) ? (string)$motion['meeting_vote_policy_id'] : '');

        $votePolicy = null;
        if ($appliedVotePolicyId !== '') {
            $votePolicy = db_select_one(
                "SELECT * FROM vote_policies WHERE id = :id",
                [':id' => $appliedVotePolicyId]
            );
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