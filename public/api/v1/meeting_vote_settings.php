<?php
// GET: vote_policy_id par défaut de la séance
// POST: définit vote_policy_id par défaut de la séance
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;

api_require_role(['operator', 'admin']);

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$repo = new MeetingRepository();

if ($method === 'GET') {
    $q = api_request('GET');
    $meetingId = api_require_uuid($q, 'meeting_id');

    $row = $repo->findVoteSettings($meetingId, api_current_tenant_id());
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

    if (!$repo->existsForTenant($meetingId, api_current_tenant_id())) {
        api_fail('meeting_not_found', 404);
    }

    if ($policyId !== '') {
        if (!$repo->votePolicyExists($policyId, api_current_tenant_id())) {
            api_fail('vote_policy_not_found', 404);
        }
    }

    $repo->updateVotePolicy($meetingId, api_current_tenant_id(), $policyId === '' ? null : $policyId);

    audit_log('meeting_vote_policy_updated', 'meeting', $meetingId, [
        'vote_policy_id' => ($policyId === '' ? null : $policyId),
    ]);

    api_ok(['saved' => true]);
}

api_fail('method_not_allowed', 405);
