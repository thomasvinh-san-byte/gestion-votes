<?php
declare(strict_types=1);
require __DIR__ . '/_drawer_util.php';

$meetingId = get_meeting_id();
if ($meetingId === '') { http_response_code(204); exit; }

$meeting = db_select_one(
  "SELECT id, title, validated_at FROM meetings WHERE tenant_id = ? AND id = ?",
  [DEFAULT_TENANT_ID, $meetingId]
);
if (!$meeting) { http_response_code(204); exit; }

$open = db_select_one(
  "SELECT id, title FROM motions WHERE tenant_id = ? AND meeting_id = ? AND opened_at IS NOT NULL AND closed_at IS NULL ORDER BY opened_at DESC LIMIT 1",
  [DEFAULT_TENANT_ID, $meetingId]
);

$present = (int)(db_select_one(
  "SELECT COUNT(*) AS c
   FROM attendances
   WHERE tenant_id = ? AND meeting_id = ? AND mode IN ('present','remote','proxy')",
  [DEFAULT_TENANT_ID, $meetingId]
)['c'] ?? 0);

$tokens = 0;
$votes = 0;
$pending = null;

if ($open) {
  $tokens = (int)(db_select_one(
    "SELECT COUNT(*) AS c FROM vote_tokens WHERE tenant_id = ? AND meeting_id = ? AND motion_id = ? AND used_at IS NULL AND expires_at > NOW()",
    [DEFAULT_TENANT_ID, $meetingId, $open['id']]
  )['c'] ?? 0);

  $votes = (int)(db_select_one(
    "SELECT COUNT(*) AS c FROM ballots WHERE tenant_id = ? AND meeting_id = ? AND motion_id = ?",
    [DEFAULT_TENANT_ID, $meetingId, $open['id']]
  )['c'] ?? 0);

  $pending = max(0, $present - $votes);
}

$sum = '';
if (!$open) {
  $sum = '<div class="muted">Aucun vote en cours.</div>';
} else {
  $sum = '<div style="font-weight:800; margin-bottom:6px;">Résolution en cours</div>'
       . '<div class="tiny muted" style="margin-bottom:10px;">'.h((string)$open['title']).'</div>'
       . '<div class="kpis" style="grid-template-columns: repeat(2, minmax(0,1fr));">'
       .   '<div class="kpi"><div class="label">Votes reçus</div><div class="value">'.(int)$votes.'</div></div>'
       .   '<div class="kpi"><div class="label">Votes en attente</div><div class="value">'.(int)$pending.'</div></div>'
       . '</div>'
       . '<div class="callout tiny muted" style="margin-top:10px;">Tokens non utilisés : '.(int)$tokens.' (informatif).</div>';
}

echo '<div id="operatorLiveSummary" class="callout" hx-swap-oob="true">'.$sum.'</div>';
echo '<span id="badgeLive" hx-swap-oob="true" class="'.badge_class('neutral').'">Live</span>';
echo '<input id="currentMotionId" hx-swap-oob="true" type="hidden" value="'.h($open ? (string)$open['id'] : '').'">';

