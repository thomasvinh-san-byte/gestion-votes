<?php
// public/api/v1/meetings_update.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

api_require_role('operator');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    api_fail('method_not_allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$meetingId = trim((string)($input['meeting_id'] ?? ''));

api_guard_meeting_not_validated($meetingId);

if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 400, ['detail' => 'meeting_id est obligatoire (uuid).']);
}

$title  = array_key_exists('title', $input) ? trim((string)$input['title']) : null;
$status = array_key_exists('status', $input) ? trim((string)$input['status']) : null;

if ($title !== null) {
    $len = mb_strlen($title);
    if ($len === 0) api_fail('missing_title', 400, ['detail' => 'Le titre de la séance est obligatoire.']);
    if ($len > 120) api_fail('title_too_long', 400, ['detail' => 'Titre trop long (120 max).']);
}

if ($status !== null) {
    $allowed = ['draft','scheduled','live','closed','archived'];
    if (!in_array($status, $allowed, true)) {
        api_fail('invalid_status', 400, ['detail' => 'Statut invalide.', 'allowed' => $allowed]);
    }
}

$current = db_select_one(
    "SELECT status FROM meetings WHERE tenant_id=:tid AND id=:id",
    [':tid' => api_current_tenant_id(), ':id' => $meetingId]
);
if (!$current) api_fail('meeting_not_found', 404);

$currentStatus = (string)$current['status'];
if ($currentStatus === 'archived') {
    api_fail('meeting_archived_locked', 409, ['detail' => 'Séance archivée : modification interdite.']);
}

if ($status !== null) {
    $allowedTransitions = [
        'draft'     => ['scheduled','live','closed'],
        'scheduled' => ['live','closed'],
        'live'      => ['closed'],
        'closed'    => ['archived'],
        'archived'  => [],
    ];

    $next = $status;
    if (!isset($allowedTransitions[$currentStatus]) || !in_array($next, $allowedTransitions[$currentStatus], true)) {
        api_fail('invalid_transition', 409, [
            'detail' => 'Transition de statut interdite.',
            'current' => $currentStatus,
            'next' => $next,
            'allowed_next' => $allowedTransitions[$currentStatus] ?? [],
        ]);
    }
}

$fields = [];
$params = [':tid' => api_current_tenant_id(), ':id' => $meetingId];

if ($title !== null) {
    $fields[] = "title = :title";
    $params[':title'] = $title;
}
if ($status !== null) {
    $fields[] = "status = :status";
    $params[':status'] = $status;
}

if (!$fields) {
    api_ok(['updated' => false, 'meeting_id' => $meetingId]);
}

$fields[] = "updated_at = now()";

$sql = "UPDATE meetings SET " . implode(", ", $fields) . " WHERE tenant_id=:tid AND id=:id";
$updated = db_execute($sql, $params);

audit_log('meeting_updated', 'meeting', $meetingId, [
    'fields' => array_keys($input),
    'from_status' => $currentStatus,
    'to_status' => $status,
]);

api_ok(['updated' => $updated > 0, 'meeting_id' => $meetingId]);
