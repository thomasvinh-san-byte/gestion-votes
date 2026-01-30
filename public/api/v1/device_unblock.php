<?php
// public/api/v1/device_unblock.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

header('Content-Type: application/json; charset=utf-8');
require_any_role(['OPERATOR','ADMIN']);

$in = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($in)) $in = [];

$tenantId  = api_current_tenant_id();
$meetingId = (string)($in['meeting_id'] ?? '');
$deviceId  = (string)($in['device_id'] ?? '');
if ($deviceId === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'missing_device_id']);
  exit;
}

try {
  $hb = null;
  $hbstmt = $pdo->prepare("
    SELECT role, ip, user_agent, battery_pct, is_charging, last_seen_at
    FROM device_heartbeats
    WHERE tenant_id = ?::uuid AND device_id = ?::uuid
    LIMIT 1
  ");
  $hbstmt->execute([$tenantId, $deviceId]);
  $hb = $hbstmt->fetch(PDO::FETCH_ASSOC) ?: null;

  $stmt = $pdo->prepare("
    INSERT INTO device_blocks (tenant_id, meeting_id, device_id, is_blocked, reason, blocked_at, updated_at)
    VALUES (?::uuid, NULLIF(?,'')::uuid, ?::uuid, false, NULL, now(), now())
    ON CONFLICT (COALESCE(meeting_id, '00000000-0000-0000-0000-000000000000'::uuid), device_id)
    DO UPDATE SET is_blocked = false, reason = NULL, updated_at = now()
  ");
  $stmt->execute([$tenantId, $meetingId, $deviceId]);

  if (function_exists('audit_log')) {
    audit_log('device_unblocked', 'device', $deviceId, [
      'meeting_id' => $meetingId,
      'device_id' => $deviceId,
      'role' => $hb['role'] ?? null,
      'ip' => $hb['ip'] ?? null,
      'user_agent' => $hb['user_agent'] ?? null,
      'battery_pct' => isset($hb['battery_pct']) ? (int)$hb['battery_pct'] : null,
      'is_charging' => isset($hb['is_charging']) ? (bool)$hb['is_charging'] : null,
      'last_seen_at' => $hb['last_seen_at'] ?? null,
    ]);
  }

  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error']);
}
