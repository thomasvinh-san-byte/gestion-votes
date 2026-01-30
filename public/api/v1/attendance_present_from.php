<?php
require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\AttendanceRepository;

api_require_role('operator');

$in = api_request('POST');
$meetingId = api_require_uuid($in, 'meeting_id');
api_guard_meeting_not_validated($meetingId);

$memberId  = api_require_uuid($in, 'member_id');

$presentFrom = trim((string)($in['present_from_at'] ?? ''));

$repo = new AttendanceRepository();
$repo->updatePresentFrom($meetingId, $memberId, $presentFrom === '' ? null : $presentFrom);

if ($presentFrom !== '' && function_exists('audit_log')) {
  audit_log('attendance_present_from_set', 'meeting', $meetingId, [
    'member_id'=>$memberId,
    'present_from_at'=>$presentFrom,
  ]);
}

api_ok(['saved'=>true]);
