<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\MotionRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\MemberRepository;

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
        if ($quorumPolicy) {
            $quorumBasis     = (string)$quorumPolicy['denominator'];
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
