<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

final class ProxiesService
{
    public static function listForMeeting(string $meetingId, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);

$maxPerReceiver = (int)(getenv('PROXY_MAX_PER_RECEIVER') ?: 99);

        return db_select_all(<<<'SQL'
            SELECT
              p.id,
              p.meeting_id,
              p.giver_member_id,
              g.name AS giver_name,
              p.receiver_member_id,
              r.name AS receiver_name,
              p.scope,
              p.created_at,
              p.revoked_at
            FROM meetings me
            JOIN proxies p ON p.meeting_id = me.id
            JOIN members g ON g.id = p.giver_member_id
            JOIN members r ON r.id = p.receiver_member_id
            WHERE me.id = :meeting_id
              AND me.tenant_id = :tenant_id
              AND g.tenant_id = me.tenant_id
              AND r.tenant_id = me.tenant_id
            ORDER BY g.name ASC
        SQL, [
            ':meeting_id' => $meetingId,
            ':tenant_id'  => $tenantId,
        ]);
    }

    /**
     * Crée ou remplace la procuration pour un mandant (giver) dans la séance.
     * Si $receiverMemberId est vide => révocation.
     */
    public static function upsert(string $meetingId, string $giverMemberId, string $receiverMemberId, ?string $tenantId = null): void
    {
        $tenantId = $tenantId ?: (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);

        if ($giverMemberId === '') {
            throw new InvalidArgumentException('giver_member_id manquant');
        }
        if ($giverMemberId === $receiverMemberId && $receiverMemberId !== '') {
            throw new InvalidArgumentException('giver != receiver');
        }

        // Check tenant coherence (meeting + members)
        $ok = (int)(db_scalar(<<<'SQL'
            SELECT count(*)
            FROM meetings me
            JOIN members g ON g.tenant_id = me.tenant_id
            WHERE me.id = :meeting_id
              AND me.tenant_id = :tenant_id
              AND g.id = :giver
        SQL, [
            ':meeting_id' => $meetingId,
            ':tenant_id'  => $tenantId,
            ':giver'      => $giverMemberId,
        ]) ?? 0);

        if ($ok !== 1) {
            throw new InvalidArgumentException('meeting_id/giver_member_id invalide pour ce tenant');
        }

if (trim($receiverMemberId) !== '') {
    // Receiver must belong to tenant
    $ok2 = (int)(db_scalar(<<<'SQL'
        SELECT count(*)
        FROM meetings me
        JOIN members r ON r.tenant_id = me.tenant_id
        WHERE me.id = :meeting_id AND me.tenant_id = :tenant_id AND r.id = :receiver
    SQL, [
        ':meeting_id' => $meetingId,
        ':tenant_id'  => $tenantId,
        ':receiver'   => $receiverMemberId,
    ]) ?? 0);
    if ($ok2 !== 1) {
        throw new InvalidArgumentException('receiver_member_id invalide pour ce tenant');
    }

    // No proxy chains: receiver cannot itself delegate in this meeting (active)
    $chain = (int)(db_scalar(
        "SELECT count(*) FROM proxies WHERE meeting_id = :meeting_id AND giver_member_id = :receiver AND revoked_at IS NULL",
        [':meeting_id'=>$meetingId, ':receiver'=>$receiverMemberId]
    ) ?? 0);
    if ($chain > 0) {
        throw new InvalidArgumentException('Chaîne de procuration interdite (le mandataire délègue déjà).');
    }

    // Cap: max active proxies per receiver
    $cur = (int)(db_scalar(
        "SELECT count(*) FROM proxies WHERE meeting_id = :meeting_id AND receiver_member_id = :receiver AND revoked_at IS NULL",
        [':meeting_id'=>$meetingId, ':receiver'=>$receiverMemberId]
    ) ?? 0);
    if ($cur >= $maxPerReceiver) {
        throw new InvalidArgumentException("Plafond procurations atteint (max {$maxPerReceiver}).");
    }
}

        if (trim($receiverMemberId) === '') {
            db_exec(
                "UPDATE proxies SET revoked_at = now() WHERE meeting_id = :meeting_id AND giver_member_id = :giver AND revoked_at IS NULL",
                [':meeting_id' => $meetingId, ':giver' => $giverMemberId]
            );
            return;
        }

        $ok2 = (int)(db_scalar(<<<'SQL'
            SELECT count(*)
            FROM meetings me
            JOIN members r ON r.tenant_id = me.tenant_id
            WHERE me.id = :meeting_id
              AND me.tenant_id = :tenant_id
              AND r.id = :receiver
        SQL, [
            ':meeting_id' => $meetingId,
            ':tenant_id'  => $tenantId,
            ':receiver'   => $receiverMemberId,
        ]) ?? 0);

        if ($ok2 !== 1) {
            throw new InvalidArgumentException('receiver_member_id invalide pour ce tenant');
        }

        db_exec(<<<'SQL'
            INSERT INTO proxies (meeting_id, giver_member_id, receiver_member_id, scope)
            VALUES (:meeting_id, :giver, :receiver, 'full')
            ON CONFLICT (meeting_id, giver_member_id)
            DO UPDATE SET
              receiver_member_id = EXCLUDED.receiver_member_id,
              scope = 'full',
              revoked_at = NULL
        SQL, [
            ':meeting_id' => $meetingId,
            ':giver'      => $giverMemberId,
            ':receiver'   => $receiverMemberId,
        ]);
    }
}
