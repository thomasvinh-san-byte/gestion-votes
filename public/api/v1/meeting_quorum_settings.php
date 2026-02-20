<?php
// GET/POST: quorum_policy_id + convocation_no de la séance
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;

api_require_role(['operator', 'admin']);

$method = api_method();
$repo = new MeetingRepository();

if ($method === 'GET') {
    $q = api_request('GET');
    $meetingId = api_require_uuid($q, 'meeting_id');

    $row = $repo->findQuorumSettings($meetingId, api_current_tenant_id());
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

    if (!$repo->existsForTenant($meetingId, api_current_tenant_id())) {
        api_fail('meeting_not_found', 404);
    }

    if ($policyId !== '') {
        if (!$repo->quorumPolicyExists($policyId, api_current_tenant_id())) {
            api_fail('quorum_policy_not_found', 404);
        }
    }

    $repo->updateQuorumPolicy($meetingId, api_current_tenant_id(), $policyId === '' ? null : $policyId, $convocationNo);

    audit_log('meeting_quorum_updated', 'meeting', $meetingId, [
        'quorum_policy_id' => ($policyId === '' ? null : $policyId),
        'convocation_no' => $convocationNo,
    ]);

    api_ok(['saved' => true]);
}

api_fail('method_not_allowed', 405);
