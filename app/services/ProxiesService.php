<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\ProxyRepository;

/**
 * Service metier pour les procurations.
 *
 * Contient la logique metier (validations, chaines, plafonds).
 * Delegue tout l'acces donnees a ProxyRepository.
 */
final class ProxiesService
{
    public static function listForMeeting(string $meetingId, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);
        $repo = new ProxyRepository();
        return $repo->listForMeeting($meetingId, $tenantId);
    }

    /**
     * Cree ou remplace la procuration pour un mandant (giver) dans la seance.
     * Si $receiverMemberId est vide => revocation.
     */
    public static function upsert(string $meetingId, string $giverMemberId, string $receiverMemberId, ?string $tenantId = null): void
    {
        $tenantId = $tenantId ?: (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);
        $maxPerReceiver = (int)(getenv('PROXY_MAX_PER_RECEIVER') ?: 99);
        $repo = new ProxyRepository();

        if ($giverMemberId === '') {
            throw new InvalidArgumentException('giver_member_id manquant');
        }
        if ($giverMemberId === $receiverMemberId && $receiverMemberId !== '') {
            throw new InvalidArgumentException('giver != receiver');
        }

        // Check tenant coherence (meeting + giver member)
        if ($repo->countTenantCoherence($meetingId, $tenantId, $giverMemberId) !== 1) {
            throw new InvalidArgumentException('meeting_id/giver_member_id invalide pour ce tenant');
        }

        // Revocation si receiver vide
        if (trim($receiverMemberId) === '') {
            $repo->revokeForGiver($meetingId, $giverMemberId);
            return;
        }

        // Receiver must belong to tenant
        if ($repo->countTenantCoherence($meetingId, $tenantId, $receiverMemberId) !== 1) {
            throw new InvalidArgumentException('receiver_member_id invalide pour ce tenant');
        }

        // No proxy chains: receiver cannot itself delegate in this meeting (active)
        if ($repo->countActiveAsGiver($meetingId, $receiverMemberId) > 0) {
            throw new InvalidArgumentException('Chaîne de procuration interdite (le mandataire délègue déjà).');
        }

        // Cap: max active proxies per receiver
        if ($repo->countActiveAsReceiver($meetingId, $receiverMemberId) >= $maxPerReceiver) {
            throw new InvalidArgumentException("Plafond procurations atteint (max {$maxPerReceiver}).");
        }

        $repo->upsertProxy($tenantId, $meetingId, $giverMemberId, $receiverMemberId);
    }

    /**
     * Revoque toutes les procurations actives d'un mandant.
     */
    public static function revoke(string $meetingId, string $giverMemberId): void
    {
        $repo = new ProxyRepository();
        $repo->revokeForGiver($meetingId, $giverMemberId);
    }

    /**
     * Verifie si une procuration active existe entre un mandant et un mandataire.
     */
    public static function hasActiveProxy(string $meetingId, string $giverMemberId, string $receiverMemberId): bool
    {
        $repo = new ProxyRepository();
        return $repo->hasActiveProxy($meetingId, $giverMemberId, $receiverMemberId);
    }
}
