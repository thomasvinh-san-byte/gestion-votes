<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';
require __DIR__ . '/../../../app/services/NotificationsService.php';

require_any_role(['operator','trust']);

$in = json_input();
$meetingId = trim((string)($in['meeting_id'] ?? ''));
$audience = trim((string)($in['audience'] ?? 'operator'));
if ($audience === '') $audience = 'operator';

if ($meetingId === '') json_err('missing_meeting_id', 400);

NotificationsService::clear($meetingId, $audience);
json_ok(['ok' => true]);
