<?php
require __DIR__ . '/../../../app/api.php';

$q = api_request('GET');
$meetingId = api_require_uuid($q, 'meeting_id');

$events = db_select_all(
  "SELECT created_at, actor_role, actor_id, event_type, entity_type, entity_id, details_json
   FROM audit_events
   WHERE tenant_id = ? AND meeting_id = ?
   ORDER BY created_at ASC",
  [DEFAULT_TENANT_ID, $meetingId]
);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="audit_'.$meetingId.'.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['created_at','actor_role','actor_id','event_type','entity_type','entity_id','details_json']);
foreach ($events as $e) {
    fputcsv($out, [
      $e['created_at'],
      $e['actor_role'],
      $e['actor_id'],
      $e['event_type'],
      $e['entity_type'],
      $e['entity_id'],
      $e['details_json'],
    ]);
}
fclose($out);
exit;
