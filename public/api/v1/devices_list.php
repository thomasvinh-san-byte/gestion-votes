<?php
// public/api/v1/devices_list.php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/auth.php';

header('Content-Type: application/json; charset=utf-8');

require_any_role(['OPERATOR','ADMIN','TRUST']);

$tenantId = DEFAULT_TENANT_ID;
$meetingId = isset($_GET['meeting_id']) ? (string)$_GET['meeting_id'] : '';

try {
  $stmt = $pdo->prepare("
    SELECT
      hb.device_id::text AS device_id,
      hb.meeting_id::text AS meeting_id,
      hb.role,
      hb.ip,
      hb.user_agent,
      hb.battery_pct,
      hb.is_charging,
      hb.last_seen_at,
      COALESCE(db.is_blocked,false) AS is_blocked,
      db.reason AS block_reason
    FROM device_heartbeats hb
    LEFT JOIN LATERAL (
      SELECT is_blocked, reason
      FROM device_blocks
      WHERE tenant_id = hb.tenant_id
        AND device_id = hb.device_id
        AND (meeting_id IS NULL OR meeting_id = hb.meeting_id)
      ORDER BY updated_at DESC
      LIMIT 1
    ) db ON TRUE
    WHERE hb.tenant_id = ?::uuid
      AND (NULLIF(?,'') IS NULL OR hb.meeting_id = NULLIF(?,'')::uuid)
    ORDER BY hb.last_seen_at DESC
    LIMIT 500
  ");
  $stmt->execute([$tenantId, $meetingId, $meetingId]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $now = new DateTimeImmutable('now');
  $onlineCut = $now->sub(new DateInterval('PT30S'));
  $staleCut  = $now->sub(new DateInterval('PT120S'));

  $items = [];
  $counts = ['total'=>0,'online'=>0,'stale'=>0,'offline'=>0,'blocked'=>0];

  foreach ($rows as $r) {
    $counts['total']++;
    $lastSeen = new DateTimeImmutable((string)$r['last_seen_at']);
    $isBlocked = (bool)$r['is_blocked'];
    if ($isBlocked) $counts['blocked']++;

    $status = 'offline';
    if ($lastSeen >= $onlineCut) { $status = 'online'; $counts['online']++; }
    elseif ($lastSeen >= $staleCut) { $status = 'stale'; $counts['stale']++; }
    else { $status = 'offline'; $counts['offline']++; }

    $items[] = [
      'device_id' => (string)$r['device_id'],
      'meeting_id' => (string)($r['meeting_id'] ?? ''),
      'role' => (string)($r['role'] ?? ''),
      'ip' => (string)($r['ip'] ?? ''),
      'user_agent' => (string)($r['user_agent'] ?? ''),
      'battery_pct' => $r['battery_pct'] !== null ? (int)$r['battery_pct'] : null,
      'is_charging' => $r['is_charging'] !== null ? (bool)$r['is_charging'] : null,
      'last_seen_at' => (string)$r['last_seen_at'],
      'status' => $status,
      'is_blocked' => $isBlocked,
      'block_reason' => (string)($r['block_reason'] ?? ''),
    ];
  }

  echo json_encode(['ok'=>true,'counts'=>$counts,'items'=>$items]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error']);
}
