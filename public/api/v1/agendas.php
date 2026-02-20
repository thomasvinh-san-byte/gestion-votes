<?php
// public/api/v1/agendas.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\AgendaRepository;
use AgVote\Core\Validation\Schemas\ValidationSchemas;

api_require_role('operator');

$data = api_request('GET', 'POST');
$method = api_method();
$meetingRepo = new MeetingRepository();
$agendaRepo = new AgendaRepository();

try {
    if ($method === 'GET') {
        $meetingId = api_require_uuid($data, 'meeting_id');

        if (!$meetingRepo->existsForTenant($meetingId, api_current_tenant_id())) {
            api_fail('meeting_not_found', 404);
        }

        $rows = $agendaRepo->listForMeeting($meetingId);
        api_ok(['agendas' => $rows]);

    } elseif ($method === 'POST') {
        $v = ValidationSchemas::agenda()->validate($data);
        $v->failIfInvalid();

        $meetingId = $v->get('meeting_id');
        $title     = $v->get('title');

        if (!$meetingRepo->existsForTenant($meetingId, api_current_tenant_id())) {
            api_fail('meeting_not_found', 404);
        }

        $id  = $agendaRepo->generateUuid();
        $idx = $agendaRepo->nextIdx($meetingId);

        $agendaRepo->create($id, api_current_tenant_id(), $meetingId, $idx, $title);

        audit_log('agenda_created', 'agenda', $id, [
            'meeting_id' => $meetingId,
            'idx'        => $idx,
            'title'      => $title,
        ]);

        api_ok([
            'agenda_id' => $id,
            'idx'       => $idx,
            'title'     => $title,
        ], 201);

    }

} catch (Throwable $e) {
    error_log('Error in agendas.php: ' . $e->getMessage());
    api_fail('server_error', 500, ['detail' => $e->getMessage()]);
}
