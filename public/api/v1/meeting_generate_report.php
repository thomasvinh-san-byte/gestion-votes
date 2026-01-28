<?php
// public/api/v1/meeting_generate_report.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';
api_require_role('president');

$in = api_request('GET');
$meetingId = trim((string)($in['meeting_id'] ?? ($_GET['meeting_id'] ?? '')));
if ($meetingId === '' || !api_is_uuid($meetingId)) api_fail('invalid_meeting_id', 400);

// Load meeting
$meeting = db_select_one(
  "SELECT m.*, u.display_name AS validated_by
   FROM meetings m
   LEFT JOIN users u ON u.id = m.validated_by_user_id
   WHERE m.id = ?",
  [$meetingId]
);
if (!$meeting) api_fail('meeting_not_found', 404);
if ($meeting['validated_at'] === null) api_fail('meeting_not_validated', 409);

// Motions + tallies
$motions = db_select_all(
  "SELECT title, evote_results
   FROM motions
   WHERE meeting_id = ?
   ORDER BY COALESCE(position, sort_order, 0) ASC",
  [$meetingId]
);

// Build HTML
ob_start();
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>PV – Séance <?= htmlspecialchars($meetingId) ?></title>
<style>
body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;font-size:14px;color:#111}
h1,h2{margin:0 0 8px}
table{border-collapse:collapse;width:100%;margin:12px 0}
th,td{border:1px solid #ccc;padding:6px 8px;text-align:left}
th{background:#f0f0f0}
.small{font-size:12px;color:#555}
</style>
</head>
<body>
<h1>Procès-verbal de séance</h1>
<p class="small">
Séance ID : <?= htmlspecialchars($meetingId) ?><br>
Validée le : <?= htmlspecialchars($meeting['validated_at']) ?><br>
Par : <?= htmlspecialchars($meeting['validated_by'] ?? '—') ?>
</p>

<?php foreach ($motions as $i=>$mo):
  $t = json_decode($mo['evote_results'] ?? '{}', true);
?>
<h2>Résolution <?= $i+1 ?> – <?= htmlspecialchars($mo['title']) ?></h2>
<table>
<tr><th>Vote</th><th>Nombre</th><th>Pondération</th></tr>
<?php foreach (['for'=>'Pour','against'=>'Contre','abstain'=>'Abstention','nsp'=>'Blanc'] as $k=>$lbl): ?>
<tr>
<td><?= $lbl ?></td>
<td><?= (int)($t[$k]['count'] ?? 0) ?></td>
<td><?= (float)($t[$k]['weight'] ?? 0) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endforeach; ?>

</body>
</html>
<?php
$html = ob_get_clean();
$hash = hash('sha256', $html);

// Persist snapshot
db_execute(
  "INSERT INTO meeting_reports(meeting_id, sha256, generated_at)
   VALUES (:m,:h,NOW())
   ON CONFLICT (meeting_id) DO UPDATE SET sha256 = EXCLUDED.sha256, generated_at = NOW()",
  [':m'=>$meetingId, ':h'=>$hash]
);

header('Content-Type: text/html; charset=utf-8');
echo $html;
