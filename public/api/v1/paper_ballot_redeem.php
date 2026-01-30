<?php
require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\BallotRepository;
use AgVote\Repository\ManualActionRepository;

api_require_role('operator');

$in = api_request('POST');

$code = trim((string)($in['code'] ?? ''));
if ($code === '' || !api_is_uuid($code)) api_fail('invalid_code', 400);

$vote = trim((string)($in['vote_value'] ?? ''));
if (!in_array($vote, ['pour','contre','abstention','blanc'], true)) api_fail('invalid_vote_value', 400);

$just = trim((string)($in['justification'] ?? 'vote papier (secours)'));
if ($just === '') api_fail('missing_justification', 400);

$hash = hash_hmac('sha256', $code, APP_SECRET);

$ballotRepo = new BallotRepository();
$manualRepo = new ManualActionRepository();

// On récupère aussi tenant_id via meeting
$pb = $ballotRepo->findUnusedPaperBallotByHash($hash);
if (!$pb) api_fail('paper_ballot_not_found_or_used', 404);

$ballotRepo->markPaperBallotUsed($pb['id']);

// Journal mode dégradé (append-only)
try {
  $manualRepo->createPaperBallotAction(
    $pb['tenant_id'],
    $pb['meeting_id'],
    $pb['motion_id'],
    $vote,
    $just
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
