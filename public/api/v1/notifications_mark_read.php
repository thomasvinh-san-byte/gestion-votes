<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Service\NotificationsService;

require_any_role(['operator','trust']);

$in = json_input();
$meetingId = trim((string)($in['meeting_id'] ?? ''));
$id = (int)($in['id'] ?? 0);

if ($meetingId === '') api_fail('missing_meeting_id', 400);
if ($id <= 0) api_fail('missing_id', 400);

NotificationsService::markRead($meetingId, $id);
api_ok(['ok' => true]);
