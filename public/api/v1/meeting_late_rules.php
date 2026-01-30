<?php
require __DIR__ . '/../../../app/api.php';
api_require_role('operator');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
  $q = api_request('GET');
  $meetingId = api_require_uuid($q, 'meeting_id');
  $row = db_select_one("SELECT id, late_rule_quorum, late_rule_vote FROM meetings WHERE tenant_id=? AND id=?", [api_current_tenant_id(), $meetingId]);
  if (!$row) api_fail('meeting_not_found', 404);
  api_ok(['meeting_id'=>$row['id'],'late_rule_quorum'=>(bool)$row['late_rule_quorum'],'late_rule_vote'=>(bool)$row['late_rule_vote']]);
}

if ($method === 'POST') {
  $in = api_request('POST');
  $meetingId = api_require_uuid($in, 'meeting_id');
api_guard_meeting_not_validated($meetingId);

  $lrq = (int)($in['late_rule_quorum'] ?? 1) ? true : false;
  $lrv = (int)($in['late_rule_vote'] ?? 1) ? true : false;

  db_execute(
    "UPDATE meetings SET late_rule_quorum=:q, late_rule_vote=:v, updated_at=NOW() WHERE tenant_id=:t AND id=:id",
    [':q'=>$lrq, ':v'=>$lrv, ':t'=>api_current_tenant_id(), ':id'=>$meetingId]
  );

  if (function_exists('audit_log')) {
    audit_log('meeting_late_rules_updated','meeting',$meetingId,['late_rule_quorum'=>$lrq,'late_rule_vote'=>$lrv]);
  }

  api_ok(['saved'=>true]);
}

api_fail('method_not_allowed', 405);
