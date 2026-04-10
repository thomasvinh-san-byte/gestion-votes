<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Core\Providers\RepositoryFactory;
use RuntimeException;
use Throwable;

/**
 * Business logic extracted from OperatorController.
 *
 * Handles workflow state computation and anomaly detection.
 * HTTP-agnostic: receives IDs as parameters, returns arrays, throws exceptions.
 */
final class OperatorWorkflowService {
    private RepositoryFactory $repos;

    public function __construct(?RepositoryFactory $repos = null) {
        $this->repos = $repos ?? RepositoryFactory::getInstance();
    }

    /**
     * Assemble the full workflow state for a meeting.
     *
     * @return array{meeting: array, motions: array, attendance: array, proxies: array, tokens: array, motion: array, consolidation: array, validation: array}
     * @throws RuntimeException if meeting not found
     */
    public function getWorkflowState(string $meetingId, string $tenantId, int $minOpen = 900, float $minParticipation = 0.5): array {
        $meetingRepo = $this->repos->meeting();
        $statsRepo = $this->repos->meetingStats();
        $memberRepo = $this->repos->member();
        $motionRepo = $this->repos->motion();
        $ballotRepo = $this->repos->ballot();

        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            throw new RuntimeException('meeting_not_found');
        }

        $eligibleMembers = $memberRepo->countActive($tenantId);
        $attRows = $memberRepo->listWithAttendanceForMeeting($meetingId, $tenantId);
        [$presentCount, $presentWeight, $totalWeight, $absentIds, $absentNames] = $this->computeAttendance($attRows);
        $totalCount = count($attRows);

        $quorumThreshold = $this->resolveQuorumThreshold($meeting, $tenantId);
        $quorumRatio = $eligibleMembers > 0 ? ($presentCount / $eligibleMembers) : 0.0;
        $quorumOk = $presentCount > 0 && $quorumRatio >= $quorumThreshold;

        $coveredRows = $this->repos->proxy()->listDistinctGivers($meetingId, $tenantId);
        $coveredSet = [];
        foreach ($coveredRows as $x) {
            $coveredSet[(string) $x['giver_member_id']] = true;
        }
        $missing = array_filter($absentIds, fn ($mid) => !isset($coveredSet[$mid]));
        $missingNames = array_values(array_filter(array_map(fn ($id) => $absentNames[$id] ?? '', $missing)));
        $proxyActive = $this->repos->proxy()->countActive($meetingId, $tenantId);

        $motions = $motionRepo->countWorkflowSummary($meetingId, $tenantId);
        $openMotion = $motionRepo->findCurrentOpen($meetingId, $tenantId);
        $nextMotion = $motionRepo->findNextNotOpened($meetingId, $tenantId);
        $lastClosed = $motionRepo->findLastClosedForProjector($meetingId, $tenantId);
        $potentialVoters = $presentCount + count($coveredSet);

        $openBallots = 0; $openAge = 0; $participation = null; $closeBlockers = []; $canClose = false;
        if ($openMotion) {
            $openBallots = $ballotRepo->countByMotionId($openMotion['id'], $tenantId);
            if (!empty($openMotion['opened_at'])) {
                $openAge = max(0, time() - strtotime($openMotion['opened_at']));
            }
            $participation = $potentialVoters > 0 ? ($openBallots / $potentialVoters) : 0.0;
            if ($openAge < $minOpen) {
                $closeBlockers[] = "Delai minimum non atteint ({$openAge}s / {$minOpen}s).";
            }
            if ($participation < $minParticipation) {
                $closeBlockers[] = 'Participation insuffisante (' . round($participation * 100) . '%, min ' . round($minParticipation * 100) . '%).';
            }
            $canClose = count($closeBlockers) === 0;
        }

        $hasClosed = $statsRepo->countClosedMotions($meetingId, $tenantId);
        $canConsolidate = ((int) ($motions['open'] ?? 0)) === 0 && $hasClosed > 0;
        $consolidated = $motionRepo->countConsolidatedMotions($meetingId, $tenantId);

        $validation = (new MeetingValidator())->canBeValidated($meetingId, $tenantId);
        (new NotificationsService())->emitReadinessTransitions($meetingId, $validation, $tenantId);

        return [
            'meeting' => ['id' => $meeting['id'], 'title' => $meeting['title'] ?? '', 'status' => $meeting['status'] ?? '', 'president_name' => $meeting['president_name'] ?? ''],
            'motions' => ['total' => (int) ($motions['total'] ?? 0), 'open' => (int) ($motions['open'] ?? 0)],
            'attendance' => ['ok' => $presentCount > 0, 'present_count' => $presentCount, 'present_weight' => $presentWeight, 'total_count' => $totalCount, 'total_weight' => $totalWeight, 'quorum_threshold' => $quorumThreshold, 'quorum_ratio' => round($quorumRatio, 4), 'quorum_ok' => $quorumOk],
            'proxies' => ['ok' => $quorumOk && (count($missing) === 0), 'active_count' => $proxyActive, 'missing_absent_without_proxy' => count($missing), 'missing_names' => $missingNames],
            'tokens' => ['disabled' => true],
            'motion' => [
                'has_any_motion' => ((int) ($motions['total'] ?? 0)) > 0, 'open_motion_id' => $openMotion['id'] ?? null, 'open_title' => $openMotion['title'] ?? null,
                'open_ballots' => $openBallots, 'open_age_seconds' => $openAge, 'potential_voters' => $potentialVoters,
                'participation_ratio' => $participation !== null ? round($participation, 4) : null, 'close_blockers' => $closeBlockers,
                'next_motion_id' => $nextMotion['id'] ?? null, 'next_title' => $nextMotion['title'] ?? null,
                'can_open_next' => $quorumOk && (count($missing) === 0) && ($openMotion === null) && ($nextMotion !== null),
                'can_close_open' => $canClose, 'last_closed_motion_id' => $lastClosed['id'] ?? null, 'last_closed_title' => $lastClosed['title'] ?? null,
            ],
            'consolidation' => [
                'can' => $canConsolidate, 'done' => ($hasClosed > 0) && ($consolidated >= $hasClosed),
                'detail' => $canConsolidate ? "Motions fermees: {$hasClosed}. Vous pouvez consolider." : (((int) ($motions['open'] ?? 0)) > 0 ? 'Fermez toutes les motions ouvertes avant consolidation.' : 'Aucune motion fermee a consolider.'),
                'closed_motions' => $hasClosed, 'consolidated_motions' => $consolidated,
            ],
            'validation' => ['ready' => (bool) ($validation['can'] ?? false), 'reasons' => (array) ($validation['reasons'] ?? [])],
        ];
    }

    /**
     * Compute anomalies for a meeting/motion.
     *
     * @return array{meeting: array, motion: ?array, eligibility: array, stats: array, anomalies: array}
     * @throws RuntimeException if meeting or motion not found
     */
    public function getAnomalies(string $meetingId, string $tenantId, string $motionId = ''): array {
        $meetingRepo = $this->repos->meeting();
        $motionRepo = $this->repos->motion();
        $memberRepo = $this->repos->member();
        $ballotRepo = $this->repos->ballot();
        $tokenRepo = $this->repos->voteToken();

        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            throw new RuntimeException('meeting_not_found');
        }

        if ($motionId === '') {
            $open = $motionRepo->findCurrentOpen($meetingId, $tenantId);
            $motionId = $open ? (string) $open['id'] : '';
        }
        $motion = null;
        if ($motionId !== '') {
            $motion = $motionRepo->findByMeetingWithDates($tenantId, $meetingId, $motionId);
            if (!$motion) {
                throw new RuntimeException('motion_not_found');
            }
        }

        $eligibleRows = $memberRepo->listEligibleForMeeting($tenantId, $meetingId);
        if (!$eligibleRows) {
            $eligibleRows = $memberRepo->listActiveFallbackByMeeting($tenantId, $meetingId);
        }
        $eligibleIds = []; $eligibleNames = [];
        foreach ($eligibleRows as $r) {
            $id = (string) ($r['member_id'] ?? '');
            if ($id === '') { continue; }
            $eligibleIds[] = $id;
            $eligibleNames[$id] = (string) ($r['full_name'] ?? '');
        }
        $eligibleCount = count($eligibleIds);

        $proxyMax = (int) config('proxy_max_per_receiver', 99);
        $proxyCeilings = [];
        try {
            $rows = $this->repos->proxy()->listCeilingViolations($tenantId, $meetingId, $proxyMax);
            foreach ($rows as $r) {
                $proxyCeilings[] = ['proxy_id' => (string) $r['proxy_id'], 'proxy_name' => $eligibleNames[(string) $r['proxy_id']] ?? null, 'count' => (int) $r['c'], 'max' => $proxyMax];
            }
        } catch (Throwable) { $proxyCeilings = []; }

        $stats = ['tokens_active_unused' => 0, 'tokens_expired_unused' => 0, 'tokens_used' => 0, 'ballots_total' => 0, 'ballots_from_eligible' => 0, 'eligible_expected' => $eligibleCount, 'missing_ballots_from_eligible' => 0];
        $missingNames = []; $ballotsNotEligible = []; $duplicates = [];

        if ($motionId !== '') {
            $stats['tokens_active_unused'] = $tokenRepo->countActiveUnused($tenantId, $meetingId, $motionId);
            $stats['tokens_expired_unused'] = $tokenRepo->countExpiredUnused($tenantId, $meetingId, $motionId);
            $stats['tokens_used'] = $tokenRepo->countUsed($tenantId, $meetingId, $motionId);
            $ballots = $ballotRepo->listForMotionWithSource($tenantId, $meetingId, $motionId);
            $stats['ballots_total'] = count($ballots);
            $votedSet = [];
            foreach ($ballots as $b) {
                $mid = (string) ($b['member_id'] ?? '');
                if ($mid === '') { continue; }
                if (isset($votedSet[$mid])) {
                    $duplicates[] = ['member_id' => $mid, 'name' => $eligibleNames[$mid] ?? null, 'detail' => 'duplicate_ballot_for_member'];
                }
                $votedSet[$mid] = true;
                if (!in_array($mid, $eligibleIds, true)) {
                    $ballotsNotEligible[] = ['member_id' => $mid, 'value' => (string) ($b['value'] ?? ''), 'source' => (string) ($b['source'] ?? ''), 'cast_at' => $b['cast_at']];
                }
            }
            $eligibleVoted = 0;
            foreach ($eligibleIds as $id) { if (isset($votedSet[$id])) { $eligibleVoted++; } }
            $stats['ballots_from_eligible'] = $eligibleVoted;
            $stats['missing_ballots_from_eligible'] = max(0, $eligibleCount - $eligibleVoted);
            if ($stats['missing_ballots_from_eligible'] > 0) {
                foreach ($eligibleIds as $id) {
                    if (!isset($votedSet[$id])) { $missingNames[] = $eligibleNames[$id] ?? $id; if (count($missingNames) >= 30) { break; } }
                }
            }
        }

        return [
            'meeting' => ['id' => $meetingId, 'status' => (string) ($meeting['status'] ?? ''), 'validated_at' => $meeting['validated_at']],
            'motion' => $motion ? ['id' => (string) $motion['id'], 'title' => (string) ($motion['title'] ?? ''), 'opened_at' => $motion['opened_at'], 'closed_at' => $motion['closed_at']] : null,
            'eligibility' => ['expected_count' => $eligibleCount],
            'stats' => $stats,
            'anomalies' => ['missing_voters_sample' => $missingNames, 'ballots_not_eligible' => $ballotsNotEligible, 'duplicates' => $duplicates],
        ];
    }

    /**
     * Parse attendance rows into present/absent counts and weights.
     *
     * @return array{0: int, 1: float, 2: float, 3: list<string>, 4: array<string, string>}
     */
    private function computeAttendance(array $attRows): array {
        $presentCount = 0; $presentWeight = 0.0; $totalWeight = 0.0;
        $absentIds = []; $absentNames = [];
        foreach ($attRows as $r) {
            $vp = (float) ($r['voting_power'] ?? 0);
            $totalWeight += $vp;
            $mode = (string) ($r['attendance_mode'] ?? '');
            if ($mode === 'present' || $mode === 'remote' || $mode === 'proxy') {
                $presentCount++; $presentWeight += $vp;
            } else {
                $mid = (string) $r['member_id'];
                $absentIds[] = $mid;
                $absentNames[$mid] = (string) ($r['full_name'] ?? '');
            }
        }
        return [$presentCount, $presentWeight, $totalWeight, $absentIds, $absentNames];
    }

    /**
     * Execute vote opening logic within a transaction context.
     *
     * @return array{inserted: int, tokensOut: array, previousStatus: string}
     * @throws RuntimeException on validation failure
     */
    public function openVote(string $meetingId, string $motionId, string $tenantId, string $userId, string $secret, bool $listTokens = false, int $expiresMinutes = 120): array {
        $meetingRepo = $this->repos->meeting();
        $motionRepo = $this->repos->motion();
        $memberRepo = $this->repos->member();
        $attendanceRepo = $this->repos->attendance();
        $tokenRepo = $this->repos->voteToken();

        $meeting = $meetingRepo->lockForUpdate($meetingId, $tenantId);
        if (!$meeting) { throw new RuntimeException('meeting_not_found'); }
        if (!empty($meeting['validated_at'])) { throw new RuntimeException('meeting_validated_locked'); }

        $status = (string) ($meeting['status'] ?? '');
        $previousStatus = $status;
        if ($status !== 'live') { $meetingRepo->updateFields($meetingId, $tenantId, ['status' => 'live']); }

        if ($motionId === '') {
            $next = $motionRepo->findNextNotOpenedForUpdate($tenantId, $meetingId);
            if (!$next) { throw new RuntimeException('no_motion_to_open'); }
            $motionId = (string) $next['id'];
        } else {
            $row = $motionRepo->findByIdAndMeetingForUpdate($tenantId, $meetingId, $motionId);
            if (!$row) { throw new RuntimeException('motion_not_found'); }
        }

        $open = $motionRepo->findCurrentOpen($meetingId, $tenantId);
        if ($open && (string) $open['id'] !== $motionId) {
            throw new RuntimeException('another_motion_active');
        }

        $motionRepo->markOpenedInMeeting($tenantId, $motionId, $meetingId);
        $meetingRepo->updateCurrentMotion($meetingId, $tenantId, $motionId);

        $eligible = $attendanceRepo->listEligibleMemberIds($tenantId, $meetingId);
        if (!$eligible) { $eligible = $memberRepo->listByMeetingFallback($tenantId, $meetingId); }

        $inserted = 0; $tokensOut = [];
        foreach ($eligible as $e) {
            $memberId = (string) $e['member_id'];
            if ($memberId === '') { continue; }
            $raw = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0x0fff) | 0x4000, random_int(0, 0x3fff) | 0x8000, random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff));
            $hash = hash_hmac('sha256', $raw, $secret);
            $active = $tokenRepo->findActiveForMember($tenantId, $meetingId, $motionId, $memberId);
            if ($active) { continue; }
            $ok = $tokenRepo->insertWithExpiry($hash, $tenantId, $meetingId, $memberId, $motionId, $expiresMinutes);
            if ($ok > 0) {
                $inserted++;
                if ($listTokens) { $tokensOut[] = ['member_id' => $memberId, 'token' => $raw, 'url' => '/vote.php?token=' . $raw]; }
            }
        }

        return ['inserted' => $inserted, 'tokensOut' => $tokensOut, 'previousStatus' => $previousStatus, 'motionId' => $motionId];
    }

    /** Resolve quorum threshold from meeting policy or default to 50%. */
    private function resolveQuorumThreshold(array $meeting, string $tenantId): float {
        $quorumPolicyId = $meeting['quorum_policy_id'] ?? null;
        if ($quorumPolicyId) {
            $policy = $this->repos->policy()->findQuorumPolicyForTenant($quorumPolicyId, $tenantId);
            if ($policy && isset($policy['threshold'])) {
                return (float) $policy['threshold'];
            }
        }
        return 0.5;
    }
}
