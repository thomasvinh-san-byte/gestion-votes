<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Acces donnees pour les seances (meetings).
 *
 * Toutes les requetes SQL concernant la table meetings sont centralisees ici.
 * Aucune logique metier (transitions, validations) — uniquement du CRUD.
 */
class MeetingRepository extends AbstractRepository
{
    // =========================================================================
    // LECTURE
    // =========================================================================

    public function findById(string $id): ?array
    {
        return $this->selectOne(
            "SELECT * FROM meetings WHERE id = :id",
            [':id' => $id]
        );
    }

    public function findByIdForTenant(string $id, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT * FROM meetings WHERE id = :id AND tenant_id = :tenant_id",
            [':id' => $id, ':tenant_id' => $tenantId]
        );
    }

    public function existsForTenant(string $id, string $tenantId): bool
    {
        return (bool)$this->scalar(
            "SELECT 1 FROM meetings WHERE id = :id AND tenant_id = :tenant_id",
            [':id' => $id, ':tenant_id' => $tenantId]
        );
    }

    /**
     * Liste toutes les seances d'un tenant (pour meetings.php GET).
     */
    public function listByTenant(string $tenantId): array
    {
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
             ORDER BY COALESCE(started_at, scheduled_at, created_at) DESC",
            [':tenant_id' => $tenantId]
        );
    }

    /**
     * Liste compacte (pour meetings_index.php).
     */
    public function listByTenantCompact(string $tenantId, int $limit = 50): array
    {
        return $this->selectAll(
            "SELECT id AS meeting_id, id, title, status::text AS status,
                    created_at, started_at, ended_at, archived_at, validated_at
             FROM meetings
             WHERE tenant_id = :tenant_id
             ORDER BY COALESCE(started_at, scheduled_at, created_at) DESC
             LIMIT " . max(1, min($limit, 200)),
            [':tenant_id' => $tenantId]
        );
    }

    /**
     * Liste les seances archivees/fermees (pour meetings_archive.php).
     */
    public function listArchived(string $tenantId, string $from = '', string $to = ''): array
    {
        $conditions = ["tenant_id = :tenant_id", "status IN ('closed','archived')"];
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
            $params
        );
    }

    /**
     * Liste les seances pour le dashboard operateur.
     */
    public function listForDashboard(string $tenantId, int $limit = 50): array
    {
        return $this->selectAll(
            "SELECT id, title, status, scheduled_at, started_at, ended_at, archived_at, validated_at
             FROM meetings
             WHERE tenant_id = :tid
             ORDER BY
               CASE status WHEN 'live' THEN 0 WHEN 'draft' THEN 1 WHEN 'archived' THEN 3 ELSE 2 END,
               COALESCE(started_at, scheduled_at, created_at) DESC
             LIMIT " . max(1, min($limit, 500)),
            [':tid' => $tenantId]
        );
    }

    /**
     * Titre d'une seance par son ID.
     */
    public function findTitle(string $meetingId): ?string
    {
        $val = $this->scalar(
            "SELECT title FROM meetings WHERE id = :id",
            [':id' => $meetingId]
        );
        return $val !== null ? (string)$val : null;
    }

    /**
     * Compte les procurations actives (non revoquees) pour une seance.
     */
    public function countActiveProxies(string $tenantId, string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM proxies WHERE tenant_id = :tid AND meeting_id = :mid AND revoked_at IS NULL",
            [':tid' => $tenantId, ':mid' => $meetingId]
        ) ?? 0);
    }

    /**
     * Trouve la seance courante (non archivee) pour un tenant.
     * Priorite : live > closed > draft.
     */
    public function findCurrentForTenant(string $tenantId): ?array
    {
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
                    WHEN 'live' THEN 1 WHEN 'closed' THEN 2
                    WHEN 'draft' THEN 3 ELSE 4
                END,
                m.created_at DESC
             LIMIT 1",
            [':tenant_id' => $tenantId]
        );
    }

    /**
     * Trouve une seance avec champs specifiques (pour meeting_status_for_meeting).
     */
    public function findStatusFields(string $meetingId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT id AS meeting_id, title AS meeting_title, status AS meeting_status,
                    started_at, ended_at, archived_at, validated_at,
                    president_name, ready_to_sign
             FROM meetings WHERE tenant_id = :tenant_id AND id = :id",
            [':tenant_id' => $tenantId, ':id' => $meetingId]
        );
    }

    /**
     * Trouve les reglages de vote d'une seance.
     */
    public function findVoteSettings(string $meetingId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT id AS meeting_id, title, vote_policy_id
             FROM meetings WHERE tenant_id = :tenant_id AND id = :id",
            [':tenant_id' => $tenantId, ':id' => $meetingId]
        );
    }

    /**
     * Trouve les reglages de quorum d'une seance.
     */
    public function findQuorumSettings(string $meetingId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT id AS meeting_id, title, quorum_policy_id,
                    COALESCE(convocation_no, 1) AS convocation_no
             FROM meetings WHERE tenant_id = :tenant_id AND id = :id",
            [':tenant_id' => $tenantId, ':id' => $meetingId]
        );
    }

    /**
     * Trouve les regles tardives d'une seance.
     */
    public function findLateRules(string $meetingId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT id, late_rule_quorum, late_rule_vote
             FROM meetings WHERE tenant_id = :tenant_id AND id = :id",
            [':tenant_id' => $tenantId, ':id' => $meetingId]
        );
    }

    /**
     * Resume de seance pour meeting_summary.
     */
    public function findSummaryFields(string $meetingId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT id, title, status, president_name, validated_at
             FROM meetings WHERE tenant_id = :tenant_id AND id = :id",
            [':tenant_id' => $tenantId, ':id' => $meetingId]
        );
    }

    /**
     * Liste les seances archivees avec info rapport (pour archives_list).
     */
    public function listArchivedWithReports(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT mt.id, mt.title, mt.archived_at, mt.validated_at, mt.president_name,
                    COALESCE(mr.sha256, NULL) AS report_sha256,
                    COALESCE(mr.generated_at, NULL) AS report_generated_at,
                    (mr.meeting_id IS NOT NULL) AS has_report
             FROM meetings mt
             LEFT JOIN meeting_reports mr ON mr.meeting_id = mt.id
             WHERE mt.tenant_id = :tid AND mt.status = 'archived'
             ORDER BY mt.archived_at DESC NULLS LAST, mt.validated_at DESC NULLS LAST",
            [':tid' => $tenantId]
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
        ?string $location
    ): void {
        $this->execute(
            "INSERT INTO meetings (id, tenant_id, title, description, status, scheduled_at, location, created_at, updated_at)
             VALUES (:id, :tenant_id, :title, :description, 'draft', :scheduled_at, :location, NOW(), NOW())",
            [
                ':id' => $id,
                ':tenant_id' => $tenantId,
                ':title' => $title,
                ':description' => $description ?: null,
                ':scheduled_at' => $scheduledAt ?: null,
                ':location' => $location ?: null,
            ]
        );
    }

    /**
     * Met a jour dynamiquement des champs d'une seance.
     *
     * @param array<string,mixed> $fields Cle=colonne SQL, valeur=nouvelle valeur
     */
    public function updateFields(string $meetingId, string $tenantId, array $fields): int
    {
        if (empty($fields)) return 0;

        $sets = [];
        $params = [':tid' => $tenantId, ':id' => $meetingId];
        foreach ($fields as $col => $val) {
            $param = ':f_' . $col;
            $sets[] = "{$col} = {$param}";
            $params[$param] = $val;
        }
        $sets[] = "updated_at = now()";

        $sql = "UPDATE meetings SET " . implode(', ', $sets) . " WHERE tenant_id = :tid AND id = :id";
        return $this->execute($sql, $params);
    }

    /**
     * Met a jour la politique de vote d'une seance.
     */
    public function updateVotePolicy(string $meetingId, string $tenantId, ?string $policyId): void
    {
        $this->execute(
            "UPDATE meetings SET vote_policy_id = :pid, updated_at = NOW()
             WHERE tenant_id = :tid AND id = :mid",
            [':pid' => $policyId, ':tid' => $tenantId, ':mid' => $meetingId]
        );
    }

    /**
     * Met a jour la politique de quorum + numero de convocation.
     */
    public function updateQuorumPolicy(string $meetingId, string $tenantId, ?string $policyId, int $convocationNo): void
    {
        $this->execute(
            "UPDATE meetings SET quorum_policy_id = :pid, convocation_no = :c, updated_at = NOW()
             WHERE tenant_id = :tid AND id = :mid",
            [':pid' => $policyId, ':c' => $convocationNo, ':tid' => $tenantId, ':mid' => $meetingId]
        );
    }

    /**
     * Met a jour les regles tardives.
     */
    public function updateLateRules(string $meetingId, string $tenantId, bool $lateRuleQuorum, bool $lateRuleVote): void
    {
        $this->execute(
            "UPDATE meetings SET late_rule_quorum = :q, late_rule_vote = :v, updated_at = NOW()
             WHERE tenant_id = :tid AND id = :id",
            [':q' => $lateRuleQuorum, ':v' => $lateRuleVote, ':tid' => $tenantId, ':id' => $meetingId]
        );
    }

    /**
     * Marque une seance comme validee.
     */
    public function markValidated(string $meetingId, string $tenantId): void
    {
        $this->execute(
            "UPDATE meetings SET status = 'validated', validated_at = NOW()
             WHERE tenant_id = :tid AND id = :id",
            [':tid' => $tenantId, ':id' => $meetingId]
        );
    }

    /**
     * Met a jour le current_motion_id d'une seance.
     */
    public function updateCurrentMotion(string $meetingId, string $tenantId, ?string $motionId): void
    {
        $this->execute(
            "UPDATE meetings SET current_motion_id = :mo, updated_at = now()
             WHERE tenant_id = :tid AND id = :mid",
            [':mo' => $motionId, ':tid' => $tenantId, ':mid' => $meetingId]
        );
    }

    /**
     * Lock meeting row pour eviter les conflits concurrents (FOR UPDATE).
     */
    public function lockForUpdate(string $meetingId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT id, validated_at, status FROM meetings
             WHERE tenant_id = :tid AND id = :id FOR UPDATE",
            [':tid' => $tenantId, ':id' => $meetingId]
        );
    }

    // =========================================================================
    // STATISTIQUES / AGGREGATIONS
    // =========================================================================

    /**
     * Compteurs de motions pour une seance (pour meeting_status).
     */
    public function countMotionStats(string $meetingId): array
    {
        $row = $this->selectOne(
            "SELECT
              COUNT(*) AS total_motions,
              SUM(CASE WHEN mo.closed_at IS NULL THEN 1 ELSE 0 END) AS open_motions,
              SUM(CASE WHEN mo.closed_at IS NOT NULL THEN 1 ELSE 0 END) AS closed_motions,
              SUM(CASE WHEN mo.closed_at IS NOT NULL
                        AND (mo.manual_total IS NULL OR mo.manual_total <= 0)
                   THEN 1 ELSE 0 END) AS closed_without_tally
             FROM motions mo WHERE mo.meeting_id = :meeting_id",
            [':meeting_id' => $meetingId]
        );
        return $row ?: ['total_motions' => 0, 'open_motions' => 0, 'closed_motions' => 0, 'closed_without_tally' => 0];
    }

    /**
     * Nombre total de membres actifs pour un tenant.
     */
    public function countActiveMembers(string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM members WHERE tenant_id = :tid AND is_active = true",
            [':tid' => $tenantId]
        ) ?? 0);
    }

    /**
     * Compteurs de presences pour une seance (meeting_summary).
     */
    public function countPresent(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM attendances
             WHERE meeting_id = :mid AND mode IN ('present', 'remote')",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countProxy(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM attendances WHERE meeting_id = :mid AND mode = 'proxy'",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    /**
     * Compteurs motions pour meeting_summary.
     */
    public function countMotions(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM motions WHERE meeting_id = :mid",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countClosedMotions(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM motions WHERE meeting_id = :mid AND closed_at IS NOT NULL",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countOpenMotions(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM motions
             WHERE meeting_id = :mid AND opened_at IS NOT NULL AND closed_at IS NULL",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countAdoptedMotions(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM motions WHERE meeting_id = :mid AND decision = 'adopted'",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countRejectedMotions(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM motions WHERE meeting_id = :mid AND decision = 'rejected'",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countBallots(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM ballots b
             JOIN motions m ON m.id = b.motion_id
             WHERE m.meeting_id = :mid",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function sumBallotWeight(string $meetingId): float
    {
        return (float)($this->scalar(
            "SELECT COALESCE(SUM(b.weight), 0) FROM ballots b
             JOIN motions m ON m.id = b.motion_id
             WHERE m.meeting_id = :mid",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countProxies(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM proxies WHERE meeting_id = :mid",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countIncidents(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM audit_events
             WHERE resource_type = 'meeting' AND resource_id = :mid
               AND action LIKE '%incident%'",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countManualVotes(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM ballots b
             JOIN motions m ON m.id = b.motion_id
             WHERE m.meeting_id = :mid AND b.source = 'manual'",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    // =========================================================================
    // REPORTS / AUDIT
    // =========================================================================

    /**
     * Stocke le PV HTML dans meeting_reports.
     */
    public function storePVHtml(string $meetingId, string $html): void
    {
        $this->execute(
            "INSERT INTO meeting_reports(meeting_id, html, created_at, updated_at)
             VALUES (:mid, :html, NOW(), NOW())
             ON CONFLICT (meeting_id) DO UPDATE SET html = EXCLUDED.html, updated_at = NOW()",
            [':mid' => $meetingId, ':html' => $html]
        );
    }

    /**
     * Trouve le PV HTML snapshot.
     */
    public function findPVSnapshot(string $meetingId): ?array
    {
        return $this->selectOne(
            "SELECT html FROM meeting_reports WHERE meeting_id = :mid",
            [':mid' => $meetingId]
        );
    }

    /**
     * Audit events pour une seance.
     */
    public function listAuditEvents(string $meetingId, string $tenantId, int $limit = 200, string $order = 'DESC'): array
    {
        $order = ($order === 'ASC') ? 'ASC' : 'DESC';
        return $this->selectAll(
            "SELECT id, action, resource_type, resource_id, payload, created_at
             FROM audit_events
             WHERE tenant_id = :tid
               AND ((resource_type = 'meeting' AND resource_id = :mid)
                    OR (resource_type = 'motion' AND resource_id IN (
                        SELECT id FROM motions WHERE meeting_id = :mid2)))
             ORDER BY created_at {$order}
             LIMIT " . max(1, $limit),
            [':tid' => $tenantId, ':mid' => $meetingId, ':mid2' => $meetingId]
        );
    }

    /**
     * Audit events filtres pour une seance (pour operator_audit_events).
     */
    public function listAuditEventsFiltered(
        string $tenantId,
        string $meetingId,
        int $limit = 200,
        string $resourceType = '',
        string $action = '',
        string $q = ''
    ): array {
        $where = "WHERE tenant_id = ? AND (
            (resource_type = 'meeting' AND resource_id = ?)
            OR
            (resource_type = 'motion' AND resource_id IN (SELECT id FROM motions WHERE meeting_id = ?))
        )";
        $params = [$tenantId, $meetingId, $meetingId];

        if ($resourceType !== '') {
            $where .= " AND resource_type = ?";
            $params[] = $resourceType;
        }
        if ($action !== '') {
            $where .= " AND action ILIKE ?";
            $params[] = "%" . $action . "%";
        }
        if ($q !== '') {
            $where .= " AND (action ILIKE ? OR resource_id ILIKE ? OR payload::text ILIKE ?)";
            $params[] = "%" . $q . "%";
            $params[] = "%" . $q . "%";
            $params[] = "%" . $q . "%";
        }

        return $this->selectAll(
            "SELECT id, action, resource_type, resource_id, payload, created_at
             FROM audit_events
             {$where}
             ORDER BY created_at DESC
             LIMIT " . max(1, min($limit, 500)),
            $params
        );
    }

    /**
     * Verifie qu'une politique de vote existe pour un tenant.
     */
    public function votePolicyExists(string $policyId, string $tenantId): bool
    {
        return (bool)$this->scalar(
            "SELECT 1 FROM vote_policies WHERE tenant_id = :tid AND id = :id",
            [':tid' => $tenantId, ':id' => $policyId]
        );
    }

    /**
     * Verifie qu'une politique de quorum existe pour un tenant.
     */
    public function quorumPolicyExists(string $policyId, string $tenantId): bool
    {
        return (bool)$this->scalar(
            "SELECT 1 FROM quorum_policies WHERE tenant_id = :tid AND id = :id",
            [':tid' => $tenantId, ':id' => $policyId]
        );
    }
}
