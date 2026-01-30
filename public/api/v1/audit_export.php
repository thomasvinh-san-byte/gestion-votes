<?php
require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;

$q = api_request('GET');
$meetingId = api_require_uuid($q, 'meeting_id');

$repo = new MeetingRepository();
$events = $repo->listAuditEventsForExport(api_current_tenant_id(), $meetingId);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="audit_'.$meetingId.'.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['created_at','actor_role','actor_user_id','action','resource_type','resource_id','payload']);
foreach ($events as $e) {
    fputcsv($out, [
      $e['created_at'],
      $e['actor_role'],
      $e['actor_user_id'],
      $e['action'],
      $e['resource_type'],
      $e['resource_id'],
      is_string($e['payload']) ? $e['payload'] : json_encode($e['payload']),
    ]);
}
fclose($out);
exit;
