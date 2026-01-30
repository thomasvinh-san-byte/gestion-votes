<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

require_role('operator');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "missing_meeting_id";
    exit;
}

// Exports autorisés uniquement après validation (exigence conformité)
$mt = db_select_one(
    "SELECT validated_at FROM meetings WHERE tenant_id = ? AND id = ?",
    [DEFAULT_TENANT_ID, $meetingId]
);
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
fputs($out, "ï»¿"); // UTF-8 BOM for Excel

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

$rows = db_select_all(
  "SELECT
      m.id AS member_id,
      m.full_name,
      (CASE WHEN m.is_active THEN '1' ELSE '0' END) AS is_active,
      COALESCE(m.voting_power, 0) AS voting_power,
      COALESCE(a.mode::text, 'absent') AS attendance_mode,
      a.checked_in_at,
      a.checked_out_at,
      pr.receiver_member_id AS proxy_to_member_id,
      r.full_name AS proxy_to_name
   FROM members m
   LEFT JOIN attendances a
          ON a.meeting_id = ? AND a.member_id = m.id
   LEFT JOIN proxies pr
          ON pr.meeting_id = ? AND pr.giver_member_id = m.id
   LEFT JOIN members r
          ON r.id = pr.receiver_member_id
   WHERE m.tenant_id = ?
   ORDER BY m.full_name ASC",
  [$meetingId, $meetingId, DEFAULT_TENANT_ID]
);

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
