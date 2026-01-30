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

$filename = "ballots_audit_" . $meetingId . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

$sep = ';';
$out = fopen('php://output', 'w');
fputs($out, "ï»¿"); // UTF-8 BOM for Excel

fputcsv($out, [
  'Ballot ID',
  'Motion ID',
  'Résolution',
  'Member ID',
  'Votant',
  'Mode présence',
  'Choix',
  'Poids',
  'Proxy vote',
  'Proxy source member_id',
  'Horodatage vote',
  'Source vote',
  'Token ID',
  'Token hash (prefix)',
  'Token expires_at',
  'Token used_at',
  'Justification (manual)',
], $sep);

// Ballots + liaison token (best-effort) + justification manual (best-effort)
$rows = db_select_all(
  "SELECT
      b.id AS ballot_id,
      mo.id AS motion_id,
      mo.title AS motion_title,
      b.member_id,
      mb.full_name AS voter_name,
      COALESCE(a.mode::text, 'absent') AS attendance_mode,
      b.value::text AS value,
      b.weight,
      b.is_proxy_vote,
      b.proxy_source_member_id,
      b.cast_at,
      COALESCE(b.source, 'tablet') AS source,
      vt.id AS token_id,
      LEFT(vt.token_hash, 12) AS token_hash_prefix,
      vt.expires_at AS token_expires_at,
      vt.used_at AS token_used_at,
      ma.justification AS manual_justification
   FROM ballots b
   JOIN motions mo ON mo.id = b.motion_id
   LEFT JOIN members mb ON mb.id = b.member_id
   LEFT JOIN attendances a ON a.meeting_id = mo.meeting_id AND a.member_id = b.member_id
   LEFT JOIN LATERAL (
      SELECT id, token_hash, expires_at, used_at
      FROM vote_tokens
      WHERE motion_id = b.motion_id AND member_id = b.member_id
      ORDER BY used_at DESC NULLS LAST, created_at DESC
      LIMIT 1
   ) vt ON true
   LEFT JOIN LATERAL (
      SELECT justification
      FROM manual_actions
      WHERE meeting_id = mo.meeting_id AND action_type = 'manual_vote'
        AND motion_id = b.motion_id AND member_id = b.member_id
      ORDER BY created_at DESC
      LIMIT 1
   ) ma ON true
   WHERE mo.meeting_id = ?
   ORDER BY mo.position ASC NULLS LAST, mo.created_at ASC, b.cast_at ASC",
  [$meetingId]
);

foreach ($rows as $r) {
  fputcsv($out, [
    (string)($r['ballot_id'] ?? ''),
    (string)($r['motion_id'] ?? ''),
    (string)($r['motion_title'] ?? ''),
    (string)($r['member_id'] ?? ''),
    (string)($r['voter_name'] ?? ''),
    (string)($r['attendance_mode'] ?? ''),
    (string)($r['value'] ?? ''),
    (string)($r['weight'] ?? ''),
    ((bool)($r['is_proxy_vote'] ?? false)) ? '1' : '0',
    (string)($r['proxy_source_member_id'] ?? ''),
    (string)($r['cast_at'] ?? ''),
    (string)($r['source'] ?? ''),
    (string)($r['token_id'] ?? ''),
    (string)($r['token_hash_prefix'] ?? ''),
    (string)($r['token_expires_at'] ?? ''),
    (string)($r['token_used_at'] ?? ''),
    (string)($r['manual_justification'] ?? ''),
  ], $sep);
}

fclose($out);
