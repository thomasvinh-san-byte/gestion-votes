<?php
require __DIR__ . '/../../../app/api.php';

$q = api_request('GET');
$aud = trim((string)($q['audience'] ?? 'operator'));
$meetingId = trim((string)($q['meeting_id'] ?? ''));

$rows = db_select_all(
  "SELECT code, title, audience, steps_json
   FROM emergency_procedures
   WHERE audience = ?
   ORDER BY code ASC",
  [$aud]
);

$checks = [];
if ($meetingId !== '' && api_is_uuid($meetingId)) {
  $checks = db_select_all(
    "SELECT procedure_code, item_index, checked
     FROM meeting_emergency_checks
     WHERE meeting_id = ?",
    [$meetingId]
  );
}

api_ok(['items' => $rows, 'checks' => $checks]);
