<?php
// public/api/v1/meeting_stats.php
require __DIR__ . '/../../../app/api.php';

api_require_role('public');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_fail('method_not_allowed', 405);
}

$meetingId = trim($_GET['meeting_id'] ?? '');
if ($meetingId === '') {
    api_fail('missing_meeting_id', 422);
}

// Vérifier que la séance existe pour ce tenant
$exists = db_scalar("
    SELECT 1
    FROM meetings
    WHERE id = ?
      AND tenant_id = ?
", [$meetingId, api_current_tenant_id()]);

if (!$exists) {
    api_fail('meeting_not_found', 404);
}

global $pdo;

//
// 1) Compter le nombre de motions de la séance
//
$motionsCount = db_scalar("
    SELECT COUNT(*)
    FROM motions
    WHERE meeting_id = ?
", [$meetingId]) ?? 0;

//
// 2) Récupérer les stats par motion (bulletins + manual_*)
//
$stmt = $pdo->prepare("
    SELECT
        mo.id   AS motion_id,
        mo.title,

        -- Stats depuis les bulletins
        COUNT(b.id)                                        AS ballots_total,
        COUNT(b.id) FILTER (WHERE b.value = 'for')         AS ballots_for,
        COUNT(b.id) FILTER (WHERE b.value = 'against')     AS ballots_against,
        COUNT(b.id) FILTER (WHERE b.value = 'abstain')     AS ballots_abstain,
        COUNT(b.id) FILTER (WHERE b.value = 'nsp')         AS ballots_nsp,

        -- Comptage manuel
        mo.manual_total,
        mo.manual_for,
        mo.manual_against,
        mo.manual_abstain

    FROM motions mo
    LEFT JOIN ballots b ON b.motion_id = mo.id
    WHERE mo.meeting_id = ?
    GROUP BY
        mo.id,
        mo.title,
        mo.manual_total,
        mo.manual_for,
        mo.manual_against,
        mo.manual_abstain
    ORDER BY mo.title
");
$stmt->execute([$meetingId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

//
// 3) Construire le tableau final des motions en choisissant la source
//
$motions = [];
$totalBallotsAllMotions = 0;

foreach ($rows as $r) {
    $ballotsTotal = (int)($r['ballots_total'] ?? 0);

    if ($ballotsTotal > 0) {
        // On utilise les bulletins individuels
        $source       = 'ballots';
        $total        = $ballotsTotal;
        $votes_for    = (int)($r['ballots_for']     ?? 0);
        $votes_against= (int)($r['ballots_against'] ?? 0);
        $votes_abstain= (int)($r['ballots_abstain'] ?? 0);
        $votes_nsp    = (int)($r['ballots_nsp']     ?? 0);
        $totalBallotsAllMotions += $ballotsTotal;

    } else {
        // Fallback sur le comptage manuel
        $source       = 'manual';
        $total        = (int)($r['manual_total']    ?? 0);
        $votes_for    = (int)($r['manual_for']      ?? 0);
        $votes_against= (int)($r['manual_against']  ?? 0);
        $votes_abstain= (int)($r['manual_abstain']  ?? 0);

        // NSP manuel = ce qui manque, si tu veux l’exposer
        $votes_nsp = max(0, $total - $votes_for - $votes_against - $votes_abstain);
    }

    $motions[] = [
        'motion_id'      => $r['motion_id'],
        'title'          => $r['title'],
        'total'          => $total,
        'votes_for'      => $votes_for,
        'votes_against'  => $votes_against,
        'votes_abstain'  => $votes_abstain,
        'votes_nsp'      => $votes_nsp,
        'tally_source'   => $source,          // 'ballots' ou 'manual'
        'manual_total'   => (int)($r['manual_total']   ?? 0),
        'manual_for'     => (int)($r['manual_for']     ?? 0),
        'manual_against' => (int)($r['manual_against'] ?? 0),
        'manual_abstain' => (int)($r['manual_abstain'] ?? 0),
        'ballots_total'  => $ballotsTotal,
    ];
}

//
// 4) Nombre de votants distincts pour la séance
//    - si des bulletins existent : COUNT(DISTINCT member_id)
//    - sinon : fallback = MAX(manual_total) sur les motions
//
$distinctVoters = 0;

if ($totalBallotsAllMotions > 0) {
    $distinctVoters = db_scalar("
        SELECT COUNT(DISTINCT b.member_id)
        FROM ballots b
        JOIN motions mo ON mo.id = b.motion_id
        WHERE mo.meeting_id = ?
    ", [$meetingId]) ?? 0;
} else {
    // Pas de bulletins : on prend le max des manual_total comme approximation
    $distinctVoters = db_scalar("
        SELECT MAX(manual_total)
        FROM motions
        WHERE meeting_id = ?
    ", [$meetingId]) ?? 0;
}

api_ok([
    'meeting_id'      => $meetingId,
    'motions_count'   => (int)$motionsCount,
    'distinct_voters' => (int)$distinctVoters,
    'motions'         => $motions,
]);
