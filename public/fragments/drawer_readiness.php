<?php
declare(strict_types=1);
require __DIR__ . '/_drawer_util.php';

$meetingId = get_meeting_id();
if ($meetingId === '') {
  echo '<div class="card pad"><div class="h2">Readiness</div><div class="muted">Aucune séance sélectionnée.</div></div>';
  exit;
}

$meeting = db_select_one("SELECT id, title, status, validated_at FROM meetings WHERE id = ?", [$meetingId]);
if (!$meeting) {
  echo '<div class="card pad"><div class="h2">Readiness</div><div class="muted">Séance introuvable.</div></div>';
  exit;
}

$cntMembers = (int)(db_select_one("SELECT COUNT(*) AS c FROM members WHERE meeting_id = ?", [$meetingId])['c'] ?? 0);
$cntRes = (int)(db_select_one("SELECT COUNT(*) AS c FROM motions WHERE meeting_id = ?", [$meetingId])['c'] ?? 0);
$cntAttendance = (int)(db_select_one("SELECT COUNT(*) AS c FROM attendance WHERE meeting_id = ?", [$meetingId])['c'] ?? 0);

$ready = ($cntMembers > 0 && $cntRes > 0);

function item(string $label, bool $ok, string $hint=''): string {
  $iconSvg = $ok
    ? '<svg class="icon" style="color:var(--color-success);width:18px;height:18px;" aria-hidden="true"><use href="/assets/icons.svg#icon-check-circle"></use></svg>'
    : '<svg class="icon" style="color:var(--color-text-muted);width:18px;height:18px;" aria-hidden="true"><use href="/assets/icons.svg#icon-square"></use></svg>';
  $cls = $ok ? '' : 'muted';
  $hintHtml = $hint ? '<div class="tiny muted">'.h($hint).'</div>' : '';
  return "<div style='display:flex; gap:10px; align-items:flex-start; margin:10px 0;'>
    <div style='width:18px; text-align:center;'>$iconSvg</div>
    <div style='min-width:0;'><div class='$cls' style='font-weight:800;'>".h($label)."</div>$hintHtml</div>
  </div>";
}

$badge = $ready ? '<span class="'.badge_class('success').'">Séance prête</span>' : '<span class="'.badge_class('warning').'">Incomplète</span>';

echo '<div class="card">';
echo '  <div class="card-head">';
echo '    <div style="min-width:0;">';
echo '      <div class="h2" style="margin:0;">Readiness</div>';
echo '      <div class="tiny muted" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Séance : '.h($meeting['title'] ?? '').'</div>';
echo '    </div>';
echo '    '.$badge;
echo '  </div>';
echo '  <div class="card-body">';

echo item("Membres importés", $cntMembers>0, $cntMembers>0 ? "$cntMembers membres" : "Importer au moins 1 membre");
echo item("Résolutions définies", $cntRes>0, $cntRes>0 ? "$cntRes résolutions" : "Créer au moins 1 résolution");
echo item("Présences saisies", $cntAttendance>0, $cntAttendance>0 ? "$cntAttendance statuts saisis" : "Recommandé avant le live");

echo '    <div class="callout tiny muted" style="margin-top:12px;">Détails juridiques et justifications : via le panneau “Informations” ou la page concernée.</div>';
echo '  </div>';
echo '</div>';
