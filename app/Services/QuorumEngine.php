<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\PolicyRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * QuorumEngine - Computes meeting and motion quorum status.
 *
 * This service calculates whether quorum requirements are met for meetings
 * and motions based on configured quorum policies.
 *
 * Features:
 * - Single and double quorum modes
 * - Evolving threshold based on convocation number
 * - Support for proxies and remote attendees
 * - Weight-based and member-count-based calculations
 * - Late arrival exclusion (for motions opened before member arrived)
 *
 * Quorum Modes:
 * - single: One threshold to meet
 * - double: Two independent thresholds (both must be met)
 * - evolving: Different threshold for 2nd convocation
 *
 * @package AgVote\Service
 */
final class QuorumEngine {
    private MotionRepository $motionRepo;
    private PolicyRepository $policyRepo;
    private AttendanceRepository $attendanceRepo;
    private MemberRepository $memberRepo;
    private MeetingRepository $meetingRepo;

    public function __construct(
        ?MotionRepository $motionRepo = null,
        ?PolicyRepository $policyRepo = null,
        ?AttendanceRepository $attendanceRepo = null,
        ?MemberRepository $memberRepo = null,
        ?MeetingRepository $meetingRepo = null,
    ) {
        $this->motionRepo = $motionRepo ?? new MotionRepository();
        $this->policyRepo = $policyRepo ?? new PolicyRepository();
        $this->attendanceRepo = $attendanceRepo ?? new AttendanceRepository();
        $this->memberRepo = $memberRepo ?? new MemberRepository();
        $this->meetingRepo = $meetingRepo ?? new MeetingRepository();
    }

    /**
     * Compute quorum status for a specific motion.
     *
     * Uses motion-level quorum policy if set, otherwise falls back to meeting policy.
     *
     * @param string $motionId Motion UUID
     *
     * @throws InvalidArgumentException If motion_id is empty
     * @throws RuntimeException If motion not found
     *
     * @return array{applied: bool, met: ?bool, details: array, justification: string, meeting: array, policy?: array}
     */
    public function computeForMotion(string $motionId): array {
        $motionId = trim($motionId);
        if ($motionId === '') {
            throw new InvalidArgumentException('motion_id obligatoire');
        }

        $row = $this->motionRepo->findWithQuorumContext($motionId);
        if (!$row) {
            throw new RuntimeException('Motion introuvable');
        }

        $policyId = (string) ($row['motion_quorum_policy_id'] ?: $row['meeting_quorum_policy_id']);
        if ($policyId === '') {
            return self::noPolicy((string) $row['meeting_id'], (string) $row['tenant_id']);
        }

        $policy = $this->policyRepo->findQuorumPolicy($policyId);
        if (!$policy) {
            return self::noPolicy((string) $row['meeting_id'], (string) $row['tenant_id']);
        }

        $openedAt = $row['motion_opened_at'] ?? null;

        return $this->computeInternal((string) $row['meeting_id'], (string) $row['tenant_id'], (int) $row['convocation_no'], $policy, $openedAt) + [
            'policy' => ['id' => (string) $policy['id'], 'name' => (string) $policy['name'], 'mode' => (string) $policy['mode']],
            'applies_to' => ['motion_id' => (string) $row['motion_id'], 'motion_title' => (string) $row['motion_title']],
        ];
    }

    /**
     * Compute quorum for a meeting.
     *
     * @param string $meetingId Meeting ID
     * @param string|null $expectedTenantId If provided, validates meeting belongs to this tenant
     */
    public function computeForMeeting(string $meetingId, ?string $expectedTenantId = null): array {
        $meetingId = trim($meetingId);
        if ($meetingId === '') {
            throw new InvalidArgumentException('meeting_id obligatoire');
        }

        $expectedTenantId = $expectedTenantId ?: api_current_tenant_id();

        $row = $this->meetingRepo->findByIdForTenant($meetingId, $expectedTenantId);
        if (!$row) {
            throw new RuntimeException('Séance introuvable');
        }

        $policyId = (string) ($row['quorum_policy_id'] ?? '');
        $tenantId = (string) $row['tenant_id'];
        $convocationNo = (int) ($row['convocation_no'] ?? 1);

        if ($policyId === '') {
            return self::noPolicy($meetingId, $tenantId);
        }

        $policy = $this->policyRepo->findQuorumPolicy($policyId);
        if (!$policy) {
            return self::noPolicy($meetingId, $tenantId);
        }

        return $this->computeInternal($meetingId, $tenantId, $convocationNo, $policy, null) + [
            'policy' => ['id' => (string) $policy['id'], 'name' => (string) $policy['name'], 'mode' => (string) $policy['mode']],
        ];
    }

    /**
     * Return a result indicating no quorum policy is applied.
     *
     * @param string $meetingId Meeting UUID
     * @param string $tenantId Tenant UUID
     *
     * @return array
     */
    private static function noPolicy(string $meetingId, string $tenantId): array {
        return [
            'applied' => false,
            'met' => null,
            'details' => [],
            'justification' => 'Aucune politique de quorum appliquée.',
            'meeting' => ['id' => $meetingId, 'tenant_id' => $tenantId],
        ];
    }

    /**
     * Internal quorum computation logic.
     *
     * @param string $meetingId Meeting UUID
     * @param string $tenantId Tenant UUID
     * @param int $convocationNo Convocation number (1 or 2)
     * @param array $policy Quorum policy configuration
     * @param string|null $motionOpenedAt Motion opened timestamp (for late arrival exclusion)
     *
     * @return array Quorum calculation results
     */
    private function computeInternal(string $meetingId, string $tenantId, int $convocationNo, array $policy, $motionOpenedAt): array {
        $includeProxies = (bool) ($policy['include_proxies'] ?? true);
        $countRemote = (bool) ($policy['count_remote'] ?? true);
        $mode = (string) ($policy['mode'] ?? 'single');

        $den1 = (string) ($policy['denominator'] ?? 'eligible_members');
        $thr1 = (float) ($policy['threshold'] ?? 0.0);

        if ($mode === 'evolving' && $convocationNo === 2 && $policy['threshold_call2'] !== null) {
            $thr1 = (float) $policy['threshold_call2'];
        }

        $allowed = ['present'];
        if ($countRemote) {
            $allowed[] = 'remote';
        }
        if ($includeProxies) {
            $allowed[] = 'proxy';
        }

        $lateCutoff = ($motionOpenedAt !== null) ? (string) $motionOpenedAt : null;

        $numMembers = $this->attendanceRepo->countPresentMembers($meetingId, $tenantId, $allowed, $lateCutoff);
        $numWeight = $this->attendanceRepo->sumPresentWeight($meetingId, $tenantId, $allowed, $lateCutoff);

        $eligibleMembers = $this->memberRepo->countActive($tenantId);
        $eligibleWeight = $this->memberRepo->sumActiveWeight($tenantId);

        $primary = self::ratioBlock($den1, $thr1, $numMembers, $numWeight, $eligibleMembers, $eligibleWeight);
        $met = $primary['met'];
        $details = ['primary' => $primary];

        if ($mode === 'double') {
            $den2 = (string) ($policy['denominator2'] ?? '');
            $thr2 = ($policy['threshold2'] !== null) ? (float) $policy['threshold2'] : null;
            if ($den2 === '' || $thr2 === null) {
                $met = false;
                $details['secondary'] = ['configured' => false, 'met' => false, 'message' => 'Double quorum : deuxième condition non configurée.'];
            } else {
                $secondary = self::ratioBlock($den2, (float) $thr2, $numMembers, $numWeight, $eligibleMembers, $eligibleWeight);
                $details['secondary'] = $secondary;
                $met = ($primary['met'] === true) && ($secondary['met'] === true);
            }
        }

        $just = self::justification((string) ($policy['name'] ?? 'Quorum'), $mode, $convocationNo, $allowed, $details, $met, $motionOpenedAt);

        return [
            'applied' => true,
            'met' => $met,
            'details' => $details,
            'justification' => $just,
            'meeting' => ['id' => $meetingId, 'tenant_id' => $tenantId, 'convocation_no' => $convocationNo],
            'numerator' => ['members' => $numMembers, 'weight' => $numWeight, 'modes' => $allowed],
            'eligible' => ['members' => $eligibleMembers, 'weight' => $eligibleWeight],
            'late_rule' => [
                'enabled' => ($motionOpenedAt !== null),
                'motion_opened_at' => $motionOpenedAt,
                'excludes_present_from_after_open' => ($motionOpenedAt !== null),
            ],
        ];
    }

    /**
     * Calculate a single quorum ratio block.
     *
     * @param string $basis Calculation basis: 'eligible_members' or 'eligible_weight'
     * @param float $threshold Required threshold (0.0 to 1.0)
     * @param int $numMembers Number of present members
     * @param float $numWeight Total weight of present members
     * @param int $eligibleMembers Total eligible members
     * @param float $eligibleWeight Total eligible weight
     *
     * @return array{configured: bool, met: bool, ratio: float, threshold: float, numerator: float, denominator: float, basis: string}
     */
    private static function ratioBlock(string $basis, float $threshold, int $numMembers, float $numWeight, int $eligibleMembers, float $eligibleWeight): array {
        if ($basis === 'eligible_members') {
            $den = max(1, $eligibleMembers);
            $num = (float) $numMembers;
        } else {
            $den = $eligibleWeight;
            $num = (float) $numWeight;
        }

        if ($den <= 0) {
            return [
                'configured' => true,
                'met' => false,
                'ratio' => 0.0,
                'threshold' => $threshold,
                'numerator' => $num,
                'denominator' => 0.0,
                'basis' => $basis,
            ];
        }

        $ratio = $num / $den;

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

    /**
     * Generate human-readable justification text for quorum result.
     *
     * @param string $name Policy name
     * @param string $mode Quorum mode (single, double, evolving)
     * @param int $convocationNo Convocation number
     * @param array $modes Attendance modes counted (present, remote, proxy)
     * @param array $details Calculation details with primary and optional secondary blocks
     * @param bool|null $met Whether quorum was met
     * @param string|null $motionOpenedAt Motion opened timestamp
     *
     * @return string Justification text in French
     */
    private static function justification(string $name, string $mode, int $convocationNo, array $modes, array $details, ?bool $met, $motionOpenedAt): string {
        $status = ($met === null) ? 'non applicable' : ($met ? 'atteint' : 'non atteint');
        $modesLabel = implode(', ', $modes);

        $p = $details['primary'];
        $pRatio = number_format((float) $p['ratio'], 4, '.', '');
        $pThr = number_format((float) $p['threshold'], 4, '.', '');

        $late = ($motionOpenedAt !== null) ? ' Retardataires exclus (present_from_at > opened_at).' : '';

        return sprintf(
            '%s (convocation %d) : base %s (ratio %s / seuil %s). Comptés: %s. Résultat: %s.%s',
            $name,
            $convocationNo,
            (string) $p['basis'],
            $pRatio,
            $pThr,
            $modesLabel,
            $status,
            $late,
        );
    }
}
