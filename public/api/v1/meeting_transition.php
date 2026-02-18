<?php
/**
 * POST /api/v1/meeting_transition.php
 *
 * Transition d'état d'une séance selon la machine à états.
 * Now includes workflow validation (Helios-style issues_before_freeze).
 */
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Service\MeetingWorkflowService;
use AgVote\WebSocket\EventBroadcaster;

$input = api_request('POST');

// Authentification minimale: operator ou supérieur
api_require_role(['operator', 'president', 'admin']);

// Validation
$meetingId = api_require_uuid($input, 'meeting_id');
$toStatus  = trim((string)($input['to_status'] ?? ''));

if ($toStatus === '') {
    api_fail('missing_to_status', 400, ['detail' => 'Le champ to_status est requis.']);
}

$validStatuses = ['draft', 'scheduled', 'frozen', 'live', 'closed', 'validated', 'archived'];
if (!in_array($toStatus, $validStatuses, true)) {
    api_fail('invalid_status', 400, [
        'detail' => "Statut '$toStatus' invalide.",
        'valid'  => $validStatuses,
    ]);
}

// Charger la séance
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

// Vérifier la transition via la machine à états (avec contexte séance pour les rôles de séance)
AuthMiddleware::requireTransition($fromStatus, $toStatus, $meetingId);

// Workflow validation (Helios-style issues_before_transition)
$forceTransition = filter_var($input['force'] ?? false, FILTER_VALIDATE_BOOLEAN);
$workflowCheck = MeetingWorkflowService::issuesBeforeTransition($meetingId, api_current_tenant_id(), $toStatus);

// Only admin may force-bypass workflow guards
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

// Construire les champs de mise à jour
$fields = ['status' => $toStatus];

$userId = api_current_user_id();

// Colonnes spécifiques à certaines transitions
switch ($toStatus) {
    case 'frozen':
        $fields['frozen_at'] = date('Y-m-d H:i:s');
        $fields['frozen_by'] = $userId;
        break;

    case 'live':
        $now = date('Y-m-d H:i:s');
        // started_at = COALESCE(started_at, NOW()) — only set if not already set
        if (empty($meeting['started_at'])) {
            $fields['started_at'] = $now;
        }
        // If scheduled_at is in the future, set it to now to satisfy meetings_times_ok constraint
        if (!empty($meeting['scheduled_at']) && $meeting['scheduled_at'] > $now) {
            $fields['scheduled_at'] = $now;
        }
        $fields['opened_by'] = $userId;
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
        // Si on dégèle: effacer frozen_at/frozen_by
        if ($fromStatus === 'frozen') {
            $fields['frozen_at'] = null;
            $fields['frozen_by'] = null;
        }
        break;

    case 'validated':
        // Si on dé-archive: effacer archived_at, garder validated_at
        if ($fromStatus === 'archived') {
            $fields['archived_at'] = null;
        } elseif (empty($meeting['validated_at'])) {
            // Transition normale closed → validated
            $fields['validated_at'] = date('Y-m-d H:i:s');
            $fields['validated_by'] = api_current_user()['name'] ?? 'unknown';
            $fields['validated_by_user_id'] = $userId;
        }
        break;
}

$repo->updateFields($meetingId, api_current_tenant_id(), $fields);

// Audit
$auditData = [
    'from_status' => $fromStatus,
    'to_status'   => $toStatus,
    'title'       => $meeting['title'],
];
if ($forceTransition) {
    $auditData['forced'] = true;
}
audit_log('meeting.transition', 'meeting', $meetingId, $auditData, $meetingId);

// Broadcast WebSocket event for real-time updates
try {
    EventBroadcaster::meetingStatusChanged($meetingId, api_current_tenant_id(), $toStatus, $fromStatus);
} catch (\Throwable $e) {
    // Don't fail if broadcast fails
}

api_ok([
    'meeting_id'      => $meetingId,
    'from_status'     => $fromStatus,
    'to_status'       => $toStatus,
    'transitioned_at' => date('c'),
    'warnings'        => $workflowCheck['warnings'] ?? [],
]);
