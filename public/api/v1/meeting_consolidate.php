<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Service\OfficialResultsService;

api_require_role('auditor');

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$meetingId = trim((string)($body['meeting_id'] ?? ''));
if ($meetingId === '') api_fail('missing_meeting_id', 400);

try {
    $r = OfficialResultsService::consolidateMeeting($meetingId);
    api_ok(['updated_motions' => $r['updated']]);
} catch (Throwable $e) {
    api_fail('consolidate_failed', 500, ['detail' => $e->getMessage()]);
}
