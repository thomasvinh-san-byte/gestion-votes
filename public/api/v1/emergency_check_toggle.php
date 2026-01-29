<?php
require __DIR__ . '/../../../app/api.php';

api_require_role(['operator', 'admin']);

$in = api_request('POST');

$meetingId = api_require_uuid($in, 'meeting_id');
api_guard_meeting_not_validated($meetingId);

$procedure = trim((string)($in['procedure_code'] ?? ''));
if ($procedure === '') api_fail('missing_procedure_code', 400);

$idx = (int)($in['item_index'] ?? -1);
if ($idx < 0) api_fail('invalid_item_index', 400);

$checked = (int)($in['checked'] ?? 0) ? true : false;

db_execute(
  "INSERT INTO meeting_emergency_checks(meeting_id, procedure_code, item_index, checked, checked_at, checked_by)
   VALUES (:m,:p,:i,:c, CASE WHEN :c THEN NOW() ELSE NULL END, :by)
   ON CONFLICT (meeting_id, procedure_code, item_index)
   DO UPDATE SET checked = EXCLUDED.checked, checked_at = EXCLUDED.checked_at, checked_by = EXCLUDED.checked_by",
  [
    ':m'=>$meetingId,
    ':p'=>$procedure,
    ':i'=>$idx,
    ':c'=>$checked,
    ':by'=>($GLOBALS['AUTH_USER']['role'] ?? null)
  ]
);

if (function_exists('audit_log')) {
  audit_log('emergency_check_toggled', 'meeting', $meetingId, [
    'procedure_code'=>$procedure,
    'item_index'=>$idx,
    'checked'=>$checked,
  ]);
}

api_ok(['saved'=>true]);
