<?php
require __DIR__ . '/../../../app/api.php';
require_once __DIR__ . '/../../../app/services/AuthService.php';

// Toujours JSON
header('Content-Type: application/json; charset=utf-8');

$enabled = AuthService::enabled();
if (!$enabled) {
  echo json_encode([
    'ok' => true,
    'auth_enabled' => false,
    'user' => null
  ]);
  exit;
}

$key = AuthService::getKeyFromRequest();
if ($key === null) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'missing_api_key','auth_enabled'=>true]);
  exit;
}

$user = AuthService::findUserByKey($key);
if (!$user) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'invalid_api_key','auth_enabled'=>true]);
  exit;
}
if (!$user['is_active']) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'user_inactive','auth_enabled'=>true]);
  exit;
}

echo json_encode([
  'ok' => true,
  'auth_enabled' => true,
  'user' => [
    'id' => $user['id'],
    'email' => $user['email'],
    'name' => $user['name'],
    'role' => $user['role'],
  ]
]);
