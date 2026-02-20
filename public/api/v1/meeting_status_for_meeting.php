<?php
// public/api/v1/meeting_status_for_meeting.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Service\MeetingValidator;
use AgVote\Service\NotificationsService;

api_request('GET');

api_require_role('auditor');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '') api_fail('missing_meeting_id', 422);

$repo = new MeetingRepository();
$meeting = $repo->findStatusFields($meetingId, api_current_tenant_id());
if (!$meeting) api_fail('meeting_not_found', 404);

// Recalcul côté lecture (inclut président + consolidation)
$validation = MeetingValidator::canBeValidated((string)$meetingId, api_current_tenant_id());
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

api_ok([
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
