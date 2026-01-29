<?php
declare(strict_types=1);

// public/api/v1/invitations_redeem.php
require __DIR__ . '/../../../app/api.php';

api_require_role('public');

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') {
    api_fail('missing_token', 400);
}

$inv = db_one(
    "SELECT id, meeting_id, member_id, status
     FROM invitations
     WHERE token = :token",
    [':token' => $token]
);
if (!$inv) {
    api_fail('invalid_token', 404);
}

$status = (string)$inv['status'];
if ($status === 'declined' || $status === 'bounced') {
    api_fail('token_not_usable', 400, ['status' => $status]);
}

// Marquer comme ouvert (best-effort)
db_exec(
    "UPDATE invitations
     SET status = CASE WHEN status IN ('pending','sent') THEN 'opened' ELSE status END,
         updated_at = now()
     WHERE id = :id",
    [':id' => (string)$inv['id']]
);

api_ok([
    'meeting_id' => (string)$inv['meeting_id'],
    'member_id' => (string)$inv['member_id'],
    'status' => $status,
]);