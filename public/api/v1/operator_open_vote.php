<?php
// public/api/v1/operator_open_vote.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

api_require_role('operator');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  json_err('method_not_allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$meetingId = trim((string)($input['meeting_id'] ?? ''));

api_guard_meeting_not_validated($meetingId);

if ($meetingId === '' || !api_is_uuid($meetingId)) {
  json_err('invalid_meeting_id', 422);
}

$motionId = trim((string)($input['motion_id'] ?? ''));
if ($motionId !== '' && !api_is_uuid($motionId)) {
  json_err('invalid_motion_id', 422);
}

$listTokens = (string)($input['list'] ?? '') === '1'; // optionnel: debug testing
$expiresMinutes = (int)($input['expires_minutes'] ?? 120);
if ($expiresMinutes < 10) $expiresMinutes = 10;
if ($expiresMinutes > 24*60) $expiresMinutes = 24*60;

// Secret pour hasher les tokens (source unique: APP_SECRET)
// IMPORTANT: vote.php utilise APP_SECRET, donc on doit rester alignés.
$secret = (defined('APP_SECRET') && (string)APP_SECRET !== '')
  ? (string)APP_SECRET
  : (getenv('APP_SECRET') ?: 'change-me-in-prod');

try {
  $pdo = db();
  $pdo->beginTransaction();

  // Meeting
  $meeting = db_select_one(
    "SELECT id, status, validated_at
     FROM meetings
     WHERE tenant_id = :tid AND id = :id
     FOR UPDATE",
    [':tid' => DEFAULT_TENANT_ID, ':id' => $meetingId]
  );
  if (!$meeting) {
    $pdo->rollBack();
    json_err('meeting_not_found', 404);
  }
  if (!empty($meeting['validated_at'])) {
    $pdo->rollBack();
    json_err('meeting_validated_locked', 409, ['detail' => "Séance validée : action interdite."]);
  }

  // Force live (pour tests / recette)
  $status = (string)($meeting['status'] ?? '');
  if ($status !== 'live') {
    db_execute(
      "UPDATE meetings SET status='live', updated_at=now()
       WHERE tenant_id=:tid AND id=:id",
      [':tid' => DEFAULT_TENANT_ID, ':id' => $meetingId]
    );
  }

  // Motion selection: either provided or next not-opened
  if ($motionId === '') {
    $next = db_select_one(
      "SELECT id
       FROM motions
       WHERE tenant_id=:tid AND meeting_id=:mid
         AND opened_at IS NULL AND closed_at IS NULL
       ORDER BY COALESCE(position, sort_order, 0) ASC
       LIMIT 1
       FOR UPDATE",
      [':tid' => DEFAULT_TENANT_ID, ':mid' => $meetingId]
    );
    if (!$next) {
      $pdo->rollBack();
      json_err('no_motion_to_open', 409, ['detail' => "Aucune résolution disponible à ouvrir."]);
    }
    $motionId = (string)$next['id'];
  } else {
    // lock row
    $row = db_select_one(
      "SELECT id FROM motions
       WHERE tenant_id=:tid AND meeting_id=:mid AND id=:id
       FOR UPDATE",
      [':tid' => DEFAULT_TENANT_ID, ':mid' => $meetingId, ':id' => $motionId]
    );
    if (!$row) {
      $pdo->rollBack();
      json_err('motion_not_found', 404);
    }
  }

  // Ensure only one open motion
  $open = db_select_one(
    "SELECT id FROM motions
     WHERE tenant_id=:tid AND meeting_id=:mid
       AND opened_at IS NOT NULL AND closed_at IS NULL
     LIMIT 1",
    [':tid' => DEFAULT_TENANT_ID, ':mid' => $meetingId]
  );
  if ($open && (string)$open['id'] !== $motionId) {
    $pdo->rollBack();
    json_err('another_motion_active', 409, [
      'detail' => "Une résolution est déjà ouverte : clôturez-la avant d'en ouvrir une autre.",
      'open_motion_id' => (string)$open['id'],
    ]);
  }

  // Open motion (idempotent)
  db_execute(
    "UPDATE motions
     SET opened_at = COALESCE(opened_at, now()),
         closed_at = NULL
     WHERE tenant_id=:tid AND id=:id AND meeting_id=:mid AND closed_at IS NULL",
    [':tid' => DEFAULT_TENANT_ID, ':id' => $motionId, ':mid' => $meetingId]
  );

  db_execute(
    "UPDATE meetings
     SET current_motion_id=:mo, updated_at=now()
     WHERE tenant_id=:tid AND id=:mid",
    [':tid' => DEFAULT_TENANT_ID, ':mid' => $meetingId, ':mo' => $motionId]
  );

  // Eligible members: present/remote/proxy; fallback all members (recette)
  // Table canonique: attendances(mode)
  $eligible = db_select_all(
    "SELECT member_id
     FROM attendances
     WHERE tenant_id=:tid AND meeting_id=:mid
       AND mode IN ('present','remote','proxy')",
    [':tid' => DEFAULT_TENANT_ID, ':mid' => $meetingId]
  );

  if (!$eligible) {
    $eligible = db_select_all(
      "SELECT id AS member_id
       FROM members
       WHERE tenant_id=:tid AND meeting_id=:mid",
      [':tid' => DEFAULT_TENANT_ID, ':mid' => $meetingId]
    );
  }

  $expiresAtSql = "now() + make_interval(mins => :mins)";
  $inserted = 0;
  $tokensOut = [];

  foreach ($eligible as $e) {
    $memberId = (string)$e['member_id'];
    if ($memberId === '') continue;

    // UUID v4 simple (PHP 8+ random_bytes)
    $raw = sprintf(
      '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
      random_int(0, 0xffff), random_int(0, 0xffff),
      random_int(0, 0xffff),
      random_int(0, 0x0fff) | 0x4000,
      random_int(0, 0x3fff) | 0x8000,
      random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
    );

    $hash = hash_hmac('sha256', $raw, $secret);

    // Idempotence pragmatique:
    // - si un token NON utilisé et NON expiré existe déjà => on ne régénère pas
    // - sinon (utilisé ou expiré) => on crée un nouveau token (PK=token_hash => pas de doublons)
    $active = db_select_one(
      "SELECT token_hash
       FROM vote_tokens
       WHERE tenant_id=:tid AND meeting_id=:mid AND motion_id=:mo AND member_id=:mb
         AND used_at IS NULL AND expires_at > NOW()
       ORDER BY created_at DESC
       LIMIT 1",
      [':tid'=>DEFAULT_TENANT_ID, ':mid'=>$meetingId, ':mo'=>$motionId, ':mb'=>$memberId]
    );
    if ($active) continue;

    $ok = db_execute(
      "INSERT INTO vote_tokens(token_hash, tenant_id, meeting_id, member_id, motion_id, expires_at, used_at, created_at)
       VALUES(:h, :tid, :mid, :mb, :mo, ($expiresAtSql), NULL, now())",
      [
        ':h'=>$hash, ':tid'=>DEFAULT_TENANT_ID, ':mid'=>$meetingId, ':mb'=>$memberId, ':mo'=>$motionId,
        ':mins'=>$expiresMinutes
      ]
    );

    if ($ok > 0) {
      $inserted++;
      if ($listTokens) {
        $tokensOut[] = [
          'member_id' => $memberId,
          'token' => $raw,
          'url' => "/vote.php?token=".$raw
        ];
      }
    }
  }

  $pdo->commit();

  audit_log('vote_tokens_generated', 'motion', $motionId, [
    'meeting_id' => $meetingId,
    'inserted' => $inserted,
    'expires_minutes' => $expiresMinutes
  ]);

  json_ok([
    'meeting_id' => $meetingId,
    'motion_id' => $motionId,
    'generated' => $inserted,
    'tokens' => $listTokens ? $tokensOut : null
  ]);
} catch (Throwable $e) {
  $pdo = db();
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_err('operator_open_vote_failed', 500, ['detail' => $e->getMessage()]);
}

