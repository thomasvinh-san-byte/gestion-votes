<?php
declare(strict_types=1);

/**
 * trust_checks.php - Contrôles de cohérence de séance
 *
 * GET /api/v1/trust_checks.php?meeting_id={uuid}
 *
 * Retourne la liste des contrôles de cohérence :
 * - Quorum atteint
 * - Tous les votes clôturés
 * - Totaux cohérents
 * - Président renseigné
 * - Procurations valides
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\PolicyRepository;

api_require_role(['auditor', 'admin', 'operator']);

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 400);
}

$tenantId = api_current_tenant_id();

$meetingRepo = new MeetingRepository();
$memberRepo  = new MemberRepository();
$motionRepo  = new MotionRepository();
$ballotRepo  = new BallotRepository();
$policyRepo  = new PolicyRepository();

// Vérifier que la séance existe
$meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);

if (!$meeting) {
    api_fail('meeting_not_found', 404);
}

$checks = [];

// ============================================================================
// 1. Président renseigné
// ============================================================================
$presidentOk = !empty($meeting['president_name']);
$checks[] = [
    'id' => 'president_defined',
    'label' => 'Président renseigné',
    'passed' => $presidentOk,
    'detail' => $presidentOk
        ? 'Président: ' . $meeting['president_name']
        : 'Aucun président défini pour cette séance',
];

// ============================================================================
// 2. Au moins un membre présent
// ============================================================================
$presentCount = $meetingRepo->countPresent($meetingId);

$checks[] = [
    'id' => 'members_present',
    'label' => 'Membres présents',
    'passed' => $presentCount > 0,
    'detail' => $presentCount > 0
        ? "{$presentCount} membre(s) présent(s)"
        : 'Aucun membre présent',
];

// ============================================================================
// 3. Quorum atteint (global)
// ============================================================================
$totalMembers = $memberRepo->countActive($tenantId);

$quorumThreshold = 0.5; // Par défaut 50%
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
    'detail' => sprintf(
        '%d / %d présents (seuil: %d soit %.0f%%)',
        $presentCount,
        $totalMembers,
        $quorumRequired,
        $quorumThreshold * 100
    ),
];

// ============================================================================
// 4. Toutes les résolutions ont été traitées
// ============================================================================
$totalMotions = $meetingRepo->countMotions($meetingId);
$closedMotions = $meetingRepo->countClosedMotions($meetingId);
$openMotions = $meetingRepo->countOpenMotions($meetingId);

$allMotionsClosed = $openMotions === 0;
$checks[] = [
    'id' => 'all_motions_closed',
    'label' => 'Résolutions closes',
    'passed' => $allMotionsClosed,
    'detail' => $openMotions > 0
        ? "{$openMotions} résolution(s) encore ouverte(s)"
        : "{$closedMotions} / {$totalMotions} résolution(s) traitée(s)",
];

// ============================================================================
// 5. Au moins une résolution traitée
// ============================================================================
$hasMotions = $closedMotions > 0;
$checks[] = [
    'id' => 'has_closed_motions',
    'label' => 'Au moins une résolution',
    'passed' => $hasMotions,
    'detail' => $hasMotions
        ? "{$closedMotions} résolution(s) votée(s)"
        : 'Aucune résolution n\'a été votée',
];

// ============================================================================
// 6. Procurations valides (pas de cycle)
// ============================================================================
$proxyCycles = $meetingRepo->findProxyCycles($meetingId);

$proxyOk = count($proxyCycles) === 0;
$checks[] = [
    'id' => 'proxies_valid',
    'label' => 'Procurations valides',
    'passed' => $proxyOk,
    'detail' => $proxyOk
        ? 'Aucun cycle de procuration détecté'
        : count($proxyCycles) . ' cycle(s) de procuration détecté(s)',
];

// ============================================================================
// 7. Totaux de vote cohérents (pour chaque motion close)
// ============================================================================
// Vérifier que chaque motion close a au moins un bulletin
$motionsWithoutVotes = $motionRepo->listClosedWithoutVotes($meetingId);

$totalsOk = count($motionsWithoutVotes) === 0;
$checks[] = [
    'id' => 'totals_consistent',
    'label' => 'Résolutions avec votes',
    'passed' => $totalsOk || $closedMotions === 0,
    'detail' => $closedMotions === 0
        ? 'Aucune résolution close à vérifier'
        : ($totalsOk
            ? 'Toutes les résolutions closes ont des bulletins'
            : count($motionsWithoutVotes) . ' résolution(s) close(s) sans vote'),
];

// ============================================================================
// 8. Pas de votes après clôture
// ============================================================================
$votesAfterClose = $ballotRepo->listVotesAfterClose($meetingId);

$noVotesAfterClose = count($votesAfterClose) === 0;
$checks[] = [
    'id' => 'no_votes_after_close',
    'label' => 'Pas de votes post-clôture',
    'passed' => $noVotesAfterClose,
    'detail' => $noVotesAfterClose
        ? 'Aucun vote enregistré après clôture'
        : count($votesAfterClose) . ' vote(s) après clôture détecté(s)',
];

// ============================================================================
// 9. Politique de vote définie
// ============================================================================
$votePolicyDefined = !empty($meeting['vote_policy_id']);
$checks[] = [
    'id' => 'vote_policy_defined',
    'label' => 'Politique de vote',
    'passed' => $votePolicyDefined,
    'detail' => $votePolicyDefined
        ? 'Politique de vote définie'
        : 'Aucune politique de vote définie (défaut appliqué)',
];

// ============================================================================
// 10. Politique de quorum définie
// ============================================================================
$quorumPolicyDefined = !empty($meeting['quorum_policy_id']);
$checks[] = [
    'id' => 'quorum_policy_defined',
    'label' => 'Politique de quorum',
    'passed' => $quorumPolicyDefined,
    'detail' => $quorumPolicyDefined
        ? 'Politique de quorum définie'
        : 'Aucune politique de quorum définie (défaut 50%)',
];

// ============================================================================
// Résumé
// ============================================================================
$passedCount = count(array_filter($checks, fn($c) => $c['passed']));
$failedCount = count($checks) - $passedCount;
$allPassed = $failedCount === 0;

api_ok([
    'meeting_id' => $meetingId,
    'all_passed' => $allPassed,
    'summary' => [
        'total' => count($checks),
        'passed' => $passedCount,
        'failed' => $failedCount,
    ],
    'checks' => $checks,
]);
