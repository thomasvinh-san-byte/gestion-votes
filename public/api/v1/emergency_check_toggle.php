<?php
require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;

api_require_role(['operator', 'admin']);

$in = api_request('POST');

$meetingId = api_require_uuid($in, 'meeting_id');
api_guard_meeting_not_validated($meetingId);

$procedure = trim((string)($in['procedure_code'] ?? ''));
if ($procedure === '') api_fail('missing_procedure_code', 400);

$idx = (int)($in['item_index'] ?? -1);
if ($idx < 0) api_fail('invalid_item_index', 400);

$checked = (int)($in['checked'] ?? 0) ? true : false;

$repo = new MeetingRepository();
$repo->upsertEmergencyCheck(
    $meetingId,
    $procedure,
    $idx,
    $checked,
    ($GLOBALS['AUTH_USER']['role'] ?? null)
);

if (function_exists('audit_log')) {
  audit_log('emergency_check_toggled', 'meeting', $meetingId, [
    'procedure_code'=>$procedure,
    'item_index'=>$idx,
    'checked'=>$checked,
  ]);
}

api_ok(['saved'=>true]);
