<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

final class QuorumEngine
{
    public static function computeForMotion(string $motionId): array
    {
        $motionId = trim($motionId);
        if ($motionId === '') throw new InvalidArgumentException('motion_id obligatoire');

        $row = db_select_one(
            "SELECT mo.id AS motion_id, mo.title AS motion_title, mo.meeting_id,
                    mo.quorum_policy_id AS motion_quorum_policy_id,
                    mo.opened_at AS motion_opened_at,
                    mt.tenant_id, mt.quorum_policy_id AS meeting_quorum_policy_id,
                    COALESCE(mt.convocation_no,1) AS convocation_no
             FROM motions mo
             JOIN meetings mt ON mt.id = mo.meeting_id
             WHERE mo.id = :id",
            [':id' => $motionId]
        );
        if (!$row) throw new RuntimeException('Motion introuvable');

        $policyId = (string)($row['motion_quorum_policy_id'] ?: $row['meeting_quorum_policy_id']);
        if ($policyId === '') return self::noPolicy((string)$row['meeting_id'], (string)$row['tenant_id']);

        $policy = db_select_one("SELECT * FROM quorum_policies WHERE id = :id", [':id' => $policyId]);
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

        $row = db_select_one("SELECT id AS meeting_id, tenant_id, quorum_policy_id, COALESCE(convocation_no,1) AS convocation_no FROM meetings WHERE id = :id", [':id'=>$meetingId]);
        if (!$row) throw new RuntimeException('Séance introuvable');

        $policyId = (string)($row['quorum_policy_id'] ?? '');
        if ($policyId === '') return self::noPolicy((string)$row['meeting_id'], (string)$row['tenant_id']);

        $policy = db_select_one("SELECT * FROM quorum_policies WHERE id = :id", [':id'=>$policyId]);
        if (!$policy) return self::noPolicy((string)$row['meeting_id'], (string)$row['tenant_id']);

        return self::computeInternal((string)$row['meeting_id'], (string)$row['tenant_id'], (int)$row['convocation_no'], $policy, null) + [
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

        $ph = implode(',', array_fill(0, count($allowed), '?'));

        $lateFilterSql = "";
        $lateParams = [];
        if ($motionOpenedAt !== null) {
            $lateFilterSql = " AND (a.present_from_at IS NULL OR a.present_from_at <= ? )";
            $lateParams[] = $motionOpenedAt;
        }

        $numMembers = (int)(db_scalar(
            "SELECT COUNT(*) FROM attendances a
             JOIN meetings mt ON mt.id=a.meeting_id
             WHERE a.meeting_id=? AND mt.tenant_id=? AND a.checked_out_at IS NULL AND a.mode IN ($ph) $lateFilterSql",
            array_merge([$meetingId,$tenantId], $allowed, $lateParams)
        ) ?? 0);

        $numWeight = (float)(db_scalar(
            "SELECT COALESCE(SUM(a.effective_power),0) FROM attendances a
             JOIN meetings mt ON mt.id=a.meeting_id
             WHERE a.meeting_id=? AND mt.tenant_id=? AND a.checked_out_at IS NULL AND a.mode IN ($ph) $lateFilterSql",
            array_merge([$meetingId,$tenantId], $allowed, $lateParams)
        ) ?? 0.0);

        $eligibleMembers = (int)(db_scalar("SELECT COUNT(*) FROM members WHERE tenant_id=? AND is_active=true", [$tenantId]) ?? 0);
        $eligibleWeight  = (float)(db_scalar("SELECT COALESCE(SUM(voting_power),0) FROM members WHERE tenant_id=? AND is_active=true", [$tenantId]) ?? 0.0);

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
