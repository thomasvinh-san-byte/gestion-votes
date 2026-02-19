<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\BallotRepository;

api_require_role('operator');

try {

$meetingId = isset($_GET['meeting_id']) ? (string)$_GET['meeting_id'] : '';
$tenantId  = (string)($GLOBALS['APP_TENANT_ID'] ?? api_current_tenant_id());

$meetingRepo = new MeetingRepository();
$memberRepo  = new MemberRepository();
$attRepo     = new AttendanceRepository();
$motionRepo  = new MotionRepository();
$ballotRepo  = new BallotRepository();

// Liste des séances (pour select UI)
$meetings = $meetingRepo->listForDashboard($tenantId);

// Séance suggérée: live si existe sinon la première de la liste
$suggested = null;
foreach ($meetings as $m) {
  if (($m['status'] ?? '') === 'live') { $suggested = $m['id']; break; }
}
if ($suggested === null && count($meetings) > 0) $suggested = $meetings[0]['id'];

if ($meetingId === '') $meetingId = (string)($suggested ?? '');

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
  // meeting
  $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
  if (!$meeting) api_fail('meeting_not_found', 404);
  $data['meeting'] = $meeting;

  // attendance summary
  $eligibleCount  = $memberRepo->countNotDeleted($tenantId);
  $eligibleWeight = $memberRepo->sumNotDeletedVoteWeight($tenantId);

  $att = $attRepo->dashboardSummary($tenantId, $meetingId);

  $data['attendance'] = [
    'eligible_count' => $eligibleCount,
    'eligible_weight' => $eligibleWeight,
    'present_count' => (int)$att['present_count'],
    'present_weight' => (int)$att['present_weight'],
  ];

  // proxies count
  $data['proxies'] = ['count' => $meetingRepo->countActiveProxies($tenantId, $meetingId)];

  // current motion
  $currentMotionId = (string)($meeting['current_motion_id'] ?? '');
  if ($currentMotionId === '') {
    $openMotion = $motionRepo->findCurrentOpen($meetingId, $tenantId);
    if ($openMotion) $currentMotionId = (string)$openMotion['id'];
  }

  if ($currentMotionId !== '') {
    $motionData = $motionRepo->findByIdForTenant($currentMotionId, $tenantId);
    $data['current_motion'] = $motionData ?: null;

    // votes (poids) sur la motion en cours
    $data['current_motion_votes'] = $ballotRepo->dashboardTally($tenantId, $meetingId, $currentMotionId);
  }

  // openable motions
  $data['openable_motions'] = $motionRepo->listOpenable($tenantId, $meetingId);

  // ready to sign
  $reasons = [];

  // président
  $pres = trim((string)($meeting['president_name'] ?? ''));
  if ($pres === '') $reasons[] = "Président non renseigné.";

  // motion ouverte ?
  $openCount = $meetingRepo->countOpenMotions($meetingId);
  if ($openCount > 0) $reasons[] = "Une motion est encore ouverte.";

  // motions fermées : vérifier qu'il y a soit un comptage manuel cohérent, soit au moins un bulletin
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

} catch (Throwable $e) {
    error_log('Error in dashboard.php: ' . $e->getMessage());
    api_fail('server_error', 500);
}
