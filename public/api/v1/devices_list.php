<?php
// public/api/v1/devices_list.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\DeviceRepository;

api_require_role(['operator','admin','trust']);

$q = api_request('GET');
$tenantId = api_current_tenant_id();
$meetingId = (string)($q['meeting_id'] ?? '');

try {
  $repo = new DeviceRepository();
  $rows = $repo->listHeartbeats($tenantId, $meetingId);

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

  api_ok(['counts' => $counts, 'items' => $items]);
} catch (Throwable $e) {
  api_fail('server_error', 500, ['detail' => $e->getMessage()]);
}
