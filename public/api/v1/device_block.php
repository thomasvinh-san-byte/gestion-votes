<?php
// public/api/v1/device_block.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\DeviceRepository;

header('Content-Type: application/json; charset=utf-8');
api_require_role(['operator', 'admin']);

$in = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($in)) $in = [];

$tenantId  = api_current_tenant_id();
$meetingId = (string)($in['meeting_id'] ?? '');
$deviceId  = (string)($in['device_id'] ?? '');
$reason    = trim((string)($in['reason'] ?? ''));
if ($reason === '') $reason = 'blocked_by_operator';

if ($deviceId === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'missing_device_id']);
  exit;
}

try {
  $repo = new DeviceRepository();

  // Fetch last heartbeat for audit context
  $hb = $repo->findHeartbeat($tenantId, $deviceId);

  $repo->blockDevice($tenantId, $meetingId, $deviceId, $reason);

  if (function_exists('audit_log')) {
    audit_log('device_blocked', 'device', $deviceId, [
      'meeting_id' => $meetingId,
      'device_id' => $deviceId,
      'reason' => $reason,
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
