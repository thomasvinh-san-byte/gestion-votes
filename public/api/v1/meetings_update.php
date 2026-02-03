<?php
// public/api/v1/meetings_update.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;

api_require_role('operator');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    api_fail('method_not_allowed', 405);
}

$input = json_decode($GLOBALS['__ag_vote_raw_body'] ?? file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$meetingId = trim((string)($input['meeting_id'] ?? ''));

api_guard_meeting_not_validated($meetingId);

if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 400, ['detail' => 'meeting_id est obligatoire (uuid).']);
}

$title  = array_key_exists('title', $input) ? trim((string)$input['title']) : null;
$status = array_key_exists('status', $input) ? trim((string)$input['status']) : null;
$presidentName = array_key_exists('president_name', $input) ? trim((string)$input['president_name']) : null;
$scheduledAt = array_key_exists('scheduled_at', $input) ? trim((string)$input['scheduled_at']) : null;

if ($title !== null) {
    $len = mb_strlen($title);
    if ($len === 0) api_fail('missing_title', 400, ['detail' => 'Le titre de la séance est obligatoire.']);
    if ($len > 120) api_fail('title_too_long', 400, ['detail' => 'Titre trop long (120 max).']);
}

if ($presidentName !== null && mb_strlen($presidentName) > 200) {
    api_fail('president_name_too_long', 400, ['detail' => 'Nom du président trop long (200 max).']);
}

if ($status !== null) {
    $allowed = ['draft','scheduled','live','closed','archived'];
    if (!in_array($status, $allowed, true)) {
        api_fail('invalid_status', 400, ['detail' => 'Statut invalide.', 'allowed' => $allowed]);
    }
}

$repo = new MeetingRepository();
$current = $repo->findByIdForTenant($meetingId, api_current_tenant_id());
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
if ($title !== null) {
    $fields['title'] = $title;
}
if ($status !== null) {
    $fields['status'] = $status;
}
if ($presidentName !== null) {
    $fields['president_name'] = $presidentName;
}
if ($scheduledAt !== null) {
    $fields['scheduled_at'] = $scheduledAt ?: null;
}

if (!$fields) {
    api_ok(['updated' => false, 'meeting_id' => $meetingId]);
}

$updated = $repo->updateFields($meetingId, api_current_tenant_id(), $fields);

audit_log('meeting_updated', 'meeting', $meetingId, [
    'fields' => array_keys($input),
    'from_status' => $currentStatus,
    'to_status' => $status,
]);

api_ok(['updated' => $updated > 0, 'meeting_id' => $meetingId]);
