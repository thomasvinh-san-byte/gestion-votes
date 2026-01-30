<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';
require __DIR__ . '/../../../app/services/OfficialResultsService.php';

require_role('trust');

$meetingId = trim((string)($_GET['meeting_id'] ?? ''));
if ($meetingId === '') json_err('missing_meeting_id', 400);

$tenant = DEFAULT_TENANT_ID;

$meeting = db_select_one("SELECT id FROM meetings WHERE tenant_id = ? AND id = ?", [$tenant, $meetingId]);
if (!$meeting) json_err('meeting_not_found', 404);

$meetingSettings = db_select_one(
  "SELECT vote_policy_id AS meeting_vote_policy_id, quorum_policy_id AS meeting_quorum_policy_id
   FROM meetings WHERE tenant_id = ? AND id = ?",
  [$tenant, $meetingId]
);
$meetingVotePolicyId = $meetingSettings['meeting_vote_policy_id'] ?? null;
$meetingQuorumPolicyId = $meetingSettings['meeting_quorum_policy_id'] ?? null;

function _policy_name(?string $table, ?string $id): ?string {
  if (!$table || !$id) return null;
  $row = db_select_one("SELECT name FROM {$table} WHERE tenant_id = ? AND id = ?", [DEFAULT_TENANT_ID, $id]);
  return $row ? (string)$row['name'] : null;
}

OfficialResultsService::ensureSchema();

$motions = db_select_all(
  "SELECT id, title, description, opened_at, closed_at,
          secret, vote_policy_id, quorum_policy_id,
          official_source, official_for, official_against, official_abstain, official_total,
          decision, decision_reason
   FROM motions WHERE meeting_id = ?
   ORDER BY position ASC NULLS LAST, created_at ASC",
  [$meetingId]
);

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

  $counts = db_select_one(
    "SELECT
       SUM(CASE WHEN COALESCE(value::text, choice)='for' THEN 1 ELSE 0 END) AS c_for,
       SUM(CASE WHEN COALESCE(value::text, choice)='against' THEN 1 ELSE 0 END) AS c_against,
       SUM(CASE WHEN COALESCE(value::text, choice)='abstain' THEN 1 ELSE 0 END) AS c_abstain
     FROM ballots WHERE motion_id = ?",
    [$mid]
  ) ?: ['c_for'=>0,'c_against'=>0,'c_abstain'=>0];

  $motionVotePolicyId = $m['vote_policy_id'] ?? null;
  $motionQuorumPolicyId = $m['quorum_policy_id'] ?? null;
  $effectiveVotePolicyId = $motionVotePolicyId ?: $meetingVotePolicyId;
  $effectiveQuorumPolicyId = $motionQuorumPolicyId ?: $meetingQuorumPolicyId;

  $votePolicyName = $effectiveVotePolicyId ? _policy_name('vote_policies', $effectiveVotePolicyId) : null;
  $quorumPolicyName = $effectiveQuorumPolicyId ? _policy_name('quorum_policies', $effectiveQuorumPolicyId) : null;

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

json_ok(['motions' => $out]);
