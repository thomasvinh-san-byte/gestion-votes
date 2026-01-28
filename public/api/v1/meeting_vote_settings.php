<?php
// GET: vote_policy_id par défaut de la séance
// POST: définit vote_policy_id par défaut de la séance
require __DIR__ . '/../../../app/api.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $q = api_request('GET');
    $meetingId = api_require_uuid($q, 'meeting_id');

    $row = db_select_one(
        "SELECT id AS meeting_id, title, vote_policy_id
         FROM meetings
         WHERE tenant_id = ? AND id = ?",
        [DEFAULT_TENANT_ID, $meetingId]
    );
    if (!$row) api_fail('meeting_not_found', 404);

    api_ok([
        'meeting_id' => $row['meeting_id'],
        'title' => $row['title'],
        'vote_policy_id' => $row['vote_policy_id'],
    ]);
}

if ($method === 'POST') {
    $in = api_request('POST');
    $meetingId = api_require_uuid($in, 'meeting_id');

api_guard_meeting_not_validated($meetingId);

    $policyId = trim((string)($in['vote_policy_id'] ?? ''));
    if ($policyId !== '' && !api_is_uuid($policyId)) {
        api_fail('invalid_vote_policy_id', 400, ['expected' => 'uuid or empty']);
    }

    $m = db_select_one("SELECT id FROM meetings WHERE tenant_id=? AND id=?", [DEFAULT_TENANT_ID, $meetingId]);
    if (!$m) api_fail('meeting_not_found', 404);

    if ($policyId !== '') {
        $p = db_select_one("SELECT id FROM vote_policies WHERE tenant_id=? AND id=?", [DEFAULT_TENANT_ID, $policyId]);
        if (!$p) api_fail('vote_policy_not_found', 404);
    }

    db_execute(
        "UPDATE meetings
         SET vote_policy_id = :pid, updated_at = NOW()
         WHERE tenant_id = :t AND id = :m",
        [
            ':pid' => ($policyId == '' ? null : $policyId),
            ':t' => DEFAULT_TENANT_ID,
            ':m' => $meetingId,
        ]
    );

    if (function_exists('audit_log')) {
        audit_log('meeting_vote_policy_updated', 'meeting', $meetingId, [
            'vote_policy_id' => ($policyId == '' ? null : $policyId),
        ]);
    }

    api_ok(['saved' => true]);
}

api_fail('method_not_allowed', 405);
