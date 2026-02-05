<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\InvitationRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Service metier pour les invitations.
 *
 * Delegue tout l'acces donnees a InvitationRepository.
 */
final class InvitationsService
{
    public static function listForMeeting(string $meetingId, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);
        $repo = new InvitationRepository();
        return $repo->listForMeeting($meetingId, $tenantId);
    }

    /**
     * Cree ou regenere un token pour (meeting_id, member_id).
     * Retour: invitation row (id, token, status).
     */
    public static function createOrRotate(string $meetingId, string $memberId, ?string $email = null, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);
        $repo = new InvitationRepository();

        $email = $email !== null ? trim($email) : null;
        if ($email === '') $email = null;

        // Check tenant coherence
        if ($repo->countTenantCoherence($meetingId, $tenantId, $memberId) !== 1) {
            throw new InvalidArgumentException('meeting_id/member_id invalide pour ce tenant');
        }

        $token = bin2hex(random_bytes(16));

        $repo->upsert($tenantId, $meetingId, $memberId, $email, $token);

        $row = $repo->findByMeetingAndMember($meetingId, $memberId);

        if (!$row) {
            throw new RuntimeException('invitation_create_failed');
        }
        return $row;
    }

    /**
     * Consomme un token et retourne meeting_id + member_id (usage public).
     */
    public static function redeem(string $token): array
    {
        $token = trim($token);
        if ($token === '') throw new InvalidArgumentException('token manquant');

        $repo = new InvitationRepository();

        $row = $repo->findByToken($token);
        if (!$row) throw new RuntimeException('token_invalide');

        // Marque "accepted" best-effort
        $repo->markAccepted((string)$row['id']);

        return [
            'meeting_id' => (string)$row['meeting_id'],
            'member_id'  => (string)$row['member_id'],
        ];
    }
}
