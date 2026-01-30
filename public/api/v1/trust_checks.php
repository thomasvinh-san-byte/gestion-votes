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

api_require_role(['auditor', 'admin', 'operator']);

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 400);
}

$tenantId = api_current_tenant_id();

// Vérifier que la séance existe
$meeting = db_one("
    SELECT id, title, status, president_name, quorum_policy_id, vote_policy_id
    FROM meetings 
    WHERE tenant_id = ? AND id = ?
", [$tenantId, $meetingId]);

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
$presentCount = (int)db_scalar("
    SELECT COUNT(*) FROM attendances
    WHERE meeting_id = ? AND mode::text IN ('present', 'remote')
", [$meetingId]);

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
$totalMembers = (int)db_scalar("
    SELECT COUNT(*) FROM members WHERE tenant_id = ? AND is_active = true
", [$tenantId]);

$quorumThreshold = 0.5; // Par défaut 50%
if ($meeting['quorum_policy_id']) {
    $policy = db_one("SELECT threshold FROM quorum_policies WHERE id = ?", [$meeting['quorum_policy_id']]);
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
$totalMotions = (int)db_scalar("SELECT COUNT(*) FROM motions WHERE meeting_id = ?", [$meetingId]);
$closedMotions = (int)db_scalar("SELECT COUNT(*) FROM motions WHERE meeting_id = ? AND closed_at IS NOT NULL", [$meetingId]);
$openMotions = (int)db_scalar("SELECT COUNT(*) FROM motions WHERE meeting_id = ? AND opened_at IS NOT NULL AND closed_at IS NULL", [$meetingId]);

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
$proxyCycles = db_all("
    SELECT p1.giver_member_id, p1.receiver_member_id
    FROM proxies p1
    JOIN proxies p2 ON p1.receiver_member_id = p2.giver_member_id AND p1.giver_member_id = p2.receiver_member_id
    WHERE p1.meeting_id = ?
", [$meetingId]);

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
$motionsWithoutVotes = db_all("
    SELECT m.id, m.title
    FROM motions m
    LEFT JOIN ballots b ON b.motion_id = m.id
    WHERE m.meeting_id = ? AND m.closed_at IS NOT NULL
    GROUP BY m.id, m.title
    HAVING COUNT(b.id) = 0
", [$meetingId]);

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
$votesAfterClose = db_all("
    SELECT b.id, m.title
    FROM ballots b
    JOIN motions m ON m.id = b.motion_id
    WHERE m.meeting_id = ?
      AND m.closed_at IS NOT NULL
      AND b.created_at > m.closed_at
", [$meetingId]);

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
