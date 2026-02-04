<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\ProxyRepository;
use InvalidArgumentException;

/**
 * Service metier pour les procurations.
 *
 * Contient la logique metier (validations, chaines, plafonds).
 * Delegue tout l'acces donnees a ProxyRepository.
 *
 * ## Regles de validation des procurations
 *
 * 1. **Anti-auto-delegation** : Un membre ne peut pas se donner procuration a lui-meme.
 *
 * 2. **Coherence tenant** : Le mandant (giver), le mandataire (receiver) et la seance
 *    doivent appartenir au meme tenant.
 *
 * 3. **Pas de chaines de procurations** : Si B a deja donne sa procuration a C,
 *    alors A ne peut pas donner sa procuration a B. Cela evite les chaines A->B->C
 *    qui permettraient a une seule personne d'accumuler trop de pouvoir de vote.
 *    Erreur: "Chaine de procuration interdite (le mandataire delegue deja)."
 *
 * 4. **Plafond par mandataire** : Un mandataire ne peut recevoir qu'un nombre
 *    limite de procurations (defaut: 99, configurable via PROXY_MAX_PER_RECEIVER).
 *    Erreur: "Plafond procurations atteint (max N)."
 *
 * ## Exemples
 *
 * - A donne procuration a B : OK (si B n'a pas delegue)
 * - B donne procuration a C : OK
 * - A veut donner a B apres que B ait delegue a C : REFUSE (chaine interdite)
 * - A revoque sa procuration : OK (receiver_member_id vide)
 *
 * @see PROXY_MAX_PER_RECEIVER Variable d'environnement pour le plafond
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
     *
     * Si $receiverMemberId est vide => revocation de la procuration existante.
     *
     * Validations effectuees:
     * - giver_member_id doit etre fourni
     * - giver != receiver (pas d'auto-delegation)
     * - seance et membres doivent appartenir au meme tenant
     * - pas de chaine: le receiver ne doit pas deja avoir delegue (voir doc classe)
     * - plafond: le receiver ne peut pas depasser PROXY_MAX_PER_RECEIVER procurations
     *
     * @param string $meetingId ID de la seance
     * @param string $giverMemberId ID du mandant (celui qui donne sa procuration)
     * @param string $receiverMemberId ID du mandataire (celui qui recoit), ou vide pour revoquer
     * @param string|null $tenantId ID du tenant (auto-detecte si null)
     * @throws InvalidArgumentException Si validation echoue
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

        // Anti-chaines de procuration: si le receiver a deja donne sa procuration
        // a quelqu'un d'autre, on ne peut pas lui donner de procuration supplementaire.
        // Cela empeche les chaines transitives A->B->C qui concentreraient le pouvoir.
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
