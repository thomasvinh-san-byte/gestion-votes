<?php
// ADMIN: liste + upsert vote_policies (majorité)
require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\PolicyRepository;

api_require_role('admin');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$repo = new PolicyRepository();

if ($method === 'GET') {
  api_request('GET');
  $rows = $repo->listVotePolicies(api_current_tenant_id());
  api_ok(['items' => $rows]);
}

if ($method === 'POST') {
  $in = api_request('POST');

  $action = trim((string)($in['action'] ?? ''));

  // ── Supprimer une politique de vote ──
  if ($action === 'delete') {
    $id = trim((string)($in['id'] ?? ''));
    if ($id === '' || !api_is_uuid($id)) api_fail('missing_id', 400);

    $repo->deleteVotePolicy($id, api_current_tenant_id());
    if (function_exists('audit_log')) {
      audit_log('admin_vote_policy_deleted', 'vote_policy', $id, []);
    }
    api_ok(['deleted' => true, 'id' => $id]);
  }

  // ── Créer ou mettre à jour ──
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
    $repo->updateVotePolicy(
      $id, api_current_tenant_id(), $name, $desc === '' ? null : $desc,
      $base, $threshold, $abstBool
    );
  } else {
    $id = $repo->generateUuid();
    $repo->createVotePolicy(
      $id, api_current_tenant_id(), $name, $base, $threshold, $abstBool
    );
  }

  if (function_exists('audit_log')) {
    audit_log('admin_vote_policy_saved', 'vote_policy', $id, ['name'=>$name,'base'=>$base]);
  }

  api_ok(['saved'=>true, 'id'=>$id]);
}

api_fail('method_not_allowed', 405);
