<?php
// public/api/v1/device_kick.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

header('Content-Type: application/json; charset=utf-8');
require_any_role(['OPERATOR','ADMIN']);

$in = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($in)) $in = [];

$tenantId  = DEFAULT_TENANT_ID;
$meetingId = (string)($in['meeting_id'] ?? '');
$deviceId  = (string)($in['device_id'] ?? '');
$message   = trim((string)($in['message'] ?? ''));
if ($message === '') $message = 'Veuillez recharger la page.';

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
    INSERT INTO device_commands (tenant_id, meeting_id, device_id, command, payload)
    VALUES (?::uuid, NULLIF(?,'')::uuid, ?::uuid, 'kick', ?::jsonb)
  ");
  $stmt->execute([$tenantId, $meetingId, $deviceId, json_encode(['message'=>$message])]);

  if (function_exists('audit_log')) {
    audit_log('device_kicked', 'device', $deviceId, [
      'meeting_id' => $meetingId,
      'device_id' => $deviceId,
      'message' => $message,
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
