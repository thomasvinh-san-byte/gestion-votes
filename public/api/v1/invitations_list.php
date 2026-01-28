<?php
declare(strict_types=1);

// public/api/v1/invitations_list.php
require __DIR__ . '/../../../app/api.php';

api_require_role('operator');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '') {
    api_fail('missing_meeting_id', 400);
}

$rows = db_all(
    "SELECT i.id, i.meeting_id, i.member_id, i.email, i.token, i.status, i.sent_at, i.responded_at,
            m.display_name, m.voting_power
     FROM invitations i
     JOIN members m ON m.id = i.member_id
     WHERE i.meeting_id = :meeting_id
     ORDER BY m.display_name ASC",
    [':meeting_id' => $meetingId]
);

api_ok(['invitations' => $rows]);