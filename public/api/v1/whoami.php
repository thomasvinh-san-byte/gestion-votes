<?php
require __DIR__ . '/../../../app/api.php';

$enabled = AuthMiddleware::isEnabled();
if (!$enabled) {
  api_ok([
    'auth_enabled' => false,
    'user' => null
  ]);
}

$user = AuthMiddleware::authenticate();
if ($user === null) {
  api_fail('missing_or_invalid_api_key', 401, ['auth_enabled' => true]);
}
if (!$user['is_active']) {
  api_fail('user_inactive', 401, ['auth_enabled' => true]);
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

api_ok([
  'auth_enabled' => true,
  'user' => [
    'id' => $user['id'],
    'email' => $user['email'],
    'name' => $user['name'],
    'role' => $user['role'],
  ],
  'member' => $linkedMember,
  'meeting_roles' => $meetingRoles,
]);
