<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AgVote\Repository\MotionRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\MemberRepository;

final class QuorumEngine
{
    public static function computeForMotion(string $motionId): array
    {
        $motionId = trim($motionId);
        if ($motionId === '') throw new InvalidArgumentException('motion_id obligatoire');

        $motionRepo = new MotionRepository();
        $row = $motionRepo->findWithQuorumContext($motionId);
        if (!$row) throw new RuntimeException('Motion introuvable');

        $policyId = (string)($row['motion_quorum_policy_id'] ?: $row['meeting_quorum_policy_id']);
        if ($policyId === '') return self::noPolicy((string)$row['meeting_id'], (string)$row['tenant_id']);

        $policyRepo = new PolicyRepository();
        $policy = $policyRepo->findQuorumPolicy($policyId);
        if (!$policy) return self::noPolicy((string)$row['meeting_id'], (string)$row['tenant_id']);

        $openedAt = $row['motion_opened_at'] ?? null;

        return self::computeInternal((string)$row['meeting_id'], (string)$row['tenant_id'], (int)$row['convocation_no'], $policy, $openedAt) + [
            'policy' => ['id'=>(string)$policy['id'],'name'=>(string)$policy['name'],'mode'=>(string)$policy['mode']],
            'applies_to' => ['motion_id'=>(string)$row['motion_id'],'motion_title'=>(string)$row['motion_title']],
        ];
    }

    public static function computeForMeeting(string $meetingId): array
    {
        $meetingId = trim($meetingId);
        if ($meetingId === '') throw new InvalidArgumentException('meeting_id obligatoire');

        $meetingRepo = new MeetingRepository();
        $row = $meetingRepo->findById($meetingId);
        if (!$row) throw new RuntimeException('Séance introuvable');

        $policyId = (string)($row['quorum_policy_id'] ?? '');
        $tenantId = (string)$row['tenant_id'];
        $convocationNo = (int)($row['convocation_no'] ?? 1);

        if ($policyId === '') return self::noPolicy($meetingId, $tenantId);

        $policyRepo = new PolicyRepository();
        $policy = $policyRepo->findQuorumPolicy($policyId);
        if (!$policy) return self::noPolicy($meetingId, $tenantId);

        return self::computeInternal($meetingId, $tenantId, $convocationNo, $policy, null) + [
            'policy' => ['id'=>(string)$policy['id'],'name'=>(string)$policy['name'],'mode'=>(string)$policy['mode']],
        ];
    }

    private static function noPolicy(string $meetingId, string $tenantId): array
    {
        return [
            'applied' => false,
            'met' => null,
            'details' => [],
            'justification' => 'Aucune politique de quorum appliquée.',
            'meeting' => ['id'=>$meetingId,'tenant_id'=>$tenantId],
        ];
    }

    private static function computeInternal(string $meetingId, string $tenantId, int $convocationNo, array $policy, $motionOpenedAt): array
    {
        $includeProxies = (bool)($policy['include_proxies'] ?? true);
        $countRemote    = (bool)($policy['count_remote'] ?? true);
        $mode           = (string)($policy['mode'] ?? 'single');

        $den1 = (string)($policy['denominator'] ?? 'eligible_members');
        $thr1 = (float)($policy['threshold'] ?? 0.0);

        if ($mode === 'evolving' && $convocationNo === 2 && $policy['threshold_call2'] !== null) {
            $thr1 = (float)$policy['threshold_call2'];
        }

        $allowed = ['present'];
        if ($countRemote) $allowed[] = 'remote';
        if ($includeProxies) $allowed[] = 'proxy';

        $lateCutoff = ($motionOpenedAt !== null) ? (string)$motionOpenedAt : null;

        $attendanceRepo = new AttendanceRepository();
        $numMembers = $attendanceRepo->countPresentMembers($meetingId, $tenantId, $allowed, $lateCutoff);
        $numWeight  = $attendanceRepo->sumPresentWeight($meetingId, $tenantId, $allowed, $lateCutoff);

        $memberRepo = new MemberRepository();
        $eligibleMembers = $memberRepo->countActive($tenantId);
        $eligibleWeight  = $memberRepo->sumActiveWeight($tenantId);

        $primary = self::ratioBlock($den1, $thr1, $numMembers, $numWeight, $eligibleMembers, $eligibleWeight);
        $met = $primary['met'];
        $details = ['primary'=>$primary];

        if ($mode === 'double') {
            $den2 = (string)($policy['denominator2'] ?? '');
            $thr2 = ($policy['threshold2'] !== null) ? (float)$policy['threshold2'] : null;
            if ($den2 === '' || $thr2 === null) {
                $met = false;
                $details['secondary'] = ['configured'=>false,'met'=>false,'message'=>'Double quorum : deuxième condition non configurée.'];
            } else {
                $secondary = self::ratioBlock($den2, (float)$thr2, $numMembers, $numWeight, $eligibleMembers, $eligibleWeight);
                $details['secondary'] = $secondary;
                $met = ($primary['met'] === true) && ($secondary['met'] === true);
            }
        }

        $just = self::justification((string)($policy['name'] ?? 'Quorum'), $mode, $convocationNo, $allowed, $details, $met, $motionOpenedAt);

        return [
            'applied' => true,
            'met' => $met,
            'details' => $details,
            'justification' => $just,
            'meeting' => ['id'=>$meetingId,'tenant_id'=>$tenantId,'convocation_no'=>$convocationNo],
            'numerator' => ['members'=>$numMembers,'weight'=>$numWeight,'modes'=>$allowed],
            'eligible'  => ['members'=>$eligibleMembers,'weight'=>$eligibleWeight],
            'late_rule' => [
                'enabled' => ($motionOpenedAt !== null),
                'motion_opened_at' => $motionOpenedAt,
                'excludes_present_from_after_open' => ($motionOpenedAt !== null),
            ],
        ];
    }

    private static function ratioBlock(string $basis, float $threshold, int $numMembers, float $numWeight, int $eligibleMembers, float $eligibleWeight): array
    {
        if ($basis === 'eligible_members') {
            $den = max(1, $eligibleMembers);
            $num = (float)$numMembers;
        } else {
            $den = $eligibleWeight > 0 ? $eligibleWeight : 0.0001;
            $num = (float)$numWeight;
        }
        $ratio = $den > 0 ? $num / $den : 0.0;

        return [
            'configured' => true,
            'met' => $ratio >= $threshold,
            'ratio' => $ratio,
            'threshold' => $threshold,
            'numerator' => $num,
            'denominator' => $den,
            'basis' => $basis,
        ];
    }

    private static function justification(string $name, string $mode, int $convocationNo, array $modes, array $details, ?bool $met, $motionOpenedAt): string
    {
        $status = ($met === null) ? 'non applicable' : ($met ? 'atteint' : 'non atteint');
        $modesLabel = implode(', ', $modes);

        $p = $details['primary'];
        $pRatio = number_format((float)$p['ratio'], 4, '.', '');
        $pThr   = number_format((float)$p['threshold'], 4, '.', '');

        $late = ($motionOpenedAt !== null) ? " Retardataires exclus (present_from_at > opened_at)." : "";

        return sprintf(
            "%s (convocation %d) : base %s (ratio %s / seuil %s). Comptés: %s. Résultat: %s.%s",
            $name, $convocationNo, (string)$p['basis'], $pRatio, $pThr, $modesLabel, $status, $late
        );
    }
}
