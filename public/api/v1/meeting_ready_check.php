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

$tenant = defined('api_current_tenant_id()') ? api_current_tenant_id() : null;

$meetingRepo    = new MeetingRepository();
$motionRepo     = new MotionRepository();
$attendanceRepo = new AttendanceRepository();
$memberRepo     = new MemberRepository();
$ballotRepo     = new BallotRepository();

// Meeting
$meeting = $meetingRepo->findById($meetingId);
if (!$meeting) api_fail('meeting_not_found', 404);

if ($tenant !== null && (string)$meeting['tenant_id'] !== (string)$tenant) {
    // Best-effort tenant isolation
    api_fail('meeting_not_found', 404);
}

$reasons = [];
$bad = [];

$pres = trim((string)($meeting['president_name'] ?? ''));
if ($pres === '') {
    $reasons[] = "Aucun président (president_name) n'est renseigné.";
}

// Motions ouvertes
$openCount = $meetingRepo->countOpenMotions($meetingId);

if ($openCount > 0) {
    $reasons[] = "Il reste {$openCount} motion(s) ouverte(s). Fermez-les avant validation.";
}

// Éligibles (règle CDC): attendances present/remote/proxy ; fallback si aucune présence saisie
$eligibleCount = $attendanceRepo->countEligible($meetingId);

$fallbackEligibleUsed = false;
if ($eligibleCount <= 0) {
    $fallbackEligibleUsed = true;
    $eligibleCount = $memberRepo->countActive($tenant);
    $reasons[] = "Présences non saisies : règle de fallback utilisée (tous membres actifs).";
}

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
    // Direct ballots: voter must be present/remote at meeting.
    $eligibleDirect = $ballotRepo->countEligibleDirect($meetingId, $motionId);

    // Proxy ballots: proxy_source_member_id must be present/remote AND an active proxy must exist.
    $eligibleProxy = $ballotRepo->countEligibleProxy($meetingId, $motionId);

    $eligibleBallots = $eligibleDirect + $eligibleProxy;

// Missing ballots (strict readiness): expect 1 ballot per éligible (source tablette ou manuel)
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

if (count($bad) > 0) {
    $reasons[] = "Certaines motions fermées présentent des anomalies ou n'ont pas de résultat exploitable.";
}

api_ok([
    'can' => count($reasons) === 0,
    'reasons' => $reasons,
    'bad_motions' => $bad,
    'meta' => [
        'meeting_id' => $meetingId,
        'eligible_count' => $eligibleCount ?? null,
        'fallback_eligible_used' => $fallbackEligibleUsed ?? false,
    ],
]);
