<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Service\NotificationsService;

// Audience par défaut: operator. Trust peut aussi lire.
require_any_role(['operator','trust']);

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '') api_fail('missing_meeting_id', 400);

$audience = trim((string)($_GET['audience'] ?? 'operator'));
if ($audience === '') $audience = 'operator';

$sinceId = (int)($_GET['since_id'] ?? 0);
$limit   = (int)($_GET['limit'] ?? 30);

$list = NotificationsService::list($meetingId, $audience, $sinceId, $limit);

// Normaliser le champ data (jsonb) en tableau pour le front.
foreach ($list as &$n) {
  if (isset($n['data']) && is_string($n['data'])) {
    $decoded = json_decode($n['data'], true);
    if (is_array($decoded)) $n['data'] = $decoded;
  }
}
unset($n);

api_ok([
  'notifications' => $list,
]);
