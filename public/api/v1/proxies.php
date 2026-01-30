<?php
// public/api/v1/proxies.php
require __DIR__ . '/../../../app/api.php';

use AgVote\Service\ProxiesService;

api_require_any_role(['operator', 'trust', 'admin']);

$q = api_request('GET');
$meetingId = api_require_uuid($q, 'meeting_id');

$rows = ProxiesService::listForMeeting($meetingId);

api_ok([
    'meeting_id' => $meetingId,
    'count'      => count($rows),
    'proxies'    => $rows,
]);