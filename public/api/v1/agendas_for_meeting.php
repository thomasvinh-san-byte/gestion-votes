<?php
// public/api/v1/agendas_for_meeting.php
require __DIR__ . '/../../../app/api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_fail('method_not_allowed', 405);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

$meetingId = $_GET['meeting_id'] ?? ($input['meeting_id'] ?? '');
$meetingId = trim($meetingId);

if ($meetingId === '') {
    api_fail('missing_meeting_id', 422);
    exit;
}

// Vérifier que la séance existe
$exists = db_scalar("SELECT 1 FROM meetings WHERE id = ?", [$meetingId]);
if (!$exists) {
    api_fail('meeting_not_found', 404);
    exit;
}

global $pdo;
$stmt = $pdo->prepare("
    SELECT id AS agenda_id, title AS agenda_title, idx AS agenda_idx
    FROM agendas
    WHERE meeting_id = ?
    ORDER BY idx ASC
");
$stmt->execute([$meetingId]);
$rows = $stmt->fetchAll();

api_ok([
    'meeting_id' => $meetingId,
    'agendas'    => $rows,
]);
