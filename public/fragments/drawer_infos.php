<?php
declare(strict_types=1);
require __DIR__ . '/_drawer_util.php';

use AgVote\Repository\FragmentRepository;

$meetingId = get_meeting_id();
if ($meetingId === '') {
  echo '<div class="card pad"><div class="h2">Informations</div><div class="muted">Aucune séance sélectionnée.</div></div>';
  exit;
}

$repo = new FragmentRepository();

$meeting = $repo->findMeetingForInfos($meetingId);
if (!$meeting) {
  echo '<div class="card pad"><div class="h2">Informations</div><div class="muted">Séance introuvable.</div></div>';
  exit;
}

// Résolution ouverte (vote en cours)
$openMotion = $repo->findOpenMotion($meetingId);

$pendingVotes = null;
$unusedTokens = null;

if ($openMotion) {
  $expected = $repo->countExpectedVoters($meetingId);
  $received = $repo->countBallotsForMotion($openMotion['id']);
  $pendingVotes = max(0, $expected - $received);
  $unusedTokens = $repo->countActiveUnusedTokens($meetingId, $openMotion['id']);
}

echo '<div class="card">';
echo '  <div class="card-head">';
echo '    <div style="min-width:0;">';
echo '      <div class="h2" style="margin:0;">Informations</div>';
echo '      <div class="tiny muted" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Séance : '.h($meeting['title'] ?? '').'</div>';
echo '    </div>';
echo '    <span class="'.badge_class('neutral').'">Informatif</span>';
echo '  </div>';
echo '  <div class="card-body">';

if (!$openMotion) {
  echo '<div class="callout muted">Aucun vote en cours.</div>';
} else {
  echo '<div class="callout" style="margin-bottom:12px;">';
  echo '  <div style="font-weight:800; margin-bottom:4px;">Résolution en cours</div>';
  echo '  <div class="tiny muted">'.h((string)$openMotion['title']).'</div>';
  echo '</div>';

  echo '<div class="kpis" style="grid-template-columns: repeat(2, minmax(0,1fr));">';
  echo '  <div class="kpi">';
  echo '    <div class="label">Votes en attente</div>';
  echo '    <div class="value">'.(int)$pendingVotes.'</div>';
  echo '    <div class="tiny muted">Informatif (n\'empêche pas l\'action).</div>';
  echo '  </div>';
  echo '  <div class="kpi">';
  echo '    <div class="label">Tokens non utilisés</div>';
  echo '    <div class="value">'.(int)$unusedTokens.'</div>';
  echo '    <div class="tiny muted">Informatif, grisé.</div>';
  echo '  </div>';
  echo '</div>';
}

echo '    <div class="callout tiny muted" style="margin-top:12px;">Les exports (CSV / PDF) sont disponibles après validation de la séance.</div>';
echo '  </div>';
echo '</div>';
