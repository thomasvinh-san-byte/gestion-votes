<?php
declare(strict_types=1);
require __DIR__ . '/_drawer_util.php';

$meetingId = get_meeting_id();
if ($meetingId === '') {
  http_response_code(204);
  exit;
}

// Open motion
$motion = db_select_one(
  "SELECT id, title, opened_at, closed_at
   FROM motions
   WHERE tenant_id = ? AND meeting_id = ? AND opened_at IS NOT NULL AND closed_at IS NULL
   ORDER BY opened_at DESC
   LIMIT 1",
  [DEFAULT_TENANT_ID, $meetingId]
);

$motionTitle = $motion ? (string)$motion['title'] : 'Aucune résolution ouverte';
$motionMeta = $motion ? ('Vote en cours') : '—';

// Expected voters (simple: present+remote)
$eligible = (int)(db_select_one(
  "SELECT COUNT(*) AS c FROM members WHERE tenant_id = ? AND meeting_id = ?",
  [DEFAULT_TENANT_ID, $meetingId]
)['c'] ?? 0);

$currentPresent = (int)(db_select_one(
  "SELECT COUNT(*) AS c FROM attendance WHERE tenant_id = ? AND meeting_id = ? AND status IN ('present','remote')",
  [DEFAULT_TENANT_ID, $meetingId]
)['c'] ?? 0);

// Quorum required based on meeting.quorum_policy_id threshold if any
$meeting = db_select_one(
  "SELECT quorum_policy_id, validated_at FROM meetings WHERE tenant_id = ? AND id = ?",
  [DEFAULT_TENANT_ID, $meetingId]
);
$required = null;
$quorumReached = null;
if ($meeting && !empty($meeting['quorum_policy_id'])) {
  $qp = db_select_one(
    "SELECT threshold FROM quorum_policies WHERE tenant_id = ? AND id = ?",
    [DEFAULT_TENANT_ID, $meeting['quorum_policy_id']]
  );
  if ($qp) {
    $th = (float)$qp['threshold'];
    $required = (int)ceil($eligible * $th);
    $quorumReached = ($currentPresent >= $required);
  }
}

// Vote counts
$counts = ['pour'=>0,'contre'=>0,'abstention'=>0,'blanc'=>0];
$total = 0;

if ($motion) {
  $rows = db_select_all(
    "SELECT value, COUNT(*) AS c
     FROM ballots
     WHERE tenant_id = ? AND meeting_id = ? AND motion_id = ?
     GROUP BY value",
    [DEFAULT_TENANT_ID, $meetingId, $motion['id']]
  );
  foreach ($rows as $r) {
    $v = (string)$r['value'];
    $c = (int)$r['c'];
    // support legacy english values
    if ($v === 'for') $v = 'pour';
    if ($v === 'against') $v = 'contre';
    if ($v === 'abstain') $v = 'abstention';
    if ($v === 'nsp') $v = 'blanc';
    if (!isset($counts[$v])) continue;
    $counts[$v] += $c;
    $total += $c;
  }
}

$expected = $currentPresent; // simple expectation; detailed rules later
$pending = max(0, $expected - $total);

// Action state
$canClose = (bool)$motion; // simple
$canValidate = (bool)($meeting && !empty($meeting['validated_at'])); // already validated => show disabled validate
$validated = (bool)($meeting && !empty($meeting['validated_at']));

// Compute bar widths (% of expected if >0 else total)
$den = $expected > 0 ? $expected : max(1, $total);
function pct(int $num, int $den): int {
  return (int)max(0, min(100, floor(($num * 100) / max(1,$den))));
}
$pPour = pct($counts['pour'], $den);
$pContre = pct($counts['contre'], $den);
$pAbs = pct($counts['abstention'], $den);
$pBlanc = pct($counts['blanc'], $den);

// Helpers for OOB swaps
function oob_input(string $id, string $value): void {
  echo '<input type="hidden" id="'.h($id).'" hx-swap-oob="true" value="'.h($value).'">';
}

function oob_text(string $id, string $text): void {
  echo '<span id="'.h($id).'" hx-swap-oob="true">'.h($text).'</span>';
}
function oob_html(string $id, string $html): void {
  echo '<div id="'.h($id).'" hx-swap-oob="true">'.$html.'</div>';
}
function oob_style_width(string $id, int $pct): void {
  echo '<span id="'.h($id).'" hx-swap-oob="true" style="width:'.$pct.'%"></span>';
}

// Resolution
oob_text('resolutionTitle', $motionTitle);
oob_input('currentMotionId', $motion ? (string)$motion['id'] : '');
oob_text('resolutionMeta', $motion ? ('Votes reçus : '.$total.' / '.$expected.' — en attente : '.$pending) : '—');

// Badges / KPIs
oob_text('badgeVotesReceived', $motion ? ($total.' / '.$expected) : '—');

if ($required === null) {
  oob_text('quorumRequired', '—');
  oob_text('quorumCurrent', (string)$currentPresent);
  oob_html('badgeQuorum', '<span class="'.badge_class('neutral').'">Quorum —</span>');
} else {
  oob_text('quorumRequired', (string)$required);
  oob_text('quorumCurrent', (string)$currentPresent);
  oob_html('badgeQuorum', '<span class="'.badge_class($quorumReached ? 'success' : 'warning').'">'.h($quorumReached ? 'Quorum atteint' : 'Quorum non atteint').'</span>');
}

// Vote values
oob_text('valPour', (string)$counts['pour']);
oob_text('valContre', (string)$counts['contre']);
oob_text('valAbstention', (string)$counts['abstention']);
oob_text('valBlanc', (string)$counts['blanc']);

// Bars
oob_style_width('barPour', $pPour);
oob_style_width('barContre', $pContre);
oob_style_width('barAbstention', $pAbs);
oob_style_width('barBlanc', $pBlanc);

// Vote status
if (!$motion) {
  oob_html('badgeVoteStatus', '<span class="'.badge_class('neutral').'">Aucun vote</span>');
} else {
  oob_html('badgeVoteStatus', '<span class="'.badge_class('info').'">Vote en cours</span>');
}

// Actions + hints
$hint = '';
if ($validated) $hint = 'Séance déjà validée.';
elseif (!$motion) $hint = 'Ouvrir une résolution en vote via la Conduite.';
elseif ($pending > 0) $hint = 'Des votes sont encore en attente (informatif).';

oob_text('actionHint', $hint);
oob_html('badgeActionState', '<span class="'.badge_class($validated ? 'success' : 'neutral').'">'.h($validated ? 'Validée' : 'En cours').'</span>');

// Swap buttons (enable/disable)
echo '<button id="btnCloseVote" class="btn primary" hx-swap-oob="true" '.($canClose && !$validated ? '' : 'disabled').'>Clôturer le vote</button>';
echo '<button id="btnValidateMeeting" class="btn" hx-swap-oob="true" '.(!$validated && $motion===null ? '' : ($validated ? 'disabled' : '')).'>Valider la séance</button>';
