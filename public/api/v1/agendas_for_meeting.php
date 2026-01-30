<?php
// public/api/v1/agendas_for_meeting.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\AgendaRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_fail('method_not_allowed', 405);
    exit;
}

$meetingId = trim($_GET['meeting_id'] ?? '');

if ($meetingId === '') {
    api_fail('missing_meeting_id', 422);
    exit;
}

$meetingRepo = new MeetingRepository();
if (!$meetingRepo->existsForTenant($meetingId, api_current_tenant_id())) {
    api_fail('meeting_not_found', 404);
    exit;
}

$agendaRepo = new AgendaRepository();
$rows = $agendaRepo->listForMeetingCompact($meetingId);

api_ok([
    'meeting_id' => $meetingId,
    'agendas'    => $rows,
]);
