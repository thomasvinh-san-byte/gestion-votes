<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\EmailEventRepository;
use AgVote\Repository\EmailQueueRepository;
use AgVote\Repository\InvitationRepository;

/**
 * Consolidates 4 invitation endpoints.
 */
final class InvitationsController extends AbstractController
{
    public function create(): void
    {
        $input = api_request('POST');

        $meetingId = trim((string)($input['meeting_id'] ?? ''));
        $memberId = trim((string)($input['member_id'] ?? ''));
        $email = isset($input['email']) ? trim((string)$input['email']) : null;

        if ($meetingId === '' || $memberId === '') {
            api_fail('missing_meeting_or_member', 400, [
                'detail' => 'meeting_id et member_id sont requis.',
            ]);
        }
        if (!api_is_uuid($meetingId)) {
            api_fail('invalid_meeting_id', 422, ['detail' => 'meeting_id doit être un UUID valide.']);
        }
        if (!api_is_uuid($memberId)) {
            api_fail('invalid_member_id', 422, ['detail' => 'member_id doit être un UUID valide.']);
        }
        if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            api_fail('invalid_email', 422, ['detail' => 'Format d\'email invalide.']);
        }

        api_guard_meeting_not_validated($meetingId);

        $token = bin2hex(random_bytes(32));
        $tenantId = api_current_tenant_id();

        (new InvitationRepository())->upsertSent($tenantId, $meetingId, $memberId, $email, $token);

        audit_log('invitation.create', 'invitation', $memberId, [
            'meeting_id' => $meetingId,
            'email' => $email,
        ], $meetingId);

        $voteUrl = "/vote.htmx.html?token=" . rawurlencode($token);

        api_ok([
            'meeting_id' => $meetingId,
            'member_id' => $memberId,
            'token' => $token,
            'vote_url' => $voteUrl,
        ]);
    }

    public function listForMeeting(): void
    {
        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }

        $rows = (new InvitationRepository())->listForMeeting($meetingId, api_current_tenant_id());
        api_ok(['invitations' => $rows]);
    }

    public function redeem(): void
    {
        $token = api_query('token');
        if ($token === '') {
            api_fail('missing_token', 400);
        }

        $repo = new InvitationRepository();
        $inv = $repo->findByToken($token);
        if (!$inv) {
            api_fail('invalid_token', 404);
        }

        // Tenant isolation: invitation must belong to current tenant
        if ((string)($inv['tenant_id'] ?? '') !== api_current_tenant_id()) {
            api_fail('invalid_token', 404);
        }

        $status = (string)$inv['status'];
        if ($status === 'declined' || $status === 'bounced') {
            api_fail('token_not_usable', 400, ['status' => $status]);
        }

        $invId = (string)$inv['id'];
        $invTenant = (string)($inv['tenant_id'] ?? '');
        if ($status === 'pending' || $status === 'sent') {
            $repo->markOpened($invId, $invTenant);
        }
        $repo->markAccepted($invId, $invTenant);

        audit_log('invitation.redeemed', 'invitation', $invId, [
            'meeting_id' => (string)$inv['meeting_id'],
            'member_id' => (string)$inv['member_id'],
        ], (string)$inv['meeting_id']);

        api_ok([
            'meeting_id' => (string)$inv['meeting_id'],
            'member_id' => (string)$inv['member_id'],
            'status' => 'accepted',
        ]);
    }

    public function stats(): void
    {
        $input = api_request('GET');

        $meetingId = trim((string)($input['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }

        $tenantId = api_current_tenant_id();
        api_guard_meeting_exists($meetingId);

        $invitationRepo = new InvitationRepository();
        $queueRepo = new EmailQueueRepository();
        $eventRepo = new EmailEventRepository();

        $invitationStats = $invitationRepo->getStatsForMeeting($meetingId, $tenantId);

        $queueStats = $queueRepo->countByStatusForMeeting($meetingId);
        $queueByStatus = [];
        foreach ($queueStats as $row) {
            $queueByStatus[$row['status']] = (int)$row['count'];
        }

        $recentEvents = $eventRepo->countByTypeForMeeting($meetingId);
        $eventsByType = [];
        foreach ($recentEvents as $row) {
            $eventsByType[$row['event_type']] = (int)$row['count'];
        }

        $total = (int)$invitationStats['total'];
        $sent = (int)$invitationStats['sent'];
        $opened = (int)$invitationStats['opened'];
        $bounced = (int)$invitationStats['bounced'];
        $accepted = (int)$invitationStats['accepted'];

        $openRate = ($sent + $opened + $accepted) > 0
            ? round(($opened + $accepted) / ($sent + $opened + $accepted) * 100, 1)
            : 0;
        $bounceRate = ($sent + $bounced) > 0
            ? round($bounced / ($sent + $bounced) * 100, 1)
            : 0;
        $acceptRate = ($sent + $opened + $accepted) > 0
            ? round($accepted / ($sent + $opened + $accepted) * 100, 1)
            : 0;

        api_ok([
            'meeting_id' => $meetingId,
            'invitations' => [
                'total' => $total,
                'pending' => (int)$invitationStats['pending'],
                'sent' => $sent,
                'opened' => $opened,
                'accepted' => $accepted,
                'declined' => (int)$invitationStats['declined'],
                'bounced' => $bounced,
            ],
            'engagement' => [
                'total_opens' => (int)$invitationStats['total_opens'],
                'total_clicks' => (int)$invitationStats['total_clicks'],
                'open_rate' => $openRate,
                'bounce_rate' => $bounceRate,
                'accept_rate' => $acceptRate,
            ],
            'queue' => $queueByStatus,
            'events' => $eventsByType,
        ]);
    }
}
