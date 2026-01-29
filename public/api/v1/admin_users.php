<?php
require __DIR__ . '/../../../app/api.php';
require_once __DIR__ . '/../../../app/services/AuthService.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
api_require_role('admin');

if ($method === 'GET') {
  api_request('GET');
  $rows = db_select_all(
    "SELECT id, email, name, role, is_active, created_at, updated_at
     FROM users
     WHERE tenant_id = ?
     ORDER BY role ASC, name ASC",
    [DEFAULT_TENANT_ID]
  );
  api_ok(['items' => $rows]);
}

if ($method === 'POST') {
  $in = api_request('POST');
  $action = trim((string)($in['action'] ?? 'upsert'));

  if ($action === 'rotate_key') {
    $userId = api_require_uuid($in, 'user_id');
    $apiKey = bin2hex(random_bytes(16));
    $hash = AuthService::hashKey($apiKey);
    db_execute("UPDATE users SET api_key_hash=:h, updated_at=NOW() WHERE tenant_id=:t AND id=:id",
      [':h'=>$hash, ':t'=>DEFAULT_TENANT_ID, ':id'=>$userId]
    );
    if (function_exists('audit_log')) audit_log('admin_user_key_rotated','user',$userId,[]);
    api_ok(['rotated'=>true,'api_key'=>$apiKey]);
  }

  if ($action === 'set_active') {
    $userId = api_require_uuid($in, 'user_id');
    $active = (int)($in['is_active'] ?? 1) ? true : false;
    db_execute("UPDATE users SET is_active=:a, updated_at=NOW() WHERE tenant_id=:t AND id=:id",
      [':a'=>$active, ':t'=>DEFAULT_TENANT_ID, ':id'=>$userId]
    );
    if (function_exists('audit_log')) audit_log('admin_user_active_set','user',$userId,['is_active'=>$active]);
    api_ok(['saved'=>true]);
  }

  $id = trim((string)($in['id'] ?? ''));
  $email = strtolower(trim((string)($in['email'] ?? '')));
  $name = trim((string)($in['name'] ?? ''));
  $role = trim((string)($in['role'] ?? ''));

  if ($email === '' || $name === '' || $role === '') api_fail('missing_fields', 400);
  if (!in_array($role, ['admin','operator','trust','readonly'], true)) api_fail('invalid_role', 400);
  if ($id !== '' && !api_is_uuid($id)) api_fail('invalid_id', 400);

  if ($id === '') {
    $apiKey = bin2hex(random_bytes(16));
    $hash = AuthService::hashKey($apiKey);
    $id = db_scalar("SELECT gen_random_uuid()");
    db_execute(
      "INSERT INTO users(id, tenant_id, email, name, role, api_key_hash, is_active, created_at, updated_at)
       VALUES (:id,:t,:e,:n,:r,:h,true,NOW(),NOW())",
      [':id'=>$id, ':t'=>DEFAULT_TENANT_ID, ':e'=>$email, ':n'=>$name, ':r'=>$role, ':h'=>$hash]
    );
    if (function_exists('audit_log')) audit_log('admin_user_created','user',$id,['email'=>$email,'role'=>$role]);
    api_ok(['saved'=>true,'id'=>$id,'api_key'=>$apiKey]);
  } else {
    db_execute(
      "UPDATE users SET email=:e, name=:n, role=:r, updated_at=NOW() WHERE tenant_id=:t AND id=:id",
      [':e'=>$email, ':n'=>$name, ':r'=>$role, ':t'=>DEFAULT_TENANT_ID, ':id'=>$id]
    );
    if (function_exists('audit_log')) audit_log('admin_user_updated','user',$id,['email'=>$email,'role'=>$role]);
    api_ok(['saved'=>true,'id'=>$id]);
  }
}

api_fail('method_not_allowed', 405);
