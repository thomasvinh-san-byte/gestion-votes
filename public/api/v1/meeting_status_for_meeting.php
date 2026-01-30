<?php
// public/api/v1/meeting_status_for_meeting.php
require __DIR__ . '/../../../app/api.php';
require __DIR__ . '/../../../app/services/MeetingValidator.php';
require __DIR__ . '/../../../app/services/NotificationsService.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_err('method_not_allowed', 405);
}

require_role('auditor');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '') json_err('missing_meeting_id', 422);

$meeting = db_select_one(
    "SELECT id AS meeting_id, title AS meeting_title, status AS meeting_status,
            started_at, ended_at, archived_at, validated_at,
            president_name, ready_to_sign
     FROM meetings
     WHERE tenant_id = ? AND id = ?",
    [DEFAULT_TENANT_ID, $meetingId]
);
if (!$meeting) json_err('meeting_not_found', 404);

// Recalcul côté lecture (inclut président + consolidation)
$validation = MeetingValidator::canBeValidated((string)$meetingId, DEFAULT_TENANT_ID);
$readyToSign = (bool)($validation['can'] ?? false);

// Notifications readiness (sans spam)
NotificationsService::emitReadinessTransitions((string)$meetingId, $validation);

$signStatus = 'not_ready';
$signMessage = '';
if (!empty($meeting['validated_at'])) {
    $signStatus = 'validated';
    $signMessage = 'Séance validée.';
} elseif ($readyToSign) {
    $signStatus = 'ready';
    $signMessage = 'Tout est prêt à être signé.';
} else {
    $signStatus = 'not_ready';
    $signMessage = 'Préparation incomplète.';
}

json_ok([
    'meeting_id' => $meeting['meeting_id'],
    'meeting_title' => $meeting['meeting_title'],
    'meeting_status' => $meeting['meeting_status'],
    'started_at' => $meeting['started_at'],
    'ended_at' => $meeting['ended_at'],
    'archived_at' => $meeting['archived_at'],
    'validated_at' => $meeting['validated_at'],
    'president_name' => $meeting['president_name'],
    'ready_to_sign' => $readyToSign,
    'sign_status' => $signStatus,
    'sign_message' => $signMessage,
]);