<?php
require __DIR__ . '/../../../app/api.php';

api_request('GET');

$rows = db_select_all(
  "SELECT mt.id, mt.title, mt.archived_at, mt.validated_at, mt.president_name,
          COALESCE(mr.sha256, NULL) AS report_sha256,
          COALESCE(mr.generated_at, NULL) AS report_generated_at,
          (mr.meeting_id IS NOT NULL) AS has_report
   FROM meetings mt
   LEFT JOIN meeting_reports mr ON mr.meeting_id = mt.id
   WHERE mt.tenant_id = ? AND mt.status = 'archived'
   ORDER BY mt.archived_at DESC NULLS LAST, mt.validated_at DESC NULLS LAST",
  [DEFAULT_TENANT_ID]
);

api_ok(['items' => $rows]);
