<?php
declare(strict_types=1);

// public/api/v1/invitations_create.php
require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\InvitationRepository;

api_require_role('operator');

$input = api_request('POST');

$meetingId = trim((string)($input['meeting_id'] ?? ''));

api_guard_meeting_not_validated($meetingId);

$memberId  = trim((string)($input['member_id'] ?? ''));
$email     = isset($input['email']) ? trim((string)$input['email']) : null;

if ($meetingId === '' || $memberId === '') {
    api_fail('missing_meeting_or_member', 400);
}

$token = bin2hex(random_bytes(16));
$tenantId = api_current_tenant_id();

$repo = new InvitationRepository();
$repo->upsertSent($tenantId, $meetingId, $memberId, $email, $token);

$voteUrl = "/vote.htmx.html?token=" . rawurlencode($token);

api_ok([
    'meeting_id' => $meetingId,
    'member_id' => $memberId,
    'token' => $token,
    'vote_url' => $voteUrl,
]);
