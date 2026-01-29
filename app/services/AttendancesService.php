<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/MembersService.php';

/**
 * Gestion des présences (check-in) par séance.
 *
 * Objectif MVP:
 * - Un membre est "présent" si une ligne attendances existe (mode present/remote/proxy)
 * - Un membre est "absent" si aucune ligne n'existe
 * - effective_power est figé au moment du check-in (voting_power du membre)
 */
final class AttendancesService
{
    /**
     * Indique si un membre est "présent" (mode present/remote/proxy, non checked_out) pour une séance.
     *
     * Utilisé pour l'éligibilité au vote (public).
     */
    public static function isPresent(string $meetingId, string $memberId, ?string $tenantId = null): bool
    {
        $tenantId = $tenantId ?: (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);
        $meetingId = trim($meetingId);
        $memberId = trim($memberId);
        if ($meetingId === '' || $memberId === '') {
            return false;
        }

        global $pdo;
        $sql = "
            SELECT 1
            FROM attendances a
            JOIN meetings mt ON mt.id = a.meeting_id
            WHERE a.meeting_id = :meeting_id
              AND a.member_id = :member_id
              AND mt.tenant_id = :tenant_id
              AND a.checked_out_at IS NULL
              AND a.mode IN ('present','remote','proxy')
            LIMIT 1
        ";
        $st = $pdo->prepare($sql);
        $st->execute([
            ':meeting_id' => $meetingId,
            ':member_id' => $memberId,
            ':tenant_id' => $tenantId,
        ]);
        return (bool)$st->fetchColumn();
    }

    /**
     * Indique si un membre est présent "directement" (present/remote uniquement).
     *
     * Recommandé pour contrôler l'éligibilité au vote afin d'éviter les chaînes de procuration.
     */
    public static function isPresentDirect(string $meetingId, string $memberId, ?string $tenantId = null): bool
    {
        $tenantId = $tenantId ?: (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);
        $meetingId = trim($meetingId);
        $memberId = trim($memberId);
        if ($meetingId === '' || $memberId === '') {
            return false;
        }

        global $pdo;
        $sql = "
            SELECT 1
            FROM attendances a
            JOIN meetings mt ON mt.id = a.meeting_id
            WHERE a.meeting_id = :meeting_id
              AND a.member_id = :member_id
              AND mt.tenant_id = :tenant_id
              AND a.checked_out_at IS NULL
              AND a.mode IN ('present','remote')
            LIMIT 1
        ";
        $st = $pdo->prepare($sql);
        $st->execute([
            ':meeting_id' => $meetingId,
            ':member_id' => $memberId,
            ':tenant_id' => $tenantId,
        ]);
        return (bool)$st->fetchColumn();
    }

    /**
     * Liste les présences d'une séance avec infos membre.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function listForMeeting(string $meetingId, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);
        $meetingId = trim($meetingId);
        if ($meetingId === '') {
            throw new InvalidArgumentException('meeting_id est obligatoire');
        }

        global $pdo;
        $sql = "
            SELECT
              a.id,
              a.meeting_id,
              a.member_id,
              a.mode,
              a.checked_in_at,
              a.checked_out_at,
              a.effective_power,
              a.notes,
              m.full_name,
              m.email,
              m.role,
              m.voting_power
            FROM attendances a
            JOIN members m ON m.id = a.member_id
            JOIN meetings mt ON mt.id = a.meeting_id
            WHERE a.meeting_id = :meeting_id
              AND mt.tenant_id = :tenant_id
            ORDER BY m.full_name ASC
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':meeting_id' => $meetingId, ':tenant_id' => $tenantId]);
        return $st->fetchAll() ?: [];
    }

    /**
     * Résumé (nb + poids) de la séance pour les modes présents.
     *
     * @return array{present_count:int,present_weight:float}
     */
    public static function summaryForMeeting(string $meetingId, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);
        $meetingId = trim($meetingId);
        if ($meetingId === '') {
            throw new InvalidArgumentException('meeting_id est obligatoire');
        }

        global $pdo;
        $sql = "
            SELECT
              COUNT(*)::int AS present_count,
              COALESCE(SUM(a.effective_power), 0)::float8 AS present_weight
            FROM attendances a
            JOIN meetings mt ON mt.id = a.meeting_id
            WHERE a.meeting_id = :meeting_id
              AND mt.tenant_id = :tenant_id
              AND a.checked_out_at IS NULL
              AND a.mode IN ('present','remote','proxy')
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':meeting_id' => $meetingId, ':tenant_id' => $tenantId]);
        $row = $st->fetch() ?: ['present_count' => 0, 'present_weight' => 0.0];

        return [
            'present_count' => (int)$row['present_count'],
            'present_weight' => (float)$row['present_weight'],
        ];
    }

    /**
     * Upsert présence.
     * - mode: present|remote|proxy => upsert actif (checked_out_at NULL)
     * - mode: absent => suppression (MVP)
     *
     * @return array<string,mixed> ligne présence (ou {deleted:true})
     */
    public static function upsert(string $meetingId, string $memberId, string $mode, ?string $notes = null): array
    {
        $meetingId = trim($meetingId);
        $memberId = trim($memberId);
        $mode = trim($mode);

        if ($meetingId === '' || $memberId === '') {
            throw new InvalidArgumentException('meeting_id et member_id sont obligatoires');
        }

        $allowed = ['present','remote','proxy','absent'];
        if (!in_array($mode, $allowed, true)) {
            throw new InvalidArgumentException("mode invalide (present/remote/proxy/absent)");
        }

        $tenantId = (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);

        // Vérifier meeting appartient au tenant
        $meeting = db_select_one(
            "SELECT id, tenant_id, status FROM meetings WHERE id = :id",
            [':id' => $meetingId]
        );
        if (!$meeting) {
            throw new RuntimeException('Séance introuvable');
        }
        if ((string)$meeting['tenant_id'] !== $tenantId) {
            throw new RuntimeException('Séance hors tenant');
        }
        if ((string)$meeting['status'] === 'archived') {
            throw new RuntimeException('Séance archivée : présence non modifiable');
        }

        // Charger membre + pouvoir de vote
        $member = MembersService::getMember($memberId);
        if ((string)$member['tenant_id'] !== $tenantId) {
            throw new RuntimeException('Membre hors tenant');
        }

        global $pdo;

        if ($mode === 'absent') {
            $st = $pdo->prepare("DELETE FROM attendances WHERE meeting_id = :meeting_id AND member_id = :member_id");
            $st->execute([':meeting_id' => $meetingId, ':member_id' => $memberId]);
            return ['deleted' => true, 'meeting_id' => $meetingId, 'member_id' => $memberId];
        }

        $effective = (float)($member['voting_power'] ?? $member['vote_weight'] ?? 1.0);

        $sql = "
            INSERT INTO attendances (meeting_id, member_id, mode, checked_in_at, checked_out_at, effective_power, notes)
            VALUES (:meeting_id, :member_id, :mode, now(), NULL, :effective_power, :notes)
            ON CONFLICT (meeting_id, member_id) DO UPDATE SET
              mode = EXCLUDED.mode,
              checked_in_at = now(),
              checked_out_at = NULL,
              effective_power = EXCLUDED.effective_power,
              notes = EXCLUDED.notes
            RETURNING id, meeting_id, member_id, mode, checked_in_at, checked_out_at, effective_power, notes
        ";
        $st = $pdo->prepare($sql);
        $st->execute([
            ':meeting_id' => $meetingId,
            ':member_id' => $memberId,
            ':mode' => $mode,
            ':effective_power' => $effective,
            ':notes' => $notes,
        ]);
        $row = $st->fetch();
        if (!$row) {
            throw new RuntimeException('Erreur upsert présence');
        }
        return $row;
    }
}