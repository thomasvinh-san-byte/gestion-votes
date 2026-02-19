<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Service\ExportService;

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

try {

$filename = ExportService::generateFilename('audit', $mt['title'] ?? '');
ExportService::initCsvOutput($filename);

$sep = ';';
$out = ExportService::openCsvOutput();

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
    ExportService::translateAttendanceMode($r['attendance_mode'] ?? ''),
    ExportService::translateVoteChoice($r['value'] ?? ''),
    (string)($r['weight'] ?? ''),
    ((bool)($r['is_proxy_vote'] ?? false)) ? 'Oui' : 'Non',
    (string)($r['proxy_source_member_id'] ?? ''),
    ExportService::formatDate($r['cast_at'] ?? null),
    ExportService::translateVoteSource($r['source'] ?? ''),
    (string)($r['token_id'] ?? ''),
    (string)($r['token_hash_prefix'] ?? ''),
    ExportService::formatDate($r['token_expires_at'] ?? null),
    ExportService::formatDate($r['token_used_at'] ?? null),
    (string)($r['manual_justification'] ?? ''),
  ], $sep);
}

fclose($out);

} catch (Throwable $e) {
    error_log('Error in export_ballots_audit_csv.php: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "server_error";
}
