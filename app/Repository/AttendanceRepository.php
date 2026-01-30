<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Acces donnees pour les presences (attendances).
 */
class AttendanceRepository extends AbstractRepository
{
    // =========================================================================
    // LECTURE
    // =========================================================================

    /**
     * Verifie si un membre est present (present/remote/proxy, non checked_out).
     */
    public function isPresent(string $meetingId, string $memberId, string $tenantId): bool
    {
        return (bool)$this->scalar(
            "SELECT 1 FROM attendances a
             JOIN meetings mt ON mt.id = a.meeting_id
             WHERE a.meeting_id = :mid AND a.member_id = :uid
               AND mt.tenant_id = :tid AND a.checked_out_at IS NULL
               AND a.mode IN ('present','remote','proxy')
             LIMIT 1",
            [':mid' => $meetingId, ':uid' => $memberId, ':tid' => $tenantId]
        );
    }

    /**
     * Verifie si un membre est present directement (present/remote uniquement).
     */
    public function isPresentDirect(string $meetingId, string $memberId, string $tenantId): bool
    {
        return (bool)$this->scalar(
            "SELECT 1 FROM attendances a
             JOIN meetings mt ON mt.id = a.meeting_id
             WHERE a.meeting_id = :mid AND a.member_id = :uid
               AND mt.tenant_id = :tid AND a.checked_out_at IS NULL
               AND a.mode IN ('present','remote')
             LIMIT 1",
            [':mid' => $meetingId, ':uid' => $memberId, ':tid' => $tenantId]
        );
    }

    /**
     * Liste les presences d'une seance avec infos membre.
     */
    public function listForMeeting(string $meetingId, string $tenantId): array
    {
        return $this->selectAll(
            "SELECT a.id, a.meeting_id, a.member_id, a.mode,
                    a.checked_in_at, a.checked_out_at, a.effective_power, a.notes,
                    m.full_name, m.email, m.role, m.voting_power
             FROM attendances a
             JOIN members m ON m.id = a.member_id
             JOIN meetings mt ON mt.id = a.meeting_id
             WHERE a.meeting_id = :mid AND mt.tenant_id = :tid
             ORDER BY m.full_name ASC",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );
    }

    /**
     * Resume (nb + poids) des presents pour une seance.
     */
    public function summaryForMeeting(string $meetingId, string $tenantId): array
    {
        $row = $this->selectOne(
            "SELECT COUNT(*)::int AS present_count,
                    COALESCE(SUM(a.effective_power), 0)::float8 AS present_weight
             FROM attendances a
             JOIN meetings mt ON mt.id = a.meeting_id
             WHERE a.meeting_id = :mid AND mt.tenant_id = :tid
               AND a.checked_out_at IS NULL
               AND a.mode IN ('present','remote','proxy')",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );
        return [
            'present_count' => (int)($row['present_count'] ?? 0),
            'present_weight' => (float)($row['present_weight'] ?? 0.0),
        ];
    }

    /**
     * Compte les membres presents (avec filtre modes et late rule).
     * Utilise par QuorumEngine.
     */
    public function countPresentMembers(string $meetingId, string $tenantId, array $modes, ?string $lateCutoff = null): int
    {
        $ph = implode(',', array_fill(0, count($modes), '?'));
        $sql = "SELECT COUNT(*) FROM attendances a
                JOIN meetings mt ON mt.id = a.meeting_id
                WHERE a.meeting_id = ? AND mt.tenant_id = ?
                  AND a.checked_out_at IS NULL AND a.mode IN ($ph)";
        $params = array_merge([$meetingId, $tenantId], $modes);

        if ($lateCutoff !== null) {
            $sql .= " AND (a.present_from_at IS NULL OR a.present_from_at <= ?)";
            $params[] = $lateCutoff;
        }

        return (int)($this->scalar($sql, $params) ?? 0);
    }

    /**
     * Somme du poids effectif des presents (avec filtre modes et late rule).
     * Utilise par QuorumEngine.
     */
    public function sumPresentWeight(string $meetingId, string $tenantId, array $modes, ?string $lateCutoff = null): float
    {
        $ph = implode(',', array_fill(0, count($modes), '?'));
        $sql = "SELECT COALESCE(SUM(a.effective_power), 0) FROM attendances a
                JOIN meetings mt ON mt.id = a.meeting_id
                WHERE a.meeting_id = ? AND mt.tenant_id = ?
                  AND a.checked_out_at IS NULL AND a.mode IN ($ph)";
        $params = array_merge([$meetingId, $tenantId], $modes);

        if ($lateCutoff !== null) {
            $sql .= " AND (a.present_from_at IS NULL OR a.present_from_at <= ?)";
            $params[] = $lateCutoff;
        }

        return (float)($this->scalar($sql, $params) ?? 0.0);
    }

    // =========================================================================
    // ECRITURE
    // =========================================================================

    /**
     * Upsert presence (INSERT ... ON CONFLICT DO UPDATE ... RETURNING).
     */
    public function upsert(string $meetingId, string $memberId, string $mode, float $effectivePower, ?string $notes = null): ?array
    {
        return $this->insertReturning(
            "INSERT INTO attendances (meeting_id, member_id, mode, checked_in_at, checked_out_at, effective_power, notes)
             VALUES (:mid, :uid, :mode, now(), NULL, :ep, :notes)
             ON CONFLICT (meeting_id, member_id) DO UPDATE SET
               mode = EXCLUDED.mode, checked_in_at = now(), checked_out_at = NULL,
               effective_power = EXCLUDED.effective_power, notes = EXCLUDED.notes
             RETURNING id, meeting_id, member_id, mode, checked_in_at, checked_out_at, effective_power, notes",
            [':mid' => $meetingId, ':uid' => $memberId, ':mode' => $mode, ':ep' => $effectivePower, ':notes' => $notes]
        );
    }

    /**
     * Supprime la presence d'un membre pour une seance.
     */
    public function deleteByMeetingAndMember(string $meetingId, string $memberId): void
    {
        $this->execute(
            "DELETE FROM attendances WHERE meeting_id = :mid AND member_id = :uid",
            [':mid' => $meetingId, ':uid' => $memberId]
        );
    }
}
