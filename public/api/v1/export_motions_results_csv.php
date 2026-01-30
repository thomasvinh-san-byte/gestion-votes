<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;

api_require_role(['operator', 'admin', 'auditor']);

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 400);
}

// Exports autorisés uniquement après validation (exigence conformité)
$meetingRepo = new MeetingRepository();
$mt = $meetingRepo->findByIdForTenant($meetingId, api_current_tenant_id());
if (!$mt) api_fail('meeting_not_found', 404);
if (empty($mt['validated_at'])) api_fail('meeting_not_validated', 409);

$filename = "motions_results_" . $meetingId . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

$sep = ';';
$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

fputcsv($out, [
  'Motion ID',
  'Résolution',
  'Position',
  'Ouverte le',
  'Clôturée le',
  'Pour (poids)',
  'Contre (poids)',
  'Abstention (poids)',
  'NSP/Blanc (poids)',
  'Total exprimé (poids)',
  'Ballots (nb)',
  'Ballots manuels (nb)',
  'Décision',
], $sep);

$motionRepo = new MotionRepository();
$rows = $motionRepo->listResultsExportForMeeting($meetingId);

foreach ($rows as $r) {
  fputcsv($out, [
    (string)($r['motion_id'] ?? ''),
    (string)($r['title'] ?? ''),
    (string)($r['position'] ?? ''),
    (string)($r['opened_at'] ?? ''),
    (string)($r['closed_at'] ?? ''),
    (string)($r['w_for'] ?? 0),
    (string)($r['w_against'] ?? 0),
    (string)($r['w_abstain'] ?? 0),
    (string)($r['w_nsp'] ?? 0),
    (string)($r['w_total'] ?? 0),
    (string)($r['ballots_count'] ?? 0),
    (string)($r['ballots_manual_count'] ?? 0),
    (string)($r['decision'] ?? ''),
  ], $sep);
}

fclose($out);
