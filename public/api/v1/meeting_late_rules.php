<?php
// GET/POST: late rules de la séance
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;

api_require_role('operator');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$repo = new MeetingRepository();

if ($method === 'GET') {
    $q = api_request('GET');
    $meetingId = api_require_uuid($q, 'meeting_id');

    $row = $repo->findLateRules($meetingId, api_current_tenant_id());
    if (!$row) api_fail('meeting_not_found', 404);

    api_ok([
        'meeting_id' => $row['id'],
        'late_rule_quorum' => (bool)$row['late_rule_quorum'],
        'late_rule_vote' => (bool)$row['late_rule_vote'],
    ]);
}

if ($method === 'POST') {
    $in = api_request('POST');
    $meetingId = api_require_uuid($in, 'meeting_id');

    api_guard_meeting_not_validated($meetingId);

    $lrq = (int)($in['late_rule_quorum'] ?? 1) ? true : false;
    $lrv = (int)($in['late_rule_vote'] ?? 1) ? true : false;

    $repo->updateLateRules($meetingId, api_current_tenant_id(), $lrq, $lrv);

    audit_log('meeting_late_rules_updated', 'meeting', $meetingId, [
        'late_rule_quorum' => $lrq,
        'late_rule_vote' => $lrv,
    ]);

    api_ok(['saved' => true]);
}

api_fail('method_not_allowed', 405);
