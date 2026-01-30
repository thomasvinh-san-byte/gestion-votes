<?php
// ADMIN: liste + upsert quorum_policies (cahier: paramétrage global)
require __DIR__ . '/../../../app/api.php';

api_require_role('admin');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
  api_request('GET');
  $rows = db_select_all(
    "SELECT id, name, description, mode, denominator, threshold, threshold_call2, denominator2, threshold2,
            include_proxies, count_remote, updated_at
     FROM quorum_policies
     WHERE tenant_id = ?
     ORDER BY name ASC",
    [api_current_tenant_id()]
  );
  api_ok(['items' => $rows]);
}

if ($method === 'POST') {
  $in = api_request('POST');

  $id = trim((string)($in['id'] ?? ''));
  $name = trim((string)($in['name'] ?? ''));
  if ($name === '') api_fail('missing_name', 400);

  $desc = trim((string)($in['description'] ?? ''));
  $mode = trim((string)($in['mode'] ?? 'single'));
  if (!in_array($mode, ['single','evolving','double'], true)) api_fail('invalid_mode', 400);

  $den = trim((string)($in['denominator'] ?? 'eligible_members'));
  if (!in_array($den, ['eligible_members','eligible_weight'], true)) api_fail('invalid_denominator', 400);

  $threshold = ($in['threshold'] === '' || !isset($in['threshold'])) ? null : (float)$in['threshold'];
  if ($threshold === null || $threshold < 0 || $threshold > 1) api_fail('invalid_threshold', 400);

  $threshold_call2 = ($in['threshold_call2'] ?? '') === '' ? null : (float)$in['threshold_call2'];
  if ($threshold_call2 !== null && ($threshold_call2 < 0 || $threshold_call2 > 1)) api_fail('invalid_threshold_call2', 400);

  $den2 = trim((string)($in['denominator2'] ?? ''));
  if ($den2 !== '' && !in_array($den2, ['eligible_members','eligible_weight'], true)) api_fail('invalid_denominator2', 400);

  $threshold2 = ($in['threshold2'] ?? '') === '' ? null : (float)$in['threshold2'];
  if ($threshold2 !== null && ($threshold2 < 0 || $threshold2 > 1)) api_fail('invalid_threshold2', 400);

  $includeProxies = (int)($in['include_proxies'] ?? 0) ? true : false;
  $countRemote = (int)($in['count_remote'] ?? 0) ? true : false;

  // Insert/update
  if ($id !== '') {
    if (!api_is_uuid($id)) api_fail('invalid_id', 400);
    db_execute(
      "UPDATE quorum_policies SET
          name=:n, description=:d, mode=:m, denominator=:den, threshold=:thr,
          threshold_call2=:c2, denominator2=:den2, threshold2=:thr2,
          include_proxies=:ip, count_remote=:cr, updated_at=NOW()
       WHERE tenant_id=:t AND id=:id",
      [
        ':n'=>$name, ':d'=>$desc, ':m'=>$mode, ':den'=>$den, ':thr'=>$threshold,
        ':c2'=>$threshold_call2, ':den2'=>($den2==''?null:$den2), ':thr2'=>$threshold2,
        ':ip'=>$includeProxies, ':cr'=>$countRemote, ':t'=>api_current_tenant_id(), ':id'=>$id
      ]
    );
  } else {
    $id = db_scalar("SELECT gen_random_uuid()");
    db_execute(
      "INSERT INTO quorum_policies(id, tenant_id, name, description, mode, denominator, threshold, threshold_call2, denominator2, threshold2, include_proxies, count_remote, created_at, updated_at)
       VALUES (:id,:t,:n,:d,:m,:den,:thr,:c2,:den2,:thr2,:ip,:cr,NOW(),NOW())",
      [
        ':id'=>$id, ':t'=>api_current_tenant_id(), ':n'=>$name, ':d'=>$desc, ':m'=>$mode, ':den'=>$den, ':thr'=>$threshold,
        ':c2'=>$threshold_call2, ':den2'=>($den2==''?null:$den2), ':thr2'=>$threshold2,
        ':ip'=>$includeProxies, ':cr'=>$countRemote
      ]
    );
  }

  if (function_exists('audit_log')) {
    audit_log('admin_quorum_policy_saved', 'quorum_policy', $id, ['name'=>$name,'mode'=>$mode]);
  }

  api_ok(['saved'=>true, 'id'=>$id]);
}

api_fail('method_not_allowed', 405);
