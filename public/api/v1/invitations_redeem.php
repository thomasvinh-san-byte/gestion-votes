<?php
declare(strict_types=1);

// public/api/v1/invitations_redeem.php
require __DIR__ . '/../../../app/api.php';
use AgVote\Repository\InvitationRepository;

api_require_role('public');

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') {
    api_fail('missing_token', 400);
}

$inv = (new InvitationRepository())->findByToken($token);
if (!$inv) {
    api_fail('invalid_token', 404);
}

$status = (string)$inv['status'];
if ($status === 'declined' || $status === 'bounced') {
    api_fail('token_not_usable', 400, ['status' => $status]);
}

$repo = new InvitationRepository();
$invId = (string)$inv['id'];

// Marquer comme ouvert si c'est la premiere fois, puis accepte
if ($status === 'pending' || $status === 'sent') {
    $repo->markOpened($invId);
}
$repo->markAccepted($invId);

audit_log('invitation.redeemed', 'invitation', $invId, [
    'meeting_id' => (string)$inv['meeting_id'],
    'member_id'  => (string)$inv['member_id'],
], (string)$inv['meeting_id']);

api_ok([
    'meeting_id' => (string)$inv['meeting_id'],
    'member_id' => (string)$inv['member_id'],
    'status' => 'accepted',
]);