<?php
require __DIR__ . '/../../../app/api.php';

api_require_role(['operator', 'admin']);

$in = api_request('POST');

$motionId = api_require_uuid($in, 'motion_id');

$policyId = trim((string)($in['quorum_policy_id'] ?? ''));
if ($policyId !== '' && !api_is_uuid($policyId)) {
    api_fail('invalid_quorum_policy_id', 400, ['expected' => 'uuid or empty']);
}

$motion = db_select_one(
    "SELECT mo.id, mo.meeting_id, mo.opened_at, mo.closed_at
     FROM motions mo
     JOIN meetings mt ON mt.id = mo.meeting_id
     WHERE mt.tenant_id = ? AND mo.id = ?",
    [api_current_tenant_id(), $motionId]
);
if (!$motion) api_fail('motion_not_found', 404);

// Garde-fou backend : une motion ACTIVE (ouverte et non clôturée) ne doit pas être modifiée.
// (Et, par cohérence juridique, une motion clôturée ne doit pas être modifiée non plus.)
if (!empty($motion['opened_at']) && empty($motion['closed_at'])) {
    api_fail('motion_active_locked', 409, [
        'detail' => 'Motion active : modification interdite pendant le vote.'
    ]);
}
if (!empty($motion['closed_at'])) {
    api_fail('motion_closed_locked', 409, [
        'detail' => 'Motion clôturée : modification interdite.'
    ]);
}

if ($policyId !== '') {
    $p = db_select_one("SELECT id FROM quorum_policies WHERE tenant_id=? AND id=?", [api_current_tenant_id(), $policyId]);
    if (!$p) api_fail('quorum_policy_not_found', 404);
}

db_execute(
  "UPDATE motions SET quorum_policy_id = :pid, updated_at = NOW() WHERE id = :id",
  [':pid' => ($policyId == '' ? null : $policyId), ':id' => $motionId]
);

if (function_exists('audit_log')) {
    audit_log('motion_quorum_updated', 'motion', $motionId, [
        'meeting_id' => $motion['meeting_id'],
        'quorum_policy_id' => ($policyId == '' ? null : $policyId),
    ]);
}

api_ok(['saved' => true]);
