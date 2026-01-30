<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';
require __DIR__ . '/../../../app/services/NotificationsService.php';

require_any_role(['operator','trust']);

$in = json_input();
$meetingId = trim((string)($in['meeting_id'] ?? ''));
$id = (int)($in['id'] ?? 0);

if ($meetingId === '') json_err('missing_meeting_id', 400);
if ($id <= 0) json_err('missing_id', 400);

NotificationsService::markRead($meetingId, $id);
json_ok(['ok' => true]);
