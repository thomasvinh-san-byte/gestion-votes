<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\BallotRepository;

/**
 * Ready-check aligné avec les règles d'éligibilité de vote (présences + procurations)
 * et avec les prérequis de validation:
 * - président renseigné
 * - aucune motion ouverte
 * - chaque motion fermée a un résultat exploitable (manuel cohérent OU e-vote éligible)
 * - absence de bulletins "non éligibles" (direct ou proxy) détectables
 */

api_require_role('auditor');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '') api_fail('missing_meeting_id', 400);

$tenant = api_current_tenant_id();

$meetingRepo    = new MeetingRepository();
$motionRepo     = new MotionRepository();
$attendanceRepo = new AttendanceRepository();
$memberRepo     = new MemberRepository();
$ballotRepo     = new BallotRepository();

// Meeting - use tenant-isolated query for security
$meeting = $meetingRepo->findByIdForTenant($meetingId, $tenant);
if (!$meeting) api_fail('meeting_not_found', 404);

$checks = [];
$bad = [];

// Check 1: Président renseigné
$pres = trim((string)($meeting['president_name'] ?? ''));
$checks[] = [
    'passed' => $pres !== '',
    'label' => 'Président renseigné',
    'detail' => $pres !== '' ? $pres : "Aucun président (president_name) n'est renseigné.",
];

// Check 2: Motions ouvertes
$openCount = $meetingRepo->countOpenMotions($meetingId);
$checks[] = [
    'passed' => $openCount === 0,
    'label' => 'Motions fermées',
    'detail' => $openCount > 0 ? "Il reste {$openCount} motion(s) ouverte(s). Fermez-les avant validation." : '',
];

// Check 3: Éligibles (règle CDC): attendances present/remote/proxy ; fallback si aucune présence saisie
$eligibleCount = $attendanceRepo->countEligible($meetingId);

$fallbackEligibleUsed = false;
if ($eligibleCount <= 0) {
    $fallbackEligibleUsed = true;
    $eligibleCount = $memberRepo->countActive($tenant);
}
$checks[] = [
    'passed' => !$fallbackEligibleUsed,
    'label' => 'Présences saisies',
    'detail' => $fallbackEligibleUsed ? "Règle de fallback utilisée (tous membres actifs)." : '',
];

// Motions fermées
$motions = $motionRepo->listClosedForMeetingWithManualTally($meetingId);

foreach ($motions as $m) {
    $motionId = (string)$m['id'];
    $title = (string)($m['title'] ?? 'Motion');

    $manualTotal = (int)($m['manual_total'] ?? 0);
    $manualFor   = (int)($m['manual_for'] ?? 0);
    $manualAg    = (int)($m['manual_against'] ?? 0);
    $manualAb    = (int)($m['manual_abstain'] ?? 0);

    $manualOk = false;
    if ($manualTotal > 0) {
        $manualOk = (($manualFor + $manualAg + $manualAb) === $manualTotal);
    }

    // --- E-vote "éligible" ---
    $eligibleDirect = $ballotRepo->countEligibleDirect($meetingId, $motionId);
    $eligibleProxy = $ballotRepo->countEligibleProxy($meetingId, $motionId);
    $eligibleBallots = $eligibleDirect + $eligibleProxy;

    // Missing ballots (strict readiness)
    $ballotsTotal = $ballotRepo->countByMotionId($motionId);
    $missing = max(0, $eligibleCount - $ballotsTotal);
    if ($missing > 0) {
        $bad[] = [
            'motion_id' => $motionId,
            'title' => $title,
            'detail' => "Votes manquants : {$missing} (attendus: {$eligibleCount}, reçus: {$ballotsTotal})."
        ];
    }

    // Invalid ballots detection (best-effort)
    $invalidDirect = $ballotRepo->countInvalidDirect($meetingId, $motionId);
    $invalidProxy = $ballotRepo->countInvalidProxy($meetingId, $motionId);

    if ($invalidDirect > 0 || $invalidProxy > 0) {
        $bad[] = [
            'motion_id' => $motionId,
            'title' => $title,
            'detail' => "Bulletins non éligibles détectés (direct: {$invalidDirect}, procuration: {$invalidProxy})."
        ];
    }

    // Motion must have usable result: manualOk OR eligible ballots exist
    if (!$manualOk && $eligibleBallots <= 0) {
        $bad[] = [
            'motion_id' => $motionId,
            'title' => $title,
            'detail' => "Aucun résultat exploitable: pas de comptage manuel cohérent et aucun bulletin e-vote éligible."
        ];
    } elseif ($manualTotal > 0 && !$manualOk) {
        $bad[] = [
            'motion_id' => $motionId,
            'title' => $title,
            'detail' => "Comptage manuel incohérent (pour+contre+abst != total)."
        ];
    }
}

// Convert bad motions into individual checks
foreach ($bad as $b) {
    $checks[] = ['passed' => false, 'label' => $b['title'], 'detail' => $b['detail']];
}

// If motions exist and none are bad, add a passing check
if (count($bad) === 0 && count($motions) > 0) {
    $checks[] = ['passed' => true, 'label' => 'Résultats exploitables', 'detail' => count($motions) . ' motion(s) avec résultat valide.'];
}

$ready = true;
foreach ($checks as $c) {
    if (!$c['passed']) { $ready = false; break; }
}

api_ok([
    'ready' => $ready,
    'checks' => $checks,
    'can' => $ready,
    'bad_motions' => $bad,
    'meta' => [
        'meeting_id' => $meetingId,
        'eligible_count' => $eligibleCount ?? null,
        'fallback_eligible_used' => $fallbackEligibleUsed ?? false,
    ],
]);
