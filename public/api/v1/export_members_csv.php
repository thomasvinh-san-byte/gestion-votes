<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;

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

$filename = "members_" . $meetingId . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

$sep = ';';
$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

fputcsv($out, [
  'Member ID',
  'Nom',
  'Actif',
  'Pouvoir de vote',
  'Mode présence',
  'Entrée',
  'Sortie',
  'Représenté par (member_id)',
  'Représenté par (nom)',
], $sep);

$memberRepo = new MemberRepository();
$rows = $memberRepo->listExportForMeeting($meetingId, api_current_tenant_id());

foreach ($rows as $r) {
  fputcsv($out, [
    (string)($r['member_id'] ?? ''),
    (string)($r['full_name'] ?? ''),
    (string)($r['is_active'] ?? '0'),
    (string)($r['voting_power'] ?? 0),
    (string)($r['attendance_mode'] ?? 'absent'),
    (string)($r['checked_in_at'] ?? ''),
    (string)($r['checked_out_at'] ?? ''),
    (string)($r['proxy_to_member_id'] ?? ''),
    (string)($r['proxy_to_name'] ?? ''),
  ], $sep);
}

fclose($out);
