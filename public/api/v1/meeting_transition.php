<?php
/**
 * POST /api/v1/meeting_transition.php
 *
 * Transition d'état d'une séance selon la machine à états.
 * Respecte la séparation des pouvoirs :
 *   - L'opérateur prépare (draft → scheduled)
 *   - Le président autorise (scheduled → frozen → live → closed → validated)
 *   - L'admin supervise et archive (validated → archived, retours arrière)
 *
 * Body JSON:
 *   { "meeting_id": "uuid", "to_status": "frozen|live|closed|validated|..." }
 *
 * Retourne:
 *   { "ok": true, "data": { "meeting_id": "...", "from_status": "...", "to_status": "...", "transitioned_at": "..." } }
 */
require __DIR__ . '/../../../app/api.php';

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
$meeting = db_select_one(
    "SELECT id, tenant_id, status, title FROM meetings WHERE id = ? AND tenant_id = ?",
    [$meetingId, DEFAULT_TENANT_ID]
);

if (!$meeting) {
    api_fail('meeting_not_found', 404);
}

$fromStatus = $meeting['status'];

if ($fromStatus === $toStatus) {
    api_fail('already_in_status', 422, [
        'detail' => "La séance est déjà au statut '$toStatus'.",
    ]);
}

// Vérifier la transition via la machine à états
AuthMiddleware::requireTransition($fromStatus, $toStatus);

// Construire la requête de mise à jour
$setClauses = ['status = :to_status', 'updated_at = NOW()'];
$params = [
    ':to_status'   => $toStatus,
    ':meeting_id'  => $meetingId,
];

$userId = api_current_user_id();

// Colonnes spécifiques à certaines transitions
switch ($toStatus) {
    case 'frozen':
        $setClauses[] = 'frozen_at = NOW()';
        $setClauses[] = 'frozen_by = :user_id';
        $params[':user_id'] = $userId;
        break;

    case 'live':
        $setClauses[] = 'started_at = COALESCE(started_at, NOW())';
        $setClauses[] = 'opened_by = :user_id';
        $params[':user_id'] = $userId;
        break;

    case 'closed':
        $setClauses[] = 'ended_at = COALESCE(ended_at, NOW())';
        $setClauses[] = 'closed_by = :user_id';
        $params[':user_id'] = $userId;
        break;

    case 'validated':
        $setClauses[] = 'validated_at = NOW()';
        $setClauses[] = 'validated_by = :user_name';
        $setClauses[] = 'validated_by_user_id = :user_id';
        $params[':user_name'] = api_current_user()['name'] ?? 'unknown';
        $params[':user_id']   = $userId;
        break;

    case 'archived':
        $setClauses[] = 'archived_at = NOW()';
        break;

    case 'scheduled':
        // Si on dégèle: effacer frozen_at/frozen_by
        if ($fromStatus === 'frozen') {
            $setClauses[] = 'frozen_at = NULL';
            $setClauses[] = 'frozen_by = NULL';
        }
        break;
}

$sql = "UPDATE meetings SET " . implode(', ', $setClauses) . " WHERE id = :meeting_id";
db_execute($sql, $params);

// Audit
audit_log(
    'meeting.transition',
    'meeting',
    $meetingId,
    [
        'from_status' => $fromStatus,
        'to_status'   => $toStatus,
        'title'       => $meeting['title'],
    ],
    $meetingId
);

api_ok([
    'meeting_id'      => $meetingId,
    'from_status'     => $fromStatus,
    'to_status'       => $toStatus,
    'transitioned_at' => date('c'),
]);
