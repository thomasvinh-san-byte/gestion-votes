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

// Charger les rôles de séance actifs
$meetingRoles = [];
try {
    $userRepo = new \AgVote\Repository\UserRepository();
    $meetingRoles = $userRepo->listActiveMeetingRolesForUser($user['id'], $user['tenant_id']);
} catch (\Throwable $e) {
    // best effort
}

// Resolve linked member (users.id → members.user_id)
$linkedMember = null;
try {
    $memberRepo = new \AgVote\Repository\MemberRepository();
    $found = $memberRepo->findByUserId($user['id'], $user['tenant_id']);
    if ($found) {
        $linkedMember = [
            'id' => $found['id'],
            'full_name' => $found['full_name'],
            'voting_power' => (float)($found['voting_power'] ?? 1),
        ];
    }
} catch (\Throwable $e) {
    // best effort
}

echo json_encode([
  'ok' => true,
  'auth_enabled' => true,
  'data' => [
    'user' => [
      'id' => $user['id'],
      'email' => $user['email'],
      'name' => $user['name'],
      'role' => $user['role'],
    ],
    'member' => $linkedMember,
    'meeting_roles' => $meetingRoles,
  ]
]);
