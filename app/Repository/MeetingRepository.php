<?php

declare(strict_types=1);

namespace AgVote\Repository;

use InvalidArgumentException;

/**
 * Data access for meetings.
 *
 * All SQL queries for the meetings table are centralized here.
 * No business logic (transitions, validations) - only CRUD.
 */
class MeetingRepository extends AbstractRepository {
    // =========================================================================
    // READ
    // =========================================================================

    public function findByIdForTenant(string $id, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT * FROM meetings WHERE id = :id AND tenant_id = :tenant_id',
            [':id' => $id, ':tenant_id' => $tenantId],
        );
    }

    public function existsForTenant(string $id, string $tenantId): bool {
        return (bool) $this->scalar(
            'SELECT 1 FROM meetings WHERE id = :id AND tenant_id = :tenant_id',
            [':id' => $id, ':tenant_id' => $tenantId],
        );
    }

    /**
     * Checks if a meeting has been validated (validated_at IS NOT NULL).
     * Used by ballots_cancel.php to guard against modifications after validation.
     */
    public function isValidated(string $id, string $tenantId): bool {
        return (bool) $this->scalar(
            'SELECT 1 FROM meetings WHERE id = :id AND tenant_id = :tid AND validated_at IS NOT NULL',
            [':id' => $id, ':tid' => $tenantId],
        );
    }

    /**
     * Finds a meeting by its slug (URL obfuscation).
     */
    public function findBySlugForTenant(string $slug, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT * FROM meetings WHERE slug = :slug AND tenant_id = :tenant_id',
            [':slug' => $slug, ':tenant_id' => $tenantId],
        );
    }

    /**
     * Finds a meeting by ID or slug (dual support).
     * Automatically detects if the identifier is a UUID or slug.
     */
    public function findByIdOrSlugForTenant(string $identifier, string $tenantId): ?array {
        // Check if it's a UUID
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier)) {
            return $this->findByIdForTenant($identifier, $tenantId);
        }
        // Otherwise, search by slug
        return $this->findBySlugForTenant($identifier, $tenantId);
    }

    /**
     * Lists all meetings for a tenant (for meetings.php GET).
     */
    public function listByTenant(string $tenantId): array {
        return $this->selectAll(
            'SELECT
                id, tenant_id, title, description,
                status::text AS status,
                scheduled_at, started_at, ended_at,
                location, quorum_policy_id, vote_policy_id,
                president_name, convocation_no,
                created_at, updated_at
             FROM meetings
             WHERE tenant_id = :tenant_id
             ORDER BY COALESCE(started_at, scheduled_at, created_at) DESC',
            [':tenant_id' => $tenantId],
        );
    }

    /**
     * Lists active meetings (excludes validated and archived).
     * Used for selection dropdowns.
     */
    public function listActiveByTenant(string $tenantId): array {
        return $this->selectAll(
            "SELECT
                id, tenant_id, title, description,
                status::text AS status,
                scheduled_at, started_at, ended_at,
                location, quorum_policy_id, vote_policy_id,
                president_name, convocation_no,
                created_at, updated_at
             FROM meetings
             WHERE tenant_id = :tenant_id
               AND status NOT IN ('validated', 'archived')
             ORDER BY COALESCE(started_at, scheduled_at, created_at) DESC",
            [':tenant_id' => $tenantId],
        );
    }

    /**
     * Compact list (for meetings_index.php).
     */
    public function listByTenantCompact(string $tenantId, int $limit = 50): array {
        return $this->selectAll(
            "SELECT m.id AS meeting_id, m.id, m.title, m.status::text AS status,
                    m.meeting_type, m.scheduled_at,
                    m.created_at, m.started_at, m.ended_at, m.archived_at, m.validated_at,
                    COALESCE(mo_cnt.cnt, 0)  AS motions_count,
                    COALESCE(att_cnt.cnt, 0) AS attendees_count
             FROM meetings m
             LEFT JOIN LATERAL (
                 SELECT COUNT(*)::int AS cnt FROM motions WHERE meeting_id = m.id
             ) mo_cnt ON true
             LEFT JOIN LATERAL (
                 SELECT COUNT(*)::int AS cnt FROM attendances
                 WHERE meeting_id = m.id AND mode IN ('present','remote')
             ) att_cnt ON true
             WHERE m.tenant_id = :tenant_id
             ORDER BY COALESCE(m.started_at, m.scheduled_at, m.created_at) DESC
             LIMIT " . max(1, min($limit, 200)),
            [':tenant_id' => $tenantId],
        );
    }

    /**
     * Compact list of active meetings (excludes validated and archived).
     */
    public function listActiveByTenantCompact(string $tenantId, int $limit = 50): array {
        return $this->selectAll(
            "SELECT m.id AS meeting_id, m.id, m.title, m.status::text AS status,
                    m.meeting_type, m.scheduled_at,
                    m.created_at, m.started_at, m.ended_at, m.archived_at, m.validated_at,
                    COALESCE(mo_cnt.cnt, 0)  AS motions_count,
                    COALESCE(att_cnt.cnt, 0) AS attendees_count
             FROM meetings m
             LEFT JOIN LATERAL (
                 SELECT COUNT(*)::int AS cnt FROM motions WHERE meeting_id = m.id
             ) mo_cnt ON true
             LEFT JOIN LATERAL (
                 SELECT COUNT(*)::int AS cnt FROM attendances
                 WHERE meeting_id = m.id AND mode IN ('present','remote')
             ) att_cnt ON true
             WHERE m.tenant_id = :tenant_id
               AND m.status NOT IN ('validated', 'archived')
             ORDER BY COALESCE(m.started_at, m.scheduled_at, m.created_at) DESC
             LIMIT " . max(1, min($limit, 200)),
            [':tenant_id' => $tenantId],
        );
    }

    /**
     * Lists archived/closed meetings (for meetings_archive.php).
     */
    public function listArchived(string $tenantId, string $from = '', string $to = ''): array {
        $conditions = ['tenant_id = :tenant_id', "status IN ('closed','archived')"];
        $params = [':tenant_id' => $tenantId];

        if ($from !== '') {
            $conditions[] = 'created_at >= :from';
            $params[':from'] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $conditions[] = 'created_at <= :to';
            $params[':to'] = $to . ' 23:59:59';
        }

        $where = implode(' AND ', $conditions);
        return $this->selectAll(
            "SELECT id, title, status, created_at, validated_by, validated_at
             FROM meetings
             WHERE {$where}
             ORDER BY created_at DESC
             LIMIT 500",
            $params,
        );
    }

    /**
     * Liste les seances pour le dashboard operateur.
     */
    public function listForDashboard(string $tenantId, int $limit = 50): array {
        return $this->selectAll(
            "SELECT id, title, status, scheduled_at, started_at, ended_at, archived_at, validated_at
             FROM meetings
             WHERE tenant_id = :tid
             ORDER BY
               CASE status WHEN 'live' THEN 0 WHEN 'draft' THEN 1 WHEN 'archived' THEN 3 ELSE 2 END,
               COALESCE(started_at, scheduled_at, created_at) DESC
             LIMIT " . max(1, min($limit, 500)),
            [':tid' => $tenantId],
        );
    }

    /**
     * Titre d'une seance par son ID.
     */
    public function findTitle(string $meetingId, string $tenantId = ''): ?string {
        $sql = 'SELECT title FROM meetings WHERE id = :id';
        $params = [':id' => $meetingId];
        if ($tenantId !== '') {
            $sql .= ' AND tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        $val = $this->scalar($sql, $params);
        return $val !== null ? (string) $val : null;
    }

    /**
     * Trouve la seance courante (non archivee) pour un tenant.
     * Priorite : live > closed > draft.
     */
    public function findCurrentForTenant(string $tenantId): ?array {
        return $this->selectOne(
            "SELECT
                m.id AS meeting_id, m.title AS meeting_title,
                m.status AS meeting_status,
                m.started_at, m.ended_at, m.archived_at,
                m.president_member_id, m.president_name, m.president_source,
                m.ready_to_sign, m.validated_at,
                u.display_name AS validated_by
             FROM meetings m
             LEFT JOIN users u ON u.id = m.validated_by_user_id
             WHERE m.tenant_id = :tenant_id AND m.status <> 'archived'
             ORDER BY
                CASE m.status
                    WHEN 'live' THEN 1 WHEN 'paused' THEN 2
                    WHEN 'closed' THEN 3 WHEN 'draft' THEN 4
                    ELSE 5
                END,
                m.created_at DESC
             LIMIT 1",
            [':tenant_id' => $tenantId],
        );
    }

    /**
     * Trouve une seance avec champs specifiques (pour meeting_status_for_meeting).
     */
    public function findStatusFields(string $meetingId, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT id AS meeting_id, title AS meeting_title, status AS meeting_status,
                    started_at, ended_at, archived_at, validated_at,
                    president_name, ready_to_sign
             FROM meetings WHERE tenant_id = :tenant_id AND id = :id',
            [':tenant_id' => $tenantId, ':id' => $meetingId],
        );
    }

    /**
     * Trouve les reglages de vote d'une seance.
     */
    public function findVoteSettings(string $meetingId, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT id AS meeting_id, title, vote_policy_id
             FROM meetings WHERE tenant_id = :tenant_id AND id = :id',
            [':tenant_id' => $tenantId, ':id' => $meetingId],
        );
    }

    /**
     * Trouve les reglages de quorum d'une seance.
     */
    public function findQuorumSettings(string $meetingId, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT id AS meeting_id, title, quorum_policy_id,
                    COALESCE(convocation_no, 1) AS convocation_no
             FROM meetings WHERE tenant_id = :tenant_id AND id = :id',
            [':tenant_id' => $tenantId, ':id' => $meetingId],
        );
    }

    /**
     * Trouve les regles tardives d'une seance.
     */
    public function findLateRules(string $meetingId, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT id, late_rule_quorum, late_rule_vote
             FROM meetings WHERE tenant_id = :tenant_id AND id = :id',
            [':tenant_id' => $tenantId, ':id' => $meetingId],
        );
    }

    /**
     * Resume de seance pour meeting_summary.
     */
    public function findSummaryFields(string $meetingId, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT id, title, status, president_name, validated_at
             FROM meetings WHERE tenant_id = :tenant_id AND id = :id',
            [':tenant_id' => $tenantId, ':id' => $meetingId],
        );
    }

    /**
     * Liste les seances archivees avec info rapport (pour archives_list).
     */
    public function listArchivedWithReports(string $tenantId): array {
        return $this->selectAll(
            "SELECT mt.id, mt.title, mt.archived_at, mt.validated_at, mt.president_name,
                    COALESCE(mr.sha256, NULL) AS report_sha256,
                    COALESCE(mr.generated_at, NULL) AS report_generated_at,
                    (mr.meeting_id IS NOT NULL) AS has_report
             FROM meetings mt
             LEFT JOIN meeting_reports mr ON mr.meeting_id = mt.id
             WHERE mt.tenant_id = :tid AND mt.status = 'archived'
             ORDER BY mt.archived_at DESC NULLS LAST, mt.validated_at DESC NULLS LAST",
            [':tid' => $tenantId],
        );
    }

    /**
     * Compte toutes les seances d'un tenant.
     */
    public function countForTenant(string $tenantId): int {
        return (int) ($this->scalar(
            'SELECT COUNT(*) FROM meetings WHERE tenant_id = :tid',
            [':tid' => $tenantId],
        ) ?? 0);
    }

    /**
     * Compte les seances live d'un tenant.
     */
    public function countLive(string $tenantId): int {
        return (int) ($this->scalar(
            "SELECT COUNT(*) FROM meetings WHERE tenant_id = :tid AND status = 'live'",
            [':tid' => $tenantId],
        ) ?? 0);
    }

    /**
     * Liste les seances live d'un tenant (pour le selecteur projecteur).
     */
    public function listLiveForTenant(string $tenantId): array {
        return $this->selectAll(
            "SELECT id, title, started_at, status
             FROM meetings
             WHERE tenant_id = :tid AND status IN ('live', 'paused')
             ORDER BY started_at DESC NULLS LAST, created_at DESC",
            [':tid' => $tenantId],
        );
    }

    // =========================================================================
    // ECRITURE
    // =========================================================================

    /**
     * Cree une seance.
     */
    public function create(
        string $id,
        string $tenantId,
        string $title,
        ?string $description,
        ?string $scheduledAt,
        ?string $location,
        string $meetingType = 'ag_ordinaire',
    ): void {
        $this->execute(
            "INSERT INTO meetings (id, tenant_id, title, description, meeting_type, status, scheduled_at, location, created_at, updated_at)
             VALUES (:id, :tenant_id, :title, :description, :meeting_type, 'draft', :scheduled_at, :location, NOW(), NOW())",
            [
                ':id' => $id,
                ':tenant_id' => $tenantId,
                ':title' => $title,
                ':description' => $description ?: null,
                ':meeting_type' => $meetingType,
                ':scheduled_at' => $scheduledAt ?: null,
                ':location' => $location ?: null,
            ],
        );
    }

    private const UPDATABLE_FIELDS = [
        'title', 'description', 'meeting_type', 'status', 'scheduled_at', 'started_at', 'ended_at',
        'location', 'quorum_policy_id', 'vote_policy_id', 'convocation_no',
        'president_name', 'president_member_id', 'president_source',
        'current_motion_id', 'late_rule_quorum', 'late_rule_vote',
        'ready_to_sign', 'validated_at', 'validated_by_user_id', 'archived_at',
    ];

    /**
     * Met a jour dynamiquement des champs d'une seance.
     *
     * @param array<string,mixed> $fields Cle=colonne SQL, valeur=nouvelle valeur
     */
    public function updateFields(string $meetingId, string $tenantId, array $fields): int {
        if (empty($fields)) {
            return 0;
        }

        $sets = [];
        $params = [':tid' => $tenantId, ':id' => $meetingId];
        foreach ($fields as $col => $val) {
            if (!in_array($col, self::UPDATABLE_FIELDS, true)) {
                throw new InvalidArgumentException("Colonne non autorisée : {$col}");
            }
            $param = ':f_' . $col;
            $sets[] = "\"{$col}\" = {$param}";
            $params[$param] = $val;
        }
        $sets[] = 'updated_at = now()';

        $sql = 'UPDATE meetings SET ' . implode(', ', $sets) . ' WHERE tenant_id = :tid AND id = :id';
        return $this->execute($sql, $params);
    }

    /**
     * Met a jour la politique de vote d'une seance.
     */
    public function updateVotePolicy(string $meetingId, string $tenantId, ?string $policyId): void {
        $this->execute(
            'UPDATE meetings SET vote_policy_id = :pid, updated_at = NOW()
             WHERE tenant_id = :tid AND id = :mid',
            [':pid' => $policyId, ':tid' => $tenantId, ':mid' => $meetingId],
        );
    }

    /**
     * Met a jour la politique de quorum + numero de convocation.
     */
    public function updateQuorumPolicy(string $meetingId, string $tenantId, ?string $policyId, int $convocationNo): void {
        $this->execute(
            'UPDATE meetings SET quorum_policy_id = :pid, convocation_no = :c, updated_at = NOW()
             WHERE tenant_id = :tid AND id = :mid',
            [':pid' => $policyId, ':c' => $convocationNo, ':tid' => $tenantId, ':mid' => $meetingId],
        );
    }

    /**
     * Met a jour les regles tardives.
     */
    public function updateLateRules(string $meetingId, string $tenantId, bool $lateRuleQuorum, bool $lateRuleVote): void {
        $this->execute(
            'UPDATE meetings SET late_rule_quorum = :q, late_rule_vote = :v, updated_at = NOW()
             WHERE tenant_id = :tid AND id = :id',
            [':q' => $lateRuleQuorum, ':v' => $lateRuleVote, ':tid' => $tenantId, ':id' => $meetingId],
        );
    }

    /**
     * Marque une seance comme validee.
     */
    public function markValidated(string $meetingId, string $tenantId): void {
        $this->execute(
            "UPDATE meetings SET status = 'validated', validated_at = NOW()
             WHERE tenant_id = :tid AND id = :id",
            [':tid' => $tenantId, ':id' => $meetingId],
        );
    }

    /**
     * Met a jour le current_motion_id d'une seance.
     */
    public function updateCurrentMotion(string $meetingId, string $tenantId, ?string $motionId): void {
        $this->execute(
            'UPDATE meetings SET current_motion_id = :mo, updated_at = now()
             WHERE tenant_id = :tid AND id = :mid',
            [':mo' => $motionId, ':tid' => $tenantId, ':mid' => $meetingId],
        );
    }

    /**
     * Upsert d'un check d'urgence (meeting_emergency_checks).
     */
    public function upsertEmergencyCheck(
        string $meetingId,
        string $procedureCode,
        int $itemIndex,
        bool $checked,
        ?string $checkedBy,
    ): void {
        $this->execute(
            'INSERT INTO meeting_emergency_checks(meeting_id, procedure_code, item_index, checked, checked_at, checked_by)
             VALUES (:m,:p,:i,:c, CASE WHEN :c2 THEN NOW() ELSE NULL END, :by)
             ON CONFLICT (meeting_id, procedure_code, item_index)
             DO UPDATE SET checked = EXCLUDED.checked, checked_at = EXCLUDED.checked_at, checked_by = EXCLUDED.checked_by',
            [
                ':m' => $meetingId,
                ':p' => $procedureCode,
                ':i' => $itemIndex,
                ':c' => $checked,
                ':c2' => $checked,
                ':by' => $checkedBy,
            ],
        );
    }

    /**
     * Lock meeting row pour eviter les conflits concurrents (FOR UPDATE).
     */
    public function lockForUpdate(string $meetingId, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT id, title, status, validated_at, started_at, scheduled_at, ended_at FROM meetings
             WHERE tenant_id = :tid AND id = :id FOR UPDATE',
            [':tid' => $tenantId, ':id' => $meetingId],
        );
    }

    /**
     * Trouve une seance avec le nom du validateur.
     */
    public function findWithValidator(string $meetingId): ?array {
        return $this->selectOne(
            'SELECT m.*, u.display_name AS validated_by
             FROM meetings m
             LEFT JOIN users u ON u.id = m.validated_by_user_id
             WHERE m.id = :id',
            [':id' => $meetingId],
        );
    }

    /**
     * Reinitialise les champs live d'une seance (reset demo).
     */
    public function resetForDemo(string $meetingId, string $tenantId): void {
        $this->execute(
            "UPDATE meetings
             SET current_motion_id = NULL, status = 'live', updated_at = now()
             WHERE id = :mid AND tenant_id = :tid",
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
    }

    /**
     * Supprime une seance en brouillon et ses donnees associees.
     * Ne fonctionne que pour les seances au statut 'draft'.
     */
    public function deleteDraft(string $meetingId, string $tenantId): int {
        return $this->execute(
            "DELETE FROM meetings WHERE id = :id AND tenant_id = :tid AND status = 'draft'",
            [':id' => $meetingId, ':tid' => $tenantId],
        );
    }

    /**
     * Verifie si une seance a ete creee par un utilisateur.
     */
    public function isOwnedByUser(string $meetingId, string $userId): bool {
        // Check if user belongs to the same tenant as the meeting
        return (bool) $this->scalar(
            'SELECT 1 FROM meetings m
             JOIN users u ON u.tenant_id = m.tenant_id
             WHERE m.id = :id AND u.id = :uid',
            [':id' => $meetingId, ':uid' => $userId],
        );
    }
}
