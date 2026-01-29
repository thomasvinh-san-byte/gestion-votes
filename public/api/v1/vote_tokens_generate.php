<?php
// public/api/v1/vote_tokens_generate.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

api_require_role('operator');

$in = api_request('POST');

$meetingId = trim((string)($in['meeting_id'] ?? ($_GET['meeting_id'] ?? '')));

api_guard_meeting_not_validated($meetingId);

$motionId  = trim((string)($in['motion_id']  ?? ($_GET['motion_id']  ?? '')));

if ($meetingId === '' || !api_is_uuid($meetingId)) api_fail('invalid_meeting_id', 400);
if ($motionId === '' || !api_is_uuid($motionId)) api_fail('invalid_motion_id', 400);

$tenant = DEFAULT_TENANT_ID;

// Validate meeting + motion relation
$meeting = db_select_one(
  "SELECT id, tenant_id, status FROM meetings WHERE tenant_id = ? AND id = ?",
  [$tenant, $meetingId]
);
if (!$meeting) api_fail('meeting_not_found', 404);

$motion = db_select_one(
  "SELECT id, meeting_id, opened_at, closed_at FROM motions WHERE id = ? AND meeting_id = ?",
  [$motionId, $meetingId]
);
if (!$motion) api_fail('motion_not_found', 404);

if ($motion['closed_at'] !== null) api_fail('motion_closed', 409);

// Eligible voters: members active with attendance present/remote for this meeting
$voters = db_select_all(
  "SELECT m.id AS member_id, COALESCE(m.full_name, m.name, m.email, m.id::text) AS member_name
   FROM members m
   JOIN attendances a ON a.member_id = m.id AND a.meeting_id = :meeting_id
   WHERE m.tenant_id = :tenant_id
     AND m.is_active = true
     AND a.mode IN ('present','remote')
   ORDER BY COALESCE(m.full_name, m.name, m.email) ASC",
  [
    ':meeting_id' => $meetingId,
    ':tenant_id'  => $tenant,
  ]
);

$ttlMinutes = (int)($in['ttl_minutes'] ?? 0);
if ($ttlMinutes <= 0) $ttlMinutes = 180; // 3h MVP
$expiresAt = db_select_one("SELECT NOW() + (:mins || ' minutes')::interval AS exp", [':mins' => (string)$ttlMinutes])['exp'] ?? null;
if (!$expiresAt) $expiresAt = (new DateTimeImmutable('+3 hours'))->format(DateTimeInterface::ATOM);

$generated = [];
$createdCount = 0;

foreach ($voters as $v) {
  $raw = api_uuid4();
  $hash = hash_hmac('sha256', $raw, APP_SECRET);

  // Idempotent: keep one token per (member,motion) by deleting previous unused tokens
  db_execute(
    "DELETE FROM vote_tokens
     WHERE meeting_id = :meeting_id AND motion_id = :motion_id AND member_id = :member_id AND used_at IS NULL",
    [
      ':meeting_id' => $meetingId,
      ':motion_id'  => $motionId,
      ':member_id'  => $v['member_id'],
    ]
  );

  db_execute(
    "INSERT INTO vote_tokens(token_hash, meeting_id, member_id, motion_id, expires_at, used_at, created_at)
     VALUES (:h,:meeting_id,:member_id,:motion_id,:exp,NULL,NOW())",
    [
      ':h'         => $hash,
      ':meeting_id'=> $meetingId,
      ':member_id' => $v['member_id'],
      ':motion_id' => $motionId,
      ':exp'       => $expiresAt,
    ]
  );

  $createdCount++;

  $generated[] = [
    'member_id'   => $v['member_id'],
    'member_name' => $v['member_name'],
    'token'       => $raw,
    'url'         => "/vote.php?token=" . $raw,
  ];
}

if (function_exists('audit_log')) {
  audit_log('vote_tokens_generated', 'motion', $motionId, [
    'meeting_id'   => $meetingId,
    'motion_id'    => $motionId,
    'count'        => $createdCount,
    'ttl_minutes'  => $ttlMinutes,
  ]);
}

api_ok([
  'count'      => $createdCount,
  'expires_in' => $ttlMinutes,
  'tokens'     => $generated, // operator-only
]);
