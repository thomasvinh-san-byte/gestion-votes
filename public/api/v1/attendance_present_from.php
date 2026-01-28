<?php
require __DIR__ . '/../../../app/api.php';
api_require_role('operator');

$in = api_request('POST');
$meetingId = api_require_uuid($in, 'meeting_id');
api_guard_meeting_not_validated($meetingId);

$memberId  = api_require_uuid($in, 'member_id');

$presentFrom = trim((string)($in['present_from_at'] ?? ''));
if ($presentFrom === '') {
  db_execute("UPDATE attendances SET present_from_at = NULL, updated_at = NOW() WHERE meeting_id = :m AND member_id = :mb",
    [':m'=>$meetingId, ':mb'=>$memberId]
  );
  api_ok(['saved'=>true]);
}

db_execute(
  "UPDATE attendances SET present_from_at = :p, updated_at = NOW() WHERE meeting_id = :m AND member_id = :mb",
  [':p'=>$presentFrom, ':m'=>$meetingId, ':mb'=>$memberId]
);

if (function_exists('audit_log')) {
  audit_log('attendance_present_from_set', 'meeting', $meetingId, [
    'member_id'=>$memberId,
    'present_from_at'=>$presentFrom,
  ]);
}

api_ok(['saved'=>true]);
