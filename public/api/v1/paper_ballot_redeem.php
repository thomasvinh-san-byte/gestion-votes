<?php
require __DIR__ . '/../../../app/api.php';
api_require_role('operator');

$in = api_request('POST');

$code = trim((string)($in['code'] ?? ''));
if ($code === '' || !api_is_uuid($code)) api_fail('invalid_code', 400);

$vote = trim((string)($in['vote_value'] ?? ''));
if (!in_array($vote, ['pour','contre','abstention','blanc'], true)) api_fail('invalid_vote_value', 400);

$just = trim((string)($in['justification'] ?? 'vote papier (secours)'));
if ($just === '') api_fail('missing_justification', 400);

$hash = hash_hmac('sha256', $code, APP_SECRET);

// On récupère aussi tenant_id via meeting
$pb = db_select_one(
  "SELECT pb.*, m.tenant_id
   FROM paper_ballots pb
   JOIN meetings m ON m.id = pb.meeting_id
   WHERE pb.code_hash = ? AND pb.used_at IS NULL",
  [$hash]
);
if (!$pb) api_fail('paper_ballot_not_found_or_used', 404);

db_execute("UPDATE paper_ballots SET used_at = NOW(), used_by_operator = true WHERE id = ?", [$pb['id']]);

// Journal mode dégradé (append-only)
try {
  db_execute(
    "INSERT INTO manual_actions(tenant_id, meeting_id, motion_id, member_id, action_type, value, justification, operator_user_id, signature_hash, created_at)
     VALUES (:t,:m,:mo,NULL,'paper_ballot', jsonb_build_object('vote_value', :v), :j, NULL, NULL, NOW())",
    [
      ':t' => $pb['tenant_id'],
      ':m' => $pb['meeting_id'],
      ':mo'=> $pb['motion_id'],
      ':v' => $vote,
      ':j' => $just,
    ]
  );
} catch (Throwable $e) {
  // best-effort
}

if (function_exists('audit_log')) {
  audit_log('paper_ballot_redeemed', 'motion', $pb['motion_id'], [
    'meeting_id'=>$pb['meeting_id'],
    'vote_value'=>$vote,
    'paper_ballot_id'=>$pb['id']
  ]);
}

api_ok(['saved'=>true]);
