<?php
// public/api/v1/motion_tally.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MotionRepository;

api_require_role('operator');

$in = api_request('GET');
$motionId = trim((string)($in['motion_id'] ?? ($_GET['motion_id'] ?? '')));
if ($motionId === '' || !api_is_uuid($motionId)) api_fail('invalid_motion_id', 400);

$repo = new MotionRepository();

$motion = $repo->findByIdForTenant($motionId, api_current_tenant_id());
if (!$motion) api_fail('motion_not_found', 404);

$rows = $repo->getTally($motionId);

$result = [
  'for' => ['count'=>0,'weight'=>0],
  'against' => ['count'=>0,'weight'=>0],
  'abstain' => ['count'=>0,'weight'=>0],
  'nsp' => ['count'=>0,'weight'=>0],
];

foreach ($rows as $r) {
  $v = $r['value'];
  if (!isset($result[$v])) continue;
  $result[$v]['count'] = (int)$r['c'];
  $result[$v]['weight'] = (float)$r['w'];
}

api_ok([
  'motion_id' => $motionId,
  'closed' => $motion['closed_at'] !== null,
  'tally' => $result,
]);
