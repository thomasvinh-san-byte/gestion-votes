<?php
// public/api/v1/agendas_for_meeting.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\AgendaRepository;

// Public access: agenda may be displayed on public/projection screen
api_require_role('public');

$q = api_request('GET');
$meetingId = api_require_uuid($q, 'meeting_id');

$meetingRepo = new MeetingRepository();
if (!$meetingRepo->existsForTenant($meetingId, api_current_tenant_id())) {
    api_fail('meeting_not_found', 404);
}

$agendaRepo = new AgendaRepository();
$rows = $agendaRepo->listForMeetingCompact($meetingId);

api_ok([
    'meeting_id' => $meetingId,
    'agendas'    => $rows,
]);
