<?php
declare(strict_types=1);

// public/api/v1/invitations_list.php
require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\InvitationRepository;

api_require_role('operator');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '') {
    api_fail('missing_meeting_id', 400);
}

$repo = new InvitationRepository();
$rows = $repo->listForMeeting($meetingId, api_current_tenant_id());

api_ok(['invitations' => $rows]);
