<?php
// public/api/v1/operator_anomalies.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

api_request('GET');
api_require_role('operator');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '' || !api_is_uuid($meetingId)) {
  api_fail('invalid_meeting_id', 422);
}

$motionId = trim((string)($_GET['motion_id'] ?? ''));
if ($motionId !== '' && !api_is_uuid($motionId)) {
  api_fail('invalid_motion_id', 422);
}

// 1) Meeting (lockdown)
$meeting = db_select_one(
  "SELECT id, status, validated_at FROM meetings WHERE tenant_id = :tid AND id = :id",
  [':tid' => api_current_tenant_id(), ':id' => $meetingId]
);
if (!$meeting) api_fail('meeting_not_found', 404);

// 2) Motion cible: motion_id -> sinon motion ouverte -> sinon null
if ($motionId === '') {
  $open = db_select_one(
    "SELECT id FROM motions
     WHERE tenant_id=:tid AND meeting_id=:mid
       AND opened_at IS NOT NULL AND closed_at IS NULL
     ORDER BY opened_at DESC LIMIT 1",
    [':tid'=>api_current_tenant_id(), ':mid'=>$meetingId]
  );
  $motionId = $open ? (string)$open['id'] : '';
}

$motion = null;
if ($motionId !== '') {
  $motion = db_select_one(
    "SELECT id, title, opened_at, closed_at
     FROM motions WHERE tenant_id=:tid AND meeting_id=:mid AND id=:id",
    [':tid'=>api_current_tenant_id(), ':mid'=>$meetingId, ':id'=>$motionId]
  );
  if (!$motion) api_fail('motion_not_found', 404);
}

// 3) Éligibles: présents/remote/proxy (fallback: tous)
$eligibleRows = db_select_all(
  "SELECT m.id AS member_id, m.full_name
   FROM members m
   JOIN attendances a ON a.member_id = m.id AND a.meeting_id = :mid AND a.tenant_id = m.tenant_id
   WHERE m.tenant_id = :tid AND m.is_active = true AND a.mode IN ('present','remote','proxy')
   ORDER BY m.full_name ASC",
  [':tid'=>api_current_tenant_id(), ':mid'=>$meetingId]
);
if (!$eligibleRows) {
  $eligibleRows = db_select_all(
    "SELECT id AS member_id, full_name
     FROM members
     WHERE tenant_id=:tid AND meeting_id=:mid AND is_active = true
     ORDER BY full_name ASC",
    [':tid'=>api_current_tenant_id(), ':mid'=>$meetingId]
  );
}

$eligibleIds = [];
$eligibleNames = [];
foreach ($eligibleRows as $r) {
  $id = (string)($r['member_id'] ?? '');
  if ($id === '') continue;
  $eligibleIds[] = $id;
  $eligibleNames[$id] = (string)($r['full_name'] ?? '');
}

$eligibleCount = count($eligibleIds);

$proxyMax = (int)($_ENV['PROXY_MAX_PER_RECEIVER'] ?? getenv('PROXY_MAX_PER_RECEIVER') ?? 99);
$proxyCeilings = [];
try {
  $rows = db_select_all(
    "SELECT proxy_id, COUNT(*) AS c
     FROM proxies
     WHERE tenant_id=:tid AND meeting_id=:mid AND revoked_at IS NULL
     GROUP BY proxy_id
     HAVING COUNT(*) > :mx
     ORDER BY c DESC",
    [':tid'=>api_current_tenant_id(), ':mid'=>$meetingId, ':mx'=>$proxyMax]
  );
  foreach ($rows as $r) {
    $pid = (string)$r['proxy_id'];
    $proxyCeilings[] = [
      'proxy_id' => $pid,
      'proxy_name' => $eligibleNames[$pid] ?? null,
      'count' => (int)$r['c'],
      'max' => $proxyMax,
    ];
  }
} catch (Throwable $e) { $proxyCeilings = []; }



// 4) Stats tokens/ballots sur la motion (si présente)
$stats = [
  'tokens_active_unused' => 0,
  'tokens_expired_unused' => 0,
  'tokens_used' => 0,
  'ballots_total' => 0,
  'ballots_from_eligible' => 0,
  'eligible_expected' => $eligibleCount,
  'missing_ballots_from_eligible' => 0,
];

$missingNames = [];
$ballotsNotEligible = [];
$duplicates = [];

if ($motionId !== '') {
  $stats['tokens_active_unused'] = (int)(db_scalar(
    "SELECT COUNT(*) FROM vote_tokens
     WHERE tenant_id=:tid AND meeting_id=:mid AND motion_id=:mo
       AND used_at IS NULL AND expires_at > NOW()",
    [':tid'=>api_current_tenant_id(), ':mid'=>$meetingId, ':mo'=>$motionId]
  ) ?? 0);

  $stats['tokens_expired_unused'] = (int)(db_scalar(
    "SELECT COUNT(*) FROM vote_tokens
     WHERE tenant_id=:tid AND meeting_id=:mid AND motion_id=:mo
       AND used_at IS NULL AND expires_at <= NOW()",
    [':tid'=>api_current_tenant_id(), ':mid'=>$meetingId, ':mo'=>$motionId]
  ) ?? 0);

  $stats['tokens_used'] = (int)(db_scalar(
    "SELECT COUNT(*) FROM vote_tokens
     WHERE tenant_id=:tid AND meeting_id=:mid AND motion_id=:mo
       AND used_at IS NOT NULL",
    [':tid'=>api_current_tenant_id(), ':mid'=>$meetingId, ':mo'=>$motionId]
  ) ?? 0);

  $ballots = db_select_all(
    "SELECT b.member_id, b.value::text AS value, b.cast_at, COALESCE(b.source,'tablet') AS source
     FROM ballots b
     WHERE b.tenant_id=:tid AND b.meeting_id=:mid AND b.motion_id=:mo
     ORDER BY b.cast_at ASC",
    [':tid'=>api_current_tenant_id(), ':mid'=>$meetingId, ':mo'=>$motionId]
  );

  $stats['ballots_total'] = count($ballots);

  $votedSet = [];
  foreach ($ballots as $b) {
    $mid = (string)($b['member_id'] ?? '');
    if ($mid === '') continue;

    // doublons théoriquement impossibles (UNIQUE motion_id, member_id), mais on trace quand même
    if (isset($votedSet[$mid])) {
      $duplicates[] = [
        'member_id' => $mid,
        'name' => $eligibleNames[$mid] ?? null,
        'detail' => 'duplicate_ballot_for_member',
      ];
    }
    $votedSet[$mid] = true;

    if (!in_array($mid, $eligibleIds, true)) {
      $ballotsNotEligible[] = [
        'member_id' => $mid,
        'value' => (string)($b['value'] ?? ''),
        'source' => (string)($b['source'] ?? ''),
        'cast_at' => $b['cast_at'],
      ];
    }
  }

  $eligibleVoted = 0;
  foreach ($eligibleIds as $id) {
    if (isset($votedSet[$id])) $eligibleVoted++;
  }
  $stats['ballots_from_eligible'] = $eligibleVoted;
  $stats['missing_ballots_from_eligible'] = max(0, $eligibleCount - $eligibleVoted);

  if ($stats['missing_ballots_from_eligible'] > 0) {
    foreach ($eligibleIds as $id) {
      if (!isset($votedSet[$id])) {
        $missingNames[] = $eligibleNames[$id] ?? $id;
        if (count($missingNames) >= 30) break;
      }
    }
  }
}

api_ok([
  'meeting' => [
    'id' => $meetingId,
    'status' => (string)($meeting['status'] ?? ''),
    'validated_at' => $meeting['validated_at'],
  ],
  'motion' => $motion ? [
    'id' => (string)$motion['id'],
    'title' => (string)($motion['title'] ?? ''),
    'opened_at' => $motion['opened_at'],
    'closed_at' => $motion['closed_at'],
  ] : null,
  'eligibility' => [
    'expected_count' => $eligibleCount,
  ],
  'stats' => $stats,
  'anomalies' => [
    'missing_voters_sample' => $missingNames,
    'ballots_not_eligible' => $ballotsNotEligible,
    'duplicates' => $duplicates,
  ],
]);
