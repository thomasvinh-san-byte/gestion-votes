<?php
// public/api/v1/device_heartbeat.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\DeviceRepository;

// Heartbeat: enforce auth in production, allow public in dev
api_require_role('public');

$in = api_request('POST');

$tenantId  = api_current_tenant_id();
$deviceId  = (string)($in['device_id'] ?? '');
$meetingId = (string)($in['meeting_id'] ?? '');
$role      = (string)($in['role'] ?? '');
$ip        = (string)($_SERVER['REMOTE_ADDR'] ?? ($in['ip'] ?? ''));
$userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? ($in['user_agent'] ?? ''));
$battery   = isset($in['battery_pct']) ? (int)$in['battery_pct'] : null;
$charging  = isset($in['is_charging']) ? (bool)$in['is_charging'] : null;

if ($deviceId === '') {
  api_fail('missing_device_id', 400);
}

try {
  $repo = new DeviceRepository();

  // Upsert heartbeat
  $repo->upsertHeartbeat($deviceId, $tenantId, $meetingId, $role, $ip, $userAgent, $battery, $charging);

  // Block state
  $b = $repo->findBlockStatus($tenantId, $deviceId, $meetingId);
  $isBlocked = $b ? (bool)$b['is_blocked'] : false;
  $blockReason = $b ? (string)($b['reason'] ?? '') : '';

  // Pending kick command
  $command = null;
  $cmd = $repo->findPendingKick($tenantId, $deviceId);
  if ($cmd) {
    $payload = json_decode((string)$cmd['payload'], true);
    $kickMsg = (is_array($payload) && isset($payload['message'])) ? (string)$payload['message'] : '';
    $command = ['type' => 'kick', 'message' => $kickMsg];
    $repo->consumeCommand((string)$cmd['id']);
  }

  api_ok([
    'device_id' => $deviceId,
    'blocked' => $isBlocked,
    'block_reason' => $blockReason,
    'command' => $command,
  ]);
} catch (Throwable $e) {
  api_fail('server_error', 500, ['detail' => $e->getMessage()]);
}
