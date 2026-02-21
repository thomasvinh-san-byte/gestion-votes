<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\WizardRepository;

/**
 * Consolidates dashboard.php and wizard_status.php.
 */
final class DashboardController extends AbstractController
{
    public function index(): void
    {
        $meetingId = isset($_GET['meeting_id']) ? (string)$_GET['meeting_id'] : '';
        $tenantId = api_current_tenant_id();

        $meetingRepo = new MeetingRepository();
        $memberRepo  = new MemberRepository();
        $attRepo     = new AttendanceRepository();
        $motionRepo  = new MotionRepository();
        $ballotRepo  = new BallotRepository();

        $meetings = $meetingRepo->listForDashboard($tenantId);

        $suggested = null;
        foreach ($meetings as $m) {
            if (in_array($m['status'] ?? '', ['live', 'paused'], true)) {
                $suggested = $m['id'];
                break;
            }
        }
        if ($suggested === null && count($meetings) > 0) {
            $suggested = $meetings[0]['id'];
        }

        if ($meetingId === '') {
            $meetingId = (string)($suggested ?? '');
        }

        $data = [
            'meetings' => $meetings,
            'suggested_meeting_id' => $suggested,
            'meeting' => null,
            'attendance' => [
                'eligible_count' => null, 'eligible_weight' => null,
                'present_count' => 0, 'present_weight' => 0,
            ],
            'proxies' => ['count' => 0],
            'current_motion' => null,
            'current_motion_votes' => ['ballots_count' => 0, 'weight_for' => 0, 'weight_against' => 0, 'weight_abstain' => 0],
            'openable_motions' => [],
            'ready_to_sign' => ['can' => false, 'reasons' => []],
        ];

        if ($meetingId !== '') {
            $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
            if (!$meeting) {
                api_fail('meeting_not_found', 404);
            }
            $data['meeting'] = $meeting;

            $eligibleCount  = $memberRepo->countNotDeleted($tenantId);
            $eligibleWeight = $memberRepo->sumNotDeletedVoteWeight($tenantId);
            $att = $attRepo->dashboardSummary($tenantId, $meetingId);

            $data['attendance'] = [
                'eligible_count' => $eligibleCount,
                'eligible_weight' => $eligibleWeight,
                'present_count' => (int)$att['present_count'],
                'present_weight' => (int)$att['present_weight'],
            ];

            $data['proxies'] = ['count' => $meetingRepo->countActiveProxies($tenantId, $meetingId)];

            $currentMotionId = (string)($meeting['current_motion_id'] ?? '');
            if ($currentMotionId === '') {
                $openMotion = $motionRepo->findCurrentOpen($meetingId, $tenantId);
                if ($openMotion) {
                    $currentMotionId = (string)$openMotion['id'];
                }
            }

            if ($currentMotionId !== '') {
                $motionData = $motionRepo->findByIdForTenant($currentMotionId, $tenantId);
                $data['current_motion'] = $motionData ?: null;

                $t = $ballotRepo->tally($currentMotionId, $tenantId);
                $data['current_motion_votes'] = [
                    'ballots_count'  => (int)$t['total_ballots'],
                    'weight_for'     => (int)$t['weight_for'],
                    'weight_against' => (int)$t['weight_against'],
                    'weight_abstain' => (int)$t['weight_abstain'],
                ];
            }

            $data['openable_motions'] = $motionRepo->listOpenable($tenantId, $meetingId);

            $reasons = [];

            $pres = trim((string)($meeting['president_name'] ?? ''));
            if ($pres === '') {
                $reasons[] = "Président non renseigné.";
            }

            $openCount = $meetingRepo->countOpenMotions($meetingId);
            if ($openCount > 0) {
                $reasons[] = "Une motion est encore ouverte.";
            }

            $closed = $motionRepo->listClosedWithManualTally($tenantId, $meetingId);

            foreach ($closed as $mo) {
                $manualTotal = (int)($mo['manual_total'] ?? 0);
                $sumManual = (int)($mo['manual_for'] ?? 0) + (int)($mo['manual_against'] ?? 0) + (int)($mo['manual_abstain'] ?? 0);

                $ballotsCount = $ballotRepo->countForMotion($tenantId, $meetingId, (string)$mo['id']);

                $manualOk = ($manualTotal > 0 && $manualTotal === $sumManual);
                $evoteOk  = ($ballotsCount > 0);

                if (!$manualOk && !$evoteOk) {
                    $reasons[] = "Comptage manquant pour: " . (string)$mo['title'];
                }
            }

            $data['ready_to_sign'] = [
                'can' => count($reasons) === 0,
                'reasons' => $reasons,
            ];
        }

        api_ok($data);
    }

    public function wizardStatus(): void
    {
        $meetingId = trim($_GET['meeting_id'] ?? '');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 422);
        }

        $tenantId = api_current_tenant_id();
        $wizardRepo = new WizardRepository();

        $m = $wizardRepo->getMeetingBasics($meetingId, $tenantId);
        if (!$m) {
            api_fail('meeting_not_found', 404);
        }

        $membersCount = $wizardRepo->countAttendances($meetingId);
        if ($membersCount === 0) {
            $membersCount = $wizardRepo->countActiveMembers($tenantId);
        }

        $presentCount = $wizardRepo->countPresentAttendances($meetingId);

        $motionsCounts = $wizardRepo->getMotionsCounts($meetingId);
        $motionsTotal = $motionsCounts['total'];
        $motionsClosed = $motionsCounts['closed'];

        $hasPresident = $wizardRepo->hasPresident($meetingId);

        $quorumMet = false;
        if ($membersCount > 0) {
            $ratio = $presentCount / $membersCount;
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
    }
}
