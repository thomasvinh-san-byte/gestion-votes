<?php
// public/api/v1/operator_open_vote.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\VoteTokenRepository;

api_require_role('operator');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  api_fail('method_not_allowed', 405);
}

$input = json_decode($GLOBALS['__ag_vote_raw_body'] ?? file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$meetingId = trim((string)($input['meeting_id'] ?? ''));

api_guard_meeting_not_validated($meetingId);

if ($meetingId === '' || !api_is_uuid($meetingId)) {
  api_fail('invalid_meeting_id', 422);
}

$motionId = trim((string)($input['motion_id'] ?? ''));
if ($motionId !== '' && !api_is_uuid($motionId)) {
  api_fail('invalid_motion_id', 422);
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

$meetingRepo = new MeetingRepository();
$motionRepo = new MotionRepository();
$memberRepo = new MemberRepository();
$attendanceRepo = new AttendanceRepository();
$tokenRepo = new VoteTokenRepository();

try {
  db()->beginTransaction();

  // Meeting
  $meeting = $meetingRepo->lockForUpdate($meetingId, api_current_tenant_id());
  if (!$meeting) {
    db()->rollBack();
    api_fail('meeting_not_found', 404);
  }
  if (!empty($meeting['validated_at'])) {
    db()->rollBack();
    api_fail('meeting_validated_locked', 409, ['detail' => "Séance validée : action interdite."]);
  }

  // Force live (pour tests / recette)
  $status = (string)($meeting['status'] ?? '');
  if ($status !== 'live') {
    $meetingRepo->updateFields($meetingId, api_current_tenant_id(), ['status' => 'live']);
  }

  // Motion selection: either provided or next not-opened
  if ($motionId === '') {
    $next = $motionRepo->findNextNotOpenedForUpdate(api_current_tenant_id(), $meetingId);
    if (!$next) {
      db()->rollBack();
      api_fail('no_motion_to_open', 409, ['detail' => "Aucune résolution disponible à ouvrir."]);
    }
    $motionId = (string)$next['id'];
  } else {
    // lock row
    $row = $motionRepo->findByIdAndMeetingForUpdate(api_current_tenant_id(), $meetingId, $motionId);
    if (!$row) {
      db()->rollBack();
      api_fail('motion_not_found', 404);
    }
  }

  // Ensure only one open motion
  $open = $motionRepo->findCurrentOpen($meetingId, api_current_tenant_id());
  if ($open && (string)$open['id'] !== $motionId) {
    db()->rollBack();
    api_fail('another_motion_active', 409, [
      'detail' => "Une résolution est déjà ouverte : clôturez-la avant d'en ouvrir une autre.",
      'open_motion_id' => (string)$open['id'],
    ]);
  }

  // Open motion (idempotent)
  $motionRepo->markOpenedInMeeting(api_current_tenant_id(), $motionId, $meetingId);

  $meetingRepo->updateCurrentMotion($meetingId, api_current_tenant_id(), $motionId);

  // Eligible members: present/remote/proxy; fallback all members (recette)
  // Table canonique: attendances(mode)
  $eligible = $attendanceRepo->listEligibleMemberIds(api_current_tenant_id(), $meetingId);

  if (!$eligible) {
    $eligible = $memberRepo->listByMeetingFallback(api_current_tenant_id(), $meetingId);
  }

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
    $active = $tokenRepo->findActiveForMember(api_current_tenant_id(), $meetingId, $motionId, $memberId);
    if ($active) continue;

    $ok = $tokenRepo->insertWithExpiry($hash, api_current_tenant_id(), $meetingId, $memberId, $motionId, $expiresMinutes);

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

  db()->commit();

  audit_log('vote_tokens_generated', 'motion', $motionId, [
    'meeting_id' => $meetingId,
    'inserted' => $inserted,
    'expires_minutes' => $expiresMinutes
  ]);

  api_ok([
    'meeting_id' => $meetingId,
    'motion_id' => $motionId,
    'generated' => $inserted,
    'tokens' => $listTokens ? $tokensOut : null
  ]);
} catch (Throwable $e) {
  if (db()->inTransaction()) db()->rollBack();
  api_fail('operator_open_vote_failed', 500, ['detail' => $e->getMessage()]);
}
