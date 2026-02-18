<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Service\OfficialResultsService;

api_require_role('auditor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_fail('method_not_allowed', 405);
}

$body = json_decode($GLOBALS['__ag_vote_raw_body'] ?? file_get_contents('php://input'), true) ?? [];
$meetingId = trim((string)($body['meeting_id'] ?? ''));
if ($meetingId === '') api_fail('missing_meeting_id', 400);

try {
    $r = OfficialResultsService::consolidateMeeting($meetingId);
    api_ok(['updated_motions' => $r['updated']]);
} catch (Throwable $e) {
    error_log('meeting_consolidate.php: ' . $e->getMessage());
    api_fail('consolidate_failed', 500);
}
