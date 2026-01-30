<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';
require __DIR__ . '/../../../app/services/OfficialResultsService.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\PolicyRepository;

api_require_role('auditor');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '') api_fail('missing_meeting_id', 400);

$tenant = api_current_tenant_id();

$meetingRepo = new MeetingRepository();
$motionRepo  = new MotionRepository();
$ballotRepo  = new BallotRepository();
$policyRepo  = new PolicyRepository();

$meeting = $meetingRepo->findByIdForTenant($meetingId, $tenant);
if (!$meeting) api_fail('meeting_not_found', 404);

$meetingVotePolicyId = $meeting['vote_policy_id'] ?? null;
$meetingQuorumPolicyId = $meeting['quorum_policy_id'] ?? null;

OfficialResultsService::ensureSchema();

$motions = $motionRepo->listForReport($meetingId);

$out = [];
foreach ($motions as $m) {
  $mid = (string)$m['id'];
  $src = (string)($m['official_source'] ?? '');
  $hasOfficial = $src !== '' && $m['official_total'] !== null;

  if (!$hasOfficial && $m['closed_at'] !== null) {
    $o = OfficialResultsService::computeOfficialTallies($mid);
    $src = $o['source'];
    $of = $o['for']; $og = $o['against']; $oa = $o['abstain']; $ot = $o['total'];
    $decStatus = $o['decision']; $decReason = $o['reason'];
  } else {
    $of = (float)($m['official_for'] ?? 0);
    $og = (float)($m['official_against'] ?? 0);
    $oa = (float)($m['official_abstain'] ?? 0);
    $ot = (float)($m['official_total'] ?? 0);
    $decStatus = (string)($m['decision'] ?? '—');
    $decReason = (string)($m['decision_reason'] ?? '');
  }

  $counts = $ballotRepo->countChoicesByMotion($mid);

  $motionVotePolicyId = $m['vote_policy_id'] ?? null;
  $motionQuorumPolicyId = $m['quorum_policy_id'] ?? null;
  $effectiveVotePolicyId = $motionVotePolicyId ?: $meetingVotePolicyId;
  $effectiveQuorumPolicyId = $motionQuorumPolicyId ?: $meetingQuorumPolicyId;

  $votePolicyName = $effectiveVotePolicyId
      ? $policyRepo->findVotePolicyName($tenant, $effectiveVotePolicyId)
      : null;
  $quorumPolicyName = $effectiveQuorumPolicyId
      ? $policyRepo->findQuorumPolicyName($tenant, $effectiveQuorumPolicyId)
      : null;

  $out[] = [
    'motion_id' => $mid,
    'title' => $m['title'] ?? '',
    'description' => $m['description'] ?? '',
    'secret' => (bool)($m['secret'] ?? false),
    'vote_policy' => [
      'id' => $effectiveVotePolicyId,
      'name' => $votePolicyName,
      'source' => $motionVotePolicyId ? 'motion' : 'meeting',
      'overridden' => (bool)$motionVotePolicyId,
    ],
    'quorum_policy' => [
      'id' => $effectiveQuorumPolicyId,
      'name' => $quorumPolicyName,
      'source' => $motionQuorumPolicyId ? 'motion' : 'meeting',
      'overridden' => (bool)$motionQuorumPolicyId,
    ],
    'opened_at' => $m['opened_at'],
    'closed_at' => $m['closed_at'],
    'official_source' => $src ?: '—',
    'tallies' => [
      'for' => ['weight' => $of, 'count' => (int)($counts['c_for'] ?? 0)],
      'against' => ['weight' => $og, 'count' => (int)($counts['c_against'] ?? 0)],
      'abstain' => ['weight' => $oa, 'count' => (int)($counts['c_abstain'] ?? 0)],
      'total' => ['weight' => $ot],
    ],
    'decision' => [
      'status' => $decStatus,
      'reason' => $decReason,
    ],
  ];
}

api_ok(['motions' => $out]);
