<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';
require __DIR__ . '/../../../app/services/NotificationsService.php';

// Operator/Trust: lecture des notifications.
require_any_role(['operator','trust']);

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '') api_fail('missing_meeting_id', 400);

$audience = trim((string)($_GET['audience'] ?? 'operator'));
if ($audience === '') $audience = 'operator';

$limit = (int)($_GET['limit'] ?? 80);

$list = NotificationsService::recent($meetingId, $audience, $limit);

foreach ($list as &$n) {
  if (isset($n['data']) && is_string($n['data'])) {
    $decoded = json_decode($n['data'], true);
    if (is_array($decoded)) $n['data'] = $decoded;
  }
}
unset($n);

api_ok(['notifications' => $list]);
