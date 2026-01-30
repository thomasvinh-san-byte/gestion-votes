<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';
require __DIR__ . '/../../../app/services/MeetingValidator.php';
require __DIR__ . '/../../../app/services/NotificationsService.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\BallotRepository;

api_require_role('operator');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '') api_fail('missing_meeting_id', 400);

$minOpen = (int)($_GET['min_open'] ?? 900);
$minParticipation = (float)($_GET['min_participation'] ?? 0.5);

$tenant = api_current_tenant_id();

$meetingRepo = new MeetingRepository();
$memberRepo = new MemberRepository();
$motionRepo = new MotionRepository();
$ballotRepo = new BallotRepository();

$meeting = $meetingRepo->findByIdForTenant($meetingId, $tenant);
if (!$meeting) api_fail('meeting_not_found', 404);

$eligibleMembers = $memberRepo->countActive($tenant);

$attRows = $memberRepo->listWithAttendanceForMeeting($meetingId, $tenant);

$presentCount = 0;
$presentWeight = 0.0;
$totalCount = count($attRows);
$totalWeight = 0.0;
$absentIds = [];
$absentNames = [];

foreach ($attRows as $r) {
  $vp = (float)($r['voting_power'] ?? 0);
  $totalWeight += $vp;
  $mode = (string)($r['attendance_mode'] ?? '');
  if ($mode === 'present' || $mode === 'remote' || $mode === 'proxy') {
    $presentCount++;
    $presentWeight += $vp;
  } else {
    $mid = (string)$r['member_id'];
    $absentIds[] = $mid;
    $absentNames[$mid] = (string)($r['full_name'] ?? '');
  }
}

$quorumThreshold = 0.5;
$quorumRatio = $eligibleMembers > 0 ? ($presentCount / $eligibleMembers) : 0.0;
$quorumOk = $presentCount > 0 && $quorumRatio >= $quorumThreshold;

$coveredRows = $meetingRepo->listDistinctProxyGivers($meetingId);
$coveredSet = [];
foreach ($coveredRows as $x) $coveredSet[(string)$x['giver_member_id']] = true;

$missing = [];
foreach ($absentIds as $mid) if (!isset($coveredSet[$mid])) $missing[] = $mid;
$missingNames = array_values(array_filter(array_map(fn($id)=>$absentNames[$id] ?? '', $missing)));

$proxyActive = $meetingRepo->countActiveProxies($tenant, $meetingId);

$motions = $motionRepo->countWorkflowSummary($meetingId);

$openMotion = $motionRepo->findCurrentOpen($meetingId, $tenant);
$nextMotion = $motionRepo->findNextNotOpened($meetingId);

$lastClosedMotion = $motionRepo->findLastClosedForProjector($meetingId);

$hasAnyMotion = ((int)($motions['total'] ?? 0)) > 0;

$openBallots = 0;
$openAgeSeconds = 0;
$participationRatio = null;
$potentialVoters = $presentCount + count($coveredSet);
$closeBlockers = [];
$canCloseOpen = false;

if ($openMotion) {
  $openBallots = $ballotRepo->countByMotionId($openMotion['id']);
  if (!empty($openMotion['opened_at'])) {
    $openAgeSeconds = max(0, time() - strtotime($openMotion['opened_at']));
  }
  $participationRatio = $potentialVoters > 0 ? ($openBallots / $potentialVoters) : 0.0;

  if ($openAgeSeconds < $minOpen) $closeBlockers[] = "Délai minimum non atteint ({$openAgeSeconds}s / {$minOpen}s).";
  if ($participationRatio < $minParticipation) $closeBlockers[] = "Participation insuffisante (".round($participationRatio*100)."%, min ".round($minParticipation*100)."%).";
  $canCloseOpen = count($closeBlockers) === 0;
}

$canOpenNext = $quorumOk && (count($missing) === 0) && ($openMotion === null) && ($nextMotion !== null);

$hasClosed = $meetingRepo->countClosedMotions($meetingId);
$canConsolidate = ((int)($motions['open'] ?? 0)) === 0 && $hasClosed > 0;
$consolidatedCount = $motionRepo->countConsolidatedMotions($meetingId);
$consolidationDone = ($hasClosed > 0) && ($consolidatedCount >= $hasClosed);

$consolidateDetail = $canConsolidate ? "Motions fermées: $hasClosed. Vous pouvez consolider."
  : (((int)($motions['open'] ?? 0)) > 0 ? "Fermez toutes les motions ouvertes avant consolidation." : "Aucune motion fermée à consolider.");

$validation = MeetingValidator::canBeValidated($meetingId, $tenant);
$ready = (bool)($validation['can'] ?? false);
$reasons = (array)($validation['reasons'] ?? []);

// Notifications: blocages / résolutions issus du ready-check (sans spam via cache).
NotificationsService::emitReadinessTransitions($meetingId, $validation);

api_ok([
  'meeting' => [
    'id' => $meeting['id'],
    'title' => $meeting['title'] ?? '',
    'status' => $meeting['status'] ?? '',
    'president_name' => $meeting['president_name'] ?? '',
  ],
  'motions' => [
    'total' => (int)($motions['total'] ?? 0),
    'open' => (int)($motions['open'] ?? 0),
  ],
  'attendance' => [
    'ok' => $presentCount > 0,
    'present_count' => $presentCount,
    'present_weight' => $presentWeight,
    'total_count' => $totalCount,
    'total_weight' => $totalWeight,
    'quorum_threshold' => $quorumThreshold,
    'quorum_ratio' => round($quorumRatio, 4),
    'quorum_ok' => $quorumOk,
  ],
  'proxies' => [
    'ok' => $quorumOk && (count($missing) === 0),
    'active_count' => $proxyActive,
    'missing_absent_without_proxy' => count($missing),
    'missing_names' => $missingNames,
  ],
  'tokens' => [ 'disabled' => true ],
  'motion' => [
    'has_any_motion' => $hasAnyMotion,
    'open_motion_id' => $openMotion['id'] ?? null,
    'open_title' => $openMotion['title'] ?? null,
    'open_ballots' => $openBallots,
    'open_age_seconds' => $openAgeSeconds,
    'potential_voters' => $potentialVoters,
    'participation_ratio' => $participationRatio !== null ? round($participationRatio, 4) : null,
    'close_blockers' => $closeBlockers,
    'next_motion_id' => $nextMotion['id'] ?? null,
    'next_title' => $nextMotion['title'] ?? null,
    'can_open_next' => $canOpenNext,
    'can_close_open' => $canCloseOpen,

    // Utile pour le mode dégradé : saisie manuelle possible sur la dernière motion fermée
    'last_closed_motion_id' => $lastClosedMotion['id'] ?? null,
    'last_closed_title' => $lastClosedMotion['title'] ?? null,
  ],
  'consolidation' => [
    'can' => $canConsolidate,
    'done' => $consolidationDone,
    'detail' => $consolidateDetail,
    'closed_motions' => $hasClosed,
    'consolidated_motions' => $consolidatedCount,
  ],
  'validation' => [
    'ready' => $ready,
    'reasons' => $reasons,
  ],
]);
