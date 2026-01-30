<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\AttendanceRepository;

api_require_role('operator');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "missing_meeting_id";
    exit;
}

// Exports autorisés uniquement après validation (exigence conformité)
$meetingRepo = new MeetingRepository();
$mt = $meetingRepo->findByIdForTenant($meetingId, api_current_tenant_id());
if (!$mt) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "meeting_not_found";
    exit;
}
if (empty($mt['validated_at'])) {
    http_response_code(409);
    header('Content-Type: text/plain; charset=utf-8');
    echo "meeting_not_validated";
    exit;
}

$attendanceRepo = new AttendanceRepository();
$rows = $attendanceRepo->listExportForMeeting($meetingId);

$filename = "presence_" . $meetingId . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

$sep = ';';
$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

// Header
fputcsv($out, ['Nom', 'Pouvoir', 'Présence', 'Arrivée', 'Départ', 'Mandataire', 'Procurations détenues'], $sep);

foreach ($rows as $r) {
    fputcsv($out, [
        (string)($r['full_name'] ?? ''),
        (string)($r['voting_power'] ?? ''),
        (string)($r['attendance_mode'] ?? ''),
        (string)($r['checked_in_at'] ?? ''),
        (string)($r['checked_out_at'] ?? ''),
        (string)($r['proxy_to_name'] ?? ''),
        (string)($r['proxies_received'] ?? '0'),
    ], $sep);
}

fclose($out);
