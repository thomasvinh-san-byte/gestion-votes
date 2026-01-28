<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

final class VoteEngine
{
    /**
     * Calcule les résultats d'une motion à partir des bulletins et des policies.
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

        $motion = db_select_one(
            "
            SELECT
              m.id            AS motion_id,
              m.title         AS motion_title,
              m.vote_policy_id,
              m.secret,
              mt.id           AS meeting_id,
              mt.tenant_id    AS tenant_id,
              mt.quorum_policy_id,
              mt.vote_policy_id AS meeting_vote_policy_id
            FROM motions m
            JOIN meetings mt ON mt.id = m.meeting_id
            WHERE m.id = :motion_id
            ",
            [':motion_id' => $motionId]
        );

        if (!$motion) {
            throw new RuntimeException('Motion introuvable');
        }

        $tenantId   = (string)$motion['tenant_id'];
        $meetingId  = (string)$motion['meeting_id'];

        // Agréger les bulletins par valeur
        global $pdo;
        $stmt = $pdo->prepare(
            "
            SELECT
              value,
              COUNT(*)                AS count,
              COALESCE(SUM(weight),0) AS weight
            FROM ballots
            WHERE motion_id = :motion_id
            GROUP BY value
            "
        );
        $stmt->execute([':motion_id' => $motionId]);
        $rows = $stmt->fetchAll() ?: [];

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

        // Électeurs éligibles (par tenant)
        $eligibleMembers = (int)(db_scalar(
            "SELECT COUNT(*) FROM members WHERE tenant_id = :tenant_id AND is_active = true",
            [':tenant_id' => $tenantId]
        ) ?? 0);

        $eligibleWeight = (float)(db_scalar(
            "SELECT COALESCE(SUM(voting_power), 0) FROM members WHERE tenant_id = :tenant_id AND is_active = true",
            [':tenant_id' => $tenantId]
        ) ?? 0.0);

        // Charger policies éventuelles
        $quorumPolicy = null;
        if (!empty($motion['quorum_policy_id'])) {
            $quorumPolicy = db_select_one(
                "SELECT * FROM quorum_policies WHERE id = :id",
                [':id' => $motion['quorum_policy_id']]
            );
        }

        $votePolicy = null;
        if (!empty($appliedVotePolicyId)) {
            $votePolicy = db_select_one(
                "SELECT * FROM vote_policies WHERE id = :id",
                [':id' => $appliedVotePolicyId]
            );
        }

        // Calcul du quorum
        $quorumMet         = null;
        $quorumRatio       = null;
        $quorumThreshold   = null;
        $quorumDenominator = null;
        $quorumBasis       = null;

        if ($quorumPolicy) {
            $quorumBasis     = (string)$quorumPolicy['denominator']; // eligible_members / eligible_weight
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

        // Calcul de la majorité
        $adopted           = null;
        $majorityRatio     = null;
        $majorityThreshold = null;
        $majorityBase      = null;
        $abstAsAgainst     = null;

        if ($votePolicy) {
            $majorityBase      = (string)$votePolicy['base']; // expressed / present / eligible
            $majorityThreshold = (float)$votePolicy['threshold'];
            $abstAsAgainst     = (bool)$votePolicy['abstention_as_against'];

            if ($majorityBase === 'expressed') {
                $baseTotal = $expressedWeight;
            } elseif ($majorityBase === 'eligible') {
                $baseTotal = $eligibleWeight;
            } elseif ($majorityBase === 'present') {
                // Pas de table de présences dans ce schéma : on approxime par les voix exprimées
                $baseTotal = $expressedWeight;
            } else {
                $baseTotal = $expressedWeight;
            }

            $forWeight     = $tallies['for']['weight'];
            $againstWeight = $tallies['against']['weight'];
            $abstainWeight = $tallies['abstain']['weight'];

            $effectiveAgainst = $againstWeight + ($abstAsAgainst ? $abstainWeight : 0.0);

            // On mesure le rapport des "pour" sur la base définie
            $denominator = $baseTotal > 0 ? $baseTotal : 0.0001;
            $ratio       = $forWeight / $denominator;

            $majorityRatio = $ratio;

            // Si pas de base de calcul ou pas de voix exprimées, non adopté
            if ($baseTotal <= 0.0 || $expressedWeight <= 0.0) {
                $adopted = false;
            } else {
                $adopted = $ratio >= $majorityThreshold;
                if ($quorumMet === false) {
                    $adopted = false;
                }
            }
        }

        // Statut global lisible
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
