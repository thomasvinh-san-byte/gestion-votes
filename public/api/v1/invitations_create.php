<?php
declare(strict_types=1);

// public/api/v1/invitations_create.php
require __DIR__ . '/../../../app/api.php';

api_require_role('operator');

$input = api_request('POST');

$meetingId = trim((string)($input['meeting_id'] ?? ''));

api_guard_meeting_not_validated($meetingId);

$memberId  = trim((string)($input['member_id'] ?? ''));
$email     = isset($input['email']) ? trim((string)$input['email']) : null;

if ($meetingId === '' || $memberId === '') {
    api_fail('missing_meeting_or_member', 400);
}

// token simple (fonctionnel, non orienté sécurité)
$token = bin2hex(random_bytes(16));

// UPSERT sur (meeting_id, member_id)
db_exec(
    "INSERT INTO invitations (meeting_id, member_id, email, token, status, sent_at, updated_at)
     VALUES (:meeting_id, :member_id, :email, :token, 'sent', now(), now())
     ON CONFLICT (meeting_id, member_id)
     DO UPDATE SET token = EXCLUDED.token,
                   email = COALESCE(EXCLUDED.email, invitations.email),
                   status = 'sent',
                   sent_at = now(),
                   updated_at = now()",
    [
        ':meeting_id' => $meetingId,
        ':member_id'  => $memberId,
        ':email'      => $email,
        ':token'      => $token,
    ]
);

$voteUrl = "/vote.htmx.html?token=" . rawurlencode($token);

api_ok([
    'meeting_id' => $meetingId,
    'member_id' => $memberId,
    'token' => $token,
    'vote_url' => $voteUrl,
]);