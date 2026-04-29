<?php

declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Data access for members.
 */
class MemberRepository extends AbstractRepository {
    /**
     * Lists all members (not deleted) for a tenant.
     */
    public function listByTenant(string $tenantId): array {
        return $this->selectAll(
            'SELECT id, tenant_id, external_ref, full_name, email,
                    COALESCE(voting_power, 1.0) AS voting_power, role, is_active, created_at, updated_at
             FROM members
             WHERE tenant_id = :tenant_id AND deleted_at IS NULL
             ORDER BY full_name',
            [':tenant_id' => $tenantId],
        );
    }

    /**
     * Lists active members for a tenant (with voting power).
     */
    public function listActive(string $tenantId): array {
        return $this->selectAll(
            'SELECT id, full_name, email, role,
                    COALESCE(voting_power, 1.0) AS voting_power,
                    is_active, created_at, updated_at, tenant_id
             FROM members
             WHERE tenant_id = :tenant_id
               AND is_active = true
               AND deleted_at IS NULL
             ORDER BY full_name ASC',
            [':tenant_id' => $tenantId],
        );
    }

    /**
     * Find a member by ID for a specific tenant.
     */
    public function findByIdForTenant(string $id, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT * FROM members WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL',
            [':id' => $id, ':tenant_id' => $tenantId],
        );
    }

    /**
     * Check if a member exists for a tenant.
     */
    public function existsForTenant(string $id, string $tenantId): bool {
        return (bool) $this->scalar(
            'SELECT 1 FROM members WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL',
            [':id' => $id, ':tenant_id' => $tenantId],
        );
    }

    /**
     * Returns the subset of $ids that exist (non-deleted) for this tenant.
     *
     * @param string[] $ids
     * @return string[] existing IDs
     */
    public function filterExistingIds(array $ids, string $tenantId): array {
        if (count($ids) === 0) {
            return [];
        }
        $params = [':tid' => $tenantId];
        $in = $this->buildInClause('id', $ids, $params);
        $rows = $this->selectAll(
            "SELECT id FROM members WHERE tenant_id = :tid AND id IN ({$in}) AND deleted_at IS NULL",
            $params,
        );
        return array_column($rows, 'id');
    }

    /**
     * Count of active members for a tenant (excludes soft-deleted).
     */
    public function countActive(string $tenantId): int {
        return (int) ($this->scalar(
            'SELECT COUNT(*) FROM members WHERE tenant_id = :tid AND is_active = true AND deleted_at IS NULL',
            [':tid' => $tenantId],
        ) ?? 0);
    }

    /**
     * Total weight of active members for a tenant (excludes soft-deleted).
     */
    public function sumActiveWeight(string $tenantId): float {
        return (float) ($this->scalar(
            'SELECT COALESCE(SUM(COALESCE(voting_power, 1.0)), 0)
             FROM members WHERE tenant_id = :tid AND is_active = true AND deleted_at IS NULL',
            [':tid' => $tenantId],
        ) ?? 0.0);
    }

    /**
     * Lists IDs of active members for a tenant.
     */
    public function listActiveIds(string $tenantId): array {
        return $this->selectAll(
            'SELECT id FROM members WHERE tenant_id = :tid AND is_active = true AND deleted_at IS NULL',
            [':tid' => $tenantId],
        );
    }

    /**
     * Checks if a member exists for a tenant.
     */
    public function existsForTenantById(string $memberId, string $tenantId): bool {
        return (bool) $this->scalar(
            'SELECT 1 FROM members WHERE id = :id AND tenant_id = :tid',
            [':id' => $memberId, ':tid' => $tenantId],
        );
    }

    /**
     * Count of non-deleted members for a tenant (for dashboard eligible count).
     */
    public function countNotDeleted(string $tenantId): int {
        return (int) ($this->scalar(
            'SELECT COUNT(*) FROM members WHERE tenant_id = :tid AND deleted_at IS NULL',
            [':tid' => $tenantId],
        ) ?? 0);
    }

    /**
     * Sum of voting_power for non-deleted members (for dashboard eligible weight).
     */
    public function sumNotDeletedVoteWeight(string $tenantId): float {
        return (float) ($this->scalar(
            'SELECT COALESCE(SUM(COALESCE(voting_power, 1.0)), 0) FROM members WHERE tenant_id = :tid AND deleted_at IS NULL',
            [':tid' => $tenantId],
        ) ?? 0.0);
    }

    /**
     * Lists active members with email (for invitations).
     */
    public function listActiveWithEmail(string $tenantId): array {
        return $this->selectAll(
            "SELECT id, full_name, email
             FROM members
             WHERE tenant_id = :tid AND is_active = true
               AND email IS NOT NULL AND email <> ''
             ORDER BY full_name ASC",
            [':tid' => $tenantId],
        );
    }

    /**
     * Lists active members with email, paginated.
     * Uses LIMIT/OFFSET for batch processing.
     * Note: OFFSET pagination is acceptable here because email scheduling
     * is idempotent (onlyUnsent check skips already-sent members).
     */
    public function listActiveWithEmailPaginated(string $tenantId, int $limit, int $offset): array {
        return $this->selectAll(
            "SELECT id, full_name, email, tenant_id
             FROM members
             WHERE tenant_id = :tid AND is_active = true
               AND email IS NOT NULL AND email <> ''
             ORDER BY id
             LIMIT :limit OFFSET :offset",
            [':tid' => $tenantId, ':limit' => $limit, ':offset' => $offset],
        );
    }

    /**
     * Find the member linked to a user account (via user_id FK).
     * Returns null if no member is linked to this user.
     */
    public function findByUserId(string $userId, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT id, full_name, email,
                    COALESCE(voting_power, 1.0) AS voting_power
             FROM members
             WHERE user_id = :uid AND tenant_id = :tid
               AND is_active = true AND deleted_at IS NULL
             LIMIT 1',
            [':uid' => $userId, ':tid' => $tenantId],
        );
    }

    /**
     * Finds an active member with voting weight (for manual_vote).
     */
    public function findActiveWithWeight(string $tenantId, string $memberId): ?array {
        return $this->selectOne(
            'SELECT id, COALESCE(voting_power, 1.0) AS voting_power FROM members
             WHERE tenant_id = :tid AND id = :id AND is_active = true',
            [':tid' => $tenantId, ':id' => $memberId],
        );
    }

    /**
     * Lists active members for president selection.
     */
    public function listActiveForPresident(string $tenantId): array {
        return $this->selectAll(
            'SELECT id, full_name, email, role
             FROM members
             WHERE tenant_id = :tid AND is_active = true
             ORDER BY full_name ASC',
            [':tid' => $tenantId],
        );
    }

    /**
     * Insere un membre de test (seed) avec ON CONFLICT DO NOTHING.
     * Retourne true si la ligne a ete inseree, false sinon.
     */
    public function insertSeedMember(string $id, string $tenantId, string $fullName): bool {
        $rows = $this->execute(
            'INSERT INTO members (id, tenant_id, full_name, is_active, voting_power, created_at, updated_at)
             VALUES (:id, :tid, :name, true, 1, now(), now())
             ON CONFLICT DO NOTHING',
            [':id' => $id, ':tid' => $tenantId, ':name' => $fullName],
        );
        return $rows > 0;
    }

    /**
     * Export CSV: membres avec presences et procurations pour une seance.
     */
    public function listExportForMeeting(string $meetingId, string $tenantId): array {
        return $this->selectAll(
            "SELECT
                m.id AS member_id,
                m.full_name,
                (CASE WHEN m.is_active THEN '1' ELSE '0' END) AS is_active,
                COALESCE(m.voting_power, 0) AS voting_power,
                COALESCE(a.mode::text, 'absent') AS attendance_mode,
                a.checked_in_at,
                a.checked_out_at,
                pr.receiver_member_id AS proxy_to_member_id,
                r.full_name AS proxy_to_name
             FROM members m
             LEFT JOIN attendances a
                    ON a.meeting_id = ? AND a.member_id = m.id
             LEFT JOIN proxies pr
                    ON pr.meeting_id = ? AND pr.giver_member_id = m.id
             LEFT JOIN members r
                    ON r.id = pr.receiver_member_id
             WHERE m.tenant_id = ?
             ORDER BY m.full_name ASC",
            [$meetingId, $meetingId, $tenantId],
        );
    }

    public function create(
        string $id,
        string $tenantId,
        string $fullName,
        ?string $email,
        float $votingPower,
        bool $isActive,
    ): void {
        $this->execute(
            'INSERT INTO members (id, tenant_id, full_name, email, voting_power, is_active, created_at, updated_at)
             VALUES (:id, :tid, :name, :email, :vp, :active, now(), now())',
            [
                ':id' => $id,
                ':tid' => $tenantId,
                ':name' => $fullName,
                ':email' => $email !== '' ? $email : null,
                ':vp' => $votingPower,
                ':active' => $isActive ? 'true' : 'false',
            ],
        );
    }

    /**
     * Liste tous les membres actifs avec leur mode de presence pour une seance.
     */
    public function listWithAttendanceForMeeting(string $meetingId, string $tenantId): array {
        return $this->selectAll(
            'SELECT m.id AS member_id, m.full_name,
                    COALESCE(m.voting_power, 1.0) AS voting_power,
                    a.mode AS attendance_mode
             FROM members m
             LEFT JOIN attendances a ON a.member_id = m.id AND a.meeting_id = :mid
             WHERE m.tenant_id = :tid AND m.is_active = true
             ORDER BY m.full_name ASC',
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
    }

    /**
     * Liste les membres d'une seance (fallback sans attendances).
     */
    public function listByMeetingFallback(string $tenantId, string $meetingId): array {
        return $this->selectAll(
            'SELECT m.id AS member_id FROM members m
             JOIN attendances a ON a.member_id = m.id AND a.meeting_id = :mid
             WHERE m.tenant_id = :tid',
            [':tid' => $tenantId, ':mid' => $meetingId],
        );
    }

    /**
     * Liste les membres eligibles (avec attendance present/remote/proxy) pour une seance.
     */
    public function listEligibleForMeeting(string $tenantId, string $meetingId): array {
        return $this->selectAll(
            "SELECT m.id AS member_id, m.full_name
             FROM members m
             JOIN attendances a ON a.member_id = m.id AND a.meeting_id = :mid AND a.tenant_id = m.tenant_id
             WHERE m.tenant_id = :tid AND m.is_active = true AND a.mode IN ('present','remote','proxy')
             ORDER BY m.full_name ASC",
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
    }

    /**
     * Liste les membres actifs d'une seance (fallback avec meeting_id sur members).
     */
    public function listActiveFallbackByMeeting(string $tenantId, string $meetingId): array {
        return $this->selectAll(
            'SELECT m.id AS member_id, m.full_name
             FROM members m
             JOIN attendances a ON a.member_id = m.id AND a.meeting_id = :mid
             WHERE m.tenant_id = :tid AND m.is_active = true
             ORDER BY m.full_name ASC',
            [':tid' => $tenantId, ':mid' => $meetingId],
        );
    }

    /**
     * Trouve un membre par email (case-insensitive).
     */
    public function findByEmail(string $tenantId, string $email): ?array {
        return $this->selectOne(
            'SELECT id FROM members WHERE tenant_id = :tid AND LOWER(email) = :email',
            [':tid' => $tenantId, ':email' => strtolower($email)],
        );
    }

    /**
     * Trouve un membre par nom complet (case-insensitive).
     */
    public function findByFullName(string $tenantId, string $fullName): ?array {
        return $this->selectOne(
            'SELECT id FROM members WHERE tenant_id = :tid AND LOWER(full_name) = LOWER(:name)',
            [':tid' => $tenantId, ':name' => $fullName],
        );
    }

    /**
     * Met a jour un membre lors d'un import CSV.
     */
    public function updateImport(string $id, string $fullName, ?string $email, float $votingPower, bool $isActive, string $tenantId): void {
        $this->execute(
            'UPDATE members SET
                full_name = :name, email = COALESCE(:email, email), voting_power = :vp, is_active = :active, updated_at = NOW()
             WHERE id = :id AND tenant_id = :tid',
            [
                ':name' => $fullName, ':email' => $email, ':vp' => $votingPower,
                ':active' => $isActive ? 'true' : 'false', ':id' => $id, ':tid' => $tenantId,
            ],
        );
    }

    /**
     * Cree un membre avec UUID genere cote serveur (import CSV).
     * Retourne l'ID du membre cree.
     */
    public function createImport(string $tenantId, string $fullName, ?string $email, float $votingPower, bool $isActive): string {
        $result = $this->insertReturning(
            'INSERT INTO members (id, tenant_id, full_name, email, voting_power, is_active, created_at, updated_at)
             VALUES (gen_random_uuid(), :tid, :name, :email, :vp, :active, NOW(), NOW())
             RETURNING id',
            [
                ':tid' => $tenantId, ':name' => $fullName, ':email' => $email,
                ':vp' => $votingPower, ':active' => $isActive ? 'true' : 'false',
            ],
        );
        return $result['id'];
    }

    /**
     * Verifie si un membre a ete cree par un utilisateur.
     */
    public function isOwnedByUser(string $memberId, string $userId): bool {
        // Check if user belongs to the same tenant as the member
        return (bool) $this->scalar(
            'SELECT 1 FROM members mb
             JOIN users u ON u.tenant_id = mb.tenant_id
             WHERE mb.id = :id AND u.id = :uid',
            [':id' => $memberId, ':uid' => $userId],
        );
    }

    /**
     * Returns members not updated since $monthsRetention months ago (candidates for RGPD purge).
     * Returns empty array if $monthsRetention <= 0 (disabled).
     */
    public function findExpiredForTenant(string $tenantId, int $monthsRetention): array {
        if ($monthsRetention <= 0) {
            return [];
        }
        $sql = "SELECT id, full_name, email, updated_at
                FROM members
                WHERE tenant_id = :tid
                  AND deleted_at IS NULL
                  AND updated_at < NOW() - INTERVAL '" . ((int) $monthsRetention) . " months'";
        return $this->selectAll($sql, [':tid' => $tenantId]);
    }

    /**
     * Hard-deletes a member row (RGPD right-to-erasure).
     * Cascades to ballots, attendances, proxies via FK ON DELETE CASCADE.
     * Returns number of rows deleted.
     */
    public function hardDeleteById(string $id, string $tenantId): int {
        return $this->execute(
            'DELETE FROM members WHERE id = :id AND tenant_id = :tid',
            [':id' => $id, ':tid' => $tenantId],
        );
    }

    /**
     * Soft delete un membre (marque deleted_at).
     * Supprime aussi toutes les procurations ou le membre est donneur ou receveur.
     */
    public function softDelete(string $id, string $tenantId): void {
        $this->execute(
            'UPDATE members SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id AND tenant_id = :tid',
            [':id' => $id, ':tid' => $tenantId],
        );
        $this->execute(
            'DELETE FROM proxies WHERE tenant_id = :tid AND (giver_member_id = :id OR receiver_member_id = :id)',
            [':id' => $id, ':tid' => $tenantId],
        );
    }

    /**
     * Lists all members of a tenant (active and inactive, not deleted).
     */
    public function listAll(string $tenantId): array {
        return $this->selectAll(
            'SELECT id, full_name, full_name AS name, email, role,
                    COALESCE(voting_power, 1.0) AS voting_power,
                    is_active, created_at, updated_at, tenant_id
             FROM members
             WHERE tenant_id = :tenant_id
               AND deleted_at IS NULL
             ORDER BY full_name ASC',
            [':tenant_id' => $tenantId],
        );
    }

    /**
     * Paginated member list (not deleted) for a tenant.
     */
    public function listPaginated(string $tenantId, int $limit = 50, int $offset = 0): array {
        $limit  = max(1, min($limit, 50));
        $offset = max(0, $offset);
        return $this->selectAll(
            'SELECT id, full_name, full_name AS name, email, role,
                    COALESCE(voting_power, 1.0) AS voting_power,
                    is_active, created_at, updated_at, tenant_id
             FROM members
             WHERE tenant_id = :tenant_id AND deleted_at IS NULL
             ORDER BY full_name ASC
             LIMIT :lim OFFSET :off',
            [':tenant_id' => $tenantId, ':lim' => $limit, ':off' => $offset],
        );
    }

    /**
     * Count of all non-deleted members for a tenant (for pagination).
     */
    public function countAll(string $tenantId): int {
        return (int) ($this->scalar(
            'SELECT COUNT(*) FROM members WHERE tenant_id = :tid AND deleted_at IS NULL',
            [':tid' => $tenantId],
        ) ?? 0);
    }

    /**
     * Paginated member list filtered by search term (full_name or email ILIKE).
     */
    public function listPaginatedFiltered(string $tenantId, int $limit = 50, int $offset = 0, ?string $search = null): array {
        $limit  = max(1, min($limit, 50));
        $offset = max(0, $offset);
        $params = [':tenant_id' => $tenantId, ':lim' => $limit, ':off' => $offset];
        $where  = 'WHERE tenant_id = :tenant_id AND deleted_at IS NULL';

        if ($search !== null && $search !== '') {
            $where .= ' AND (full_name ILIKE :search OR email ILIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        return $this->selectAll(
            "SELECT id, full_name, full_name AS name, email, role,
                    COALESCE(voting_power, 1.0) AS voting_power,
                    is_active, created_at, updated_at, tenant_id
             FROM members
             {$where}
             ORDER BY full_name ASC
             LIMIT :lim OFFSET :off",
            $params,
        );
    }

    /**
     * Count of non-deleted members matching optional search filter.
     */
    public function countFiltered(string $tenantId, ?string $search = null): int {
        $params = [':tid' => $tenantId];
        $where  = 'WHERE tenant_id = :tid AND deleted_at IS NULL';

        if ($search !== null && $search !== '') {
            $where .= ' AND (full_name ILIKE :search OR email ILIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        return (int) ($this->scalar(
            "SELECT COUNT(*) FROM members {$where}",
            $params,
        ) ?? 0);
    }

    /**
     * Bulk update voting_power for a set of member IDs.
     * Only updates members that exist and are not deleted for the tenant.
     * Returns number of rows updated.
     */
    public function bulkUpdateVotingPower(array $ids, string $tenantId, float $power): int {
        $ids = $this->filterExistingIds($ids, $tenantId);
        if (count($ids) === 0) {
            return 0;
        }
        $params = [':power' => $power, ':tid' => $tenantId];
        $in = $this->buildInClause('id', $ids, $params);

        return $this->execute(
            "UPDATE members SET voting_power = :power, updated_at = NOW()
             WHERE id IN ({$in}) AND tenant_id = :tid AND deleted_at IS NULL",
            $params,
        );
    }

    /**
     * Fetch current voting_power for a list of member IDs scoped to the tenant.
     *
     * Used by audit-trail callers (F04) that must record `before → after` snapshots
     * per member, not just an aggregate count.
     *
     * @param  list<string> $ids
     * @return array<string, float>  id => current voting_power
     */
    public function listVotingPowersByIds(array $ids, string $tenantId): array {
        $ids = $this->filterExistingIds($ids, $tenantId);
        if (count($ids) === 0) {
            return [];
        }
        $params = [':tid' => $tenantId];
        $in = $this->buildInClause('id', $ids, $params);

        $rows = $this->selectAll(
            "SELECT id, voting_power FROM members
             WHERE id IN ({$in}) AND tenant_id = :tid AND deleted_at IS NULL",
            $params,
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['id']] = (float) $row['voting_power'];
        }
        return $map;
    }
}
