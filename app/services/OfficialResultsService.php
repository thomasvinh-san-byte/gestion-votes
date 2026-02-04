<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\MotionRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\MemberRepository;
use InvalidArgumentException;
use RuntimeException;

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
        $motionRepo = new MotionRepository();
        $motionRepo->ensureOfficialColumns();
    }

    /**
     * @return array{decision:string,reason:string,quorum_met:bool|null,majority_ratio:float|null,majority_threshold:float|null,majority_base:string|null}
     */
    private static function decideWithPolicies(array $motion, float $forWeight, float $againstWeight, float $abstainWeight, float $expressedWeight, int $expressedMembersApprox): array
    {
        $tenantId = (string)$motion['tenant_id'];

        $memberRepo = new MemberRepository();
        $eligibleMembers = $memberRepo->countActive($tenantId);
        $eligibleWeight  = $memberRepo->sumActiveWeight($tenantId);

        // Quorum policy: motion-level > meeting-level
        $appliedQuorumPolicyId = !empty($motion['quorum_policy_id'])
            ? (string)$motion['quorum_policy_id']
            : (!empty($motion['meeting_quorum_policy_id']) ? (string)$motion['meeting_quorum_policy_id'] : '');

        $policyRepo = new PolicyRepository();

        $quorumPolicy = null;
        if ($appliedQuorumPolicyId !== '') {
            $quorumPolicy = $policyRepo->findQuorumPolicy($appliedQuorumPolicyId);
        }

        // Vote policy: motion-level > meeting-level
        $appliedVotePolicyId = !empty($motion['vote_policy_id'])
            ? (string)$motion['vote_policy_id']
            : (!empty($motion['meeting_vote_policy_id']) ? (string)$motion['meeting_vote_policy_id'] : '');

        $votePolicy = null;
        if ($appliedVotePolicyId !== '') {
            $votePolicy = $policyRepo->findVotePolicy($appliedVotePolicyId);
        }

        // Quorum (même logique que VoteEngine)
        $quorumMet = null;
        $quorumRatio = null;
        $quorumThreshold = null;
        $quorumBasis = null;
        $quorumNumerator = null;
        $quorumDenominator = null;

        if ($quorumPolicy) {
            $quorumBasis     = (string)$quorumPolicy['denominator'];
            $quorumThreshold = (float)$quorumPolicy['threshold'];

            if ($quorumBasis === 'eligible_members') {
                $quorumDenominator = max(1, $eligibleMembers);
                $quorumNumerator   = max(0, $expressedMembersApprox);
            } else {
                $quorumDenominator = $eligibleWeight > 0 ? $eligibleWeight : 0.0001;
                $quorumNumerator   = $expressedWeight;
            }

            $quorumRatio = $quorumDenominator > 0 ? $quorumNumerator / $quorumDenominator : 0.0;
            $quorumMet = $quorumRatio >= $quorumThreshold;
        }

        // Majorité (même logique que VoteEngine)
        $adopted           = null;
        $majorityRatio     = null;
        $majorityThreshold = null;
        $majorityBase      = null;
        $abstAsAgainst     = null;
        $majorityDenominator = null;

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

            $majorityDenominator = $baseTotal > 0 ? $baseTotal : 0.0001;
            $majorityRatio = $forWeight / $majorityDenominator;

            if ($baseTotal <= 0.0 || $expressedWeight <= 0.0) {
                $adopted = false;
            } else {
                $adopted = $majorityRatio >= $majorityThreshold;
                if ($quorumMet === false) {
                    $adopted = false;
                }
            }
        }

        // Décision finale et raison explicite
        $status = 'rejected';
        $reason = '';

        if ($votePolicy) {
            $status = $adopted ? 'adopted' : 'rejected';

            // Raison explicite avec valeurs numériques
            if ($quorumMet === false) {
                // Quorum non atteint
                $quorumPct = self::formatPct($quorumRatio);
                $thresholdPct = self::formatPct($quorumThreshold);
                $basisLabel = ($quorumBasis === 'eligible_members') ? 'des membres éligibles' : 'du poids éligible';
                $reason = "Quorum non atteint ({$quorumPct} < {$thresholdPct} {$basisLabel})";
            } elseif ($adopted) {
                // Majorité atteinte
                $ratioPct = self::formatPct($majorityRatio);
                $thresholdPct = self::formatPct($majorityThreshold);
                $baseLabel = self::getMajorityBaseLabel($majorityBase);
                $reason = "Majorité atteinte ({$ratioPct} >= {$thresholdPct} {$baseLabel})";
            } else {
                // Majorité non atteinte
                $ratioPct = self::formatPct($majorityRatio);
                $thresholdPct = self::formatPct($majorityThreshold);
                $baseLabel = self::getMajorityBaseLabel($majorityBase);
                $reason = "Majorité non atteinte ({$ratioPct} < {$thresholdPct} {$baseLabel})";
            }
        } else {
            // Pas de politique de vote: majorité simple
            $status = ($forWeight > $againstWeight) ? 'adopted' : 'rejected';

            if ($quorumMet === false) {
                $status = 'rejected';
                $quorumPct = self::formatPct($quorumRatio);
                $thresholdPct = self::formatPct($quorumThreshold);
                $basisLabel = ($quorumBasis === 'eligible_members') ? 'des membres éligibles' : 'du poids éligible';
                $reason = "Quorum non atteint ({$quorumPct} < {$thresholdPct} {$basisLabel})";
            } elseif ($status === 'adopted') {
                $forFmt = self::formatWeight($forWeight);
                $againstFmt = self::formatWeight($againstWeight);
                $reason = "Majorité simple (Pour: {$forFmt} > Contre: {$againstFmt})";
            } else {
                $forFmt = self::formatWeight($forWeight);
                $againstFmt = self::formatWeight($againstWeight);
                if ($forWeight === $againstWeight) {
                    $reason = "Égalité des voix (Pour: {$forFmt} = Contre: {$againstFmt})";
                } else {
                    $reason = "Majorité simple non atteinte (Pour: {$forFmt} <= Contre: {$againstFmt})";
                }
            }
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
     * Formate un pourcentage pour les raisons de décision
     */
    private static function formatPct(?float $value): string
    {
        if ($value === null) {
            return '0%';
        }
        $pct = $value * 100;
        // Afficher sans décimales si entier, sinon 1 décimale
        if (abs($pct - round($pct)) < 0.01) {
            return number_format((int)round($pct), 0, ',', ' ') . '%';
        }
        return number_format($pct, 1, ',', ' ') . '%';
    }

    /**
     * Formate un poids pour les raisons de décision
     */
    private static function formatWeight(float $value): string
    {
        if (abs($value - round($value)) < 0.0001) {
            return number_format((int)round($value), 0, ',', ' ');
        }
        return number_format($value, 2, ',', ' ');
    }

    /**
     * Retourne le label français pour la base de majorité
     */
    private static function getMajorityBaseLabel(?string $base): string
    {
        return match ($base) {
            'expressed' => 'des exprimés',
            'eligible' => 'des éligibles',
            'present' => 'des présents',
            default => 'des votants',
        };
    }

    /**
     * Construit une raison explicite à partir des données de VoteEngine
     */
    private static function buildExplicitReasonFromVoteEngine(array $result, float $forWeight, float $againstWeight, string $status): string
    {
        $quorum = $result['quorum'] ?? [];
        $majority = $result['majority'] ?? [];

        // Cas: quorum non atteint
        if (($quorum['applied'] ?? false) && ($quorum['met'] ?? true) === false) {
            $quorumPct = self::formatPct($quorum['ratio'] ?? 0);
            $thresholdPct = self::formatPct($quorum['threshold'] ?? 0);
            $basis = ($quorum['basis'] ?? '') === 'eligible_members' ? 'des membres éligibles' : 'du poids éligible';
            return "Quorum non atteint ({$quorumPct} < {$thresholdPct} {$basis})";
        }

        // Cas: politique de vote appliquée
        if ($majority['applied'] ?? false) {
            $ratioPct = self::formatPct($majority['ratio'] ?? 0);
            $thresholdPct = self::formatPct($majority['threshold'] ?? 0);
            $baseLabel = self::getMajorityBaseLabel($majority['base'] ?? null);

            if ($majority['met'] ?? false) {
                return "Majorité atteinte ({$ratioPct} >= {$thresholdPct} {$baseLabel})";
            }
            return "Majorité non atteinte ({$ratioPct} < {$thresholdPct} {$baseLabel})";
        }

        // Cas: pas de politique (majorité simple) ou aucun vote
        if ($status === 'no_votes') {
            return 'Aucun bulletin enregistré';
        }

        if ($status === 'no_policy') {
            $forFmt = self::formatWeight($forWeight);
            $againstFmt = self::formatWeight($againstWeight);
            if ($forWeight > $againstWeight) {
                return "Majorité simple (Pour: {$forFmt} > Contre: {$againstFmt})";
            } elseif ($forWeight === $againstWeight) {
                return "Égalité des voix (Pour: {$forFmt} = Contre: {$againstFmt})";
            }
            return "Majorité simple non atteinte (Pour: {$forFmt} <= Contre: {$againstFmt})";
        }

        // Fallback pour les autres statuts
        $forFmt = self::formatWeight($forWeight);
        $againstFmt = self::formatWeight($againstWeight);
        if ($status === 'adopted') {
            return "Majorité simple (Pour: {$forFmt} > Contre: {$againstFmt})";
        }
        return "Majorité simple non atteinte (Pour: {$forFmt} <= Contre: {$againstFmt})";
    }

    /**
     * @return array{source:string,for:float,against:float,abstain:float,total:float,decision:string,reason:string}
     */
    public static function computeOfficialTallies(string $motionId): array
    {
        $motionId = trim($motionId);
        if ($motionId === '') throw new InvalidArgumentException('motion_id obligatoire');

        $motionRepo = new MotionRepository();
        $motion = $motionRepo->findWithOfficialContext($motionId);
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
                (int)round($manualTotal)
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

        // EVOTE: totaux depuis ballots + décision depuis VoteEngine
        $ballotRepo = new BallotRepository();
        $rows = $ballotRepo->weightedTally($motionId);

        $forW = (float)$rows['w_for'];
        $agW  = (float)$rows['w_against'];
        $abW  = (float)$rows['w_abstain'];
        $totW = (float)$rows['w_total'];

        $r = VoteEngine::computeMotionResult($motionId);
        $status = (string)($r['decision']['status'] ?? (($forW > $agW) ? 'adopted' : 'rejected'));

        // Construire une raison explicite à partir des données de VoteEngine
        $reason = self::buildExplicitReasonFromVoteEngine($r, $forW, $agW, $status);

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

    /**
     * Compute and persist official results for a single motion.
     * @return array{source:string,for:float,against:float,abstain:float,total:float,decision:string,reason:string}
     */
    public static function computeAndPersistMotion(string $motionId): array
    {
        self::ensureSchema();

        $o = self::computeOfficialTallies($motionId);

        $motionRepo = new MotionRepository();
        $motionRepo->updateOfficialResults(
            $motionId,
            $o['source'],
            $o['for'],
            $o['against'],
            $o['abstain'],
            $o['total'],
            $o['decision'],
            $o['reason']
        );

        return $o;
    }

    /** @return array{updated:int} */
    public static function consolidateMeeting(string $meetingId): array
    {
        self::ensureSchema();

        $motionRepo = new MotionRepository();
        $motions = $motionRepo->listClosedForMeeting($meetingId);

        $updated = 0;
        foreach ($motions as $m) {
            $mid = (string)$m['id'];
            $o = self::computeOfficialTallies($mid);

            $motionRepo->updateOfficialResults(
                $mid,
                $o['source'],
                $o['for'],
                $o['against'],
                $o['abstain'],
                $o['total'],
                $o['decision'],
                $o['reason']
            );
            $updated++;
        }

        return ['updated' => $updated];
    }
}
