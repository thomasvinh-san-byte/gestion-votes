<?php
declare(strict_types=1);

// public/api/v1/invitations_create.php
require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\InvitationRepository;

api_require_role('operator');

$input = api_request('POST');

$meetingId = trim((string)($input['meeting_id'] ?? ''));
$memberId  = trim((string)($input['member_id'] ?? ''));
$email     = isset($input['email']) ? trim((string)$input['email']) : null;

// Validate required fields
if ($meetingId === '' || $memberId === '') {
    api_fail('missing_meeting_or_member', 400, [
        'detail' => 'meeting_id et member_id sont requis.',
    ]);
}

// Validate UUIDs
if (!api_is_uuid($meetingId)) {
    api_fail('invalid_meeting_id', 422, [
        'detail' => 'meeting_id doit être un UUID valide.',
    ]);
}

if (!api_is_uuid($memberId)) {
    api_fail('invalid_member_id', 422, [
        'detail' => 'member_id doit être un UUID valide.',
    ]);
}

// Validate email format if provided
if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_fail('invalid_email', 422, [
        'detail' => 'Format d\'email invalide.',
    ]);
}

api_guard_meeting_not_validated($meetingId);

// Generate secure token (32 bytes = 256 bits)
$token = bin2hex(random_bytes(32));
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
