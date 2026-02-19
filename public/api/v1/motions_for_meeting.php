<?php
// public/api/v1/motions_for_meeting.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\PolicyRepository;

// Public access: voters and public display need this endpoint
api_require_role('public');

$q = api_request('GET');
$meetingId = api_require_uuid($q, 'meeting_id');

try {
    $meetingRepo = new MeetingRepository();
    $motionRepo = new MotionRepository();

    if (!$meetingRepo->existsForTenant($meetingId, api_current_tenant_id())) {
        api_fail('meeting_not_found', 404);
    }

    // Récupérer toutes les motions + agendas en une fois (JSON agrégé)
    $row = $motionRepo->listForMeetingJson($meetingId);

    $motions = [];

    if ($row && isset($row['motions']) && $row['motions'] !== null) {
        if (is_string($row['motions'])) {
            $decoded = json_decode($row['motions'], true);
            if (is_array($decoded)) {
                $motions = $decoded;
            }
        } elseif (is_array($row['motions'])) {
            $motions = $row['motions'];
        }
    }

    // Enrich motions with vote tallies and policy names
    $stats = $motionRepo->listStatsForMeeting($meetingId);
    $statsMap = [];
    foreach ($stats as $s) {
        $statsMap[(string)$s['motion_id']] = $s;
    }

    $tenantId = api_current_tenant_id();
    $policyRepo = new PolicyRepository();
    $policyNameCache = [];

    foreach ($motions as &$m) {
        // Normalize id/title aliases
        $m['id'] = $m['motion_id'] ?? $m['id'] ?? null;
        $m['title'] = $m['motion_title'] ?? $m['title'] ?? '';
        $m['description'] = $m['motion_description'] ?? $m['description'] ?? '';
        $m['result'] = $m['decision'] ?? null;

        // Add vote tallies from stats
        $mid = (string)$m['id'];
        if (isset($statsMap[$mid])) {
            $m['votes_for']     = (int)$statsMap[$mid]['ballots_for'];
            $m['votes_against'] = (int)$statsMap[$mid]['ballots_against'];
            $m['votes_abstain'] = (int)$statsMap[$mid]['ballots_abstain'];
            $m['votes_nsp']     = (int)$statsMap[$mid]['ballots_nsp'];
            $m['votes_count']   = (int)$statsMap[$mid]['ballots_total'];
        } else {
            $m['votes_for'] = 0;
            $m['votes_against'] = 0;
            $m['votes_abstain'] = 0;
            $m['votes_nsp'] = 0;
            $m['votes_count'] = 0;
        }

        // Resolve policy names (cached)
        $vpId = (string)($m['vote_policy_id'] ?? '');
        if ($vpId !== '' && !isset($policyNameCache['v_' . $vpId])) {
            $policyNameCache['v_' . $vpId] = $policyRepo->findVotePolicyName($tenantId, $vpId);
        }
        $m['vote_policy_name'] = $policyNameCache['v_' . $vpId] ?? null;

        $qpId = (string)($m['quorum_policy_id'] ?? '');
        if ($qpId !== '' && !isset($policyNameCache['q_' . $qpId])) {
            $policyNameCache['q_' . $qpId] = $policyRepo->findQuorumPolicyName($tenantId, $qpId);
        }
        $m['quorum_policy_name'] = $policyNameCache['q_' . $qpId] ?? null;
    }
    unset($m);

    // Motion courante de la séance (peut être NULL)
    $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
    $currentMotionId = $meeting['current_motion_id'] ?? null;

    api_ok([
        'meeting_id'        => $meetingId,
        'current_motion_id' => $currentMotionId,
        'motions'           => $motions,
    ]);
} catch (Throwable $e) {
    error_log("Error in motions_for_meeting.php: " . $e->getMessage());
    api_fail('server_error', 500, ['detail' => $e->getMessage()]);
}
