<?php
require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\EmergencyProcedureRepository;

api_require_role('operator');

header('Content-Type: text/html; charset=utf-8');

$aud = trim((string)($_GET['audience'] ?? 'operator'));
$meetingId = trim((string)($_GET['meeting_id'] ?? ''));

$repo = new EmergencyProcedureRepository();

$rows = $repo->listByAudience($aud);

$checkMap = [];
if ($meetingId !== '' && api_is_uuid($meetingId)) {
  $checks = $repo->listChecksForMeeting($meetingId);
  foreach ($checks as $c) {
    $key = $c['procedure_code'].'#'.$c['item_index'];
    $checkMap[$key] = (bool)$c['checked'];
  }
}

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

echo '<section class="card">';
echo '<div class="row between"><div><div style="font-weight:700;">Procédures d\'urgence</div><div class="muted tiny">Checklists "Que faire si…"</div></div></div>';
echo '<div class="hr"></div>';

if (!$rows) {
  echo '<div class="muted tiny">Aucune procédure.</div></section>';
  exit;
}

foreach ($rows as $p) {
  $code = (string)$p['code'];
  $title = $h($p['title']);
  $steps = json_decode((string)$p['steps_json'], true) ?: [];
  echo '<div style="margin-bottom:10px;">';
  echo '<div style="font-weight:700;">'.$title.'</div>';
  echo '<div class="muted tiny">'.$h($code).'</div>';
  echo '<div style="margin-top:6px;">';
  foreach ($steps as $i => $step) {
    $k = $code.'#'.$i;
    $checked = !empty($checkMap[$k]);
    $chk = $checked ? 'checked' : '';
    echo '<label class="row" style="gap:8px; align-items:flex-start; margin:6px 0;">';
    echo '  <input type="checkbox" class="emgChk" data-proc="'.$h($code).'" data-idx="'.$i.'" '.$chk.' />';
    echo '  <span class="tiny">'.$h($step).'</span>';
    echo '</label>';
  }
  echo '</div></div>';
}

echo '<div class="hr"></div>';
echo '<div class="muted tiny">Ces checklists sont tracées dans l\'audit.</div>';
echo '</section>';
