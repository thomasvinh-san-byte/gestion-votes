<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

api_require_role('operator');

$meetingId = isset($_GET['meeting_id']) ? (string)$_GET['meeting_id'] : '';
$tenantId  = (string)($GLOBALS['APP_TENANT_ID'] ?? api_current_tenant_id());

global $pdo;

// Liste des séances (pour select UI)
$meetings = [];
$stmt = $pdo->prepare("
  SELECT id, title, status, scheduled_at, started_at, ended_at, archived_at, validated_at
  FROM meetings
  WHERE tenant_id = :t
  ORDER BY
    CASE status WHEN 'live' THEN 0 WHEN 'draft' THEN 1 WHEN 'archived' THEN 3 ELSE 2 END,
    COALESCE(started_at, scheduled_at, created_at) DESC
  LIMIT 50
");
$stmt->execute([':t' => $tenantId]);
$meetings = $stmt->fetchAll() ?: [];

// Séance suggérée: live si existe sinon la première de la liste
$suggested = null;
foreach ($meetings as $m) {
  if (($m['status'] ?? '') === 'live') { $suggested = $m['id']; break; }
}
if ($suggested === null && count($meetings) > 0) $suggested = $meetings[0]['id'];

if ($meetingId === '') $meetingId = (string)($suggested ?? '');

$data = [
  'meetings' => $meetings,
  'suggested_meeting_id' => $suggested,
  'meeting' => null,
  'attendance' => [
    'eligible_count' => null, 'eligible_weight' => null,
    'present_count' => 0, 'present_weight' => 0,
  ],
  'proxies' => ['count' => 0],
  'current_motion' => null,
  'current_motion_votes' => ['ballots_count' => 0, 'weight_for' => 0, 'weight_against' => 0, 'weight_abstain' => 0],
  'openable_motions' => [],
  'ready_to_sign' => ['can' => false, 'reasons' => []],
];

if ($meetingId !== '') {
  // meeting
  $stmt = $pdo->prepare("
    SELECT id, title, status, scheduled_at, started_at, ended_at, archived_at, validated_at, president_name, current_motion_id
    FROM meetings
    WHERE tenant_id = :t AND id = :id
    LIMIT 1
  ");
  $stmt->execute([':t' => $tenantId, ':id' => $meetingId]);
  $meeting = $stmt->fetch();
  if (!$meeting) api_fail('meeting_not_found', 404);
  $data['meeting'] = $meeting;

  // attendance summary
  // eligible = members not deleted (best-effort), present = attendances present/remote
  $eligibleCount = (int)$pdo->query("SELECT COUNT(*)::int AS c FROM members WHERE tenant_id = " . $pdo->quote($tenantId) . " AND (deleted_at IS NULL)")->fetch()['c'];
  $eligibleWeight = (int)$pdo->query("SELECT COALESCE(SUM(vote_weight),0)::int AS w FROM members WHERE tenant_id = " . $pdo->quote($tenantId) . " AND (deleted_at IS NULL)")->fetch()['w'];

  $stmt = $pdo->prepare("
    SELECT
      COUNT(*) FILTER (WHERE a.mode IN ('present','remote'))::int AS present_count,
      COALESCE(SUM(m.vote_weight) FILTER (WHERE a.mode IN ('present','remote')),0)::int AS present_weight
    FROM attendances a
    JOIN members m ON m.id = a.member_id
    WHERE a.tenant_id = :t AND a.meeting_id = :mid
  ");
  $stmt->execute([':t' => $tenantId, ':mid' => $meetingId]);
  $att = $stmt->fetch() ?: ['present_count' => 0, 'present_weight' => 0];

  $data['attendance'] = [
    'eligible_count' => $eligibleCount,
    'eligible_weight' => $eligibleWeight,
    'present_count' => (int)$att['present_count'],
    'present_weight' => (int)$att['present_weight'],
  ];

  // proxies count
  $stmt = $pdo->prepare("SELECT COUNT(*)::int AS c FROM proxies WHERE tenant_id = :t AND meeting_id = :mid AND revoked_at IS NULL");
  $stmt->execute([':t' => $tenantId, ':mid' => $meetingId]);
  $data['proxies'] = ['count' => (int)($stmt->fetch()['c'] ?? 0)];

  // current motion
  $currentMotionId = (string)($meeting['current_motion_id'] ?? '');
  if ($currentMotionId === '') {
    $stmt = $pdo->prepare("
      SELECT id
      FROM motions
      WHERE tenant_id = :t AND meeting_id = :mid AND opened_at IS NOT NULL AND closed_at IS NULL
      ORDER BY opened_at DESC
      LIMIT 1
    ");
    $stmt->execute([':t' => $tenantId, ':mid' => $meetingId]);
    $row = $stmt->fetch();
    if ($row) $currentMotionId = (string)$row['id'];
  }

  if ($currentMotionId !== '') {
    $stmt = $pdo->prepare("
      SELECT id, title, body, opened_at, closed_at
      FROM motions
      WHERE tenant_id = :t AND id = :id
      LIMIT 1
    ");
    $stmt->execute([':t' => $tenantId, ':id' => $currentMotionId]);
    $data['current_motion'] = $stmt->fetch() ?: null;

    // votes (poids) sur la motion en cours
    $stmt = $pdo->prepare("
      SELECT
        COUNT(*)::int AS ballots_count,
        COALESCE(SUM(CASE WHEN COALESCE(value::text, choice)='for' THEN weight ELSE 0 END),0)::int AS weight_for,
        COALESCE(SUM(CASE WHEN COALESCE(value::text, choice)='against' THEN weight ELSE 0 END),0)::int AS weight_against,
        COALESCE(SUM(CASE WHEN COALESCE(value::text, choice)='abstain' THEN weight ELSE 0 END),0)::int AS weight_abstain
      FROM ballots
      WHERE tenant_id = :t AND meeting_id = :mid AND motion_id = :moid
    ");
    $stmt->execute([':t' => $tenantId, ':mid' => $meetingId, ':moid' => $currentMotionId]);
    $data['current_motion_votes'] = $stmt->fetch() ?: $data['current_motion_votes'];
  }

  // openable motions: motions in this meeting that are not open and not closed (draft) OR closed already (allow reopen?) -> only draft here
  $stmt = $pdo->prepare("
    SELECT id, title
    FROM motions
    WHERE tenant_id = :t AND meeting_id = :mid
      AND opened_at IS NULL AND closed_at IS NULL
    ORDER BY position NULLS LAST, created_at ASC
    LIMIT 100
  ");
  $stmt->execute([':t' => $tenantId, ':mid' => $meetingId]);
  $data['openable_motions'] = $stmt->fetchAll() ?: [];

  // ready to sign (même logique que votre validation: aucune motion ouverte + président renseigné + (manuel cohérent ou evote présent) pour motions fermées)
  $reasons = [];

  // président
  $pres = trim((string)($meeting['president_name'] ?? ''));
  if ($pres === '') $reasons[] = "Président non renseigné.";

  // motion ouverte ?
  $stmt = $pdo->prepare("SELECT COUNT(*)::int AS c FROM motions WHERE tenant_id=:t AND meeting_id=:mid AND opened_at IS NOT NULL AND closed_at IS NULL");
  $stmt->execute([':t' => $tenantId, ':mid' => $meetingId]);
  if ((int)($stmt->fetch()['c'] ?? 0) > 0) $reasons[] = "Une motion est encore ouverte.";

  // motions fermées : vérifier qu'il y a soit un comptage manuel cohérent, soit au moins un bulletin
  $stmt = $pdo->prepare("
    SELECT id, title, manual_total, manual_for, manual_against, manual_abstain
    FROM motions
    WHERE tenant_id=:t AND meeting_id=:mid AND closed_at IS NOT NULL
    ORDER BY closed_at ASC
  ");
  $stmt->execute([':t' => $tenantId, ':mid' => $meetingId]);
  $closed = $stmt->fetchAll() ?: [];

  foreach ($closed as $mo) {
    $manualTotal = (int)($mo['manual_total'] ?? 0);
    $sumManual = (int)($mo['manual_for'] ?? 0) + (int)($mo['manual_against'] ?? 0) + (int)($mo['manual_abstain'] ?? 0);

    $stmt2 = $pdo->prepare("SELECT COUNT(*)::int AS c FROM ballots WHERE tenant_id=:t AND meeting_id=:mid AND motion_id=:moid");
    $stmt2->execute([':t' => $tenantId, ':mid' => $meetingId, ':moid' => (string)$mo['id']]);
    $ballotsCount = (int)($stmt2->fetch()['c'] ?? 0);

    $manualOk = ($manualTotal > 0 && $manualTotal === $sumManual);
    $evoteOk  = ($ballotsCount > 0);

    if (!$manualOk && !$evoteOk) {
      $reasons[] = "Comptage manquant pour: " . (string)$mo['title'];
    }
  }

  $data['ready_to_sign'] = [
    'can' => count($reasons) === 0,
    'reasons' => $reasons,
  ];
}

api_ok($data);