<?php
// public/api/v1/device_kick.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\DeviceRepository;

api_require_role(['operator','admin']);

$in = api_request('POST');

$tenantId  = api_current_tenant_id();
$meetingId = (string)($in['meeting_id'] ?? '');
$deviceId  = (string)($in['device_id'] ?? '');
$message   = trim((string)($in['message'] ?? ''));
if ($message === '') $message = 'Veuillez recharger la page.';

if ($deviceId === '') {
  api_fail('missing_device_id', 400);
}

try {
  $repo = new DeviceRepository();

  $hb = $repo->findHeartbeat($tenantId, $deviceId);

  $repo->insertKickCommand($tenantId, $meetingId, $deviceId, $message);

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

  api_ok([]);
} catch (Throwable $e) {
  api_fail('server_error', 500, ['detail' => $e->getMessage()]);
}
