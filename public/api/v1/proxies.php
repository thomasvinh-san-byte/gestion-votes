<?php
// public/api/v1/proxies.php
require __DIR__ . '/../../../app/api.php';

api_require_any_role(['operator', 'trust', 'admin']);

$q = api_request('GET');
$meetingId = api_require_uuid($q, 'meeting_id');

require_once __DIR__ . '/../../../app/services/ProxiesService.php';

$rows = ProxiesService::listForMeeting($meetingId);

api_ok([
    'meeting_id' => $meetingId,
    'count'      => count($rows),
    'proxies'    => $rows,
]);