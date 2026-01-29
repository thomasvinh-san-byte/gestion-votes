<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/VoteEngine.php';

/**
 * Source unique de vérité "officielle" des résultats.
 *
 * Règle:
 * - Si manual_total > 0 ET cohérent => source = manual (totaux manuels)
 * - Sinon => source = evote (agrégation ballots)
 *
 * Décision:
 * - Utilise la même logique de quorum/majorité que VoteEngine (policies quorum_policies / vote_policies).
 * - En manuel, on applique ces policies sur les totaux manuels (avec expressed_members approx = manual_total).
 */
final class OfficialResultsService
{
    public static function ensureSchema(): void
    {
        $sql = <<<SQL
        ALTER TABLE motions
          ADD COLUMN IF NOT EXISTS official_source text,
          ADD COLUMN IF NOT EXISTS official_for double precision,
          ADD COLUMN IF NOT EXISTS official_against double precision,
          ADD COLUMN IF NOT EXISTS official_abstain double precision,
          ADD COLUMN IF NOT EXISTS official_total double precision,
          ADD COLUMN IF NOT EXISTS decision text,
          ADD COLUMN IF NOT EXISTS decision_reason text,
          ADD COLUMN IF NOT EXISTS decided_at timestamptz
        SQL;
        try { db_exec($sql); } catch (Throwable $e) { /* best-effort */ }
    }

    /**
     * @return array{decision:string,reason:string,quorum_met:bool|null,majority_ratio:float|null,majority_threshold:float|null,majority_base:string|null}
     */
    private static function decideWithPolicies(array $motion, float $forWeight, float $againstWeight, float $abstainWeight, float $expressedWeight, int $expressedMembersApprox): array
    {
        $tenantId = (string)$motion['tenant_id'];

        $eligibleMembers = (int)(db_scalar(
            "SELECT COUNT(*) FROM members WHERE tenant_id = :tenant_id AND is_active = true",
            [':tenant_id' => $tenantId]
        ) ?? 0);

        $eligibleWeight = (float)(db_scalar(
            "SELECT COALESCE(SUM(COALESCE(voting_power, vote_weight, 1.0)), 0) FROM members WHERE tenant_id = :tenant_id AND is_active = true",
            [':tenant_id' => $tenantId]
        ) ?? 0.0);

        // Quorum policy: motion-level > meeting-level
        $appliedQuorumPolicyId = !empty($motion['quorum_policy_id'])
            ? (string)$motion['quorum_policy_id']
            : (!empty($motion['meeting_quorum_policy_id']) ? (string)$motion['meeting_quorum_policy_id'] : '');

        $quorumPolicy = null;
        if ($appliedQuorumPolicyId !== '') {
            $quorumPolicy = db_select_one(
                "SELECT * FROM quorum_policies WHERE id = :id",
                [':id' => $appliedQuorumPolicyId]
            );
        }

        // Vote policy: motion-level > meeting-level
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

        // Quorum (même logique que VoteEngine)
        $quorumMet = null;
        if ($quorumPolicy) {
            $quorumBasis     = (string)$quorumPolicy['denominator']; // eligible_members / eligible_weight
            $quorumThreshold = (float)$quorumPolicy['threshold'];

            if ($quorumBasis === 'eligible_members') {
                $denominator = max(1, $eligibleMembers);
                $numerator   = max(0, $expressedMembersApprox);
            } else {
                $denominator = $eligibleWeight > 0 ? $eligibleWeight : 0.0001;
                $numerator   = $expressedWeight;
            }

            $ratio    = $denominator > 0 ? $numerator / $denominator : 0.0;
            $quorumMet = $ratio >= $quorumThreshold;
        }

        // Majorité (même logique que VoteEngine)
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
                // VoteEngine approxime présent par exprimé
                $baseTotal = $expressedWeight;
            } else {
                $baseTotal = $expressedWeight;
            }

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

        // Décision finale
        if ($votePolicy) {
            $status = $adopted ? 'adopted' : 'rejected';
            $reason = $adopted ? 'vote_policy_met' : (($quorumMet === false) ? 'quorum_not_met' : 'vote_policy_not_met');
        } else {
            // Fallback: logique simple (comme avant), mais toujours bloquée si quorum non atteint
            $status = ($forWeight > $againstWeight) ? 'adopted' : 'rejected';
            if ($quorumMet === false) $status = 'rejected';
            $reason = $votePolicy ? 'vote_policy' : (($quorumMet === false) ? 'quorum_not_met' : 'simple_majority');
        }

        return [
            'decision' => $status,
            'reason' => $reason,
            'quorum_met' => $quorumMet,
            'majority_ratio' => $majorityRatio,
            'majority_threshold' => $majorityThreshold,
            'majority_base' => $majorityBase,
        ];
    }

    /**
     * @return array{source:string,for:float,against:float,abstain:float,total:float,decision:string,reason:string}
     */
    public static function computeOfficialTallies(string $motionId): array
    {
        $motionId = trim($motionId);
        if ($motionId === '') throw new InvalidArgumentException('motion_id obligatoire');

        $motion = db_select_one(
            "SELECT
               m.id AS motion_id,
               m.meeting_id,
               m.title,
               m.vote_policy_id,
               m.quorum_policy_id,
               mt.tenant_id AS tenant_id,
               mt.vote_policy_id AS meeting_vote_policy_id,
               mt.quorum_policy_id AS meeting_quorum_policy_id,
               m.manual_total, m.manual_for, m.manual_against, m.manual_abstain,
               m.closed_at
             FROM motions m
             JOIN meetings mt ON mt.id = m.meeting_id
             WHERE m.id = ?",
            [$motionId]
        );
        if (!$motion) {
            throw new RuntimeException('motion_not_found');
        }

        $manualTotal = (float)($motion['manual_total'] ?? 0);
        $manualFor   = (float)($motion['manual_for'] ?? 0);
        $manualAg    = (float)($motion['manual_against'] ?? 0);
        $manualAb    = (float)($motion['manual_abstain'] ?? 0);

        $manualOk = ($manualTotal > 0) && abs(($manualFor + $manualAg + $manualAb) - $manualTotal) < 0.000001;

        if ($manualOk) {
            $expressedWeight = $manualFor + $manualAg + $manualAb;
            $decision = self::decideWithPolicies(
                $motion,
                $manualFor,
                $manualAg,
                $manualAb,
                $expressedWeight,
                (int)round($manualTotal) // approx exprimés (membres)
            );

            return [
                'source' => 'manual',
                'for' => $manualFor,
                'against' => $manualAg,
                'abstain' => $manualAb,
                'total' => $manualTotal,
                'decision' => $decision['decision'],
                'reason' => $decision['reason'],
            ];
        }

        // EVOTE: totaux depuis ballots + décision depuis VoteEngine (règle complète)
        // Utilise COALESCE entre colonnes canoniques (value/weight) et alias compat (choice/effective_power)
        $rows = db_select_one(
            "SELECT
               COALESCE(SUM(CASE WHEN COALESCE(value::text, choice) = 'for' THEN COALESCE(weight, effective_power, 0) ELSE 0 END), 0) AS w_for,
               COALESCE(SUM(CASE WHEN COALESCE(value::text, choice) = 'against' THEN COALESCE(weight, effective_power, 0) ELSE 0 END), 0) AS w_against,
               COALESCE(SUM(CASE WHEN COALESCE(value::text, choice) = 'abstain' THEN COALESCE(weight, effective_power, 0) ELSE 0 END), 0) AS w_abstain,
               COALESCE(SUM(COALESCE(weight, effective_power, 0)), 0) AS w_total
             FROM ballots
             WHERE motion_id = ?",
            [$motionId]
        ) ?: ['w_for'=>0,'w_against'=>0,'w_abstain'=>0,'w_total'=>0];

        $forW = (float)$rows['w_for'];
        $agW  = (float)$rows['w_against'];
        $abW  = (float)$rows['w_abstain'];
        $totW = (float)$rows['w_total'];

        $r = VoteEngine::computeMotionResult($motionId);
        $status = (string)($r['decision']['status'] ?? (($forW > $agW) ? 'adopted' : 'rejected'));
        $reason = (string)($r['decision']['reason'] ?? 'vote_engine');

        return [
            'source' => 'evote',
            'for' => $forW,
            'against' => $agW,
            'abstain' => $abW,
            'total' => $totW,
            'decision' => $status,
            'reason' => $reason,
        ];
    }

    /** @return array{updated:int} */
    public static function consolidateMeeting(string $meetingId): array
    {
        self::ensureSchema();

        $motions = db_select_all(
            "SELECT id FROM motions WHERE meeting_id = ? AND closed_at IS NOT NULL ORDER BY closed_at ASC",
            [$meetingId]
        );

        $updated = 0;
        foreach ($motions as $m) {
            $mid = (string)$m['id'];
            $o = self::computeOfficialTallies($mid);

            db_exec(
                "UPDATE motions SET
                   official_source = ?,
                   official_for = ?,
                   official_against = ?,
                   official_abstain = ?,
                   official_total = ?,
                   decision = ?,
                   decision_reason = ?,
                   decided_at = NOW()
                 WHERE id = ?",
                [
                    $o['source'],
                    $o['for'],
                    $o['against'],
                    $o['abstain'],
                    $o['total'],
                    $o['decision'],
                    $o['reason'],
                    $mid
                ]
            );
            $updated++;
        }

        return ['updated' => $updated];
    }
}
