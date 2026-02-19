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

if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 400, ['detail' => 'meeting_id est obligatoire (uuid).']);
}

api_guard_meeting_not_validated($meetingId);

$title  = array_key_exists('title', $input) ? trim((string)$input['title']) : null;
$presidentName = array_key_exists('president_name', $input) ? trim((string)$input['president_name']) : null;
$scheduledAt = array_key_exists('scheduled_at', $input) ? trim((string)$input['scheduled_at']) : null;
$meetingType = array_key_exists('meeting_type', $input) ? trim((string)$input['meeting_type']) : null;

// Status transitions must go through meeting_transition.php (proper state machine
// with role checks, workflow guards, and complete state coverage).
if (array_key_exists('status', $input)) {
    api_fail('status_via_transition', 400, [
        'detail' => 'Les transitions de statut doivent passer par /api/v1/meeting_transition.php.',
    ]);
}

if ($title !== null) {
    $len = mb_strlen($title);
    if ($len === 0) api_fail('missing_title', 400, ['detail' => 'Le titre de la séance est obligatoire.']);
    if ($len > 120) api_fail('title_too_long', 400, ['detail' => 'Titre trop long (120 max).']);
}

if ($presidentName !== null && mb_strlen($presidentName) > 200) {
    api_fail('president_name_too_long', 400, ['detail' => 'Nom du président trop long (200 max).']);
}

$validMeetingTypes = ['ag_ordinaire', 'ag_extraordinaire', 'conseil', 'bureau', 'autre'];
if ($meetingType !== null && !in_array($meetingType, $validMeetingTypes, true)) {
    api_fail('invalid_meeting_type', 400, ['detail' => 'Type de séance invalide.', 'valid_types' => $validMeetingTypes]);
}

$repo = new MeetingRepository();
$current = $repo->findByIdForTenant($meetingId, api_current_tenant_id());
if (!$current) api_fail('meeting_not_found', 404);

$currentStatus = (string)$current['status'];
if ($currentStatus === 'archived') {
    api_fail('meeting_archived_locked', 409, ['detail' => 'Séance archivée : modification interdite.']);
}

$fields = [];
if ($title !== null) {
    $fields['title'] = $title;
}
if ($presidentName !== null) {
    $fields['president_name'] = $presidentName;
}
if ($scheduledAt !== null) {
    $fields['scheduled_at'] = $scheduledAt ?: null;
}
if ($meetingType !== null) {
    $fields['meeting_type'] = $meetingType;
}

if (!$fields) {
    api_ok(['updated' => false, 'meeting_id' => $meetingId]);
}

$updated = $repo->updateFields($meetingId, api_current_tenant_id(), $fields);

audit_log('meeting_updated', 'meeting', $meetingId, [
    'fields' => array_keys($fields),
]);

api_ok(['updated' => $updated > 0, 'meeting_id' => $meetingId]);
