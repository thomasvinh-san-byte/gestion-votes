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
            throw new RuntimeException("La séance est déjà au statut '{$toStatus}'.");
        }
        if ($fromStatus === 'archived') {
            throw new RuntimeException('Séance archivée : aucune transition autorisée.');
        }

        $workflowCheck = (new MeetingWorkflowService())->issuesBeforeTransition($meetingId, $tenantId, $toStatus);

        return [
            'meeting_id' => $meetingId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'meeting' => $meeting,
            'workflow_check' => $workflowCheck,
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

        $path = match ($fromStatus) {
            'draft' => ['scheduled', 'frozen', 'live'],
            'scheduled' => ['frozen', 'live'],
            'frozen' => ['live'],
            'live' => throw new RuntimeException('La séance est déjà en cours.'),
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

        // Check 1: Président renseigné
        $pres = trim((string) ($meeting['president_name'] ?? ''));
        $checks[] = [
            'passed' => $pres !== '',
            'label' => 'Président renseigné',
            'detail' => $pres !== '' ? $pres : "Aucun président (president_name) n'est renseigné.",
        ];

        // Check 2: Motions ouvertes
        $openCount = $statsRepo->countOpenMotions($meetingId, $tenantId);
        $checks[] = [
            'passed' => $openCount === 0,
            'label' => 'Motions fermées',
            'detail' => $openCount > 0 ? "Il reste {$openCount} motion(s) ouverte(s). Fermez-les avant validation." : '',
        ];

        // Check 3: Éligibles
        $eligibleCount = $attendanceRepo->countEligible($meetingId, $tenantId);
        $fallbackEligibleUsed = false;
        if ($eligibleCount <= 0) {
            $fallbackEligibleUsed = true;
            $eligibleCount = $memberRepo->countActive($tenantId);
        }
        $checks[] = [
            'passed' => !$fallbackEligibleUsed,
            'label' => 'Présences saisies',
            'detail' => $fallbackEligibleUsed ? 'Règle de fallback utilisée (tous membres actifs).' : '',
        ];

        // Motions fermées
        $motions = $motionRepo->listClosedForMeetingWithManualTally($meetingId, $tenantId);

        foreach ($motions as $m) {
            $motionId = (string) $m['id'];
            $title = (string) ($m['title'] ?? 'Motion');

            $manualTotal = (int) ($m['manual_total'] ?? 0);
            $manualFor = (int) ($m['manual_for'] ?? 0);
            $manualAg = (int) ($m['manual_against'] ?? 0);
            $manualAb = (int) ($m['manual_abstain'] ?? 0);

            $manualOk = false;
            if ($manualTotal > 0) {
                $manualOk = (($manualFor + $manualAg + $manualAb) === $manualTotal);
            }

            $eligibleDirect = $ballotRepo->countEligibleDirect($meetingId, $motionId, $tenantId);
            $eligibleProxy = $ballotRepo->countEligibleProxy($meetingId, $motionId, $tenantId);
            $eligibleBallots = $eligibleDirect + $eligibleProxy;

            $ballotsTotal = $ballotRepo->countByMotionId($motionId, $tenantId);
            $missing = max(0, $eligibleCount - $ballotsTotal);
            if ($missing > 0) {
                $bad[] = [
                    'motion_id' => $motionId,
                    'title' => $title,
                    'detail' => "Votes manquants : {$missing} (attendus: {$eligibleCount}, reçus: {$ballotsTotal}).",
                ];
            }

            $invalidDirect = $ballotRepo->countInvalidDirect($meetingId, $motionId, $tenantId);
            $invalidProxy = $ballotRepo->countInvalidProxy($meetingId, $motionId, $tenantId);

            if ($invalidDirect > 0 || $invalidProxy > 0) {
                $bad[] = [
                    'motion_id' => $motionId,
                    'title' => $title,
                    'detail' => "Bulletins non éligibles détectés (direct: {$invalidDirect}, procuration: {$invalidProxy}).",
                ];
            }

            if (!$manualOk && $eligibleBallots <= 0) {
                $bad[] = [
                    'motion_id' => $motionId,
                    'title' => $title,
                    'detail' => 'Aucun résultat exploitable: pas de comptage manuel cohérent et aucun bulletin e-vote éligible.',
                ];
            } elseif ($manualTotal > 0 && !$manualOk) {
                $bad[] = [
                    'motion_id' => $motionId,
                    'title' => $title,
                    'detail' => 'Comptage manuel incohérent (pour+contre+abst != total).',
                ];
            }
        }

        foreach ($bad as $b) {
            $checks[] = ['passed' => false, 'label' => $b['title'], 'detail' => $b['detail']];
        }

        if (count($bad) === 0 && count($motions) > 0) {
            $checks[] = ['passed' => true, 'label' => 'Résultats exploitables', 'detail' => count($motions) . ' motion(s) avec résultat valide.'];
        }

        $ready = true;
        foreach ($checks as $c) {
            if (!$c['passed']) {
                $ready = false;
                break;
            }
        }

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
     * Reset a single meeting or all non-validated meetings for demo purposes.
     *
     * @return array{ok: bool, reset_count: int}
     */
    public function resetDemo(string $meetingId, string $tenantId): void {
        if ($meetingId !== '' && api_is_uuid($meetingId)) {
            $mt = $this->repos->meeting()->findByIdForTenant($meetingId, $tenantId);
            if (!$mt) {
                throw new RuntimeException('meeting_not_found');
            }
            if (!empty($mt['validated_at'])) {
                throw new RuntimeException('Séance validée : reset interdit (séance figée).');
            }
            $meetings = [$mt];
        } else {
            $all = $this->repos->meeting()->listByTenant($tenantId);
            $meetings = array_filter($all, fn($m) => empty($m['validated_at']));
        }

        foreach ($meetings as $mt) {
            $mid = $mt['id'];
            $this->repos->ballot()->deleteByMeeting($mid, $tenantId);
            $this->repos->voteToken()->deleteByMeetingMotions($mid, $tenantId);
            $this->repos->manualAction()->deleteByMeeting($mid, $tenantId);
            $this->repos->motion()->resetStatesForMeeting($mid, $tenantId);
            $this->repos->meeting()->resetForDemo($mid, $tenantId);
        }
    }
}
