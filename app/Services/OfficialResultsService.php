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
 * Single source of truth for "official" results.
 *
 * Rule:
 * - If manual_total > 0 AND consistent => source = manual (manual totals)
 * - Otherwise => source = evote (ballot aggregation)
 *
 * Decision:
 * - Uses the same quorum/majority logic as VoteEngine (quorum_policies / vote_policies).
 * - In manual mode, applies these policies on manual totals (with expressed_members approx = manual_total).
 */
final class OfficialResultsService
{
    /**
     * Applies quorum/majority policies to weighted vote totals.
     * Delegates the core calculation to VoteEngine::computeDecision() (single source of truth).
     *
     * @return array{decision:string,reason:string,quorum_met:bool|null,majority_ratio:float|null,majority_threshold:float|null,majority_base:string|null}
     */
    private static function decideWithPolicies(array $motion, float $forWeight, float $againstWeight, float $abstainWeight, float $expressedWeight, int $expressedMembersApprox): array
    {
        $tenantId = (string)$motion['tenant_id'];

        $memberRepo = new MemberRepository();
        $eligibleMembers = $memberRepo->countActive($tenantId);
        $eligibleWeight  = $memberRepo->sumActiveWeight($tenantId);

        // Quorum policy: motion-level > meeting-level (inheritance)
        $appliedQuorumPolicyId = !empty($motion['quorum_policy_id'])
            ? (string)$motion['quorum_policy_id']
            : (!empty($motion['meeting_quorum_policy_id']) ? (string)$motion['meeting_quorum_policy_id'] : '');

        $policyRepo = new PolicyRepository();

        $quorumPolicy = null;
        if ($appliedQuorumPolicyId !== '') {
            $quorumPolicy = $policyRepo->findQuorumPolicy($appliedQuorumPolicyId);
        }

        // Vote policy: motion-level > meeting-level (inheritance)
        $appliedVotePolicyId = !empty($motion['vote_policy_id'])
            ? (string)$motion['vote_policy_id']
            : (!empty($motion['meeting_vote_policy_id']) ? (string)$motion['meeting_vote_policy_id'] : '');

        $votePolicy = null;
        if ($appliedVotePolicyId !== '') {
            $votePolicy = $policyRepo->findVotePolicy($appliedVotePolicyId);
        }

        // Resolve present weight for 'present' majority base
        $presentWeight = null;
        if ($votePolicy && ($votePolicy['base'] ?? '') === 'present') {
            $meetingId = (string)($motion['meeting_id'] ?? '');
            $attendanceRepo = new \AgVote\Repository\AttendanceRepository();
            $presentWeight = $attendanceRepo->sumPresentWeight($meetingId, $tenantId, ['present', 'remote']);
        }

        // Delegate to VoteEngine (single source of truth for quorum/majority)
        $calc = VoteEngine::computeDecision(
            $quorumPolicy,
            $votePolicy,
            $forWeight,
            $againstWeight,
            $abstainWeight,
            $expressedWeight,
            $expressedMembersApprox,
            $eligibleMembers,
            $eligibleWeight,
            $presentWeight,
        );

        $quorumMet         = $calc['quorum']['met'];
        $quorumRatio       = $calc['quorum']['ratio'];
        $quorumThreshold   = $calc['quorum']['threshold'];
        $quorumBasis       = $calc['quorum']['basis'];
        $adopted           = $calc['majority']['met'];
        $majorityRatio     = $calc['majority']['ratio'];
        $majorityThreshold = $calc['majority']['threshold'];
        $majorityBase      = $calc['majority']['base'];

        // Build explicit French reason
        $status = 'rejected';
        $reason = '';

        if ($votePolicy) {
            $status = $adopted ? 'adopted' : 'rejected';

            if ($quorumMet === false) {
                $quorumPct = self::formatPct($quorumRatio);
                $thresholdPct = self::formatPct($quorumThreshold);
                $basisLabel = ($quorumBasis === 'eligible_members') ? 'des membres éligibles' : 'du poids éligible';
                $reason = "Quorum non atteint ({$quorumPct} < {$thresholdPct} {$basisLabel})";
            } elseif ($adopted) {
                $ratioPct = self::formatPct($majorityRatio);
                $thresholdPct = self::formatPct($majorityThreshold);
                $baseLabel = self::getMajorityBaseLabel($majorityBase);
                $reason = "Majorité atteinte ({$ratioPct} >= {$thresholdPct} {$baseLabel})";
            } else {
                $ratioPct = self::formatPct($majorityRatio);
                $thresholdPct = self::formatPct($majorityThreshold);
                $baseLabel = self::getMajorityBaseLabel($majorityBase);
                $reason = "Majorité non atteinte ({$ratioPct} < {$thresholdPct} {$baseLabel})";
            }
        } else {
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
     * Formats a percentage for decision reasons
     */
    private static function formatPct(?float $value): string
    {
        if ($value === null) {
            return '0%';
        }
        $pct = $value * 100;
        // Display without decimals if integer, otherwise 1 decimal
        if (abs($pct - round($pct)) < 0.01) {
            return number_format((int)round($pct), 0, ',', ' ') . '%';
        }
        return number_format($pct, 1, ',', ' ') . '%';
    }

    /**
     * Formats a weight for decision reasons
     */
    private static function formatWeight(float $value): string
    {
        if (abs($value - round($value)) < 0.0001) {
            return number_format((int)round($value), 0, ',', ' ');
        }
        return number_format($value, 2, ',', ' ');
    }

    /**
     * Returns the French label for majority base
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
     * Builds an explicit reason from VoteEngine data
     */
    private static function buildExplicitReasonFromVoteEngine(array $result, float $forWeight, float $againstWeight, string $status): string
    {
        $quorum = $result['quorum'] ?? [];
        $majority = $result['majority'] ?? [];

        // Case: quorum not met
        if (($quorum['applied'] ?? false) && ($quorum['met'] ?? true) === false) {
            $quorumPct = self::formatPct($quorum['ratio'] ?? 0);
            $thresholdPct = self::formatPct($quorum['threshold'] ?? 0);
            $basis = ($quorum['basis'] ?? '') === 'eligible_members' ? 'des membres éligibles' : 'du poids éligible';
            return "Quorum non atteint ({$quorumPct} < {$thresholdPct} {$basis})";
        }

        // Case: vote policy applied
        if ($majority['applied'] ?? false) {
            $ratioPct = self::formatPct($majority['ratio'] ?? 0);
            $thresholdPct = self::formatPct($majority['threshold'] ?? 0);
            $baseLabel = self::getMajorityBaseLabel($majority['base'] ?? null);

            if ($majority['met'] ?? false) {
                return "Majorité atteinte ({$ratioPct} >= {$thresholdPct} {$baseLabel})";
            }
            return "Majorité non atteinte ({$ratioPct} < {$thresholdPct} {$baseLabel})";
        }

        // Case: no policy (simple majority) or no votes
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

        // Fallback for other statuses
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

        // EVOTE: totals from ballots + decision from VoteEngine
        $ballotRepo = new BallotRepository();
        $t = $ballotRepo->tally($motionId, (string)$motion['tenant_id']);

        $forW = (float)$t['weight_for'];
        $agW  = (float)$t['weight_against'];
        $abW  = (float)$t['weight_abstain'];
        $totW = (float)$t['weight_total'];

        $r = VoteEngine::computeMotionResult($motionId);
        $status = (string)($r['decision']['status'] ?? (($forW > $agW) ? 'adopted' : 'rejected'));

        // Build explicit reason from VoteEngine data
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
    public static function computeAndPersistMotion(string $motionId, string $tenantId): array
    {
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
            $o['reason'],
            $tenantId
        );

        return $o;
    }

    /** @return array{updated:int} */
    public static function consolidateMeeting(string $meetingId, string $tenantId): array
    {
        $motionRepo = new MotionRepository();
        $motions = $motionRepo->listClosedForMeeting($meetingId, $tenantId);

        $updated = 0;
        api_transaction(function () use ($motions, $motionRepo, $tenantId, &$updated) {
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
                    $o['reason'],
                    $tenantId
                );
                $updated++;
            }
        });

        return ['updated' => $updated];
    }
}
