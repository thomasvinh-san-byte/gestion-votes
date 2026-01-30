<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';
require __DIR__ . '/../../../app/services/OfficialResultsService.php';

require_role('trust');

$body = json_read();
$meetingId = trim((string)($body['meeting_id'] ?? ''));
if ($meetingId === '') json_err('missing_meeting_id', 400);

try {
    $r = OfficialResultsService::consolidateMeeting($meetingId);
    json_ok(['updated_motions' => $r['updated']]);
} catch (Throwable $e) {
    json_err('consolidate_failed', 500, ['detail' => $e->getMessage()]);
}
