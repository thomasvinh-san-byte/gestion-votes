<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

final class InvitationsService
{
    public static function listForMeeting(string $meetingId, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);

        return db_select_all(<<<'SQL'
            SELECT
              i.id,
              i.meeting_id,
              i.member_id,
              m.full_name AS member_name,
              i.email,
              i.token,
              i.status,
              i.sent_at,
              i.responded_at,
              i.created_at,
              i.updated_at
            FROM meetings me
            JOIN invitations i ON i.meeting_id = me.id
            JOIN members m ON m.id = i.member_id AND m.tenant_id = me.tenant_id
            WHERE me.id = :meeting_id
              AND me.tenant_id = :tenant_id
            ORDER BY m.full_name ASC
        SQL, [
            ':meeting_id' => $meetingId,
            ':tenant_id'  => $tenantId,
        ]);
    }

    /**
     * Crée ou régénère un token pour (meeting_id, member_id).
     * Retour: invitation row (id, token, status).
     */
    public static function createOrRotate(string $meetingId, string $memberId, ?string $email = null, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);

        $email = $email !== null ? trim($email) : null;
        if ($email === '') $email = null;

        // Check tenant coherence
        $ok = (int)(db_scalar(<<<'SQL'
            SELECT count(*)
            FROM meetings me
            JOIN members m ON m.tenant_id = me.tenant_id
            WHERE me.id = :meeting_id
              AND me.tenant_id = :tenant_id
              AND m.id = :member_id
        SQL, [
            ':meeting_id' => $meetingId,
            ':tenant_id'  => $tenantId,
            ':member_id'  => $memberId,
        ]) ?? 0);

        if ($ok !== 1) {
            throw new InvalidArgumentException('meeting_id/member_id invalide pour ce tenant');
        }

        $token = bin2hex(random_bytes(16));

        db_exec(<<<'SQL'
            INSERT INTO invitations (tenant_id, meeting_id, member_id, email, token, status, updated_at)
            VALUES (:tenant_id, :meeting_id, :member_id, :email, :token, 'pending', now())
            ON CONFLICT (tenant_id, meeting_id, member_id)
            DO UPDATE SET
              email = EXCLUDED.email,
              token = EXCLUDED.token,
              status = 'pending',
              updated_at = now(),
              responded_at = NULL
        SQL, [
            ':tenant_id'  => $tenantId,
            ':meeting_id' => $meetingId,
            ':member_id'  => $memberId,
            ':email'      => $email,
            ':token'      => $token,
        ]);

        $row = db_select_one(
            "SELECT id, meeting_id, member_id, email, token, status, created_at, updated_at FROM invitations WHERE meeting_id = :meeting_id AND member_id = :member_id",
            [':meeting_id' => $meetingId, ':member_id' => $memberId]
        );

        if (!$row) {
            throw new RuntimeException('invitation_create_failed');
        }
        return $row;
    }

    /**
     * Consomme un token et retourne meeting_id + member_id (usage public).
     * Note: ne gère pas de limitation "1 usage" ici ; c'est volontairement MVP.
     */
    public static function redeem(string $token): array
    {
        $token = trim($token);
        if ($token === '') throw new InvalidArgumentException('token manquant');

        $row = db_select_one(<<<'SQL'
            SELECT
              i.id,
              i.meeting_id,
              i.member_id,
              i.status
            FROM invitations i
            WHERE i.token = :token
            LIMIT 1
        SQL, [':token' => $token]);

        if (!$row) throw new RuntimeException('token_invalide');

        // Marque "accepted" best-effort (ne bloque pas le flux si déjà fait)
        db_exec(
            "UPDATE invitations SET status = 'accepted', responded_at = coalesce(responded_at, now()), updated_at = now() WHERE id = :id",
            [':id' => $row['id']]
        );

        return [
            'meeting_id' => (string)$row['meeting_id'],
            'member_id'  => (string)$row['member_id'],
        ];
    }
}
