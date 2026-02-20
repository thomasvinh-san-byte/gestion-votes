<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\ProxyRepository;
use InvalidArgumentException;

/**
 * Business service for proxies.
 *
 * Contains business logic (validations, chains, caps).
 * Delegates all data access to ProxyRepository.
 *
 * ## Proxy validation rules
 *
 * 1. **Anti-self-delegation**: A member cannot give proxy to themselves.
 *
 * 2. **Tenant coherence**: The giver, receiver, and meeting
 *    must belong to the same tenant.
 *
 * 3. **No proxy chains**: If B has already given proxy to C,
 *    then A cannot give proxy to B. This prevents A->B->C chains
 *    that would allow one person to accumulate too much voting power.
 *    Error: "Proxy chain forbidden (receiver already delegates)."
 *
 * 4. **Cap per receiver**: A receiver can only receive a limited
 *    number of proxies (default: 99, configurable via PROXY_MAX_PER_RECEIVER).
 *    Error: "Proxy cap reached (max N)."
 *
 * ## Examples
 *
 * - A gives proxy to B: OK (if B has not delegated)
 * - B gives proxy to C: OK
 * - A wants to give to B after B delegated to C: REFUSED (chain forbidden)
 * - A revokes their proxy: OK (receiver_member_id empty)
 *
 * @see PROXY_MAX_PER_RECEIVER Environment variable for the cap
 */
final class ProxiesService
{
    public static function listForMeeting(string $meetingId, string $tenantId): array
    {
        $repo = new ProxyRepository();
        return $repo->listForMeeting($meetingId, $tenantId);
    }

    /**
     * Creates or replaces proxy for a giver in the meeting.
     *
     * If $receiverMemberId is empty => revokes existing proxy.
     *
     * Validations performed:
     * - giver_member_id must be provided
     * - giver != receiver (no self-delegation)
     * - meeting and members must belong to the same tenant
     * - no chain: receiver must not already have delegated (see class doc)
     * - cap: receiver cannot exceed PROXY_MAX_PER_RECEIVER proxies
     *
     * @param string $meetingId Meeting ID
     * @param string $giverMemberId Giver ID (the one giving their proxy)
     * @param string $receiverMemberId Receiver ID (the one receiving), or empty to revoke
     * @param string|null $tenantId Tenant ID (auto-detected if null)
     * @throws InvalidArgumentException If validation fails
     */
    public static function upsert(string $meetingId, string $giverMemberId, string $receiverMemberId, string $tenantId): void
    {
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

        // Revoke if receiver is empty
        if (trim($receiverMemberId) === '') {
            $repo->revokeForGiver($meetingId, $giverMemberId);
            return;
        }

        // Receiver must belong to tenant
        if ($repo->countTenantCoherence($meetingId, $tenantId, $receiverMemberId) !== 1) {
            throw new InvalidArgumentException('receiver_member_id invalide pour ce tenant');
        }

        // Wrap chain check + cap check + upsert in a transaction with row-level
        // locking to prevent TOCTOU race conditions on concurrent proxy creation.
        $pdo = \db();
        $pdo->beginTransaction();
        try {
            // Anti-proxy-chain: lock and check if receiver already delegates
            if ($repo->countActiveAsGiverForUpdate($meetingId, $receiverMemberId) > 0) {
                $pdo->rollBack();
                throw new InvalidArgumentException('Chaîne de procuration interdite (le mandataire délègue déjà).');
            }

            // Cap: lock and check max active proxies per receiver
            if ($repo->countActiveAsReceiverForUpdate($meetingId, $receiverMemberId) >= $maxPerReceiver) {
                $pdo->rollBack();
                throw new InvalidArgumentException("Plafond procurations atteint (max {$maxPerReceiver}).");
            }

            $repo->upsertProxy($tenantId, $meetingId, $giverMemberId, $receiverMemberId);
            $pdo->commit();
        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Revokes all active proxies from a giver.
     */
    public static function revoke(string $meetingId, string $giverMemberId): void
    {
        $repo = new ProxyRepository();
        $repo->revokeForGiver($meetingId, $giverMemberId);
    }

    /**
     * Checks if an active proxy exists between a giver and receiver.
     */
    public static function hasActiveProxy(string $meetingId, string $giverMemberId, string $receiverMemberId): bool
    {
        $repo = new ProxyRepository();
        return $repo->hasActiveProxy($meetingId, $giverMemberId, $receiverMemberId);
    }
}
