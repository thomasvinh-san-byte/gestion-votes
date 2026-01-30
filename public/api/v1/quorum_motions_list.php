<?php
// Fragment HTML: liste des motions + badge quorum + justification (cahier §8.3 visibilité)
require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MotionRepository;
use AgVote\Service\QuorumEngine;

header('Content-Type: text/html; charset=utf-8');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '') {
  echo '<section class="card"><div class="muted">Quorum motions: meeting_id requis.</div></section>';
  exit;
}

// Récupère motions de la séance
$repo = new MotionRepository();
$motions = $repo->listForQuorumDisplay($meetingId);

echo '<section class="card">';
echo '  <div class="row between">';
echo '    <div><div class="k">Quorum par résolution</div><div class="muted tiny">Badges par motion (override si défini)</div></div>';
echo '  </div>';
echo '  <div class="hr"></div>';

if (!$motions) {
  echo '<div class="muted tiny">Aucune résolution.</div>';
  echo '</section>';
  exit;
}

echo '<table class="table">';
echo '<thead><tr><th>Résolution</th><th>État</th><th>Quorum</th></tr></thead><tbody>';

foreach ($motions as $mo) {
  $motionId = (string)$mo['id'];
  $title = htmlspecialchars((string)$mo['title'], ENT_QUOTES, 'UTF-8');
  $status = (string)($mo['status'] ?? '');
  $statusLabel = $status ?: '—';
  $statusSafe = htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8');

  $badgeClass = 'muted';
  $badgeText  = '—';
  $just = '';

  try {
    $qr = QuorumEngine::computeForMotion($motionId);
    if (!($qr['applied'] ?? false)) {
      $badgeClass = 'muted';
      $badgeText = '—';
      $just = 'Aucune politique de quorum.';
    } else {
      $met = $qr['met'];
      if ($met === true) { $badgeClass='success'; $badgeText='atteint'; }
      if ($met === false){ $badgeClass='danger';  $badgeText='non atteint'; }
      $just = (string)($qr['justification'] ?? '');
    }
  } catch (Throwable $e) {
    $badgeClass = 'danger';
    $badgeText = 'erreur';
    $just = $e->getMessage();
  }

  $justSafe = htmlspecialchars($just, ENT_QUOTES, 'UTF-8');

  echo '<tr>';
  echo '  <td style="max-width:520px"><div><strong>' . $title . '</strong></div><div class="muted tiny">' . $justSafe . '</div></td>';
  echo '  <td><span class="badge">' . $statusSafe . '</span></td>';
  echo '  <td><span class="badge ' . $badgeClass . '">' . $badgeText . '</span></td>';
  echo '</tr>';
}

echo '</tbody></table>';
echo '</section>';
