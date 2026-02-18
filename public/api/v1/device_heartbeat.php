<?php
// public/api/v1/device_heartbeat.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\DeviceRepository;

header('Content-Type: application/json; charset=utf-8');

// Heartbeat: enforce auth in production, allow public in dev
api_require_role('public');

$raw = file_get_contents('php://input');
$in = json_decode($raw ?: '[]', true);
if (!is_array($in)) $in = [];

$tenantId  = api_current_tenant_id();
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
  $repo = new DeviceRepository();

  // Upsert heartbeat
  $repo->upsertHeartbeat($deviceId, $tenantId, $meetingId, $role, $ip, $userAgent, $battery, $charging);

  // Block state
  $b = $repo->findBlockStatus($tenantId, $deviceId, $meetingId);
  $isBlocked = $b ? (bool)$b['is_blocked'] : false;
  $blockReason = $b ? (string)($b['reason'] ?? '') : '';

  // Pending kick command
  $kick = false;
  $kickMsg = '';
  $cmd = $repo->findPendingKick($tenantId, $deviceId);
  if ($cmd) {
    $kick = true;
    $payload = json_decode((string)$cmd['payload'], true);
    if (is_array($payload) && isset($payload['message'])) $kickMsg = (string)$payload['message'];

    $repo->consumeCommand((string)$cmd['id']);
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
