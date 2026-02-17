<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Data access for attendance records.
 */
class AttendanceRepository extends AbstractRepository
{
    // =========================================================================
    // READ
    // =========================================================================

    /**
     * Checks if a member is present (present/remote/proxy, not checked_out).
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
     * Checks if a member is present directly (present/remote only).
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
     * Lists attendance records for a meeting with member info.
     */
    public function listForMeeting(string $meetingId, string $tenantId): array
    {
        return $this->selectAll(
            "SELECT m.id AS member_id, m.full_name, m.email, m.role,
                    COALESCE(m.voting_power, m.vote_weight, 1) AS voting_power,
                    a.id AS attendance_id, a.meeting_id,
                    COALESCE(a.mode::text, 'absent') AS mode,
                    a.checked_in_at, a.checked_out_at, a.effective_power, a.notes
             FROM members m
             LEFT JOIN attendances a ON a.member_id = m.id AND a.meeting_id = :mid AND a.tenant_id = :tid
             WHERE m.tenant_id = :tid2 AND m.is_active = true AND m.deleted_at IS NULL
             ORDER BY m.full_name ASC",
            [':mid' => $meetingId, ':tid' => $tenantId, ':tid2' => $tenantId]
        );
    }

    /**
     * Summary (count + weight) of attendees for a meeting.
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
     * Lists attendance for report (with member info).
     */
    public function listForReport(string $meetingId, string $tenantId): array
    {
        return $this->selectAll(
            "SELECT m.id AS member_id, m.full_name,
                    COALESCE(m.voting_power, m.vote_weight, 1.0) AS voting_power,
                    a.mode, a.checked_in_at, a.checked_out_at
             FROM members m
             LEFT JOIN attendances a ON a.member_id = m.id AND a.meeting_id = :mid AND a.tenant_id = :tid
             WHERE m.tenant_id = :tid2 AND m.is_active = true AND m.deleted_at IS NULL
             ORDER BY m.full_name ASC",
            [':mid' => $meetingId, ':tid' => $tenantId, ':tid2' => $tenantId]
        );
    }

    /**
     * Resume attendance pour le dashboard (present count + weight via members.vote_weight).
     */
    public function dashboardSummary(string $tenantId, string $meetingId): array
    {
        $row = $this->selectOne(
            "SELECT
                COUNT(*) FILTER (WHERE a.mode IN ('present','remote'))::int AS present_count,
                COALESCE(SUM(m.vote_weight) FILTER (WHERE a.mode IN ('present','remote')),0)::int AS present_weight
             FROM attendances a
             JOIN members m ON m.id = a.member_id
             WHERE a.tenant_id = :tid AND a.meeting_id = :mid",
            [':tid' => $tenantId, ':mid' => $meetingId]
        );
        return $row ?: ['present_count' => 0, 'present_weight' => 0];
    }

    /**
     * Statistics par mode de presence (pour WebSocket broadcast).
     */
    public function getStatsByMode(string $meetingId, string $tenantId): array
    {
        $row = $this->selectOne(
            "SELECT
                COUNT(*) FILTER (WHERE mode = 'present')::int AS present,
                COUNT(*) FILTER (WHERE mode = 'remote')::int AS remote,
                COUNT(*) FILTER (WHERE mode = 'proxy')::int AS proxy,
                COUNT(*) FILTER (WHERE mode = 'excused')::int AS excused,
                COUNT(*) FILTER (WHERE mode IN ('present','remote','proxy'))::int AS total_present,
                COALESCE(SUM(effective_power) FILTER (WHERE mode IN ('present','remote','proxy')), 0)::float8 AS total_weight
             FROM attendances
             WHERE meeting_id = :mid AND tenant_id = :tid AND checked_out_at IS NULL",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );
        return $row ?: [
            'present' => 0,
            'remote' => 0,
            'proxy' => 0,
            'excused' => 0,
            'total_present' => 0,
            'total_weight' => 0.0,
        ];
    }

    /**
     * Compte les presences eligibles (present/remote/proxy) pour une seance.
     */
    public function countEligible(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT count(*) FROM attendances WHERE meeting_id = :mid AND mode IN ('present','remote','proxy')",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    /**
     * Compte les membres presents ou distants (pour workflow validation).
     */
    public function countPresentOrRemote(string $meetingId, string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM attendances
             WHERE tenant_id = :tid AND meeting_id = :mid
               AND mode IN ('present','remote')
               AND checked_out_at IS NULL",
            [':tid' => $tenantId, ':mid' => $meetingId]
        ) ?? 0);
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
    public function upsert(string $tenantId, string $meetingId, string $memberId, string $mode, float $effectivePower, ?string $notes = null): ?array
    {
        return $this->insertReturning(
            "INSERT INTO attendances (tenant_id, meeting_id, member_id, mode, checked_in_at, checked_out_at, effective_power, notes)
             VALUES (:tid, :mid, :uid, :mode, now(), NULL, :ep, :notes)
             ON CONFLICT (tenant_id, meeting_id, member_id) DO UPDATE SET
               mode = EXCLUDED.mode, checked_in_at = now(), checked_out_at = NULL,
               effective_power = EXCLUDED.effective_power, notes = EXCLUDED.notes,
               updated_at = now()
             RETURNING id, tenant_id, meeting_id, member_id, mode, checked_in_at, checked_out_at, effective_power, notes",
            [':tid' => $tenantId, ':mid' => $meetingId, ':uid' => $memberId, ':mode' => $mode, ':ep' => $effectivePower, ':notes' => $notes]
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

    /**
     * Met a jour present_from_at pour un membre.
     */
    public function updatePresentFrom(string $meetingId, string $memberId, ?string $presentFromAt): void
    {
        $this->execute(
            "UPDATE attendances SET present_from_at = :p, updated_at = NOW()
             WHERE meeting_id = :mid AND member_id = :uid",
            [':p' => $presentFromAt, ':mid' => $meetingId, ':uid' => $memberId]
        );
    }

    /**
     * Upsert simplifie pour bulk (mode seul, sans effective_power).
     * Uses ON CONFLICT to avoid race conditions between concurrent requests.
     * @return bool true si cree, false si mis a jour
     */
    public function upsertMode(string $meetingId, string $memberId, string $mode, string $tenantId): bool
    {
        $row = $this->insertReturning(
            "INSERT INTO attendances (id, tenant_id, meeting_id, member_id, mode, created_at, updated_at)
             VALUES (gen_random_uuid(), :tid, :mid, :uid, :mode, now(), now())
             ON CONFLICT (tenant_id, meeting_id, member_id) DO UPDATE SET
               mode = EXCLUDED.mode, updated_at = now()
             RETURNING (xmax = 0) AS inserted",
            [':tid' => $tenantId, ':mid' => $meetingId, ':uid' => $memberId, ':mode' => $mode]
        );
        // xmax = 0 means the row was freshly inserted (not updated)
        return (bool)($row['inserted'] ?? false);
    }

    /**
     * Upsert de presence pour le seeding (ON CONFLICT met a jour le mode).
     */
    public function upsertSeed(string $id, string $tenantId, string $meetingId, string $memberId, string $mode): void
    {
        $this->execute(
            "INSERT INTO attendances (id, tenant_id, meeting_id, member_id, mode, checked_in_at, created_at, updated_at)
             VALUES (:id, :tid, :mid, :mem, :mode, now(), now(), now())
             ON CONFLICT (meeting_id, member_id) DO UPDATE SET mode = EXCLUDED.mode, updated_at = now()",
            [':id' => $id, ':tid' => $tenantId, ':mid' => $meetingId, ':mem' => $memberId, ':mode' => $mode]
        );
    }

    /**
     * CSV export: attendance with member info and proxies.
     * Note: Reinforced tenant isolation on all JOINs to avoid cross-tenant leaks.
     */
    public function listExportForMeeting(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT
                m.id AS member_id, m.full_name, m.voting_power,
                COALESCE(a.mode::text, 'absent') AS attendance_mode,
                a.checked_in_at, a.checked_out_at,
                pr.receiver_member_id AS proxy_to_member_id,
                r.full_name AS proxy_to_name,
                COALESCE(rc.cnt, 0) AS proxies_received
             FROM members m
             JOIN meetings mt ON mt.id = :mid1 AND mt.tenant_id = m.tenant_id
             LEFT JOIN attendances a ON a.meeting_id = mt.id AND a.member_id = m.id AND a.tenant_id = mt.tenant_id
             LEFT JOIN proxies pr ON pr.meeting_id = mt.id AND pr.giver_member_id = m.id AND pr.tenant_id = mt.tenant_id AND pr.revoked_at IS NULL
             LEFT JOIN members r ON r.id = pr.receiver_member_id AND r.tenant_id = mt.tenant_id
             LEFT JOIN (
                SELECT p2.receiver_member_id, COUNT(*)::int AS cnt
                FROM proxies p2
                JOIN meetings mt2 ON mt2.id = p2.meeting_id AND mt2.tenant_id = p2.tenant_id
                WHERE p2.meeting_id = :mid2 AND p2.revoked_at IS NULL
                GROUP BY p2.receiver_member_id
             ) rc ON rc.receiver_member_id = m.id
             WHERE m.tenant_id = mt.tenant_id AND m.is_active = true
             ORDER BY m.full_name ASC",
            [':mid1' => $meetingId, ':mid2' => $meetingId]
        );
    }

    /**
     * Liste les member_id eligibles (present/remote/proxy) pour une seance.
     */
    public function listEligibleMemberIds(string $tenantId, string $meetingId): array
    {
        return $this->selectAll(
            "SELECT member_id FROM attendances
             WHERE tenant_id = :tid AND meeting_id = :mid
               AND mode IN ('present','remote','proxy')",
            [':tid' => $tenantId, ':mid' => $meetingId]
        );
    }

    /**
     * Liste les votants eligibles avec nom (present/remote) pour generation tokens.
     */
    public function listEligibleVotersWithName(string $tenantId, string $meetingId): array
    {
        return $this->selectAll(
            "SELECT m.id AS member_id, COALESCE(m.full_name, m.name, m.email, m.id::text) AS member_name
             FROM members m
             JOIN attendances a ON a.member_id = m.id AND a.meeting_id = :mid
             WHERE m.tenant_id = :tid
               AND m.is_active = true
               AND a.mode IN ('present','remote')
             ORDER BY COALESCE(m.full_name, m.name, m.email) ASC",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );
    }
}
