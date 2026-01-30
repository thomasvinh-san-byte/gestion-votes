<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

/**
 * Ready-check aligné avec les règles d'éligibilité de vote (présences + procurations)
 * et avec les prérequis de validation:
 * - président renseigné
 * - aucune motion ouverte
 * - chaque motion fermée a un résultat exploitable (manuel cohérent OU e-vote éligible)
 * - absence de bulletins "non éligibles" (direct ou proxy) détectables
 */

require_role('trust');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '') json_err('missing_meeting_id', 400);

$tenant = defined('DEFAULT_TENANT_ID') ? DEFAULT_TENANT_ID : null;

// Meeting
$meeting = db_select_one(
    "SELECT id, tenant_id, title, status, president_name
     FROM meetings
     WHERE id = ?",
    [$meetingId]
);
if (!$meeting) json_err('meeting_not_found', 404);

if ($tenant !== null && (string)$meeting['tenant_id'] !== (string)$tenant) {
    // Best-effort tenant isolation
    json_err('meeting_not_found', 404);
}

$reasons = [];
$bad = [];

$pres = trim((string)($meeting['president_name'] ?? ''));
if ($pres === '') {
    $reasons[] = "Aucun président (president_name) n’est renseigné.";
}

// Motions ouvertes
$openCount = (int)(db_scalar(
    "SELECT count(*)
     FROM motions
     WHERE meeting_id = ? AND opened_at IS NOT NULL AND closed_at IS NULL",
    [$meetingId]
) ?? 0);

if ($openCount > 0) {
    $reasons[] = "Il reste {$openCount} motion(s) ouverte(s). Fermez-les avant validation.";
}

// Éligibles (règle CDC): attendances present/remote/proxy ; fallback si aucune présence saisie
$eligibleCount = (int)(db_scalar(
    "SELECT count(*) FROM attendances WHERE meeting_id = ? AND mode IN ('present','remote','proxy')",
    [$meetingId]
) ?? 0);

$fallbackEligibleUsed = false;
if ($eligibleCount <= 0) {
    $fallbackEligibleUsed = true;
    $eligibleCount = (int)(db_scalar(
        "SELECT count(*) FROM members WHERE tenant_id = ? AND is_active = true",
        [$tenant]
    ) ?? 0);
    $reasons[] = "Présences non saisies : règle de fallback utilisée (tous membres actifs).";
}

// Motions fermées
$motions = db_select_all(
    "SELECT id, title, manual_total, manual_for, manual_against, manual_abstain, opened_at, closed_at
     FROM motions
     WHERE meeting_id = ? AND closed_at IS NOT NULL
     ORDER BY closed_at ASC NULLS LAST",
    [$meetingId]
);

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
    $eligibleDirect = (int)(db_scalar(
        "SELECT count(*)
         FROM ballots b
         JOIN members mem ON mem.id = b.member_id
         JOIN attendances a ON a.meeting_id = ? AND a.member_id = b.member_id
         WHERE b.motion_id = ?
           AND COALESCE(b.is_proxy_vote, false) = false
           AND mem.is_active = true
           AND a.checked_out_at IS NULL
           AND a.mode IN ('present','remote')",
        [$meetingId, $motionId]
    ) ?? 0);

    // Proxy ballots: proxy_source_member_id must be present/remote AND an active proxy must exist.
    $eligibleProxy = (int)(db_scalar(
        "SELECT count(*)
         FROM ballots b
         JOIN members mandant ON mandant.id = b.member_id
         JOIN attendances a ON a.meeting_id = ? AND a.member_id = b.proxy_source_member_id
         JOIN proxies p ON p.meeting_id = ?
                      AND p.giver_member_id = b.member_id
                      AND p.receiver_member_id = b.proxy_source_member_id
                      AND p.revoked_at IS NULL
         WHERE b.motion_id = ?
           AND COALESCE(b.is_proxy_vote, false) = true
           AND b.proxy_source_member_id IS NOT NULL
           AND mandant.is_active = true
           AND a.checked_out_at IS NULL
           AND a.mode IN ('present','remote')",
        [$meetingId, $meetingId, $motionId]
    ) ?? 0);

    $eligibleBallots = $eligibleDirect + $eligibleProxy;

// Missing ballots (strict readiness): expect 1 ballot per éligible (source tablette ou manuel)
$ballotsTotal = (int)(db_scalar(
    "SELECT count(*) FROM ballots WHERE motion_id = ?",
    [$motionId]
) ?? 0);

$missing = max(0, $eligibleCount - $ballotsTotal);
if ($missing > 0) {
    $bad[] = [
        'motion_id' => $motionId,
        'title' => $title,
        'detail' => "Votes manquants : {$missing} (attendus: {$eligibleCount}, reçus: {$ballotsTotal})."
    ];
}

    // Invalid ballots detection (best-effort)
    $invalidDirect = (int)(db_scalar(
        "SELECT count(*)
         FROM ballots b
         JOIN members mem ON mem.id = b.member_id
         LEFT JOIN attendances a ON a.meeting_id = ? AND a.member_id = b.member_id
         WHERE b.motion_id = ?
           AND COALESCE(b.is_proxy_vote, false) = false
           AND mem.is_active = true
           AND (a.member_id IS NULL OR a.checked_out_at IS NOT NULL OR a.mode NOT IN ('present','remote'))",
        [$meetingId, $motionId]
    ) ?? 0);

    $invalidProxy = (int)(db_scalar(
        "SELECT count(*)
         FROM ballots b
         JOIN members mandant ON mandant.id = b.member_id
         LEFT JOIN attendances a ON a.meeting_id = ? AND a.member_id = b.proxy_source_member_id
         LEFT JOIN proxies p ON p.meeting_id = ?
                            AND p.giver_member_id = b.member_id
                            AND p.receiver_member_id = b.proxy_source_member_id
                            AND p.revoked_at IS NULL
         WHERE b.motion_id = ?
           AND COALESCE(b.is_proxy_vote, false) = true
           AND mandant.is_active = true
           AND (
                b.proxy_source_member_id IS NULL
                OR a.member_id IS NULL
                OR a.checked_out_at IS NOT NULL
                OR a.mode NOT IN ('present','remote')
                OR p.id IS NULL
           )",
        [$meetingId, $meetingId, $motionId]
    ) ?? 0);

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
    $reasons[] = "Certaines motions fermées présentent des anomalies ou n’ont pas de résultat exploitable.";
}

json_ok([
    'can' => count($reasons) === 0,
    'reasons' => $reasons,
    'bad_motions' => $bad,
    'meta' => [
        'meeting_id' => $meetingId,
        'eligible_count' => $eligibleCount ?? null,
        'fallback_eligible_used' => $fallbackEligibleUsed ?? false,
    ],
]);