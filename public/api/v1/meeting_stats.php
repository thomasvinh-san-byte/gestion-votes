<?php
// public/api/v1/meeting_stats.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;

api_require_role('public');

api_request('GET');

$meetingId = trim($_GET['meeting_id'] ?? '');
if ($meetingId === '') {
    api_fail('missing_meeting_id', 422);
}

$meetingRepo = new MeetingRepository();
$motionRepo = new MotionRepository();

if (!$meetingRepo->existsForTenant($meetingId, api_current_tenant_id())) {
    api_fail('meeting_not_found', 404);
}

//
// 1) Compter le nombre de motions de la séance
//
$motionsCount = $meetingRepo->countMotions($meetingId);

//
// 2) Récupérer les stats par motion (bulletins + manual_*)
//
$rows = $motionRepo->listStatsForMeeting($meetingId);

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

        // NSP manuel = ce qui manque
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
        'tally_source'   => $source,
        'manual_total'   => (int)($r['manual_total']   ?? 0),
        'manual_for'     => (int)($r['manual_for']     ?? 0),
        'manual_against' => (int)($r['manual_against'] ?? 0),
        'manual_abstain' => (int)($r['manual_abstain'] ?? 0),
        'ballots_total'  => $ballotsTotal,
    ];
}

//
// 4) Nombre de votants distincts pour la séance
//
$distinctVoters = 0;

if ($totalBallotsAllMotions > 0) {
    $distinctVoters = $motionRepo->countDistinctVoters($meetingId);
} else {
    $distinctVoters = $motionRepo->maxManualTotal($meetingId);
}

api_ok([
    'meeting_id'      => $meetingId,
    'motions_count'   => (int)$motionsCount,
    'distinct_voters' => (int)$distinctVoters,
    'motions'         => $motions,
]);
