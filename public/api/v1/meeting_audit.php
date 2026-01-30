<?php
// public/api/v1/meeting_audit.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;

api_require_role(['auditor', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_fail('method_not_allowed', 405);
}

$meetingId = trim($_GET['meeting_id'] ?? '');
if ($meetingId === '') {
    api_fail('missing_meeting_id', 422);
}

$repo = new MeetingRepository();

if (!$repo->existsForTenant($meetingId, api_current_tenant_id())) {
    api_fail('meeting_not_found', 404);
}

$rows = $repo->listAuditEvents($meetingId, api_current_tenant_id(), 200, 'ASC');

api_ok([
    'meeting_id' => $meetingId,
    'events'     => $rows,
]);
