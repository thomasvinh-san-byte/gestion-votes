<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap.php';
require __DIR__ . '/../../../app/auth.php';

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

$filename = "votes_" . $meetingId . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

$sep = ';';
$out = fopen('php://output', 'w');
fputs($out, "ï»¿"); // UTF-8 BOM for Excel

fputcsv($out, [
    'Résolution',
    'Motion ID',
    'Votant',
    'Member ID',
    'Choix',
    'Poids',
    'Proxy',
    'Proxy source member_id',
    'Horodatage',
    'Source'
], $sep);

// We export ballots (nominatif) as source of truth
$rows = db_select_all(
    "SELECT
        mo.title AS motion_title,
        mo.id AS motion_id,
        mb.full_name AS voter_name,
        b.member_id,
        b.value::text AS value,
        b.weight,
        b.is_proxy_vote,
        b.proxy_source_member_id,
        b.cast_at,
        COALESCE(b.source, 'tablet') AS source
     FROM motions mo
     JOIN meetings mt ON mt.id = mo.meeting_id AND mt.id = ?
     LEFT JOIN ballots b ON b.motion_id = mo.id
     LEFT JOIN members mb ON mb.id = b.member_id
     WHERE mo.meeting_id = ?
     ORDER BY mo.opened_at NULLS LAST, mo.created_at ASC, mb.full_name ASC NULLS LAST",
    [$meetingId, $meetingId]
);

foreach ($rows as $r) {
    if ($r['member_id'] === null) continue;
    fputcsv($out, [
        (string)($r['motion_title'] ?? ''),
        (string)($r['motion_id'] ?? ''),
        (string)($r['voter_name'] ?? ''),
        (string)($r['member_id'] ?? ''),
        (string)($r['value'] ?? ''),
        (string)($r['weight'] ?? ''),
        ((bool)($r['is_proxy_vote'] ?? false)) ? '1' : '0',
        (string)($r['proxy_source_member_id'] ?? ''),
        (string)($r['cast_at'] ?? ''),
        (string)($r['source'] ?? ''),
    ], $sep);
}

fclose($out);
