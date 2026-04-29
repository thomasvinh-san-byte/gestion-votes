<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Core\Providers\RepositoryFactory;
use AgVote\Core\Security\AuthMiddleware;
use InvalidArgumentException;
use RuntimeException;

/**
 * Transition/launch/readyCheck/resetDemo logic extracted from MeetingWorkflowController.
 *
 * Uses MeetingWorkflowService internally for pre-transition validation.
 * Controllers remain thin HTTP adapters handling audit_log, EventBroadcaster, responses.
 */
final class MeetingTransitionService {
    private RepositoryFactory $repos;

    public function __construct(?RepositoryFactory $repos = null) {
        $this->repos = $repos ?? RepositoryFactory::getInstance();
    }

    /**
     * Execute a single-step meeting status transition.
     *
     * @return array{meeting_id: string, from_status: string, to_status: string, transitioned_at: string, warnings: array, results_emails: int}
     */
    public function transition(string $meetingId, string $tenantId, string $toStatus, string $userId): array {
        $validStatuses = ['draft', 'scheduled', 'frozen', 'live', 'paused', 'closed', 'validated', 'archived'];
        if ($toStatus === '') {
            throw new InvalidArgumentException('Le champ to_status est requis.');
        }
        if (!in_array($toStatus, $validStatuses, true)) {
            throw new InvalidArgumentException("Statut '{$toStatus}' invalide.");
        }

        $repo = $this->repos->meeting();
        $meeting = $repo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            throw new RuntimeException('meeting_not_found');
        }

        $fromStatus = $meeting['status'];

        if ($fromStatus === $toStatus) {
            return [
                'meeting_id' => $meetingId,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'already_in_target' => true,
            ];
        }
        if ($fromStatus === 'archived') {
            throw new RuntimeException('Séance archivée : aucune transition autorisée.');
        }

        return [
            'meeting_id' => $meetingId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'meeting' => $meeting,
        ];
    }

    /**
     * Multi-step fast-forward launch to live status.
     *
     * @return array{meeting_id: string, from_status: string, to_status: string, path: array, warnings: array}
     */
    public function launch(string $meetingId, string $tenantId, string $userId): array {
        $repo = $this->repos->meeting();
        $meeting = $repo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            throw new RuntimeException('meeting_not_found');
        }

        $fromStatus = $meeting['status'];

        if ($fromStatus === 'live') {
            return [
                'meeting_id' => $meetingId,
                'from_status' => 'live',
                'to_status' => 'live',
                'path' => [],
                'already_in_target' => true,
            ];
        }

        $path = match ($fromStatus) {
            'draft' => ['scheduled', 'frozen', 'live'],
            'scheduled' => ['frozen', 'live'],
            'frozen' => ['live'],
            default => throw new RuntimeException("Impossible de lancer depuis le statut '{$fromStatus}'."),
        };

        // Check prerequisites for EACH step in the launch path
        $allIssues = [];
        $allWarnings = [];
        $simulatedFrom = $fromStatus;
        foreach ($path as $step) {
            $stepCheck = (new MeetingWorkflowService())->issuesBeforeTransition($meetingId, $tenantId, $step, $simulatedFrom);
            $allIssues = array_merge($allIssues, $stepCheck['issues']);
            $allWarnings = array_merge($allWarnings, $stepCheck['warnings']);
            $simulatedFrom = $step;
        }

        if (count($allIssues) > 0) {
            throw new RuntimeException(json_encode([
                'code' => 'workflow_issues',
                'detail' => 'Lancement bloqué par des pré-requis',
                'issues' => $allIssues,
                'warnings' => $allWarnings,
            ]));
        }

        return [
            'meeting_id' => $meetingId,
            'from_status' => $fromStatus,
            'to_status' => 'live',
            'path' => $path,
            'warnings' => $allWarnings,
            'meeting' => $meeting,
        ];
    }

    /**
     * Pre-validation readiness check with detailed checks array.
     *
     * @return array{ready: bool, checks: array, can: bool, bad_motions: array, meta: array}
     */
    public function readyCheck(string $meetingId, string $tenantId): array {
        $meetingRepo = $this->repos->meeting();
        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            throw new RuntimeException('meeting_not_found');
        }

        $statsRepo = $this->repos->meetingStats();
        $motionRepo = $this->repos->motion();
        $attendanceRepo = $this->repos->attendance();
        $memberRepo = $this->repos->member();
        $ballotRepo = $this->repos->ballot();

        $checks = [];
        $bad = [];
        $pres = trim((string) ($meeting['president_name'] ?? ''));
        $checks[] = ['passed' => $pres !== '', 'label' => 'Président renseigné', 'detail' => $pres !== '' ? $pres : "Aucun président (president_name) n'est renseigné."];
        $openCount = $statsRepo->countOpenMotions($meetingId, $tenantId);
        $checks[] = ['passed' => $openCount === 0, 'label' => 'Motions fermées', 'detail' => $openCount > 0 ? "Il reste {$openCount} motion(s) ouverte(s). Fermez-les avant validation." : ''];
        $eligibleCount = $attendanceRepo->countEligible($meetingId, $tenantId);
        $fallbackEligibleUsed = false;
        if ($eligibleCount <= 0) { $fallbackEligibleUsed = true; $eligibleCount = $memberRepo->countActive($tenantId); }
        $checks[] = ['passed' => !$fallbackEligibleUsed, 'label' => 'Présences saisies', 'detail' => $fallbackEligibleUsed ? 'Règle de fallback utilisée (tous membres actifs).' : ''];
        $motions = $motionRepo->listClosedForMeetingWithManualTally($meetingId, $tenantId);
        foreach ($motions as $m) {
            $mid = (string) $m['id']; $title = (string) ($m['title'] ?? 'Motion');
            $mt = (int) ($m['manual_total'] ?? 0); $mf = (int) ($m['manual_for'] ?? 0);
            $ma = (int) ($m['manual_against'] ?? 0); $mab = (int) ($m['manual_abstain'] ?? 0);
            $manualOk = $mt > 0 && ($mf + $ma + $mab) === $mt;
            $eBallots = $ballotRepo->countEligibleDirect($meetingId, $mid, $tenantId) + $ballotRepo->countEligibleProxy($meetingId, $mid, $tenantId);
            $bt = $ballotRepo->countByMotionId($mid, $tenantId);
            $missing = max(0, $eligibleCount - $bt);
            if ($missing > 0) { $bad[] = ['motion_id' => $mid, 'title' => $title, 'detail' => "Votes manquants : {$missing} (attendus: {$eligibleCount}, reçus: {$bt})."]; }
            $iD = $ballotRepo->countInvalidDirect($meetingId, $mid, $tenantId);
            $iP = $ballotRepo->countInvalidProxy($meetingId, $mid, $tenantId);
            if ($iD > 0 || $iP > 0) { $bad[] = ['motion_id' => $mid, 'title' => $title, 'detail' => "Bulletins non éligibles détectés (direct: {$iD}, procuration: {$iP})."]; }
            if (!$manualOk && $eBallots <= 0) { $bad[] = ['motion_id' => $mid, 'title' => $title, 'detail' => 'Aucun résultat exploitable: pas de comptage manuel cohérent et aucun bulletin e-vote éligible.']; }
            elseif ($mt > 0 && !$manualOk) { $bad[] = ['motion_id' => $mid, 'title' => $title, 'detail' => 'Comptage manuel incohérent (pour+contre+abst != total).']; }
        }
        foreach ($bad as $b) { $checks[] = ['passed' => false, 'label' => $b['title'], 'detail' => $b['detail']]; }
        if (count($bad) === 0 && count($motions) > 0) { $checks[] = ['passed' => true, 'label' => 'Résultats exploitables', 'detail' => count($motions) . ' motion(s) avec résultat valide.']; }
        $ready = true;
        foreach ($checks as $c) { if (!$c['passed']) { $ready = false; break; } }

        return [
            'ready' => $ready,
            'checks' => $checks,
            'can' => $ready,
            'bad_motions' => $bad,
            'meta' => [
                'meeting_id' => $meetingId,
                'eligible_count' => $eligibleCount,
                'fallback_eligible_used' => $fallbackEligibleUsed,
            ],
        ];
    }

    /**
     * Build the fields array for a status transition.
     * @return array<string, mixed> Fields to update
     */
    public function buildTransitionFields(string $toStatus, string $fromStatus, array $meeting, string $userId, ?string $validatedByName = null): array {
        $fields = ['status' => $toStatus];
        $now = date('Y-m-d H:i:s');
        switch ($toStatus) {
            case 'frozen': $fields['frozen_at'] = $now; $fields['frozen_by'] = $userId; break;
            case 'live':
                if (empty($meeting['started_at'])) { $fields['started_at'] = $now; }
                if (!empty($meeting['scheduled_at']) && $meeting['scheduled_at'] > $now) { $fields['scheduled_at'] = $now; }
                $fields['opened_by'] = $userId;
                if ($fromStatus === 'paused') { $fields['paused_at'] = null; $fields['paused_by'] = null; }
                break;
            case 'paused': $fields['paused_at'] = $now; $fields['paused_by'] = $userId; break;
            case 'closed':
                if (empty($meeting['ended_at'])) { $fields['ended_at'] = $now; }
                $fields['closed_by'] = $userId; break;
            case 'archived': $fields['archived_at'] = $now; break;
            case 'scheduled':
                if ($fromStatus === 'frozen') { $fields['frozen_at'] = null; $fields['frozen_by'] = null; }
                break;
            case 'validated':
                if (empty($meeting['validated_at'])) {
                    $fields['validated_at'] = $now;
                    $fields['validated_by'] = $validatedByName ?? 'unknown';
                    $fields['validated_by_user_id'] = $userId;
                }
                break;
        }
        return $fields;
    }

    /**
     * Reset a single meeting or all non-validated meetings for demo purposes.
     *
     * @return array{ok: bool, reset_count: int}
     */
    /**
     * Status whitelist for reset (F09 hardening). Only meetings that have not
     * yet started a real workflow (draft) or are scheduled but not begun
     * (scheduled) can be reset. live/frozen/closed/validated/archived are
     * refused.
     */
    private const RESETTABLE_STATUSES = ['draft', 'scheduled'];

    public function resetDemo(string $meetingId, string $tenantId): void {
        // F09: meeting_id is required at the controller level — service no
        // longer falls back to "reset all non-validated meetings". Defense
        // in depth: even if a future caller forgets the controller gate,
        // the service rejects an unscoped reset.
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            throw new RuntimeException('meeting_not_found');
        }

        $mt = $this->repos->meeting()->findByIdForTenant($meetingId, $tenantId);
        if (!$mt) {
            throw new RuntimeException('meeting_not_found');
        }
        if (!empty($mt['validated_at'])) {
            throw new RuntimeException('Séance validée : reset interdit (séance figée).');
        }

        // F09: status whitelist. Resetting a live or closed meeting was
        // previously accepted — it wiped ballots already cast. That is
        // catastrophic if invoked accidentally during an actual AG.
        $status = (string) ($mt['status'] ?? '');
        if (!in_array($status, self::RESETTABLE_STATUSES, true)) {
            throw new RuntimeException('meeting_status_not_resettable');
        }

        $mid = $mt['id'];
        $this->repos->ballot()->deleteByMeeting($mid, $tenantId);
        $this->repos->voteToken()->deleteByMeetingMotions($mid, $tenantId);
        $this->repos->manualAction()->deleteByMeeting($mid, $tenantId);
        $this->repos->motion()->resetStatesForMeeting($mid, $tenantId);
        $this->repos->meeting()->resetForDemo($mid, $tenantId);
    }
}
