<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\PolicyRepository;

final class TrustController extends AbstractController
{
    public function anomalies(): void
    {
        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }

        $tenantId = api_current_tenant_id();

        $meeting = (new MeetingRepository())->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }

        $anomalies = [];

        // 1. Votes sans présence enregistrée
        $votesWithoutAttendance = (new BallotRepository())->listVotesWithoutAttendance($meetingId, $tenantId);
        foreach ($votesWithoutAttendance as $row) {
            $anomalies[] = [
                'id' => 'vote_no_attendance_' . $row['member_id'],
                'type' => 'vote_without_attendance',
                'severity' => 'warning',
                'title' => 'Vote sans présence',
                'description' => sprintf('%s a voté sur "%s" sans être marqué présent', $row['full_name'], $row['motion_title']),
                'member_id' => $row['member_id'],
                'member_name' => $row['full_name'],
            ];
        }

        // 2. Doubles votes potentiels
        $duplicateVotes = (new BallotRepository())->listDuplicateVotes($meetingId);
        foreach ($duplicateVotes as $row) {
            $anomalies[] = [
                'id' => 'duplicate_vote_' . $row['member_id'],
                'type' => 'duplicate_vote',
                'severity' => 'danger',
                'title' => 'Votes multiples détectés',
                'description' => sprintf('%s a %d votes sur "%s"', $row['full_name'], $row['vote_count'], $row['motion_title']),
                'member_id' => $row['member_id'],
                'member_name' => $row['full_name'],
                'vote_count' => (int)$row['vote_count'],
            ];
        }

        // 3. Incohérences de pondération
        $weightMismatches = (new BallotRepository())->listWeightMismatches($meetingId);
        foreach ($weightMismatches as $row) {
            $anomalies[] = [
                'id' => 'weight_mismatch_' . $row['member_id'],
                'type' => 'weight_mismatch',
                'severity' => 'warning',
                'title' => 'Pondération incohérente',
                'description' => sprintf('%s: poids voté %.2f ≠ poids membre %.2f sur "%s"', $row['full_name'], $row['actual_weight'], $row['expected_weight'], $row['motion_title']),
                'member_id' => $row['member_id'],
                'member_name' => $row['full_name'],
                'expected_weight' => (float)$row['expected_weight'],
                'actual_weight' => (float)$row['actual_weight'],
            ];
        }

        // 4. Procurations orphelines
        $orphanProxies = (new MeetingRepository())->listOrphanProxies($meetingId);
        foreach ($orphanProxies as $row) {
            $anomalies[] = [
                'id' => 'orphan_proxy_' . $row['id'],
                'type' => 'orphan_proxy',
                'severity' => 'warning',
                'title' => 'Procuration orpheline',
                'description' => sprintf('%s a donné procuration à %s qui n\'est pas présent', $row['giver_name'], $row['receiver_name']),
                'giver_name' => $row['giver_name'],
                'receiver_name' => $row['receiver_name'],
            ];
        }

        // 5. Résolutions non closes
        $unclosedMotions = (new MotionRepository())->listUnclosed($meetingId);
        foreach ($unclosedMotions as $row) {
            $anomalies[] = [
                'id' => 'unclosed_motion_' . $row['id'],
                'type' => 'unclosed_motion',
                'severity' => 'info',
                'title' => 'Résolution non close',
                'description' => sprintf('"%s" ouverte le %s mais pas encore fermée', $row['title'], date('d/m/Y H:i', strtotime($row['opened_at']))),
                'motion_id' => $row['id'],
                'motion_title' => $row['title'],
            ];
        }

        // 6. Votes manuels non justifiés
        $unjustifiedManualVotes = (new BallotRepository())->listUnjustifiedManualVotes($meetingId);
        foreach ($unjustifiedManualVotes as $row) {
            $anomalies[] = [
                'id' => 'unjustified_manual_' . $row['id'],
                'type' => 'unjustified_manual_vote',
                'severity' => 'warning',
                'title' => 'Vote manuel non justifié',
                'description' => sprintf('Vote manuel de %s sur "%s" sans justification', $row['full_name'], $row['motion_title']),
                'member_name' => $row['full_name'],
            ];
        }

        // Frontend-expected aliases
        foreach ($anomalies as &$a) {
            $a['message'] = $a['description'] ?? '';
            $parts = [];
            if (!empty($a['member_name'])) $parts[] = $a['member_name'];
            if (!empty($a['motion_title'])) $parts[] = $a['motion_title'];
            if (!empty($a['giver_name'])) $parts[] = $a['giver_name'] . ' → ' . ($a['receiver_name'] ?? '');
            $a['context'] = implode(' · ', $parts) ?: null;
        }
        unset($a);

        $summary = [
            'total' => count($anomalies),
            'danger' => count(array_filter($anomalies, fn($a) => $a['severity'] === 'danger')),
            'warning' => count(array_filter($anomalies, fn($a) => $a['severity'] === 'warning')),
            'info' => count(array_filter($anomalies, fn($a) => $a['severity'] === 'info')),
        ];

        api_ok([
            'meeting_id' => $meetingId,
            'summary' => $summary,
            'anomalies' => $anomalies,
        ]);
    }

    public function checks(): void
    {
        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }

        $tenantId = api_current_tenant_id();

        $meetingRepo = new MeetingRepository();
        $memberRepo  = new MemberRepository();
        $motionRepo  = new MotionRepository();
        $ballotRepo  = new BallotRepository();
        $policyRepo  = new PolicyRepository();

        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }

        $checks = [];

        // 1. Président renseigné
        $presidentOk = !empty($meeting['president_name']);
        $checks[] = [
            'id' => 'president_defined',
            'label' => 'Président renseigné',
            'passed' => $presidentOk,
            'detail' => $presidentOk ? 'Président: ' . $meeting['president_name'] : 'Aucun président défini pour cette séance',
        ];

        // 2. Au moins un membre présent
        $presentCount = $meetingRepo->countPresent($meetingId);
        $checks[] = [
            'id' => 'members_present',
            'label' => 'Membres présents',
            'passed' => $presentCount > 0,
            'detail' => $presentCount > 0 ? "{$presentCount} membre(s) présent(s)" : 'Aucun membre présent',
        ];

        // 3. Quorum atteint (global)
        $totalMembers = $memberRepo->countActive($tenantId);
        $quorumThreshold = 0.5;
        if ($meeting['quorum_policy_id']) {
            $policy = $policyRepo->findQuorumPolicy($meeting['quorum_policy_id']);
            if ($policy) {
                $quorumThreshold = (float)($policy['threshold'] ?? 0.5);
            }
        }
        $quorumRequired = (int)ceil($totalMembers * $quorumThreshold);
        $quorumMet = $presentCount >= $quorumRequired;
        $checks[] = [
            'id' => 'quorum_met',
            'label' => 'Quorum atteint',
            'passed' => $quorumMet,
            'detail' => sprintf('%d / %d présents (seuil: %d soit %.0f%%)', $presentCount, $totalMembers, $quorumRequired, $quorumThreshold * 100),
        ];

        // 4. Toutes les résolutions traitées
        $totalMotions = $meetingRepo->countMotions($meetingId);
        $closedMotions = $meetingRepo->countClosedMotions($meetingId);
        $openMotions = $meetingRepo->countOpenMotions($meetingId);
        $allMotionsClosed = $openMotions === 0;
        $checks[] = [
            'id' => 'all_motions_closed',
            'label' => 'Résolutions closes',
            'passed' => $allMotionsClosed,
            'detail' => $openMotions > 0 ? "{$openMotions} résolution(s) encore ouverte(s)" : "{$closedMotions} / {$totalMotions} résolution(s) traitée(s)",
        ];

        // 5. Au moins une résolution traitée
        $hasMotions = $closedMotions > 0;
        $checks[] = [
            'id' => 'has_closed_motions',
            'label' => 'Au moins une résolution',
            'passed' => $hasMotions,
            'detail' => $hasMotions ? "{$closedMotions} résolution(s) votée(s)" : 'Aucune résolution n\'a été votée',
        ];

        // 6. Procurations valides (pas de cycle)
        $proxyCycles = $meetingRepo->findProxyCycles($meetingId);
        $proxyOk = count($proxyCycles) === 0;
        $checks[] = [
            'id' => 'proxies_valid',
            'label' => 'Procurations valides',
            'passed' => $proxyOk,
            'detail' => $proxyOk ? 'Aucun cycle de procuration détecté' : count($proxyCycles) . ' cycle(s) de procuration détecté(s)',
        ];

        // 7. Totaux de vote cohérents
        $motionsWithoutVotes = $motionRepo->listClosedWithoutVotes($meetingId);
        $totalsOk = count($motionsWithoutVotes) === 0;
        $checks[] = [
            'id' => 'totals_consistent',
            'label' => 'Résolutions avec votes',
            'passed' => $totalsOk || $closedMotions === 0,
            'detail' => $closedMotions === 0
                ? 'Aucune résolution close à vérifier'
                : ($totalsOk ? 'Toutes les résolutions closes ont des bulletins' : count($motionsWithoutVotes) . ' résolution(s) close(s) sans vote'),
        ];

        // 8. Pas de votes après clôture
        $votesAfterClose = $ballotRepo->listVotesAfterClose($meetingId);
        $noVotesAfterClose = count($votesAfterClose) === 0;
        $checks[] = [
            'id' => 'no_votes_after_close',
            'label' => 'Pas de votes post-clôture',
            'passed' => $noVotesAfterClose,
            'detail' => $noVotesAfterClose ? 'Aucun vote enregistré après clôture' : count($votesAfterClose) . ' vote(s) après clôture détecté(s)',
        ];

        // 9. Politique de vote définie
        $votePolicyDefined = !empty($meeting['vote_policy_id']);
        $checks[] = [
            'id' => 'vote_policy_defined',
            'label' => 'Politique de vote',
            'passed' => $votePolicyDefined,
            'detail' => $votePolicyDefined ? 'Politique de vote définie' : 'Aucune politique de vote définie (défaut appliqué)',
        ];

        // 10. Politique de quorum définie
        $quorumPolicyDefined = !empty($meeting['quorum_policy_id']);
        $checks[] = [
            'id' => 'quorum_policy_defined',
            'label' => 'Politique de quorum',
            'passed' => $quorumPolicyDefined,
            'detail' => $quorumPolicyDefined ? 'Politique de quorum définie' : 'Aucune politique de quorum définie (défaut 50%)',
        ];

        $passedCount = count(array_filter($checks, fn($c) => $c['passed']));
        $failedCount = count($checks) - $passedCount;

        api_ok([
            'meeting_id' => $meetingId,
            'all_passed' => $failedCount === 0,
            'summary' => ['total' => count($checks), 'passed' => $passedCount, 'failed' => $failedCount],
            'checks' => $checks,
        ]);
    }
}
