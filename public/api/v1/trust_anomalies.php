<?php
declare(strict_types=1);

/**
 * trust_anomalies.php - Détection des anomalies de séance
 * 
 * GET /api/v1/trust_anomalies.php?meeting_id={uuid}
 * 
 * Retourne la liste des anomalies détectées :
 * - Votes sans présence enregistrée
 * - Doubles votes potentiels
 * - Incohérences de pondération
 * - Procurations orphelines
 * - Résolutions non closes
 */

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\MotionRepository;

api_require_role(['auditor', 'admin', 'operator']);

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '' || !api_is_uuid($meetingId)) {
    api_fail('missing_meeting_id', 400);
}

$tenantId = api_current_tenant_id();

// Vérifier que la séance existe
$meeting = (new MeetingRepository())->findByIdForTenant($meetingId, $tenantId);
if (!$meeting) {
    api_fail('meeting_not_found', 404);
}

$anomalies = [];

// ============================================================================
// 1. Votes sans présence enregistrée
// ============================================================================
$votesWithoutAttendance = (new BallotRepository())->listVotesWithoutAttendance($meetingId, $tenantId);

foreach ($votesWithoutAttendance as $row) {
    $anomalies[] = [
        'id' => 'vote_no_attendance_' . $row['member_id'],
        'type' => 'vote_without_attendance',
        'severity' => 'warning',
        'title' => 'Vote sans présence',
        'description' => sprintf(
            '%s a voté sur "%s" sans être marqué présent',
            $row['full_name'],
            $row['motion_title']
        ),
        'member_id' => $row['member_id'],
        'member_name' => $row['full_name'],
    ];
}

// ============================================================================
// 2. Doubles votes potentiels (même membre, même motion)
// ============================================================================
$duplicateVotes = (new BallotRepository())->listDuplicateVotes($meetingId);

foreach ($duplicateVotes as $row) {
    $anomalies[] = [
        'id' => 'duplicate_vote_' . $row['member_id'],
        'type' => 'duplicate_vote',
        'severity' => 'danger',
        'title' => 'Votes multiples détectés',
        'description' => sprintf(
            '%s a %d votes sur "%s"',
            $row['full_name'],
            $row['vote_count'],
            $row['motion_title']
        ),
        'member_id' => $row['member_id'],
        'member_name' => $row['full_name'],
        'vote_count' => (int)$row['vote_count'],
    ];
}

// ============================================================================
// 3. Incohérences de pondération
// ============================================================================
$weightMismatches = (new BallotRepository())->listWeightMismatches($meetingId);

foreach ($weightMismatches as $row) {
    $anomalies[] = [
        'id' => 'weight_mismatch_' . $row['member_id'],
        'type' => 'weight_mismatch',
        'severity' => 'warning',
        'title' => 'Pondération incohérente',
        'description' => sprintf(
            '%s: poids voté %.2f ≠ poids membre %.2f sur "%s"',
            $row['full_name'],
            $row['actual_weight'],
            $row['expected_weight'],
            $row['motion_title']
        ),
        'member_id' => $row['member_id'],
        'member_name' => $row['full_name'],
        'expected_weight' => (float)$row['expected_weight'],
        'actual_weight' => (float)$row['actual_weight'],
    ];
}

// ============================================================================
// 4. Procurations orphelines (donneur absent sans proxy valide)
// ============================================================================
$orphanProxies = (new MeetingRepository())->listOrphanProxies($meetingId);

foreach ($orphanProxies as $row) {
    $anomalies[] = [
        'id' => 'orphan_proxy_' . $row['id'],
        'type' => 'orphan_proxy',
        'severity' => 'warning',
        'title' => 'Procuration orpheline',
        'description' => sprintf(
            '%s a donné procuration à %s qui n\'est pas présent',
            $row['giver_name'],
            $row['receiver_name']
        ),
        'giver_name' => $row['giver_name'],
        'receiver_name' => $row['receiver_name'],
    ];
}

// ============================================================================
// 5. Résolutions non closes (ouverte mais pas fermée)
// ============================================================================
$unclosedMotions = (new MotionRepository())->listUnclosed($meetingId);

foreach ($unclosedMotions as $row) {
    $anomalies[] = [
        'id' => 'unclosed_motion_' . $row['id'],
        'type' => 'unclosed_motion',
        'severity' => 'info',
        'title' => 'Résolution non close',
        'description' => sprintf(
            '"%s" ouverte le %s mais pas encore fermée',
            $row['title'],
            date('d/m/Y H:i', strtotime($row['opened_at']))
        ),
        'motion_id' => $row['id'],
        'motion_title' => $row['title'],
    ];
}

// ============================================================================
// 6. Votes manuels non justifiés
// ============================================================================
$unjustifiedManualVotes = (new BallotRepository())->listUnjustifiedManualVotes($meetingId);

foreach ($unjustifiedManualVotes as $row) {
    $anomalies[] = [
        'id' => 'unjustified_manual_' . $row['id'],
        'type' => 'unjustified_manual_vote',
        'severity' => 'warning',
        'title' => 'Vote manuel non justifié',
        'description' => sprintf(
            'Vote manuel de %s sur "%s" sans justification',
            $row['full_name'],
            $row['motion_title']
        ),
        'member_name' => $row['full_name'],
    ];
}

// ============================================================================
// Résumé
// ============================================================================
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
