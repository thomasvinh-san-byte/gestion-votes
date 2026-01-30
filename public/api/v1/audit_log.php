<?php
declare(strict_types=1);

/**
 * audit_log.php - Journal d'audit avec pagination
 *
 * GET /api/v1/audit_log.php?meeting_id={uuid}&limit=50&offset=0
 *
 * Retourne les événements d'audit formatés pour affichage timeline.
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;

api_require_role(['auditor', 'admin', 'operator', 'president']);

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 400);
}

$limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

$tenantId = api_current_tenant_id();

$repo = new MeetingRepository();

// Vérifier que la séance existe
$meeting = $repo->findByIdForTenant($meetingId, $tenantId);
if (!$meeting) {
    api_fail('meeting_not_found', 404);
}

// Récupérer les événements liés à cette séance
$events = $repo->listAuditEventsForLog($tenantId, $meetingId, $limit, $offset);

// Compter le total
$total = $repo->countAuditEventsForLog($tenantId, $meetingId);

// Formatter les événements
$formatted = [];
foreach ($events as $e) {
    $payload = [];
    if (!empty($e['payload'])) {
        try {
            $payload = is_string($e['payload'])
                ? json_decode($e['payload'], true) ?? []
                : (array)$e['payload'];
        } catch (\Throwable $ex) {
            $payload = [];
        }
    }

    // Déterminer le label de l'action
    $actionLabels = [
        'meeting_created' => 'Séance créée',
        'meeting_updated' => 'Séance modifiée',
        'meeting_validated' => 'Séance validée',
        'meeting_archived' => 'Séance archivée',
        'motion_created' => 'Résolution créée',
        'motion_opened' => 'Vote ouvert',
        'motion_closed' => 'Vote clôturé',
        'ballot_cast' => 'Vote enregistré',
        'manual_vote' => 'Vote manuel',
        'attendance_updated' => 'Présence modifiée',
        'attendances_bulk_update' => 'Présences modifiées en masse',
        'proxy_created' => 'Procuration créée',
        'proxy_deleted' => 'Procuration supprimée',
        'speech_requested' => 'Demande de parole',
        'speech_granted' => 'Parole accordée',
        'speech_ended' => 'Fin de parole',
        'incident_reported' => 'Incident signalé',
        'quorum_reached' => 'Quorum atteint',
        'quorum_lost' => 'Quorum perdu',
    ];

    $actionLabel = $actionLabels[$e['action']] ?? ucfirst(str_replace('_', ' ', $e['action']));

    // Construire le message
    $message = $payload['message'] ?? $payload['detail'] ?? '';
    if (empty($message) && isset($payload['member_name'])) {
        $message = $payload['member_name'];
    }
    if (empty($message) && isset($payload['title'])) {
        $message = $payload['title'];
    }

    // Déterminer l'acteur
    $actor = $e['actor_role'] ?? 'système';
    if (!empty($payload['actor_name'])) {
        $actor = $payload['actor_name'];
    }

    $formatted[] = [
        'id' => $e['id'],
        'timestamp' => $e['created_at'],
        'action' => $e['action'],
        'action_label' => $actionLabel,
        'resource_type' => $e['resource_type'],
        'resource_id' => $e['resource_id'],
        'actor' => $actor,
        'actor_role' => $e['actor_role'],
        'message' => $message,
        'ip_address' => $e['ip_address'],
        'payload' => $payload,
    ];
}

api_ok([
    'meeting_id' => $meetingId,
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset,
    'events' => $formatted,
]);
