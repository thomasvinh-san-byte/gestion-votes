<?php
declare(strict_types=1);
require __DIR__ . '/_drawer_util.php';

$meetingId = get_meeting_id();
if ($meetingId === '') {
  echo '<div class="card pad"><div class="h2">Menu</div><div class="muted">Aucune séance sélectionnée.</div></div>';
  exit;
}

$meeting = db_select_one("SELECT id, title, status FROM meetings WHERE id = ?", [$meetingId]);
if (!$meeting) {
  echo '<div class="card pad"><div class="h2">Menu</div><div class="muted">Séance introuvable.</div></div>';
  exit;
}

$motions = db_select_all(
  "SELECT id, title, COALESCE(position, sort_order, 0) AS pos, opened_at, closed_at
   FROM motions WHERE meeting_id = ?
   ORDER BY COALESCE(position, sort_order, 0) ASC",
  [$meetingId]
);

echo '<div class="card">';
echo '  <div class="card-head">';
echo '    <div style="min-width:0;">';
echo '      <div class="h2" style="margin:0;">Navigation séance</div>';
echo '      <div class="tiny muted" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Séance : '.h($meeting['title'] ?? '').'</div>';
echo '    </div>';
echo '    <span class="'.badge_class('neutral').'">Menu</span>';
echo '  </div>';
echo '  <div class="card-body">';

echo '<div class="toolbar" style="flex-direction:column; align-items:stretch;">';
echo '  <a class="btn" href="/operator.htmx.html?meeting_id='.h($meetingId).'">🗂️ Fiche séance</a>';
echo '  <a class="btn" href="/speaker.htmx.html?meeting_id='.h($meetingId).'">🎙️ Gestion parole</a>';
echo '  <a class="btn" href="/vote.htmx.html?meeting_id='.h($meetingId).'">🗳️ Vote</a>';
echo '</div>';

echo '<div style="height:12px;"></div>';
echo '<div class="h2" style="font-size:14px; margin:0 0 8px;">Résolutions</div>';

if (!$motions) {
  echo '<div class="callout muted">Aucune résolution.</div>';
} else {
  echo '<div style="display:flex; flex-direction:column; gap:10px;">';
  foreach ($motions as $m) {
    $state = 'À venir';
    $b = badge_class('neutral');
    if (!empty($m['opened_at']) && empty($m['closed_at'])) { $state='Vote en cours'; $b = badge_class('info'); }
    if (!empty($m['closed_at'])) { $state='Vote clôturé'; $b = badge_class('success'); }
    $label = 'Résolution '.(int)$m['pos'].' — '.(string)$m['title'];

    echo '<div class="card" style="box-shadow:none;">';
    echo '  <div class="card-body" style="display:flex; justify-content:space-between; gap:10px; align-items:flex-start;">';
    echo '    <div style="min-width:0;">';
    echo '      <div style="font-weight:800; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">'.h($label).'</div>';
    echo '      <div class="tiny muted">'.h($state).'</div>';
    echo '    </div>';
    echo '    <span class="'.$b.'">'.h($state).'</span>';
    echo '  </div>';
    echo '</div>';
  }
  echo '</div>';
}

echo '  </div>';
echo '</div>';
