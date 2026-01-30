<?php
// POST: définit (ou efface) motions.quorum_policy_id ('' => hérite de la séance)
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MotionRepository;
use AgVote\Repository\MeetingRepository;

api_require_role(['operator', 'admin']);

$in = api_request('POST');

$motionId = api_require_uuid($in, 'motion_id');

$policyId = trim((string)($in['quorum_policy_id'] ?? ''));
if ($policyId !== '' && !api_is_uuid($policyId)) {
    api_fail('invalid_quorum_policy_id', 400, ['expected' => 'uuid or empty']);
}

$motionRepo = new MotionRepository();
$motion = $motionRepo->findWithMeetingStatus($motionId, api_current_tenant_id());
if (!$motion) api_fail('motion_not_found', 404);

// Garde-fou backend : une motion ACTIVE ne doit pas être modifiée.
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
    $meetingRepo = new MeetingRepository();
    if (!$meetingRepo->quorumPolicyExists($policyId, api_current_tenant_id())) {
        api_fail('quorum_policy_not_found', 404);
    }
}

$motionRepo->updateQuorumPolicy($motionId, $policyId === '' ? null : $policyId);

audit_log('motion_quorum_updated', 'motion', $motionId, [
    'meeting_id' => $motion['meeting_id'],
    'quorum_policy_id' => ($policyId === '' ? null : $policyId),
]);

api_ok(['saved' => true]);
