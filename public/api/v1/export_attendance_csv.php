<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

api_require_role('operator');

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
    [api_current_tenant_id(), $meetingId]
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

$tenantId = defined('api_current_tenant_id()') ? api_current_tenant_id() : null;

$rows = db_select_all(
    "SELECT
        m.id AS member_id,
        m.full_name,
        m.voting_power,
        COALESCE(a.mode::text, 'absent') AS attendance_mode,
        a.checked_in_at,
        a.checked_out_at,
        pr.receiver_member_id AS proxy_to_member_id,
        r.full_name AS proxy_to_name,
        COALESCE(rc.cnt, 0) AS proxies_received
     FROM members m
     JOIN meetings mt ON mt.id = ? AND mt.tenant_id = m.tenant_id
     LEFT JOIN attendances a ON a.meeting_id = mt.id AND a.member_id = m.id
     LEFT JOIN proxies pr ON pr.meeting_id = mt.id AND pr.giver_member_id = m.id AND pr.revoked_at IS NULL
     LEFT JOIN members r ON r.id = pr.receiver_member_id
     LEFT JOIN (
        SELECT receiver_member_id, COUNT(*)::int AS cnt
        FROM proxies
        WHERE meeting_id = ? AND revoked_at IS NULL
        GROUP BY receiver_member_id
     ) rc ON rc.receiver_member_id = m.id
     WHERE m.tenant_id = mt.tenant_id AND m.is_active = true
     ORDER BY m.full_name ASC",
    [$meetingId, $meetingId]
);

$filename = "presence_" . $meetingId . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

$sep = ';';
$out = fopen('php://output', 'w');
fputs($out, "ï»¿"); // UTF-8 BOM for Excel

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
