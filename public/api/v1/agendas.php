<?php
// public/api/v1/agendas.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\AgendaRepository;
use AgVote\Core\Validation\Schemas\ValidationSchemas;

api_require_role('operator');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$meetingRepo = new MeetingRepository();
$agendaRepo = new AgendaRepository();

try {
    if ($method === 'GET') {
        $q = api_request('GET');
        $meetingId = api_require_uuid($q, 'meeting_id');

        if (!$meetingRepo->existsForTenant($meetingId, api_current_tenant_id())) {
            api_fail('meeting_not_found', 404);
        }

        $rows = $agendaRepo->listForMeeting($meetingId);
        api_ok(['agendas' => $rows]);

    } elseif ($method === 'POST') {
        $input = json_decode($GLOBALS['__ag_vote_raw_body'] ?? file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $v = ValidationSchemas::agenda()->validate($input);
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

    } else {
        api_fail('method_not_allowed', 405);
    }

} catch (Throwable $e) {
    error_log('Error in agendas.php: ' . $e->getMessage());
    api_fail('server_error', 500, ['detail' => $e->getMessage()]);
}
