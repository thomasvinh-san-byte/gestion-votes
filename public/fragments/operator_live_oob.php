<?php
declare(strict_types=1);
require __DIR__ . '/_drawer_util.php';

use AgVote\Repository\FragmentRepository;

$meetingId = get_meeting_id();
if ($meetingId === '') { http_response_code(204); exit; }

$tenantId = api_current_tenant_id();
$repo = new FragmentRepository();

$meeting = $repo->findMeetingForLive($meetingId, $tenantId);
if (!$meeting) { http_response_code(204); exit; }

$open = $repo->findOpenMotionForLive($meetingId, $tenantId);

$present = $repo->countPresentForLive($meetingId, $tenantId);

$tokens = 0;
$votes = 0;
$pending = null;

if ($open) {
  $tokens = $repo->countActiveTokensForLive($meetingId, $open['id'], $tenantId);
  $votes = $repo->countBallotsForLive($meetingId, $open['id'], $tenantId);
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
