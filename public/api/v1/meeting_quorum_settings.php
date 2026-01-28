<?php
require __DIR__ . '/../../../app/api.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $q = api_request('GET');
    $meetingId = api_require_uuid($q, 'meeting_id');

    $row = db_select_one(
        "SELECT id AS meeting_id, title, quorum_policy_id, COALESCE(convocation_no,1) AS convocation_no
         FROM meetings
         WHERE tenant_id = ? AND id = ?",
        [DEFAULT_TENANT_ID, $meetingId]
    );
    if (!$row) api_fail('meeting_not_found', 404);

    api_ok([
        'meeting_id' => $row['meeting_id'],
        'title' => $row['title'],
        'quorum_policy_id' => $row['quorum_policy_id'],
        'convocation_no' => (int)$row['convocation_no'],
    ]);
}

if ($method === 'POST') {
    $in = api_request('POST');
    $meetingId = api_require_uuid($in, 'meeting_id');

api_guard_meeting_not_validated($meetingId);

    $policyId = trim((string)($in['quorum_policy_id'] ?? ''));
    if ($policyId !== '' && !api_is_uuid($policyId)) {
        api_fail('invalid_quorum_policy_id', 400, ['expected' => 'uuid or empty']);
    }

    $convocationNo = (int)($in['convocation_no'] ?? 1);
    if (!in_array($convocationNo, [1,2], true)) {
        api_fail('invalid_convocation_no', 400, ['expected' => '1 or 2']);
    }

    $m = db_select_one("SELECT id FROM meetings WHERE tenant_id=? AND id=?", [DEFAULT_TENANT_ID, $meetingId]);
    if (!$m) api_fail('meeting_not_found', 404);

    if ($policyId !== '') {
        $p = db_select_one("SELECT id FROM quorum_policies WHERE tenant_id=? AND id=?", [DEFAULT_TENANT_ID, $policyId]);
        if (!$p) api_fail('quorum_policy_not_found', 404);
    }

    db_execute(
        "UPDATE meetings
         SET quorum_policy_id = :pid, convocation_no = :c, updated_at = NOW()
         WHERE tenant_id = :t AND id = :m",
        [
            ':pid' => ($policyId == '' ? null : $policyId),
            ':c' => $convocationNo,
            ':t' => DEFAULT_TENANT_ID,
            ':m' => $meetingId,
        ]
    );

    if (function_exists('audit_log')) {
        audit_log('meeting_quorum_updated', 'meeting', $meetingId, [
            'quorum_policy_id' => ($policyId == '' ? null : $policyId),
            'convocation_no' => $convocationNo,
        ]);
    }

    api_ok(['saved' => true]);
}

api_fail('method_not_allowed', 405);
