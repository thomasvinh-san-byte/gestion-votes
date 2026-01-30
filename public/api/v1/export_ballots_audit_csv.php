<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\BallotRepository;

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

$filename = "ballots_audit_" . $meetingId . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

$sep = ';';
$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

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
$ballotRepo = new BallotRepository();
$rows = $ballotRepo->listAuditExportForMeeting($meetingId);

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
