<?php
require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\EmergencyProcedureRepository;

$q = api_request('GET');
$aud = trim((string)($q['audience'] ?? 'operator'));
$meetingId = trim((string)($q['meeting_id'] ?? ''));

$repo = new EmergencyProcedureRepository();

$rows = $repo->listByAudienceWithField($aud);

$checks = [];
if ($meetingId !== '' && api_is_uuid($meetingId)) {
  $checks = $repo->listChecksForMeeting($meetingId);
}

api_ok(['items' => $rows, 'checks' => $checks]);
