<?php
// public/api/v1/wizard_status.php
// Endpoint leger pour le polling du wizard de seance.
// Retourne l'etat synthetique d'une seance en un seul appel.
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\WizardRepository;

api_require_role('viewer'); // any authenticated user

$meetingId = trim($_GET['meeting_id'] ?? '');
if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 422);
}

$tenantId = api_current_tenant_id();
$wizardRepo = new WizardRepository();

try {
    // Meeting basics
    $m = $wizardRepo->getMeetingBasics($meetingId, $tenantId);

    if (!$m) {
        api_fail('meeting_not_found', 404);
    }

    // Members count: enrolled in this meeting (via attendances table)
    $membersCount = $wizardRepo->countAttendances($meetingId);

    // Fallback: if no attendances yet, count all active members for tenant
    if ($membersCount === 0) {
        $membersCount = $wizardRepo->countActiveMembers($tenantId);
    }

    // Attendance: present count (present, remote, proxy)
    $presentCount = $wizardRepo->countPresentAttendances($meetingId);

    // Motions counts
    $motionsCounts = $wizardRepo->getMotionsCounts($meetingId);
    $motionsTotal = $motionsCounts['total'];
    $motionsClosed = $motionsCounts['closed'];

    // President assigned?
    $hasPresident = $wizardRepo->hasPresident($meetingId);

    // Quorum — simplified check
    $quorumMet = false;
    if ($membersCount > 0) {
        // Use quorum_status endpoint logic simplified: present / eligible
        $ratio = $presentCount / $membersCount;
        // Default threshold 0 (no quorum required) if no policy
        $quorumMet = $ratio > 0;
        if ($m['quorum_policy_id']) {
            $threshold = $wizardRepo->getQuorumThreshold($m['quorum_policy_id']);
            if ($threshold !== null) {
                $quorumMet = $ratio >= $threshold;
            }
        }
    }

    api_ok([
        'meeting_id'        => $m['id'],
        'meeting_title'     => $m['title'],
        'meeting_status'    => $m['status'],
        'current_motion_id' => $m['current_motion_id'],
        'members_count'     => $membersCount,
        'present_count'     => $presentCount,
        'motions_total'     => $motionsTotal,
        'motions_closed'    => $motionsClosed,
        'has_president'     => $hasPresident,
        'quorum_met'        => $quorumMet,
        'policies_assigned' => !empty($m['vote_policy_id']) && !empty($m['quorum_policy_id']),
    ]);
} catch (Throwable $e) {
    error_log("wizard_status.php error: " . $e->getMessage());
    api_fail('internal_error', 500);
}
