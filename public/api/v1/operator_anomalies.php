<?php
// public/api/v1/operator_anomalies.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\VoteTokenRepository;

api_request('GET');
api_require_role('operator');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '' || !api_is_uuid($meetingId)) {
  api_fail('invalid_meeting_id', 422);
}

$motionId = trim((string)($_GET['motion_id'] ?? ''));
if ($motionId !== '' && !api_is_uuid($motionId)) {
  api_fail('invalid_motion_id', 422);
}

$meetingRepo = new MeetingRepository();
$motionRepo = new MotionRepository();
$memberRepo = new MemberRepository();
$ballotRepo = new BallotRepository();
$tokenRepo = new VoteTokenRepository();

// 1) Meeting (lockdown)
$meeting = $meetingRepo->findByIdForTenant($meetingId, api_current_tenant_id());
if (!$meeting) api_fail('meeting_not_found', 404);

// 2) Motion cible: motion_id -> sinon motion ouverte -> sinon null
if ($motionId === '') {
  $open = $motionRepo->findCurrentOpen($meetingId, api_current_tenant_id());
  $motionId = $open ? (string)$open['id'] : '';
}

$motion = null;
if ($motionId !== '') {
  $motion = $motionRepo->findByMeetingWithDates(api_current_tenant_id(), $meetingId, $motionId);
  if (!$motion) api_fail('motion_not_found', 404);
}

// 3) Éligibles: présents/remote/proxy (fallback: tous)
$eligibleRows = $memberRepo->listEligibleForMeeting(api_current_tenant_id(), $meetingId);
if (!$eligibleRows) {
  $eligibleRows = $memberRepo->listActiveFallbackByMeeting(api_current_tenant_id(), $meetingId);
}

$eligibleIds = [];
$eligibleNames = [];
foreach ($eligibleRows as $r) {
  $id = (string)($r['member_id'] ?? '');
  if ($id === '') continue;
  $eligibleIds[] = $id;
  $eligibleNames[$id] = (string)($r['full_name'] ?? '');
}

$eligibleCount = count($eligibleIds);

$proxyMax = (int)($_ENV['PROXY_MAX_PER_RECEIVER'] ?? getenv('PROXY_MAX_PER_RECEIVER') ?? 99);
$proxyCeilings = [];
try {
  $rows = $meetingRepo->listProxyCeilingViolations(api_current_tenant_id(), $meetingId, $proxyMax);
  foreach ($rows as $r) {
    $pid = (string)$r['proxy_id'];
    $proxyCeilings[] = [
      'proxy_id' => $pid,
      'proxy_name' => $eligibleNames[$pid] ?? null,
      'count' => (int)$r['c'],
      'max' => $proxyMax,
    ];
  }
} catch (Throwable $e) { $proxyCeilings = []; }



// 4) Stats tokens/ballots sur la motion (si présente)
$stats = [
  'tokens_active_unused' => 0,
  'tokens_expired_unused' => 0,
  'tokens_used' => 0,
  'ballots_total' => 0,
  'ballots_from_eligible' => 0,
  'eligible_expected' => $eligibleCount,
  'missing_ballots_from_eligible' => 0,
];

$missingNames = [];
$ballotsNotEligible = [];
$duplicates = [];

if ($motionId !== '') {
  $stats['tokens_active_unused'] = $tokenRepo->countActiveUnused(api_current_tenant_id(), $meetingId, $motionId);

  $stats['tokens_expired_unused'] = $tokenRepo->countExpiredUnused(api_current_tenant_id(), $meetingId, $motionId);

  $stats['tokens_used'] = $tokenRepo->countUsed(api_current_tenant_id(), $meetingId, $motionId);

  $ballots = $ballotRepo->listForMotionWithSource(api_current_tenant_id(), $meetingId, $motionId);

  $stats['ballots_total'] = count($ballots);

  $votedSet = [];
  foreach ($ballots as $b) {
    $mid = (string)($b['member_id'] ?? '');
    if ($mid === '') continue;

    // doublons théoriquement impossibles (UNIQUE motion_id, member_id), mais on trace quand même
    if (isset($votedSet[$mid])) {
      $duplicates[] = [
        'member_id' => $mid,
        'name' => $eligibleNames[$mid] ?? null,
        'detail' => 'duplicate_ballot_for_member',
      ];
    }
    $votedSet[$mid] = true;

    if (!in_array($mid, $eligibleIds, true)) {
      $ballotsNotEligible[] = [
        'member_id' => $mid,
        'value' => (string)($b['value'] ?? ''),
        'source' => (string)($b['source'] ?? ''),
        'cast_at' => $b['cast_at'],
      ];
    }
  }

  $eligibleVoted = 0;
  foreach ($eligibleIds as $id) {
    if (isset($votedSet[$id])) $eligibleVoted++;
  }
  $stats['ballots_from_eligible'] = $eligibleVoted;
  $stats['missing_ballots_from_eligible'] = max(0, $eligibleCount - $eligibleVoted);

  if ($stats['missing_ballots_from_eligible'] > 0) {
    foreach ($eligibleIds as $id) {
      if (!isset($votedSet[$id])) {
        $missingNames[] = $eligibleNames[$id] ?? $id;
        if (count($missingNames) >= 30) break;
      }
    }
  }
}

api_ok([
  'meeting' => [
    'id' => $meetingId,
    'status' => (string)($meeting['status'] ?? ''),
    'validated_at' => $meeting['validated_at'],
  ],
  'motion' => $motion ? [
    'id' => (string)$motion['id'],
    'title' => (string)($motion['title'] ?? ''),
    'opened_at' => $motion['opened_at'],
    'closed_at' => $motion['closed_at'],
  ] : null,
  'eligibility' => [
    'expected_count' => $eligibleCount,
  ],
  'stats' => $stats,
  'anomalies' => [
    'missing_voters_sample' => $missingNames,
    'ballots_not_eligible' => $ballotsNotEligible,
    'duplicates' => $duplicates,
  ],
]);
