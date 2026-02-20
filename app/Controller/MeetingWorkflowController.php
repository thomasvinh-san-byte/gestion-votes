<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Security\AuthMiddleware;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\ManualActionRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\VoteTokenRepository;
use AgVote\Service\MeetingWorkflowService;
use AgVote\Service\OfficialResultsService;
use AgVote\WebSocket\EventBroadcaster;

/**
 * Consolidates 6 meeting workflow endpoints.
 *
 * Shared pattern: state machine transitions, workflow validation, MeetingWorkflowService.
 */
final class MeetingWorkflowController extends AbstractController
{
    public function transition(): void
    {
        api_require_role(['operator', 'president', 'admin']);
        $input = api_request('POST');

        $meetingId = api_require_uuid($input, 'meeting_id');
        $toStatus = trim((string)($input['to_status'] ?? ''));

        if ($toStatus === '') {
            api_fail('missing_to_status', 400, ['detail' => 'Le champ to_status est requis.']);
        }

        $validStatuses = ['draft', 'scheduled', 'frozen', 'live', 'paused', 'closed', 'validated', 'archived'];
        if (!in_array($toStatus, $validStatuses, true)) {
            api_fail('invalid_status', 400, [
                'detail' => "Statut '$toStatus' invalide.",
                'valid' => $validStatuses,
            ]);
        }

        $repo = new MeetingRepository();
        $meeting = $repo->findByIdForTenant($meetingId, api_current_tenant_id());

        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }

        $fromStatus = $meeting['status'];

        if ($fromStatus === $toStatus) {
            api_fail('already_in_status', 422, [
                'detail' => "La séance est déjà au statut '$toStatus'.",
            ]);
        }

        // Archived is terminal. No force, no bypass, no exceptions.
        if ($fromStatus === 'archived') {
            api_fail('archived_immutable', 403, [
                'detail' => 'Séance archivée : aucune transition autorisée.',
            ]);
        }

        AuthMiddleware::requireTransition($fromStatus, $toStatus, $meetingId);

        $forceTransition = filter_var($input['force'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $workflowCheck = MeetingWorkflowService::issuesBeforeTransition($meetingId, api_current_tenant_id(), $toStatus);

        if ($forceTransition && api_current_role() !== 'admin') {
            api_fail('force_requires_admin', 403, [
                'detail' => 'Seul un administrateur peut forcer une transition.',
            ]);
        }

        if (!$workflowCheck['can_proceed'] && !$forceTransition) {
            api_fail('workflow_issues', 422, [
                'detail' => 'Transition bloquée par des pré-requis',
                'issues' => $workflowCheck['issues'],
                'warnings' => $workflowCheck['warnings'],
                'hint' => 'Corrigez les issues ou passez force=true pour ignorer (admin uniquement)',
            ]);
        }

        $fields = ['status' => $toStatus];
        $userId = api_current_user_id();

        switch ($toStatus) {
            case 'frozen':
                $fields['frozen_at'] = date('Y-m-d H:i:s');
                $fields['frozen_by'] = $userId;
                break;

            case 'live':
                $now = date('Y-m-d H:i:s');
                if (empty($meeting['started_at'])) {
                    $fields['started_at'] = $now;
                }
                if (!empty($meeting['scheduled_at']) && $meeting['scheduled_at'] > $now) {
                    $fields['scheduled_at'] = $now;
                }
                $fields['opened_by'] = $userId;
                if ($fromStatus === 'paused') {
                    $fields['paused_at'] = null;
                    $fields['paused_by'] = null;
                }
                break;

            case 'paused':
                $fields['paused_at'] = date('Y-m-d H:i:s');
                $fields['paused_by'] = $userId;
                break;

            case 'closed':
                if (empty($meeting['ended_at'])) {
                    $fields['ended_at'] = date('Y-m-d H:i:s');
                }
                $fields['closed_by'] = $userId;
                break;

            case 'archived':
                $fields['archived_at'] = date('Y-m-d H:i:s');
                break;

            case 'scheduled':
                if ($fromStatus === 'frozen') {
                    $fields['frozen_at'] = null;
                    $fields['frozen_by'] = null;
                }
                break;

            case 'validated':
                if (empty($meeting['validated_at'])) {
                    $fields['validated_at'] = date('Y-m-d H:i:s');
                    $fields['validated_by'] = api_current_user()['name'] ?? 'unknown';
                    $fields['validated_by_user_id'] = $userId;
                }
                break;
        }

        $repo->updateFields($meetingId, api_current_tenant_id(), $fields);

        $auditData = [
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'title' => $meeting['title'],
        ];
        if ($forceTransition) {
            $auditData['forced'] = true;
        }
        audit_log('meeting.transition', 'meeting', $meetingId, $auditData, $meetingId);

        try {
            EventBroadcaster::meetingStatusChanged($meetingId, api_current_tenant_id(), $toStatus, $fromStatus);
        } catch (\Throwable $e) {
        }

        api_ok([
            'meeting_id' => $meetingId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'transitioned_at' => date('c'),
            'warnings' => $workflowCheck['warnings'] ?? [],
        ]);
    }

    public function launch(): void
    {
        api_require_role(['operator', 'president', 'admin']);
        $input = api_request('POST');

        $meetingId = api_require_uuid($input, 'meeting_id');

        $repo = new MeetingRepository();
        $tenant = api_current_tenant_id();
        $userId = api_current_user_id();

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $meeting = $repo->lockForUpdate($meetingId, $tenant);

            if (!$meeting) {
                $pdo->rollBack();
                api_fail('meeting_not_found', 404);
            }

            $fromStatus = $meeting['status'];

            $path = [];
            switch ($fromStatus) {
                case 'draft':
                    $path = ['scheduled', 'frozen', 'live'];
                    break;
                case 'scheduled':
                    $path = ['frozen', 'live'];
                    break;
                case 'frozen':
                    $path = ['live'];
                    break;
                case 'live':
                    $pdo->rollBack();
                    api_fail('already_in_status', 422, ['detail' => 'La séance est déjà en cours.']);
                    break;
                default:
                    $pdo->rollBack();
                    api_fail('invalid_launch_status', 422, [
                        'detail' => "Impossible de lancer depuis le statut '$fromStatus'.",
                    ]);
            }

            // Check prerequisites for EACH step in the launch path, not just the final target.
            // Without this, launching from 'draft' would skip motions/attendance checks
            // that are specific to intermediate transitions (draft→scheduled, scheduled→frozen).
            $allIssues = [];
            $allWarnings = [];
            $simulatedFrom = $fromStatus;
            foreach ($path as $step) {
                $stepCheck = MeetingWorkflowService::issuesBeforeTransition($meetingId, $tenant, $step, $simulatedFrom);
                $allIssues = array_merge($allIssues, $stepCheck['issues']);
                $allWarnings = array_merge($allWarnings, $stepCheck['warnings']);
                $simulatedFrom = $step;
            }

            if (count($allIssues) > 0) {
                $pdo->rollBack();
                api_fail('workflow_issues', 422, [
                    'detail' => 'Lancement bloqué par des pré-requis',
                    'issues' => $allIssues,
                    'warnings' => $allWarnings,
                ]);
            }

            $workflowCheck = ['warnings' => $allWarnings];

            $now = date('Y-m-d H:i:s');
            $currentStatus = $fromStatus;

            foreach ($path as $toStatus) {
                AuthMiddleware::requireTransition($currentStatus, $toStatus, $meetingId);

                $fields = ['status' => $toStatus];

                switch ($toStatus) {
                    case 'frozen':
                        $fields['frozen_at'] = $now;
                        $fields['frozen_by'] = $userId;
                        break;
                    case 'live':
                        if (empty($meeting['started_at'])) {
                            $fields['started_at'] = $now;
                        }
                        if (!empty($meeting['scheduled_at']) && $meeting['scheduled_at'] > $now) {
                            $fields['scheduled_at'] = $now;
                        }
                        $fields['opened_by'] = $userId;
                        break;
                }

                $repo->updateFields($meetingId, $tenant, $fields);
                $currentStatus = $toStatus;
            }

            audit_log('meeting.launch', 'meeting', $meetingId, [
                'from_status' => $fromStatus,
                'to_status' => 'live',
                'path' => $path,
                'title' => $meeting['title'],
            ], $meetingId);

            $pdo->commit();

            try {
                EventBroadcaster::meetingStatusChanged($meetingId, $tenant, 'live', $fromStatus);
            } catch (\Throwable $e) {
            }

            api_ok([
                'meeting_id' => $meetingId,
                'from_status' => $fromStatus,
                'to_status' => 'live',
                'path' => $path,
                'transitioned_at' => date('c'),
                'warnings' => $workflowCheck['warnings'] ?? [],
            ]);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function workflowCheck(): void
    {
        api_require_role(['operator', 'president', 'admin', 'viewer']);

        $meetingId = api_require_uuid($_GET, 'meeting_id');
        $toStatus = trim((string)($_GET['to_status'] ?? ''));

        $tenantId = api_current_tenant_id();

        if ($toStatus === '') {
            $result = MeetingWorkflowService::getTransitionReadiness($meetingId, $tenantId);
            api_ok($result);
        } else {
            $result = MeetingWorkflowService::issuesBeforeTransition($meetingId, $tenantId, $toStatus);
            api_ok([
                'to_status' => $toStatus,
                'can_proceed' => $result['can_proceed'],
                'issues' => $result['issues'],
                'warnings' => $result['warnings'],
            ]);
        }
    }

    public function readyCheck(): void
    {
        api_require_role('auditor');

        $meetingId = trim((string)($_GET['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }

        $tenant = api_current_tenant_id();

        $meetingRepo = new MeetingRepository();
        $motionRepo = new MotionRepository();
        $attendanceRepo = new AttendanceRepository();
        $memberRepo = new MemberRepository();
        $ballotRepo = new BallotRepository();

        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenant);
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }

        $checks = [];
        $bad = [];

        // Check 1: Président renseigné
        $pres = trim((string)($meeting['president_name'] ?? ''));
        $checks[] = [
            'passed' => $pres !== '',
            'label' => 'Président renseigné',
            'detail' => $pres !== '' ? $pres : "Aucun président (president_name) n'est renseigné.",
        ];

        // Check 2: Motions ouvertes
        $openCount = $meetingRepo->countOpenMotions($meetingId);
        $checks[] = [
            'passed' => $openCount === 0,
            'label' => 'Motions fermées',
            'detail' => $openCount > 0 ? "Il reste {$openCount} motion(s) ouverte(s). Fermez-les avant validation." : '',
        ];

        // Check 3: Éligibles
        $eligibleCount = $attendanceRepo->countEligible($meetingId);
        $fallbackEligibleUsed = false;
        if ($eligibleCount <= 0) {
            $fallbackEligibleUsed = true;
            $eligibleCount = $memberRepo->countActive($tenant);
        }
        $checks[] = [
            'passed' => !$fallbackEligibleUsed,
            'label' => 'Présences saisies',
            'detail' => $fallbackEligibleUsed ? "Règle de fallback utilisée (tous membres actifs)." : '',
        ];

        // Motions fermées
        $motions = $motionRepo->listClosedForMeetingWithManualTally($meetingId);

        foreach ($motions as $m) {
            $motionId = (string)$m['id'];
            $title = (string)($m['title'] ?? 'Motion');

            $manualTotal = (int)($m['manual_total'] ?? 0);
            $manualFor = (int)($m['manual_for'] ?? 0);
            $manualAg = (int)($m['manual_against'] ?? 0);
            $manualAb = (int)($m['manual_abstain'] ?? 0);

            $manualOk = false;
            if ($manualTotal > 0) {
                $manualOk = (($manualFor + $manualAg + $manualAb) === $manualTotal);
            }

            $eligibleDirect = $ballotRepo->countEligibleDirect($meetingId, $motionId);
            $eligibleProxy = $ballotRepo->countEligibleProxy($meetingId, $motionId);
            $eligibleBallots = $eligibleDirect + $eligibleProxy;

            $ballotsTotal = $ballotRepo->countByMotionId($motionId);
            $missing = max(0, $eligibleCount - $ballotsTotal);
            if ($missing > 0) {
                $bad[] = [
                    'motion_id' => $motionId,
                    'title' => $title,
                    'detail' => "Votes manquants : {$missing} (attendus: {$eligibleCount}, reçus: {$ballotsTotal}).",
                ];
            }

            $invalidDirect = $ballotRepo->countInvalidDirect($meetingId, $motionId);
            $invalidProxy = $ballotRepo->countInvalidProxy($meetingId, $motionId);

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
                    'detail' => "Aucun résultat exploitable: pas de comptage manuel cohérent et aucun bulletin e-vote éligible.",
                ];
            } elseif ($manualTotal > 0 && !$manualOk) {
                $bad[] = [
                    'motion_id' => $motionId,
                    'title' => $title,
                    'detail' => "Comptage manuel incohérent (pour+contre+abst != total).",
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

        api_ok([
            'ready' => $ready,
            'checks' => $checks,
            'can' => $ready,
            'bad_motions' => $bad,
            'meta' => [
                'meeting_id' => $meetingId,
                'eligible_count' => $eligibleCount,
                'fallback_eligible_used' => $fallbackEligibleUsed,
            ],
        ]);
    }

    public function consolidate(): void
    {
        api_require_role(['operator', 'admin']);
        $body = api_request('POST');

        $meetingId = api_require_uuid($body, 'meeting_id');
        $tenantId = api_current_tenant_id();

        $repo = new MeetingRepository();
        $meeting = $repo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }

        // Only closed or validated meetings can be consolidated
        if (!in_array($meeting['status'], ['closed', 'validated'], true)) {
            api_fail('invalid_status_for_consolidation', 422, [
                'detail' => 'Seule une séance clôturée ou validée peut être consolidée.',
                'current_status' => $meeting['status'],
            ]);
        }

        $r = OfficialResultsService::consolidateMeeting($meetingId, $tenantId);

        audit_log('meeting.consolidate', 'meeting', $meetingId, [
            'updated_motions' => $r['updated'],
            'title' => $meeting['title'] ?? '',
        ], $meetingId);

        api_ok(['updated_motions' => $r['updated']]);
    }

    public function resetDemo(): void
    {
        api_require_role(['operator', 'admin']);
        $in = api_request('POST');

        $meetingId = api_require_uuid($in, 'meeting_id');

        $confirm = (string)($in['confirm'] ?? '');
        if ($confirm !== 'RESET') {
            api_fail('missing_confirm', 400, ['detail' => 'Envoyez {confirm:"RESET"} pour éviter les resets accidentels.']);
        }

        $mt = (new MeetingRepository())->findByIdForTenant($meetingId, api_current_tenant_id());
        if (!$mt) {
            api_fail('meeting_not_found', 404);
        }
        if (!empty($mt['validated_at'])) {
            api_fail('meeting_validated', 409, ['detail' => 'Séance validée : reset interdit (séance figée).']);
        }

        $tenantId = api_current_tenant_id();

        db()->beginTransaction();
        try {
            (new BallotRepository())->deleteByMeeting($meetingId, $tenantId);
            (new VoteTokenRepository())->deleteByMeetingMotions($meetingId, $tenantId);
            (new ManualActionRepository())->deleteByMeeting($meetingId, $tenantId);

            (new MotionRepository())->resetStatesForMeeting($meetingId, $tenantId);
            (new MeetingRepository())->resetForDemo($meetingId, $tenantId);

            // Audit log inside the transaction so it can't be lost
            audit_log('meeting.reset_demo', 'meeting', $meetingId, [
                'title' => $mt['title'] ?? '',
                'reset_by' => api_current_user_id(),
            ], $meetingId);

            db()->commit();

            api_ok(['ok' => true, 'meeting_id' => $meetingId]);
        } catch (\Throwable $e) {
            db()->rollBack();
            api_fail('reset_failed', 500, ['detail' => 'Reset demo échoué']);
        }
    }
}
