<?php
// public/api/v1/device_heartbeat.php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Heartbeat is typically called by voter/projector clients.
// Auth is intentionally not enforced here in DEV mode; in prod you may gate it.
$raw = file_get_contents('php://input');
$in = json_decode($raw ?: '[]', true);
if (!is_array($in)) $in = [];

$tenantId  = DEFAULT_TENANT_ID;
$deviceId  = (string)($in['device_id'] ?? '');
$meetingId = (string)($in['meeting_id'] ?? '');
$role      = (string)($in['role'] ?? '');
$ip        = (string)($_SERVER['REMOTE_ADDR'] ?? ($in['ip'] ?? ''));
$userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? ($in['user_agent'] ?? ''));
$battery   = isset($in['battery_pct']) ? (int)$in['battery_pct'] : null;
$charging  = isset($in['is_charging']) ? (bool)$in['is_charging'] : null;

if ($deviceId === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'missing_device_id']);
  exit;
}

try {
  // Upsert heartbeat
  $sql = "
    INSERT INTO device_heartbeats (
      device_id, tenant_id, meeting_id, role, ip, user_agent, battery_pct, is_charging, last_seen_at
    ) VALUES (
      ?::uuid, ?::uuid, NULLIF(?,'')::uuid, NULLIF(?,'')::text, NULLIF(?,'')::text, NULLIF(?,'')::text,
      ?, ?, now()
    )
    ON CONFLICT (device_id)
    DO UPDATE SET
      meeting_id   = EXCLUDED.meeting_id,
      role         = EXCLUDED.role,
      ip           = EXCLUDED.ip,
      user_agent   = EXCLUDED.user_agent,
      battery_pct  = EXCLUDED.battery_pct,
      is_charging  = EXCLUDED.is_charging,
      last_seen_at = now()
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    $deviceId,
    $tenantId,
    $meetingId,
    $role,
    $ip,
    $userAgent,
    $battery,
    $charging
  ]);

  // Block state
  $bstmt = $pdo->prepare("
    SELECT is_blocked, reason
    FROM device_blocks
    WHERE tenant_id = ?::uuid
      AND device_id = ?::uuid
      AND (meeting_id IS NULL OR meeting_id = NULLIF(?,'')::uuid)
    ORDER BY updated_at DESC
    LIMIT 1
  ");
  $bstmt->execute([$tenantId, $deviceId, $meetingId]);
  $b = $bstmt->fetch(PDO::FETCH_ASSOC);
  $isBlocked = $b ? (bool)$b['is_blocked'] : false;
  $blockReason = $b ? (string)($b['reason'] ?? '') : '';

  // Pending kick command
  $kick = false;
  $kickMsg = '';
  $cstmt = $pdo->prepare("
    SELECT id, payload
    FROM device_commands
    WHERE tenant_id = ?::uuid
      AND device_id = ?::uuid
      AND command = 'kick'
      AND consumed_at IS NULL
    ORDER BY created_at DESC
    LIMIT 1
  ");
  $cstmt->execute([$tenantId, $deviceId]);
  $cmd = $cstmt->fetch(PDO::FETCH_ASSOC);
  if ($cmd) {
    $kick = true;
    $payload = json_decode((string)$cmd['payload'], true);
    if (is_array($payload) && isset($payload['message'])) $kickMsg = (string)$payload['message'];

    $upd = $pdo->prepare("UPDATE device_commands SET consumed_at = now() WHERE id = ?::uuid");
    $upd->execute([$cmd['id']]);
  }

  echo json_encode([
    'ok' => true,
    'device_id' => $deviceId,
    'blocked' => $isBlocked,
    'block_reason' => $blockReason,
    'kick' => $kick,
    'kick_message' => $kickMsg,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error']);
}
