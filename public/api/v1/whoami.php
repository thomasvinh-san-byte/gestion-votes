<?php
require __DIR__ . '/../../../app/api.php';

// Toujours JSON
header('Content-Type: application/json; charset=utf-8');

$enabled = AuthMiddleware::isEnabled();
if (!$enabled) {
  echo json_encode([
    'ok' => true,
    'auth_enabled' => false,
    'user' => null
  ]);
  exit;
}

$user = AuthMiddleware::authenticate();
if ($user === null) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'missing_or_invalid_api_key','auth_enabled'=>true]);
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
