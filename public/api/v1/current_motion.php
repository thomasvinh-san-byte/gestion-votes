<?php
// public/api/v1/current_motion.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\BallotRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;

// Allow authenticated users (voters, operators, etc.) to query current motion
api_require_role('public');

try {
    api_request('GET');

    $meetingId = trim((string)($_GET['meeting_id'] ?? ''));
    if ($meetingId === '' || !api_is_uuid($meetingId)) {
        api_fail('invalid_request', 422);
    }

    $tenantId = api_current_tenant_id();
    $motionRepo = new MotionRepository();
    $motion = $motionRepo->findCurrentOpen($meetingId, $tenantId);

    // KPI context for voter progress & participation
    $totalMotions  = $motionRepo->countForMeeting($meetingId);
    $meetingRepo   = new MeetingRepository();
    $eligibleCount = $meetingRepo->countActiveMembers($tenantId);

    $ballotsCast = 0;
    if ($motion) {
        $ballotRepo  = new BallotRepository();
        $ballotsCast = $ballotRepo->countByMotionId((string)$motion['id']);
    }

    api_ok([
        'motion'         => $motion,
        'total_motions'  => $totalMotions,
        'eligible_count' => $eligibleCount,
        'ballots_cast'   => $ballotsCast,
    ]);
} catch (Throwable $e) {
    error_log('Error in current_motion.php: ' . $e->getMessage());
    api_fail('internal_error', 500);
}
