<?php
// ADMIN: liste + upsert vote_policies (majorité)
require __DIR__ . '/../../../app/api.php';

api_require_role('admin');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
  api_request('GET');
  $rows = db_select_all(
    "SELECT id, name, description, base, threshold, abstention_as_against, updated_at
     FROM vote_policies
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
  $base = trim((string)($in['base'] ?? 'expressed'));
  if (!in_array($base, ['expressed','total_eligible'], true)) api_fail('invalid_base', 400);

  $threshold = ($in['threshold'] === '' || !isset($in['threshold'])) ? null : (float)$in['threshold'];
  if ($threshold === null || $threshold < 0 || $threshold > 1) api_fail('invalid_threshold', 400);

  $abst = (string)($in['abstention_as_against'] ?? '0');
  $abstBool = ($abst === '1' || $abst === 'true' || $abst === 'on');

  if ($id !== '') {
    if (!api_is_uuid($id)) api_fail('invalid_id', 400);
    db_execute(
      "UPDATE vote_policies SET
          name=:n, description=:d, base=:b, threshold=:thr, abstention_as_against=:a, updated_at=NOW()
       WHERE tenant_id=:t AND id=:id",
      [':n'=>$name, ':d'=>$desc, ':b'=>$base, ':thr'=>$threshold, ':a'=>$abstBool, ':t'=>api_current_tenant_id(), ':id'=>$id]
    );
  } else {
    $id = db_scalar("SELECT gen_random_uuid()");
    db_execute(
      "INSERT INTO vote_policies(id, tenant_id, name, description, base, threshold, abstention_as_against, created_at, updated_at)
       VALUES (:id,:t,:n,:d,:b,:thr,:a,NOW(),NOW())",
      [':id'=>$id, ':t'=>api_current_tenant_id(), ':n'=>$name, ':d'=>$desc, ':b'=>$base, ':thr'=>$threshold, ':a'=>$abstBool]
    );
  }

  if (function_exists('audit_log')) {
    audit_log('admin_vote_policy_saved', 'vote_policy', $id, ['name'=>$name,'base'=>$base]);
  }

  api_ok(['saved'=>true, 'id'=>$id]);
}

api_fail('method_not_allowed', 405);
