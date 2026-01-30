<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

api_require_role(['operator', 'admin', 'auditor']);

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 400);
}

// Exports autorisés uniquement après validation (exigence conformité)
$mt = db_select_one(
    "SELECT validated_at FROM meetings WHERE tenant_id = ? AND id = ?",
    [DEFAULT_TENANT_ID, $meetingId]
);
if (!$mt) api_fail('meeting_not_found', 404);
if (empty($mt['validated_at'])) api_fail('meeting_not_validated', 409);

$filename = "motions_results_" . $meetingId . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

$sep = ';';
$out = fopen('php://output', 'w');
fputs($out, "ï»¿"); // UTF-8 BOM for Excel

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

$rows = db_select_all(
  "SELECT
      mo.id AS motion_id,
      mo.title,
      mo.position,
      mo.opened_at,
      mo.closed_at,
      COALESCE(SUM(CASE WHEN b.value = 'for' THEN b.weight ELSE 0 END), 0) AS w_for,
      COALESCE(SUM(CASE WHEN b.value = 'against' THEN b.weight ELSE 0 END), 0) AS w_against,
      COALESCE(SUM(CASE WHEN b.value = 'abstain' THEN b.weight ELSE 0 END), 0) AS w_abstain,
      COALESCE(SUM(CASE WHEN b.value = 'nsp' THEN b.weight ELSE 0 END), 0) AS w_nsp,
      COALESCE(SUM(b.weight), 0) AS w_total,
      COALESCE(COUNT(b.id), 0) AS ballots_count,
      COALESCE(SUM(CASE WHEN b.source = 'manual' THEN 1 ELSE 0 END), 0) AS ballots_manual_count,
      COALESCE(mo.decision, '') AS decision
   FROM motions mo
   LEFT JOIN ballots b ON b.motion_id = mo.id
   WHERE mo.meeting_id = ?
   GROUP BY mo.id, mo.title, mo.position, mo.opened_at, mo.closed_at, mo.decision
   ORDER BY mo.position ASC NULLS LAST, mo.created_at ASC",
  [$meetingId]
);

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
