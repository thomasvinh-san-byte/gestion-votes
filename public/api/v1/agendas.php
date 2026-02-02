<?php
// public/api/v1/agendas.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\AgendaRepository;

api_require_role('operator');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$meetingRepo = new MeetingRepository();
$agendaRepo = new AgendaRepository();

try {
    if ($method === 'GET') {
        $meetingId = trim($_GET['meeting_id'] ?? '');

        if ($meetingId === '') {
            api_fail('missing_meeting_id', 422, [
                'detail' => 'meeting_id est obligatoire.'
            ]);
        }

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

        $meetingId = trim($input['meeting_id'] ?? '');
        if ($meetingId === '') {
            api_fail('missing_meeting_id', 422, [
                'detail' => 'meeting_id est obligatoire.'
            ]);
        }

        if (!$meetingRepo->existsForTenant($meetingId, api_current_tenant_id())) {
            api_fail('meeting_not_found', 404);
        }

        $title = trim($input['title'] ?? '');
        $len   = mb_strlen($title);

        if ($len === 0) {
            api_fail('missing_title', 400, [
                'detail' => 'Le titre du point est obligatoire.'
            ]);
        }
        if ($len > 40) {
            api_fail('title_too_long', 400, [
                'detail' => 'Le titre du point ne doit pas dépasser 40 caractères.'
            ]);
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

} catch (PDOException $e) {
    error_log("Database error in agendas.php: " . $e->getMessage());
    api_fail('database_error', 500, ['detail' => 'Erreur de base de données']);
} catch (Throwable $e) {
    error_log("Unexpected error in agendas.php: " . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
